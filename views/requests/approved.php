<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Get current page for pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

try {
    // Get total count of approved requests
    $countStmt = $connect->prepare("SELECT COUNT(*) FROM request_list WHERE status = 'Approved'");
    $countStmt->execute();
    $totalApproved = $countStmt->fetchColumn();
    $totalPages = ceil($totalApproved / $recordsPerPage);
    
    // Get approved requests for current page
    $stmt = $connect->prepare("
        SELECT * FROM request_list
        WHERE status = 'Approved'
        ORDER BY request_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $approvedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error and set flash message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading approved requests. Please try again.";
    $_SESSION['flash_type'] = "error";
    
    // Initialize with empty arrays to prevent errors
    $approvedRequests = [];
    $totalApproved = 0;
    $totalPages = 1;
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Approved Requests <span class="badge bg-success ms-2"><?= $totalApproved ?></span></h2>
            </div>
            
            <!-- Search and filters -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchRequest" class="form-control" placeholder="Search by name, ID...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterTest" class="form-select">
                        <option value="">All Tests</option>
                        <option value="CBC">CBC</option>
                        <option value="Urinalysis">Urinalysis</option>
                        <option value="Blood Chemistry">Blood Chemistry</option>
                    </select>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-success p-2">
                        <i class="bi bi-check-circle"></i> Approved: <?= $totalApproved ?>
                    </span>
                </div>
            </div>
            
            <!-- Approved Requests Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="130">Date</th>
                            <th>Sample ID</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Test</th>
                            <th>Station/Ward</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvedTableBody">
                        <?php if (!empty($approvedRequests)): ?>
                            <?php foreach ($approvedRequests as $request): ?>
                                <tr id="request-row-<?= $request['id'] ?>">
                                    <td><?= date('M d, Y H:i', strtotime($request['request_date'])) ?></td>
                                    <td><?= htmlspecialchars($request['sample_id']) ?></td>
                                    <td><?= htmlspecialchars($request['patient_id']) ?></td>
                                    <td><?= htmlspecialchars($request['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($request['test_name']) ?></td>
                                    <td><?= htmlspecialchars($request['station_ward']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary view-request" 
                                                    data-id="<?= $request['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="<?= BASE_PATH ?>/views/tests/add_result.php?request_id=<?= $request['id'] ?>" 
                                               class="btn btn-success">
                                                <i class="bi bi-clipboard-plus"></i> Test
                                            </a>
                                            <button type="button" class="btn btn-warning move-to-pending"
                                                    data-id="<?= $request['id'] ?>"
                                                    data-sample-id="<?= htmlspecialchars($request['sample_id']) ?>">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No approved requests found</td>
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
                        <a class="page-link" href="?page=<?= $page-1 ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Request Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewRequestModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Request ID</label>
                        <input type="text" class="form-control" id="viewRequestId" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Request Date</label>
                        <input type="text" class="form-control" id="viewRequestDate" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient ID</label>
                        <input type="text" class="form-control" id="viewPatientId" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" id="viewPatientName" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Sample ID</label>
                        <input type="text" class="form-control" id="viewSampleId" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Test Name</label>
                        <input type="text" class="form-control" id="viewTestName" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Station/Ward</label>
                        <input type="text" class="form-control" id="viewStation" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <input type="text" class="form-control" id="viewGender" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-control" id="viewAge" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Birth Date</label>
                        <input type="text" class="form-control" id="viewBirthDate" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Clinical Information</label>
                    <textarea class="form-control" id="viewClinicalInfo" rows="3" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="addResultBtn" class="btn btn-success">Add Test Result</a>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchRequest').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    filterTable();
});

// Test filter
document.getElementById('filterTest').addEventListener('change', function() {
    filterTable();
});

// Filter table based on all criteria
function filterTable() {
    const searchValue = document.getElementById('searchRequest').value.toLowerCase();
    const testValue = document.getElementById('filterTest').value.toLowerCase();
    
    const rows = document.querySelectorAll('#approvedTableBody tr');
    
    rows.forEach(row => {
        const rowData = row.textContent.toLowerCase();
        const testCell = row.cells[4].textContent.toLowerCase();
        
        // Apply all filters
        const matchesSearch = rowData.includes(searchValue);
        const matchesTest = !testValue || testCell.includes(testValue);
        
        // Show row only if it matches all active filters
        row.style.display = (matchesSearch && matchesTest) ? '' : 'none';
    });
}

// View request details
document.querySelectorAll('.view-request').forEach(button => {
    button.addEventListener('click', async function() {
        const requestId = this.getAttribute('data-id');
        
        // Show loading state
        Swal.fire({
            title: 'Loading request details...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            // Fetch request details
            const response = await fetch(`<?= BASE_PATH ?>/controllers/RequestController.php?action=get_details&id=${requestId}`);
            const result = await response.json();
            
            if (result.success) {
                // Close loading dialog
                Swal.close();
                
                // Populate modal with request details
                document.getElementById('viewRequestId').value = result.request.id;
                document.getElementById('viewRequestDate').value = new Date(result.request.request_date).toLocaleString();
                document.getElementById('viewPatientId').value = result.request.patient_id;
                document.getElementById('viewPatientName').value = result.request.patient_name;
                document.getElementById('viewSampleId').value = result.request.sample_id;
                document.getElementById('viewTestName').value = result.request.test_name;
                document.getElementById('viewStation').value = result.request.station_ward;
                document.getElementById('viewGender').value = result.request.gender || 'N/A';
                document.getElementById('viewAge').value = result.request.age || 'N/A';
                document.getElementById('viewBirthDate').value = result.request.birth_date || 'N/A';
                document.getElementById('viewClinicalInfo').value = result.request.clinical_info || 'N/A';
                
                // Update Add Result button href
                document.getElementById('addResultBtn').href = `<?= BASE_PATH ?>/views/tests/add_result.php?request_id=${result.request.id}`;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
                modal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: result.message || 'Failed to load request details'
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
    });
});

// Move to Pending
document.querySelectorAll('.move-to-pending').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.getAttribute('data-id');
        const sampleId = this.getAttribute('data-sample-id');
        
        Swal.fire({
            title: 'Move to Pending',
            html: `Are you sure you want to move request <strong>${sampleId}</strong> back to pending?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, move it'
        }).then((result) => {
            if (result.isConfirmed) {
                moveToPending(requestId);
            }
        });
    });
});

// Function to move request back to pending
async function moveToPending(requestId) {
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        html: 'Moving request to pending',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('csrf_token', '<?= $csrf_token ?>');
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/RequestController.php?action=move_to_pending', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Moved!',
                text: result.message || 'Request moved back to pending successfully',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Remove the request row from the table
            const row = document.getElementById(`request-row-${requestId}`);
            if (row) {
                row.style.transition = 'opacity 0.5s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    
                    // Update approved count
                    const totalApproved = <?= $totalApproved ?> - 1;
                    document.querySelector('h2 .badge').textContent = totalApproved;
                    
                    // Check if table is empty
                    if (document.querySelectorAll('#approvedTableBody tr').length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="7" class="text-center">No approved requests found</td>';
                        document.getElementById('approvedTableBody').appendChild(emptyRow);
                    }
                }, 500);
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to move request to pending'
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