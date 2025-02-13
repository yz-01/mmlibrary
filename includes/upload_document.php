<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Database connection and S3 handler
require_once "db/config.php";
require_once "s3_handler.php";

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
$allowedMimeTypes = [
    'image/jpeg',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/pdf'
];
if (!in_array($fileType, $allowedMimeTypes)) {
    die(json_encode(['success' => false, 'message' => 'Only PDF, JPEG, XLSX, and DOCX files are allowed']));
}

// Validate file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'xlsx', 'docx', 'pdf'];
if (!in_array($fileExtension, $allowedExtensions)) {
    die(json_encode(['success' => false, 'message' => 'Only PDF, JPEG, XLSX, and DOCX files are allowed']));
}

// Validate file size (10MB max)
// $maxSize = 10 * 1024 * 1024; // 10MB in bytes
// if ($file['size'] > $maxSize) {
//     die(json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']));
// }

try {
    // Generate unique filename with extension
    $uniqueFilename = uniqid() . '.' . $fileExtension;
    
    // Create a folder name based on current date
    $folder = date('Y-m-d');
    
    // Upload to S3 with the unique filename
    $s3_url = uploadFileToS3(
        $file['tmp_name'],
        S3_BUCKET,
        $folder,
        S3_REGION,
        S3_ENDPOINT,
        S3_ACCESS_KEY,
        S3_SECRET_KEY,
        $uniqueFilename  // Pass the unique filename with extension
    );

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
    ) VALUES (?, ?, ?, ?, ?, NULL, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", 
        $name,           // name
        $fileExtension,  // type
        $s3_url,        // path (now stores S3 URL)
        $parent_directory, // parent_directory
        $description,    // description
        $_SESSION['id'], // created_by
        $_SESSION['id']  // modified_by
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}

$conn->close();
?>