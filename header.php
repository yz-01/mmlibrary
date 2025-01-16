<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>

<nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
        <a class="navbar-brand brand-logo" href="documents.php">
            <img src="assets/images/crm_logo.png" alt="logo" style="height: 40px;" />
        </a>
        <a class="navbar-brand brand-logo-mini" href="documents.php">
            <img src="assets/images/crm_logo_mini.png" alt="logo" />
        </a>
    </div>
    <div class="navbar-menu-wrapper d-flex align-items-stretch">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
            <span class="mdi mdi-menu"></span>
        </button>
        <ul class="navbar-nav navbar-nav-right">
            <?php if (!isset($_SESSION["role"]) || $_SESSION["role"] != 1): ?>
            <li class="nav-item">
                <div class="nav-link">
                    <span class="text-danger fw-bold" id="sessionTimer"></span>
                </div>
            </li>
            <style>
            #sessionTimer {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
                border-radius: 4px;
                background: #fff5f5;
            }

            @media (max-width: 991.98px) {
                .navbar-brand-wrapper {
                    width: 70px;
                }
                
                .navbar-menu-wrapper {
                    padding: 0 1rem;
                }
                
                .brand-logo {
                    display: none;
                }
                
                .brand-logo-mini {
                    display: block !important;
                }
            }
            </style>
            <script>
            // Calculate the remaining time
            const expireTime = <?php 
                // Use existing timestamp if set, otherwise create new one
                if (!isset($_SESSION["expire_timestamp"])) {
                    $minutes = isset($_SESSION["expire_time"]) ? $_SESSION["expire_time"] : 0;
                    $_SESSION["expire_timestamp"] = time() + ($minutes * 60);
                }
                echo $_SESSION["expire_timestamp"];
            ?> * 1000; // Convert to milliseconds

            function updateTimer() {
                const now = new Date().getTime();
                const distance = expireTime - now;
                
                if (distance <= 0) {
                    // Session expired
                    clearInterval(timerInterval);
                    document.getElementById('sessionTimer').innerHTML = "Session Expired";
                    window.location.href = 'logout.php?expired=1';
                    return;
                }
                
                // Calculate minutes and seconds
                const minutes = Math.floor(distance / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Display the timer with leading zeros
                document.getElementById('sessionTimer').innerHTML = 
                    `Session expires in: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                    
                // Add warning class when less than 30 seconds
                if (distance <= 30000) {
                    document.getElementById('sessionTimer').classList.add('bg-danger', 'text-white');
                }
            }

            // Update timer every second
            const timerInterval = setInterval(updateTimer, 1000);
            updateTimer(); // Initial call
            </script>
            <?php endif; ?>
            <li class="nav-item nav-profile dropdown">
                <a class="nav-link dropdown-toggle" id="profileDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="nav-profile-text">
                        <p class="mb-1 text-black"><?php echo htmlspecialchars($_SESSION["email"]); ?></p>
                    </div>
                </a>
                <div class="dropdown-menu navbar-dropdown" aria-labelledby="profileDropdown">
                    <a class="dropdown-item" href="#" onclick="openChangePasswordModal()">
                        <i class="mdi mdi-key me-2 text-primary"></i> Change Password 
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="mdi mdi-logout me-2 text-primary"></i> Logout 
                    </a>
                </div>
            </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
            <span class="mdi mdi-menu"></span>
        </button>
    </div>
</nav>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="changePassword()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function openChangePasswordModal() {
    $('#changePasswordModal').modal('show');
}

function changePassword() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        Swal.fire('Error', 'New passwords do not match!', 'error');
        return;
    }
    
    const data = {
        action: 'change_password',
        user_id: <?php echo $_SESSION['id']; ?>,
        current_password: currentPassword,
        new_password: newPassword
    };
    
    fetch('includes/user_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Success', data.message, 'success');
            $('#changePasswordModal').modal('hide');
            document.getElementById('changePasswordForm').reset();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'An error occurred while changing the password.', 'error');
    });
}
</script>
