<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$selectedMonth = sanitize($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));

$employees = $db->query("SELECT * FROM employees WHERE status IN ('Active', 'On Leave') ORDER BY role_type ASC, employee_code ASC")->fetchAll(PDO::FETCH_ASSOC);

// Daily status map
$dailyMap = [];
$dStmt = $db->prepare("SELECT * FROM employee_attendance WHERE attendance_date = ?");
$dStmt->execute([$selectedDate]);
foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $dailyMap[$r['employee_pk']] = $r;
}

// Monthly summary map per employee
$monthlyReport = [];
foreach ($employees as $emp) {
    $sum = hr_get_monthly_attendance_summary($emp['id'], $selectedMonth);
    $totalTracked = $sum['present_days'] + $sum['absent_days'] + $sum['half_days'] + $sum['paid_leave_days'] + $sum['unpaid_leave_days'];
    $rate = $totalTracked > 0 ? round(($sum['present_days'] / $totalTracked) * 100, 1) : 100;
    $monthlyReport[] = [
        'emp' => $emp,
        'summary' => $sum,
        'tracked' => $totalTracked,
        'rate' => $rate
    ];
}

// Recent Leave Requests
$leaves = $db->query("
    SELECT lr.*, e.employee_code, e.full_name, e.designation, lt.name as leave_type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_pk = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    ORDER BY lr.created_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">All Employees Attendance Report</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Monitor daily check-ins, monthly punctuality, absences, and approved leave records for every employee.</p>
  </div>
  <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
    <div>
      <label style="font-size: 0.78rem; font-weight: 700;">Monthly Report</label>
      <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" class="form-control" onchange="this.form.submit()">
    </div>
    <div>
      <label style="font-size: 0.78rem; font-weight: 700;">Daily Snapshot Date</label>
      <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" class="form-control" onchange="this.form.submit()">
    </div>
  </form>
</div>

<!-- Monthly Matrix across all employees -->
<div class="card-box" style="margin-bottom: 1.5rem;">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Monthly Attendance Summary &mdash; <?= date('F Y', strtotime($selectedMonth . '-01')) ?> (<?= count($employees) ?> Staff)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff ID</th>
          <th>Employee &amp; Designation</th>
          <th>Department</th>
          <th>Present (Incl. Late)</th>
          <th>Late</th>
          <th>Absent</th>
          <th>Paid Leave</th>
          <th>Unpaid Leave</th>
          <th>Presence Rate</th>
          <th><?= format_date($selectedDate) ?> Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($monthlyReport)): ?>
          <?php foreach ($monthlyReport as $row): ?>
            <?php
              $e = $row['emp'];
              $s = $row['summary'];
              $d = $dailyMap[$e['id']] ?? null;
              $dStatus = $d['status'] ?? 'Not Marked';
            ?>
            <tr>
              <td><code><?= htmlspecialchars($e['employee_code']) ?></code></td>
              <td>
                <strong><?= htmlspecialchars($e['full_name']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($e['designation']) ?></small>
              </td>
              <td><?= htmlspecialchars($e['department']) ?></td>
              <td><strong style="color: #15803d;"><?= intval($s['present_days']) ?></strong></td>
              <td><strong style="color: #b45309;"><?= intval($s['late_days']) ?></strong></td>
              <td><strong style="color: #dc2626;"><?= intval($s['absent_days']) ?></strong></td>
              <td><?= intval($s['paid_leave_days']) ?></td>
              <td><?= intval($s['unpaid_leave_days']) ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div class="progress-track" style="width: 70px;">
                    <div class="progress-fill" style="width: <?= $row['rate'] ?>%; background: <?= $row['rate'] >= 85 ? '#10b981' : '#f59e0b' ?>;"></div>
                  </div>
                  <strong><?= $row['rate'] ?>%</strong>
                </div>
              </td>
              <td>
                <span class="badge <?= $dStatus === 'Present' ? 'badge-success' : ($dStatus === 'Absent' ? 'badge-danger' : ($dStatus === 'Not Marked' ? 'badge-info' : 'badge-warning')) ?>">
                  <?= htmlspecialchars($dStatus) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" style="text-align: center; padding: 2rem; color: #94a3b8;">No active employees registered yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Leave Activity -->
<div class="card-box">
  <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-minus"></i> Recent Employee Leave Records</h3></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Type</th>
          <th>Dates</th>
          <th>Days</th>
          <th>Reason</th>
          <th>Approval Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($leaves)): ?>
          <?php foreach ($leaves as $lv): ?>
            <tr>
              <td><code><?= htmlspecialchars($lv['employee_code']) ?></code> <strong><?= htmlspecialchars($lv['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($lv['leave_type_name']) ?> (<?= $lv['is_paid'] ? 'Paid' : 'Unpaid' ?>)</td>
              <td><?= format_date($lv['start_date']) ?> &ndash; <?= format_date($lv['end_date']) ?></td>
              <td><strong><?= intval($lv['total_days']) ?></strong></td>
              <td><small><?= htmlspecialchars($lv['reason']) ?></small></td>
              <td><span class="badge <?= $lv['status'] === 'Approved' ? 'badge-success' : ($lv['status'] === 'Pending' ? 'badge-warning' : 'badge-danger') ?>"><?= htmlspecialchars($lv['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center; padding: 1.5rem; color: #94a3b8;">No leave records logged yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
