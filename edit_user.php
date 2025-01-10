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

// Database connection
require_once "includes/db/config.php";

// Get user ID from URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header("location: users.php");
    exit;
}

// Get user data
$sql = "SELECT * FROM user WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("location: users.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $expire_time = $_POST['expire_time'] ?? null;
    $is_readable = isset($_POST['is_readable']) ? 1 : 0;
    $is_downloadable = isset($_POST['is_downloadable']) ? 1 : 0;
    $is_editable = isset($_POST['is_editable']) ? 1 : 0;
    $is_block = isset($_POST['is_block']) ? 1 : 0;

    // Convert minutes to milliseconds
    // $expire_time = $expire_time ? $expire_time * 60000 : null;

    if ($password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE user SET username = ?, email = ?, password = ?, expire_time = ?, 
                is_readable = ?, is_downloadable = ?, is_editable = ?, is_block = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiiiiii", $username, $email, $hashed_password, $expire_time, 
                         $is_readable, $is_downloadable, $is_editable, $is_block, $id);
    } else {
        $sql = "UPDATE user SET username = ?, email = ?, expire_time = ?, 
                is_readable = ?, is_downloadable = ?, is_editable = ?, is_block = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiii", $username, $email, $expire_time, 
                         $is_readable, $is_downloadable, $is_editable, $is_block, $id);
    }

    if ($stmt->execute()) {
        header("location: users.php");
        exit();
    } else {
        $error = "Error updating user.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit User</title>
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
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
                        <h3 class="page-title">Edit User</h3>
                    </div>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <?php if (isset($error)): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <div class="form-group">
                                            <label>Username</label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>New Password (leave blank to keep current)</label>
                                            <input type="password" class="form-control" name="password">
                                        </div>
                                        <div class="form-group">
                                            <label>Session Expire Time (minutes)</label>
                                            <input type="number" class="form-control" name="expire_time" min="1" 
                                                   value="<?php echo $user['expire_time']; ?>">
                                            <small class="text-muted">Leave empty for 2 minutes expiration time</small>
                                        </div>
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_readable" 
                                                       <?php echo $user['is_readable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Can Read Files</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_downloadable" 
                                                       <?php echo $user['is_downloadable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Can Download Files</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_editable" 
                                                       <?php echo $user['is_editable'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Can Edit Files</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_block" 
                                                       <?php echo $user['is_block'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Block User</label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-gradient-primary me-2">Update</button>
                                        <a href="users.php" class="btn btn-light">Cancel</a>
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
</body>
</html> 