<?php
require_once '../../config/database.php';
require_once '../../controllers/PatientController.php';
require_once '../../includes/auth.php';

// Check authentication
checkAuth();

// Create CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Get current page from URL parameter
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Initialize patient controller
$patientController = new PatientController($connect);

// Get patients data with pagination
try {
    $data = $patientController->getAllPatients($page);
    $patients = $data['patients'];
    $totalPatients = $data['totalPatients'];
    $totalPages = $data['totalPages'];
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Get current user info
$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? '';

// Include the header
include_once '../layout/header.php';
?>

<!-- Main content -->
<div class="main-content">
    <div class="table-container">
        <h2>PATIENT LIST</h2>

        <!-- Record Count Display -->
        <div class="record-count">
            Total Patients: <span id="patientCount"><?= $totalPatients ?></span>
        </div>

        <!-- Add Patient Button: Trigger Modal -->
        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">Add Patient</button>

        <!-- Search Bar -->
        <div class="mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Search patients...">
        </div>

        <!-- Table -->
        <table id="patientTable" class="table table-striped table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Patient ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Date of Birth</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if (!empty($patients)): ?>
                    <?php foreach ($patients as $row): ?>
                    <tr id="patient-row-<?= $row['patient_id'] ?>">
                        <td><?= htmlspecialchars($row['patient_id']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['gender']) ?></td>
                        <td><?= htmlspecialchars($row['age']) ?></td>
                        <td><?= htmlspecialchars($row['birth_date']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-btn" 
                                    data-id="<?= $row['patient_id'] ?>" 
                                    data-name="<?= htmlspecialchars($row['full_name']) ?>" 
                                    data-gender="<?= htmlspecialchars($row['gender']) ?>" 
                                    data-age="<?= htmlspecialchars($row['age']) ?>" 
                                    data-birth="<?= htmlspecialchars($row['birth_date']) ?>">Edit</button>

                            <button class="btn btn-sm btn-danger delete-btn" 
                                    data-id="<?= $row['patient_id'] ?>" 
                                    data-name="<?= htmlspecialchars($row['full_name']) ?>">Delete</button>

                            <button class="btn btn-sm btn-info request-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#requestModal"
                                    data-patient-id="<?= $row['patient_id'] ?>"
                                    data-name="<?= htmlspecialchars($row['full_name']) ?>"
                                    data-gender="<?= htmlspecialchars($row['gender']) ?>"
                                    data-age="<?= htmlspecialchars($row['age']) ?>"
                                    data-birth="<?= htmlspecialchars($row['birth_date']) ?>">Request</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <!-- Modal content here -->
</div>

<!-- Include necessary JavaScript -->
<script src="../../assets/js/patients/list.js"></script>

<?php
// Include the footer
include_once '../layout/footer.php';
?>