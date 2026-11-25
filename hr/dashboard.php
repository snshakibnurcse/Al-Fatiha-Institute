<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$today = date('Y-m-d');
$curMonth = date('Y-m');

$totalActiveStaff = intval($db->query("SELECT COUNT(*) FROM employees WHERE status IN ('Active','On Leave')")->fetchColumn());
$academicStaff = intval($db->query("SELECT COUNT(*) FROM employees WHERE status IN ('Active','On Leave') AND role_type IN ('principal','teacher','assistant_teacher')")->fetchColumn());
$supportAndOpsStaff = $totalActiveStaff - $academicStaff;

$todayPresent = intval($db->query("SELECT COUNT(*) FROM employee_attendance WHERE attendance_date = '$today' AND status IN ('Present','Late')")->fetchColumn());
$todayLate = intval($db->query("SELECT COUNT(*) FROM employee_attendance WHERE attendance_date = '$today' AND status = 'Late'")->fetchColumn());
$pendingLeaves = intval($db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn());

$payrollRow = $db->query("SELECT COALESCE(SUM(net_payable),0) as total_net, SUM(CASE WHEN payment_status='Paid' THEN 1 ELSE 0 END) as paid_cnt, COUNT(*) as slip_cnt FROM payroll_slips WHERE payroll_month = '$curMonth'")->fetch(PDO::FETCH_ASSOC);

$recentLeaves = $db->query("
    SELECT lr.*, e.employee_code, e.full_name, e.designation, lt.name AS leave_type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_pk = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'Pending'
    ORDER BY lr.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$recentEmployees = $db->query("SELECT * FROM employees ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">HR &amp; Workforce Overview</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Manage employee directory, attendance tracking, leave approvals, and monthly salary disbursement.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="employees.php" class="btn" style="background: #0e73b9; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
      <i class="fas fa-user-plus"></i> Staff Directory
    </a>
    <a href="payroll.php" class="btn" style="background: #0f766e; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; text-decoration: none;">
      <i class="fas fa-calculator"></i> Run Payroll
    </a>
  </div>
</div>

<!-- KPI Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.8rem;">
  <div class="card-box" style="padding: 1.25rem;">
    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active Workforce</span>
    <h3 style="margin: 6px 0 2px 0; font-size: 1.9rem; color: var(--admin-deep-navy);"><?= $totalActiveStaff ?></h3>
    <small style="color: #0284c7; font-weight: 600;"><?= $academicStaff ?> Academic &bull; <?= $supportAndOpsStaff ?> Admin/Support</small>
  </div>

  <div class="card-box" style="padding: 1.25rem;">
    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Today Attendance (<?= date('d M') ?>)</span>
    <h3 style="margin: 6px 0 2px 0; font-size: 1.9rem; color: #15803d;"><?= $todayPresent ?> / <?= $totalActiveStaff ?></h3>
    <small style="color: #b45309; font-weight: 600;"><?= $todayLate ?> Late Arrivals Today</small>
  </div>

  <div class="card-box" style="padding: 1.25rem;">
    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Leave Requests</span>
    <h3 style="margin: 6px 0 2px 0; font-size: 1.9rem; color: <?= $pendingLeaves > 0 ? '#dc2626' : '#0f766e' ?>;"><?= $pendingLeaves ?></h3>
    <a href="leaves.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">Review Requests &rarr;</a>
  </div>

  <div class="card-box" style="padding: 1.25rem;">
    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?= date('F Y') ?> Payroll</span>
    <h3 style="margin: 6px 0 2px 0; font-size: 1.65rem; color: var(--admin-deep-navy);"><?= format_currency($payrollRow['total_net'] ?? 0) ?></h3>
    <small style="color: #64748b; font-weight: 600;"><?= intval($payrollRow['paid_cnt'] ?? 0) ?> / <?= intval($payrollRow['slip_cnt'] ?? 0) ?> Slips Paid</small>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.25rem;">
  <!-- Pending Leave Queue -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-clock text-gold"></i> Pending Leave Approvals</h3>
      <a href="leaves.php" style="font-size: 0.84rem; font-weight: 700; color: #0e73b9; text-decoration: none;">View All</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Leave Type</th>
            <th>Dates</th>
            <th>Days</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recentLeaves)): ?>
            <?php foreach ($recentLeaves as $lr): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($lr['full_name']) ?></strong>
                  <br><small><code><?= htmlspecialchars($lr['employee_code']) ?></code> &bull; <?= htmlspecialchars($lr['designation']) ?></small>
                </td>
                <td>
                  <span class="badge <?= $lr['is_paid'] ? 'badge-info' : 'badge-warning' ?>"><?= htmlspecialchars($lr['leave_type_name']) ?></span>
                </td>
                <td><small><?= format_date($lr['start_date']) ?> &ndash; <?= format_date($lr['end_date']) ?></small></td>
                <td><strong><?= intval($lr['total_days']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No pending leave applications.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Staff Snapshot -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-users"></i> Staff ID Prefix Quick Reference</h3>
      <a href="employees.php" style="font-size: 0.84rem; font-weight: 700; color: #0e73b9; text-decoration: none;">Manage Staff</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Role / Designation</th>
            <th>Salary</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recentEmployees)): ?>
            <?php foreach ($recentEmployees as $emp): ?>
              <?php $gross = floatval($emp['basic_salary']) + floatval($emp['house_rent']) + floatval($emp['medical_allowance']) + floatval($emp['transport_allowance']) + floatval($emp['other_allowance']); ?>
              <tr>
                <td><code><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($emp['designation']) ?></td>
                <td><strong><?= format_currency($gross) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No employees registered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

