<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['username']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: " . BASE_PATH . "/views/auth/login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klinika Papaya - Laboratory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/dashboard.css">
    <style>
        body {
            background-color: #f1f3f5;
            padding-top: 0px;
        }
        
        header {
            background-color: #f8f9fa;
            padding: 10px 30px;
            text-align: right;
            position: fixed;
            width: calc(100% - 250px);
            top: 0;
            z-index: 1000;
            left: 250px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        header a {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
        }

        .main-content {
            margin-left: 250px;
            padding: 80px 50px 50px;
            width: calc(100% - 250px);
        }
        
        .table-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <header>
        <div class="dropdown">
            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                <li><a class="dropdown-item" href="<?= BASE_PATH ?>/views/auth/profile.php">
                    <i class="bi bi-pencil-square"></i> Edit Profile
                </a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a class="dropdown-item" href="<?= BASE_PATH ?>/views/auth/add_user.php">
                    <i class="bi bi-person-plus"></i> Add User
                </a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_PATH ?>/views/auth/logout.php" id="logoutBtn">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a></li>
            </ul>
        </div>
    </header>

    <?php include __DIR__ . '/sidebar.php'; ?>