<?php
// controllers/RequestController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class RequestController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Handle request-related actions
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $this->createRequest();
                break;
            case 'approve':
                $this->approveRequest();
                break;
            case 'reject':
                $this->rejectRequest();
                break;
            case 'move_to_pending':
                $this->moveToPending();
                break;
            case 'get_details':
                $this->getRequestDetails();
                break;
            case 'search':
                $this->searchRequests();
                break;
            default:
                $this->respondWithError('Invalid action');
        }
    }
    
    /**
     * Create a new test request
     */
    private function createRequest() {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Validate required fields
        $requiredFields = ['patient_id', 'sample_id', 'station', 'test_name'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $this->respondWithError("Missing required field: $field");
                return;
            }
        }
        
        // Validate sample ID format (LAB-XXXXXX)
        if (!preg_match('/^LAB-\d{6}$/', $_POST['sample_id'])) {
            $this->respondWithError("Invalid Sample ID format. Must be LAB-XXXXXX where X is a digit.");
            return;
        }
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Check if sample ID already exists
            $checkStmt = $this->db->prepare("
                SELECT COUNT(*) FROM pending_requests WHERE sample_id = :sample_id
            ");
            $checkStmt->bindParam(':sample_id', $_POST['sample_id']);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                $this->db->rollBack();
                $this->respondWithError("Sample ID already exists");
                return;
            }
            
            // Get patient data
            $patientStmt = $this->db->prepare("
                SELECT * FROM patients WHERE patient_id = :id AND delete_status = 0
            ");
            $patientStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
            $patientStmt->execute();
            
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                $this->db->rollBack();
                $this->respondWithError("Patient not found");
                return;
            }
            
            // Insert request into pending_requests
            $stmt = $this->db->prepare("
                INSERT INTO pending_requests (
                    date, 
                    patient_id, 
                    sample_id, 
                    full_name, 
                    station, 
                    gender, 
                    age, 
                    birth_date, 
                    test_name, 
                    clinical_info, 
                    physician, 
                    status, 
                    requested_by,
                    urgency,
                    payment_status
                ) VALUES (
                    NOW(), 
                    :patient_id, 
                    :sample_id, 
                    :full_name, 
                    :station, 
                    :gender, 
                    :age, 
                    :birth_date, 
                    :test_name, 
                    :clinical_info, 
                    :physician, 
                    'Pending', 
                    :requested_by,
                    :urgency,
                    :payment_status
                )
            ");
            
            $stmt->bindParam(':patient_id', $_POST['patient_id'], PDO::PARAM_INT);
            $stmt->bindParam(':sample_id', $_POST['sample_id']);
            $stmt->bindParam(':full_name', $patient['full_name']);
            $stmt->bindParam(':station', $_POST['station']);
            $stmt->bindParam(':gender', $patient['gender']);
            $stmt->bindParam(':age', $patient['age'], PDO::PARAM_INT);
            $stmt->bindParam(':birth_date', $patient['birth_date']);
            $stmt->bindParam(':test_name', $_POST['test_name']);
            $clinicalInfo = $_POST['clinical_info'] ?? "N/A";
            $stmt->bindParam(':clinical_info', $clinicalInfo);
            $physician = $_POST['physician'] ?? "N/A";
            $stmt->bindParam(':physician', $physician);
            $stmt->bindParam(':requested_by', $_SESSION['username']);
            $urgency = $_POST['urgency'] ?? 'Routine';
            $stmt->bindParam(':urgency', $urgency);
            $paymentStatus = $_POST['payment_status'] ?? 'Unpaid';
            $stmt->bindParam(':payment_status', $paymentStatus);
            
            $stmt->execute();
            
            $requestId = $this->db->lastInsertId();
            
            // Commit transaction
            $this->db->commit();
            
            // Log the activity
            logActivity('Created test request', 'pending_requests', $requestId, null, json_encode($_POST));
            
            $this->respondWithSuccess('Request created successfully', [
                'request_id' => $requestId,
                'sample_id' => $_POST['sample_id']
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to create request: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to create request: " . $e->getMessage());
        }
    }
    
    /**
     * Approve a pending request
     */
    private function approveRequest() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        if (!isset($_POST['patient_id']) || empty($_POST['patient_id'])) {
            $this->respondWithError("Patient ID is required");
            return;
        }
        
        $requestId = $_POST['request_id'] ?? null;
        $patientId = $_POST['patient_id'];
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Get the pending request
            $query = "
                SELECT * FROM pending_requests 
                WHERE patient_id = :patient_id AND status = 'Pending'
            ";
            $params = [':patient_id' => $patientId];
            
            if ($requestId) {
                $query .= " AND id = :request_id";
                $params[':request_id'] = $requestId;
            }
            
            $stmt = $this->db->prepare($query . " LIMIT 1");
            $stmt->execute($params);
            
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $this->db->rollBack();
                $this->respondWithError("No pending request found");
                return;
            }
            
            // Insert into approved requests (request_list)
            $insertStmt = $this->db->prepare("
                INSERT INTO request_list (
                    patient_id, 
                    sample_id, 
                    patient_name, 
                    station_ward, 
                    gender, 
                    age, 
                    birth_date, 
                    request_date, 
                    test_name, 
                    clinical_info, 
                    physician, 
                    status, 
                    created_at
                ) VALUES (
                    :patient_id, 
                    :sample_id, 
                    :patient_name, 
                    :station_ward, 
                    :gender, 
                    :age, 
                    :birth_date, 
                    :request_date, 
                    :test_name, 
                    :clinical_info, 
                    :physician, 
                    'Approved', 
                    NOW()
                )
            ");
            
            $insertStmt->bindParam(':patient_id', $request['patient_id'], PDO::PARAM_INT);
            $insertStmt->bindParam(':sample_id', $request['sample_id']);
            $insertStmt->bindParam(':patient_name', $request['full_name']);
            $insertStmt->bindParam(':station_ward', $request['station']);
            $insertStmt->bindParam(':gender', $request['gender']);
            $insertStmt->bindParam(':age', $request['age'], PDO::PARAM_INT);
            $insertStmt->bindParam(':birth_date', $request['birth_date']);
            $insertStmt->bindParam(':request_date', $request['date']);
            $insertStmt->bindParam(':test_name', $request['test_name']);
            $insertStmt->bindParam(':clinical_info', $request['clinical_info']);
            $insertStmt->bindParam(':physician', $request['physician']);
            
            $insertStmt->execute();
            
            $approvedRequestId = $this->db->lastInsertId();
            
            // Update pending request status
            $updateStmt = $this->db->prepare("
                UPDATE pending_requests 
                SET status = 'Approved', 
                    processed_at = NOW(),
                    processed_by = :user_id
                WHERE id = :id
            ");
            
            $updateStmt->bindParam(':id', $request['id'], PDO::PARAM_INT);
            $updateStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            
            $updateStmt->execute();
            
            // Commit transaction
            $this->db->commit();
            
            // Log the activity
            logActivity('Approved test request', 'pending_requests', $request['id'], 
                      json_encode(['status' => 'Pending']), 
                      json_encode(['status' => 'Approved']));
            
            $this->respondWithSuccess('Request approved successfully', [
                'request_id' => $approvedRequestId,
                'patient_id' => $request['patient_id'],
                'patient_name' => $request['full_name'],
                'sample_id' => $request['sample_id']
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to approve request: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to approve request: " . $e->getMessage());
        }
    }
    
    /**
     * Reject a pending request
     */
    private function rejectRequest() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        if (!isset($_POST['request_id']) || empty($_POST['request_id'])) {
            $this->respondWithError("Request ID is required");
            return;
        }
        
        if (!isset($_POST['reject_reason']) || empty($_POST['reject_reason'])) {
            $this->respondWithError("Reason for rejection is required");
            return;
        }
        
        $requestId = $_POST['request_id'];
        $rejectReason = $_POST['reject_reason'];
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Get the pending request
            $stmt = $this->db->prepare("
                SELECT * FROM pending_requests WHERE id = :id AND status = 'Pending'
            ");
            $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $stmt->execute();
            
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $this->db->rollBack();
                $this->respondWithError("No pending request found with ID $requestId");
                return;
            }
            
            // Insert into rejected_requests table
            $insertStmt = $this->db->prepare("
                INSERT INTO rejected_requests (
                    request_id, 
                    patient_id, 
                    sample_id, 
                    rejection_reason, 
                    rejected_by, 
                    rejected_at
                ) VALUES (
                    :request_id, 
                    :patient_id, 
                    :sample_id, 
                    :rejection_reason, 
                    :rejected_by, 
                    NOW()
                )
            ");
            
            $insertStmt->bindParam(':request_id', $request['id'], PDO::PARAM_INT);
            $insertStmt->bindParam(':patient_id', $request['patient_id'], PDO::PARAM_INT);
            $insertStmt->bindParam(':sample_id', $request['sample_id']);
            $insertStmt->bindParam(':rejection_reason', $rejectReason);
            $insertStmt->bindParam(':rejected_by', $_SESSION['username']);
            
            $insertStmt->execute();
            
            // Update pending request status
            $updateStmt = $this->db->prepare("
                UPDATE pending_requests 
                SET status = 'Rejected', 
                    processed_at = NOW(),
                    processed_by = :user_id,
                    reject_reason = :reject_reason
                WHERE id = :id
            ");
            
            $updateStmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $updateStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $updateStmt->bindParam(':reject_reason', $rejectReason);
            
            $updateStmt->execute();
            
            // Commit transaction
            $this->db->commit();
            
            // Log the activity
            logActivity('Rejected test request', 'pending_requests', $requestId, 
                      json_encode(['status' => 'Pending']), 
                      json_encode(['status' => 'Rejected', 'reason' => $rejectReason]));
            
            $this->respondWithSuccess('Request rejected successfully');
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to reject request: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to reject request: " . $e->getMessage());
        }
    }
    
    /**
     * Move an approved request back to pending
     */
    private function moveToPending() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        if (!isset($_POST['request_id']) || empty($_POST['request_id'])) {
            $this->respondWithError("Request ID is required");
            return;
        }
        
        $requestId = $_POST['request_id'];
        
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Get the approved request
            $stmt = $this->db->prepare("
                SELECT * FROM request_list WHERE id = :id AND status = 'Approved'
            ");
            $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $stmt->execute();
            
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                $this->db->rollBack();
                $this->respondWithError("No approved request found with ID $requestId");
                return;
            }
            
            // Check if this request already has test results
            $checkTestsStmt = $this->db->prepare("
                SELECT COUNT(*) FROM test_records WHERE sample_id = :sample_id
            ");
            $checkTestsStmt->bindParam(':sample_id', $request['sample_id']);
            $checkTestsStmt->execute();
            
            if ($checkTestsStmt->fetchColumn() > 0) {
                $this->db->rollBack();
                $this->respondWithError("Cannot move request back to pending: It already has test results");
                return;
            }
            
            // Update or insert into pending_requests
            $checkPendingStmt = $this->db->prepare("
                SELECT id FROM pending_requests WHERE sample_id = :sample_id
            ");
            $checkPendingStmt->bindParam(':sample_id', $request['sample_id']);
            $checkPendingStmt->execute();
            
            $pendingRequest = $checkPendingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pendingRequest) {
                // Update existing pending request
                $updateStmt = $this->db->prepare("
                    UPDATE pending_requests 
                    SET status = 'Pending', 
                        processed_at = NULL,
                        processed_by = NULL
                    WHERE id = :id
                ");
                $updateStmt->bindParam(':id', $pendingRequest['id'], PDO::PARAM_INT);
                $updateStmt->execute();
            } else {
                // Insert new pending request
                $insertStmt = $this->db->prepare("
                    INSERT INTO pending_requests (
                        date, 
                        patient_id, 
                        sample_id, 
                        full_name, 
                        station, 
                        gender, 
                        age, 
                        birth_date, 
                        test_name, 
                        clinical_info, 
                        physician, 
                        status, 
                        requested_by
                    ) VALUES (
                        :request_date, 
                        :patient_id, 
                        :sample_id, 
                        :patient_name, 
                        :station_ward, 
                        :gender, 
                        :age, 
                        :birth_date, 
                        :test_name, 
                        :clinical_info, 
                        :physician, 
                        'Pending', 
                        :requested_by
                    )
                ");
                
                $insertStmt->bindParam(':request_date', $request['request_date']);
                $insertStmt->bindParam(':patient_id', $request['patient_id'], PDO::PARAM_INT);
                $insertStmt->bindParam(':sample_id', $request['sample_id']);
                $insertStmt->bindParam(':patient_name', $request['patient_name']);
                $insertStmt->bindParam(':station_ward', $request['station_ward']);
                $insertStmt->bindParam(':gender', $request['gender']);
                $insertStmt->bindParam(':age', $request['age'], PDO::PARAM_INT);
                $insertStmt->bindParam(':birth_date', $request['birth_date']);
                $insertStmt->bindParam(':test_name', $request['test_name']);
                $insertStmt->bindParam(':clinical_info', $request['clinical_info']);
                $insertStmt->bindParam(':physician', $request['physician']);
                $insertStmt->bindParam(':requested_by', $_SESSION['username']);
                
                $insertStmt->execute();
            }
            
            // Delete from request_list
            $deleteStmt = $this->db->prepare("DELETE FROM request_list WHERE id = :id");
            $deleteStmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Commit transaction
            $this->db->commit();
            
            // Log the activity
            logActivity('Moved request to pending', 'request_list', $requestId, 
                      json_encode(['status' => 'Approved']), 
                      json_encode(['status' => 'Pending']));
            
            $this->respondWithSuccess('Request moved back to pending successfully');
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to move request to pending: " . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error: " . $e->getMessage());
            $this->respondWithError("Failed to move request to pending: " . $e->getMessage());
        }
    }
    
    /**
     * Get request details
     */
    private function getRequestDetails() {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            $this->respondWithError("Request ID is required");
            return;
        }
        
        $requestId = $_GET['id'];
        
        try {
            // Check in pending_requests first
            $pendingStmt = $this->db->prepare("
                SELECT * FROM pending_requests WHERE id = :id
            ");
            $pendingStmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $pendingStmt->execute();
            
            $request = $pendingStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                // If not found in pending_requests, check request_list
                $approvedStmt = $this->db->prepare("
                    SELECT * FROM request_list WHERE id = :id
                ");
                $approvedStmt->bindParam(':id', $requestId, PDO::PARAM_INT);
                $approvedStmt->execute();
                
                $request = $approvedStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request) {
                    $this->respondWithError("Request not found");
                    return;
                }
                
                // Use consistent field names
                if (isset($request['patient_name']) && !isset($request['full_name'])) {
                    $request['full_name'] = $request['patient_name'];
                }
                
                if (isset($request['station_ward']) && !isset($request['station'])) {
                    $request['station'] = $request['station_ward'];
                }
            }
            
            $this->respondWithSuccess('Request details retrieved', ['request' => $request]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to get request details: " . $e->getMessage());
        }
    }
    
    /**
     * Search for requests
     */
    private function searchRequests() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            $this->respondWithError("Search query is required");
            return;
        }
        
        try {
            $query = '%' . $_GET['query'] . '%';
            
            // Search in pending_requests
            $pendingStmt = $this->db->prepare("
                SELECT 
                    id,
                    date as request_date,
                    patient_id,
                    sample_id,
                    full_name as patient_name,
                    station as station_ward,
                    test_name,
                    status,
                    'pending_requests' as source
                FROM pending_requests 
                WHERE (full_name LIKE :query OR sample_id LIKE :query OR patient_id LIKE :query)
                LIMIT 10
            ");
            
            $pendingStmt->bindParam(':query', $query);
            $pendingStmt->execute();
            
            $pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Search in request_list
            $approvedStmt = $this->db->prepare("
                SELECT 
                    id,
                    request_date,
                    patient_id,
                    sample_id,
                    patient_name,
                    station_ward,
                    test_name,
                    status,
                    'request_list' as source
                FROM request_list 
                WHERE (patient_name LIKE :query OR sample_id LIKE :query OR patient_id LIKE :query)
                LIMIT 10
            ");
            
            $approvedStmt->bindParam(':query', $query);
            $approvedStmt->execute();
            
            $approvedRequests = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Combine results
            $requests = array_merge($pendingRequests, $approvedRequests);
            
            // Sort by date (newest first)
            usort($requests, function($a, $b) {
                return strtotime($b['request_date']) - strtotime($a['request_date']);
            });
            
            $this->respondWithSuccess('Search results', ['requests' => $requests]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to search requests: " . $e->getMessage());
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
$requestController = new RequestController($connect);
$requestController->handleRequest();