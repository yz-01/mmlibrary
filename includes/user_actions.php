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
$action = $data['action'] ?? '';

switch ($action) {
    case 'create':
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $expire_time = $data['expire_time'] ?? null;
        $is_readable = $data['is_readable'] ?? 1;
        $is_downloadable = $data['is_downloadable'] ?? 1;
        $is_editable = $data['is_editable'] ?? 1;
        $is_block = $data['is_block'] ?? 0;
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO user (username, email, password, role, expire_time, is_readable, is_downloadable, is_editable, is_block) 
                VALUES (?, ?, ?, 2, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiiis", $username, $email, $hashed_password, $expire_time, $is_readable, $is_downloadable, $is_editable, $is_block);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'update':
        $id = $data['id'] ?? '';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $expire_time = $data['expire_time'] ?? null;
        $is_readable = $data['is_readable'] ?? 0;
        $is_downloadable = $data['is_downloadable'] ?? 0;
        $is_editable = $data['is_editable'] ?? 0;
        $is_block = $data['is_block'] ?? 0;

        // Convert minutes to milliseconds for expire_time
        $expire_time = $expire_time ? $expire_time * 60000 : null;

        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE user SET 
                    username = ?, 
                    email = ?, 
                    password = ?, 
                    expire_time = ?, 
                    is_readable = ?, 
                    is_downloadable = ?, 
                    is_editable = ?, 
                    is_block = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiiiiii", 
                $username, 
                $email, 
                $hashed_password, 
                $expire_time, 
                $is_readable, 
                $is_downloadable, 
                $is_editable, 
                $is_block, 
                $id
            );
        } else {
            $sql = "UPDATE user SET 
                    username = ?, 
                    email = ?, 
                    expire_time = ?, 
                    is_readable = ?, 
                    is_downloadable = ?, 
                    is_editable = ?, 
                    is_block = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiiiii", 
                $username, 
                $email, 
                $expire_time, 
                $is_readable, 
                $is_downloadable, 
                $is_editable, 
                $is_block, 
                $id
            );
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'change_password':
        $user_id = $data['user_id'] ?? '';
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        
        // Verify user exists and get current password
        $sql = "SELECT password FROM user WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $update_sql = "UPDATE user SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating password']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;

    case 'delete':
        $id = $data['id'] ?? '';
        
        $sql = "DELETE FROM user WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?> 