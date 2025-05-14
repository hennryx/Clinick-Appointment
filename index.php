<?php
require_once './config/database.php';
require_once './includes/auth.php';
require_once './config/config.php';

checkAuth();

include_once './views/layout/header.php';

try {
    $patientStmt = $connect->query("SELECT COUNT(*) FROM patients WHERE delete_status = 0");
    $totalPatients = $patientStmt->fetchColumn();
    
    $pendingStmt = $connect->query("SELECT COUNT(*) FROM pending_requests WHERE status = 'Pending'");
    $pendingRequests = $pendingStmt->fetchColumn();
    
    $approvedStmt = $connect->query("SELECT COUNT(*) FROM request_list WHERE status = 'Approved'");
    $approvedRequests = $approvedStmt->fetchColumn();
    
    $completedStmt = $connect->query("SELECT COUNT(*) FROM test_records WHERE status = 'Completed'");
    $completedTests = $completedStmt->fetchColumn();
    
    $userStmt = $connect->query("SELECT COUNT(*) FROM users");
    $totalUsers = $userStmt->fetchColumn();
    
    $recentStmt = $connect->query("SELECT * FROM patients WHERE delete_status = 0 ORDER BY patient_id DESC LIMIT 5");
    $recentPatients = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $recentTestsStmt = $connect->query("
        SELECT tr.*, p.full_name as patient_name 
        FROM test_records tr
        JOIN patients p ON tr.patient_id = p.patient_id
        ORDER BY tr.test_date DESC 
        LIMIT 5
    ");
    $recentTests = $recentTestsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $totalPatients = 0;
    $pendingRequests = 0;
    $approvedRequests = 0;
    $completedTests = 0;
    $totalUsers = 0;
    $recentPatients = [];
    $recentTests = [];
}
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
                <p class="text-muted">
                    Dashboard Overview | <?= date('l, F j, Y') ?>
                </p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Patients</h6>
                                <h2 class="mt-2 mb-0"><?= $totalPatients ?></h2>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-people-fill display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="<?= BASE_PATH ?>/views/patients/patients.php" class="text-white text-decoration-none small">
                            View Details <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Pending Requests</h6>
                                <h2 class="mt-2 mb-0"><?= $pendingRequests ?></h2>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-hourglass-split display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="<?= BASE_PATH ?>/views/requests/pending.php" class="text-dark text-decoration-none small">
                            View Details <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Tests Completed</h6>
                                <h2 class="mt-2 mb-0"><?= $completedTests ?></h2>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-clipboard2-check-fill display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="text-white text-decoration-none small">
                            View Details <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">System Users</h6>
                                <h2 class="mt-2 mb-0"><?= $totalUsers ?></h2>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-person-badge-fill display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="<?= BASE_PATH ?>/views/auth/add_user.php" class="text-white text-decoration-none small">
                                Add User <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-white small">
                                Active Staff Members
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="<?= BASE_PATH ?>/views/patients/patients.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                    <i class="bi bi-person-plus-fill display-6 mb-2"></i>
                                    <span>Add New Patient</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?= BASE_PATH ?>/views/requests/pending.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                    <i class="bi bi-clipboard-plus display-6 mb-2"></i>
                                    <span>Review Pending Requests</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                    <i class="bi bi-file-earmark-medical display-6 mb-2"></i>
                                    <span>Add Test Result</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?= BASE_PATH ?>/views/reports/reports.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                    <i class="bi bi-graph-up display-6 mb-2"></i>
                                    <span>Generate Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Patients</h5>
                        <a href="<?= BASE_PATH ?>/views/patients/patients.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Gender</th>
                                        <th>Age</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentPatients)): ?>
                                        <?php foreach ($recentPatients as $patient): ?>
                                            <tr>
                                                <td><?= $patient['patient_id'] ?></td>
                                                <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                <td><?= htmlspecialchars($patient['gender']) ?></td>
                                                <td><?= htmlspecialchars($patient['age']) ?></td>
                                                <td>
                                                    <a href="<?= BASE_PATH ?>/views/patients/view.php?id=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent patients found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Test Results</h5>
                        <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentTests)): ?>
                                        <?php foreach ($recentTests as $test): ?>
                                            <tr>
                                                <td><?= date('M d', strtotime($test['test_date'])) ?></td>
                                                <td><?= htmlspecialchars($test['patient_name']) ?></td>
                                                <td><?= htmlspecialchars($test['test_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $test['status'] == 'Completed' ? 'success' : ($test['status'] == 'In Progress' ? 'info' : 'warning') ?>">
                                                        <?= htmlspecialchars($test['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= BASE_PATH ?>/views/tests/view_result.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent test results found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card-icon {
    opacity: 0.4;
}
.welcome-message {
    font-size: 1.8rem;
    font-weight: 600;
    color: #333;
}
</style>

<?php include_once './views/layout/footer.php'; ?>