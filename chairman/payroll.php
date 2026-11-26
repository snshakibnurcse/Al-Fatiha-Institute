<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$selectedMonth = sanitize($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');

$slipsStmt = $db->prepare("SELECT * FROM payroll_slips WHERE payroll_month = ? ORDER BY employee_code_snapshot ASC");
$slipsStmt->execute([$selectedMonth]);
$slips = $slipsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalGross = 0;
$totalDed = 0;
$totalBonus = 0;
$totalNet = 0;
$paidCount = 0;
foreach ($slips as $s) {
    $totalGross += floatval($s['gross_salary']);
    $totalDed += floatval($s['unpaid_deduction']) + floatval($s['late_deduction']) + floatval($s['other_deduction']);
    $totalBonus += floatval($s['bonus_amount']);
    $totalNet += floatval($s['net_payable']);
    if ($s['payment_status'] === 'Paid') $paidCount++;
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Payroll &amp; Salary Monitor</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">View monthly payroll sheets, deductions, bonuses, and payment status across all employees.</p>
  </div>
  <form method="GET">
    <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-control" onchange="this.form.submit()">
  </form>
</div>

<div class="kpi-exec-grid" style="margin-bottom: 1.25rem;">
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Gross</small>
    <h2 style="margin: 4px 0 0 0;"><?= format_currency($totalGross) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #dc2626; text-transform: uppercase;">Total Deductions</small>
    <h2 style="margin: 4px 0 0 0; color: #dc2626;">- <?= format_currency($totalDed) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #059669; text-transform: uppercase;">Bonuses</small>
    <h2 style="margin: 4px 0 0 0; color: #059669;">+ <?= format_currency($totalBonus) ?></h2>
  </div>
  <div class="kpi-exec-card">
    <small style="font-size: 0.75rem; font-weight: 700; color: #032d4e; text-transform: uppercase;">Net Payable (<?= $paidCount ?>/<?= count($slips) ?> Paid)</small>
    <h2 style="margin: 4px 0 0 0; color: #032d4e;"><?= format_currency($totalNet) ?></h2>
  </div>
</div>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Monthly Salary Register &mdash; <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff ID</th>
          <th>Employee &amp; Designation</th>
          <th>Gross Salary</th>
          <th>Unpaid Ded.</th>
          <th>Late Ded.</th>
          <th>Bonus / Other</th>
          <th>Net Payable</th>
          <th>Payment Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($slips)): ?>
          <?php foreach ($slips as $s): ?>
            <tr>
              <td><code><?= htmlspecialchars($s['employee_code_snapshot']) ?></code></td>
              <td>
                <strong><?= htmlspecialchars($s['employee_name_snapshot']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($s['designation_snapshot']) ?></small>
              </td>
              <td><?= format_currency($s['gross_salary']) ?></td>
              <td style="color: #dc2626;"><?= format_currency($s['unpaid_deduction']) ?></td>
              <td style="color: #b45309;"><?= format_currency($s['late_deduction']) ?></td>
              <td>
                <small style="color: #059669;">+ <?= format_currency($s['bonus_amount']) ?></small>
                <br><small style="color: #dc2626;">- <?= format_currency($s['other_deduction']) ?></small>
              </td>
              <td><strong><?= format_currency($s['net_payable']) ?></strong></td>
              <td>
                <span class="badge <?= $s['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($s['payment_status']) ?></span>
                <?php if ($s['payment_status'] === 'Paid' && !empty($s['paid_date'])): ?>
                  <br><small style="color: #64748b;"><?= format_date($s['paid_date']) ?></small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #94a3b8;">No payroll slips generated for <?= date('F Y', strtotime($selectedMonth . '-01')) ?> yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
