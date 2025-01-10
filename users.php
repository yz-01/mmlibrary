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

// Fetch all users
$sql = "SELECT * FROM user ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>User Management</title>
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
                        <h3 class="page-title">User Management</h3>
                        <a href="create_user.php" class="btn btn-primary">
                            <i class="mdi mdi-account-plus"></i> Add New User
                        </a>
                    </div>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Expire Time</th>
                                                    <th>Permissions</th>
                                                    <th>Created At</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['username'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($row['expire_time']) {
                                                            echo round($row['expire_time']) . ' minutes';
                                                        } else {
                                                            echo 'No expiration';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $permissions = [];
                                                        if ($row['is_readable']) $permissions[] = 'Read';
                                                        if ($row['is_downloadable']) $permissions[] = 'Download';
                                                        if ($row['is_editable']) $permissions[] = 'Edit';
                                                        echo implode(', ', $permissions);
                                                        ?>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($row['is_block']): ?>
                                                            <span class="badge bg-danger">Blocked</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $row['id']; ?>)">
                                                            <i class="mdi mdi-delete"></i>
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

    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script>
    function createUser() {
        const username = document.getElementById('newUsername').value;
        const email = document.getElementById('newEmail').value;
        const password = document.getElementById('newPassword').value;
        const expireTime = document.getElementById('newExpireTime').value;
        const isReadable = document.getElementById('newIsReadable').checked ? 1 : 0;
        const isDownloadable = document.getElementById('newIsDownloadable').checked ? 1 : 0;
        const isEditable = document.getElementById('newIsEditable').checked ? 1 : 0;
        const isBlock = document.getElementById('newIsBlock').checked ? 1 : 0;

        fetch('includes/user_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                username: username,
                email: email,
                password: password,
                expire_time: expireTime ? expireTime * 60000 : null, // Convert to milliseconds
                is_readable: isReadable,
                is_downloadable: isDownloadable,
                is_editable: isEditable,
                is_block: isBlock
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function editUser(userData) {
        document.getElementById('editUserId').value = userData.id;
        document.getElementById('editUsername').value = userData.username;
        document.getElementById('editEmail').value = userData.email;
        document.getElementById('editExpireTime').value = userData.expire_time;
        
        // Convert string/number to boolean for checkboxes
        document.getElementById('editIsReadable').checked = userData.is_readable === 1 || userData.is_readable === true;
        document.getElementById('editIsDownloadable').checked = userData.is_downloadable === 1 || userData.is_downloadable === true;
        document.getElementById('editIsEditable').checked = userData.is_editable === 1 || userData.is_editable === true;
        document.getElementById('editIsBlock').checked = userData.is_block === 1 || userData.is_block === true;
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    function updateUser() {
        const id = document.getElementById('editUserId').value;
        const username = document.getElementById('editUsername').value;
        const email = document.getElementById('editEmail').value;
        const password = document.getElementById('editPassword').value;
        const expireTime = document.getElementById('editExpireTime').value;
        
        // Convert checkbox values to 1 or 0
        const isReadable = document.getElementById('editIsReadable').checked ? 1 : 0;
        const isDownloadable = document.getElementById('editIsDownloadable').checked ? 1 : 0;
        const isEditable = document.getElementById('editIsEditable').checked ? 1 : 0;
        const isBlock = document.getElementById('editIsBlock').checked ? 1 : 0;

        fetch('includes/user_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                id: id,
                username: username,
                email: email,
                password: password,
                expire_time: expireTime,
                is_readable: isReadable,
                is_downloadable: isDownloadable,
                is_editable: isEditable,
                is_block: isBlock
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteUser(id) {
        if (confirm('Are you sure you want to delete this user?')) {
            fetch('includes/user_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }
    </script>
</body>
</html> 