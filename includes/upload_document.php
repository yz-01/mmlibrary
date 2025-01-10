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

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'message' => 'No file uploaded or upload error']));
}

// Get form data
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$parent_directory = $_POST['parent_directory'] ?? '';
$file = $_FILES['file'];

// Validate file type
$fileType = mime_content_type($file['tmp_name']);
if ($fileType !== 'application/pdf') {
    die(json_encode(['success' => false, 'message' => 'Only PDF files are allowed']));
}

// Validate file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileExtension !== 'pdf') {
    die(json_encode(['success' => false, 'message' => 'Only PDF files are allowed']));
}

// Validate file size (5MB max)
$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    die(json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']));
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/documents/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$uniqueFilename = uniqid() . '.pdf';
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    die(json_encode(['success' => false, 'message' => 'Failed to save file']));
}

// Save file information to database
$sql = "INSERT INTO folders_files (
    name, 
    type, 
    path, 
    parent_directory, 
    description, 
    content,
    created_by, 
    modified_by
) VALUES (?, 'pdf', ?, ?, ?, NULL, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssii", 
    $name,          // name
    $uniqueFilename, // path
    $parent_directory, // parent_directory
    $description,    // description
    $_SESSION['id'], // created_by
    $_SESSION['id']  // modified_by
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    // If database insert fails, delete the uploaded file
    unlink($uploadPath);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$conn->close();
?> 