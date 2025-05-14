<?php
/**
 * Format date and time to a readable format
 * 
 * @param string $dateTime The date and time to format
 * @param string $format The format to use (default: 'M d, Y H:i')
 * @return string The formatted date and time
 */
function formatDateTime($dateTime, $format = 'M d, Y H:i') {
    return date($format, strtotime($dateTime));
}

/**
 * Format test result for display
 * Add line breaks and highlight abnormal values
 * 
 * @param string $result The test result to format
 * @return string Formatted result
 */
function formatTestResult($result) {
    if (empty($result)) {
        return 'No results available';
    }
    
    $lines = explode(', ', $result);
    $formatted = '';
    
    foreach ($lines as $line) {
        $parts = explode(': ', $line, 2);
        if (count($parts) === 2) {
            list($parameter, $value) = $parts;
            
            // Highlight abnormal values (assuming they have markers)
            if (strpos($value, 'High') !== false || strpos($value, 'Low') !== false) {
                $formatted .= "<strong class='text-danger'>{$parameter}: {$value}</strong><br>";
            } else {
                $formatted .= "{$parameter}: {$value}<br>";
            }
        } else {
            $formatted .= "{$line}<br>";
        }
    }
    
    return $formatted;
}

/**
 * Get status badge HTML
 * 
 * @param string $status The status text
 * @return string HTML badge element
 */
function getStatusBadge($status) {
    $badgeClass = 'bg-secondary';
    
    switch ($status) {
        case 'Completed':
            $badgeClass = 'bg-success';
            break;
        case 'In Progress':
            $badgeClass = 'bg-info';
            break;
        case 'Pending':
            $badgeClass = 'bg-warning text-dark';
            break;
        case 'Approved':
            $badgeClass = 'bg-primary';
            break;
        case 'Rejected':
            $badgeClass = 'bg-danger';
            break;
    }
    
    return "<span class='badge {$badgeClass}'>{$status}</span>";
}

/**
 * Log system activity
 * 
 * @param string $action Description of the action
 * @param string $table_name Name of the affected table
 * @param int $record_id ID of the affected record
 * @param string $old_value Previous value (optional)
 * @param string $new_value New value (optional)
 * @return void
 */
function logActivity($action, $table_name, $record_id, $old_value = null, $new_value = null) {
    global $connect;
    
    try {
        $stmt = $connect->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, old_value, new_value, ip_address)
            VALUES (:user_id, :action, :table_name, :record_id, :old_value, :new_value, :ip_address)
        ");
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':table_name', $table_name);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':old_value', $old_value);
        $stmt->bindParam(':new_value', $new_value);
        $stmt->bindParam(':ip_address', $ipAddress);
        
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}