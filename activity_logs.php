<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Database connection
require_once "includes/db/config.php";

// Fetch logs with user information
$sql = "SELECT al.*, u.username, u.email 
        FROM activity_logs al 
        LEFT JOIN user u ON al.user_id = u.id 
        ORDER BY al.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Activity Logs</title>
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
                        <h3 class="page-title">Activity Logs</h3>
                    </div>

                    <div class="row">
                        <div class="col-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="logsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>User</th>
                                                    <th>IP Address</th>
                                                    <th>Location</th>
                                                    <th>Action</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = $result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                                                        <td>
                                                            <?php 
                                                            if ($row['user_id']) {
                                                                echo htmlspecialchars($row['email'] ?? 'N/A');
                                                            } else {
                                                                echo 'System';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($row['location'])) {
                                                                $location = json_decode($row['location'], true);
                                                                if ($location) {
                                                                    $locationParts = [];
                                                                    if (!empty($location['city'])) {
                                                                        $locationParts[] = htmlspecialchars($location['city']);
                                                                    }
                                                                    if (!empty($location['region'])) {
                                                                        $locationParts[] = htmlspecialchars($location['region']);
                                                                    }
                                                                    if (!empty($location['country'])) {
                                                                        $locationParts[] = htmlspecialchars($location['country']);
                                                                    }
                                                                    echo implode(', ', $locationParts);
                                                                }
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $actionClass = '';
                                                            switch ($row['action']) {
                                                                case 'LOGIN':
                                                                    $actionClass = 'badge badge-info';
                                                                    break;
                                                                case 'DOCUMENT_UPLOAD':
                                                                    $actionClass = 'badge badge-primary';
                                                                    break;
                                                                case 'LOGIN_BLOCKED':
                                                                    $actionClass = 'badge badge-danger';
                                                                    break;
                                                                default:
                                                                    $actionClass = 'badge badge-secondary';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $actionClass; ?>">
                                                                <?php echo htmlspecialchars($row['action']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = $row['status'] === 'SUCCESS' 
                                                                ? 'badge badge-success' 
                                                                : 'badge badge-danger';
                                                            ?>
                                                            <span class="<?php echo $statusClass; ?>">
                                                                <?php echo htmlspecialchars($row['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($row['details'])): ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-info view-details" 
                                                                        data-bs-toggle="tooltip" 
                                                                        data-bs-placement="top" 
                                                                        title="<?php echo htmlspecialchars($row['details']); ?>">
                                                                    View
                                                                </button>
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

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="detailsContent"></p>
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
        $('#logsTable').DataTable({
            "order": [[0, "desc"]], // Sort by date/time by default
            "pageLength": 25 // Show 25 entries per page
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle details view
        $('.view-details').click(function() {
            var details = $(this).attr('data-bs-original-title');
            $('#detailsContent').text(details);
            var detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
            detailsModal.show();
        });
    });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?> 