<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

require_once '../../config/config.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "Unauthorized access. Admin privileges required.";
    $_SESSION['flash_type'] = "error";
    header("Location: /index.php");
    exit();
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Add New User</h4>
                    </div>
                    <div class="card-body">
                        <form id="addUserForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="form-text">Username must be unique and at least 4 characters long</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">User Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_PATH ?>/index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Add User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
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

// Add User Form Submission
document.getElementById('addUserForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    
    // Validate password match
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Passwords do not match'
        });
        return;
    }
    
    // Validate password length
    if (password.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Password must be at least 8 characters long'
        });
        return;
    }
    
    // Validate username length
    const username = document.getElementById('username').value;
    if (username.length < 4) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Username must be at least 4 characters long'
        });
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable button and show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding User...';
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch('/controllers/AuthController.php?action=add_user', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'User added successfully',
                confirmButtonText: 'Go to Dashboard'
            }).then(() => {
                window.location.href = '/index.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to add user'
            });
            
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    } catch (error) {
        console.error('Error:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
        
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    }
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>