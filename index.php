<?php
// Add this at the very top of your file
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize the session
session_start();

// Include config file
require_once "includes/db/config.php";

// Include PHPMailer files
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";
$verification_code = "";
$verification_code_err = "";

// Add at the top after session_start()
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

// Add before processing login
$ip_address = getClientIP();

// Check if IP is blocked
$sql = "SELECT * FROM login_attempts 
        WHERE ip_address = ? AND is_blocked = 1 
        AND block_expires_at > NOW()";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $login_err = "Too many failed attempts. Please try again later.";
        logActivity($conn, null, "LOGIN_BLOCKED", "FAILED", "IP blocked due to multiple attempts");
        exit;
    }
    $stmt->close();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // If verification code is submitted
    if (isset($_POST["verification_code"])) {
        // Verify the code
        if (empty(trim($_POST["verification_code"]))) {
            $verification_code_err = "Please enter verification code.";
        } else {
            $verification_code = trim($_POST["verification_code"]);
            
            // Verify the code from database
            $sql = "SELECT u.id, u.email, u.role, u.branch_id, u.team_id, u.is_superadmin 
                   FROM verification_codes vc 
                   JOIN user u ON vc.user_id = u.id 
                   WHERE vc.code = ? AND vc.is_used = 0 
                   AND vc.expires_at > NOW()";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $verification_code);
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($id, $email, $role, $branch_id, $team_id, $is_superadmin);
                        if ($stmt->fetch()) {
                            // Start session and set variables
                            session_start();
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;
                            $_SESSION["branch_id"] = $branch_id;
                            $_SESSION["team_id"] = $team_id;
                            $_SESSION["is_superadmin"] = $is_superadmin;

                            // Mark verification code as used
                            $update_sql = "UPDATE verification_codes SET is_used = 1 WHERE code = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("s", $verification_code);
                            $update_stmt->execute();
                            
                            header("location: dashboard.php?uid=" . $id . "&uemail=" . $email);
                        }
                    } else {
                        $verification_code_err = "Invalid or expired verification code.";
                    }
                }
                $stmt->close();
            }
        }
    } else {
        // Normal login flow
        // Check if email is empty
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter email.";
        } else {
            $email = trim($_POST["email"]);
        }

        // Check if password is empty
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter your password.";
        } else {
            $password = trim($_POST["password"]);
        }

        // Validate credentials
        if (empty($email_err) && empty($password_err)) {
            // Prepare a select statement
            $sql = "SELECT id, email, password, role, branch_id, team_id, is_superadmin FROM user WHERE email = ?";

            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("s", $param_email);

                // Set parameters
                $param_email = $email;

                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    // Store result
                    $stmt->store_result();

                    // Check if email exists, if yes then verify password
                    if ($stmt->num_rows == 1) {
                        // Bind result variables
                        $stmt->bind_result($id, $email, $hashed_password, $role, $branch_id, $team_id, $is_superadmin);
                        if ($stmt->fetch()) {
                            if (password_verify($password, $hashed_password)) {
                                // Generate a unique token for admin approval
                                $token = bin2hex(random_bytes(32));
                                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10-minute expiration for admin approval
                                
                                // Save approval request
                                $insert_sql = "INSERT INTO pending_approvals (user_id, token, expires_at) VALUES (?, ?, ?)";
                                if ($insert_stmt = $conn->prepare($insert_sql)) {
                                    $insert_stmt->bind_param("iss", $id, $token, $expires_at);
                                    $insert_stmt->execute();
                                    $insert_stmt->close();
                                    
                                    // Send email to admin
                                    $mail = new PHPMailer(true);
                                    $mail->isSMTP();
                                    $mail->Host       = 'smtp.gmail.com';
                                    $mail->SMTPAuth   = true;
                                    $mail->Username   = 'noreply.nrhere@gmail.com';
                                    $mail->Password   = 'shea dgfr thuq klcg';
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    $mail->Port       = 587;

                                    // Recipients
                                    $mail->setFrom('noreply.nrhere@gmail.com', 'Login Approval');
                                    $mail->addAddress('multiple.rsc@gmail.com', 'Admin'); // Admin email
                                    $mail->addAddress('noreply.nrhere@gmail.com', 'Admin2'); // Second Admin email

                                    // Content
                                    $mail->isHTML(true);
                                    $mail->Subject = 'Login Approval Request';
                                    $approval_link = "http://{$_SERVER['HTTP_HOST']}/approve_login.php?token=" . $token . "&user_id=" . $id;
                                    $mail->Body    = "A user with email {$email} is trying to login.<br><br>
                                                     Click <a href='{$approval_link}'>here</a> to approve and send verification code.<br><br>
                                                     IP Address: {$ip_address}";
                                    $mail->AltBody = "A user with email {$email} is trying to login. Click the following link to approve: {$approval_link}";

                                    try {
                                        $mail->send();
                                        
                                        // Log the approval request
                                        logActivity($conn, $id, "LOGIN_APPROVAL_REQUESTED", "PENDING", "Approval request sent to admin");
                                        
                                        // Store email and IP in session temporarily
                                        $_SESSION["temp_email"] = $email;
                                        $_SESSION["temp_ip"] = $ip_address;
                                        
                                        // Show message to user
                                        $login_err = "Please wait for admin approval. You will receive a verification code once approved.";
                                        
                                        // Redirect to verify.php
                                        header("location: verify.php");
                                        exit;
                                    } catch (Exception $e) {
                                        $login_err = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                                        logActivity($conn, $id, "LOGIN_APPROVAL_REQUEST_FAILED", "FAILED", $mail->ErrorInfo);
                                    }
                                }
                            } else {
                                // Record failed attempt
                                $sql = "INSERT INTO login_attempts (ip_address) VALUES (?)";
                                if ($stmt = $conn->prepare($sql)) {
                                    $stmt->bind_param("s", $ip_address);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                                
                                // Check number of recent failed attempts
                                $sql = "SELECT COUNT(*) as attempt_count FROM login_attempts 
                                        WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
                                if ($stmt = $conn->prepare($sql)) {
                                    $stmt->bind_param("s", $ip_address);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $row = $result->fetch_assoc();
                                    
                                    if ($row['attempt_count'] >= 5) {
                                        // Block IP for 30 minutes
                                        $sql = "UPDATE login_attempts SET is_blocked = 1, 
                                               block_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) 
                                               WHERE ip_address = ?";
                                        if ($block_stmt = $conn->prepare($sql)) {
                                            $block_stmt->bind_param("s", $ip_address);
                                            $block_stmt->execute();
                                            $block_stmt->close();
                                        }
                                        $login_err = "Too many failed attempts. Please try again later.";
                                        logActivity($conn, null, "LOGIN_BLOCKED", "FAILED", "IP blocked due to multiple attempts");
                                    } else {
                                        $login_err = "Invalid email or password.";
                                        logActivity($conn, null, "LOGIN_ATTEMPT", "FAILED", "Invalid credentials from IP: " . $ip_address);
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                    } else {
                        // Email doesn't exist, display a generic error message
                        $login_err = "Invalid email or password.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                $stmt->close();
            }
        }
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login</title>
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
</head>
<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth">
                <div class="row flex-grow">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-left p-5">
                            <div class="brand-logo">
                                <img src="assets/images/crm_logo.png">
                            </div>
                            <h4>Hello! Let's get started</h4>
                            <h6 class="font-weight-light">Sign in to continue.</h6>
                            <?php 
                            if(!empty($login_err)){
                                echo '<div class="alert alert-danger">' . $login_err . '</div>';
                            }        
                            ?>
                            <?php 
                            if (isset($_GET['msg']) && $_GET['msg'] == 'expired') {
                                echo '<div class="alert alert-warning">Your session has expired. Please log in again.</div>';
                            }
                            ?>
                            <form class="pt-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                                    <input type="email" name="email" class="form-control form-control-lg" id="exampleInputEmail1" placeholder="Email" value="<?php echo $email; ?>">
                                    <span class="help-block text-danger"><?php echo $email_err; ?></span>
                                </div>
                                <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                                    <input type="password" name="password" class="form-control form-control-lg" id="exampleInputPassword1" placeholder="Password">
                                    <span class="help-block text-danger"><?php echo $password_err; ?></span>
                                </div>
                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn">SIGN IN</button>
                                </div>
                                <div class="text-center mt-4 font-weight-light">
                                    Don't have an account? <a href="register.php" class="text-primary">Create</a>
                                </div>
                            </form>
                            <?php if (isset($show_verification_form)): ?>
                                <form class="pt-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="form-group <?php echo (!empty($verification_code_err)) ? 'has-error' : ''; ?>">
                                        <input type="text" name="verification_code" class="form-control form-control-lg" 
                                               placeholder="Enter verification code" maxlength="6">
                                        <span class="help-block text-danger"><?php echo $verification_code_err; ?></span>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <button type="submit" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn">
                                            Verify Code
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/misc.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
    <script src="assets/js/jquery.cookie.js"></script>
</body>
</html>
