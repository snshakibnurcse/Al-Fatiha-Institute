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

if ($filterStart && $filterEnd) {
    $stmtInc = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Income' AND transaction_date BETWEEN ? AND ?");
    $stmtInc->execute([$filterStart, $filterEnd]);
    $totalIncome = $stmtInc->fetchColumn() ?: 0;

    $stmtExp = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Expense' AND transaction_date BETWEEN ? AND ?");
    $stmtExp->execute([$filterStart, $filterEnd]);
    $totalExpense = $stmtExp->fetchColumn() ?: 0;

    $stmtVouch = $db->prepare("SELECT COUNT(*) FROM accounts_transactions WHERE voucher_file IS NOT NULL AND voucher_file != '' AND transaction_date BETWEEN ? AND ?");
    $stmtVouch->execute([$filterStart, $filterEnd]);
    $totalVouchers = $stmtVouch->fetchColumn() ?: 0;

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

    $stmtCatExp = $db->prepare("
        SELECT category, SUM(amount) as total 
        FROM accounts_transactions 
        WHERE type='Expense' AND transaction_date BETWEEN ? AND ?
        GROUP BY category 
        ORDER BY total DESC
    ");
    $stmtCatExp->execute([$filterStart, $filterEnd]);
    $catExpenses = $stmtCatExp->fetchAll();

    $stmtTxns = $db->prepare("SELECT * FROM accounts_transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC, id DESC");
    $stmtTxns->execute([$filterStart, $filterEnd]);
    $detailedTxns = $stmtTxns->fetchAll();
} else {
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

<!-- Accounts Header Card -->
<div class="dash-welcome-card">
  <div>
    <div class="welcome-meta-row">
      <span class="date-badge"><?= date('D, d M Y') ?></span>
      <span class="role-pill-tag">Financial Ledger</span>
    </div>
    <h1 class="welcome-title">School Accounts &amp; Cash Summary</h1>
    <p class="welcome-sub">
      Revenues, voucher expenses, net cash balance, and fee invoicing.
    </p>
  </div>
  <div class="welcome-actions">
    <a href="fees.php?action=issue_invoice" class="btn-welcome-primary">
      <i class="fas fa-plus"></i> Issue Invoice
    </a>
    <a href="fees.php" class="btn-welcome-secondary">
      Fees &amp; Due Tracker
    </a>
    <a href="expenses.php?action=add" class="btn-welcome-secondary" style="color: #dc2626; border-color: rgba(220,38,38,0.25);">
      Add Expense
    </a>
    <a href="income.php?action=add" class="btn-welcome-secondary" style="color: #15803d; border-color: rgba(22,163,74,0.25);">
      Add Income
    </a>
  </div>
</div>

<!-- Time & Date Range Filter Card -->
<div class="card-box">
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; justify-content: space-between;">
    <div>
      <label style="font-size: 12.5px; font-weight: 500; color: var(--admin-text-secondary); display: block; margin-bottom: 8px;">
        Time Period
      </label>
      <div style="display: flex; flex-wrap: wrap; gap: 6px;">
        <a href="dashboard.php?period=all" class="btn" style="background: <?= $period === 'all' ? 'var(--admin-navy)' : 'var(--admin-raised)' ?>; color: <?= $period === 'all' ? '#ffffff' : 'var(--admin-text-secondary)' ?>; height: 34px; padding: 0 12px; display: inline-flex; align-items: center; font-size: 13px; text-decoration: none;">
          All Time
        </a>
        <a href="dashboard.php?period=today" class="btn" style="background: <?= $period === 'today' ? 'var(--admin-navy)' : 'var(--admin-raised)' ?>; color: <?= $period === 'today' ? '#ffffff' : 'var(--admin-text-secondary)' ?>; height: 34px; padding: 0 12px; display: inline-flex; align-items: center; font-size: 13px; text-decoration: none;">
          Today
        </a>
        <a href="dashboard.php?period=this_month" class="btn" style="background: <?= $period === 'this_month' ? 'var(--admin-navy)' : 'var(--admin-raised)' ?>; color: <?= $period === 'this_month' ? '#ffffff' : 'var(--admin-text-secondary)' ?>; height: 34px; padding: 0 12px; display: inline-flex; align-items: center; font-size: 13px; text-decoration: none;">
          This Month
        </a>
        <a href="dashboard.php?period=this_quarter" class="btn" style="background: <?= $period === 'this_quarter' ? 'var(--admin-navy)' : 'var(--admin-raised)' ?>; color: <?= $period === 'this_quarter' ? '#ffffff' : 'var(--admin-text-secondary)' ?>; height: 34px; padding: 0 12px; display: inline-flex; align-items: center; font-size: 13px; text-decoration: none;">
          This Quarter
        </a>
        <a href="dashboard.php?period=this_year" class="btn" style="background: <?= $period === 'this_year' ? 'var(--admin-navy)' : 'var(--admin-raised)' ?>; color: <?= $period === 'this_year' ? '#ffffff' : 'var(--admin-text-secondary)' ?>; height: 34px; padding: 0 12px; display: inline-flex; align-items: center; font-size: 13px; text-decoration: none;">
          <?= date('Y') ?>
        </a>
      </div>
    </div>

    <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
      <input type="hidden" name="period" value="custom">
      <div>
        <label style="font-size: 12px; font-weight: 500; color: var(--admin-text-secondary); display: block; margin-bottom: 4px;">From</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStart ?: date('Y-m-01')) ?>" style="height: 36px; padding: 0 10px;">
      </div>
      <div>
        <label style="font-size: 12px; font-weight: 500; color: var(--admin-text-secondary); display: block; margin-bottom: 4px;">To</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEnd ?: date('Y-m-d')) ?>" style="height: 36px; padding: 0 10px;">
      </div>
      <button type="submit" class="btn-welcome-primary" style="height: 36px; cursor: pointer;">
        Filter
      </button>
      <?php if ($period !== 'all'): ?>
        <a href="dashboard.php" class="btn-welcome-secondary" style="height: 36px;">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($period !== 'all'): ?>
    <div style="margin-top: 14px; padding: 10px 14px; background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 8px; font-size: 13px; color: var(--admin-text-secondary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
      <div>
        Active filter: <strong style="color: var(--admin-navy);"><?= htmlspecialchars($filterLabel) ?></strong>
        (<?= count($detailedTxns) ?> records)
      </div>
      <a href="reports.php?period=<?= $period ?>&start_date=<?= $filterStart ?>&end_date=<?= $filterEnd ?>" target="_blank" style="color: var(--admin-primary); font-weight: 500; text-decoration: none;">
        Print Full Statement &rarr;
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- KPI Cards Grid -->
<div class="stats-grid">
  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= format_currency($totalIncome) ?></h3>
      <p>Total income</p>
    </div>
    <div class="kpi-icon green"><i class="fas fa-arrow-down"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= format_currency($totalExpense) ?></h3>
      <p>Total expenses</p>
    </div>
    <div class="kpi-icon" style="background: #fef2f2; color: #dc2626;"><i class="fas fa-arrow-up"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3 style="color: <?= $netBalance >= 0 ? 'var(--admin-navy)' : '#dc2626' ?>;"><?= format_currency($netBalance) ?></h3>
      <p>Net cash balance</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= number_format($totalVouchers) ?></h3>
      <p>Attached vouchers</p>
    </div>
    <div class="kpi-icon gold"><i class="fas fa-receipt"></i></div>
  </div>
