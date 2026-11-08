<?php
require_once __DIR__ . '/../includes/functions.php';
require_teacher();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Portal | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/logo.png?v=2">
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo">
      <div>
        <strong>AL FATIHA</strong>
        <span>TEACHER PORTAL</span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i> <span>Teacher Overview</span>
        </a>
      </li>
      <li>
        <a href="subjects.php" class="<?= ($current_script == 'subjects.php') ? 'active' : '' ?>">
          <i class="fas fa-book-open"></i> <span>My Assigned Subjects</span>
        </a>
      </li>
      <li>
        <a href="attendance.php" class="<?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-clipboard-user"></i> <span>Mark Attendance</span>
        </a>
      </li>
      <li>
        <a href="results.php" class="<?= ($current_script == 'results.php') ? 'active' : '' ?>">
          <i class="fas fa-pen-to-square"></i> <span>Input Marks & Grades</span>
        </a>
      </li>
      <li>
        <a href="students.php" class="<?= ($current_script == 'students.php') ? 'active' : '' ?>">
          <i class="fas fa-user-graduate"></i> <span>Class Roster</span>
        </a>
      </li>
      <li>
        <a href="../admin/timetable.php" target="_blank">
          <i class="fas fa-calendar-alt"></i> <span>Class Timetables</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar" style="background: var(--admin-gold); color: var(--admin-deep-navy);">T</div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span>Faculty Educator</span>
        </div>
      </div>
      <a href="logout.php" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout Session</span>
      </a>
    </div>
  </aside>

  <!-- Sidebar Backdrop for Mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Main Area -->
  <div class="main-wrapper">
    <div class="top-nav">
      <div class="top-nav-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
          <h2>Teacher Management Portal</h2>
        </div>
      </div>

      <div class="top-actions">
        <a href="../index.php" target="_blank" class="btn btn-view-site" style="background: var(--admin-bg); color: var(--admin-text); padding: 0.45rem 0.9rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fas fa-external-link-alt"></i> <span class="hide-mobile">View Website</span>
        </a>
        <a href="logout.php" class="btn-logout-mobile" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

    <div class="content-area">
