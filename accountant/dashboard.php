<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

// Period & Date Range Filter Logic
$period = sanitize($_GET['period'] ?? 'all');
$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');

$today = date('Y-m-d');
$firstDayMonth = date('Y-m-01');
$lastDayMonth = date('Y-m-t');
$firstDayYear = date('Y-01-01');
$lastDayYear = date('Y-12-31');

$filterStart = null;
$filterEnd = null;
$filterLabel = "All Time Financial Summary";

if ($period === 'today') {
    $filterStart = $today;
    $filterEnd = $today;
    $filterLabel = "Daily Summary (" . format_date($today) . ")";
} elseif ($period === 'this_month') {
    $filterStart = $firstDayMonth;
    $filterEnd = $lastDayMonth;
    $filterLabel = "Monthly Summary (" . date('F Y') . ")";
} elseif ($period === 'this_quarter') {
    $currentMonth = date('n');
    $quarterStartMonth = floor(($currentMonth - 1) / 3) * 3 + 1;
    $filterStart = date('Y-' . sprintf('%02d', $quarterStartMonth) . '-01');
    $filterEnd = date('Y-m-t', strtotime("+2 months", strtotime($filterStart)));
    $filterLabel = "Quarterly Summary (" . format_date($filterStart) . " to " . format_date($filterEnd) . ")";
} elseif ($period === 'this_year') {
    $filterStart = $firstDayYear;
    $filterEnd = $lastDayYear;
    $filterLabel = "Yearly Summary (" . date('Y') . ")";
} elseif ($period === 'custom' && !empty($startDate) && !empty($endDate)) {
    $filterStart = $startDate;
    $filterEnd = $endDate;
    $filterLabel = "Custom Range (" . format_date($startDate) . " to " . format_date($endDate) . ")";
} else {
    $period = 'all';
}

