<?php
session_start();
if (isset($_SESSION['username'])) {
    header("Location: /index.php");
    exit();
}
require_once '../../config/config.php';

$errMsg = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
unset($_SESSION['login_error']);

require_once '../../config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Klinika Papaya Laboratory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .login-container {
            max-width: 450px;
            margin: 80px auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .login-header {
            background-color: #feb1b7;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
            background-color: white;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #feb1b7;
            padding: 3px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
            margin-top: 10px;
        }
        .login-btn {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .login-btn:hover {
            background-color: #bb2d3b;
            border-color: #bb2d3b;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <div class="logo-container">
                    <img src="../../assets/image/profile.jpg" alt="Klinika Papaya Logo">
                    <div class="logo-text">DIAGNOSTIC LABORATORY AND CLINIC</div>
                </div>
            </div>
            
            <div class="login-body">
                <h4 class="text-center mb-4">Login to Your Account</h4>
                
                <?php if($errMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($errMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">Remember Me</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary login-btn">Login</button>
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <small class="text-muted">© 2025 Klinika Papaya. All rights reserved.</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
    
    document.getElementById('loginForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        
        const loginBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = loginBtn.innerHTML;
        
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';
        
        try {
            const formData = new FormData(this);
            
            const response = await fetch('../../controllers/AuthController.php?action=login', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: result.message || 'Login successful',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "<?= BASE_PATH ?>" + result.redirect || '<?= BASE_PATH ?>/index.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: result.message || 'Invalid username or password',
                    confirmButtonColor: '#dc3545'
                });
                
                loginBtn.disabled = false;
                loginBtn.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('Error:', error);
            
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An unexpected error occurred. Please try again.',
                confirmButtonColor: '#dc3545'
            });
            
            loginBtn.disabled = false;
            loginBtn.innerHTML = originalBtnText;
        }
    });
    </script>
</body>
</html>