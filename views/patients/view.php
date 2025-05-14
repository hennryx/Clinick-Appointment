<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../config/config.php';

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid patient ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit();
}

$patientId = (int)$_GET['id'];

try {
    // Get patient details
    $stmt = $connect->prepare("SELECT * FROM patients WHERE patient_id = :id AND delete_status = 0");
    $stmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $stmt->execute();
    
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['flash_message'] = "Patient not found";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php");
        exit();
    }
    
    // Get patient test history
    $testStmt = $connect->prepare("
        SELECT tr.*, rl.sample_id
        FROM test_records tr 
        JOIN request_list rl ON tr.sample_id = rl.sample_id
        WHERE tr.patient_id = :id
        ORDER BY tr.test_date DESC
    ");
    $testStmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $testStmt->execute();
    $testHistory = $testStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending requests
    $pendingStmt = $connect->prepare("
        SELECT * FROM pending_requests
        WHERE patient_id = :id AND status = 'Pending'
        ORDER BY date DESC
    ");
    $pendingStmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $pendingStmt->execute();
    $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get approved requests
    $approvedStmt = $connect->prepare("
        SELECT * FROM request_list
        WHERE patient_id = :id AND status = 'Approved'
        ORDER BY request_date DESC
    ");
    $approvedStmt->bindParam(':id', $patientId, PDO::PARAM_INT);
    $approvedStmt->execute();
    $approvedRequests = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error loading patient data: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit();
}

include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Patient Information Card -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Patient Information</h5>
                            <div>
                                <a href="patients.php" class="btn btn-sm btn-light">
                                    <i class="bi bi-arrow-left"></i> Back to List
                                </a>
                                <button class="btn btn-sm btn-warning ms-2 edit-patient" 
                                        data-id="<?= $patient['patient_id'] ?>"
                                        data-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                        data-gender="<?= htmlspecialchars($patient['gender']) ?>"
                                        data-age="<?= htmlspecialchars($patient['age']) ?>"
                                        data-birth="<?= htmlspecialchars($patient['birth_date']) ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-success ms-2 create-request"
                                        data-id="<?= $patient['patient_id'] ?>"
                                        data-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                        data-gender="<?= htmlspecialchars($patient['gender']) ?>"
                                        data-age="<?= htmlspecialchars($patient['age']) ?>"
                                        data-birth="<?= htmlspecialchars($patient['birth_date']) ?>">
                                    <i class="bi bi-file-earmark-plus"></i> New Request
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="150">Patient ID:</th>
                                        <td><?= htmlspecialchars($patient['patient_id']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Full Name:</th>
                                        <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Gender:</th>
                                        <td><?= htmlspecialchars($patient['gender']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="150">Age:</th>
                                        <td><?= htmlspecialchars($patient['age']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Birth Date:</th>
                                        <td><?= htmlspecialchars($patient['birth_date']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registered:</th>
                                        <td><?= isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs for Patient History -->
        <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="test-history-tab" data-bs-toggle="tab" data-bs-target="#test-history" type="button" role="tab" aria-controls="test-history" aria-selected="true">
                    Test History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pending-requests-tab" data-bs-toggle="tab" data-bs-target="#pending-requests" type="button" role="tab" aria-controls="pending-requests" aria-selected="false">
                    Pending Requests <span class="badge bg-warning"><?= count($pendingRequests) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="approved-requests-tab" data-bs-toggle="tab" data-bs-target="#approved-requests" type="button" role="tab" aria-controls="approved-requests" aria-selected="false">
                    Approved Requests <span class="badge bg-success"><?= count($approvedRequests) ?></span>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="patientTabsContent">
            <!-- Test History Tab -->
            <div class="tab-pane fade show active" id="test-history" role="tabpanel" aria-labelledby="test-history-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Test Date</th>
                                <th>Sample ID</th>
                                <th>Test Name</th>
                                <th>Section</th>
                                <th>Result</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($testHistory)): ?>
                                <?php foreach ($testHistory as $test): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($test['test_date'])) ?></td>
                                        <td><?= htmlspecialchars($test['sample_id']) ?></td>
                                        <td><?= htmlspecialchars($test['test_name']) ?></td>
                                        <td><?= htmlspecialchars($test['section']) ?></td>
                                        <td><?= htmlspecialchars(substr($test['result'], 0, 50)) . (strlen($test['result']) > 50 ? '...' : '') ?></td>
                                        <td>
                                            <?php if ($test['status'] == 'Completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php elseif ($test['status'] == 'In Progress'): ?>
                                                <span class="badge bg-info">In Progress</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>/views/tests/view_result.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if ($test['status'] != 'Completed'): ?>
                                                <a href="<?= BASE_PATH ?>/views/tests/add_result.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-pencil"></i> Add Result
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No test history found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pending Requests Tab -->
            <div class="tab-pane fade" id="pending-requests" role="tabpanel" aria-labelledby="pending-requests-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Sample ID</th>
                                <th>Test Name</th>
                                <th>Station</th>
                                <th>Physician</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pendingRequests)): ?>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($request['date'])) ?></td>
                                        <td><?= htmlspecialchars($request['sample_id']) ?></td>
                                        <td><?= htmlspecialchars($request['test_name']) ?></td>
                                        <td><?= htmlspecialchars($request['station']) ?></td>
                                        <td><?= htmlspecialchars($request['physician'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>/views/requests/view_request.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
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
            </div>
            
            <!-- Approved Requests Tab -->
            <div class="tab-pane fade" id="approved-requests" role="tabpanel" aria-labelledby="approved-requests-tab">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Sample ID</th>
                                <th>Test Name</th>
                                <th>Station</th>
                                <th>Clinical Info</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($approvedRequests)): ?>
                                <?php foreach ($approvedRequests as $request): ?>
                                    <tr>
                                        <td><?= date('M d, Y H:i', strtotime($request['request_date'])) ?></td>
                                        <td><?= htmlspecialchars($request['sample_id']) ?></td>
                                        <td><?= htmlspecialchars($request['test_name']) ?></td>
                                        <td><?= htmlspecialchars($request['station_ward']) ?></td>
                                        <td><?= htmlspecialchars(substr($request['clinical_info'] ?? 'N/A', 0, 50)) . (strlen($request['clinical_info'] ?? '') > 50 ? '...' : '') ?></td>
                                        <td><span class="badge bg-success">Approved</span></td>
                                        <td>
                                            <a href="<?= BASE_PATH ?>/views/tests/add_result.php?request_id=<?= $request['id'] ?>" class="btn btn-sm btn-success">
                                                <i class="bi bi-plus-circle"></i> Add Result
                                            </a>
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
            </div>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal fade" id="editPatientModal" tabindex="-1" aria-labelledby="editPatientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPatientModalLabel">Edit Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editPatientForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" id="editPatientId" name="patient_id" value="<?= $patient['patient_id'] ?>">
                    
                    <div class="mb-3">
                        <label for="editFullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" value="<?= htmlspecialchars($patient['full_name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editGender" class="form-label">Gender</label>
                        <select class="form-select" id="editGender" name="gender" required>
                            <option value="Male" <?= $patient['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $patient['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAge" class="form-label">Age</label>
                        <input type="number" class="form-control" id="editAge" name="age" min="0" max="150" value="<?= htmlspecialchars($patient['age']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editBirthDate" class="form-label">Birth Date</label>
                        <input type="date" class="form-control" id="editBirthDate" name="birth_date" value="<?= htmlspecialchars($patient['birth_date']) ?>" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updatePatientBtn">Update Patient</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Request Modal -->
<div class="modal fade" id="createRequestModal" tabindex="-1" aria-labelledby="createRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createRequestModalLabel">Create New Test Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createRequestForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" id="requestPatientId" name="patient_id" value="<?= $patient['patient_id'] ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Patient ID</label>
                                <input type="text" class="form-control" id="requestPatientIdDisplay" value="<?= $patient['patient_id'] ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" id="requestPatientName" value="<?= htmlspecialchars($patient['full_name']) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sampleId" class="form-label">Sample ID*</label>
                                <input type="text" class="form-control" id="sampleId" name="sample_id" required>
                                <small class="form-text text-muted">Format: LAB-XXXXXX</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stationWard" class="form-label">Station/Ward*</label>
                                <input type="text" class="form-control" id="stationWard" name="station" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="testName" class="form-label">Test Name*</label>
                                <select class="form-select" id="testName" name="test_name" required>
                                    <option value="">Select Test</option>
                                    <option value="CBC">Complete Blood Count (CBC)</option>
                                    <option value="Urinalysis">Urinalysis</option>
                                    <option value="Blood Chemistry">Blood Chemistry</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="urgency" class="form-label">Urgency</label>
                                <select class="form-select" id="urgency" name="urgency">
                                    <option value="Routine">Routine</option>
                                    <option value="Urgent">Urgent</option>
                                    <option value="STAT">STAT</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="physician" class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" id="physician" name="physician">
                    </div>
                    
                    <div class="mb-3">
                        <label for="clinicalInfo" class="form-label">Clinical Information</label>
                        <textarea class="form-control" id="clinicalInfo" name="clinical_info" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentStatus" class="form-label">Payment Status</label>
                        <select class="form-select" id="paymentStatus" name="payment_status">
                            <option value="Unpaid">Unpaid</option>
                            <option value="Paid">Paid</option>
                            <option value="Insurance">Insurance</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitRequestBtn">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<script>
// Generate Sample ID
function generateSampleId() {
    const prefix = 'LAB-';
    const randomNum = Math.floor(100000 + Math.random() * 900000); // 6-digit number
    return prefix + randomNum;
}

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Set sample ID on page load
    document.getElementById('sampleId').value = generateSampleId();
});

// Edit Patient button click
document.querySelector('.edit-patient').addEventListener('click', function() {
    // Show edit modal
    const modal = new bootstrap.Modal(document.getElementById('editPatientModal'));
    modal.show();
});

// Create Request button click
document.querySelector('.create-request').addEventListener('click', function() {
    // Show create request modal
    const modal = new bootstrap.Modal(document.getElementById('createRequestModal'));
    modal.show();
});

// Update Patient
document.getElementById('updatePatientBtn').addEventListener('click', async function() {
    const form = document.getElementById('editPatientForm');
    const formData = new FormData(form);
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Disable button and show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
    
    try {
        const response = await fetch('<?= BASE_PATH ?>/controllers/PatientController.php?action=update', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                timer: 2000,
                showConfirmButton: false
            });
            
            // Reload the page to show updated info
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to update patient'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
        console.error('Error:', error);
    } finally {
        // Reset button state
        this.disabled = false;
        this.innerHTML = 'Update Patient';
    }
});

// Submit Request
document.getElementById('submitRequestBtn').addEventListener('click', async function() {
    const form = document.getElementById('createRequestForm');
    const formData = new FormData(form);
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Validate sample ID format
    const sampleId = document.getElementById('sampleId').value;
    if (!/^LAB-\d{6}$/.test(sampleId)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Sample ID',
            text: 'Sample ID must be in the format LAB-XXXXXX (where X is a digit)'
        });
        return;
    }
    
    // Disable button and show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
    
    try {
        const response = await fetch('<?= BASE_PATH ?>/controllers/RequestController.php?action=create', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                footer: `Sample ID: ${result.sample_id}`,
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to create request'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
        console.error('Error:', error);
    } finally {
        this.disabled = false;
        this.innerHTML = 'Submit Request';
    }
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>