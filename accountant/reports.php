<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

// Period Filter preset
$period = sanitize($_GET['period'] ?? 'monthly');
$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');

$today = date('Y-m-d');
$firstDayMonth = date('Y-m-01');
$lastDayMonth = date('Y-m-t');
$firstDayYear = date('Y-01-01');
$lastDayYear = date('Y-12-31');

if ($period === 'daily') {
    $filterStart = $today;
    $filterEnd = $today;
    $periodLabel = "Daily Financial Statement (" . format_date($today) . ")";
} elseif ($period === 'yearly') {
    $filterStart = $firstDayYear;
    $filterEnd = $lastDayYear;
    $periodLabel = "Yearly Financial Statement (Year " . date('Y') . ")";
} elseif ($period === 'custom' && !empty($startDate) && !empty($endDate)) {
    $filterStart = $startDate;
    $filterEnd = $endDate;
    $periodLabel = "Custom Range Statement (" . format_date($startDate) . " to " . format_date($endDate) . ")";
} else {
    // Default: Monthly
    $period = 'monthly';
    $filterStart = $firstDayMonth;
    $filterEnd = $lastDayMonth;
    $periodLabel = "Monthly Financial Statement (" . date('F Y') . ")";
}

// 1. Overall Summary
$stmtInc = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Income' AND transaction_date BETWEEN ? AND ?");
$stmtInc->execute([$filterStart, $filterEnd]);
$periodIncome = $stmtInc->fetchColumn() ?: 0;

$stmtExp = $db->prepare("SELECT SUM(amount) FROM accounts_transactions WHERE type='Expense' AND transaction_date BETWEEN ? AND ?");
$stmtExp->execute([$filterStart, $filterEnd]);
$periodExpense = $stmtExp->fetchColumn() ?: 0;

$periodNet = $periodIncome - $periodExpense;

