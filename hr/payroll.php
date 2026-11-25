<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';
$selectedMonth = sanitize($_REQUEST['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

// 1. Generate / Refresh Monthly Payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_generate_payroll'])) {
    $wDays = max(1, intval($_POST['working_days'] ?? 26));
    $lThresh = max(1, intval($_POST['late_threshold'] ?? 3));
    $res = hr_generate_monthly_payroll($selectedMonth, $wDays, $lThresh, get_current_user_id());
    if ($res['success']) $msg = $res['message']; else $err = $res['message'];
}

// 2. Manual HR Adjustment on Late Deduction / Bonus / Other Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_adjust_slip'])) {
    $slipId = intval($_POST['slip_id'] ?? 0);
    $appliedLateDays = intval($_POST['applied_late_deduct_days'] ?? 0);
    $lateDed = floatval($_POST['late_deduction'] ?? 0);
    $bonus = floatval($_POST['bonus_amount'] ?? 0);
    $otherDed = floatval($_POST['other_deduction'] ?? 0);
    $note = sanitize($_POST['adjustment_note'] ?? '');

    $res = hr_update_payroll_slip_adjustments($slipId, $appliedLateDays, $lateDed, $bonus, $otherDed, $note);
    if ($res['success']) $msg = $res['message']; else $err = $res['message'];
}

// 3. Pay Individual Slip & Sync to Accounts Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pay_slip'])) {
    $slipId = intval($_POST['slip_id'] ?? 0);
    $pMethod = sanitize($_POST['payment_method'] ?? 'Cash');
    $pDate = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $res = hr_mark_slip_paid($slipId, $pMethod, $pDate, get_current_user_id());
    if ($res['success']) $msg = $res['message']; else $err = $res['message'];
}

// 4. Bulk Pay All Remaining Unpaid Slips in Month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pay_all'])) {
    $pDate = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $unpaidStmt = $db->prepare("SELECT id, payment_method FROM payroll_slips WHERE payroll_month = ? AND payment_status = 'Unpaid'");
    $unpaidStmt->execute([$selectedMonth]);
    $paidCount = 0;
    foreach ($unpaidStmt->fetchAll(PDO::FETCH_ASSOC) as $uSlip) {
        $r = hr_mark_slip_paid($uSlip['id'], $uSlip['payment_method'], $pDate, get_current_user_id());
        if ($r['success']) $paidCount++;
    }
    $msg = "Marked <strong>$paidCount</strong> salary slip(s) as Paid and synced to Expense Ledger!";
}

$runStmt = $db->prepare("SELECT * FROM payroll_runs WHERE payroll_month = ? LIMIT 1");
$runStmt->execute([$selectedMonth]);
$run = $runStmt->fetch(PDO::FETCH_ASSOC);

