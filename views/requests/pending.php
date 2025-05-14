<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/config.php';

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

try {
    $countStmt = $connect->prepare("SELECT COUNT(*) FROM pending_requests WHERE status = 'Pending'");
    $countStmt->execute();
    $totalPending = $countStmt->fetchColumn();
    $totalPages = ceil($totalPending / $recordsPerPage);
    
    $stmt = $connect->prepare("
        SELECT pr.*, p.full_name, p.gender, p.age, p.birth_date 
        FROM pending_requests pr
        LEFT JOIN patients p ON pr.patient_id = p.patient_id
        WHERE pr.status = 'Pending'
        ORDER BY pr.date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading pending requests. Please try again.";
    $_SESSION['flash_type'] = "error";
    
    $pendingRequests = [];
    $totalPending = 0;
    $totalPages = 1;
}

include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Pending Requests <span class="badge bg-warning ms-2"><?= $totalPending ?></span></h2>
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
                <div class="col-md-2">
                    <select id="filterUrgency" class="form-select">
                        <option value="">All Urgency</option>
                        <option value="Routine">Routine</option>
                        <option value="Urgent">Urgent</option>
                        <option value="STAT">STAT</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary p-2">
                        <i class="bi bi-clock"></i> Pending: <?= $totalPending ?>
                    </span>
                </div>
            </div>
            
            <!-- Pending Requests Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="130">Date</th>
                            <th>Sample ID</th>
                            <th>Patient Name</th>
                            <th>Test</th>
                            <th>Station</th>
                            <th>Urgency</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTableBody">
                        <?php if (!empty($pendingRequests)): ?>
                            <?php foreach ($pendingRequests as $request): ?>
                                <tr id="request-row-<?= $request['id'] ?>" class="
                                    <?= isset($request['urgency']) && $request['urgency'] == 'STAT' ? 'table-danger' : '' ?>
                                    <?= isset($request['urgency']) && $request['urgency'] == 'Urgent' ? 'table-warning' : '' ?>
                                ">
                                    <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                    <td><?= htmlspecialchars($request['sample_id']) ?></td>
                                    <td><?= htmlspecialchars($request['full_name']) ?></td>
                                    <td><?= htmlspecialchars($request['test_name']) ?></td>
                                    <td><?= htmlspecialchars($request['station']) ?></td>
                                    <td>
                                        <?php if (isset($request['urgency'])): ?>
                                            <?php if ($request['urgency'] == 'STAT'): ?>
                                                <span class="badge bg-danger">STAT</span>
                                            <?php elseif ($request['urgency'] == 'Urgent'): ?>
                                                <span class="badge bg-warning text-dark">Urgent</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark">Routine</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">Routine</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary view-request" 
                                                    data-id="<?= $request['id'] ?>"
                                                    data-patient-id="<?= $request['patient_id'] ?>"
                                                    data-sample-id="<?= htmlspecialchars($request['sample_id']) ?>"
                                                    data-name="<?= htmlspecialchars($request['full_name']) ?>"
                                                    data-test="<?= htmlspecialchars($request['test_name']) ?>"
                                                    data-station="<?= htmlspecialchars($request['station']) ?>"
                                                    data-date="<?= htmlspecialchars($request['date']) ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-success approve-request"
                                                    data-id="<?= $request['id'] ?>"
                                                    data-patient-id="<?= $request['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($request['full_name']) ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger reject-request"
                                                    data-id="<?= $request['id'] ?>"
                                                    data-patient-id="<?= $request['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($request['full_name']) ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No pending requests found</td>
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
                        <label class="form-label">Urgency</label>
                        <input type="text" class="form-control" id="viewUrgency" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <input type="text" class="form-control" id="viewGender" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-control" id="viewAge" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Requesting Physician</label>
                    <input type="text" class="form-control" id="viewPhysician" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Clinical Information</label>
                    <textarea class="form-control" id="viewClinicalInfo" rows="3" readonly></textarea>
                </div>
                
                <div id="rejectReasonContainer" class="mb-3" style="display:none;">
                    <label for="rejectReason" class="form-label">Reason for Rejection</label>
                    <textarea class="form-control" id="rejectReason" rows="3" placeholder="Please provide a reason for rejection" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="modalRejectBtn">Reject</button>
                <button type="button" class="btn btn-success" id="modalApproveBtn">Approve</button>
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

// Urgency filter
document.getElementById('filterUrgency').addEventListener('change', function() {
    filterTable();
});

// Filter table based on all criteria
function filterTable() {
    const searchValue = document.getElementById('searchRequest').value.toLowerCase();
    const testValue = document.getElementById('filterTest').value.toLowerCase();
    const urgencyValue = document.getElementById('filterUrgency').value.toLowerCase();
    
    const rows = document.querySelectorAll('#pendingTableBody tr');
    
    rows.forEach(row => {
        const rowData = row.textContent.toLowerCase();
        const testCell = row.cells[3].textContent.toLowerCase();
        
        // Get urgency from badge class
        let urgencyText = '';
        const urgencyBadge = row.cells[5].querySelector('.badge');
        if (urgencyBadge) {
            urgencyText = urgencyBadge.textContent.toLowerCase();
        }
        
        // Apply all filters
        const matchesSearch = rowData.includes(searchValue);
        const matchesTest = !testValue || testCell.includes(testValue);
        const matchesUrgency = !urgencyValue || urgencyText.includes(urgencyValue.toLowerCase());
        
        // Show row only if it matches all active filters
        row.style.display = (matchesSearch && matchesTest && matchesUrgency) ? '' : 'none';
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
                document.getElementById('viewRequestDate').value = new Date(result.request.date).toLocaleString();
                document.getElementById('viewPatientId').value = result.request.patient_id;
                document.getElementById('viewPatientName').value = result.request.full_name;
                document.getElementById('viewSampleId').value = result.request.sample_id;
                document.getElementById('viewTestName').value = result.request.test_name;
                document.getElementById('viewStation').value = result.request.station;
                document.getElementById('viewGender').value = result.request.gender;
                document.getElementById('viewAge').value = result.request.age;
                document.getElementById('viewUrgency').value = result.request.urgency || 'Routine';
                document.getElementById('viewPhysician').value = result.request.physician || 'N/A';
                document.getElementById('viewClinicalInfo').value = result.request.clinical_info || 'N/A';
                
                // Set data attributes for approve/reject buttons
                document.getElementById('modalApproveBtn').setAttribute('data-id', result.request.id);
                document.getElementById('modalApproveBtn').setAttribute('data-patient-id', result.request.patient_id);
                document.getElementById('modalRejectBtn').setAttribute('data-id', result.request.id);
                document.getElementById('modalRejectBtn').setAttribute('data-patient-id', result.request.patient_id);
                
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

// Handle approval from modal
document.getElementById('modalApproveBtn').addEventListener('click', function() {
    approveRequest(this.getAttribute('data-id'), this.getAttribute('data-patient-id'));
});

// Handle rejection from modal
document.getElementById('modalRejectBtn').addEventListener('click', function() {
    const rejectReasonContainer = document.getElementById('rejectReasonContainer');
    
    // Toggle reject reason container
    if (rejectReasonContainer.style.display === 'none') {
        rejectReasonContainer.style.display = 'block';
        document.getElementById('rejectReason').focus();
        this.textContent = 'Confirm Rejection';
    } else {
        const reason = document.getElementById('rejectReason').value.trim();
        
        if (!reason) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Field',
                text: 'Please provide a reason for rejection'
            });
            return;
        }
        
        rejectRequest(
            this.getAttribute('data-id'), 
            this.getAttribute('data-patient-id'), 
            reason
        );
    }
});

// Hide reject reason container when modal is closed
document.getElementById('viewRequestModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('rejectReasonContainer').style.display = 'none';
    document.getElementById('rejectReason').value = '';
    document.getElementById('modalRejectBtn').textContent = 'Reject';
});

