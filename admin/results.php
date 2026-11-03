<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$results = $db->query("SELECT r.*, s.student_id, s.first_name, s.last_name, s.roll_no, sub.subject_name, c.class_name FROM results r JOIN students s ON r.student_id = s.id JOIN subjects sub ON r.subject_id = sub.id JOIN classes c ON s.class_id = c.id ORDER BY r.id DESC")->fetchAll();
?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-award"></i> Exam Results & Report Cards Master Directory</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Class & Roll</th>
          <th>Exam Term</th>
          <th>Subject</th>
          <th>Marks</th>
          <th>Grade</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($results)): ?>
          <?php foreach ($results as $res): ?>
            <?php $gInfo = calculate_grade($res['marks_obtained'], $res['max_marks']); ?>
            <tr>
              <td><code><?= htmlspecialchars($res['student_id']) ?></code></td>
              <td><strong><?= htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) ?></strong></td>
              <td><?= htmlspecialchars($res['class_name']) ?> (#<?= $res['roll_no'] ?>)</td>
              <td><span class="badge badge-info"><?= htmlspecialchars($res['exam_term']) ?></span></td>
              <td><?= htmlspecialchars($res['subject_name']) ?></td>
              <td><strong><?= $res['marks_obtained'] ?> / <?= $res['max_marks'] ?></strong></td>
              <td><span class="badge <?= $gInfo['class'] ?>"><?= $gInfo['grade'] ?> (<?= $gInfo['gpa'] ?>)</span></td>
              <td><?= htmlspecialchars($res['teacher_remarks']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center;">No result records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
