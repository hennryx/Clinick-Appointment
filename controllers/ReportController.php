<?php
// controllers/ReportController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class ReportController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Handle report-related actions
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'income':
                $this->generateIncomeReport();
                break;
            case 'census':
                $this->generateCensusReport();
                break;
            case 'workload':
                $this->generateWorkloadReport();
                break;
            case 'consumption':
                $this->generateConsumptionReport();
                break;
            case 'test_summary':
                $this->generateTestSummaryReport();
                break;
            case 'custom':
                $this->generateCustomReport();
                break;
            default:
                $this->respondWithError('Invalid action');
        }
    }
    
    /**
     * Generate income report
     */
    private function generateIncomeReport() {
        // Get date range from parameters
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $format = $_GET['format'] ?? 'json';
        
        try {
            // Get total income for the period
            $totalStmt = $this->db->prepare("
                SELECT SUM(price) as total 
                FROM test_results 
                WHERE test_date BETWEEN :start_date AND :end_date
            ");
            $totalStmt->bindParam(':start_date', $startDate);
            $totalStmt->bindParam(':end_date', $endDate);
            $totalStmt->execute();
            
            $totalIncome = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get income by test type
            $testTypeStmt = $this->db->prepare("
                SELECT test_name, COUNT(*) as count, SUM(price) as total 
                FROM test_results 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY test_name
            ");
            $testTypeStmt->bindParam(':start_date', $startDate);
            $testTypeStmt->bindParam(':end_date', $endDate);
            $testTypeStmt->execute();
            
            $testTypeIncome = $testTypeStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get income by day
            $dailyStmt = $this->db->prepare("
                SELECT DATE(test_date) as date, COUNT(*) as count, SUM(price) as total 
                FROM test_results 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY DATE(test_date)
            ");
            $dailyStmt->bindParam(':start_date', $startDate);
            $dailyStmt->bindParam(':end_date', $endDate);
            $dailyStmt->execute();
            
            $dailyIncome = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare report data
            $reportData = [
                'total_income' => $totalIncome,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'by_test_type' => $testTypeIncome,
                'by_day' => $dailyIncome
            ];
            
            // Return report based on requested format
            switch ($format) {
                case 'json':
                    $this->respondWithSuccess('Income report generated', ['report' => $reportData]);
                    break;
                case 'html':
                    $this->renderIncomeReportHtml($reportData);
                    break;
                case 'csv':
                    $this->generateIncomeReportCsv($reportData);
                    break;
                default:
                    $this->respondWithError('Unsupported format');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to generate income report: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to generate income report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate patient census report
     */
    private function generateCensusReport() {
        // Get date range from parameters
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $format = $_GET['format'] ?? 'json';
        
        try {
            // Get total number of patients
            $totalStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM patients 
                WHERE delete_status = 0
            ");
            $totalStmt->execute();
            $totalPatients = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get new patients within date range
            $newStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM patients 
                WHERE delete_status = 0 
                AND (created_at BETWEEN :start_date AND :end_date)
            ");
            $newStmt->bindParam(':start_date', $startDate);
            $newStmt->bindParam(':end_date', $endDate);
            $newStmt->execute();
            $newPatients = $newStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get patient distribution by gender
            $genderStmt = $this->db->prepare("
                SELECT gender, COUNT(*) as count 
                FROM patients 
                WHERE delete_status = 0 
                GROUP BY gender
            ");
            $genderStmt->execute();
            $genderDistribution = $genderStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient distribution by age group
            $ageGroupStmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN age < 18 THEN '0-17'
                        WHEN age BETWEEN 18 AND 30 THEN '18-30'
                        WHEN age BETWEEN 31 AND 45 THEN '31-45'
                        WHEN age BETWEEN 46 AND 60 THEN '46-60'
                        ELSE '60+' 
                    END as age_group,
                    COUNT(*) as count
                FROM patients 
                WHERE delete_status = 0 
                GROUP BY age_group
            ");
            $ageGroupStmt->execute();
            $ageGroupDistribution = $ageGroupStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get test requests by patient
            $requestsStmt = $this->db->prepare("
                SELECT p.patient_id, p.full_name, COUNT(pr.id) as request_count
                FROM patients p
                LEFT JOIN pending_requests pr ON p.patient_id = pr.patient_id
                WHERE p.delete_status = 0
                AND (pr.date BETWEEN :start_date AND :end_date OR pr.date IS NULL)
                GROUP BY p.patient_id
                ORDER BY request_count DESC
                LIMIT 10
            ");
            $requestsStmt->bindParam(':start_date', $startDate);
            $requestsStmt->bindParam(':end_date', $endDate);
            $requestsStmt->execute();
            $patientRequests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare report data
            $reportData = [
                'total_patients' => $totalPatients,
                'new_patients' => $newPatients,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'gender_distribution' => $genderDistribution,
                'age_group_distribution' => $ageGroupDistribution,
                'patient_requests' => $patientRequests
            ];
            
            // Return report based on requested format
            switch ($format) {
                case 'json':
                    $this->respondWithSuccess('Census report generated', ['report' => $reportData]);
                    break;
                case 'html':
                    $this->renderCensusReportHtml($reportData);
                    break;
                case 'csv':
                    $this->generateCensusReportCsv($reportData);
                    break;
                default:
                    $this->respondWithError('Unsupported format');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to generate census report: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to generate census report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate laboratory workload report
     */
    private function generateWorkloadReport() {
        // Get date range from parameters
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $format = $_GET['format'] ?? 'json';
        
        try {
            // Get total number of tests
            $totalStmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date
            ");
            $totalStmt->bindParam(':start_date', $startDate);
            $totalStmt->bindParam(':end_date', $endDate);
            $totalStmt->execute();
            $totalTests = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get test distribution by section
            $sectionStmt = $this->db->prepare("
                SELECT section, COUNT(*) as count 
                FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY section
            ");
            $sectionStmt->bindParam(':start_date', $startDate);
            $sectionStmt->bindParam(':end_date', $endDate);
            $sectionStmt->execute();
            $sectionDistribution = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get test distribution by test name
            $testNameStmt = $this->db->prepare("
                SELECT test_name, COUNT(*) as count 
                FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY test_name
            ");
            $testNameStmt->bindParam(':start_date', $startDate);
            $testNameStmt->bindParam(':end_date', $endDate);
            $testNameStmt->execute();
            $testNameDistribution = $testNameStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get tests by status
            $statusStmt = $this->db->prepare("
                SELECT status, COUNT(*) as count 
                FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY status
            ");
            $statusStmt->bindParam(':start_date', $startDate);
            $statusStmt->bindParam(':end_date', $endDate);
            $statusStmt->execute();
            $statusDistribution = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get daily test count
            $dailyStmt = $this->db->prepare("
                SELECT DATE(test_date) as date, COUNT(*) as count 
                FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                GROUP BY DATE(test_date)
            ");
            $dailyStmt->bindParam(':start_date', $startDate);
            $dailyStmt->bindParam(':end_date', $endDate);
            $dailyStmt->execute();
            $dailyTests = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top performers (staff who processed the most tests)
            $performersStmt = $this->db->prepare("
                SELECT performed_by, COUNT(*) as count 
                FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                AND performed_by IS NOT NULL
                GROUP BY performed_by
                ORDER BY count DESC
                LIMIT 5
            ");
            $performersStmt->bindParam(':start_date', $startDate);
            $performersStmt->bindParam(':end_date', $endDate);
            $performersStmt->execute();
            $topPerformers = $performersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare report data
            $reportData = [
                'total_tests' => $totalTests,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'section_distribution' => $sectionDistribution,
                'test_name_distribution' => $testNameDistribution,
                'status_distribution' => $statusDistribution,
                'daily_tests' => $dailyTests,
                'top_performers' => $topPerformers
            ];
            
            // Return report based on requested format
            switch ($format) {
                case 'json':
                    $this->respondWithSuccess('Workload report generated', ['report' => $reportData]);
                    break;
                case 'html':
                    $this->renderWorkloadReportHtml($reportData);
                    break;
                case 'csv':
                    $this->generateWorkloadReportCsv($reportData);
                    break;
                default:
                    $this->respondWithError('Unsupported format');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to generate workload report: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to generate workload report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate reagent consumption report
     */
    private function generateConsumptionReport() {
        // Get date range from parameters
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $format = $_GET['format'] ?? 'json';
        
        try {
            // Get total reagent consumption
            $totalStmt = $this->db->prepare("
                SELECT SUM(quantity_used) as total 
                FROM reagent_consumption 
                WHERE usage_date BETWEEN :start_date AND :end_date
            ");
            $totalStmt->bindParam(':start_date', $startDate);
            $totalStmt->bindParam(':end_date', $endDate);
            $totalStmt->execute();
            $totalConsumption = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get consumption by reagent
            $reagentStmt = $this->db->prepare("
                SELECT reagent_name, SUM(quantity_used) as quantity 
                FROM reagent_consumption 
                WHERE usage_date BETWEEN :start_date AND :end_date 
                GROUP BY reagent_name
            ");
            $reagentStmt->bindParam(':start_date', $startDate);
            $reagentStmt->bindParam(':end_date', $endDate);
            $reagentStmt->execute();
            $reagentConsumption = $reagentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get daily consumption
            $dailyStmt = $this->db->prepare("
                SELECT usage_date as date, SUM(quantity_used) as quantity 
                FROM reagent_consumption 
                WHERE usage_date BETWEEN :start_date AND :end_date 
                GROUP BY usage_date
            ");
            $dailyStmt->bindParam(':start_date', $startDate);
            $dailyStmt->bindParam(':end_date', $endDate);
            $dailyStmt->execute();
            $dailyConsumption = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get consumption by user
            $userStmt = $this->db->prepare("
                SELECT used_by, SUM(quantity_used) as quantity 
                FROM reagent_consumption 
                WHERE usage_date BETWEEN :start_date AND :end_date 
                GROUP BY used_by
            ");
            $userStmt->bindParam(':start_date', $startDate);
            $userStmt->bindParam(':end_date', $endDate);
            $userStmt->execute();
            $userConsumption = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare report data
            $reportData = [
                'total_consumption' => $totalConsumption,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reagent_consumption' => $reagentConsumption,
                'daily_consumption' => $dailyConsumption,
                'user_consumption' => $userConsumption
            ];
            
            // Return report based on requested format
            switch ($format) {
                case 'json':
                    $this->respondWithSuccess('Consumption report generated', ['report' => $reportData]);
                    break;
                case 'html':
                    $this->renderConsumptionReportHtml($reportData);
                    break;
                case 'csv':
                    $this->generateConsumptionReportCsv($reportData);
                    break;
                default:
                    $this->respondWithError('Unsupported format');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to generate consumption report: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to generate consumption report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate test summary report
     */
    private function generateTestSummaryReport() {
        // Get date range from parameters
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $format = $_GET['format'] ?? 'json';
        
        try {
            // Get test records within date range
            $stmt = $this->db->prepare("
                SELECT * FROM test_records 
                WHERE test_date BETWEEN :start_date AND :end_date 
                ORDER BY test_date DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare report data
            $reportData = [
                'tests' => $tests,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_tests' => count($tests)
            ];
            
            // Return report based on requested format
            switch ($format) {
                case 'json':
                    $this->respondWithSuccess('Test summary report generated', ['report' => $reportData]);
                    break;
                case 'html':
                    $this->renderTestSummaryReportHtml($reportData);
                    break;
                case 'csv':
                    $this->generateTestSummaryReportCsv($reportData);
                    break;
                default:
                    $this->respondWithError('Unsupported format');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to generate test summary report: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to generate test summary report: " . $e->getMessage());
        }
    }
    
    /**
     * Generate a custom report
     */
    private function generateCustomReport() {
        // Get parameters
        $reportType = $_GET['report_type'] ?? '';
        
        // Call the appropriate report method based on report type
        switch ($reportType) {
            case 'income':
                $this->generateIncomeReport();
                break;
            case 'census':
                $this->generateCensusReport();
                break;
            case 'workload':
                $this->generateWorkloadReport();
                break;
            case 'consumption':
                $this->generateConsumptionReport();
                break;
            case 'tests':
                $this->generateTestSummaryReport();
                break;
            default:
                $this->respondWithError('Invalid report type');
        }
    }
    
    /**
     * Get date range from parameters
     */
    private function getDateRange() {
        $dateRange = $_GET['date_range'] ?? 'this_month';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        // If custom date range is provided
        if ($dateRange === 'custom' && !empty($startDate) && !empty($endDate)) {
            return [
                'start_date' => $startDate . ' 00:00:00',
                'end_date' => $endDate . ' 23:59:59'
            ];
        }
        
        // Calculate date range based on predefined options
        $now = new DateTime();
        $today = $now->format('Y-m-d');
        
        switch ($dateRange) {
            case 'today':
                return [
                    'start_date' => $today . ' 00:00:00',
                    'end_date' => $today . ' 23:59:59'
                ];
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return [
                    'start_date' => $yesterday . ' 00:00:00',
                    'end_date' => $yesterday . ' 23:59:59'
                ];
            case 'this_week':
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                return [
                    'start_date' => $weekStart . ' 00:00:00',
                    'end_date' => $today . ' 23:59:59'
                ];
            case 'last_week':
                $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
                $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
                return [
                    'start_date' => $lastWeekStart . ' 00:00:00',
                    'end_date' => $lastWeekEnd . ' 23:59:59'
                ];
            case 'this_month':
                $monthStart = date('Y-m-01');
                return [
                    'start_date' => $monthStart . ' 00:00:00',
                    'end_date' => $today . ' 23:59:59'
                ];
            case 'last_month':
                $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
                $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
                return [
                    'start_date' => $lastMonthStart . ' 00:00:00',
                    'end_date' => $lastMonthEnd . ' 23:59:59'
                ];
            case 'this_year':
                $yearStart = date('Y-01-01');
                return [
                    'start_date' => $yearStart . ' 00:00:00',
                    'end_date' => $today . ' 23:59:59'
                ];
            default:
                // Default to this month
                $monthStart = date('Y-m-01');
                return [
                    'start_date' => $monthStart . ' 00:00:00',
                    'end_date' => $today . ' 23:59:59'
                ];
        }
    }
    
    /**
     * Render income report as HTML
     */
    private function renderIncomeReportHtml($data) {
        header('Content-Type: text/html');
        
        $startDate = date('M d, Y', strtotime($data['start_date']));
        $endDate = date('M d, Y', strtotime($data['end_date']));
        $totalIncome = number_format($data['total_income'], 2);
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Income Report ({$startDate} - {$endDate})</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Income Report</h1>
                <button class='print-btn' onclick='window.print()'>Print Report</button>
            </div>
            <p><strong>Period:</strong> {$startDate} - {$endDate}</p>
            <p><strong>Total Income:</strong> ₱{$totalIncome}</p>
            
            <h2>Income by Test Type</h2>
            <table>
                <tr>
                    <th>Test Name</th>
                    <th>Count</th>
                    <th>Total</th>
                </tr>";
        
        foreach ($data['by_test_type'] as $item) {
            $total = number_format($item['total'], 2);
            echo "<tr>
                <td>{$item['test_name']}</td>
                <td>{$item['count']}</td>
                <td>₱{$total}</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Daily Income</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Count</th>
                    <th>Total</th>
                </tr>";
        
        foreach ($data['by_day'] as $item) {
            $date = date('M d, Y', strtotime($item['date']));
            $total = number_format($item['total'], 2);
            echo "<tr>
                <td>{$date}</td>
                <td>{$item['count']}</td>
                <td>₱{$total}</td>
            </tr>";
        }
        
        echo "</table>
            
            <script>
                // Auto print when loaded for PDF export
                if (window.location.search.includes('autoPrint=true')) {
                    window.onload = function() {
                        window.print();
                    };
                }
            </script>
        </body>
        </html>";
        
        exit;
    }
    
    /**
     * Generate income report as CSV
     */
    private function generateIncomeReportCsv($data) {
        $filename = 'income_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['Income Report']);
        fputcsv($output, ['Period', date('M d, Y', strtotime($data['start_date'])) . ' - ' . date('M d, Y', strtotime($data['end_date']))]);
        fputcsv($output, ['Total Income', $data['total_income']]);
        fputcsv($output, []);
        
        // Add test type data
        fputcsv($output, ['Income by Test Type']);
        fputcsv($output, ['Test Name', 'Count', 'Total']);
        
        foreach ($data['by_test_type'] as $item) {
            fputcsv($output, [$item['test_name'], $item['count'], $item['total']]);
        }
        
        fputcsv($output, []);
        
        // Add daily data
        fputcsv($output, ['Daily Income']);
        fputcsv($output, ['Date', 'Count', 'Total']);
        
        foreach ($data['by_day'] as $item) {
            fputcsv($output, [date('M d, Y', strtotime($item['date'])), $item['count'], $item['total']]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render census report as HTML
     */
    private function renderCensusReportHtml($data) {
        header('Content-Type: text/html');
        
        $startDate = date('M d, Y', strtotime($data['start_date']));
        $endDate = date('M d, Y', strtotime($data['end_date']));
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Patient Census Report ({$startDate} - {$endDate})</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Patient Census Report</h1>
                <button class='print-btn' onclick='window.print()'>Print Report</button>
            </div>
            <p><strong>Period:</strong> {$startDate} - {$endDate}</p>
            <p><strong>Total Patients:</strong> {$data['total_patients']}</p>
            <p><strong>New Patients:</strong> {$data['new_patients']}</p>
            
            <h2>Gender Distribution</h2>
            <table>
                <tr>
                    <th>Gender</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['gender_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_patients']) * 100;
            echo "<tr>
                <td>{$item['gender']}</td>
                <td>{$item['count']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Age Group Distribution</h2>
            <table>
                <tr>
                    <th>Age Group</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['age_group_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_patients']) * 100;
            echo "<tr>
                <td>{$item['age_group']}</td>
                <td>{$item['count']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Top Patients by Request Count</h2>
            <table>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Request Count</th>
                </tr>";
        
        foreach ($data['patient_requests'] as $item) {
            echo "<tr>
                <td>{$item['patient_id']}</td>
                <td>{$item['full_name']}</td>
                <td>{$item['request_count']}</td>
            </tr>";
        }
        
        echo "</table>
            
            <script>
                // Auto print when loaded for PDF export
                if (window.location.search.includes('autoPrint=true')) {
                    window.onload = function() {
                        window.print();
                    };
                }
            </script>
        </body>
        </html>";
        
        exit;
    }
    
    /**
     * Generate census report as CSV
     */
    private function generateCensusReportCsv($data) {
        $filename = 'census_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['Patient Census Report']);
        fputcsv($output, ['Period', date('M d, Y', strtotime($data['start_date'])) . ' - ' . date('M d, Y', strtotime($data['end_date']))]);
        fputcsv($output, ['Total Patients', $data['total_patients']]);
        fputcsv($output, ['New Patients', $data['new_patients']]);
        fputcsv($output, []);
        
        // Add gender distribution data
        fputcsv($output, ['Gender Distribution']);
        fputcsv($output, ['Gender', 'Count', 'Percentage']);
        
        foreach ($data['gender_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_patients']) * 100;
            fputcsv($output, [
                $item['gender'], 
                $item['count'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add age group distribution data
        fputcsv($output, ['Age Group Distribution']);
        fputcsv($output, ['Age Group', 'Count', 'Percentage']);
        
        foreach ($data['age_group_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_patients']) * 100;
            fputcsv($output, [
                $item['age_group'], 
                $item['count'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add patient requests data
        fputcsv($output, ['Top Patients by Request Count']);
        fputcsv($output, ['Patient ID', 'Name', 'Request Count']);
        
        foreach ($data['patient_requests'] as $item) {
            fputcsv($output, [$item['patient_id'], $item['full_name'], $item['request_count']]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render workload report as HTML
     */
    private function renderWorkloadReportHtml($data) {
        header('Content-Type: text/html');
        
        $startDate = date('M d, Y', strtotime($data['start_date']));
        $endDate = date('M d, Y', strtotime($data['end_date']));
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Laboratory Workload Report ({$startDate} - {$endDate})</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Laboratory Workload Report</h1>
                <button class='print-btn' onclick='window.print()'>Print Report</button>
            </div>
            <p><strong>Period:</strong> {$startDate} - {$endDate}</p>
            <p><strong>Total Tests:</strong> {$data['total_tests']}</p>
            
            <h2>Test Distribution by Section</h2>
            <table>
                <tr>
                    <th>Section</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['section_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            echo "<tr>
                <td>{$item['section']}</td>
                <td>{$item['count']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Test Distribution by Test Name</h2>
            <table>
                <tr>
                    <th>Test Name</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['test_name_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            echo "<tr>
                <td>{$item['test_name']}</td>
                <td>{$item['count']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Test Distribution by Status</h2>
            <table>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['status_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            echo "<tr>
                <td>{$item['status']}</td>
                <td>{$item['count']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Daily Test Count</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Count</th>
                </tr>";
        
        foreach ($data['daily_tests'] as $item) {
            $date = date('M d, Y', strtotime($item['date']));
            echo "<tr>
                <td>{$date}</td>
                <td>{$item['count']}</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Top Performers</h2>
            <table>
                <tr>
                    <th>Staff</th>
                    <th>Tests Processed</th>
                </tr>";
        
        foreach ($data['top_performers'] as $item) {
            echo "<tr>
                <td>{$item['performed_by']}</td>
                <td>{$item['count']}</td>
            </tr>";
        }
        
        echo "</table>
            
            <script>
                // Auto print when loaded for PDF export
                if (window.location.search.includes('autoPrint=true')) {
                    window.onload = function() {
                        window.print();
                    };
                }
            </script>
        </body>
        </html>";
        
        exit;
    }
    
    /**
     * Generate workload report as CSV
     */
    private function generateWorkloadReportCsv($data) {
        $filename = 'workload_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['Laboratory Workload Report']);
        fputcsv($output, ['Period', date('M d, Y', strtotime($data['start_date'])) . ' - ' . date('M d, Y', strtotime($data['end_date']))]);
        fputcsv($output, ['Total Tests', $data['total_tests']]);
        fputcsv($output, []);
        
        // Add section distribution data
        fputcsv($output, ['Test Distribution by Section']);
        fputcsv($output, ['Section', 'Count', 'Percentage']);
        
        foreach ($data['section_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            fputcsv($output, [
                $item['section'], 
                $item['count'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add test name distribution data
        fputcsv($output, ['Test Distribution by Test Name']);
        fputcsv($output, ['Test Name', 'Count', 'Percentage']);
        
        foreach ($data['test_name_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            fputcsv($output, [
                $item['test_name'], 
                $item['count'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add status distribution data
        fputcsv($output, ['Test Distribution by Status']);
        fputcsv($output, ['Status', 'Count', 'Percentage']);
        
        foreach ($data['status_distribution'] as $item) {
            $percentage = ($item['count'] / $data['total_tests']) * 100;
            fputcsv($output, [
                $item['status'], 
                $item['count'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add daily test count data
        fputcsv($output, ['Daily Test Count']);
        fputcsv($output, ['Date', 'Count']);
        
        foreach ($data['daily_tests'] as $item) {
            fputcsv($output, [date('M d, Y', strtotime($item['date'])), $item['count']]);
        }
        
        fputcsv($output, []);
        
        // Add top performers data
        fputcsv($output, ['Top Performers']);
        fputcsv($output, ['Staff', 'Tests Processed']);
        
        foreach ($data['top_performers'] as $item) {
            fputcsv($output, [$item['performed_by'], $item['count']]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render consumption report as HTML
     */
    private function renderConsumptionReportHtml($data) {
        header('Content-Type: text/html');
        
        $startDate = date('M d, Y', strtotime($data['start_date']));
        $endDate = date('M d, Y', strtotime($data['end_date']));
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Reagent Consumption Report ({$startDate} - {$endDate})</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Reagent Consumption Report</h1>
                <button class='print-btn' onclick='window.print()'>Print Report</button>
            </div>
            <p><strong>Period:</strong> {$startDate} - {$endDate}</p>
            <p><strong>Total Consumption:</strong> {$data['total_consumption']} units</p>
            
            <h2>Consumption by Reagent</h2>
            <table>
                <tr>
                    <th>Reagent Name</th>
                    <th>Quantity</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['reagent_consumption'] as $item) {
            $percentage = ($item['quantity'] / $data['total_consumption']) * 100;
            echo "<tr>
                <td>{$item['reagent_name']}</td>
                <td>{$item['quantity']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Daily Consumption</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Quantity</th>
                </tr>";
        
        foreach ($data['daily_consumption'] as $item) {
            $date = date('M d, Y', strtotime($item['date']));
            echo "<tr>
                <td>{$date}</td>
                <td>{$item['quantity']}</td>
            </tr>";
        }
        
        echo "</table>
            
            <h2>Consumption by User</h2>
            <table>
                <tr>
                    <th>User</th>
                    <th>Quantity</th>
                    <th>Percentage</th>
                </tr>";
        
        foreach ($data['user_consumption'] as $item) {
            $percentage = ($item['quantity'] / $data['total_consumption']) * 100;
            echo "<tr>
                <td>{$item['used_by']}</td>
                <td>{$item['quantity']}</td>
                <td>" . number_format($percentage, 2) . "%</td>
            </tr>";
        }
        
        echo "</table>
            
            <script>
                // Auto print when loaded for PDF export
                if (window.location.search.includes('autoPrint=true')) {
                    window.onload = function() {
                        window.print();
                    };
                }
            </script>
        </body>
        </html>";
        
        exit;
    }
    
    /**
     * Generate consumption report as CSV
     */
    private function generateConsumptionReportCsv($data) {
        $filename = 'consumption_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['Reagent Consumption Report']);
        fputcsv($output, ['Period', date('M d, Y', strtotime($data['start_date'])) . ' - ' . date('M d, Y', strtotime($data['end_date']))]);
        fputcsv($output, ['Total Consumption', $data['total_consumption'] . ' units']);
        fputcsv($output, []);
        
        // Add reagent consumption data
        fputcsv($output, ['Consumption by Reagent']);
        fputcsv($output, ['Reagent Name', 'Quantity', 'Percentage']);
        
        foreach ($data['reagent_consumption'] as $item) {
            $percentage = ($item['quantity'] / $data['total_consumption']) * 100;
            fputcsv($output, [
                $item['reagent_name'], 
                $item['quantity'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fputcsv($output, []);
        
        // Add daily consumption data
        fputcsv($output, ['Daily Consumption']);
        fputcsv($output, ['Date', 'Quantity']);
        
        foreach ($data['daily_consumption'] as $item) {
            fputcsv($output, [date('M d, Y', strtotime($item['date'])), $item['quantity']]);
        }
        
        fputcsv($output, []);
        
        // Add user consumption data
        fputcsv($output, ['Consumption by User']);
        fputcsv($output, ['User', 'Quantity', 'Percentage']);
        
        foreach ($data['user_consumption'] as $item) {
            $percentage = ($item['quantity'] / $data['total_consumption']) * 100;
            fputcsv($output, [
                $item['used_by'], 
                $item['quantity'], 
                number_format($percentage, 2) . '%'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render test summary report as HTML
     */
    private function renderTestSummaryReportHtml($data) {
        header('Content-Type: text/html');
        
        $startDate = date('M d, Y', strtotime($data['start_date']));
        $endDate = date('M d, Y', strtotime($data['end_date']));
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Test Summary Report ({$startDate} - {$endDate})</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1, h2 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; }
                .header { display: flex; justify-content: space-between; align-items: center; }
                .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; }
                .status-completed { background-color: #dff0d8; }
                .status-progress { background-color: #d9edf7; }
                .status-pending { background-color: #fcf8e3; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Test Summary Report</h1>
                <button class='print-btn' onclick='window.print()'>Print Report</button>
            </div>
            <p><strong>Period:</strong> {$startDate} - {$endDate}</p>
            <p><strong>Total Tests:</strong> {$data['total_tests']}</p>
            
            <h2>Test Records</h2>
            <table>
                <tr>
                    <th>Test Date</th>
                    <th>Sample ID</th>
                    <th>Patient</th>
                    <th>Test Name</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Result</th>
                </tr>";
        
        foreach ($data['tests'] as $test) {
            $statusClass = '';
            if ($test['status'] === 'Completed') {
                $statusClass = 'status-completed';
            } elseif ($test['status'] === 'In Progress') {
                $statusClass = 'status-progress';
            } elseif ($test['status'] === 'Pending') {
                $statusClass = 'status-pending';
            }
            
            $date = date('M d, Y H:i', strtotime($test['test_date']));
            $result = htmlspecialchars(substr($test['result'] ?? 'Not available', 0, 100));
            if (strlen($test['result'] ?? '') > 100) {
                $result .= '...';
            }
            
            echo "<tr class='{$statusClass}'>
                <td>{$date}</td>
                <td>{$test['sample_id']}</td>
                <td>{$test['patient_name']}</td>
                <td>{$test['test_name']}</td>
                <td>{$test['section']}</td>
                <td>{$test['status']}</td>
                <td>{$result}</td>
            </tr>";
        }
        
        echo "</table>
            
            <script>
                // Auto print when loaded for PDF export
                if (window.location.search.includes('autoPrint=true')) {
                    window.onload = function() {
                        window.print();
                    };
                }
            </script>
        </body>
        </html>";
        
        exit;
    }
    
    /**
     * Generate test summary report as CSV
     */
    private function generateTestSummaryReportCsv($data) {
        $filename = 'test_summary_report_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add report header
        fputcsv($output, ['Test Summary Report']);
        fputcsv($output, ['Period', date('M d, Y', strtotime($data['start_date'])) . ' - ' . date('M d, Y', strtotime($data['end_date']))]);
        fputcsv($output, ['Total Tests', $data['total_tests']]);
        fputcsv($output, []);
        
        // Add test records data
        fputcsv($output, ['Test Records']);
        fputcsv($output, ['Test Date', 'Sample ID', 'Patient', 'Test Name', 'Section', 'Status', 'Result']);
        
        foreach ($data['tests'] as $test) {
            fputcsv($output, [
                date('M d, Y H:i', strtotime($test['test_date'])),
                $test['sample_id'],
                $test['patient_name'],
                $test['test_name'],
                $test['section'],
                $test['status'],
                $test['result'] ?? 'Not available'
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Respond with a success message
     */
    private function respondWithSuccess($message, $data = []) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
        exit;
    }
    
    /**
     * Respond with an error message
     */
    private function respondWithError($message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

// Initialize and handle the request
$reportController = new ReportController($connect);
$reportController->handleRequest();