// 2. Category-wise Income Breakdown
$stmtCatInc = $db->prepare("
    SELECT category, COUNT(*) as count, SUM(amount) as total 
    FROM accounts_transactions 
    WHERE type='Income' AND transaction_date BETWEEN ? AND ? 
    GROUP BY category 
    ORDER BY total DESC
");
$stmtCatInc->execute([$filterStart, $filterEnd]);
$incCategories = $stmtCatInc->fetchAll();

// 3. Category-wise Expense Breakdown
$stmtCatExp = $db->prepare("
    SELECT category, COUNT(*) as count, SUM(amount) as total,
           SUM(CASE WHEN voucher_file IS NOT NULL AND voucher_file != '' THEN 1 ELSE 0 END) as voucher_count
    FROM accounts_transactions 
    WHERE type='Expense' AND transaction_date BETWEEN ? AND ? 
    GROUP BY category 
    ORDER BY total DESC
");
$stmtCatExp->execute([$filterStart, $filterEnd]);
$expCategories = $stmtCatExp->fetchAll();

// 4. All Transactions in period
$stmtAll = $db->prepare("
    SELECT * FROM accounts_transactions 
    WHERE transaction_date BETWEEN ? AND ? 
    ORDER BY transaction_date ASC, id ASC
");
$stmtAll->execute([$filterStart, $filterEnd]);
$allTransactions = $stmtAll->fetchAll();
?>

<style>
  @media print {
    body * {
      visibility: hidden;
    }
    .printable-report, .printable-report * {
      visibility: visible;
    }
    .printable-report {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
    }
    .no-print {
      display: none !important;
    }
  }
</style>

<!-- Header & Period Filter -->
<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; color: #032d4e; margin: 0;">
      <i class="fas fa-file-invoice-dollar" style="color: #0e73b9;"></i> School Financial Reports & Cash Ledger
    </h1>
    <p style="color: #64748b; margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Generate daily, monthly, yearly, and category-wise income-expense audit statements.
    </p>
  </div>

  <div>
    <button onclick="window.print()" class="btn" style="background: #032d4e; color: #ffffff; padding: 0.8rem 1.4rem; border-radius: 12px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(3, 45, 78, 0.35);">
      <i class="fas fa-print"></i> Print Cash Ledger
    </button>
  </div>
</div>

<!-- Time Range Filter Pills -->
<div class="no-print" style="background: #ffffff; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 1.8rem;">
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
    
    <div>
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 0.3rem;">Select Period Preset</label>
      <div style="display: flex; gap: 0.4rem;">
        <a href="reports.php?period=daily" class="btn" style="background: <?= $period === 'daily' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'daily' ? '#ffffff' : '#334155' ?>; padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          Daily (Today)
        </a>
        <a href="reports.php?period=monthly" class="btn" style="background: <?= $period === 'monthly' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'monthly' ? '#ffffff' : '#334155' ?>; padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          Monthly (This Month)
        </a>
        <a href="reports.php?period=yearly" class="btn" style="background: <?= $period === 'yearly' ? '#0e73b9' : '#f1f5f9' ?>; color: <?= $period === 'yearly' ? '#ffffff' : '#334155' ?>; padding: 0.6rem 1.1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
          Yearly (<?= date('Y') ?>)
        </a>
      </div>
    </div>

    <div style="border-left: 1px solid #e2e8f0; padding-left: 1rem; display: flex; gap: 0.75rem; align-items: flex-end;">
      <input type="hidden" name="period" value="custom">
      <div>
        <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStart) ?>" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
      </div>
      <div>
        <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEnd) ?>" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
      </div>
      <button type="submit" class="btn" style="background: #10b981; color: #ffffff; padding: 0.65rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
        Custom Filter
      </button>
    </div>

  </form>
</div>

<!-- Printable Financial Statement -->
<div class="printable-report">
  
  <!-- Printable Header -->
  <div style="text-align: center; border-bottom: 2px solid #032d4e; padding-bottom: 1rem; margin-bottom: 1.5rem;">
    <img src="../assets/images/logo.png?v=2" alt="School Logo" style="height: 65px; margin: 0 auto 0.5rem auto;">
    <h2 style="font-family: 'Outfit', sans-serif; color: #032d4e; margin: 0; font-size: 1.6rem;">AL FATIHA INSTITUTE</h2>
    <p style="margin: 0.2rem 0 0 0; color: #64748b; font-size: 0.85rem;">Cambridge Curriculum & Islamic Ethos Kindergarten • Cox's Bazar</p>
    <h3 style="font-family: 'Outfit', sans-serif; color: #0e73b9; margin: 0.8rem 0 0 0; font-size: 1.3rem; text-transform: uppercase;">
      <?= htmlspecialchars($periodLabel) ?>
    </h3>
  </div>

  <!-- Summary KPI Cards -->
  <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
    
    <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 14px; padding: 1.25rem; text-align: center;">
      <span style="font-size: 0.85rem; color: #166534; font-weight: 700; text-transform: uppercase;">Total Period Revenue</span>
      <h2 style="font-family: 'Outfit', sans-serif; color: #15803d; font-size: 1.8rem; margin: 0.3rem 0 0 0;">
        <?= format_currency($periodIncome) ?>
      </h2>
    </div>

    <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 14px; padding: 1.25rem; text-align: center;">
      <span style="font-size: 0.85rem; color: #991b1b; font-weight: 700; text-transform: uppercase;">Total Period Expenses</span>
      <h2 style="font-family: 'Outfit', sans-serif; color: #b91c1c; font-size: 1.8rem; margin: 0.3rem 0 0 0;">
        <?= format_currency($periodExpense) ?>
      </h2>
    </div>

    <div style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 14px; padding: 1.25rem; text-align: center;">
      <span style="font-size: 0.85rem; color: #075985; font-weight: 700; text-transform: uppercase;">Net Cash Surplus / Balance</span>
      <h2 style="font-family: 'Outfit', sans-serif; color: <?= $periodNet >= 0 ? '#0284c7' : '#dc2626' ?>; font-size: 1.8rem; margin: 0.3rem 0 0 0;">
        <?= format_currency($periodNet) ?>
      </h2>
    </div>

  </div>

  <!-- Category Breakdown Side-by-Side Tables -->
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    
    <!-- Category-Wise Income Breakdown -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
      <h3 style="font-family: 'Outfit', sans-serif; color: #15803d; font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; border-bottom: 2px solid #bbf7d0; padding-bottom: 0.5rem;">
        <i class="fas fa-list-ul"></i> Income Stream Breakdown
      </h3>
      <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; color: #475569;">
            <th style="padding: 0.6rem; text-align: left;">Category</th>
            <th style="padding: 0.6rem; text-align: center;">Txns</th>
            <th style="padding: 0.6rem; text-align: right;">Total Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($incCategories)): ?>
            <tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">No income recorded in this period.</td></tr>
          <?php else: ?>
            <?php foreach($incCategories as $ci): ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 0.6rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($ci['category']) ?></td>
                <td style="padding: 0.6rem; text-align: center; font-weight: 700; color: #64748b;"><?= $ci['count'] ?></td>
                <td style="padding: 0.6rem; text-align: right; font-weight: 800; color: #16a34a;"><?= format_currency($ci['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Category-Wise Expense Breakdown -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
      <h3 style="font-family: 'Outfit', sans-serif; color: #b91c1c; font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem; border-bottom: 2px solid #fecaca; padding-bottom: 0.5rem;">
        <i class="fas fa-list-ul"></i> Expense Categories Breakdown
      </h3>
      <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
        <thead>
          <tr style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; color: #475569;">
            <th style="padding: 0.6rem; text-align: left;">Category</th>
            <th style="padding: 0.6rem; text-align: center;">Txns / Vouchers</th>
            <th style="padding: 0.6rem; text-align: right;">Total Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($expCategories)): ?>
            <tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">No expenses recorded in this period.</td></tr>
          <?php else: ?>
            <?php foreach($expCategories as $ce): ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 0.6rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($ce['category']) ?></td>
                <td style="padding: 0.6rem; text-align: center; font-weight: 700; color: #64748b;">
                  <?= $ce['count'] ?> <small style="color: #0284c7;">(<?= $ce['voucher_count'] ?> vouchers)</small>
                </td>
                <td style="padding: 0.6rem; text-align: right; font-weight: 800; color: #dc2626;"><?= format_currency($ce['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- Detailed Cash Ledger Statement Table -->
  <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <h3 style="font-family: 'Outfit', sans-serif; color: #032d4e; font-size: 1.2rem; margin-top: 0; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
      <i class="fas fa-book-open"></i> Detailed Itemized Cash Ledger Statement
    </h3>

    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1; color: #475569;">
          <th style="padding: 0.75rem;">Txn ID</th>
          <th style="padding: 0.75rem;">Date</th>
          <th style="padding: 0.75rem;">Type</th>
          <th style="padding: 0.75rem;">Category & Particulars</th>
          <th style="padding: 0.75rem;">Method / Ref</th>
          <th style="padding: 0.75rem; text-align: right;">Debit / Expense (৳)</th>
          <th style="padding: 0.75rem; text-align: right;">Credit / Income (৳)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allTransactions)): ?>
          <tr><td colspan="7" style="padding: 2rem; text-align: center; color: #94a3b8;">No transactions found for the selected date range.</td></tr>
        <?php else: ?>
          <?php foreach ($allTransactions as $t): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.65rem; font-weight: 700; color: #0e73b9;"><code><?= htmlspecialchars($t['transaction_no']) ?></code></td>
              <td style="padding: 0.65rem; font-weight: 600; color: #475569;"><?= format_date($t['transaction_date']) ?></td>
              <td style="padding: 0.65rem;">
                <span class="<?= $t['type'] === 'Income' ? 'badge-income' : 'badge-expense' ?>">
                  <?= htmlspecialchars($t['type']) ?>
                </span>
              </td>
              <td style="padding: 0.65rem;">
                <strong><?= htmlspecialchars($t['title']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($t['category']) ?></small>
              </td>
              <td style="padding: 0.65rem; font-size: 0.8rem; color: #64748b;">
                <?= htmlspecialchars($t['payment_method']) ?>
                <?php if ($t['reference_no']): ?>
                  <br><code>Ref: <?= htmlspecialchars($t['reference_no']) ?></code>
                <?php endif; ?>
              </td>
              <td style="padding: 0.65rem; text-align: right; font-weight: 800; color: #dc2626;">
                <?= $t['type'] === 'Expense' ? format_currency($t['amount']) : '-' ?>
              </td>
              <td style="padding: 0.65rem; text-align: right; font-weight: 800; color: #16a34a;">
                <?= $t['type'] === 'Income' ? format_currency($t['amount']) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr style="background: #f8fafc; font-weight: 800; font-size: 0.95rem; border-top: 2px solid #cbd5e1;">
          <td colspan="5" style="padding: 0.85rem; text-align: right; color: #032d4e;">TOTAL PERIOD SUMMARY:</td>
          <td style="padding: 0.85rem; text-align: right; color: #dc2626;"><?= format_currency($periodExpense) ?></td>
          <td style="padding: 0.85rem; text-align: right; color: #16a34a;"><?= format_currency($periodIncome) ?></td>
        </tr>
      </tfoot>
    </table>

    <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 2rem;">
      <div style="text-align: center;">
        <div style="border-bottom: 1px solid #334155; width: 180px; margin-bottom: 0.3rem;"></div>
        <span style="font-size: 0.8rem; font-weight: 700; color: #475569;">Prepared by (Accountant)</span>
      </div>
      <div style="text-align: center;">
        <div style="border-bottom: 1px solid #334155; width: 180px; margin-bottom: 0.3rem;"></div>
        <span style="font-size: 0.8rem; font-weight: 700; color: #475569;">Approved by (Principal)</span>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
