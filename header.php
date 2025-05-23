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
                    <!-- <button id="extendSessionBtn" class="btn btn-sm btn-outline-primary ms-2" onclick="extendSession(); return false;">Extend Session</button> -->
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

            // Function to extend the session
            function extendSession() {
                fetch('includes/extend_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' || data.status === 'warning') {
                        // Update the expiration time
                        expireTime = data.new_expire_time * 1000;
                        
                        // Reset the timer display
                        document.getElementById('sessionTimer').classList.remove('bg-danger', 'text-white');
                        updateTimer();
                        
                        // Show success message
                        alert('Your session has been extended successfully.');
                    } else {
                        alert('Failed to extend session: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error extending session:', error);
                    // The session may still have been extended even if there was an error in the response
                    // So we'll refresh the timer anyway
                    location.reload();
                });
            }

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
                    
                    // Show confirmation dialog if we haven't shown it yet for this countdown
                    if (!sessionStorage.getItem('alertShown')) {
                        if (confirm('Your session will expire in less than 30 seconds! Would you like to extend your session?')) {
                            // User clicked OK - extend the session
                            extendSession();
                        }
                        sessionStorage.setItem('alertShown', 'true');
                    }
                }
                
                // Reset the alert flag if we're back above 30 seconds
                // This allows the alert to show again in the next countdown
                if (distance > 30000) {
                    sessionStorage.removeItem('alertShown');
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
                    <a class="dropdown-item" href="change_password.php">
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
