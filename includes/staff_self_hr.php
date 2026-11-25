<?php
/**
 * Shared Staff Self-Service HR Panel (Leave Applications + My Payslips + My Attendance)
 * Used by teacher/my_hr.php and accountant/my_hr.php
 */
$db = get_db_connection();
$uid = intval(get_current_user_id());
$emp = get_employee_by_user_id($uid);

$msg = '';
$err = '';

if ($emp && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_apply_my_leave'])) {
    $ltId = intval($_POST['leave_type_id'] ?? 0);
    $start = sanitize($_POST['start_date'] ?? '');
    $end = sanitize($_POST['end_date'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');

    if ($ltId <= 0 || empty($start) || empty($end) || empty($reason)) {
        $err = "Please select leave type, start/end dates, and provide a reason.";
    } else {
        $days = hr_count_leave_days($start, $end);
        if ($days <= 0) {
            $err = "End date must be on or after start date.";
        } else {
            try {
                $db->prepare("INSERT INTO leave_requests (employee_pk, leave_type_id, start_date, end_date, total_days, reason, status, applied_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)")
                   ->execute([$emp['id'], $ltId, $start, $end, $days, $reason, $uid]);
                $msg = "Leave request for <strong>$days day(s)</strong> submitted for HR/Principal approval!";
            } catch (Exception $e) {
                $err = "Failed to submit leave request: " . $e->getMessage();
            }
        }
    }
}

$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status = 'Active' ORDER BY is_paid DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$myLeaves = [];
$mySlips = [];
$myAtt = [];
if ($emp) {
    $lStmt = $db->prepare("SELECT lr.*, lt.name AS leave_type_name, lt.is_paid FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.id WHERE lr.employee_pk = ? ORDER BY lr.created_at DESC");
    $lStmt->execute([$emp['id']]);
    $myLeaves = $lStmt->fetchAll(PDO::FETCH_ASSOC);

    $pStmt = $db->prepare("SELECT * FROM payroll_slips WHERE employee_pk = ? ORDER BY payroll_month DESC");
    $pStmt->execute([$emp['id']]);
    $mySlips = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    $aStmt = $db->prepare("SELECT * FROM employee_attendance WHERE employee_pk = ? ORDER BY attendance_date DESC LIMIT 20");
    $aStmt->execute([$emp['id']]);
    $myAtt = $aStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<?php if (!$emp): ?>
  <div class="card-box" style="padding: 2rem; text-align: center;">
    <h3 style="color: var(--admin-deep-navy); margin-top: 0;">Employee HR Profile Not Linked</h3>
    <p style="color: #64748b;">Your login account does not have an HR Employee record linked yet. Please contact the HR Office.</p>
  </div>
<?php else: ?>
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
      <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">My HR, Leave &amp; Payslips</h2>
      <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">
        Staff ID: <code><?= htmlspecialchars($emp['employee_code']) ?></code> &bull;
        <?= htmlspecialchars($emp['designation']) ?> &bull;
        Status: <strong><?= htmlspecialchars($emp['status']) ?></strong>
      </p>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <!-- Apply Leave Form -->
    <div class="card-box">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-paper-plane"></i> Apply for Leave</h3>
      </div>
      <form method="POST">
        <input type="hidden" name="action_apply_my_leave" value="1">
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Leave Type *</label>
          <select name="leave_type_id" class="form-control" required>
            <?php foreach ($leaveTypes as $lt): ?>
              <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 0.75rem;">
          <div class="form-group">
            <label style="font-size: 0.82rem; font-weight: 700;">Start Date *</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
          </div>
          <div class="form-group">
            <label style="font-size: 0.82rem; font-weight: 700;">End Date *</label>
            <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
          </div>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
          <label style="font-size: 0.82rem; font-weight: 700;">Reason *</label>
          <textarea name="reason" rows="2" class="form-control" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 0.9rem; background: #0e73b9; color: #fff; font-weight: 700; padding: 0.55rem 1.25rem; border-radius: 8px; border: none; cursor: pointer;">
          Submit Leave Request
        </button>
      </form>
    </div>

    <!-- My Salary Slips -->
    <div class="card-box">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> My Monthly Payslips</h3>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Month</th>
              <th>Gross</th>
              <th>Net Payable</th>
              <th>Status</th>
              <th>Print</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($mySlips)): ?>
              <?php foreach ($mySlips as $ms): ?>
                <tr>
                  <td><strong><?= date('M Y', strtotime($ms['payroll_month'] . '-01')) ?></strong></td>
                  <td><?= format_currency($ms['gross_salary']) ?></td>
                  <td><strong><?= format_currency($ms['net_payable']) ?></strong></td>
                  <td><span class="badge <?= $ms['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($ms['payment_status']) ?></span></td>
                  <td><a href="../hr/payslip.php?id=<?= $ms['id'] ?>" target="_blank" style="color: #0e73b9; font-weight: 700; text-decoration: none;">View Slip</a></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No payslips issued yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- My Leave History & Recent Attendance -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.25rem;">
    <div class="card-box">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> My Leave History</h3></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!empty($myLeaves)): ?>
              <?php foreach ($myLeaves as $ml): ?>
                <tr>
                  <td><?= htmlspecialchars($ml['leave_type_name']) ?></td>
                  <td><small><?= format_date($ml['start_date']) ?> &ndash; <?= format_date($ml['end_date']) ?></small></td>
                  <td><?= intval($ml['total_days']) ?></td>
                  <td><span class="badge <?= $ml['status'] === 'Approved' ? 'badge-success' : ($ml['status'] === 'Pending' ? 'badge-warning' : 'badge-danger') ?>"><?= htmlspecialchars($ml['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No leave applications yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-box">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-user-check"></i> Recent Attendance</h3></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
            <?php if (!empty($myAtt)): ?>
              <?php foreach ($myAtt as $ma): ?>
                <tr>
                  <td><?= format_date($ma['attendance_date']) ?></td>
                  <td><span class="badge <?= $ma['status'] === 'Present' ? 'badge-success' : ($ma['status'] === 'Absent' ? 'badge-danger' : 'badge-warning') ?>"><?= htmlspecialchars($ma['status']) ?></span></td>
                  <td><small><?= htmlspecialchars($ma['remarks'] ?: '-') ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No attendance logs yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

