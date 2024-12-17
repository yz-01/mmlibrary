<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Error handling configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-error.log');

// Database connection
require_once "includes/db/config.php";

// Define variables
$title = $description = "";
$title_err = $file_err = $description_err = $submit_err = "";

// Allowed file types
$allowed_types = array(
  'application/pdf',
  'image/jpeg',
  'image/jpg',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
);

// Maximum file size (5MB)
$max_size = 5 * 1024 * 1024;

// Add these functions at the top of the file after session_start()
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
    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description.";
    } else {
        $description = trim($_POST["description"]);
    }

    // Validate file upload
    if (!isset($_FILES["document"]) || $_FILES["document"]["error"] == UPLOAD_ERR_NO_FILE) {
        $file_err = "Please select a file to upload.";
    } else {
        $file = $_FILES["document"];
        
        // Check file size
        if ($file["size"] > $max_size) {
            $file_err = "File is too large. Maximum size is 5MB.";
        }
        
        // Check file type
        if (!in_array($file["type"], $allowed_types)) {
            $file_err = "Invalid file type. Only PDF, JPEG, Excel, and Word documents are allowed.";
        }
    }

    // Check input errors before inserting in database
    if (empty($title_err) && empty($file_err) && empty($description_err)) {
        try {
            // Create uploads directory if it doesn't exist
            $upload_dir = "uploads/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename
            $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file["tmp_name"], $upload_path)) {
                // Prepare an insert statement
                $sql = "INSERT INTO documents (title, file_name, file_type, file_size, uploaded_by, description) VALUES (?, ?, ?, ?, ?, ?)";
                
                if ($stmt = $conn->prepare($sql)) {
                    // Bind parameters
                    $stmt->bind_param("sssiis", 
                        $param_title, 
                        $param_filename, 
                        $param_filetype, 
                        $param_filesize, 
                        $param_uploaded_by, 
                        $param_description
                    );
                    
                    // Set parameters
                    $param_title = $title;
                    $param_filename = $new_filename;
                    $param_filetype = $file["type"];
                    $param_filesize = $file["size"];
                    $param_uploaded_by = $_SESSION["id"];
                    $param_description = $description;
                    
                    // Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        // Log successful upload
                        logActivity($conn, $_SESSION["id"], "DOCUMENT_UPLOAD", "SUCCESS", 
                            "Document uploaded: " . $param_title . " (" . $param_filename . ")");
                        
                        $_SESSION['success_message'] = "Document uploaded successfully.";
                        header("Location: documents.php");
                        exit();
                    } else {
                        // Log database error
                        $error_message = "Database Error: " . $stmt->error;
                        logActivity($conn, $_SESSION["id"], "DOCUMENT_UPLOAD", "FAILED", $error_message);
                        $submit_err = "Failed to save document record. " . $error_message;
                        
                        // Delete uploaded file since database insert failed
                        unlink($upload_path);
                    }
                    $stmt->close();
                } else {
                    // Log prepare statement error
                    $error_message = "Prepare Statement Error: " . $conn->error;
                    logActivity($conn, $_SESSION["id"], "DOCUMENT_UPLOAD", "FAILED", $error_message);
                    $submit_err = "Database error. " . $error_message;
                    
                    // Delete uploaded file
                    unlink($upload_path);
                }
            } else {
                $submit_err = "Failed to upload file.";
                logActivity($conn, $_SESSION["id"], "DOCUMENT_UPLOAD", "FAILED", 
                    "Failed to move uploaded file to destination");
            }
        } catch (Exception $e) {
            // Log any unexpected errors
            $error_message = "Exception: " . $e->getMessage();
            logActivity($conn, $_SESSION["id"], "DOCUMENT_UPLOAD", "FAILED", $error_message);
            $submit_err = "An error occurred. " . $error_message;
        }
    }

    // Display error message if any
    if (!empty($submit_err)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($submit_err) . '</div>';
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Upload Document</title>
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
    <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
</head>
<body>
    <div class="container-scroller">
        <?php include 'header.php'; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php'; ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="page-header">
                        <h3 class="page-title">Upload Document</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-6 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <form class="forms-sample" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label for="title">Document Title</label>
                                            <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" id="title" placeholder="Enter document title" value="<?php echo htmlspecialchars($title); ?>">
                                            <div class="invalid-feedback"><?php echo $title_err; ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea name="description" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" id="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                                            <div class="invalid-feedback"><?php echo $description_err; ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label for="document">Upload File</label>
                                            <input type="file" name="document" class="form-control <?php echo (!empty($file_err)) ? 'is-invalid' : ''; ?>" id="document">
                                            <div class="invalid-feedback"><?php echo $file_err; ?></div>
                                            <small class="form-text text-muted">Allowed files: PDF, JPEG, Excel, Word (Max size: 5MB)</small>
                                        </div>
                                        <button type="submit" class="btn btn-gradient-primary me-2">Upload</button>
                                        <a href="documents.php" class="btn btn-light">Cancel</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/misc.js"></script>
</body>
</html>