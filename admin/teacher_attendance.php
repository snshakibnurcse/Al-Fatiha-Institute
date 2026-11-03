<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

$selected_date = sanitize($_POST['attendance_date'] ?? $_GET['attendance_date'] ?? date('Y-m-d'));

// Save Teacher Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_teacher_attendance'])) {
    $att_status = $_POST['status'] ?? [];
    $att_remarks = $_POST['remarks'] ?? [];

    try {
        $db->beginTransaction();
        foreach ($att_status as $tch_id => $status) {
            $t_pk = intval($tch_id);
            $rem = sanitize($att_remarks[$t_pk] ?? '');

            // Delete existing record for same teacher and date if exists
            $del = $db->prepare("DELETE FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?");
            $del->execute([$t_pk, $selected_date]);

            // Insert new record
            $ins = $db->prepare("INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?)");
            $ins->execute([$t_pk, $selected_date, $status, $rem]);
        }
        $db->commit();
        $msg = "Faculty Teacher Attendance for <strong>" . format_date($selected_date) . "</strong> recorded successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $err = "Failed to save teacher attendance: " . $e->getMessage();
    }
}

// Fetch all teachers
$teachers = $db->query("SELECT * FROM teachers ORDER BY full_name ASC")->fetchAll();

// Existing attendance map for selected date
$existingAtt = [];
$attStmt = $db->prepare("SELECT * FROM teacher_attendance WHERE attendance_date = ?");
$attStmt->execute([$selected_date]);
foreach ($attStmt->fetchAll() as $row) {
    $existingAtt[$row['teacher_id']] = $row;
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<!-- Top Header Banner -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">
      <i class="fas fa-clipboard-user" style="color: var(--admin-primary);"></i> Principal's Teacher Attendance Portal
    </h2>
    <p style="color: var(--admin-text-muted); margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Mark and track daily attendance, punctuality, and leaves for faculty teaching staff.
    </p>
  </div>
</div>

<div class="card-box">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <h3 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Faculty Attendance Register</h3>
    
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
      <label style="font-size: 0.85rem; font-weight: 700; color: var(--admin-primary-dark);">Select Date:</label>
      <input type="date" name="attendance_date" value="<?= $selected_date ?>" class="form-control" style="width: 170px; padding: 0.4rem 0.6rem; font-size: 0.85rem;" onchange="this.form.submit()">
    </form>
  </div>

  <form method="POST">
    <input type="hidden" name="attendance_date" value="<?= $selected_date ?>">

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Teacher ID</th>
            <th>Teacher Name</th>
            <th>Specialty & Qualification</th>
            <th>Phone / Email</th>
            <th>Attendance Status</th>
            <th>Remarks / Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($teachers)): ?>
            <?php foreach ($teachers as $t): ?>
              <?php
                $curr_status = $existingAtt[$t['id']]['status'] ?? 'Present';
                $curr_rem = $existingAtt[$t['id']]['remarks'] ?? '';
              ?>
              <tr>
                <td><code><?= htmlspecialchars($t['teacher_code']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($t['full_name']) ?></strong>
                </td>
                <td>
                  <span class="badge badge-info"><?= htmlspecialchars($t['specialty']) ?></span>
                  <br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($t['qualification']) ?></small>
                </td>
                <td>
                  <?= htmlspecialchars($t['phone']) ?>
                  <br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($t['email']) ?></small>
                </td>
                <td>
                  <label style="margin-right: 0.8rem; cursor: pointer;">
                    <input type="radio" name="status[<?= $t['id'] ?>]" value="Present" <?= ($curr_status == 'Present') ? 'checked' : '' ?>>
                    <span class="badge badge-success">Present</span>
                  </label>
                  <label style="margin-right: 0.8rem; cursor: pointer;">
                    <input type="radio" name="status[<?= $t['id'] ?>]" value="Absent" <?= ($curr_status == 'Absent') ? 'checked' : '' ?>>
                    <span class="badge badge-danger">Absent</span>
                  </label>
                  <label style="margin-right: 0.8rem; cursor: pointer;">
                    <input type="radio" name="status[<?= $t['id'] ?>]" value="Late" <?= ($curr_status == 'Late') ? 'checked' : '' ?>>
                    <span class="badge badge-warning">Late</span>
                  </label>
                  <label style="cursor: pointer;">
                    <input type="radio" name="status[<?= $t['id'] ?>]" value="Excused" <?= ($curr_status == 'Excused') ? 'checked' : '' ?>>
                    <span class="badge" style="background: #e0f2fe; color: #0369a1;">Leave / Excused</span>
                  </label>
                </td>
                <td>
                  <input type="text" name="remarks[<?= $t['id'] ?>]" value="<?= htmlspecialchars($curr_rem) ?>" placeholder="e.g. Sick Leave, Official Duty" class="form-control" style="padding: 0.3rem 0.6rem; font-size: 0.85rem;">
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align: center;">No teachers registered in system yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($teachers)): ?>
      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="submit" name="save_teacher_attendance" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.75rem 2rem; border-radius: 8px; border: none; font-weight: 800; cursor: pointer;">
          <i class="fas fa-save"></i> Save Teacher Attendance Sheet
        </button>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
