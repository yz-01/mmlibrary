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

// Get file information with its full path
$file_id = isset($_GET['file']) ? $_GET['file'] : null;
if (!$file_id) {
    header("location: file_management.php");
    exit;
}

$sql = "SELECT f.*, 
        CONCAT(
            CASE 
                WHEN f.parent_directory = '' THEN ''
                ELSE CONCAT(f.parent_directory, '/')
            END,
            f.name
        ) as full_path
        FROM folders_files f 
        WHERE f.id = ? AND f.type = 'file'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $file_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

if (!$file) {
    header("location: file_management.php");
    exit;
}

// Generate breadcrumb data
$path_parts = $file['parent_directory'] ? explode('/', trim($file['parent_directory'], '/')) : [];
$breadcrumbs = [];
$current_path = '';
foreach ($path_parts as $part) {
    $current_path .= '/' . $part;
    $breadcrumbs[] = [
        'name' => $part,
        'path' => trim($current_path, '/')
    ];
}

// Define variables
$content = $file['content'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Edit File - <?php echo htmlspecialchars($file['name']); ?></title>
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
    <style>
        .field-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }
        .field-row input {
            flex: 1;
        }
        .remove-field {
            color: #dc3545;
            cursor: pointer;
        }
        .add-field-btn {
            margin-bottom: 20px;
        }
        .breadcrumb {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #495057;
        }
        
        .folder-icon {
            color: #ffc107;
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
                        <h3 class="page-title">
                            <span class="page-title-icon bg-gradient-primary text-white me-2">
                                <i class="mdi mdi-file-document"></i>
                            </span>
                            <?php echo htmlspecialchars($file['name']); ?>
                        </h3>
                    </div>

                    <!-- Add breadcrumb navigation -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="file_management.php">
                                    <i class="mdi mdi-folder folder-icon"></i> Root
                                </a>
                            </li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                            <li class="breadcrumb-item">
                                <a href="file_management.php?dir=<?php echo urlencode($crumb['path']); ?>">
                                    <?php echo htmlspecialchars($crumb['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </li>
                        </ol>
                    </nav>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <?php if ($_SESSION["is_editable"]): ?>
                                    <form id="dynamicForm" class="forms-sample">
                                        <div id="fieldsContainer">
                                            <?php 
                                            $saved_fields = json_decode($file['content'] ?? '[]', true);
                                            if (!empty($saved_fields)) {
                                                foreach ($saved_fields as $field) {
                                                    ?>
                                                    <div class="field-row">
                                                        <div class="form-group mb-0 me-2" style="flex: 1;">
                                                            <input type="text" class="form-control" placeholder="Field Name" value="<?php echo htmlspecialchars($field['name'] ?? ''); ?>">
                                                        </div>
                                                        <div class="form-group mb-0" style="flex: 1;">
                                                            <input type="text" class="form-control" placeholder="Field Value" value="<?php echo htmlspecialchars($field['value'] ?? ''); ?>">
                                                        </div>
                                                        <i class="mdi mdi-close-circle remove-field"></i>
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <div class="field-row">
                                                    <div class="form-group mb-0 me-2" style="flex: 1;">
                                                        <input type="text" class="form-control" placeholder="Field Name">
                                                    </div>
                                                    <div class="form-group mb-0" style="flex: 1;">
                                                        <input type="text" class="form-control" placeholder="Field Value">
                                                    </div>
                                                    <i class="mdi mdi-close-circle remove-field"></i>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                        
                                        <button type="button" class="btn btn-info btn-sm add-field-btn" onclick="addField()">
                                            <i class="mdi mdi-plus"></i> Add Field
                                        </button>
                                        
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-gradient-primary me-2" onclick="saveContent()">Save</button>
                                            <a href="file_management.php" class="btn btn-light">Cancel</a>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Field Name</th>
                                                    <th>Field Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $saved_fields = json_decode($file['content'] ?? '[]', true);
                                                if (!empty($saved_fields)) {
                                                    foreach ($saved_fields as $field) {
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($field['name'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars($field['value'] ?? ''); ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="2" class="text-center">No data available</td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <a href="file_management.php" class="btn btn-light">Back</a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script>
    // Add new field row
    function addField() {
        const container = document.getElementById('fieldsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'field-row';
        newRow.innerHTML = `
            <div class="form-group mb-0 me-2" style="flex: 1;">
                <input type="text" class="form-control" placeholder="Field Name">
            </div>
            <div class="form-group mb-0" style="flex: 1;">
                <input type="text" class="form-control" placeholder="Field Value">
            </div>
            <i class="mdi mdi-close-circle remove-field"></i>
        `;
        container.appendChild(newRow);

        // Add event listener to the new remove button
        newRow.querySelector('.remove-field').addEventListener('click', function() {
            this.parentElement.remove();
        });
    }

    // Add event listeners to initial remove buttons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.remove-field').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.remove();
            });
        });
    });

    function saveContent() {
        const fields = [];
        document.querySelectorAll('.field-row').forEach(row => {
            const inputs = row.querySelectorAll('input');
            fields.push({
                name: inputs[0].value,
                value: inputs[1].value
            });
        });

        const fileId = <?php echo $file_id; ?>;
        
        fetch('includes/save_file_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_id: fileId,
                content: JSON.stringify(fields)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('File saved successfully');
            } else {
                alert('Error saving file: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error saving file');
            console.error('Error:', error);
        });
    }
    </script>
</body>
</html>
