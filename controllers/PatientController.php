<?php
// controllers/PatientController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class PatientController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Handle patient-related actions
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $this->addPatient();
                break;
            case 'update':
                $this->updatePatient();
                break;
            case 'delete':
                $this->deletePatient();
                break;
            case 'get':
                $this->getPatient();
                break;
            case 'search':
                $this->searchPatients();
                break;
            default:
                $this->respondWithError('Invalid action');
        }
    }
    
    /**
     * Add a new patient
     */
    private function addPatient() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Validate required fields
        $requiredFields = ['full_name', 'gender', 'age', 'birth_date'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $this->respondWithError("Missing required field: $field");
                return;
            }
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO patients (full_name, gender, age, birth_date, delete_status) 
                VALUES (:full_name, :gender, :age, :birth_date, 0)
            ");
            
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':gender', $_POST['gender']);
            $stmt->bindParam(':age', $_POST['age'], PDO::PARAM_INT);
            $stmt->bindParam(':birth_date', $_POST['birth_date']);
            
            $stmt->execute();
            
            $patientId = $this->db->lastInsertId();
            
            // Log the activity
            logActivity('Added patient', 'patients', $patientId, null, json_encode($_POST));
            
            $this->respondWithSuccess('Patient added successfully', [
                'patient_id' => $patientId,
                'full_name' => $_POST['full_name'],
                'gender' => $_POST['gender'],
                'age' => $_POST['age'],
                'birth_date' => $_POST['birth_date']
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to add patient: " . $e->getMessage());
        }
    }
    
    /**
     * Update an existing patient
     */
    private function updatePatient() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        // Validate required fields
        $requiredFields = ['patient_id', 'full_name', 'gender', 'age', 'birth_date'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $this->respondWithError("Missing required field: $field");
                return;
            }
        }
        
        try {
            // Get current patient data for logging
            $getStmt = $this->db->prepare("SELECT * FROM patients WHERE patient_id = :id");
            $getStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
            $getStmt->execute();
            $oldData = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                $this->respondWithError("Patient not found");
                return;
            }
            
            $stmt = $this->db->prepare("
                UPDATE patients 
                SET full_name = :full_name, gender = :gender, age = :age, birth_date = :birth_date 
                WHERE patient_id = :patient_id
            ");
            
            $stmt->bindParam(':patient_id', $_POST['patient_id'], PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $_POST['full_name']);
            $stmt->bindParam(':gender', $_POST['gender']);
            $stmt->bindParam(':age', $_POST['age'], PDO::PARAM_INT);
            $stmt->bindParam(':birth_date', $_POST['birth_date']);
            
            $stmt->execute();
            
            // Log the activity
            logActivity('Updated patient', 'patients', $_POST['patient_id'], 
                      json_encode($oldData), json_encode($_POST));
            
            $this->respondWithSuccess('Patient updated successfully', [
                'patient_id' => $_POST['patient_id'],
                'full_name' => $_POST['full_name'],
                'gender' => $_POST['gender'],
                'age' => $_POST['age'],
                'birth_date' => $_POST['birth_date']
            ]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to update patient: " . $e->getMessage());
        }
    }
    
    /**
     * Delete a patient (soft delete - set delete_status to 1)
     */
    private function deletePatient() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $this->respondWithError('Invalid CSRF token');
            return;
        }
        
        if (!isset($_POST['patient_id']) || empty($_POST['patient_id'])) {
            $this->respondWithError("Patient ID is required");
            return;
        }
        
        try {
            // Get current patient data for logging
            $getStmt = $this->db->prepare("SELECT * FROM patients WHERE patient_id = :id");
            $getStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
            $getStmt->execute();
            $oldData = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                $this->respondWithError("Patient not found");
                return;
            }
            
            // Check if patient has any pending requests or test records
            $checkRequestsStmt = $this->db->prepare("
                SELECT COUNT(*) FROM pending_requests WHERE patient_id = :id
            ");
            $checkRequestsStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
            $checkRequestsStmt->execute();
            
            $checkTestsStmt = $this->db->prepare("
                SELECT COUNT(*) FROM test_records WHERE patient_id = :id
            ");
            $checkTestsStmt->bindParam(':id', $_POST['patient_id'], PDO::PARAM_INT);
            $checkTestsStmt->execute();
            
            if ($checkRequestsStmt->fetchColumn() > 0 || $checkTestsStmt->fetchColumn() > 0) {
                $this->respondWithError("Cannot delete patient with existing requests or test records");
                return;
            }
            
            // Soft delete - set delete_status to 1
            $stmt = $this->db->prepare("
                UPDATE patients SET delete_status = 1 WHERE patient_id = :patient_id
            ");
            
            $stmt->bindParam(':patient_id', $_POST['patient_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the activity
            logActivity('Deleted patient', 'patients', $_POST['patient_id'], 
                      json_encode($oldData), null);
            
            $this->respondWithSuccess('Patient deleted successfully');
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to delete patient: " . $e->getMessage());
        }
    }
    
    /**
     * Get a single patient
     */
    private function getPatient() {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            $this->respondWithError("Patient ID is required");
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM patients WHERE patient_id = :id AND delete_status = 0
            ");
            
            $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                $this->respondWithError("Patient not found");
                return;
            }
            
            $this->respondWithSuccess('Patient found', ['patient' => $patient]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to get patient: " . $e->getMessage());
        }
    }
    
    /**
     * Search for patients
     */
    private function searchPatients() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            $this->respondWithError("Search query is required");
            return;
        }
        
        try {
            $query = '%' . $_GET['query'] . '%';
            
            $stmt = $this->db->prepare("
                SELECT * FROM patients 
                WHERE (full_name LIKE :query OR patient_id LIKE :query) 
                AND delete_status = 0
                LIMIT 10
            ");
            
            $stmt->bindParam(':query', $query);
            $stmt->execute();
            
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->respondWithSuccess('Search results', ['patients' => $patients]);
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->respondWithError("Failed to search patients: " . $e->getMessage());
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
$patientController = new PatientController($connect);
$patientController->handleRequest();