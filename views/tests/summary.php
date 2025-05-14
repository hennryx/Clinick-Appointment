<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/config.php';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Get search term if provided
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Build query based on whether search term is provided
    $countQuery = "SELECT COUNT(*) FROM test_records";
    $testQuery = "SELECT * FROM test_records";
    
    if (!empty($searchTerm)) {
        $searchWhere = " WHERE test_name LIKE :search OR patient_name LIKE :search OR sample_id LIKE :search";
        $countQuery .= $searchWhere;
        $testQuery .= $searchWhere;
    }
    
    $testQuery .= " ORDER BY test_date DESC LIMIT :limit OFFSET :offset";
    
    // Get total count
    $countStmt = $connect->prepare($countQuery);
    if (!empty($searchTerm)) {
        $searchParam = "%{$searchTerm}%";
        $countStmt->bindParam(':search', $searchParam);
    }
    $countStmt->execute();
    $totalTests = $countStmt->fetchColumn();
    $totalPages = ceil($totalTests / $recordsPerPage);
    
    // Get tests for current page
    $stmt = $connect->prepare($testQuery);
    if (!empty($searchTerm)) {
        $searchParam = "%{$searchTerm}%";
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $testSummaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errMsg = "Database error: " . $e->getMessage();
    error_log($errMsg);
    $testSummaries = [];
    $totalTests = 0;
    $totalPages = 1;
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="test-summary-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Test Summary</h2>
                <div>
                    <a href="/views/tests/add_test.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Test
                    </a>
                </div>
            </div>

            <!-- Search Bar -->
            <form method="GET" class="d-flex mb-4">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search by Test Name, Patient, or Sample ID" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="summary.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Test Summary Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Tests</h5>
                            <p class="card-text display-6"><?= $totalTests ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <?php
                            try {
                                $completedStmt = $connect->query("SELECT COUNT(*) FROM test_records WHERE status = 'Completed'");
                                $completedCount = $completedStmt->fetchColumn();
                            } catch (PDOException $e) {
                                $completedCount = 0;
                            }
                            ?>
                            <p class="card-text display-6"><?= $completedCount ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">In Progress</h5>
                            <?php
                            try {
                                $inProgressStmt = $connect->query("SELECT COUNT(*) FROM test_records WHERE status = 'In Progress'");
                                $inProgressCount = $inProgressStmt->fetchColumn();
                            } catch (PDOException $e) {
                                $inProgressCount = 0;
                            }
                            ?>
                            <p class="card-text display-6"><?= $inProgressCount ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Pending</h5>
                            <?php
                            try {
                                $pendingStmt = $connect->query("SELECT COUNT(*) FROM test_records WHERE status = 'Pending'");
                                $pendingCount = $pendingStmt->fetchColumn();
                            } catch (PDOException $e) {
                                $pendingCount = 0;
                            }
                            ?>
                            <p class="card-text display-6"><?= $pendingCount ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Message Display -->
            <?php if(isset($errMsg)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($errMsg); ?>
                </div>
            <?php endif; ?>

            <!-- Test Summary Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Test Date</th>
                            <th>Sample ID</th>
                            <th>Patient</th>
                            <th>Test Name</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($testSummaries) > 0): ?>
                            <?php foreach ($testSummaries as $test): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($test['test_date'])) ?></td>
                                    <td><?= htmlspecialchars($test['sample_id']) ?></td>
                                    <td><?= htmlspecialchars($test['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($test['test_name']) ?></td>
                                    <td><?= htmlspecialchars($test['section']) ?></td>
                                    <td>
                                        <?php if ($test['status'] == 'Completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($test['status'] == 'In Progress'): ?>
                                            <span class="badge bg-info">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(substr($test['result'] ?? 'Not available', 0, 30)) ?>
                                        <?= strlen($test['result'] ?? '') > 30 ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_result.php?id=<?= $test['id'] ?>" class="btn btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($test['status'] != 'Completed'): ?>
                                                <a href="add_result.php?id=<?= $test['id'] ?>" class="btn btn-success">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-danger delete-test" data-id="<?= $test['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <a href="<?= BASE_PATH ?>/views/reports/print_result.php?id=<?= $test['id'] ?>" class="btn btn-info">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No test summaries available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Delete Test functionality
document.querySelectorAll('.delete-test').forEach(button => {
    button.addEventListener('click', function() {
        const testId = this.getAttribute('data-id');
        
        Swal.fire({
            title: 'Delete Test',
            text: 'Are you sure you want to delete this test? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteTest(testId);
            }
        });
    });
});

// Function to delete test
async function deleteTest(testId) {
    try {
        const formData = new FormData();
        formData.append('test_id', testId);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/test_controller.php?action=delete', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: result.message || 'Test deleted successfully',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Reload the page to refresh the list
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to delete test'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
        console.error('Error:', error);
    }
}
</script>

<?php include_once '../../views/layout/footer.php'; ?>