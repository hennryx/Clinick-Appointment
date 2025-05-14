<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mydatabase');

// Establish database connection using PDO
try {
    $connect = new PDO("mysql:host=".DB_HOST."; dbname=".DB_NAME, DB_USER, DB_PASS);
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log error to file
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Set error message in session
    $_SESSION['flash_message'] = "Database connection failed. Please contact the administrator.";
    $_SESSION['flash_type'] = "error";
    
    // If this is not the error page, redirect to it
    if (basename($_SERVER['PHP_SELF']) != 'error.php') {
        header("Location: /error.php");
        exit();
    }
}