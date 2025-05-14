<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class AuthController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'update_profile':
                $this->updateProfile();
                break;
            case 'change_password':
                $this->changePassword();
                break;
            case 'add_user':
                $this->addUser();
                break;
            case 'delete_user':
                $this->deleteUser();
                break;
            default:
                $this->respondWithError('Invalid action');
        }
    }

    private function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondWithError("Invalid request method");
            return;
        }
        
        if (!isset($_POST['username']) || empty($_POST['username']) || !isset($_POST['password']) || empty($_POST['password'])) {
            $this->respondWithError("Username and password are required");
            return;
        }
        
        $username = $_POST['username'];
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->respondWithError("Invalid username or password");
                return;
            }
            
            if ($password !== $user['password']) {
                $this->respondWithError("Invalid username or password");
                return;
            }
            
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($rememberMe) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 60*60*24*30, '/'); // 30 days
                
                $stmt = $this->db->prepare("
                    UPDATE users SET remember_token = :token WHERE id = :id
                ");
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
            logActivity('User login', 'users', $user['id'], null, null);
            
            $redirect = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/index.php';
            unset($_SESSION['redirect_url']);
            
            $this->respondWithSuccess('Login successful', [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ],
                'redirect' => $redirect
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Login failed. Please try again later." . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Login failed. Please try again later.");
        }
    }
    
    private function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log the activity if user is logged in
        if (isset($_SESSION['user_id'])) {
            logActivity('User logout', 'users', $_SESSION['user_id'], null, null);
        }
        
        // Clear all session variables
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Delete remember token cookie
        setcookie('remember_token', '', time() - 3600, '/');
        
        // Destroy session
        session_destroy();
        
        // Redirect to login page
        header("Location: /views/auth/login.php");
        exit;
    }
    
    /**
     * Update user profile
     */
    private function updateProfile() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Validate required fields
        if (!isset($_POST['user_id']) || empty($_POST['user_id']) || !isset($_POST['username']) || empty($_POST['username'])) {
            $this->respondWithError("User ID and username are required");
            return;
        }
        
        // Check if the user is updating their own profile or if they are an admin
        if ($_SESSION['user_id'] != $_POST['user_id'] && $_SESSION['role'] !== 'admin') {
            $this->respondWithError("You are not authorized to update this profile");
            return;
        }
        
        $userId = $_POST['user_id'];
        $username = $_POST['username'];
        $role = isset($_POST['role']) && $_SESSION['role'] === 'admin' ? $_POST['role'] : null;
        
        try {
            // Get current user data for logging
            $getStmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
            $getStmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $getStmt->execute();
            $oldData = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                $this->respondWithError("User not found");
                return;
            }
            
            // Check if username already exists
            if ($username !== $oldData['username']) {
                $checkStmt = $this->db->prepare("
                    SELECT COUNT(*) FROM users WHERE username = :username AND id != :id
                ");
                $checkStmt->bindParam(':username', $username);
                $checkStmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    $this->respondWithError("Username already exists");
                    return;
                }
            }
            
            // Prepare SQL query based on available data
            $sql = "UPDATE users SET username = :username";
            $params = [':username' => $username, ':id' => $userId];
            
            if ($role !== null) {
                $sql .= ", role = :role";
                $params[':role'] = $role;
            }
            
            $sql .= " WHERE id = :id";
            
            // Update user
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Update session if user is updating their own profile
            if ($_SESSION['user_id'] == $userId) {
                $_SESSION['username'] = $username;
                if ($role !== null) {
                    $_SESSION['role'] = $role;
                }
            }
            
            // Log the activity
            logActivity('Updated user profile', 'users', $userId, 
                      json_encode(['username' => $oldData['username'], 'role' => $oldData['role']]), 
                      json_encode(['username' => $username, 'role' => $role ?? $oldData['role']]));
            
            $this->respondWithSuccess('Profile updated successfully', [
                'user' => [
                    'id' => $userId,
                    'username' => $username,
                    'role' => $role ?? $oldData['role']
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to update profile: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to update profile: " . $e->getMessage());
        }
    }
    
    /**
     * Change user password
     */
    private function changePassword() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Validate required fields
        if (!isset($_POST['user_id']) || empty($_POST['user_id']) || 
            !isset($_POST['current_password']) || empty($_POST['current_password']) || 
            !isset($_POST['new_password']) || empty($_POST['new_password']) || 
            !isset($_POST['confirm_password']) || empty($_POST['confirm_password'])) {
            $this->respondWithError("All fields are required");
            return;
        }
        
        // Check if the user is changing their own password or if they are an admin
        if ($_SESSION['user_id'] != $_POST['user_id'] && $_SESSION['role'] !== 'admin') {
            $this->respondWithError("You are not authorized to change this password");
            return;
        }
        
        // Check if new password and confirm password match
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $this->respondWithError("New passwords do not match");
            return;
        }
        
        // Validate password length
        if (strlen($_POST['new_password']) < 8) {
            $this->respondWithError("Password must be at least 8 characters long");
            return;
        }
        
        $userId = $_POST['user_id'];
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        
        try {
            // Get current user data
            $getStmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
            $getStmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $getStmt->execute();
            $user = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->respondWithError("User not found");
                return;
            }
            
            // Verify current password
            // Note: For simplicity using direct comparison, but should use password_verify with hashed passwords
            if ($currentPassword !== $user['password'] && $_SESSION['role'] !== 'admin') {
                $this->respondWithError("Current password is incorrect");
                return;
            }
            
            // Update password
            // Note: For simplicity using direct assignment, but should use password_hash
            $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->bindParam(':password', $newPassword);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the activity
            logActivity('Changed password', 'users', $userId, null, null);
            
            $this->respondWithSuccess('Password changed successfully');
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to change password: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to change password: " . $e->getMessage());
        }
    }
    
    /**
     * Add a new user
     */
    private function addUser() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Check if user is admin
        if ($_SESSION['role'] !== 'admin') {
            $this->respondWithError("Only administrators can add users");
            return;
        }
        
        // Validate required fields
        if (!isset($_POST['username']) || empty($_POST['username']) || 
            !isset($_POST['password']) || empty($_POST['password']) || 
            !isset($_POST['confirm_password']) || empty($_POST['confirm_password']) || 
            !isset($_POST['role']) || empty($_POST['role'])) {
            $this->respondWithError("All fields are required");
            return;
        }
        
        // Check if passwords match
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $this->respondWithError("Passwords do not match");
            return;
        }
        
        // Validate password length
        if (strlen($_POST['password']) < 8) {
            $this->respondWithError("Password must be at least 8 characters long");
            return;
        }
        
        // Validate username length
        if (strlen($_POST['username']) < 4) {
            $this->respondWithError("Username must be at least 4 characters long");
            return;
        }
        
        // Validate role
        if (!in_array($_POST['role'], ['admin', 'staff'])) {
            $this->respondWithError("Invalid role");
            return;
        }
        
        $username = $_POST['username'];
        $password = $_POST['password']; // Should use password_hash in production
        $role = $_POST['role'];
        
        try {
            // Check if username already exists
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkStmt->bindParam(':username', $username);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->respondWithError("Username already exists");
                return;
            }
            
            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, role) 
                VALUES (:username, :password, :role)
            ");
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':role', $role);
            
            $stmt->execute();
            
            $newUserId = $this->db->lastInsertId();
            
            // Log the activity
            logActivity('Added new user', 'users', $newUserId, null, json_encode([
                'username' => $username,
                'role' => $role
            ]));
            
            $this->respondWithSuccess('User added successfully', [
                'user' => [
                    'id' => $newUserId,
                    'username' => $username,
                    'role' => $role
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to add user: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to add user: " . $e->getMessage());
        }
    }
    
    /**
     * Delete a user
     */
    private function deleteUser() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Check if user is admin
        if ($_SESSION['role'] !== 'admin') {
            $this->respondWithError("Only administrators can delete users");
            return;
        }
        
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            $this->respondWithError("User ID is required");
            return;
        }
        
        $userId = $_POST['user_id'];
        
        // Prevent self-deletion
        if ($userId == $_SESSION['user_id']) {
            $this->respondWithError("You cannot delete your own account");
            return;
        }
        
        try {
            // Get user data for logging
            $getStmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
            $getStmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $getStmt->execute();
            $user = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->respondWithError("User not found");
                return;
            }
            
            // Delete user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the activity
            logActivity('Deleted user', 'users', $userId, json_encode($user), null);
            
            $this->respondWithSuccess('User deleted successfully');
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to delete user: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to delete user: " . $e->getMessage());
        }
    }
    
    /**
     * Respond with a success message
     */
    private function respondWithSuccess($message, $data = []) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
        exit;
    }
    
    /**
     * Respond with an error message
     */
    private function respondWithError($message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

// Initialize and handle the request
$authController = new AuthController($connect);
$authController->handleRequest();