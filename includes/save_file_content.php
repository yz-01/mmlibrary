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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$file_id = $data['file_id'] ?? null;
$content = $data['content'] ?? '[]'; // This will be a JSON string of fields

if (!$file_id) {
    die(json_encode(['success' => false, 'message' => 'File ID is required']));
}

// Validate that the content is valid JSON
if (!json_decode($content)) {
    die(json_encode(['success' => false, 'message' => 'Invalid content format']));
}

// Update the file content
$sql = "UPDATE folders_files SET content = ?, modified_by = ?, modified_at = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $content, $_SESSION['id'], $file_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$conn->close();
?> 