// Prepare SQL Queries based on filter
if ($filterStart && $filterEnd) {
    // Filtered Queries
    $stmtInc = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Income' AND transaction_date BETWEEN ? AND ?");
    $stmtInc->execute([$filterStart, $filterEnd]);
    $totalIncome = $stmtInc->fetchColumn() ?: 0;

    $stmtExp = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Expense' AND transaction_date BETWEEN ? AND ?");
    $stmtExp->execute([$filterStart, $filterEnd]);
    $totalExpense = $stmtExp->fetchColumn() ?: 0;

    $stmtVouch = $db->prepare("SELECT COUNT(*) FROM accounts_transactions WHERE voucher_file IS NOT NULL AND voucher_file != '' AND transaction_date BETWEEN ? AND ?");
    $stmtVouch->execute([$filterStart, $filterEnd]);
    $totalVouchers = $stmtVouch->fetchColumn() ?: 0;

    // Filtered Monthly Summary
    $stmtMonthly = $db->prepare("
        SELECT 
            MONTH(transaction_date) as month_num,
            SUM(CASE WHEN type='Income' THEN amount ELSE 0 END) as inc,
            SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END) as exp
        FROM accounts_transactions
        WHERE transaction_date BETWEEN ? AND ?
        GROUP BY MONTH(transaction_date)
        ORDER BY month_num ASC
    ");
    $stmtMonthly->execute([$filterStart, $filterEnd]);
    $monthlyData = $stmtMonthly->fetchAll();

    // Filtered Expense Category Breakdown
    $stmtCatExp = $db->prepare("
        SELECT category, SUM(amount) as total 
        FROM accounts_transactions 
        WHERE type='Expense' AND transaction_date BETWEEN ? AND ?
        GROUP BY category 
        ORDER BY total DESC
    ");
    $stmtCatExp->execute([$filterStart, $filterEnd]);
    $catExpenses = $stmtCatExp->fetchAll();

    // Filtered Detailed Transactions List
    $stmtTxns = $db->prepare("SELECT * FROM accounts_transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC, id DESC");
    $stmtTxns->execute([$filterStart, $filterEnd]);
    $detailedTxns = $stmtTxns->fetchAll();
} else {
    // All Time Queries
    $totalIncome = $db->query("SELECT SUM(amount) FROM accounts_transactions WHERE type='Income'")->fetchColumn() ?: 0;
    $totalExpense = $db->query("SELECT SUM(amount) FROM accounts_transactions WHERE type='Expense'")->fetchColumn() ?: 0;
    $totalVouchers = $db->query("SELECT COUNT(*) FROM accounts_transactions WHERE voucher_file IS NOT NULL AND voucher_file != ''")->fetchColumn() ?: 0;

    $currentYear = date('Y');
    $monthlyData = $db->query("
        SELECT 
            MONTH(transaction_date) as month_num,
            SUM(CASE WHEN type='Income' THEN amount ELSE 0 END) as inc,
            SUM(CASE WHEN type='Expense' THEN amount ELSE 0 END) as exp
        FROM accounts_transactions
        WHERE YEAR(transaction_date) = '$currentYear'
        GROUP BY MONTH(transaction_date)
        ORDER BY month_num ASC
    ")->fetchAll();

    $catExpenses = $db->query("
        SELECT category, SUM(amount) as total 
        FROM accounts_transactions 
        WHERE type='Expense' 
        GROUP BY category 
        ORDER BY total DESC
    ")->fetchAll();

    $detailedTxns = $db->query("SELECT * FROM accounts_transactions ORDER BY transaction_date DESC, id DESC LIMIT 50")->fetchAll();
}

$netBalance = $totalIncome - $totalExpense;
?>

<!-- Header Banner -->
<div style="background: linear-gradient(135deg, #032d4e 0%, #064878 100%); color: #ffffff; padding: 2rem; border-radius: 20px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 30px rgba(3, 45, 78, 0.15);">
  <div>
    <span style="color: #f3b527; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">FINANCIAL DASHBOARD</span>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.1rem; color: #ffffff; margin: 0.2rem 0;">School Accounts & Cash Summary</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 0; font-size: 0.95rem;">
      Real-time overview of school revenues, voucher expenses, cash balances, and filtered financial audit statements.
    </p>
  </div>
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="fees.php?action=issue_invoice" class="btn" style="background: #2563eb; color: #ffffff; padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
      <i class="fas fa-file-invoice"></i> Issue Invoice (Khata/Book/Fees)
    </a>
    <a href="expenses.php?action=add" class="btn" style="background: #ef4444; color: #ffffff; padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);">
      <i class="fas fa-plus-circle"></i> Add Expense
    </a>
    <a href="fees.php" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);">
      <i class="fas fa-file-invoice-dollar"></i> Fees &amp; Due Tracker
    </a>
    <a href="income.php?action=add" class="btn" style="background: #10b981; color: #ffffff; padding: 0.75rem 1.25rem; border-radius: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
      <i class="fas fa-plus-circle"></i> Add Income
    </a>
  </div>
</div>

<!-- Time & Date Range Filter Controls Card -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.25rem 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 2rem;">
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: flex-end; justify-content: space-between;">
    
    <!-- Preset Filter Pills -->
    <div>
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.4rem;">
        <i class="fas fa-calendar-alt" style="color: #0e73b9;"></i> Time Period Presets
      </label>
      <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
        <a href="dashboard.php?period=all" class="btn" style="background: <?= $period === 'all' ? '#032d4e' : '#f1f5f9' ?>; color: <?= $period === 'all' ? '#ffffff' : '#334155' ?>; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          All Time
        </a>
        <a href="dashboard.php?period=today" class="btn" style="background: <?= $period === 'today' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'today' ? '#ffffff' : '#334155' ?>; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          Today
        </a>
        <a href="dashboard.php?period=this_month" class="btn" style="background: <?= $period === 'this_month' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'this_month' ? '#ffffff' : '#334155' ?>; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          This Month
        </a>
        <a href="dashboard.php?period=this_quarter" class="btn" style="background: <?= $period === 'this_quarter' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'this_quarter' ? '#ffffff' : '#334155' ?>; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          This Quarter
        </a>
        <a href="dashboard.php?period=this_year" class="btn" style="background: <?= $period === 'this_year' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'this_year' ? '#ffffff' : '#334155' ?>; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          This Year (<?= date('Y') ?>)
        </a>
      </div>
    </div>

    <!-- Custom Range Inputs -->
    <div style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
      <input type="hidden" name="period" value="custom">
      <div>
        <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStart ?: date('Y-m-01')) ?>" style="padding: 0.55rem; border-radius: 8px; border: 1.5px solid #cbd5e1;">
      </div>
      <div>
        <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEnd ?: date('Y-m-d')) ?>" style="padding: 0.55rem; border-radius: 8px; border: 1.5px solid #cbd5e1;">
      </div>
      <button type="submit" class="btn" style="background: #10b981; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
        <i class="fas fa-filter"></i> Filter Range
      </button>
      <?php if ($period !== 'all'): ?>
        <a href="dashboard.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none;">Reset</a>
      <?php endif; ?>
    </div>

  </form>

  <?php if ($period !== 'all'): ?>
    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; font-size: 0.9rem; color: #0369a1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
      <div>
        <i class="fas fa-info-circle"></i> Active Filter: <strong><?= htmlspecialchars($filterLabel) ?></strong> 
        (Found <strong><?= count($detailedTxns) ?></strong> transaction records)
      </div>
      <a href="reports.php?period=<?= $period ?>&start_date=<?= $filterStart ?>&end_date=<?= $filterEnd ?>" target="_blank" style="color: #0284c7; font-weight: 700; text-decoration: none; font-size: 0.85rem;">
        <i class="fas fa-print"></i> Print Full Audit Statement
      </a>
    </div>
  <?php endif; ?>

