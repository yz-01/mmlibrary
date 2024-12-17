<?php
header('Content-Type: text/html');
require_once "includes/db/config.php";
require_once "includes/mail/mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Disable displaying errors on screen
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/php-error.log');

// Get the token and user ID from the URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

if (empty($token) || empty($user_id)) {
    header("Location: approval_success.php?success=false&message=" . urlencode('Invalid request'));
    exit;
}

// Verify the token from pending_approvals table
$sql = "SELECT * FROM pending_approvals WHERE user_id = ? AND token = ? AND is_used = 0 AND expires_at > NOW()";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $approval = $result->fetch_assoc();
        
        // Mark token as used
        $update_sql = "UPDATE pending_approvals SET is_used = 1 WHERE id = ?";
        if ($update_stmt = $conn->prepare($update_sql)) {
            $update_stmt->bind_param("i", $approval['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Generate verification code for user
        $verification_code = sprintf("%06d", mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        
        // Save verification code
        $insert_sql = "INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)";
        if ($insert_stmt = $conn->prepare($insert_sql)) {
            $insert_stmt->bind_param("iss", $user_id, $verification_code, $expires_at);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Get user email
            $user_sql = "SELECT email FROM user WHERE id = ?";
            if ($user_stmt = $conn->prepare($user_sql)) {
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                $user_stmt->close();
                
                // Send verification code to user
                $mail = createMailer();
                $mail->addAddress($user['email']);
                $mail->Subject = 'Login Verification Code';
                $mail->Body    = 'Your verification code is: <b>' . $verification_code . '</b>';
                $mail->AltBody = 'Your verification code is: ' . $verification_code;
                
                try {
                    $mail->send();
                    header("Location: approval_success.php?success=true");
                    exit;
                } catch (Exception $e) {
                    header("Location: approval_success.php?success=false&message=" . urlencode('Failed to send verification code'));
                    exit;
                }
            }
        }
    } else {
        header("Location: approval_success.php?success=false&message=" . urlencode('Invalid or expired token'));
        exit;
    }
    $stmt->close();
}

$conn->close();
?>
