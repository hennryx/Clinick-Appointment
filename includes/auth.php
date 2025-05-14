<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

function checkAuth() {
    if (!isset($_SESSION['username'])) {
        // Store the requested URL for redirection after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        echo 'base path' . BASE_PATH ;
        
        header("Location: " . BASE_PATH  . "../views/auth/login.php");
        exit();
    }
}

/**
 * Check if user has admin role
 * If not, redirect to dashboard with error message
 * 
 * @return void
 */
function checkAdmin() {
    checkAuth(); // First check if user is logged in
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $_SESSION['flash_message'] = "You don't have permission to access this page. Admin privileges required.";
        $_SESSION['flash_type'] = "error";

        header("Location: " . BASE_PATH . "index.php");
        exit();
    }
}

/**
 * Generate CSRF token and store in session
 * 
 * @return string The generated CSRF token
 */
function generateCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate CSRF token against the one stored in session
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}