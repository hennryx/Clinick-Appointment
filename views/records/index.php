<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Get current page for pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Get section filter if set
$sectionFilter = isset($_GET['section']) ? $_GET['section'] : '';

try {
    // Base query
    $countQuery = "SELECT COUNT(*) FROM test_records";
    $recordsQuery = "SELECT tr.*, p.full_name 
                     FROM test_records tr
                     JOIN patients p ON tr.patient_id = p.patient_id";
    
    // Add section filter if selected
    if (!empty($sectionFilter)) {
        $countQuery .= " WHERE section = :section";
        $recordsQuery .= " WHERE section = :section";
    }
    
    // Add sorting
    $recordsQuery .= " ORDER BY test_date DESC LIMIT :limit OFFSET :offset";
    
    // Get total count
    $countStmt = $connect->prepare($countQuery);
    if (!empty($sectionFilter)) {
        $countStmt->bindParam(':section', $sectionFilter);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $recordsPerPage);
    
    // Get records for current page
    $recordsStmt = $connect->prepare($recordsQuery);
    if (!empty($sectionFilter)) {
        $recordsStmt->bindParam(':section', $sectionFilter);
    }
    $recordsStmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $recordsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $recordsStmt->execute();
    $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available sections for filter dropdown
    $sectionsStmt = $connect->query("SELECT DISTINCT section FROM test_records ORDER BY section");
    $sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $errMsg = "Database error: " . $e->getMessage();
    error_log($errMsg);
    $records = [];
    $totalRecords = 0;
    $totalPages = 1;
    $sections = [];
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h2 class="mb-4">Laboratory Records</h2>
        
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-4">
                <form method="GET" action="<?= BASE_PATH ?>/views/records/index.php">
                    <div class="input-group">
                        <label class="input-group-text" for="sectionFilter">Section:</label>
                        <select class="form-select" id="sectionFilter" name="section" onchange="this.form.submit()">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= htmlspecialchars($section) ?>" <?= $section === $sectionFilter ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by patient name or test...">
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-primary p-2">Total Records: <?= $totalRecords ?></span>
            </div>
        </div>
        
        <!-- Records Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Sample ID</th>
                        <th>Patient</th>
                        <th>Test Name</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recordsTableBody">
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($record['test_date'])) ?></td>
                                <td><?= htmlspecialchars($record['sample_id']) ?></td>
                                <td><?= htmlspecialchars($record['full_name']) ?></td>
                                <td><?= htmlspecialchars($record['test_name']) ?></td>
                                <td><?= htmlspecialchars($record['section']) ?></td>
                                <td>
                                    <?php if ($record['status'] == 'Completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($record['status'] == 'In Progress'): ?>
                                        <span class="badge bg-info">In Progress</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_PATH ?>/views/tests/view_result.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php if ($record['status'] != 'Completed'): ?>
                                        <a href="<?= BASE_PATH ?>/views/tests/add_result.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= BASE_PATH ?>/views/reports/print_result.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No records found</td>
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
                    <a class="page-link" href="<?= BASE_PATH ?>/views/records/index.php?page=<?= $page-1 ?>&section=<?= urlencode($sectionFilter) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= BASE_PATH ?>/views/records/index.php?page=<?= $i ?>&section=<?= urlencode($sectionFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_PATH ?>/views/records/index.php?page=<?= $page+1 ?>&section=<?= urlencode($sectionFilter) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    document.querySelectorAll('#recordsTableBody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>