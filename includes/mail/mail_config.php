<?php
// Include PHPMailer files
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'teyyoongzhun@gmail.com');
define('SMTP_PASSWORD', 'qpnb vpku jcdt xjuk');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_FROM_EMAIL', 'teyyoongzhun@gmail.com');
define('SMTP_FROM_NAME', 'CRM System');

// Function to create a configured PHPMailer instance
function createMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    
    return $mail;
}
?>
