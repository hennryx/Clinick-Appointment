<?php
// controllers/TestController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class TestController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Handle test-related actions
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'save_result':
                $this->saveTestResult();
                break;
            case 'update_result':
                $this->updateTestResult();
                break;
            case 'delete':
                $this->deleteTest();
                break;
            case 'get_details':
                $this->getTestDetails();
                break;
            case 'search':
                $this->searchTests();
                break;
            default:
                $this->respondWithError('Invalid action');
        }
    }
    
    /**
     * Save a new test result or create a new test record
     */
    private function saveTestResult() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Check if this is an update of an existing test or a new test
        $testId = $_POST['test_id'] ?? null;
        $requestId = $_POST['request_id'] ?? null;
        
        // Validate required fields
        if ((!$testId && !$requestId) || !isset($_POST['patient_id']) || empty($_POST['patient_id']) || 
            !isset($_POST['sample_id']) || empty($_POST['sample_id']) || 
            !isset($_POST['test_name']) || empty($_POST['test_name'])) {
            $this->respondWithError("Missing required fields");
            return;
        }
        
        // Prepare parameters (combine test parameters for result field)
        $result = '';
        if (isset($_POST['parameters']) && is_array($_POST['parameters'])) {
            $parts = [];
            foreach ($_POST['parameters'] as $key => $value) {
                $parts[] = ucfirst($key) . ': ' . $value;
            }
            $result = implode(', ', $parts);
        } else {
            $result = $_POST['result'] ?? '';
        }
        
        $status = $_POST['status'] ?? 'In Progress';
        $remarks = $_POST['remarks'] ?? '';
        $performedBy = $_POST['performed_by'] ?? $_SESSION['username'];
        
        try {
            $this->db->beginTransaction();
            
            if ($testId) {
                // Update existing test record
                $stmt = $this->db->prepare("
                    UPDATE test_records 
                    SET result = :result, 
                        status = :status, 
                        remarks = :remarks, 
                        performed_by = :performed_by, 
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':id', $testId, PDO::PARAM_INT);
                $stmt->bindParam(':result', $result);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':remarks', $remarks);
                $stmt->bindParam(':performed_by', $performedBy);
                
                $stmt->execute();
                
                // Get the test details for the response
                $getStmt = $this->db->prepare("SELECT * FROM test_records WHERE id = :id");
                $getStmt->bindParam(':id', $testId, PDO::PARAM_INT);
                $getStmt->execute();
                $test = $getStmt->fetch(PDO::FETCH_ASSOC);
                
                // If test is completed, update the request status if it exists
                if ($status === 'Completed') {
                    $updateRequestStmt = $this->db->prepare("
                        UPDATE request_list 
                        SET status = 'Completed' 
                        WHERE sample_id = :sample_id AND test_name = :test_name
                    ");
                    
                    $updateRequestStmt->bindParam(':sample_id', $test['sample_id']);
                    $updateRequestStmt->bindParam(':test_name', $test['test_name']);
                    $updateRequestStmt->execute();
                }
                
                // Log the activity
                logActivity('Updated test result', 'test_records', $testId, null, json_encode([
                    'result' => $result,
                    'status' => $status,
                    'remarks' => $remarks
                ]));
                
                $this->db->commit();
                
                $this->respondWithSuccess('Test result updated successfully', [
                    'test_id' => $testId,
                    'test' => $test
                ]);
            } else {
                // Create new test record
                // First check if a record already exists for this sample and test
                $checkStmt = $this->db->prepare("
                    SELECT id FROM test_records 
                    WHERE sample_id = :sample_id AND test_name = :test_name
                ");
                
                $checkStmt->bindParam(':sample_id', $_POST['sample_id']);
                $checkStmt->bindParam(':test_name', $_POST['test_name']);
                $checkStmt->execute();
                
                $existingTest = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingTest) {
                    $this->db->rollBack();
                    $this->respondWithError("A test record already exists for this sample and test");
                    return;
                }
                
                // Insert new test record
                $stmt = $this->db->prepare("
                    INSERT INTO test_records (
                        patient_id, 
                        patient_name, 
                        sample_id, 
                        test_name, 
                        section, 
                        test_date, 
                        result, 
                        status, 
                        remarks, 
                        performed_by, 
                        created_at
                    ) VALUES (
                        :patient_id, 
                        :patient_name, 
                        :sample_id, 
                        :test_name, 
                        :section, 
                        NOW(), 
                        :result, 
                        :status, 
                        :remarks, 
                        :performed_by, 
                        NOW()
                    )
                ");
                
                // Get patient name if not provided
                if (!isset($_POST['patient_name']) || empty($_POST['patient_name'])) {
                    $patientStmt = $this->db->prepare("
                        SELECT full_name FROM patients WHERE patient_id = :id
                    ");
                    $patientStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
                    $patientStmt->execute();
                    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
                    $patientName = $patient ? $patient['full_name'] : 'Unknown';
                } else {
                    $patientName = $_POST['patient_name'];
                }
                
                $stmt->bindParam(':patient_id', $_POST['patient_id'], PDO::PARAM_INT);
                $stmt->bindParam(':patient_name', $patientName);
                $stmt->bindParam(':sample_id', $_POST['sample_id']);
                $stmt->bindParam(':test_name', $_POST['test_name']);
                $stmt->bindParam(':section', $_POST['section']);
                $stmt->bindParam(':result', $result);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':remarks', $remarks);
                $stmt->bindParam(':performed_by', $performedBy);
                
                $stmt->execute();
                
                $newTestId = $this->db->lastInsertId();
                
                // If test is completed, update the request status if it exists
                if ($status === 'Completed') {
                    $updateRequestStmt = $this->db->prepare("
                        UPDATE request_list 
                        SET status = 'Completed' 
                        WHERE sample_id = :sample_id AND test_name = :test_name
                    ");
                    
                    $updateRequestStmt->bindParam(':sample_id', $_POST['sample_id']);
                    $updateRequestStmt->bindParam(':test_name', $_POST['test_name']);
                    $updateRequestStmt->execute();
                }
                
                // Log the activity
                logActivity('Created test record', 'test_records', $newTestId, null, json_encode([
                    'patient_id' => $_POST['patient_id'],
                    'sample_id' => $_POST['sample_id'],
                    'test_name' => $_POST['test_name'],
                    'status' => $status
                ]));
                
                $this->db->commit();
                
                $this->respondWithSuccess('Test result saved successfully', [
                    'test_id' => $newTestId
                ]);
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to save test result: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to save test result: " . $e->getMessage());
        }
    }
    
    /**
     * Update an existing test result
     * (Redundant - kept for compatibility, same as saveTestResult with testId)
     */
    private function updateTestResult() {
        $this->saveTestResult();
    }
    
    /**
     * Delete a test record
     */
    private function deleteTest() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        if (!isset($_POST['test_id']) || empty($_POST['test_id'])) {
            $this->respondWithError("Test ID is required");
            return;
        }
        
        $testId = $_POST['test_id'];
        
        try {
            $this->db->beginTransaction();
            
            // Get test details before deletion for logging
            $getStmt = $this->db->prepare("SELECT * FROM test_records WHERE id = :id");
            $getStmt->bindParam(':id', $testId, PDO::PARAM_INT);
            $getStmt->execute();
            $test = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                $this->db->rollBack();
                $this->respondWithError("Test not found");
                return;
            }
            
            // Check if test is completed - don't allow deletion of completed tests
            if ($test['status'] === 'Completed') {
                $this->db->rollBack();
                $this->respondWithError("Cannot delete a completed test");
                return;
            }
            
            // Delete the test record
            $stmt = $this->db->prepare("DELETE FROM test_records WHERE id = :id");
            $stmt->bindParam(':id', $testId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the activity
            logActivity('Deleted test record', 'test_records', $testId, json_encode($test), null);
            
            $this->db->commit();
            
            $this->respondWithSuccess('Test deleted successfully');
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to delete test: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to delete test: " . $e->getMessage());
        }
    }
    
    /**
     * Get test details
     */
    private function getTestDetails() {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            $this->respondWithError("Test ID is required");
            return;
        }
        
        $testId = $_GET['id'];
        
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, p.gender, p.age, p.birth_date 
                FROM test_records t
                LEFT JOIN patients p ON t.patient_id = p.patient_id
                WHERE t.id = :id
            ");
            $stmt->bindParam(':id', $testId, PDO::PARAM_INT);
            $stmt->execute();
            
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                $this->respondWithError("Test not found");
                return;
            }
            
            $this->respondWithSuccess('Test details retrieved', ['test' => $test]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to get test details: " . $e->getMessage());
        }
    }
    
    /**
     * Search for tests
     */
    private function searchTests() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            $this->respondWithError("Search query is required");
            return;
        }
        
        try {
            $query = '%' . $_GET['query'] . '%';
            
            $stmt = $this->db->prepare("
                SELECT * FROM test_records 
                WHERE (patient_name LIKE :query OR sample_id LIKE :query OR test_name LIKE :query)
                ORDER BY test_date DESC
                LIMIT 10
            ");
            
            $stmt->bindParam(':query', $query);
            $stmt->execute();
            
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->respondWithSuccess('Search results', ['tests' => $tests]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to search tests: " . $e->getMessage());
        }
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
$testController = new TestController($connect);
$testController->handleRequest();