<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../config/config.php';

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Get user data
$userId = $_SESSION['user_id'] ?? null;

try {
    $stmt = $connect->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // If user not found, redirect to login
        $_SESSION['flash_message'] = "User not found. Please login again.";
        $_SESSION['flash_type'] = "error";
        header("Location: /views/auth/logout.php");
        exit();
    }
} catch (PDOException $e) {
    // Log error and set flash message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading user profile. Please try again.";
    $_SESSION['flash_type'] = "error";
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">User Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4 text-center">
                                <div class="profile-img-container mb-3">
                                    <img src="<?= BASE_PATH ?>/assets/image/profile.jpg" alt="Profile Image" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                </div>
                                <h5><?= htmlspecialchars($user['username']) ?></h5>
                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'info' ?>"><?= ucfirst(htmlspecialchars($user['role'])) ?></span>
                            </div>
                            <div class="col-md-8">
                                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">Profile Information</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">Change Password</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content p-3" id="profileTabsContent">
                                    <!-- Profile Information Tab -->
                                    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                                        <form id="updateProfileForm">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                            </div>
                                            
                                            <?php if ($user['role'] === 'admin'): ?>
                                            <div class="mb-3">
                                                <label for="role" class="form-label">Role</label>
                                                <select class="form-select" id="role" name="role">
                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <button type="submit" class="btn btn-primary">Update Profile</button>
                                        </form>
                                    </div>
                                    
                                    <!-- Change Password Tab -->
                                    <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                        <form id="changePasswordForm">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <div class="form-text">Password must be at least 8 characters long</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Change Password</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update Profile Form Submission
document.getElementById('updateProfileForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable button and show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/AuthController.php?action=update_profile', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'Profile updated successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Reload the page to reflect changes
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to update profile'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
    } finally {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    }
});

// Change Password Form Submission
document.getElementById('changePasswordForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    
    // Validate password match
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'New passwords do not match'
        });
        return;
    }
    
    // Validate password length
    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Password must be at least 8 characters long'
        });
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable button and show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Changing Password...';
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/AuthController.php?action=change_password', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'Password changed successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Clear the form
                this.reset();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to change password'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
    } finally {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    }
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>