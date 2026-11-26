<?php
require_once __DIR__ . '/../includes/functions.php';
require_chairman();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
$user_role = get_current_user_role();
$can_access_admin = in_array($user_role, ['super_admin', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Chairman Executive Monitor | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=2">
  <link rel="icon" type="image/png" href="../assets/images/logo.png?v=2">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .exec-hero {
      background: linear-gradient(135deg, #032d4e 0%, #064878 65%, #0e73b9 100%);
      color: #ffffff;
      border-radius: 16px;
      padding: 26px 28px;
      margin-bottom: 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
      box-shadow: 0 12px 30px rgba(3, 45, 78, 0.16);
      position: relative;
      overflow: hidden;
    }
    .exec-hero::after {
      content: '';
      position: absolute;
      right: -40px;
      top: -40px;
      width: 220px;
      height: 220px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(243, 181, 39, 0.22) 0%, rgba(243, 181, 39, 0) 70%);
      pointer-events: none;
    }
    .exec-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(243, 181, 39, 0.18);
      border: 1px solid rgba(243, 181, 39, 0.45);
      color: #fde68a;
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 4px 12px;
      border-radius: 20px;
    }
    .kpi-exec-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 14px;
      margin-bottom: 22px;
    }
    .kpi-exec-card {
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      padding: 18px 20px;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .kpi-exec-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }
    .kpi-exec-icon {
      width: 42px;
      height: 42px;
      border-radius: 11px;
      display: grid;
      place-items: center;
      font-size: 1.15rem;
      margin-bottom: 10px;
    }
    .progress-track {
      width: 100%;
      height: 8px;
      background: #f1f5f9;
      border-radius: 999px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      border-radius: 999px;
    }
  </style>
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo">
      <div>
        <strong>AL FATIHA</strong>
        <span>Chairman Monitor</span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-section-label">Executive Overview</li>
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-pie"></i> <span>Executive Dashboard</span>
        </a>
      </li>

      <li class="sidebar-section-label">Institution Monitors</li>
      <li>
        <a href="students.php" class="<?= ($current_script == 'students.php') ? 'active' : '' ?>">
          <i class="fas fa-user-graduate"></i> <span>Class-Wise Students</span>
        </a>
      </li>
      <li>
        <a href="finance.php" class="<?= ($current_script == 'finance.php') ? 'active' : '' ?>">
          <i class="fas fa-coins"></i> <span>Income &amp; Expenses</span>
        </a>
      </li>
      <li>
        <a href="attendance.php" class="<?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
          <i class="fas fa-user-check"></i> <span>Staff Attendance</span>
        </a>
      </li>
      <li>
        <a href="payroll.php" class="<?= ($current_script == 'payroll.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> <span>Payroll Overview</span>
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
        <div class="user-avatar" style="background: #f3b527; color: #032d4e;">
          <?= strtoupper(substr($user_name ?: 'C', 0, 1)) ?>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span><?= ($user_role === 'chairman') ? 'Chairman (View Only)' : ucfirst(str_replace('_', ' ', $user_role)) ?></span>
        </div>
      </div>
      <a href="logout.php" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
      </a>
    </div>
  </aside>

  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main-wrapper">
    <div class="top-nav">
      <div class="top-nav-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
          <h2>Chairman Executive Monitor</h2>
        </div>
      </div>

      <div class="top-actions">
        <span style="background: #f1f5f9; color: #032d4e; padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" class="hide-mobile">
          <i class="fas fa-eye" style="color: #0e73b9;"></i> Read-Only Oversight
        </span>
        <a href="../index.php" target="_blank" class="btn-view-site">
          <i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Website</span>
        </a>
        <a href="logout.php" class="btn-logout-mobile" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

    <div class="content-area">
