<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
$user_role = get_current_user_role();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Panel | AL FATIHA INSTITUTE</title>
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
        <span><?= ($user_role === 'super_admin') ? 'Super Admin' : (($user_role === 'principal') ? 'Principal' : 'Admin') ?></span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-section-label">Overview</li>
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
      </li>

      <li class="sidebar-section-label">Academics</li>
      <li>
        <a href="admissions.php" class="<?= ($current_script == 'admissions.php') ? 'active' : '' ?>">
          <i class="fas fa-file-signature"></i> <span>Online Applications</span>
        </a>
      </li>
      <li>
        <a href="students.php" class="<?= ($current_script == 'students.php' || $current_script == 'student_profile.php') ? 'active' : '' ?>">
          <i class="fas fa-user-graduate"></i> <span>Students Directory</span>
        </a>
      </li>
      <li>
        <a href="promotions.php" class="<?= ($current_script == 'promotions.php') ? 'active' : '' ?>">
          <i class="fas fa-level-up-alt"></i> <span>Class Promotion Engine</span>
        </a>
      </li>
      <li>
        <a href="teachers.php" class="<?= ($current_script == 'teachers.php') ? 'active' : '' ?>">
          <i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span>
        </a>
      </li>
      <li>
        <a href="classes.php" class="<?= ($current_script == 'classes.php') ? 'active' : '' ?>">
          <i class="fas fa-school"></i> <span>Classes &amp; Fees</span>
        </a>
      </li>
      <li>
        <a href="subjects.php" class="<?= ($current_script == 'subjects.php') ? 'active' : '' ?>">
          <i class="fas fa-book-open"></i> <span>Subjects &amp; Assign</span>
        </a>
      </li>
      <li>
        <a href="attendance.php" class="<?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-clipboard-user"></i> <span>Student Attendance</span>
        </a>
      </li>
      <li>
        <a href="teacher_attendance.php" class="<?= ($current_script == 'teacher_attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-user-check"></i> <span>Teacher Attendance</span>
        </a>
      </li>
      <li>
        <a href="timetable.php" class="<?= ($current_script == 'timetable.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-alt"></i> <span>Class Timetable</span>
        </a>
      </li>
      <li>
        <a href="results.php" class="<?= ($current_script == 'results.php') ? 'active' : '' ?>">
          <i class="fas fa-award"></i> <span>Exam Marks &amp; Results</span>
        </a>
      </li>

      <li class="sidebar-section-label">Finance</li>
      <li>
        <a href="fees.php" class="<?= ($current_script == 'fees.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> <span>Fees &amp; Due Tracker</span>
        </a>
      </li>
      <li>
        <a href="expenses.php" class="<?= ($current_script == 'expenses.php') ? 'active' : '' ?>">
          <i class="fas fa-receipt"></i> <span>Expense &amp; Vouchers</span>
        </a>
      </li>
      <li>
        <a href="income.php" class="<?= ($current_script == 'income.php') ? 'active' : '' ?>">
          <i class="fas fa-hand-holding-usd"></i> <span>Income Ledger</span>
        </a>
      </li>
      <li>
        <a href="reports.php" class="<?= ($current_script == 'reports.php') ? 'active' : '' ?>">
          <i class="fas fa-calculator"></i> <span>Financial Reports</span>
        </a>
      </li>

      <li class="sidebar-section-label">Communication</li>
      <li>
        <a href="notices.php" class="<?= ($current_script == 'notices.php') ? 'active' : '' ?>">
          <i class="fas fa-bullhorn"></i> <span>Notices</span>
        </a>
      </li>
      <li>
        <a href="inquiries.php" class="<?= ($current_script == 'inquiries.php') ? 'active' : '' ?>">
          <i class="fas fa-envelope-open-text"></i> <span>Website Inquiries</span>
        </a>
      </li>
      <?php if (is_super_admin()): ?>
      <li>
        <a href="users.php" class="<?= ($current_script == 'users.php') ? 'active' : '' ?>">
          <i class="fas fa-users-cog"></i> <span>User Management</span>
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar">
          <?= strtoupper(substr($user_name ?: 'A', 0, 1)) ?>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span><?= ($user_role === 'super_admin') ? 'Super Admin' : (($user_role === 'principal') ? 'Principal' : 'Administrator') ?></span>
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
          <h2>School Management</h2>
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
