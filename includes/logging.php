<?php
/**
 * Activity logging functions
 */

/**
 * Log user activity to the activity_logs table
 *
 * @param int $user_id User ID
 * @param string $action Action performed (view, create, edit, delete, etc.)
 * @param string $status Status of the action (success, failed)
 * @param string $details Additional details about the action
 * @return bool True on success, false on failure
 */
function log_activity($user_id, $action, $status, $details = '') {
    global $conn;
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Get location data (optional - you can implement IP geolocation if needed)
    $location = json_encode(['address' => 'Unknown']);
    
    // Prepare the SQL statement
    $sql = "INSERT INTO activity_logs (user_id, ip_address, location, action, status, details) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $user_id, $ip_address, $location, $action, $status, $details);
    
    // Execute the query
    $result = $stmt->execute();
    
    // Close the statement
    $stmt->close();
    
    return $result;
} 