</div>

<!-- KPI Cards Grid -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  
  <div class="kpi-card" style="background: #ffffff; border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
    <div style="width: 58px; height: 58px; border-radius: 14px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
      <i class="fas fa-arrow-down"></i>
    </div>
    <div>
      <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Total Income</span>
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.7rem; color: #0f172a; margin: 0.1rem 0;"><?= format_currency($totalIncome) ?></h3>
      <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700;"><i class="fas fa-check-circle"></i> Fees & Revenue Collections</span>
    </div>
  </div>

  <div class="kpi-card" style="background: #ffffff; border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
    <div style="width: 58px; height: 58px; border-radius: 14px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
      <i class="fas fa-arrow-up"></i>
    </div>
    <div>
      <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Total Expenses</span>
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.7rem; color: #0f172a; margin: 0.1rem 0;"><?= format_currency($totalExpense) ?></h3>
      <span style="font-size: 0.75rem; color: #dc2626; font-weight: 700;"><i class="fas fa-file-invoice"></i> Salaries, Bills & Vouchers</span>
    </div>
  </div>

  <div class="kpi-card" style="background: #ffffff; border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
    <div style="width: 58px; height: 58px; border-radius: 14px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
      <i class="fas fa-wallet"></i>
    </div>
    <div>
      <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Net Cash Balance</span>
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.7rem; color: <?= $netBalance >= 0 ? '#0284c7' : '#dc2626' ?>; margin: 0.1rem 0;"><?= format_currency($netBalance) ?></h3>
      <span style="font-size: 0.75rem; color: #0284c7; font-weight: 700;"><i class="fas fa-balance-scale"></i> Income - Expenses</span>
    </div>
  </div>

  <div class="kpi-card" style="background: #ffffff; border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
    <div style="width: 58px; height: 58px; border-radius: 14px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.6rem;">
      <i class="fas fa-receipt"></i>
    </div>
    <div>
      <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Attached Vouchers</span>
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.7rem; color: #0f172a; margin: 0.1rem 0;"><?= $totalVouchers ?></h3>
      <span style="font-size: 0.75rem; color: #d97706; font-weight: 700;"><i class="fas fa-paperclip"></i> Memo & Bill Copies</span>
    </div>
  </div>

