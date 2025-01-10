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
require_once "includes/db/config.php";

// Get user's expire_time from database
$sql = "SELECT expire_time FROM user WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION["id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && $user['expire_time']) {
    // Update session with new expire time
    $_SESSION["expire_time"] = $user['expire_time'];
    echo json_encode(['success' => true, 'expire_time' => $user['expire_time']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not extend session']);
}

$conn->close();
?> 