<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
checkAdmin(); 
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2>Manage Tests</h2>
        <p>This is a placeholder for the test management page.</p>
        
        <div class="d-flex my-3">
            <a href="<?= BASE_PATH ?>/views/setup/index.php" class="btn btn-secondary">Back to Setup</a>
        </div>
    </div>
</div>

<?php include_once '../../views/layout/footer.php'; ?>