</div>

<!-- Financial Charts Section -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
  
  <!-- Monthly Income vs Expense Comparison Bar Chart -->
  <div class="chart-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; color: #032d4e; margin: 0;">
        <i class="fas fa-chart-bar" style="color: #0e73b9;"></i> Income vs Expense Comparison
      </h3>
      <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;"><?= htmlspecialchars($filterLabel) ?></span>
    </div>
    <div style="height: 300px;">
      <canvas id="incExpBarChart"></canvas>
    </div>
  </div>

  <!-- Category-Wise Expense Breakdown Chart -->
  <div class="chart-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; color: #032d4e; margin: 0;">
        <i class="fas fa-pie-chart" style="color: #ef4444;"></i> Expense Categories
      </h3>
      <a href="expenses.php" style="font-size: 0.8rem; color: #0e73b9; text-decoration: none; font-weight: 700;">View All</a>
    </div>
    <div style="height: 300px; display: flex; justify-content: center; align-items: center;">
      <canvas id="expCategoryChart"></canvas>
    </div>
  </div>

</div>

<!-- Detailed Filtered Transactions Table -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 2rem;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; color: #032d4e; margin: 0;">
        <i class="fas fa-list-alt" style="color: #10b981;"></i> Filtered Transaction Itemized Details
      </h3>
      <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
        Showing all <strong><?= count($detailedTxns) ?></strong> income and expense transactions for period: <strong><?= htmlspecialchars($filterLabel) ?></strong>
      </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
      <a href="reports.php?period=<?= $period ?>&start_date=<?= $filterStart ?>&end_date=<?= $filterEnd ?>" target="_blank" class="btn" style="background: #032d4e; color: #ffffff; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 0.85rem;">
        <i class="fas fa-print"></i> Print Full Statement
      </a>
    </div>
  </div>

  <div style="overflow-x: auto;">
    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
          <th style="padding: 0.85rem;">Txn ID</th>
          <th style="padding: 0.85rem;">Date</th>
          <th style="padding: 0.85rem;">Type</th>
          <th style="padding: 0.85rem;">Category</th>
          <th style="padding: 0.85rem;">Title / Details</th>
          <th style="padding: 0.85rem;">Amount (৳)</th>
          <th style="padding: 0.85rem;">Method</th>
          <th style="padding: 0.85rem;">Voucher / Memo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detailedTxns)): ?>
          <tr><td colspan="8" style="padding: 2.5rem; text-align: center; color: #94a3b8;">No accounting transactions found for the selected time range.</td></tr>
        <?php else: ?>
          <?php foreach ($detailedTxns as $tx): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.85rem; font-weight: 700; color: #0e73b9;"><code><?= htmlspecialchars($tx['transaction_no']) ?></code></td>
              <td style="padding: 0.85rem; font-weight: 600; color: #475569;"><?= format_date($tx['transaction_date']) ?></td>
              <td style="padding: 0.85rem;">
                <span class="<?= $tx['type'] === 'Income' ? 'badge-income' : 'badge-expense' ?>">
                  <i class="fas <?= $tx['type'] === 'Income' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i> <?= htmlspecialchars($tx['type']) ?>
                </span>
              </td>
              <td style="padding: 0.85rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($tx['category']) ?></td>
              <td style="padding: 0.85rem;">
                <strong><?= htmlspecialchars($tx['title']) ?></strong>
                <?php if ($tx['reference_no']): ?>
                  <br><small style="color: #64748b;">Ref: <?= htmlspecialchars($tx['reference_no']) ?></small>
                <?php endif; ?>
              </td>
              <td style="padding: 0.85rem; font-weight: 800; color: <?= $tx['type'] === 'Income' ? '#16a34a' : '#dc2626' ?>;">
                <?= $tx['type'] === 'Income' ? '+' : '-' ?> <?= format_currency($tx['amount']) ?>
              </td>
              <td style="padding: 0.85rem; font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($tx['payment_method']) ?></td>
              <td style="padding: 0.85rem;">
                <?php if ($tx['voucher_file']): ?>
                  <button onclick="previewVoucher('../uploads/vouchers/<?= htmlspecialchars($tx['voucher_file']) ?>', '<?= htmlspecialchars(addslashes($tx['title'])) ?>')" style="background: #eff6ff; color: #0284c7; border: 1px solid #bfdbfe; padding: 0.35rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fas fa-paperclip"></i> View Voucher
                  </button>
                <?php else: ?>
                  <span style="color: #cbd5e1; font-size: 0.8rem;">No file</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal for Viewing Voucher Document -->