// Approve request
document.querySelectorAll('.approve-request').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.getAttribute('data-id');
        const patientId = this.getAttribute('data-patient-id');
        
        Swal.fire({
            title: 'Approve Request',
            text: 'Are you sure you want to approve this request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, approve it!'
        }).then((result) => {
            if (result.isConfirmed) {
                approveRequest(requestId, patientId);
            }
        });
    });
});

// Reject request
document.querySelectorAll('.reject-request').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.getAttribute('data-id');
        const patientId = this.getAttribute('data-patient-id');
        const patientName = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Reject Request',
            html: `Are you sure you want to reject the request for <strong>${patientName}</strong>?`,
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Reason for Rejection',
            inputPlaceholder: 'Enter reason for rejection...',
            inputAttributes: {
                required: 'required'
            },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reject it!',
            showLoaderOnConfirm: true,
            preConfirm: (reason) => {
                if (!reason.trim()) {
                    Swal.showValidationMessage('Please enter a reason for rejection');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                rejectRequest(requestId, patientId, result.value);
            }
        });
    });
});

// Function to approve request
async function approveRequest(requestId, patientId) {
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        html: 'Approving request',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('patient_id', patientId);
        formData.append('csrf_token', '<?= $csrf_token ?>');
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/RequestController.php?action=approve', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Approved!',
                text: result.message || 'Request approved successfully',
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
                    
                    // Update pending count
                    const totalPending = <?= $totalPending ?> - 1;
                    document.querySelector('h2 .badge').textContent = totalPending;
                    
                    // Check if table is empty
                    if (document.querySelectorAll('#pendingTableBody tr').length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="7" class="text-center">No pending requests found</td>';
                        document.getElementById('pendingTableBody').appendChild(emptyRow);
                    }
                }, 500);
            }
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('viewRequestModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to approve request'
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

// Function to reject request
async function rejectRequest(requestId, patientId, reason) {
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        html: 'Rejecting request',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('patient_id', patientId);
        formData.append('reject_reason', reason);
        formData.append('csrf_token', '<?= $csrf_token ?>');
        
        const response = await fetch('<?= BASE_PATH ?>/controllers/RequestController.php?action=reject', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Rejected!',
                text: result.message || 'Request rejected successfully',
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
                    
                    // Update pending count
                    const totalPending = <?= $totalPending ?> - 1;
                    document.querySelector('h2 .badge').textContent = totalPending;
                    
                    // Check if table is empty
                    if (document.querySelectorAll('#pendingTableBody tr').length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="7" class="text-center">No pending requests found</td>';
                        document.getElementById('pendingTableBody').appendChild(emptyRow);
                    }
                }, 500);
            }
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('viewRequestModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to reject request'
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