</div>

<!-- Financial Charts Section -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
  <div class="chart-card" style="margin-bottom: 0;">
    <div class="card-header" style="margin-bottom: 16px;">
      <h3 class="card-title">Income vs Expense Comparison</h3>
      <span style="font-family: 'IBM Plex Mono', monospace; font-size: 12px; color: var(--admin-text-muted);"><?= htmlspecialchars($filterLabel) ?></span>
    </div>
    <div style="height: 290px;">
      <canvas id="incExpBarChart"></canvas>
    </div>
  </div>

  <div class="chart-card" style="margin-bottom: 0;">
    <div class="card-header" style="margin-bottom: 16px;">
      <h3 class="card-title">Expense Categories</h3>
      <a href="expenses.php" style="font-size: 13px; color: var(--admin-primary); text-decoration: none; font-weight: 500;">View all &rarr;</a>
    </div>
    <div style="height: 290px; display: flex; justify-content: center; align-items: center;">
      <canvas id="expCategoryChart"></canvas>
    </div>
  </div>
</div>

<!-- Detailed Filtered Transactions Table -->
<div class="card-box">
  <div class="card-header">
    <div>
      <h3 class="card-title">Itemized Transactions</h3>
      <p style="font-size: 13px; color: var(--admin-text-muted); margin: 4px 0 0 0;">
        Showing <?= count($detailedTxns) ?> records for <?= htmlspecialchars($filterLabel) ?>.
      </p>
    </div>
    <a href="reports.php?period=<?= $period ?>&start_date=<?= $filterStart ?>&end_date=<?= $filterEnd ?>" target="_blank" class="btn-welcome-secondary" style="height: 34px; padding: 0 12px; font-size: 13px;">
      <i class="fas fa-print"></i> Print Statement
    </a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Txn ID</th>
          <th>Date</th>
          <th>Type</th>
          <th>Category</th>
          <th>Title / Details</th>
          <th>Amount (৳)</th>
          <th>Method</th>
          <th>Voucher</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detailedTxns)): ?>
          <tr><td colspan="8" style="padding: 36px; text-align: center; color: var(--admin-text-muted);">No accounting transactions found for the selected time range.</td></tr>
        <?php else: ?>
          <?php foreach ($detailedTxns as $tx): ?>
            <tr>
              <td><code style="font-family: 'IBM Plex Mono', monospace; font-size: 12px; color: var(--admin-navy);"><?= htmlspecialchars($tx['transaction_no']) ?></code></td>
              <td style="font-family: 'IBM Plex Mono', monospace; font-size: 12.5px; color: var(--admin-text-secondary);"><?= format_date($tx['transaction_date']) ?></td>
              <td>
                <span class="<?= $tx['type'] === 'Income' ? 'badge-income' : 'badge-expense' ?>">
                  <?= htmlspecialchars($tx['type']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($tx['category']) ?></td>
              <td>
                <strong><?= htmlspecialchars($tx['title']) ?></strong>
                <?php if ($tx['reference_no']): ?>
                  <br><small style="color: var(--admin-text-muted); font-family: 'IBM Plex Mono', monospace;">Ref: <?= htmlspecialchars($tx['reference_no']) ?></small>
                <?php endif; ?>
              </td>
              <td style="font-family: 'IBM Plex Mono', monospace; font-weight: 600; font-variant-numeric: tabular-nums; color: <?= $tx['type'] === 'Income' ? '#15803d' : '#dc2626' ?>;">
                <?= $tx['type'] === 'Income' ? '+' : '-' ?> <?= format_currency($tx['amount']) ?>
              </td>
              <td style="color: var(--admin-text-secondary); font-size: 13px;"><?= htmlspecialchars($tx['payment_method']) ?></td>
              <td>
                <?php if ($tx['voucher_file']): ?>
                  <button onclick="previewVoucher('../uploads/vouchers/<?= htmlspecialchars($tx['voucher_file']) ?>', '<?= htmlspecialchars(addslashes($tx['title'])) ?>')" class="btn-welcome-secondary" style="height: 28px; padding: 0 10px; font-size: 12px; cursor: pointer;">
                    View
                  </button>
                <?php else: ?>
                  <span style="color: var(--admin-text-muted); font-size: 12px;">—</span>
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
    <h3 id="voucherModalTitle" style="color: var(--admin-navy); margin-top: 0; margin-bottom: 16px; font-size: 18px; font-weight: 600;">
      Supporting Voucher
    </h3>
    <div id="voucherMediaContainer" style="text-align: center; margin-bottom: 20px;"></div>
    <div style="display: flex; justify-content: flex-end; gap: 8px;">
      <a id="voucherDownloadBtn" href="#" download class="btn-welcome-primary" style="height: 38px;">
        <i class="fas fa-download"></i> Download
      </a>
      <button onclick="closeVoucherModal()" class="btn-welcome-secondary" style="height: 38px; cursor: pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
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
            backgroundColor: '#082447',
            borderRadius: 4
          },
          {
            label: 'Expense (৳)',
            data: expData,
            backgroundColor: '#d4d4d8',
            borderRadius: 4
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
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(23,23,23,0.06)' } }
        }
      }
    });

    const expLabels = <?= json_encode(array_column($catExpenses, 'category')) ?>;
    const expTotals = <?= json_encode(array_map('floatval', array_column($catExpenses, 'total'))) ?>;
    const colors = ['#082447', '#0e73b9', '#e5a922', '#16a34a', '#737373', '#a3a3a3', '#d4d4d8'];

    const ctx2 = document.getElementById('expCategoryChart').getContext('2d');
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: expLabels.length ? expLabels : ['No Expenses'],
        datasets: [{
          data: expTotals.length ? expTotals : [1],
          backgroundColor: colors,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10 } }
        }
      }
    });
  });

  function previewVoucher(filePath, title) {
    const modal = document.getElementById('voucherModal');
    const container = document.getElementById('voucherMediaContainer');
    const downloadBtn = document.getElementById('voucherDownloadBtn');
    document.getElementById('voucherModalTitle').innerText = 'Voucher: ' + title;
    downloadBtn.href = filePath;

    const ext = filePath.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) {
      container.innerHTML = `<img src="${filePath}" alt="Voucher Memo" style="max-width: 100%; max-height: 440px; border-radius: 10px; border: 1px solid rgba(23,23,23,0.1);">`;
    } else {
      container.innerHTML = `<div style="padding: 32px; background: var(--admin-bg); border-radius: 10px; color: var(--admin-text-secondary);">
        <p style="margin-bottom: 12px;">Document format: <strong>.${ext.toUpperCase()}</strong></p>
        <a href="${filePath}" target="_blank" class="btn-welcome-primary">Open Document</a>
      </div>`;
    }
    modal.style.display = 'flex';
  }

  function closeVoucherModal() {
    document.getElementById('voucherModal').style.display = 'none';
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
