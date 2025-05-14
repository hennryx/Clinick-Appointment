<?php
require_once __DIR__ . '/../../config/config.php';
?>
<div class="sidebar">
    <div class="logo">
        <img src="<?= BASE_PATH ?>/assets/image/profile.jpg" alt="Logo">
        <h4 class="mt-2 text-center text-white">Klinika Papaya</h4>
    </div>
    
    <a href="<?= BASE_PATH ?>/index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= BASE_PATH ?>/views/patients/patients.php" class="<?= $current_page == 'patients.php' && dirname($_SERVER['PHP_SELF']) == '/views/patients' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Patients
    </a>
    <a href="<?= BASE_PATH ?>/views/requests/pending.php" class="<?= $current_page == 'pending.php' ? 'active' : '' ?>">
        <i class="bi bi-hourglass-split"></i> Pending Requests
    </a>
    <a href="<?= BASE_PATH ?>/views/requests/approved.php" class="<?= $current_page == 'approved.php' ? 'active' : '' ?>">
        <i class="bi bi-clipboard-check"></i> Approved Requests
    </a>
    <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="<?= $current_page == 'summary.php' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-medical"></i> Test Summary
    </a>
    <a href="<?= BASE_PATH ?>/views/tests/results.php" class="<?= $current_page == 'results.php' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Test Results
    </a>
    <a href="<?= BASE_PATH ?>/views/records/index.php" class="<?= $current_page == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/views/records' ? 'active' : '' ?>">
        <i class="bi bi-folder-fill"></i> Records
    </a>
    <a href="<?= BASE_PATH ?>/views/reports/reports.php" class="<?= $current_page == 'reports.php' && dirname($_SERVER['PHP_SELF']) == '/views/reports' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart"></i> Reports
    </a>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <a href="<?= BASE_PATH ?>/views/setup/index.php" class="<?= $current_page == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/views/setup' ? 'active' : '' ?>">
        <i class="bi bi-gear-fill"></i> Setup
    </a>
    <?php endif; ?>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    background-color: #feb1b7;
    padding-top: 20px;
    overflow-y: auto;
    z-index: 100;
}

.sidebar .logo {
    text-align: center;
    margin-bottom: 20px;
    padding: 0 15px;
}

.sidebar .logo img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
}

.sidebar a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    font-size: 16px;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
    margin: 4px 8px;
    border-radius: 5px;
}

.sidebar a:hover, .sidebar a.active {
    background-color: #dc3545;
    color: white;
    transform: translateX(5px);
}

.sidebar a i {
    margin-right: 10px;
    font-size: 1.2rem;
}
</style>