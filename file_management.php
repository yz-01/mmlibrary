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

// Get current directory path
$current_directory = isset($_GET['dir']) ? $_GET['dir'] : '';

// Generate breadcrumb data
$path_parts = $current_directory ? explode('/', trim($current_directory, '/')) : [];
$breadcrumbs = [];
$current_path = '';
foreach ($path_parts as $part) {
    $current_path .= '/' . $part;
    $breadcrumbs[] = [
        'name' => $part,
        'path' => trim($current_path, '/')
    ];
}

// Fetch current directory contents
$sql = "SELECT * FROM folders_files 
        WHERE parent_directory = ? 
        ORDER BY type DESC, name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_directory);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Similar header setup as activity_logs.php -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>File Management</title>
    <!-- Add additional CSS for file management -->
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
        .folder-icon {
            color: #ffc107;
            margin-right: 5px;
        }
        
        .file-icon {
            color: #28a745;
            margin-right: 5px;
        }
        
        .breadcrumb {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
        }
        
        .breadcrumb-item.active {
            color: #495057;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge {
            padding: 5px 10px;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }
        
        .btn-action {
            padding: 4px 8px;
            margin: 0 2px;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .parent-dir {
            color: #6c757d;
            font-style: italic;
        }
        
        .parent-dir i {
            margin-right: 5px;
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
                        <h3 class="page-title">File Management</h3>
                        <?php if ($_SESSION["is_editable"]): ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                                <i class="mdi mdi-folder-plus"></i> New Folder
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createFileModal">
                                <i class="mdi mdi-file-plus"></i> New File
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="mdi mdi-upload"></i> Upload Document
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Breadcrumb navigation -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="?dir=" class="text-decoration-none">
                                    <i class="mdi mdi-folder folder-icon"></i> Root
                                </a>
                            </li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                            <li class="breadcrumb-item">
                                <a href="?dir=<?php echo urlencode($crumb['path']); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($crumb['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>

                    <!-- Directory Contents -->
                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">

                                    <div class="table-responsive">
                                        <table id="filesTable" class="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40%">Name</th>
                                                    <th style="width: 15%">Type</th>
                                                    <th style="width: 15%">Created</th>
                                                    <th style="width: 15%">Modified</th>
                                                    <th style="width: 15%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($current_directory && count($path_parts) > 0): ?>
                                                <tr class="parent-dir">
                                                    <td>
                                                        <a href="?dir=<?php 
                                                            $parent_path = implode('/', array_slice($path_parts, 0, -1));
                                                            echo urlencode($parent_path); 
                                                        ?>" class="text-decoration-none">
                                                            <i class="mdi mdi-arrow-up-bold-circle"></i> Parent Directory
                                                        </a>
                                                    </td>
                                                    <td><span class="badge bg-secondary">Directory</span></td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                </tr>
                                                <?php endif; ?>

                                                <?php while($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <?php if($row['type'] == 'folder'): ?>
                                                            <a href="?dir=<?php 
                                                                $new_path = $current_directory ? $current_directory . '/' . $row['name'] : $row['name'];
                                                                echo urlencode($new_path); 
                                                            ?>" class="text-decoration-none">
                                                                <i class="mdi mdi-folder folder-icon"></i>
                                                                <?php echo htmlspecialchars($row['name']); ?>
                                                            </a>
                                                        <?php elseif($row['type'] == 'pdf' || $row['type'] == 'docx' || $row['type'] == 'xlsx' || $row['type'] == 'jpg' || $row['type'] == 'jpeg' || $row['type'] == 'png'): ?>
                                                            <?php if ($_SESSION["is_readable"]): ?>
                                                                <?php if (strpos($row['path'], 'https') === 0): ?>
                                                                    <a href="includes/get_presigned_url.php?path=<?php echo urlencode($row['path']); ?>&redirect=preview.php&file_id=<?php echo urlencode($row['id']); ?>" class="text-decoration-none">
                                                                        <i class="mdi mdi-file file-icon"></i>
                                                                        <?php echo htmlspecialchars($row['name']); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="<?php echo htmlspecialchars($row['path']); ?>" class="text-decoration-none" download>
                                                                        <i class="mdi mdi-file file-icon"></i>
                                                                        <?php echo htmlspecialchars($row['name']); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">
                                                                    <i class="mdi mdi-file file-icon"></i>
                                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <?php if ($_SESSION["is_readable"]): ?>
                                                            <a href="spreadsheet.php?file=<?php echo urlencode($row['id']); ?>" class="text-decoration-none">
                                                                <i class="mdi mdi-file-document file-icon"></i>
                                                                <?php echo htmlspecialchars($row['name']); ?>
                                                            </a>
                                                            <?php else: ?>
                                                            <span class="text-muted">
                                                                <i class="mdi mdi-file-document file-icon"></i>
                                                                <?php echo htmlspecialchars($row['name']); ?>
                                                            </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $row['type'] == 'folder' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                                            <?php echo ucfirst($row['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($row['modified_at'])); ?></td>
                                                    <td>
                                                        <?php if ($row['type'] == 'file'): ?>
                                                            <?php 
                                                            $fileExtension = strtolower(pathinfo($row['name'], PATHINFO_EXTENSION));
                                                            if ($fileExtension === 'pdf'): 
                                                            ?>
                                                                <button class="btn btn-primary btn-sm preview-pdf" data-file-key="<?php echo htmlspecialchars($row['path']); ?>">
                                                                    <i class="mdi mdi-eye"></i> Preview
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($_SESSION["is_editable"]): ?>
                                                        <button class="btn btn-sm btn-info btn-action" onclick="renameItem(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteItem(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                            <i class="mdi mdi-delete"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        -
                                                        <?php endif; ?>
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

    <!-- Create Folder Modal -->
    <div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createFolderModalLabel">Create New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createFolderForm">
                        <div class="form-group">
                            <label for="folderName">Folder Name</label>
                            <input type="text" class="form-control" id="folderName" name="folderName" required>
                        </div>
                        <input type="hidden" name="parent_directory" value="<?php echo htmlspecialchars($current_directory); ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createFolder()">Create Folder</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create File Modal -->
    <div class="modal fade" id="createFileModal" tabindex="-1" aria-labelledby="createFileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createFileModalLabel">Create New File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createFileForm">
                        <div class="form-group mb-3">
                            <label for="fileName">File Name</label>
                            <input type="text" class="form-control" id="fileName" name="fileName" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="fileDescription">Description (Optional)</label>
                            <textarea class="form-control" id="fileDescription" name="fileDescription" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="parent_directory" value="<?php echo htmlspecialchars($current_directory); ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="createFile()">Create File</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal after your existing modals -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDocumentModalLabel">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadDocumentForm" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label for="documentName">Document Name</label>
                            <input type="text" class="form-control" id="documentName" name="documentName" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="documentFile">File</label>
                            <input type="file" class="form-control" id="documentFile" name="documentFile" accept=".jpeg,.jpg,.xlsx,.docx,.pdf" required>
                            <small class="form-text text-muted">Allowed file types: PDF, JPEG, XLSX, DOCX</small>
                        </div>
                        <div class="form-group mb-3">
                            <label for="documentDescription">Description (Optional)</label>
                            <textarea class="form-control" id="documentDescription" name="documentDescription" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="parent_directory" value="<?php echo htmlspecialchars($current_directory); ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="uploadDocument()">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update the PDF viewer modal -->
    <div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-labelledby="pdfViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfViewerModalLabel">View PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <object id="pdfViewer" type="application/pdf" style="width: 100%; height: 80vh;">
                        <embed id="pdfEmbed" type="application/pdf" style="width: 100%; height: 80vh;">
                    </object>
                </div>
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

    <script>
    $(document).ready(function() {
        // Initialize DataTable with custom options
        $('#filesTable').DataTable({
            "order": [[2, "desc"]], // Sort by created date by default
            "pageLength": 25,
            "language": {
                "search": "Search files:",
                "lengthMenu": "Show _MENU_ files per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ files",
                "infoEmpty": "No files available",
                "infoFiltered": "(filtered from _MAX_ total files)"
            },
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Disable sorting on actions column
            ]
        });

        // Function to handle rename
        window.renameItem = function(id, currentName) {
            if (!<?php echo $_SESSION["is_editable"] ? 'true' : 'false'; ?>) {
                Swal.fire({
                    icon: 'error',
                    title: 'Permission Denied',
                    text: 'You do not have permission to rename items'
                });
                return;
            }
            
            Swal.fire({
                title: 'Rename Item',
                input: 'text',
                inputLabel: `Enter new name for: ${currentName}`,
                inputValue: currentName,
                showCancelButton: true,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Name cannot be empty';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value !== currentName) {
                    $.post('includes/file_actions.php', {
                        action: 'rename',
                        id: id,
                        new_name: result.value
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Renamed Successfully',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    }, 'json');
                }
            });
        };
    });

    // Function to handle delete
    window.deleteItem = function(id, name) {
        if (!<?php echo $_SESSION["is_editable"] ? 'true' : 'false'; ?>) {
            Swal.fire({
                icon: 'error',
                title: 'Permission Denied',
                text: 'You do not have permission to delete items'
            });
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('includes/file_actions.php', {
                    action: 'delete',
                    id: id
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'The item has been deleted.',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                }, 'json');
            }
        });
    };

    // Function to handle create folder
    function createFolder() {
        if (!<?php echo $_SESSION["is_editable"] ? 'true' : 'false'; ?>) {
            alert('You do not have permission to create folders');
            return;
        }
        const folderName = $('#folderName').val().trim();
        const currentDirectory = '<?php echo $current_directory; ?>';
        
        if (!folderName) {
            alert('Please enter a folder name');
            return;
        }

        $.post('includes/file_actions.php', {
            action: 'create_folder',
            name: folderName,
            parent_directory: currentDirectory
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    }

    // Clear form when modal is closed
    $('#createFolderModal').on('hidden.bs.modal', function () {
        $('#createFolderForm')[0].reset();
    });

    // Function to handle create file
    function createFile() {
        if (!<?php echo $_SESSION["is_editable"] ? 'true' : 'false'; ?>) {
            alert('You do not have permission to create files');
            return;
        }
        const fileName = $('#fileName').val().trim();
        const fileDescription = $('#fileDescription').val().trim();
        const currentDirectory = '<?php echo $current_directory; ?>';
        
        if (!fileName) {
            alert('Please enter a file name');
            return;
        }

        $.post('includes/file_actions.php', {
            action: 'create_file',
            name: fileName,
            description: fileDescription,
            parent_directory: currentDirectory
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    }

    // Clear form when modal is closed
    $('#createFileModal').on('hidden.bs.modal', function () {
        $('#createFileForm')[0].reset();
    });

    // Function to handle upload document
    function uploadDocument() {
        if (!<?php echo $_SESSION["is_editable"] ? 'true' : 'false'; ?>) {
            alert('You do not have permission to upload documents');
            return;
        }
        const formData = new FormData();
        const fileInput = document.getElementById('documentFile');
        const nameInput = document.getElementById('documentName');
        const descriptionInput = document.getElementById('documentDescription');
        const currentDirectory = '<?php echo $current_directory; ?>';

        if (!fileInput.files[0]) {
            alert('Please select a file');
            return;
        }

        if (!nameInput.value.trim()) {
            alert('Please enter a document name');
            return;
        }

        formData.append('file', fileInput.files[0]);
        formData.append('name', nameInput.value.trim());
        formData.append('description', descriptionInput.value.trim());
        formData.append('parent_directory', currentDirectory);

        fetch('includes/upload_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error uploading document: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error uploading document');
            console.error('Error:', error);
        });
    }

    // Clear form when modal is closed
    $('#uploadDocumentModal').on('hidden.bs.modal', function () {
        $('#uploadDocumentForm')[0].reset();
    });
    </script>
</body>
</html>