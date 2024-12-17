<?php
// Initialize the session
session_start();

// Include config file
require_once "includes/db/config.php";

// Define variables and initialize with empty values
$email = $password = $confirm_password = "";
$email_err = $password_err = $confirm_password_err = "";

// Add these functions at the top after session_start()
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

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
        logActivity($conn, null, "REGISTRATION_ATTEMPT", "FAILED", "Empty email field");
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM user WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already taken.";
                    logActivity($conn, null, "REGISTRATION_ATTEMPT", "FAILED", "Email already exists: " . $param_email);
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
        logActivity($conn, null, "REGISTRATION_ATTEMPT", "FAILED", "Empty password field");
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
        logActivity($conn, null, "REGISTRATION_ATTEMPT", "FAILED", "Password too short");
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO user (email, password) VALUES (?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $param_email, $param_password);
            
            // Set parameters
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id; // Get the ID of the newly created user
                logActivity($conn, $new_user_id, "REGISTRATION", "SUCCESS", "New user registered: " . $param_email);
                // Redirect to login page
                header("location: index.php");
            } else {
                logActivity($conn, null, "REGISTRATION", "FAILED", "Database error: " . $conn->error);
                echo "Oops! Something went wrong. Please try again later.";
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
    <title>Register</title>
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
                            <h4>New here?</h4>
                            <h6 class="font-weight-light">Signing up is easy. It only takes a few steps</h6>
                            <form class="pt-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="form-group">
                                    <input type="email" name="email" class="form-control form-control-lg <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" placeholder="Email" value="<?php echo $email; ?>">
                                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password" class="form-control form-control-lg <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Password">
                                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                                </div>
                                <div class="form-group">
                                    <input type="password" name="confirm_password" class="form-control form-control-lg <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm Password">
                                    <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                                </div>
                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn">SIGN UP</button>
                                </div>
                                <div class="text-center mt-4 font-weight-light">
                                    Already have an account? <a href="index.php" class="text-primary">Login</a>
                                </div>
                            </form>
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