<?php
// Initialize the session
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
  header("location: index.php");
  exit;
}

// Database connection
require_once "includes/db/config.php";

// Check user role
$user_role = $_SESSION["role"];
$is_superadmin = $_SESSION["is_superadmin"];
$is_readable = $_SESSION["is_readable"];
$is_downloadable = $_SESSION["is_downloadable"];
$is_editable = $_SESSION["is_editable"];
?>

<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <div class="sidebar-sticky">
    <ul class="nav">
      <!-- <li class="nav-item">
        <a class="nav-link" href="documents.php">
          <span class="menu-title">Documents</span>
          <i class="mdi mdi-view-dashboard menu-icon"></i>
        </a>
      </li> -->
      <?php if ($is_readable == 1): ?>
      <li class="nav-item">
        <a class="nav-link" href="file_management.php">
          <span class="menu-title">File Management</span>
          <i class="mdi mdi-view-dashboard menu-icon"></i>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($user_role == 1): ?>
      <li class="nav-item">
        <a class="nav-link" href="users.php">
          <span class="menu-title">User Management</span>
          <i class="mdi mdi-view-dashboard menu-icon"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="activity_logs.php">
          <span class="menu-title">Activity Logs</span>
          <i class="mdi mdi-view-dashboard menu-icon"></i>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<!-- <style>
  .sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
  }

  .sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 48px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
  }

  @supports ((position: -webkit-sticky) or (position: sticky)) {
    .sidebar-sticky {
      position: -webkit-sticky;
      position: sticky;
    }
  }

  .main-panel {
    margin-left: 260px; /* Adjust this value based on your sidebar width */
  }

  @media (max-width: 991.98px) {
    .sidebar {
      position: fixed;
      top: 0;
      bottom: 0;
      left: -260px;
      z-index: 1000;
      transition: all 0.3s;
    }

    .sidebar.active {
      left: 0;
    }

    .main-panel {
      margin-left: 0;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtn = document.querySelector('.navbar-toggler');

    if (toggleSidebarBtn) {
      toggleSidebarBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
      });
    }

    // Close sidebar when clicking outside of it
    document.addEventListener('click', function(event) {
      const isClickInsideSidebar = sidebar.contains(event.target);
      const isClickOnToggleBtn = toggleSidebarBtn.contains(event.target);

      if (!isClickInsideSidebar && !isClickOnToggleBtn && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
      }
    });
  });
</script> -->