<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$typeFilter = sanitize($_GET['type'] ?? 'all');
$categoryFilter = sanitize($_GET['category'] ?? '');
$startDate = sanitize($_GET['start_date'] ?? date('Y-m-01'));
$endDate = sanitize($_GET['end_date'] ?? date('Y-m-t'));
$q = sanitize($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];
if (in_array($typeFilter, ['Income', 'Expense'], true)) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}
if ($categoryFilter !== '') {
    $where[] = "category = ?";
    $params[] = $categoryFilter;
}
if ($startDate !== '') {
    $where[] = "transaction_date >= ?";
    $params[] = $startDate;
}
if ($endDate !== '') {
    $where[] = "transaction_date <= ?";
    $params[] = $endDate;
}
if ($q !== '') {
    $where[] = "(title LIKE ? OR transaction_no LIKE ? OR reference_no LIKE ? OR description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$txStmt = $db->prepare("SELECT * FROM accounts_transactions WHERE " . implode(' AND ', $where) . " ORDER BY transaction_date DESC, id DESC");
$txStmt->execute($params);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$filteredIncome = 0;
$filteredExpense = 0;
$categoryTotals = [];
foreach ($transactions as $t) {
    $amt = floatval($t['amount']);
    if ($t['type'] === 'Income') $filteredIncome += $amt; else $filteredExpense += $amt;
    $key = $t['type'] . '::' . $t['category'];
    if (!isset($categoryTotals[$key])) {
        $categoryTotals[$key] = ['type' => $t['type'], 'category' => $t['category'], 'total' => 0, 'cnt' => 0];
    }
    $categoryTotals[$key]['total'] += $amt;
    $categoryTotals[$key]['cnt']++;
}
$filteredNet = $filteredIncome - $filteredExpense;

$allCategories = $db->query("SELECT DISTINCT category FROM accounts_transactions ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$totalDues = floatval($db->query("SELECT COALESCE(SUM(GREATEST(0, amount - COALESCE(paid_amount, 0))), 0) FROM fees WHERE status IN ('Unpaid', 'Partial')")->fetchColumn());
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Income &amp; Expense Financial Monitor</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">View institutional cash flow, student fee revenue, staff salary disbursements, and expense vouchers.</p>
  </div>
</div>

<!-- Summary KPIs -->
<div class="kpi-exec-grid" style="margin-bottom: 1.25rem;">
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #16a34a; text-transform: uppercase;">Filtered Income</small>
    <h2 style="margin: 4px 0 0 0; color: #15803d;"><?= format_currency($filteredIncome) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #dc2626; text-transform: uppercase;">Filtered Expense</small>
    <h2 style="margin: 4px 0 0 0; color: #dc2626;"><?= format_currency($filteredExpense) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #032d4e; text-transform: uppercase;">Filtered Net Balance</small>
    <h2 style="margin: 4px 0 0 0; color: <?= $filteredNet >= 0 ? '#032d4e' : '#dc2626' ?>;"><?= format_currency($filteredNet) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #b45309; text-transform: uppercase;">Unpaid Student Dues</small>
    <h2 style="margin: 4px 0 0 0; color: #b45309;"><?= format_currency($totalDues) ?></h2>
  </div>
</div>

<!-- Filters -->
<div class="card-box" style="margin-bottom: 1.25rem;">
  <form method="GET" style="display: grid; grid-template-columns: 1.5fr 1fr 1.2fr 1fr 1fr auto; gap: 10px; align-items: flex-end;">
    <div>
      <label style="font-size: 0.8rem; font-weight: 700;">Keyword</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control">
    </div>
    <div>
      <label style="font-size: 0.8rem; font-weight: 700;">Type</label>
      <select name="type" class="form-control">
        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All</option>
        <option value="Income" <?= $typeFilter === 'Income' ? 'selected' : '' ?>>Income</option>
        <option value="Expense" <?= $typeFilter === 'Expense' ? 'selected' : '' ?>>Expense</option>
      </select>
    </div>
    <div>
      <label style="font-size: 0.8rem; font-weight: 700;">Category</label>
      <select name="category" class="form-control">
        <option value="">All Categories</option>
        <?php foreach ($allCategories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size: 0.8rem; font-weight: 700;">From</label>
      <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
    </div>
    <div>
      <label style="font-size: 0.8rem; font-weight: 700;">To</label>
      <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
    </div>
    <div style="display: flex; gap: 6px;">
      <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1rem; border-radius: 8px; border: none;">Filter</button>
      <a href="finance.php?start_date=&end_date=" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; border-radius: 8px; text-decoration: none;">All Time</a>
    </div>
  </form>
</div>

<!-- Category Breakdown -->
<?php if (!empty($categoryTotals)): ?>
<div class="card-box" style="margin-bottom: 1.25rem;">
  <div class="card-header"><h3 class="card-title"><i class="fas fa-layer-group"></i> Category-Wise Summary</h3></div>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
    <?php foreach ($categoryTotals as $ct): ?>
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px;">
        <span class="badge <?= $ct['type'] === 'Income' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($ct['type']) ?></span>
        <div style="font-weight: 700; font-size: 0.86rem; margin-top: 4px;"><?= htmlspecialchars($ct['category']) ?></div>
        <strong style="font-size: 1.05rem; color: <?= $ct['type'] === 'Income' ? '#15803d' : '#dc2626' ?>;"><?= format_currency($ct['total']) ?></strong>
        <small style="color: #64748b; display: block;"><?= $ct['cnt'] ?> record(s)</small>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Transactions Ledger -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-book"></i> Financial Ledger (<?= count($transactions) ?> records)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Txn ID</th>
          <th>Date</th>
          <th>Type</th>
          <th>Category</th>
          <th>Title &amp; Notes</th>
          <th>Amount (৳)</th>
          <th>Method</th>
          <th>Voucher</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($transactions)): ?>
          <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><code><?= htmlspecialchars($tx['transaction_no']) ?></code></td>
              <td><?= format_date($tx['transaction_date']) ?></td>
              <td><span class="badge <?= $tx['type'] === 'Income' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($tx['type']) ?></span></td>
              <td><?= htmlspecialchars($tx['category']) ?></td>
              <td>
                <strong><?= htmlspecialchars($tx['title']) ?></strong>
                <?php if (!empty($tx['reference_no'])): ?><br><small style="color: #64748b;">Ref: <?= htmlspecialchars($tx['reference_no']) ?></small><?php endif; ?>
                <?php if (!empty($tx['description'])): ?><br><small style="color: #94a3b8;"><?= htmlspecialchars($tx['description']) ?></small><?php endif; ?>
              </td>
              <td style="font-weight: 800; color: <?= $tx['type'] === 'Income' ? '#15803d' : '#dc2626' ?>;">
                <?= ($tx['type'] === 'Income' ? '+ ' : '- ') . format_currency($tx['amount']) ?>
              </td>
              <td><?= htmlspecialchars($tx['payment_method']) ?></td>
              <td>
                <?php if (!empty($tx['voucher_file'])): ?>
                  <a href="../uploads/vouchers/<?= htmlspecialchars($tx['voucher_file']) ?>" target="_blank" style="color: #0e73b9; font-weight: 700; text-decoration: none;">View File</a>
                <?php else: ?>
                  <span style="color: #cbd5e1;">&mdash;</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #94a3b8;">No ledger entries found for selected period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
