<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$selected_date = sanitize($_GET['date'] ?? date('Y-m-d'));
$selected_class = intval($_GET['class_id'] ?? 4);

$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();

$attendanceData = $db->query("SELECT a.*, s.student_id, s.first_name, s.last_name, s.roll_no, c.class_name FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON a.class_id = c.id WHERE a.attendance_date = '$selected_date' AND a.class_id = $selected_class")->fetchAll();
?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clipboard-user"></i> Daily Attendance Summary</h3>
    
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
      <select name="class_id" class="form-control" style="width: 160px; padding: 0.35rem 0.6rem; font-size: 0.85rem;">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($selected_class == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="date" value="<?= $selected_date ?>" class="form-control" style="width: 150px; padding: 0.35rem 0.6rem; font-size: 0.85rem;">
      <button type="submit" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.4rem 0.9rem; font-size: 0.82rem; border-radius: 6px; border: none; cursor: pointer;">Filter</button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Roll No</th>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Date</th>
          <th>Attendance Status</th>
          <th>Teacher Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($attendanceData)): ?>
          <?php foreach ($attendanceData as $att): ?>
            <tr>
              <td><strong>#<?= $att['roll_no'] ?></strong></td>
              <td><code><?= htmlspecialchars($att['student_id']) ?></code></td>
              <td><strong><?= htmlspecialchars($att['first_name'] . ' ' . $att['last_name']) ?></strong></td>
              <td><?= format_date($att['attendance_date']) ?></td>
              <td>
                <?php if ($att['status'] == 'Present'): ?>
                  <span class="badge badge-success">Present</span>
                <?php elseif ($att['status'] == 'Late'): ?>
                  <span class="badge badge-warning">Late</span>
                <?php else: ?>
                  <span class="badge badge-danger">Absent</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($att['remarks'] ?: 'N/A') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center;">No attendance records found for selected class and date.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
