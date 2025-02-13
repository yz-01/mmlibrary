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

// Get the presigned URL and file info from the query parameter
$fileUrl = $_GET['url'] ?? '';
$fileId = $_GET['file'] ?? '';

if (empty($fileUrl)) {
    die("Error: No URL provided");
}

// Determine file type from URL
$fileExtension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
$isPDF = $fileExtension === 'pdf';

// Google Docs Viewer URL for PDF preview
$googleViewerUrl = $isPDF ? "https://docs.google.com/gview?embedded=true&url=" . urlencode($fileUrl) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>File Preview</title>
    <!-- Include the same CSS as file_management.php -->
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
    <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
    <style>
        .preview-container {
            background: #fff;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .preview-iframe {
            width: 100%;
            height: 800px;
            border: none;
            background: #fff;
        }
        .action-buttons {
            margin-bottom: 20px;
        }
        .no-preview-container {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .no-preview-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        .no-preview-text {
            color: #6c757d;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-scroller">
        <?php include 'header.php'; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php'; ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="page-header">
                        <h3 class="page-title">File Preview</h3>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="file_management.php">Files</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Preview File</li>
                            </ol>
                        </nav>
                    </div>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="action-buttons">
                                        <a href="file_management.php" class="btn btn-light">
                                            <i class="mdi mdi-arrow-left"></i> Back to Files
                                        </a>
                                        <?php if ($_SESSION["is_downloadable"]): ?>
                                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" class="btn btn-primary" download>
                                            <i class="mdi mdi-download"></i> Download File
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($isPDF): ?>
                                    <!-- PDF Preview -->
                                    <div class="preview-container">
                                        <iframe src="<?php echo htmlspecialchars($googleViewerUrl); ?>" 
                                                class="preview-iframe"
                                                allowfullscreen></iframe>
                                    </div>
                                    <?php else: ?>
                                    <!-- No Preview Available -->
                                    <div class="preview-container">
                                        <div class="no-preview-container">
                                            <div class="no-preview-icon">
                                                <i class="mdi mdi-file-document-outline"></i>
                                            </div>
                                            <h4 class="no-preview-text">
                                                Preview not available for this file type.<br>
                                                Please download the file to view its contents.
                                            </h4>
                                            <?php if ($_SESSION["is_downloadable"]): ?>
                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" class="btn btn-primary btn-lg" download>
                                                <i class="mdi mdi-download"></i> Download File
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <?php include 'footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/misc.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>