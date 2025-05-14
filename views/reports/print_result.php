<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid test ID";
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
        echo "Test not found";
        exit();
    }
    
} catch (PDOException $e) {
    echo "Error loading test data: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Result - <?= htmlspecialchars($test['sample_id']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #feb1b7;
        }
        
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 5px;
        }
        
        .clinic-address {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-left: 4px solid #feb1b7;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .info-table td {
            padding: 5px 10px;
            border: 1px solid #ddd;
        }
        
        .info-table td:first-child {
            font-weight: bold;
            width: 30%;
            background-color: #f8f9fa;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .results-table th, .results-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .results-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            color: rgba(200, 200, 200, 0.1);
            font-size: 120px;
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">KLINIKA PAPAYA</div>
    
    <div class="container-fluid">
        <div class="text-end mb-3 no-print">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button class="btn btn-secondary" onclick="navigate()">
                Close
            </button>
        </div>
        
        <div class="report-header">
            <div class="clinic-name">KLINIKA PAPAYA DIAGNOSTIC LABORATORY AND CLINIC</div>
            <div class="clinic-address">Magalang, Central Luzon, Philippines</div>
            <div class="clinic-address">Tel: (123) 456-7890 | Email: info@klinikapapaya.com</div>
            <div class="report-title">LABORATORY TEST REPORT</div>
        </div>
        
        <div class="section-title">TEST INFORMATION</div>
        <table class="info-table">
            <tr>
                <td>Test ID:</td>
                <td><?= $test['id'] ?></td>
                <td>Sample ID:</td>
                <td><?= htmlspecialchars($test['sample_id']) ?></td>
            </tr>
            <tr>
                <td>Test Name:</td>
                <td><?= htmlspecialchars($test['test_name']) ?></td>
                <td>Section:</td>
                <td><?= htmlspecialchars($test['section']) ?></td>
            </tr>
            <tr>
                <td>Date Performed:</td>
                <td><?= date('F d, Y h:i A', strtotime($test['test_date'])) ?></td>
                <td>Status:</td>
                <td><?= htmlspecialchars($test['status']) ?></td>
            </tr>
        </table>
        
        <div class="section-title">PATIENT INFORMATION</div>
        <table class="info-table">
            <tr>
                <td>Patient ID:</td>
                <td><?= $test['patient_id'] ?></td>
                <td>Full Name:</td>
                <td><?= htmlspecialchars($test['patient_name']) ?></td>
            </tr>
            <tr>
                <td>Gender:</td>
                <td><?= htmlspecialchars($test['gender']) ?></td>
                <td>Age:</td>
                <td><?= htmlspecialchars($test['age']) ?></td>
            </tr>
            <tr>
                <td>Date of Birth:</td>
                <td colspan="3"><?= htmlspecialchars($test['birth_date']) ?></td>
            </tr>
        </table>
        
        <div class="section-title">TEST RESULTS</div>
        <?php if (!empty($test['result'])): ?>
            <?php if (stripos($test['test_name'], 'CBC') !== false): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Interpretation</th>
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
                                $paramName = trim($parameter);
                                $refRange = $referenceRanges[$paramName] ?? 'N/A';
                                
                                $interpretation = 'Normal';
                                if ($refRange != 'N/A') {
                                    $numValue = preg_replace('/[^0-9.]/', '', $value);
                                    $ranges = explode('-', preg_replace('/[^0-9.-]/', '', $refRange));
                                    if (count($ranges) == 2 && is_numeric($numValue)) {
                                        if ($numValue < $ranges[0]) {
                                            $interpretation = 'Low';
                                        } elseif ($numValue > $ranges[1]) {
                                            $interpretation = 'High';
                                        }
                                    }
                                }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($parameter) ?></td>
                            <td><?= htmlspecialchars($value) ?></td>
                            <td><?= $refRange ?></td>
                            <td><?= $interpretation ?></td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            <?php elseif (stripos($test['test_name'], 'Urinalysis') !== false): ?>
                <table class="results-table">
                    <thead>
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
            <?php elseif (stripos($test['test_name'], 'Blood Chemistry') !== false): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Result</th>
                            <th>Reference Range</th>
                            <th>Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $results = explode(', ', $test['result']);
                        $referenceRanges = [
                            'Glucose' => '70-100 mg/dL',
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
                                $paramName = trim($parameter);
                                $refRange = $referenceRanges[$paramName] ?? 'N/A';
                                
                                // Determine if value is normal
                                $interpretation = 'Normal';
                                $numValue = preg_replace('/[^0-9.]/', '', $value);
                                
                                if (substr($refRange, 0, 1) === '<') {
                                    $limit = preg_replace('/[^0-9.]/', '', $refRange);
                                    if ($numValue >= $limit) {
                                        $interpretation = 'High';
                                    }
                                } elseif (strpos($refRange, '-') !== false) {
                                    $ranges = explode('-', preg_replace('/[^0-9.-]/', '', $refRange));
                                    if (count($ranges) == 2 && is_numeric($numValue)) {
                                        if ($numValue < $ranges[0]) {
                                            $interpretation = 'Low';
                                        } elseif ($numValue > $ranges[1]) {
                                            $interpretation = 'High';
                                        }
                                    }
                                }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($parameter) ?></td>
                            <td><?= htmlspecialchars($value) ?></td>
                            <td><?= $refRange ?></td>
                            <td><?= $interpretation ?></td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <p><?= nl2br(htmlspecialchars($test['result'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-center">No results available for this test.</p>
        <?php endif; ?>
        
        <?php if (!empty($test['remarks'])): ?>
            <div class="section-title">REMARKS</div>
            <p><?= nl2br(htmlspecialchars($test['remarks'])) ?></p>
        <?php endif; ?>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    <?= htmlspecialchars($test['performed_by'] ?? 'Laboratory Technician') ?>
                    <br>
                    <small>Performed by</small>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Medical Director
                    <br>
                    <small>Reviewed and Approved by</small>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>
                This laboratory report is electronically generated and is valid without signature.
                <br>
                Report Date: <?= date('F d, Y h:i A') ?>
                <br>
                Test ID: <?= $test['id'] ?> | Sample ID: <?= htmlspecialchars($test['sample_id']) ?>
            </p>
        </div>
    </div>
    
    <script>
        if (window.location.search.includes('printAuto=true')) {
            window.onload = function() {
                window.print();
            };
        }

        function navigate() {
          window.location.href="<?= BASE_PATH ?>/views/records/index.php"
        }
    </script>
</body>
</html>