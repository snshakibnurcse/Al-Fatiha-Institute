<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();
$stStmt = $db->prepare("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.parent_user_id = ? LIMIT 1");
$stStmt->execute([$user_id]);
$student = $stStmt->fetch();
if (!$student) {
    $student = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = 1")->fetch();
}

$st_id = $student['id'];
$attendanceList = $db->query("SELECT * FROM attendance WHERE student_id = $st_id ORDER BY attendance_date DESC")->fetchAll();
?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-calendar-check"></i> Attendance History Log</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Day</th>
          <th>Status</th>
          <th>Teacher Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($attendanceList)): ?>
          <?php foreach ($attendanceList as $a): ?>
            <tr>
              <td><strong><?= format_date($a['attendance_date']) ?></strong></td>
              <td><?= date('l', strtotime($a['attendance_date'])) ?></td>
              <td>
                <?php if ($a['status'] == 'Present'): ?>
                  <span class="badge badge-success">Present</span>
                <?php elseif ($a['status'] == 'Late'): ?>
                  <span class="badge badge-warning">Late</span>
                <?php else: ?>
                  <span class="badge badge-danger">Absent</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($a['remarks'] ?: 'Normal class routine') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" style="text-align: center;">No attendance records found yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
