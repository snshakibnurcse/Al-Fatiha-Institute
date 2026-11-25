<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';
$selected_date = sanitize($_POST['attendance_date'] ?? $_GET['attendance_date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff_attendance'])) {
    $statuses = $_POST['status'] ?? [];
    $check_ins = $_POST['check_in'] ?? [];
    $check_outs = $_POST['check_out'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $actorId = get_current_user_id();

    try {
        $db->beginTransaction();
        $attStmt = $db->prepare("
            INSERT INTO employee_attendance (employee_pk, attendance_date, status, check_in, check_out, remarks, marked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = VALUES(check_in), check_out = VALUES(check_out), remarks = VALUES(remarks), marked_by = VALUES(marked_by)
        ");

        $tchLookup = $db->prepare("SELECT teacher_id FROM employees WHERE id = ? LIMIT 1");
        foreach ($statuses as $empId => $st) {
            $empPk = intval($empId);
            $ci = !empty($check_ins[$empPk]) ? $check_ins[$empPk] : null;
            $co = !empty($check_outs[$empPk]) ? $check_outs[$empPk] : null;
            $rem = sanitize($remarks[$empPk] ?? '');
            $attStmt->execute([$empPk, $selected_date, $st, $ci, $co, $rem, $actorId]);

            // Sync with legacy teacher_attendance if employee is linked to a teacher
            $tchLookup->execute([$empPk]);
            $tPk = intval($tchLookup->fetchColumn());
            if ($tPk > 0) {
                $tStatus = ($st === 'On Leave') ? 'Excused' : (($st === 'Half-Day') ? 'Late' : $st);
                $db->prepare("DELETE FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?")->execute([$tPk, $selected_date]);
                $db->prepare("INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?)")->execute([$tPk, $selected_date, $tStatus, $rem]);
            }
        }
        $db->commit();
        $msg = "Staff attendance for <strong>" . format_date($selected_date) . "</strong> saved successfully!";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $err = "Failed to save attendance: " . $e->getMessage();
    }
}

$employees = $db->query("SELECT * FROM employees WHERE status IN ('Active', 'On Leave') ORDER BY role_type ASC, employee_code ASC")->fetchAll(PDO::FETCH_ASSOC);

$existing = [];
$eStmt = $db->prepare("SELECT * FROM employee_attendance WHERE attendance_date = ?");
$eStmt->execute([$selected_date]);
foreach ($eStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row['employee_pk']] = $row;
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">All Staff Attendance Register</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Track daily attendance, late arrivals, half-days, and approved leaves for all staff members.</p>
  </div>
  <form method="GET" style="display: flex; align-items: center; gap: 8px;">
    <label style="font-size: 0.85rem; font-weight: 700;">Date:</label>
    <input type="date" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>" class="form-control" style="width: 170px;" onchange="this.form.submit()">
  </form>
</div>

<div class="card-box">
  <form method="POST">
    <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Staff ID</th>
            <th>Employee &amp; Designation</th>
            <th>Status</th>
            <th>In / Out</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($employees)): ?>
            <?php foreach ($employees as $emp): ?>
              <?php
                $cur = $existing[$emp['id']] ?? null;
                $st = $cur['status'] ?? 'Present';
                $ci = $cur['check_in'] ?? '';
                $co = $cur['check_out'] ?? '';
                $rem = $cur['remarks'] ?? '';
              ?>
              <tr>
                <td><code><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                  <br><small style="color: #64748b;"><?= htmlspecialchars($emp['designation']) ?></small>
                </td>
                <td>
                  <select name="status[<?= $emp['id'] ?>]" class="form-control" style="min-width: 140px; font-weight: 600;">
                    <?php foreach (['Present', 'Absent', 'Late', 'Half-Day', 'On Leave'] as $opt): ?>
                      <option value="<?= $opt ?>" <?= $st === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <div style="display: flex; gap: 4px;">
                    <input type="time" name="check_in[<?= $emp['id'] ?>]" value="<?= htmlspecialchars($ci) ?>" class="form-control" style="width: 110px; font-size: 0.8rem;">
                    <input type="time" name="check_out[<?= $emp['id'] ?>]" value="<?= htmlspecialchars($co) ?>" class="form-control" style="width: 110px; font-size: 0.8rem;">
                  </div>
                </td>
                <td>
                  <input type="text" name="remarks[<?= $emp['id'] ?>]" value="<?= htmlspecialchars($rem) ?>" class="form-control" style="min-width: 160px;">
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No active employees found. Add staff in Employee Directory first.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($employees)): ?>
      <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
        <button type="submit" name="save_staff_attendance" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.65rem 1.5rem; border-radius: 8px; border: none; cursor: pointer;">
          <i class="fas fa-save"></i> Save Attendance Register
        </button>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

