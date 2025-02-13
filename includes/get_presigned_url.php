<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

require_once "s3_handler.php";

// Get the object path from the request
$objectPath = $_GET['path'] ?? '';

if (empty($objectPath)) {
    die(json_encode(['success' => false, 'message' => 'No path provided']));
}

try {
    // Extract the actual file path from the full URL if needed
    if (strpos($objectPath, 'http') !== false) {
        // Extract the date and filename from the URL
        if (preg_match('/(\d{4}-\d{2}-\d{2}\/[^?]+)/', $objectPath, $matches)) {
            $objectKey = $matches[1];
        } else {
            throw new Exception('Invalid file path format');
        }
    } else {
        // If it's already a relative path, just clean it
        $objectKey = trim($objectPath, '/');
    }
    
    // For debugging
    error_log("Generating presigned URL for object key: " . $objectKey);
    
    // Generate presigned URL (valid for 1 hour)
    $presignedUrl = generatePresignedUrl($objectKey, 3600);
    
    // For debugging
    error_log("Generated presigned URL: " . $presignedUrl);

    // Get redirect parameters if present
    $redirect = $_GET['redirect'] ?? '';
    $file_id = $_GET['file_id'] ?? '';

    // If redirect is specified, redirect to the page with the presigned URL
    if ($redirect && $presignedUrl) {
        $redirectUrl = "../{$redirect}?url=" . urlencode($presignedUrl);
        if ($file_id) {
            $redirectUrl .= "&file=" . urlencode($file_id);
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    echo json_encode([
        'success' => true,
        'url' => $presignedUrl,
        'debug' => [
            'objectKey' => $objectKey,
            'originalPath' => $objectPath
        ]
    ]);
} catch (Exception $e) {
    error_log("Error generating presigned URL: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'objectKey' => $objectKey ?? '',
            'originalPath' => $objectPath
        ]
    ]);
}
