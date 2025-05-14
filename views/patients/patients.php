<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
require_once dirname(__DIR__, 2)  . '/config/config.php';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

try {
    $countStmt = $connect->query("SELECT COUNT(*) FROM patients WHERE delete_status = 0");
    $totalPatients = $countStmt->fetchColumn();
    
    $stmt = $connect->prepare("SELECT * FROM patients WHERE delete_status = 0 ORDER BY full_name LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalPages = ceil($totalPatients / $recordsPerPage);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['flash_message'] = "Error loading patients. Please try again.";
    $_SESSION['flash_type'] = "error";
    
    $patients = [];
    $totalPatients = 0;
    $totalPages = 1;
}

include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Patient Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="bi bi-plus-circle"></i> Add New Patient
                </button>
            </div>
            
            <!-- Search and filters -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchPatient" class="form-control" placeholder="Search by name, ID...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterGender" class="form-select">
                        <option value="">All Genders</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-end align-items-center">
                        <span class="me-2">Records:</span>
                        <span id="patientCount" class="badge bg-primary"><?= $totalPatients ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Patients Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Birth Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="patientsTableBody">
                        <?php if (!empty($patients)): ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr id="patient-row-<?= $patient['patient_id'] ?>">
                                    <td><?= htmlspecialchars($patient['patient_id']) ?></td>
                                    <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                    <td><?= htmlspecialchars($patient['gender']) ?></td>
                                    <td><?= htmlspecialchars($patient['age']) ?></td>
                                    <td><?= htmlspecialchars($patient['birth_date']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info view-patient" 
                                                    data-id="<?= $patient['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($patient['full_name']) ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-primary edit-patient" 
                                                    data-id="<?= $patient['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                                    data-gender="<?= htmlspecialchars($patient['gender']) ?>"
                                                    data-age="<?= htmlspecialchars($patient['age']) ?>"
                                                    data-birth="<?= htmlspecialchars($patient['birth_date']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger delete-patient" 
                                                    data-id="<?= $patient['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($patient['full_name']) ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button type="button" class="btn btn-success create-request"
                                                    data-id="<?= $patient['patient_id'] ?>"
                                                    data-name="<?= htmlspecialchars($patient['full_name']) ?>"
                                                    data-gender="<?= htmlspecialchars($patient['gender']) ?>"
                                                    data-age="<?= htmlspecialchars($patient['age']) ?>"
                                                    data-birth="<?= htmlspecialchars($patient['birth_date']) ?>">
                                                <i class="bi bi-file-earmark-plus"></i> Request
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No patients found</td>
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

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPatientModalLabel">Add New Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPatientForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullName" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="age" class="form-label">Age</label>
                        <input type="number" class="form-control" id="age" name="age" min="0" max="150" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="birthDate" class="form-label">Birth Date</label>
                        <input type="date" class="form-control" id="birthDate" name="birth_date" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePatientBtn">Save Patient</button>
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
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" id="editPatientId" name="patient_id">
                    
                    <div class="mb-3">
                        <label for="editFullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editGender" class="form-label">Gender</label>
                        <select class="form-select" id="editGender" name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAge" class="form-label">Age</label>
                        <input type="number" class="form-control" id="editAge" name="age" min="0" max="150" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editBirthDate" class="form-label">Birth Date</label>
                        <input type="date" class="form-control" id="editBirthDate" name="birth_date" required>
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
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" id="requestPatientId" name="patient_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Patient ID</label>
                                <input type="text" class="form-control" id="requestPatientIdDisplay" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control" id="requestPatientName" readonly>
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
function generateSampleId() {
    const prefix = 'LAB-';
    const randomNum = Math.floor(100000 + Math.random() * 900000); 
    return prefix + randomNum;
}

document.getElementById('searchPatient').addEventListener('keyup', function() {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#patientsTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(value) ? '' : 'none';
    });
    
    updateVisibleCount();
});

document.getElementById('filterGender').addEventListener('change', function() {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#patientsTableBody tr');
    
    rows.forEach(row => {
        if (!value) {
            row.style.display = '';
        } else {
            const gender = row.children[2].textContent.toLowerCase();
            row.style.display = gender === value.toLowerCase() ? '' : 'none';
        }
    });
    
    updateVisibleCount();
});

function updateVisibleCount() {
    const visibleRows = document.querySelectorAll('#patientsTableBody tr:not([style*="display: none"])').length;
    document.getElementById('patientCount').textContent = visibleRows;
}

document.getElementById('savePatientBtn').addEventListener('click', async function() {
    const form = document.getElementById('addPatientForm');
    const formData = new FormData(form);
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    try {
        const response = await fetch('<?= BASE_PATH ?>/controllers/PatientController.php?action=add', {
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
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to add patient'
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
        this.innerHTML = 'Save Patient';
    }
});

document.querySelectorAll('.edit-patient').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const gender = this.getAttribute('data-gender');
        const age = this.getAttribute('data-age');
        const birthDate = this.getAttribute('data-birth');
        
        document.getElementById('editPatientId').value = id;
        document.getElementById('editFullName').value = name;
        document.getElementById('editGender').value = gender;
        document.getElementById('editAge').value = age;
        document.getElementById('editBirthDate').value = birthDate;
        
        const modal = new bootstrap.Modal(document.getElementById('editPatientModal'));
        modal.show();
    });
});

document.getElementById('updatePatientBtn').addEventListener('click', async function() {
    const form = document.getElementById('editPatientForm');
    const formData = new FormData(form);
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
    
    try {
        const response = await fetch('<?= BASE_PATH ?>/controllers/AuthController.php?action=update_profile', {
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
        this.disabled = false;
        this.innerHTML = 'Update Patient';
    }
});

document.querySelectorAll('.delete-patient').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Delete Patient',
            html: `Are you sure you want to delete <strong>${name}</strong>?<br>This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('patient_id', id);
                    formData.append('csrf_token', '<?= $csrf_token ?>');
                    
                    const response = await fetch('<?= BASE_PATH ?>/controllers/PatientController.php?action=delete', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message || 'Patient deleted successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        const row = document.getElementById(`patient-row-${id}`);
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            document.getElementById('patientCount').textContent = 
                                document.querySelectorAll('#patientsTableBody tr').length;
                        }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message || 'Failed to delete patient'
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
        });
    });
});

document.querySelectorAll('.create-request').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        
        document.getElementById('requestPatientId').value = id;
        document.getElementById('requestPatientIdDisplay').value = id;
        document.getElementById('requestPatientName').value = name;
        
        document.getElementById('sampleId').value = generateSampleId();
        
        const modal = new bootstrap.Modal(document.getElementById('createRequestModal'));
        modal.show();
    });
});

document.getElementById('submitRequestBtn').addEventListener('click', async function() {
    const form = document.getElementById('createRequestForm');
    const formData = new FormData(form);
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const sampleId = document.getElementById('sampleId').value;
    if (!/^LAB-\d{6}$/.test(sampleId)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Sample ID',
            text: 'Sample ID must be in the format LAB-XXXXXX (where X is a digit)'
        });
        return;
    }
    
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
                confirmButtonText: 'View Pending Requests',
                showCancelButton: true,
                cancelButtonText: 'Stay on this page'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?= BASE_PATH ?>/views/requests/pending.php';
                } else {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createRequestModal'));
                    modal.hide();
                }
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

document.querySelectorAll('.view-patient').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        
        window.location.href = `<?= BASE_PATH ?>/views/patients/view.php?id=${id}`;
    });
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>