$slipsStmt = $db->prepare("SELECT * FROM payroll_slips WHERE payroll_month = ? ORDER BY employee_code_snapshot ASC");
$slipsStmt->execute([$selectedMonth]);
$slips = $slipsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalGross = 0;
$totalDed = 0;
$totalBonus = 0;
$totalNet = 0;
$unpaidCnt = 0;
foreach ($slips as $s) {
    $totalGross += floatval($s['gross_salary']);
    $totalDed += floatval($s['unpaid_deduction']) + floatval($s['late_deduction']) + floatval($s['other_deduction']);
    $totalBonus += floatval($s['bonus_amount']);
    $totalNet += floatval($s['net_payable']);
    if ($s['payment_status'] !== 'Paid') $unpaidCnt++;
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Salary Calculation &amp; Payroll</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Unpaid absences &amp; 3-late penalties are computed automatically. HR can manually adjust or waive late deductions anytime before payment.</p>
  </div>
</div>

<!-- Month & Payroll Generator Card -->
<div class="card-box" style="margin-bottom: 1.5rem;">
  <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
    <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
      <div>
        <label style="font-size: 0.82rem; font-weight: 700;">Select Month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-control" onchange="this.form.submit()">
      </div>
    </form>

    <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
      <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
      <div>
        <label style="font-size: 0.82rem; font-weight: 700;">Working Days</label>
        <input type="number" name="working_days" value="<?= intval($run['working_days'] ?? 26) ?>" min="1" max="31" class="form-control" style="width: 110px;">
      </div>
      <div>
        <label style="font-size: 0.82rem; font-weight: 700;">Late Rule (Lates = 1 Day)</label>
        <input type="number" name="late_threshold" value="<?= intval($run['late_threshold'] ?? 3) ?>" min="1" max="15" class="form-control" style="width: 140px;">
      </div>
      <button type="submit" name="action_generate_payroll" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.62rem 1.25rem; border-radius: 8px; border: none; cursor: pointer;">
        <i class="fas fa-sync-alt"></i> <?= $run ? 'Recalculate Sheet' : 'Generate Monthly Payroll' ?>
      </button>
    </form>
  </div>
</div>

<?php if (!empty($slips)): ?>
<!-- Summary KPIs -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
  <div class="card-box" style="padding: 1rem;">
    <small style="color: #64748b; font-weight: 700; text-transform: uppercase;">Total Gross</small>
    <h3 style="margin: 4px 0 0 0;"><?= format_currency($totalGross) ?></h3>
  </div>
  <div class="card-box" style="padding: 1rem;">
    <small style="color: #dc2626; font-weight: 700; text-transform: uppercase;">Total Deductions</small>
    <h3 style="margin: 4px 0 0 0; color: #dc2626;">- <?= format_currency($totalDed) ?></h3>
  </div>
  <div class="card-box" style="padding: 1rem;">
    <small style="color: #059669; font-weight: 700; text-transform: uppercase;">Bonuses Added</small>
    <h3 style="margin: 4px 0 0 0; color: #059669;">+ <?= format_currency($totalBonus) ?></h3>
  </div>
  <div class="card-box" style="padding: 1rem;">
    <small style="color: #0369a1; font-weight: 700; text-transform: uppercase;">Net Payroll Payable</small>
    <h3 style="margin: 4px 0 0 0; color: #0369a1;"><?= format_currency($totalNet) ?></h3>
  </div>
</div>
<?php endif; ?>

<!-- Payroll Sheet Table -->
<div class="card-box">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Payroll Sheet &mdash; <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h3>
    <?php if ($unpaidCnt > 0): ?>
      <form method="POST" style="display: inline-flex; gap: 8px; align-items: center;">
        <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
        <input type="hidden" name="paid_date" value="<?= date('Y-m-d') ?>">
        <button type="submit" name="action_pay_all" class="btn" style="background: #10b981; color: #fff; font-weight: 700; padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer;" onclick="return confirm('Mark all remaining unpaid slips as Paid and auto-sync to Expense Ledger?')">
          <i class="fas fa-check-double"></i> Pay All Remaining (<?= $unpaidCnt ?>)
        </button>
      </form>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff ID &amp; Name</th>
          <th>Gross Salary</th>
          <th>Attendance &amp; Late</th>
          <th>Unpaid Ded.</th>
          <th>Late Ded. (HR Override)</th>
          <th>Bonus / Other</th>
          <th>Net Payable</th>
          <th>Status &amp; Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($slips)): ?>
          <?php foreach ($slips as $s): ?>
            <?php $dailyRate = intval($s['working_days']) > 0 ? round(floatval($s['gross_salary']) / intval($s['working_days']), 2) : 0; ?>
            <tr>
              <td>
                <code><?= htmlspecialchars($s['employee_code_snapshot']) ?></code>
                <br><strong><?= htmlspecialchars($s['employee_name_snapshot']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($s['designation_snapshot']) ?></small>
              </td>
              <td>
                <strong><?= format_currency($s['gross_salary']) ?></strong>
                <br><small style="color: #64748b;">Rate/d: <?= format_currency($dailyRate) ?></small>
              </td>
              <td>
                <small style="display: block;">P: <strong><?= intval($s['present_days']) ?></strong> | PL: <strong><?= intval($s['paid_leave_days']) ?></strong></small>
                <small style="display: block; color: #dc2626;">Absent: <strong><?= intval($s['absent_days']) ?></strong> | UL: <strong><?= intval($s['unpaid_leave_days']) ?></strong></small>
                <small style="display: block; color: #b45309;">Late: <strong><?= intval($s['late_days']) ?> day(s)</strong></small>
              </td>
              <td>
                <strong style="color: #dc2626;"><?= format_currency($s['unpaid_deduction']) ?></strong>
              </td>
              <td>
                <strong style="color: #b45309;"><?= format_currency($s['late_deduction']) ?></strong>
                <br><small style="color: #64748b;">Applied: <?= intval($s['applied_late_deduct_days']) ?>d (Sugg: <?= intval($s['suggested_late_deduct_days']) ?>d)</small>
                <?php if (!empty($s['adjustment_note'])): ?>
                  <br><small style="color: #0284c7;"><?= htmlspecialchars($s['adjustment_note']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <small style="color: #059669; display: block;">+ <?= format_currency($s['bonus_amount']) ?></small>
                <small style="color: #dc2626; display: block;">- <?= format_currency($s['other_deduction']) ?></small>
              </td>
              <td>
                <strong style="font-size: 1rem; color: var(--admin-deep-navy);"><?= format_currency($s['net_payable']) ?></strong>
              </td>
              <td style="white-space: nowrap;">
                <?php if ($s['payment_status'] === 'Paid'): ?>
                  <span class="badge badge-success" style="margin-bottom: 4px; display: inline-block;">Paid (<?= format_date($s['paid_date']) ?>)</span><br>
                <?php else: ?>
                  <button type="button" class="btn" style="background: #fef3c7; color: #92400e; padding: 0.28rem 0.6rem; font-size: 0.76rem; font-weight: 700; border: 1px solid #fde68a; border-radius: 6px; cursor: pointer;" onclick='openAdjustSlipModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>, <?= $dailyRate ?>)'>
                    Adjust Late/Bonus
                  </button>
                  <button type="button" class="btn" style="background: #10b981; color: #fff; padding: 0.28rem 0.65rem; font-size: 0.76rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer;" onclick='openPaySlipModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'>
                    Pay
                  </button>
                <?php endif; ?>
                <a href="payslip.php?id=<?= $s['id'] ?>" target="_blank" class="btn" style="background: #e0f2fe; color: #0369a1; padding: 0.28rem 0.6rem; font-size: 0.76rem; font-weight: 700; border-radius: 6px; text-decoration: none;">
                  <i class="fas fa-print"></i> Slip
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No payroll slips generated for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>. Click <strong>Generate Monthly Payroll</strong> above.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Manual Adjustment Modal (Late Override / Waive / Bonus) -->
<div id="adjustSlipModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 540px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Adjust Payroll: <span id="adjEmpName"></span></h3>
      <button type="button" onclick="closeModal('adjustSlipModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action_adjust_slip" value="1">
      <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
      <input type="hidden" name="slip_id" id="adjSlipId">

      <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 0.9rem; margin-bottom: 1rem; font-size: 0.85rem;">
        Late Arrivals: <strong id="adjLateCount"></strong> day(s) &bull; Suggested Penalty: <strong id="adjSuggDays"></strong> day(s).
        <div style="margin-top: 6px;">
          <button type="button" onclick="waiveLateDeduction()" style="background: #0f766e; color: #fff; border: none; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.78rem; cursor: pointer;">
            Waive Late Deduction (Set ৳0)
          </button>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Applied Late Penalty Days</label>
          <input type="number" name="applied_late_deduct_days" id="adjAppliedDays" min="0" class="form-control" oninput="recalcLateMoney()">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Late Deduction Amount (৳)</label>
          <input type="number" name="late_deduction" id="adjLateAmount" min="0" step="0.01" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Bonus / Overtime (৳)</label>
          <input type="number" name="bonus_amount" id="adjBonus" min="0" step="50" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Advance / Other Deduction (৳)</label>
          <input type="number" name="other_deduction" id="adjOtherDed" min="0" step="50" class="form-control">
        </div>
      </div>

      <div class="form-group" style="margin-top: 0.75rem;">
        <label style="font-size: 0.8rem; font-weight: 700;">HR Adjustment Note</label>
        <input type="text" name="adjustment_note" id="adjNote" class="form-control">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustSlipModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.3rem; border-radius: 8px;">Apply Adjustment</button>
      </div>
    </form>
  </div>
</div>

<!-- Mark Salary Paid Modal -->
<div id="paySlipModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 460px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Disburse Salary</h3>
      <button type="button" onclick="closeModal('paySlipModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action_pay_slip" value="1">
      <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
      <input type="hidden" name="slip_id" id="paySlipId">

      <p style="margin: 0 0 12px 0; font-size: 0.92rem;">Employee: <strong id="payEmpName"></strong><br>Net Payable: <strong id="payNetAmt" style="color: #059669; font-size: 1.15rem;"></strong></p>

      <div class="form-group">
        <label style="font-size: 0.82rem; font-weight: 700;">Payment Method</label>
        <select name="payment_method" id="payMethod" class="form-control">
          <option value="Cash">Cash</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="bKash">bKash</option>
          <option value="Nagad">Nagad</option>
        </select>
      </div>
      <div class="form-group" style="margin-top: 0.75rem;">
        <label style="font-size: 0.82rem; font-weight: 700;">Payment Date</label>
        <input type="date" name="paid_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
      </div>
      <small style="display: block; margin-top: 8px; color: #64748b;">Confirming payment automatically posts a Staff Salary Expense entry into the Accounting Ledger.</small>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('paySlipModal')">Cancel</button>
        <button type="submit" class="btn" style="background: #10b981; color: #fff; font-weight: 700; padding: 0.6rem 1.3rem; border-radius: 8px; border: none;">Confirm Paid</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentDailyRate = 0;
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openAdjustSlipModal(s, dailyRate) {
  currentDailyRate = dailyRate || 0;
  document.getElementById('adjSlipId').value = s.id;
  document.getElementById('adjEmpName').innerText = s.employee_name_snapshot;
  document.getElementById('adjLateCount').innerText = s.late_days;
  document.getElementById('adjSuggDays').innerText = s.suggested_late_deduct_days;
  document.getElementById('adjAppliedDays').value = s.applied_late_deduct_days;
  document.getElementById('adjLateAmount').value = s.late_deduction;
  document.getElementById('adjBonus').value = s.bonus_amount;
  document.getElementById('adjOtherDed').value = s.other_deduction;
  document.getElementById('adjNote').value = (s.adjustment_note || '').replace('[MANUAL] ', '');
  openModal('adjustSlipModal');
}
function recalcLateMoney() {
  const days = parseFloat(document.getElementById('adjAppliedDays').value || 0);
  document.getElementById('adjLateAmount').value = (days * currentDailyRate).toFixed(2);
}
function waiveLateDeduction() {
  document.getElementById('adjAppliedDays').value = 0;
  document.getElementById('adjLateAmount').value = '0.00';
  if (!document.getElementById('adjNote').value) {
    document.getElementById('adjNote').value = 'Late deduction waived by HR';
  }
}
function openPaySlipModal(s) {
  document.getElementById('paySlipId').value = s.id;
  document.getElementById('payEmpName').innerText = s.employee_name_snapshot + ' (' + s.employee_code_snapshot + ')';
  document.getElementById('payNetAmt').innerText = '৳ ' + parseFloat(s.net_payable).toLocaleString('en-US', {minimumFractionDigits: 2});
  document.getElementById('payMethod').value = s.payment_method || 'Cash';
  openModal('paySlipModal');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

