<?php
// Initialize the session
session_start();
require_once "includes/db/config.php";

// Check if session expired
$expired = isset($_GET['expired']) && $_GET['expired'] == 1;

// Log the logout
if (isset($_SESSION["id"])) {
    function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    function logActivity($conn, $user_id, $action, $status, $details = null) {
        $ip = getClientIP();
        $sql = "INSERT INTO activity_logs (user_id, ip_address, action, status, details) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issss", $user_id, $ip, $action, $status, $details);
            $stmt->execute();
            $stmt->close();
        }
    }

    logActivity($conn, $_SESSION["id"], 
        $expired ? "SESSION_EXPIRED" : "LOGOUT", 
        "SUCCESS", 
        $expired ? "Session timed out after 2 minutes" : "User logged out manually"
    );
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with appropriate message
if ($expired) {
    header("location: index.php?msg=expired");
} else {
    header("location: index.php");
}
exit;
?>
