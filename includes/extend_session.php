<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set proper headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include database connection
require_once 'db/config.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Get user ID from session
$user_id = isset($_SESSION["id"]) ? $_SESSION["id"] : null;

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$ip_address = getClientIP();

// Get location information (can be enhanced with IP geolocation service)
$location = json_encode([
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
    'page' => $_SERVER['REQUEST_URI']
]);

// Extend session time (default 30 minutes)
$minutes = isset($_SESSION["expire_time"]) ? $_SESSION["expire_time"] : 30;
$_SESSION["expire_timestamp"] = time() + ($minutes * 60);

// Log the session extension
$action = "Session Extension";
$status = "Success";
$details = "Session extended for " . $minutes . " minutes";

// Always extend the session first, even if logging fails
$new_expire_time = $_SESSION["expire_timestamp"];

// Try to log the activity, but don't fail if it doesn't work
try {
    // Prepare and execute SQL statement
    if ($stmt = $conn->prepare("INSERT INTO activity_logs (user_id, ip_address, location, action, status, details) VALUES (?, ?, ?, ?, ?, ?)")) {
        $stmt->bind_param("isssss", $user_id, $ip_address, $location, $action, $status, $details);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Session extended successfully', 'new_expire_time' => $new_expire_time]);
        } else {
            echo json_encode(['status' => 'warning', 'message' => 'Session extended but logging failed: ' . $stmt->error, 'new_expire_time' => $new_expire_time]);
        }
        
        $stmt->close();
    } else {
        // Prepare statement failed
        echo json_encode(['status' => 'warning', 'message' => 'Session extended but logging failed: ' . $conn->error, 'new_expire_time' => $new_expire_time]);
    }
} catch (Exception $e) {
    // Catch any exceptions and still return success for the session extension
    echo json_encode(['status' => 'warning', 'message' => 'Session extended but logging failed: ' . $e->getMessage(), 'new_expire_time' => $new_expire_time]);
} finally {
    // Always close the connection
    $conn->close();
}
?>
