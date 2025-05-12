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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$file_id = $data['file_id'] ?? null;
$file_name = $data['file_name'] ?? 'Unknown File';
$content = $data['content'] ?? '[]'; // This will be a JSON string of fields

if (!$file_id) {
    // Log the failed attempt
    log_activity($_SESSION['id'], 'save_spreadsheet', 'failed', 'Missing file ID');
    die(json_encode(['success' => false, 'message' => 'File ID is required']));
}

// Validate that the content is valid JSON
if (!json_decode($content)) {
    // Log the failed attempt with invalid content
    log_activity($_SESSION['id'], 'save_spreadsheet', 'failed', "Invalid content format for file: $file_name (ID: $file_id)");
    die(json_encode(['success' => false, 'message' => 'Invalid content format']));
}

// Update the file content
$sql = "UPDATE folders_files SET content = ?, modified_by = ?, modified_at = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $content, $_SESSION['id'], $file_id);

if ($stmt->execute()) {
    // Log successful save
    $fields_count = count(json_decode($content, true));
    $details = "Saved changes to spreadsheet: $file_name (ID: $file_id) with $fields_count fields";
    log_activity($_SESSION['id'], 'save_spreadsheet', 'success', $details);
    
    echo json_encode(['success' => true]);
} else {
    // Log failed save
    $details = "Failed to save changes to spreadsheet: $file_name (ID: $file_id). Error: " . $stmt->error;
    log_activity($_SESSION['id'], 'save_spreadsheet', 'failed', $details);
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$conn->close();
?> 