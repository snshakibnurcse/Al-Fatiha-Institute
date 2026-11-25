<?php
require_once __DIR__ . '/../includes/functions.php';
require_hr();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
$user_role = get_current_user_role();
$can_access_admin = in_array($user_role, ['super_admin', 'admin', 'principal'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HR &amp; Payroll Portal | AL FATIHA INSTITUTE</title>
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
        <span>HR &amp; Payroll</span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-section-label">HR Workspace</li>
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i> <span>HR Dashboard</span>
        </a>
      </li>
      <li>
        <a href="employees.php" class="<?= ($current_script == 'employees.php') ? 'active' : '' ?>">
          <i class="fas fa-id-card-alt"></i> <span>Employee Directory</span>
        </a>
      </li>

      <li class="sidebar-section-label">Time &amp; Leave</li>
      <li>
        <a href="attendance.php" class="<?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-user-check"></i> <span>Staff Attendance</span>
        </a>
      </li>
      <li>
        <a href="leaves.php" class="<?= ($current_script == 'leaves.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-minus"></i> <span>Leave Management</span>
        </a>
      </li>

      <li class="sidebar-section-label">Compensation</li>
      <li>
        <a href="payroll.php" class="<?= ($current_script == 'payroll.php' || $current_script == 'payslip.php') ? 'active' : '' ?>">
          <i class="fas fa-money-check-alt"></i> <span>Salary &amp; Payroll</span>
        </a>
      </li>

      <?php if ($can_access_admin): ?>
      <li class="sidebar-section-label">Administration</li>
      <li>
        <a href="../admin/dashboard.php">
          <i class="fas fa-arrow-left"></i> <span>Back to Admin Panel</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar">
          <?= strtoupper(substr($user_name ?: 'H', 0, 1)) ?>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span><?= ($user_role === 'hr') ? 'HR Officer' : ucfirst(str_replace('_', ' ', $user_role)) ?></span>
        </div>
      </div>
      <a href="logout.php" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
      </a>
    </div>
  </aside>

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Main Area -->
  <div class="main-wrapper">
    <div class="top-nav">
      <div class="top-nav-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
          <h2>HR &amp; Payroll Management</h2>
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

