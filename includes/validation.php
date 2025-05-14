<?php

function validatePatientData($data) {
    $errors = [];
    
    if (empty($data['full_name'])) {
        $errors[] = "Full name is required";
    } elseif (strlen($data['full_name']) > 100) {
        $errors[] = "Full name must be less than 100 characters";
    }
    
    if (empty($data['gender'])) {
        $errors[] = "Gender is required";
    } elseif (!in_array($data['gender'], ['Male', 'Female'])) {
        $errors[] = "Gender must be Male or Female";
    }
    
    if (!isset($data['age']) || $data['age'] === '') {
        $errors[] = "Age is required";
    } elseif (!is_numeric($data['age']) || $data['age'] < 0 || $data['age'] > 150) {
        $errors[] = "Age must be a valid number between 0 and 150";
    }
    
    if (empty($data['birth_date'])) {
        $errors[] = "Birth date is required";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['birth_date'])) {
        $errors[] = "Birth date must be in YYYY-MM-DD format";
    }
    
    return $errors;
}