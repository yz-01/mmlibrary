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

// Fetch documents from database
$sql = "SELECT d.*, u.username as uploader 
        FROM documents d 
        LEFT JOIN user u ON d.uploaded_by = u.id 
        ORDER BY d.upload_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Documents</title>
    <!-- Your existing CSS links -->
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
    <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
    <style>
    .modal-dialog-scrollable {
        max-height: 90vh;
    }

    .document-viewer-content {
        min-height: 70vh;
        max-height: 70vh;
        overflow-y: auto;
    }

    .document-viewer-content embed,
    .document-viewer-content iframe {
        width: 100%;
        height: 100%;
        min-height: 70vh;
        border: none;
    }

    .document-viewer-content img {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
    }

    /* For PDF viewing */
    .pdf-container {
        width: 100%;
        height: 70vh;
        overflow-y: auto;
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
                        <h3 class="page-title">Documents</h3>
                        <a href="add_document.php" class="btn btn-primary">Upload New Document</a>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="documentsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Description</th>
                                                    <th>File Type</th>
                                                    <th>Size</th>
                                                    <th>Uploaded By</th>
                                                    <th>Upload Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = $result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['file_type']); ?></td>
                                                        <td><?php echo number_format($row['file_size'] / 1024, 2) . ' KB'; ?></td>
                                                        <td><?php echo htmlspecialchars($row['uploader']); ?></td>
                                                        <td><?php echo date('Y-m-d H:i', strtotime($row['upload_date'])); ?></td>
                                                        <td>
                                                            <button type="button" 
                                                                    class="btn btn-primary btn-sm view-document" 
                                                                    data-file="<?php echo htmlspecialchars($row['file_name']); ?>"
                                                                    data-type="<?php echo htmlspecialchars($row['file_type']); ?>">
                                                                View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div class="modal fade" id="documentViewerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="documentContent" class="document-viewer-content">
                        <!-- Document will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#documentsTable').DataTable({
            "order": [[5, "desc"]] // Sort by upload date by default
        });

        // Handle document viewing
        $('.view-document').click(function() {
            var fileName = $(this).data('file');
            var fileType = $(this).data('type');
            var fileUrl = 'uploads/' + fileName;
            var contentDiv = $('#documentContent');
            
            // Clear previous content
            contentDiv.empty();

            // Handle different file types
            if (fileType.includes('pdf')) {
                contentDiv.html('<div class="pdf-container"><embed src="' + fileUrl + '" type="application/pdf" width="100%" height="100%"></div>');
            } else if (fileType.includes('image')) {
                contentDiv.html('<div class="text-center"><img src="' + fileUrl + '" class="img-fluid" alt="Document Image"></div>');
            } else if (fileType.includes('excel') || fileType.includes('spreadsheet')) {
                contentDiv.html('<div class="alert alert-info m-3">Excel files cannot be previewed. <a href="' + fileUrl + '" target="_blank" class="btn btn-primary btn-sm ms-2">Download to View</a></div>');
            } else if (fileType.includes('word') || fileType.includes('document')) {
                contentDiv.html('<div class="alert alert-info m-3">Word documents cannot be previewed. <a href="' + fileUrl + '" target="_blank" class="btn btn-primary btn-sm ms-2">Download to View</a></div>');
            } else {
                contentDiv.html('<div class="alert alert-warning m-3">Preview not available for this file type. <a href="' + fileUrl + '" target="_blank" class="btn btn-primary btn-sm ms-2">Download to View</a></div>');
            }

            // Show the modal using Bootstrap 5 syntax
            var myModal = new bootstrap.Modal(document.getElementById('documentViewerModal'));
            myModal.show();
        });

        // Add event listener for modal close
        document.getElementById('documentViewerModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('documentContent').innerHTML = '';
        });
    });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>