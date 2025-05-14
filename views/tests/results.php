<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Get current page for pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

try {
    // Get total count of test results
    $countStmt = $connect->query("SELECT COUNT(*) FROM test_results");
    $totalResults = $countStmt->fetchColumn();
    $totalPages = ceil($totalResults / $recordsPerPage);
    
    // Get test results for current page
    $stmt = $connect->prepare("
        SELECT tr.*, p.full_name 
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.patient_id
        ORDER BY tr.test_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errMsg = "Database error: " . $e->getMessage();
    error_log($errMsg);
    $testResults = [];
    $totalResults = 0;
    $totalPages = 1;
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Test Results</h2>
                
                <!-- Search and Export Options -->
                <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by patient name, test type...">
                        <button class="btn btn-primary" type="button">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                    <div>
                        <button class="btn btn-success">
                            <i class="bi bi-file-earmark-excel"></i> Export to Excel
                        </button>
                        <button class="btn btn-danger">
                            <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                        </button>
                    </div>
                </div>
                
                <!-- Results Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Test Name</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($testResults)): ?>
                                <?php foreach ($testResults as $result): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($result['test_date'])) ?></td>
                                        <td><?= htmlspecialchars($result['patient_id']) ?></td>
                                        <td><?= htmlspecialchars($result['full_name']) ?></td>
                                        <td><?= htmlspecialchars($result['test_name']) ?></td>
                                        <td>₱<?= number_format($result['price'], 2) ?></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>/views/tests/view_result.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="<?= BASE_PATH ?>/views/reports/print_result.php?id=<?= $result['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No test results found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= BASE_PATH ?>/views/tests/results.php?page=<?= $page-1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= BASE_PATH ?>/views/tests/results.php?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= BASE_PATH ?>/views/tests/results.php?page=<?= $page+1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>