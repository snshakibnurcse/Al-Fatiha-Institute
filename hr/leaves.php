<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// 1. Handle Apply Leave (by HR on behalf of any employee)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_leave'])) {
    $empPk = intval($_POST['employee_pk'] ?? 0);
    $leaveTypeId = intval($_POST['leave_type_id'] ?? 0);
    $startDate = sanitize($_POST['start_date'] ?? '');
    $endDate = sanitize($_POST['end_date'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $autoApprove = isset($_POST['auto_approve']) && $_POST['auto_approve'] === '1';

    if ($empPk <= 0 || $leaveTypeId <= 0 || empty($startDate) || empty($endDate) || empty($reason)) {
        $err = "Please select employee, leave type, start/end dates, and provide reason.";
    } else {
        $days = hr_count_leave_days($startDate, $endDate);
        if ($days <= 0) {
            $err = "End date must be on or after start date.";
        } else {
            try {
                $ins = $db->prepare("INSERT INTO leave_requests (employee_pk, leave_type_id, start_date, end_date, total_days, reason, status, applied_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)");
                $ins->execute([$empPk, $leaveTypeId, $startDate, $endDate, $days, $reason, get_current_user_id()]);
                $reqId = intval($db->lastInsertId());

                if ($autoApprove) {
                    $rev = hr_review_leave_request($reqId, 'Approved', get_current_user_id(), 'Recorded and approved by HR');
                    $msg = $rev['message'];
                } else {
                    $msg = "Leave application submitted ($days day(s)) and awaiting review.";
                }
            } catch (Exception $e) {
                $err = "Failed to log leave: " . $e->getMessage();
            }
        }
    }
}

// 2. Handle Approve / Reject Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_review_leave'])) {
    $reqId = intval($_POST['leave_id'] ?? 0);
    $decision = sanitize($_POST['decision'] ?? '');
    $notes = sanitize($_POST['review_notes'] ?? '');
    $res = hr_review_leave_request($reqId, $decision, get_current_user_id(), $notes);
    if ($res['success']) {
        $msg = $res['message'];
    } else {
        $err = $res['message'];
    }
}

// 3. Handle Add / Update Leave Type Policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_leave_type'])) {
    $ltName = sanitize($_POST['name'] ?? '');
    $isPaid = isset($_POST['is_paid']) ? 1 : 0;
    $quota = max(0, intval($_POST['annual_quota'] ?? 10));
    $desc = sanitize($_POST['description'] ?? '');
    if (!empty($ltName)) {
        try {
            $db->prepare("INSERT INTO leave_types (name, is_paid, annual_quota, description, status) VALUES (?, ?, ?, ?, 'Active')")
               ->execute([$ltName, $isPaid, $quota, $desc]);
            $msg = "Leave type '<strong>" . htmlspecialchars($ltName) . "</strong>' added.";
        } catch (Exception $e) {
            $err = "Failed to add leave type: " . $e->getMessage();
        }
    }
}

