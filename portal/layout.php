<?php
require_once __DIR__ . '/../includes/functions.php';
require_parent();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Parent Portal | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=2">
  <link rel="icon" type="image/png" href="../assets/images/logo.png?v=2">
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo">
      <div>
        <strong>AL FATIHA</strong>
        <span>Parent Portal</span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-section-label">Student</li>
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i> <span>Student Profile</span>
        </a>
      </li>

      <li class="sidebar-section-label">Records</li>
      <li>
        <a href="results.php" class="<?= ($current_script == 'results.php') ? 'active' : '' ?>">
          <i class="fas fa-award"></i> <span>Report Card &amp; Results</span>
        </a>
      </li>
      <li>
        <a href="attendance.php" class="<?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-check"></i> <span>Attendance Record</span>
        </a>
      </li>
      <li>
        <a href="timetable.php" class="<?= ($current_script == 'timetable.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-alt"></i> <span>Class Timetable</span>
        </a>
      </li>

      <li class="sidebar-section-label">Billing</li>
      <li>
        <a href="fees.php" class="<?= ($current_script == 'fees.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> <span>Fees &amp; Payments</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar">
          <?= strtoupper(substr($user_name ?: 'P', 0, 1)) ?>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span>Guardian Account</span>
        </div>
      </div>
      <a href="logout.php" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
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
          <h2>Parent &amp; Student Portal</h2>
        </div>
      </div>

      <div class="top-actions">
        <a href="../index.php" target="_blank" class="btn-view-site">
          <i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Website</span>
        </a>
        <a href="logout.php" class="btn-logout-mobile" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

    <div class="content-area">
