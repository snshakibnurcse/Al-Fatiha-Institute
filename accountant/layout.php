<?php
require_once __DIR__ . '/../includes/functions.php';
require_accountant();

$current_script = basename($_SERVER['PHP_SELF']);
$user_name = get_current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Accounts & Finance Section | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/logo.png?v=2">
  <!-- Chart.js CDN for visual financial graphs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .sidebar {
      width: 260px !important;
      position: fixed !important;
      top: 0; bottom: 0; left: 0;
      z-index: 100;
    }
    .main-content, .main-wrapper {
      margin-left: 260px !important;
      width: calc(100% - 260px) !important;
      min-height: 100vh;
      flex: 1;
      display: flex;
      flex-direction: column;
      box-sizing: border-box !important;
    }
    .topbar, .top-nav {
      width: 100% !important;
      box-sizing: border-box !important;
    }
    .account-badge {
      background: #ecfdf5;
      color: #047857;
      border: 1px solid #a7f3d0;
      padding: 0.35rem 0.85rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.8rem;
    }
    .voucher-thumb {
      width: 45px;
      height: 45px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #cbd5e1;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .voucher-thumb:hover {
      transform: scale(1.15);
    }
    .badge-income {
      background: #dcfce7;
      color: #15803d;
      padding: 0.25rem 0.65rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.75rem;
    }
    .badge-expense {
      background: #fee2e2;
      color: #b91c1c;
      padding: 0.25rem 0.65rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.75rem;
    }
    .chart-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      border: 1px solid #e2e8f0;
      margin-bottom: 1.5rem;
    }
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #ffffff;
      border-radius: 20px;
      width: 90%;
      max-width: 650px;
      padding: 2rem;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }
    .close-modal {
      position: absolute;
      top: 1.2rem;
      right: 1.2rem;
      font-size: 1.5rem;
      color: #64748b;
      cursor: pointer;
      background: none;
      border: none;
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
        <small style="color: #10b981; font-size: 0.75rem; display: block; font-weight: 800;">ACCOUNTS SECTION</small>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-line"></i> Accounts Dashboard
        </a>
      </li>
      <li>
        <a href="fees.php" class="<?= ($current_script == 'fees.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> Monthly Fees & Due Tracker
        </a>
      </li>
      <li>
        <a href="expenses.php" class="<?= ($current_script == 'expenses.php') ? 'active' : '' ?>">
          <i class="fas fa-receipt"></i> Expense & Vouchers
        </a>
      </li>
      <li>
        <a href="income.php" class="<?= ($current_script == 'income.php') ? 'active' : '' ?>">
          <i class="fas fa-hand-holding-usd"></i> Income Ledger
        </a>
      </li>
      <li>
        <a href="reports.php" class="<?= ($current_script == 'reports.php') ? 'active' : '' ?>">
          <i class="fas fa-calculator"></i> Financial Reports
        </a>
      </li>
      <li style="margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.8rem;">
        <a href="../index.php" target="_blank">
          <i class="fas fa-globe"></i> View Website
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar" style="background: #10b981; color: #ffffff;">
          <i class="fas fa-wallet"></i>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars(get_current_user_name()) ?></strong>
          <span>Finance & Accounts</span>
        </div>
      </div>
      <a href="../login.php?logout=1" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout Session</span>
      </a>
    </div>
  </aside>

  <!-- Sidebar Backdrop for Mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Main Content Wrapper -->
  <div class="main-content">
    <!-- Topbar -->
    <header class="topbar">
      <div class="top-nav-left" style="display: flex; align-items: center; gap: 0.85rem;">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <h2 style="font-family: 'Outfit', sans-serif; margin: 0; font-size: 1.25rem; color: var(--admin-deep-navy);">
          School Accounts & Finance
        </h2>
      </div>

      <div style="display: flex; align-items: center; gap: 0.85rem;">
        <span class="account-badge hide-mobile">
          <i class="fas fa-user-tie"></i> <?= htmlspecialchars($user_name) ?>
        </span>
        <a href="../index.php" target="_blank" class="btn btn-view-site" style="background: var(--admin-bg); color: var(--admin-text); padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Website</span>
        </a>
        <a href="../login.php?logout=1" class="btn btn-logout-mobile" title="Logout" style="background: #fee2e2; color: #dc2626; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center;">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </header>

    <main class="page-body">
