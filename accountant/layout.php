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
  <title>Accounts &amp; Finance | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=2">
  <link rel="icon" type="image/png" href="../assets/images/logo.png?v=2">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .account-badge {
      font-family: 'IBM Plex Mono', monospace;
      background: #eef7f1;
      color: #15803d;
      border: 1px solid rgba(22, 163, 74, 0.22);
      padding: 4px 10px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 12px;
    }
    .voucher-thumb {
      width: 44px;
      height: 44px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--admin-border-strong);
      cursor: pointer;
    }
    .badge-income {
      font-family: 'IBM Plex Mono', monospace;
      background: #eef7f1;
      color: #15803d;
      padding: 3px 8px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 11px;
    }
    .badge-expense {
      font-family: 'IBM Plex Mono', monospace;
      background: #fef2f2;
      color: #b91c1c;
      padding: 3px 8px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 11px;
    }
    .chart-card {
      background: var(--admin-card);
      border-radius: 12px;
      padding: 20px 24px;
      box-shadow: 0 1px 2px rgba(10, 20, 36, 0.04);
      border: 1px solid var(--admin-border);
      margin-bottom: 20px;
    }
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(6, 28, 56, 0.55);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #ffffff;
      border-radius: 14px;
      width: 92%;
      max-width: 640px;
      padding: 24px;
      box-shadow: 0 16px 36px rgba(10, 20, 36, 0.14);
      border: 1px solid var(--admin-border);
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }
    .close-modal {
      position: absolute;
      top: 16px;
      right: 16px;
      font-size: 20px;
      color: var(--admin-text-muted);
      cursor: pointer;
      background: none;
      border: none;
    }
    @media (max-width: 768px) {
      .modal-content {
        padding: 20px 16px;
        width: 95%;
      }
      .chart-card {
        padding: 16px;
      }
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
        <span>Accounts Section</span>
      </div>
      <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-section-label">Overview</li>
      <li>
        <a href="dashboard.php" class="<?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
          <i class="fas fa-chart-line"></i> <span>Accounts Dashboard</span>
        </a>
      </li>

      <li class="sidebar-section-label">Billing &amp; Ledger</li>
      <li>
        <a href="fees.php" class="<?= ($current_script == 'fees.php') ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i> <span>Monthly Fees &amp; Due</span>
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

      <li class="sidebar-section-label">HR &amp; Self-Service</li>
      <li>
        <a href="my_hr.php" class="<?= ($current_script == 'my_hr.php') ? 'active' : '' ?>">
          <i class="fas fa-calendar-minus"></i> <span>Leave &amp; Payslips</span>
        </a>
      </li>

      <li class="sidebar-section-label">External</li>
      <li>
        <a href="../index.php" target="_blank">
          <i class="fas fa-globe"></i> <span>School Website</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-badge">
        <div class="user-avatar">
          <?= strtoupper(substr($user_name ?: 'A', 0, 1)) ?>
        </div>
        <div class="user-info">
          <strong><?= htmlspecialchars($user_name) ?></strong>
          <span>Finance &amp; Accounts</span>
        </div>
      </div>
      <a href="logout.php" class="sidebar-logout-btn">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- Sidebar Backdrop for Mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Main Content Wrapper -->
  <div class="main-content">
    <header class="topbar">
      <div class="top-nav-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
          <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
          <h2>Accounts &amp; Finance</h2>
        </div>
      </div>

      <div class="top-actions">
        <span class="account-badge hide-mobile">
          <?= htmlspecialchars($user_name) ?>
        </span>
        <a href="../index.php" target="_blank" class="btn-view-site">
          <i class="fas fa-external-link-alt"></i> <span class="hide-mobile">Website</span>
        </a>
        <a href="logout.php" class="btn-logout-mobile" title="Logout">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </header>

    <main class="page-body">
