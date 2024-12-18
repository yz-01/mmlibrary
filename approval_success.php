<?php
// Initialize the session
session_start();

// Get the message from URL parameters
$success = isset($_GET['success']) ? $_GET['success'] === 'true' : false;
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login Approval Status</title>
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
                            <div class="brand-logo text-center">
                                <img src="assets/images/crm_logo.png" alt="logo" style="height: 40px;">
                            </div>
                            <div class="text-center mt-4">
                                <?php if ($success): ?>
                                    <i class="mdi mdi-check-circle text-success" style="font-size: 48px;"></i>
                                    <h4 class="text-success mt-3">Success!</h4>
                                    <p class="mt-3">Verification code has been sent to the user.</p>
                                <?php else: ?>
                                    <i class="mdi mdi-close-circle text-danger" style="font-size: 48px;"></i>
                                    <h4 class="text-danger mt-3">Error</h4>
                                    <p class="mt-3"><?php echo $message ?: 'An error occurred while processing the request.'; ?></p>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <p>You can close this window now.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/misc.js"></script>
</body>
</html>
