<?php
require_once __DIR__ . '/../includes/functions.php';
if (!is_logged_in()) {
    redirect('../login.php');
}
$db = get_db_connection();
$slipId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT ps.*, e.department, e.phone, e.email, e.bank_account, e.basic_salary AS cur_basic,
           e.house_rent, e.medical_allowance, e.transport_allowance, e.other_allowance, e.user_id
    FROM payroll_slips ps
    JOIN employees e ON ps.employee_pk = e.id
    WHERE ps.id = ? LIMIT 1
");
$stmt->execute([$slipId]);
$slip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slip) {
    die("Payslip not found.");
}

// Restrict access: HR and System Admin can view any slip; other employees can only view their own slip
$role = get_current_user_role();
if (!in_array($role, ['hr', 'super_admin', 'admin'], true)) {
    if (intval($slip['user_id']) !== intval(get_current_user_id())) {
        die("Access Denied: You can only view your own payslip.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payslip <?= htmlspecialchars($slip['slip_no']) ?> | AL FATIHA INSTITUTE</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px; }
    .slip-wrap { max-width: 780px; margin: 0 auto; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 14px; padding: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
    .slip-head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #032d4e; padding-bottom: 16px; margin-bottom: 20px; }
    .slip-head h1 { margin: 0; font-size: 1.35rem; color: #032d4e; }
    .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 10px; margin-bottom: 18px; font-size: 0.9rem; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 0.92rem; }
    th, td { border: 1px solid #cbd5e1; padding: 9px 12px; text-align: left; }
    th { background: #f1f5f9; color: #032d4e; }
    .right { text-align: right; }
    .net-box { background: #032d4e; color: #fff; padding: 14px 18px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 1.15rem; font-weight: 700; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 48px; font-size: 0.85rem; color: #475569; }
    .sig-box { border-top: 1px solid #64748b; width: 200px; text-align: center; padding-top: 6px; }
    @media print {
      body { background: #fff; padding: 0; }
      .slip-wrap { border: none; box-shadow: none; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="slip-wrap">
    <div style="display:flex; justify-content:flex-end; margin-bottom:10px;" class="no-print">
      <button onclick="window.print()" style="background:#0e73b9; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-weight:700; cursor:pointer;">Print Payslip</button>
    </div>

    <div class="slip-head">
      <div>
        <h1>AL FATIHA INSTITUTE</h1>
        <small style="color:#475569;">Official Monthly Salary Slip &bull; <?= date('F Y', strtotime($slip['payroll_month'] . '-01')) ?></small>
      </div>
      <div style="text-align:right;">
        <strong>Slip No:</strong> <code><?= htmlspecialchars($slip['slip_no']) ?></code><br>
        <strong>Status:</strong> <?= htmlspecialchars($slip['payment_status']) ?>
      </div>
    </div>

    <div class="meta-grid">
      <div>
        <div><strong>Staff ID:</strong> <?= htmlspecialchars($slip['employee_code_snapshot']) ?></div>
        <div><strong>Name:</strong> <?= htmlspecialchars($slip['employee_name_snapshot']) ?></div>
        <div><strong>Designation:</strong> <?= htmlspecialchars($slip['designation_snapshot']) ?> (<?= htmlspecialchars($slip['department']) ?>)</div>
      </div>
      <div>
        <div><strong>Working Days:</strong> <?= intval($slip['working_days']) ?></div>
        <div><strong>Attendance:</strong> Present <?= intval($slip['present_days']) ?> | Paid Leave <?= intval($slip['paid_leave_days']) ?> | Absent <?= intval($slip['absent_days']) ?> | Unpaid Leave <?= intval($slip['unpaid_leave_days']) ?></div>
        <div><strong>Late Days:</strong> <?= intval($slip['late_days']) ?> (Penalty Applied: <?= intval($slip['applied_late_deduct_days']) ?>d)</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Earnings</th>
          <th class="right">Amount (৳)</th>
          <th>Deductions</th>
          <th class="right">Amount (৳)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Basic Salary</td>
          <td class="right"><?= format_currency($slip['basic_salary']) ?></td>
          <td>Unpaid Absent / Leave Deduction</td>
          <td class="right"><?= format_currency($slip['unpaid_deduction']) ?></td>
        </tr>
        <tr>
          <td>Total Allowances (House Rent, Medical, Transport, Other)</td>
          <td class="right"><?= format_currency($slip['allowances']) ?></td>
          <td>Late Arrival Deduction</td>
          <td class="right"><?= format_currency($slip['late_deduction']) ?></td>
        </tr>
        <tr>
          <td>Bonus / Overtime</td>
          <td class="right"><?= format_currency($slip['bonus_amount']) ?></td>
          <td>Advance / Other Deduction</td>
          <td class="right"><?= format_currency($slip['other_deduction']) ?></td>
        </tr>
        <tr>
          <th>Total Gross + Bonus</th>
          <th class="right"><?= format_currency(floatval($slip['gross_salary']) + floatval($slip['bonus_amount'])) ?></th>
          <th>Total Deductions</th>
          <th class="right"><?= format_currency(floatval($slip['unpaid_deduction']) + floatval($slip['late_deduction']) + floatval($slip['other_deduction'])) ?></th>
        </tr>
      </tbody>
    </table>

    <div class="net-box">
      <span>NET PAYABLE SALARY</span>
      <span><?= format_currency($slip['net_payable']) ?></span>
    </div>

    <?php if (!empty($slip['adjustment_note']) || !empty($slip['paid_date'])): ?>
      <p style="font-size:0.85rem; color:#475569; margin-top:12px;">
        <?php if ($slip['payment_status'] === 'Paid'): ?>
          <strong>Paid on:</strong> <?= format_date($slip['paid_date']) ?> via <strong><?= htmlspecialchars($slip['payment_method']) ?></strong>
        <?php endif; ?>
        <?php if (!empty($slip['adjustment_note'])): ?>
          <br><strong>HR Note:</strong> <?= htmlspecialchars($slip['adjustment_note']) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <div class="sig-row">
      <div class="sig-box">HR / Accounts Officer</div>
      <div class="sig-box">Principal Approval</div>
      <div class="sig-box">Employee Signature</div>
    </div>
  </div>
</body>
</html>