$employees = $db->query("SELECT id, employee_code, full_name, designation FROM employees WHERE status IN ('Active','On Leave') ORDER BY employee_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$leaveTypes = $db->query("SELECT * FROM leave_types ORDER BY is_paid DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$requests = $db->query("
    SELECT lr.*, e.employee_code, e.full_name, e.designation, lt.name AS leave_type_name, lt.is_paid
    FROM leave_requests lr
    JOIN employees e ON lr.employee_pk = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    ORDER BY (lr.status = 'Pending') DESC, lr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Leave Requests &amp; Policy</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Review employee leave requests. Approved leaves automatically sync to Staff Attendance and Payroll.</p>
  </div>
  <div style="display: flex; gap: 8px;">
    <button type="button" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer;" onclick="openModal('addLeaveModal')">
      <i class="fas fa-plus-circle"></i> Record Leave Request
    </button>
    <button type="button" class="btn" style="background: #f1f5f9; color: #334155; font-weight: 700; padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;" onclick="openModal('addLeaveTypeModal')">
      <i class="fas fa-sliders-h"></i> Leave Types
    </button>
  </div>
</div>

<!-- Requests Table -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-calendar-check"></i> All Leave Applications (<?= count($requests) ?>)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Leave Type</th>
          <th>Duration</th>
          <th>Days</th>
          <th>Reason</th>
          <th>Status</th>
          <th style="text-align: center;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($requests)): ?>
          <?php foreach ($requests as $lr): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($lr['full_name']) ?></strong>
                <br><small><code><?= htmlspecialchars($lr['employee_code']) ?></code> &bull; <?= htmlspecialchars($lr['designation']) ?></small>
              </td>
              <td>
                <span class="badge <?= $lr['is_paid'] ? 'badge-info' : 'badge-warning' ?>">
                  <?= htmlspecialchars($lr['leave_type_name']) ?> (<?= $lr['is_paid'] ? 'Paid' : 'Unpaid' ?>)
                </span>
              </td>
              <td><?= format_date($lr['start_date']) ?> &ndash; <?= format_date($lr['end_date']) ?></td>
              <td><strong><?= intval($lr['total_days']) ?></strong></td>
              <td><small><?= htmlspecialchars($lr['reason']) ?></small></td>
              <td>
                <span class="badge <?= $lr['status'] === 'Approved' ? 'badge-success' : ($lr['status'] === 'Pending' ? 'badge-warning' : 'badge-danger') ?>">
                  <?= htmlspecialchars($lr['status']) ?>
                </span>
                <?php if (!empty($lr['review_notes'])): ?>
                  <br><small style="color: #64748b;"><?= htmlspecialchars($lr['review_notes']) ?></small>
                <?php endif; ?>
              </td>
              <td style="text-align: center; white-space: nowrap;">
                <?php if ($lr['status'] === 'Pending'): ?>
                  <form method="POST" style="display: inline-flex; gap: 4px;">
                    <input type="hidden" name="action_review_leave" value="1">
                    <input type="hidden" name="leave_id" value="<?= $lr['id'] ?>">
                    <button type="submit" name="decision" value="Approved" class="btn" style="background: #10b981; color: #fff; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer;">Approve</button>
                    <button type="submit" name="decision" value="Rejected" class="btn" style="background: #ef4444; color: #fff; padding: 0.3rem 0.65rem; font-size: 0.78rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer;">Reject</button>
                  </form>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.8rem;">Reviewed</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">No leave applications recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Leave Modal -->
<div id="addLeaveModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 560px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Record Employee Leave Request</h3>
      <button type="button" onclick="closeModal('addLeaveModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action_add_leave" value="1">
      <div class="form-group">
        <label style="font-size: 0.82rem; font-weight: 700;">Select Employee *</label>
        <select name="employee_pk" class="form-control" required>
          <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>">[<?= htmlspecialchars($emp['employee_code']) ?>] <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['designation']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-top: 0.75rem;">
        <label style="font-size: 0.82rem; font-weight: 700;">Leave Type *</label>
        <select name="leave_type_id" class="form-control" required>
          <?php foreach ($leaveTypes as $lt): ?>
            <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?> &bull; Quota: <?= $lt['annual_quota'] ?>d)</option>
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
      <div style="margin-top: 0.75rem;">
        <label style="font-size: 0.85rem; font-weight: 700; cursor: pointer;">
          <input type="checkbox" name="auto_approve" value="1" checked> Approve immediately &amp; mark Staff Attendance as On Leave
        </label>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addLeaveModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.3rem; border-radius: 8px;">Submit Leave</button>
      </div>
    </form>
  </div>
</div>

<!-- Leave Types Modal -->
<div id="addLeaveTypeModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 540px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Configured Leave Types</h3>
      <button type="button" onclick="closeModal('addLeaveTypeModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
    </div>
    <ul style="list-style: none; padding: 0; margin: 0 0 1rem 0;">
      <?php foreach ($leaveTypes as $lt): ?>
        <li style="padding: 8px 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 6px; display: flex; justify-content: space-between;">
          <span><strong><?= htmlspecialchars($lt['name']) ?></strong> (<?= $lt['annual_quota'] ?> days/yr)</span>
          <span class="badge <?= $lt['is_paid'] ? 'badge-success' : 'badge-warning' ?>"><?= $lt['is_paid'] ? 'Paid' : 'Unpaid' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="POST" style="border-top: 1px solid #e2e8f0; padding-top: 1rem;">
      <input type="hidden" name="action_save_leave_type" value="1">
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">New Leave Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Annual Quota</label>
          <input type="number" name="annual_quota" value="10" min="1" class="form-control">
        </div>
      </div>
      <div style="margin-top: 8px;">
        <label style="font-size: 0.84rem; font-weight: 700;"><input type="checkbox" name="is_paid" value="1" checked> Paid Leave (No salary deduction)</label>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addLeaveTypeModal')">Close</button>
        <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.5rem 1.1rem; border-radius: 8px;">Add Policy</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

