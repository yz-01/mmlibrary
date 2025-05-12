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
// Include logging functions
require_once "logging.php";

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    // Log failed upload attempt
    log_activity($_SESSION['id'], 'upload_document', 'failed', 'No file uploaded or upload error');
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
    // Log invalid file type upload attempt
    $details = "Invalid file type attempted upload: $name, type: $fileType";
    log_activity($_SESSION['id'], 'upload_document', 'failed', $details);
    die(json_encode(['success' => false, 'message' => 'Only PDF, JPEG, XLSX, and DOCX files are allowed']));
}

// Validate file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'xlsx', 'docx', 'pdf'];
if (!in_array($fileExtension, $allowedExtensions)) {
    // Log invalid file extension upload attempt
    $details = "Invalid file extension attempted upload: $name, extension: $fileExtension";
    log_activity($_SESSION['id'], 'upload_document', 'failed', $details);
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
    $stmt->bind_param("sssssii", 
        $name,           // name
        $fileExtension,  // type
        $s3_url,        // path (now stores S3 URL)
        $parent_directory, // parent_directory
        $description,    // description
        $_SESSION['id'], // created_by
        $_SESSION['id']  // modified_by
    );

    if ($stmt->execute()) {
        // Log successful file upload
        $location = $parent_directory ? $parent_directory : 'root directory';
        $details = "Uploaded file: $name ($fileExtension) to $location, size: " . round($file['size']/1024, 2) . " KB";
        log_activity($_SESSION['id'], 'upload_document', 'success', $details);
        
        echo json_encode(['success' => true]);
    } else {
        // Log database error
        $details = "Database error during upload: " . $stmt->error;
        log_activity($_SESSION['id'], 'upload_document', 'failed', $details);
        
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

} catch (Exception $e) {
    // Log exception during upload
    $details = "Upload error: " . $e->getMessage();
    log_activity($_SESSION['id'], 'upload_document', 'failed', $details);
    
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}

$conn->close();
?>