<div id="voucherModal" class="modal-overlay">
  <div class="modal-content">
    <button class="close-modal" onclick="closeVoucherModal()">&times;</button>
    <h3 id="voucherModalTitle" style="font-family: 'Outfit', sans-serif; color: #032d4e; margin-top: 0; margin-bottom: 1rem;">
      Supporting Voucher / Document
    </h3>
    <div id="voucherMediaContainer" style="text-align: center; margin-bottom: 1.5rem;">
      <!-- Image or Link inserted by JS -->
    </div>
    <div style="text-align: right;">
      <a id="voucherDownloadBtn" href="#" download class="btn" style="background: #0e73b9; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <i class="fas fa-download"></i> Download Voucher
      </a>
      <button onclick="closeVoucherModal()" class="btn" style="background: #f1f5f9; color: #334155; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; margin-left: 0.5rem;">
        Close
      </button>
    </div>
  </div>
</div>

<!-- Chart.js Script -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // 1. Monthly Bar Chart
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const incData = new Array(12).fill(0);
    const expData = new Array(12).fill(0);

    <?php foreach ($monthlyData as $row): ?>
      incData[<?= $row['month_num'] - 1 ?>] = <?= $row['inc'] ?>;
      expData[<?= $row['month_num'] - 1 ?>] = <?= $row['exp'] ?>;
    <?php endforeach; ?>

    const ctx1 = document.getElementById('incExpBarChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Income (৳)',
            data: incData,
            backgroundColor: '#10b981',
            borderRadius: 6
          },
          {
            label: 'Expense (৳)',
            data: expData,
            backgroundColor: '#ef4444',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' }
        },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // 2. Expense Category Doughnut Chart
    const expLabels = <?= json_encode(array_column($catExpenses, 'category')) ?>;
    const expTotals = <?= json_encode(array_map('floatval', array_column($catExpenses, 'total'))) ?>;
    const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'];

    const ctx2 = document.getElementById('expCategoryChart').getContext('2d');
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: expLabels.length ? expLabels : ['No Expenses'],
        datasets: [{
          data: expTotals.length ? expTotals : [1],
          backgroundColor: colors
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12 } }
        }
      }
    });
  });

  function previewVoucher(filePath, title) {
    const modal = document.getElementById('voucherModal');
    const container = document.getElementById('voucherMediaContainer');
    const downloadBtn = document.getElementById('voucherDownloadBtn');
    document.getElementById('voucherModalTitle').innerText = 'Voucher Memo: ' + title;
    
    downloadBtn.href = filePath;
    
    const ext = filePath.split('.').pop().toLowerCase();
    if(['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) {
      container.innerHTML = `<img src="${filePath}" alt="Voucher Memo" style="max-width: 100%; max-height: 450px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">`;
    } else {
      container.innerHTML = `<div style="padding: 3rem; background: #f8fafc; border-radius: 12px; color: #475569;">
        <i class="fas fa-file-pdf" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
        <p>Document format: <strong>.${ext.toUpperCase()}</strong></p>
        <a href="${filePath}" target="_blank" class="btn" style="background: #0284c7; color: #ffffff; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700;">
          <i class="fas fa-external-link-alt"></i> Open Document in New Tab
        </a>
      </div>`;
    }
    
    modal.style.display = 'flex';
  }

  function closeVoucherModal() {
    document.getElementById('voucherModal').style.display = 'none';
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
