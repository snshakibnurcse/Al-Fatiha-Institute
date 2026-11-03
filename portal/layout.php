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
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.svg" alt="AFI Logo">
      <div>
        <strong>AL FATIHA</strong>
        <span>PARENT PORTAL</span>
      </div>
    </div>

    <ul class="sidebar-menu">
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i> <span>Student Profile</span>
        </a>
      </li>
      <li>
        <a href="results.php" class="<?= ($current_script == 'results.php') ? 'active' : '' ?>">
          <i class="fas fa-award"></i> <span>Report Card & Results</span>
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
      <li>
        <a href="fees.php" class="<?= ($current_script == 'fees.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> <span>Fees & Payments</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar" style="background: var(--admin-primary); color: #ffffff;">P</div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span>Guardian Account</span>
        </div>
      </div>
      <a href="logout.php" style="display: inline-block; margin-top: 0.8rem; color: #ef4444; font-weight: 700; font-size: 0.85rem; text-decoration: none;">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main Area -->
  <div class="main-wrapper" style="margin-left: 260px !important; width: calc(100% - 260px) !important; flex: 1; display: flex; flex-direction: column; min-height: 100vh; box-sizing: border-box;">
    <div class="top-nav">
      <div class="page-title">
        <h2>Parent & Student Access Portal</h2>
      </div>

      <div class="top-actions">
        <a href="../index.php" target="_blank" class="btn" style="background: var(--admin-bg); color: var(--admin-text); padding: 0.4rem 0.9rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 700;">
          <i class="fas fa-external-link-alt"></i> View Public Website
        </a>
      </div>
    </div>

    <div class="content-area">
