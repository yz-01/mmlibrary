<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Database connection
require_once "db/config.php";
// Include logging functions
require_once "logging.php";

// Get the action and file details
$action = $_POST['action'] ?? '';
$file_name = $_POST['file_name'] ?? '';
$file_id = $_POST['file_id'] ?? '';

// Only proceed if we have an action and file name
if (!empty($action) && !empty($file_name)) {
    // Sanitize inputs
    $action = htmlspecialchars($action);
    $file_name = htmlspecialchars($file_name);
    $file_id = htmlspecialchars($file_id);
    
    // Create details string
    $details = "File: $file_name";
    if (!empty($file_id)) {
        $details .= " (ID: $file_id)";
    }
    
    // Log the action
    log_activity($_SESSION['id'], $action, 'success', $details);
    
    // Return success response
    echo json_encode(['success' => true]);
} else {
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
}

// Close database connection
$conn->close();
?> 