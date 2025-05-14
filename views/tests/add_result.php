<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/config.php';

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Check if we have a test ID or request ID
$testId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : null;

$test = null;
$patient = null;
$error = null;

try {
    if ($testId) {
        // Get existing test data
        $stmt = $connect->prepare("
            SELECT t.*, p.full_name as patient_name, p.gender, p.age, p.birth_date
            FROM test_records t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            WHERE t.id = :id
        ");
        $stmt->bindParam(':id', $testId, PDO::PARAM_INT);
        $stmt->execute();
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            throw new Exception("Test not found");
        }
        
        if ($test['status'] === 'Completed') {
            $error = "This test result has already been completed.";
        }
    } elseif ($requestId) {
        // Get request data to create a new test record
        $stmt = $connect->prepare("
            SELECT r.*, p.gender, p.age, p.birth_date 
            FROM request_list r
            LEFT JOIN patients p ON r.patient_id = p.patient_id
            WHERE r.id = :id AND r.status = 'Approved'
        ");
        $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception("Request not found or not approved");
        }
        
        // Check if a test record already exists for this request
        $checkStmt = $connect->prepare("
            SELECT COUNT(*) FROM test_records 
            WHERE sample_id = :sample_id AND test_name = :test_name
        ");
        $checkStmt->bindParam(':sample_id', $request['sample_id']);
        $checkStmt->bindParam(':test_name', $request['test_name']);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("A test record already exists for this request");
        }
        
        // Determine section based on test name
        $section = '';
        if (stripos($request['test_name'], 'CBC') !== false) {
            $section = 'Hematology';
        } elseif (stripos($request['test_name'], 'Urinalysis') !== false) {
            $section = 'Clinical Microscopy';
        } elseif (stripos($request['test_name'], 'Chemistry') !== false) {
            $section = 'Chemistry';
        }
        
        // Create a temporary test object to use in the form
        $test = [
            'patient_id' => $request['patient_id'],
            'patient_name' => $request['patient_name'],
            'sample_id' => $request['sample_id'],
            'test_name' => $request['test_name'],
            'section' => $section,
            'gender' => $request['gender'],
            'age' => $request['age'],
            'birth_date' => $request['birth_date'],
            'test_date' => date('Y-m-d H:i:s'),
            'status' => 'In Progress',
            'request_id' => $requestId
        ];
    } else {
        throw new Exception("No test or request ID provided");
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Include header
include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <h4 class="alert-heading">Error!</h4>
                <p><?= htmlspecialchars($error) ?></p>
                <hr>
                <p class="mb-0">
                    <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="btn btn-primary">Go to Test Summary</a>
                </p>
            </div>
        <?php elseif ($test): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <?= $testId ? 'Update Test Result' : 'Add New Test Result' ?>
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Patient and Test Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Patient Information</h5>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Patient Name:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['patient_name']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Patient ID:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['patient_id']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Gender:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['gender']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Age:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['age']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Test Information</h5>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Sample ID:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['sample_id']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Test Name:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['test_name']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Section:</div>
                                <div class="col-md-8"><?= htmlspecialchars($test['section']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Test Date:</div>
                                <div class="col-md-8"><?= date('M d, Y H:i', strtotime($test['test_date'])) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Result Form -->
                    <form id="testResultForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="test_id" value="<?= $testId ?>">
                        <input type="hidden" name="request_id" value="<?= $test['request_id'] ?? null ?>">
                        <input type="hidden" name="patient_id" value="<?= $test['patient_id'] ?>">
                        <input type="hidden" name="sample_id" value="<?= $test['sample_id'] ?>">
                        <input type="hidden" name="test_name" value="<?= $test['test_name'] ?>">
                        <input type="hidden" name="section" value="<?= $test['section'] ?>">
                        
                        <?php if (stripos($test['test_name'], 'CBC') !== false): ?>
                            <!-- CBC Test Parameters -->
                            <h5 class="border-bottom pb-2 mb-3">Complete Blood Count Results</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="wbc" class="form-label">WBC (x10^9/L)</label>
                                    <input type="text" class="form-control" id="wbc" name="parameters[wbc]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'WBC') !== false ? preg_replace('/.*WBC: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 4.5-11.0</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="rbc" class="form-label">RBC (x10^12/L)</label>
                                    <input type="text" class="form-control" id="rbc" name="parameters[rbc]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'RBC') !== false ? preg_replace('/.*RBC: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 4.5-5.5</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="hgb" class="form-label">Hemoglobin (g/dL)</label>
                                    <input type="text" class="form-control" id="hgb" name="parameters[hgb]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Hgb') !== false ? preg_replace('/.*Hgb: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 13.5-17.5 (M), 12.0-15.5 (F)</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="hct" class="form-label">Hematocrit (%)</label>
                                    <input type="text" class="form-control" id="hct" name="parameters[hct]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Hct') !== false ? preg_replace('/.*Hct: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 41-50 (M), 36-44 (F)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="plt" class="form-label">Platelets (x10^9/L)</label>
                                    <input type="text" class="form-control" id="plt" name="parameters[plt]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Plt') !== false ? preg_replace('/.*Plt: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 150-450</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="mcv" class="form-label">MCV (fL)</label>
                                    <input type="text" class="form-control" id="mcv" name="parameters[mcv]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'MCV') !== false ? preg_replace('/.*MCV: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 80-96</small>
                                </div>
                            </div>
                        <?php elseif (stripos($test['test_name'], 'Urinalysis') !== false): ?>
                            <!-- Urinalysis Test Parameters -->
                            <h5 class="border-bottom pb-2 mb-3">Urinalysis Results</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <select class="form-select" id="color" name="parameters[color]" required>
                                        <option value="">Select color</option>
                                        <option value="Yellow" <?= isset($test['result']) && stripos($test['result'], 'Color: Yellow') !== false ? 'selected' : '' ?>>Yellow</option>
                                        <option value="Pale Yellow" <?= isset($test['result']) && stripos($test['result'], 'Color: Pale Yellow') !== false ? 'selected' : '' ?>>Pale Yellow</option>
                                        <option value="Dark Yellow" <?= isset($test['result']) && stripos($test['result'], 'Color: Dark Yellow') !== false ? 'selected' : '' ?>>Dark Yellow</option>
                                        <option value="Amber" <?= isset($test['result']) && stripos($test['result'], 'Color: Amber') !== false ? 'selected' : '' ?>>Amber</option>
                                        <option value="Red" <?= isset($test['result']) && stripos($test['result'], 'Color: Red') !== false ? 'selected' : '' ?>>Red</option>
                                        <option value="Orange" <?= isset($test['result']) && stripos($test['result'], 'Color: Orange') !== false ? 'selected' : '' ?>>Orange</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="transparency" class="form-label">Transparency</label>
                                    <select class="form-select" id="transparency" name="parameters[transparency]" required>
                                        <option value="">Select transparency</option>
                                        <option value="Clear" <?= isset($test['result']) && stripos($test['result'], 'Transparency: Clear') !== false ? 'selected' : '' ?>>Clear</option>
                                        <option value="Slightly Cloudy" <?= isset($test['result']) && stripos($test['result'], 'Transparency: Slightly Cloudy') !== false ? 'selected' : '' ?>>Slightly Cloudy</option>
                                        <option value="Cloudy" <?= isset($test['result']) && stripos($test['result'], 'Transparency: Cloudy') !== false ? 'selected' : '' ?>>Cloudy</option>
                                        <option value="Turbid" <?= isset($test['result']) && stripos($test['result'], 'Transparency: Turbid') !== false ? 'selected' : '' ?>>Turbid</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ph" class="form-label">pH</label>
                                    <select class="form-select" id="ph" name="parameters[ph]" required>
                                        <option value="">Select pH</option>
                                        <option value="5.0" <?= isset($test['result']) && stripos($test['result'], 'pH: 5.0') !== false ? 'selected' : '' ?>>5.0</option>
                                        <option value="5.5" <?= isset($test['result']) && stripos($test['result'], 'pH: 5.5') !== false ? 'selected' : '' ?>>5.5</option>
                                        <option value="6.0" <?= isset($test['result']) && stripos($test['result'], 'pH: 6.0') !== false ? 'selected' : '' ?>>6.0</option>
                                        <option value="6.5" <?= isset($test['result']) && stripos($test['result'], 'pH: 6.5') !== false ? 'selected' : '' ?>>6.5</option>
                                        <option value="7.0" <?= isset($test['result']) && stripos($test['result'], 'pH: 7.0') !== false ? 'selected' : '' ?>>7.0</option>
                                        <option value="7.5" <?= isset($test['result']) && stripos($test['result'], 'pH: 7.5') !== false ? 'selected' : '' ?>>7.5</option>
                                        <option value="8.0" <?= isset($test['result']) && stripos($test['result'], 'pH: 8.0') !== false ? 'selected' : '' ?>>8.0</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="protein" class="form-label">Protein</label>
                                    <select class="form-select" id="protein" name="parameters[protein]" required>
                                        <option value="">Select result</option>
                                        <option value="Negative" <?= isset($test['result']) && stripos($test['result'], 'Protein: Negative') !== false ? 'selected' : '' ?>>Negative</option>
                                        <option value="Trace" <?= isset($test['result']) && stripos($test['result'], 'Protein: Trace') !== false ? 'selected' : '' ?>>Trace</option>
                                        <option value="1+" <?= isset($test['result']) && stripos($test['result'], 'Protein: 1+') !== false ? 'selected' : '' ?>>1+</option>
                                        <option value="2+" <?= isset($test['result']) && stripos($test['result'], 'Protein: 2+') !== false ? 'selected' : '' ?>>2+</option>
                                        <option value="3+" <?= isset($test['result']) && stripos($test['result'], 'Protein: 3+') !== false ? 'selected' : '' ?>>3+</option>
                                        <option value="4+" <?= isset($test['result']) && stripos($test['result'], 'Protein: 4+') !== false ? 'selected' : '' ?>>4+</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="glucose" class="form-label">Glucose</label>
                                    <select class="form-select" id="glucose" name="parameters[glucose]" required>
                                        <option value="">Select result</option>
                                        <option value="Negative" <?= isset($test['result']) && stripos($test['result'], 'Glucose: Negative') !== false ? 'selected' : '' ?>>Negative</option>
                                        <option value="Trace" <?= isset($test['result']) && stripos($test['result'], 'Glucose: Trace') !== false ? 'selected' : '' ?>>Trace</option>
                                        <option value="1+" <?= isset($test['result']) && stripos($test['result'], 'Glucose: 1+') !== false ? 'selected' : '' ?>>1+</option>
                                        <option value="2+" <?= isset($test['result']) && stripos($test['result'], 'Glucose: 2+') !== false ? 'selected' : '' ?>>2+</option>
                                        <option value="3+" <?= isset($test['result']) && stripos($test['result'], 'Glucose: 3+') !== false ? 'selected' : '' ?>>3+</option>
                                        <option value="4+" <?= isset($test['result']) && stripos($test['result'], 'Glucose: 4+') !== false ? 'selected' : '' ?>>4+</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ketones" class="form-label">Ketones</label>
                                    <select class="form-select" id="ketones" name="parameters[ketones]" required>
                                        <option value="">Select result</option>
                                        <option value="Negative" <?= isset($test['result']) && stripos($test['result'], 'Ketones: Negative') !== false ? 'selected' : '' ?>>Negative</option>
                                        <option value="Trace" <?= isset($test['result']) && stripos($test['result'], 'Ketones: Trace') !== false ? 'selected' : '' ?>>Trace</option>
                                        <option value="Small" <?= isset($test['result']) && stripos($test['result'], 'Ketones: Small') !== false ? 'selected' : '' ?>>Small</option>
                                        <option value="Moderate" <?= isset($test['result']) && stripos($test['result'], 'Ketones: Moderate') !== false ? 'selected' : '' ?>>Moderate</option>
                                        <option value="Large" <?= isset($test['result']) && stripos($test['result'], 'Ketones: Large') !== false ? 'selected' : '' ?>>Large</option>
                                    </select>
                                </div>
                            </div>
                        <?php elseif (stripos($test['test_name'], 'Blood Chemistry') !== false): ?>
                            <!-- Blood Chemistry Test Parameters -->
                            <h5 class="border-bottom pb-2 mb-3">Blood Chemistry Results</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="glucose" class="form-label">Glucose (mg/dL)</label>
                                    <input type="text" class="form-control" id="glucose" name="parameters[glucose]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Glucose') !== false ? preg_replace('/.*Glucose: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 70-100 (fasting)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="creatinine" class="form-label">Creatinine (mg/dL)</label>
                                    <input type="text" class="form-control" id="creatinine" name="parameters[creatinine]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Creatinine') !== false ? preg_replace('/.*Creatinine: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 0.6-1.2 (M), 0.5-1.1 (F)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="bun" class="form-label">BUN (mg/dL)</label>
                                    <input type="text" class="form-control" id="bun" name="parameters[bun]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'BUN') !== false ? preg_replace('/.*BUN: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 7-20</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="uric_acid" class="form-label">Uric Acid (mg/dL)</label>
                                    <input type="text" class="form-control" id="uric_acid" name="parameters[uric_acid]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Uric Acid') !== false ? preg_replace('/.*Uric Acid: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: 3.5-7.2 (M), 2.6-6.0 (F)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cholesterol" class="form-label">Cholesterol (mg/dL)</label>
                                    <input type="text" class="form-control" id="cholesterol" name="parameters[cholesterol]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Cholesterol') !== false ? preg_replace('/.*Cholesterol: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: < 200</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="triglycerides" class="form-label">Triglycerides (mg/dL)</label>
                                    <input type="text" class="form-control" id="triglycerides" name="parameters[triglycerides]" required
                                           value="<?= isset($test['result']) && stripos($test['result'], 'Triglycerides') !== false ? preg_replace('/.*Triglycerides: ([0-9.]+).*/i', '$1', $test['result']) : '' ?>">
                                    <small class="form-text text-muted">Normal: < 150</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Generic Test Result -->
                            <div class="mb-3">
                                <label for="resultText" class="form-label">Test Result</label>
                                <textarea class="form-control" id="resultText" name="result" rows="5" required><?= htmlspecialchars($test['result'] ?? '') ?></textarea>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"><?= htmlspecialchars($test['remarks'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="In Progress" <?= ($test['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed" <?= ($test['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="performed_by" class="form-label">Performed By</label>
                            <input type="text" class="form-control" id="performed_by" name="performed_by" value="<?= htmlspecialchars($test['performed_by'] ?? $_SESSION['username']) ?>">
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_PATH ?>/views/tests/summary.php" class="btn btn-secondary">Cancel</a>
                        <button type="button" id="saveResultBtn" class="btn btn-primary">Save Result</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('saveResultBtn')?.addEventListener('click', async function() {
    const form = document.getElementById('testResultForm');
    
    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
    // Prepare form data
    const formData = new FormData(form);
    
    try {
        const response = await fetch('<?= BASE_PATH ?>/controllers/TestController.php?action=save_result', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                confirmButtonText: 'View Results'
            }).then((result) => {
                // Redirect to test summary or view result page
                window.location.href = `<?= BASE_PATH ?>/views/tests/view_result.php?id=${result.test_id}`;
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to save test result'
            });
            
            // Reset button state
            this.disabled = false;
            this.innerHTML = 'Save Result';
        }
    } catch (error) {
        console.error('Error:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Please try again.'
        });
        
        // Reset button state
        this.disabled = false;
        this.innerHTML = 'Save Result';
    }
});
</script>

<?php include_once '../../views/layout/footer.php'; ?>