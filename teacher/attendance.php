<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// Fetch teacher record if user is a teacher
$teacher = null;
if ($user_role === 'teacher') {
    $tchStmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
    $tchStmt->execute([$user_id]);
    $teacher = $tchStmt->fetch();
    if (!$teacher) {
        $teacher = $db->query("SELECT * FROM teachers LIMIT 1")->fetch();
    }
}

// Fetch allowed classes for attendance
if ($user_role === 'teacher' && $teacher) {
    $t_id = $teacher['id'];
    $classesStmt = $db->prepare("SELECT * FROM classes WHERE primary_teacher_id = ? OR backup_teacher_id = ? OR id IN (SELECT class_id FROM subjects WHERE teacher_id = ?) ORDER BY id ASC");
    $classesStmt->execute([$t_id, $t_id, $t_id]);
    $classes = $classesStmt->fetchAll();
} else {
    // Admin can access all classes
    $classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();
}

$selected_class_id = intval($_POST['class_id'] ?? $_GET['class_id'] ?? ($classes[0]['id'] ?? 4));
$selected_date = sanitize($_POST['attendance_date'] ?? $_GET['attendance_date'] ?? date('Y-m-d'));

// Save Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $att_status = $_POST['status'] ?? [];
    $att_remarks = $_POST['remarks'] ?? [];

    try {
        $db->beginTransaction();
        foreach ($att_status as $st_id => $status) {
            $rem = sanitize($att_remarks[$st_id] ?? '');

            // Delete old record for same student and date if exists
            $del = $db->prepare("DELETE FROM attendance WHERE student_id = ? AND attendance_date = ?");
            $del->execute([$st_id, $selected_date]);

            // Insert new record
            $ins = $db->prepare("INSERT INTO attendance (student_id, class_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$st_id, $selected_class_id, $selected_date, $status, $rem]);
        }
        $db->commit();
        $msg = "Attendance marked successfully for date: <strong>" . format_date($selected_date) . "</strong>!";
    } catch (Exception $e) {
        $db->rollBack();
        $err = "Failed to save attendance: " . $e->getMessage();
    }
}

$students = $db->query("SELECT * FROM students WHERE class_id = $selected_class_id ORDER BY roll_no ASC")->fetchAll();

// Existing attendance map
$existingAtt = [];
$attStmt = $db->query("SELECT * FROM attendance WHERE class_id = $selected_class_id AND attendance_date = '$selected_date'");
foreach ($attStmt->fetchAll() as $row) {
    $existingAtt[$row['student_id']] = $row;
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clipboard-user"></i> Class Attendance Marking Sheet</h3>
    
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
      <select name="class_id" class="form-control" style="width: 170px; padding: 0.4rem 0.6rem; font-size: 0.85rem;" onchange="this.form.submit()">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($selected_class_id == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="attendance_date" value="<?= $selected_date ?>" class="form-control" style="width: 150px; padding: 0.4rem 0.6rem; font-size: 0.85rem;" onchange="this.form.submit()">
    </form>
  </div>

  <form method="POST">
    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
    <input type="hidden" name="attendance_date" value="<?= $selected_date ?>">

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Roll No</th>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Attendance Status</th>
            <th>Remarks / Note</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $st): ?>
              <?php
                $curr_status = $existingAtt[$st['id']]['status'] ?? 'Present';
                $curr_rem = $existingAtt[$st['id']]['remarks'] ?? '';
              ?>
              <tr>
                <td><strong>#<?= $st['roll_no'] ?></strong></td>
                <td><code><?= htmlspecialchars($st['student_id']) ?></code></td>
                <td><strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong></td>
                <td>
                  <label style="margin-right: 0.8rem; cursor: pointer;">
                    <input type="radio" name="status[<?= $st['id'] ?>]" value="Present" <?= ($curr_status == 'Present') ? 'checked' : '' ?>>
                    <span class="badge badge-success">Present</span>
                  </label>
                  <label style="margin-right: 0.8rem; cursor: pointer;">
                    <input type="radio" name="status[<?= $st['id'] ?>]" value="Absent" <?= ($curr_status == 'Absent') ? 'checked' : '' ?>>
                    <span class="badge badge-danger">Absent</span>
                  </label>
                  <label style="cursor: pointer;">
                    <input type="radio" name="status[<?= $st['id'] ?>]" value="Late" <?= ($curr_status == 'Late') ? 'checked' : '' ?>>
                    <span class="badge badge-warning">Late</span>
                  </label>
                </td>
                <td>
                  <input type="text" name="remarks[<?= $st['id'] ?>]" value="<?= htmlspecialchars($curr_rem) ?>" placeholder="Optional note" class="form-control" style="padding: 0.25rem 0.5rem; font-size: 0.82rem;">
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align: center;">No students enrolled in this class yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($students)): ?>
      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="submit" name="save_attendance" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.75rem 2rem; border-radius: 8px; border: none; font-weight: 800; cursor: pointer;">
          <i class="fas fa-save"></i> Save Attendance Sheet
        </button>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
