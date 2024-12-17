<?php
session_start();
require_once "includes/db/config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(["success" => false]);
    exit;
}

// Extend session by 2 minutes
$_SESSION["expire_time"] = time() + (2 * 60);

// Log session extension
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getLocationFromIP($ip) {
    try {
        // Using ipapi.co service (free tier - 1000 requests per day)
        $response = @file_get_contents("http://ip-api.com/json/" . $ip);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'region' => $data['regionName'] ?? 'Unknown'
                ];
            }
        }
    } catch (Exception $e) {
        // If geolocation fails, return unknown
        error_log("Geolocation error: " . $e->getMessage());
    }
    return [
        'country' => 'Unknown',
        'city' => 'Unknown',
        'region' => 'Unknown'
    ];
}

function logActivity($conn, $user_id, $action, $status, $details = null) {
    $ip = getClientIP();
    $location = getLocationFromIP($ip);
    
    $location_string = json_encode([
        'country' => $location['country'],
        'city' => $location['city'],
        'region' => $location['region']
    ]);

    $sql = "INSERT INTO activity_logs (user_id, ip_address, location, action, status, details) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isssss", $user_id, $ip, $location_string, $action, $status, $details);
        $stmt->execute();
        $stmt->close();
    }
}

logActivity($conn, $_SESSION["id"], "SESSION_EXTENDED", "SUCCESS", "Session extended for 2 minutes");

echo json_encode(["success" => true]); 