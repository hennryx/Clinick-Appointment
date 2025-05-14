<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SESSION['role'] !== 'admin') {
    $_SESSION['flash_message'] = "Unauthorized access. Admin privileges required.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . BASE_PATH . "/index.php");
    exit();
}

include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4">System Setup</h2>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Test Masterlist</h5>
                    </div>
                    <div class="card-body">
                        <p>Configure available diagnostic tests, reference ranges, and pricing.</p>
                        <div class="d-flex flex-column">
                            <a href="<?= BASE_PATH ?>/views/setup/tests.php" class="btn btn-outline-primary mb-2">Manage Tests</a>
                            <a href="<?= BASE_PATH ?>/views/setup/sections.php" class="btn btn-outline-primary mb-2">Manage Sections</a>
                            <a href="<?= BASE_PATH ?>/views/setup/pricing.php" class="btn btn-outline-primary">Manage Pricing</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Management -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">User Management</h5>
                    </div>
                    <div class="card-body">
                        <p>Manage system users, roles, and permissions.</p>
                        <div class="d-flex flex-column">
                            <a href="<?= BASE_PATH ?>/views/auth/add_user.php" class="btn btn-outline-primary mb-2">Add New User</a>
                            <a href="<?= BASE_PATH ?>/views/setup/roles.php" class="btn btn-outline-primary mb-2">Manage Roles</a>
                            <a href="<?= BASE_PATH ?>/views/setup/user_list.php" class="btn btn-outline-primary">View All Users</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reagent Management -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Reagent Management</h5>
                    </div>
                    <div class="card-body">
                        <p>Track reagent inventory, usage, and set up alerts for low stock.</p>
                        <div class="d-flex flex-column">
                            <a href="<?= BASE_PATH ?>/views/setup/reagents.php" class="btn btn-outline-primary mb-2">Manage Reagents</a>
                            <a href="<?= BASE_PATH ?>/views/setup/consumption.php" class="btn btn-outline-primary">View Consumption</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Settings -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">System Settings</h5>
                    </div>
                    <div class="card-body">
                        <p>Configure laboratory information, report templates, and system preferences.</p>
                        <div class="d-flex flex-column">
                            <a href="<?= BASE_PATH ?>/views/setup/lab_info.php" class="btn btn-outline-primary mb-2">Laboratory Information</a>
                            <a href="<?= BASE_PATH ?>/views/setup/templates.php" class="btn btn-outline-primary mb-2">Report Templates</a>
                            <a href="<?= BASE_PATH ?>/views/setup/backup.php" class="btn btn-outline-primary">Database Backup</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Version:</strong> 1.0.0</p>
                        <p><strong>Last Update:</strong> May 14, 2025</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
                        <p><strong>Database:</strong> MySQL 10.4.32-MariaDB</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
                        <p><strong>License:</strong> Klinika Papaya</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../views/layout/footer.php'; ?>