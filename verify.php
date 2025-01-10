<?php
// Add this at the very top of your file
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize the session
session_start();

// Check if user is already logged in
if(!isset($_SESSION["temp_email"])) {
    header("location: index.php");
    exit;
}

// Include config file
require_once "includes/db/config.php";

// Define variables
$verification_code = "";
$verification_code_err = "";

// Add the getClientIP function
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Add the logActivity function
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

// Add after session_start()
if(!isset($_SESSION["temp_ip"]) || $_SESSION["temp_ip"] !== getClientIP()) {
    logActivity($conn, null, "VERIFICATION_ATTEMPT", "FAILED", "IP mismatch during verification");
    session_destroy();
    header("location: index.php");
    exit;
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate verification code
    if(empty(trim($_POST["verification_code"]))) {
        $verification_code_err = "Please enter verification code.";
    } else {
        $verification_code = trim($_POST["verification_code"]);
        
        // Verify the code from database
        $sql = "SELECT u.id, u.email, u.role, u.expire_time, u.is_readable, u.is_downloadable, u.is_editable 
               FROM verification_codes vc 
               JOIN user u ON vc.user_id = u.id 
               WHERE vc.code = ? AND vc.is_used = 0 
               AND vc.expires_at > NOW()";
        
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $verification_code);
            
            if($stmt->execute()) {
                $stmt->store_result();
                
                if($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $email, $role, $expire_time, $is_readable, $is_downloadable, $is_editable);
                    if($stmt->fetch()) {
                        // Log successful verification
                        logActivity($conn, $id, "VERIFICATION_SUCCESS", "SUCCESS", "User verified and logged in");
                        
                        // Start session and set variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["email"] = $email;
                        $_SESSION["role"] = $role;
                        $_SESSION["expire_time"] = $expire_time;
                        $_SESSION["is_readable"] = $is_readable;
                        $_SESSION["is_downloadable"] = $is_downloadable;
                        $_SESSION["is_editable"] = $is_editable;

                        // Mark verification code as used
                        $update_sql = "UPDATE verification_codes SET is_used = 1 WHERE code = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("s", $verification_code);
                        $update_stmt->execute();
                        
                        // Remove temporary variables
                        unset($_SESSION["temp_email"]);
                        unset($_SESSION["temp_ip"]);
                        
                        // Redirect to dashboard
                        header("location: file_management.php?uid=" . $id . "&uemail=" . $email);
                        exit;
                    }
                } else {
                    $verification_code_err = "Invalid or expired verification code.";
                }
            }
            $stmt->close();
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verification Code</title>
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
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
                            <h4>Verify Your Email</h4>
                            <h6 class="font-weight-light">Enter the verification code sent to <?php echo htmlspecialchars($_SESSION["temp_email"]); ?></h6>
                            
                            <form class="pt-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="form-group">
                                    <input type="text" name="verification_code" 
                                           class="form-control form-control-lg <?php echo (!empty($verification_code_err)) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Enter verification code" maxlength="6">
                                    <span class="invalid-feedback"><?php echo $verification_code_err; ?></span>
                                </div>
                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn">
                                        Verify Code
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Optional: Add resend code button -->
                            <div class="text-center mt-4">
                                <a href="resend_code.php" class="text-primary">Didn't receive the code? Click to resend</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/misc.js"></script>
</body>
</html> 