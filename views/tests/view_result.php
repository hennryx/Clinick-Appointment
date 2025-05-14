<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid test ID";
    $_SESSION['flash_type'] = "error";
    header("Location: summary.php");
    exit();
}

$testId = (int)$_GET['id'];

try {
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
        $_SESSION['flash_message'] = "Test not found";
        $_SESSION['flash_type'] = "error";
        header("Location: summary.php");
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error loading test data: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: summary.php");
    exit();
}

include_once '../../views/layout/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>Test Result</h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="summary.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                <a href="<?= BASE_PATH ?>/views/reports/print_result.php?id=<?= $test['id'] ?>" class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer"></i> Print
                </a>
            </div>
        </div>
        
        <!-- Test Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Test Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Test ID:</strong> <?= $test['id'] ?></p>
                        <p><strong>Sample ID:</strong> <?= htmlspecialchars($test['sample_id']) ?></p>
                        <p><strong>Test Name:</strong> <?= htmlspecialchars($test['test_name']) ?></p>
                        <p><strong>Section:</strong> <?= htmlspecialchars($test['section']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Test Date:</strong> <?= date('M d, Y h:i A', strtotime($test['test_date'])) ?></p>
                        <p><strong>Status:</strong> 
                            <?php if ($test['status'] == 'Completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php elseif ($test['status'] == 'In Progress'): ?>
                                <span class="badge bg-info">In Progress</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Performed By:</strong> <?= htmlspecialchars($test['performed_by'] ?? 'N/A') ?></p>
                        <p><strong>Last Updated:</strong> <?= date('M d, Y h:i A', strtotime($test['updated_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Patient Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Patient Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Patient ID:</strong> <?= $test['patient_id'] ?></p>
                        <p><strong>Full Name:</strong> <?= htmlspecialchars($test['patient_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Gender:</strong> <?= htmlspecialchars($test['gender']) ?></p>
                        <p><strong>Age:</strong> <?= htmlspecialchars($test['age']) ?></p>
                        <p><strong>Birth Date:</strong> <?= htmlspecialchars($test['birth_date']) ?></p>
                    </div>
                </div>
                <div class="text-end">
                    <a href="<?= BASE_PATH ?>/views/patients/view.php?id=<?= $test['patient_id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person"></i> View Patient
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Test Results Card -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Test Results</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($test['result'])): ?>
                    <?php if (stripos($test['test_name'], 'CBC') !== false): ?>
                        <!-- CBC Test Results -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Result</th>
                                        <th>Reference Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $results = explode(', ', $test['result']);
                                    $referenceRanges = [
                                        'WBC' => '4.5-11.0 x10^9/L',
                                        'RBC' => '4.5-5.5 x10^12/L',
                                        'Hgb' => ($test['gender'] == 'Male') ? '13.5-17.5 g/dL' : '12.0-15.5 g/dL',
                                        'Hct' => ($test['gender'] == 'Male') ? '41-50%' : '36-44%',
                                        'Plt' => '150-450 x10^9/L',
                                        'MCV' => '80-96 fL'
                                    ];
                                    
                                    foreach ($results as $result): 
                                        $parts = explode(': ', $result, 2);
                                        if (count($parts) === 2):
                                            list($parameter, $value) = $parts;
                                            $refRange = $referenceRanges[trim($parameter)] ?? 'N/A';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($parameter) ?></td>
                                        <td><?= htmlspecialchars($value) ?></td>
                                        <td><?= $refRange ?></td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (stripos($test['test_name'], 'Urinalysis') !== false): ?>
                        <!-- Urinalysis Test Results -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Result</th>
                                        <th>Reference Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $results = explode(', ', $test['result']);
                                    $referenceRanges = [
                                        'Color' => 'Yellow',
                                        'Transparency' => 'Clear',
                                        'pH' => '5.0-7.0',
                                        'Protein' => 'Negative',
                                        'Glucose' => 'Negative',
                                        'Ketones' => 'Negative'
                                    ];
                                    
                                    foreach ($results as $result): 
                                        $parts = explode(': ', $result, 2);
                                        if (count($parts) === 2):
                                            list($parameter, $value) = $parts;
                                            $refRange = $referenceRanges[trim($parameter)] ?? 'N/A';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($parameter) ?></td>
                                        <td><?= htmlspecialchars($value) ?></td>
                                        <td><?= $refRange ?></td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (stripos($test['test_name'], 'Blood Chemistry') !== false): ?>
                        <!-- Blood Chemistry Test Results -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Result</th>
                                        <th>Reference Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $results = explode(', ', $test['result']);
                                    $referenceRanges = [
                                        'Glucose' => '70-100 mg/dL (fasting)',
                                        'Creatinine' => ($test['gender'] == 'Male') ? '0.6-1.2 mg/dL' : '0.5-1.1 mg/dL',
                                        'BUN' => '7-20 mg/dL',
                                        'Uric Acid' => ($test['gender'] == 'Male') ? '3.5-7.2 mg/dL' : '2.6-6.0 mg/dL',
                                        'Cholesterol' => '< 200 mg/dL',
                                        'Triglycerides' => '< 150 mg/dL'
                                    ];
                                    
                                    foreach ($results as $result): 
                                        $parts = explode(': ', $result, 2);
                                        if (count($parts) === 2):
                                            list($parameter, $value) = $parts;
                                            $refRange = $referenceRanges[trim($parameter)] ?? 'N/A';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($parameter) ?></td>
                                        <td><?= htmlspecialchars($value) ?></td>
                                        <td><?= $refRange ?></td>
                                    </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Generic Test Results -->
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Results:</h6>
                                <p class="card-text"><?= nl2br(htmlspecialchars($test['result'])) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No results available for this test.
                    </div>
                <?php endif; ?>
                
                <!-- Remarks Section -->
                <?php if (!empty($test['remarks'])): ?>
                    <div class="mt-4">
                        <h6>Remarks:</h6>
                        <div class="alert alert-secondary">
                            <?= nl2br(htmlspecialchars($test['remarks'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-end">
                    <?php if ($test['status'] != 'Completed'): ?>
                        <a href="add_result.php?id=<?= $test['id'] ?>" class="btn btn-success">
                            <i class="bi bi-pencil"></i> Update Results
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../views/layout/footer.php'; ?>