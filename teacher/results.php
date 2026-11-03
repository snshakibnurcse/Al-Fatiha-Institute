<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $exam_term = sanitize($_POST['exam_term'] ?? 'Mid Term');
    $marks_obtained = floatval($_POST['marks_obtained'] ?? 0);
    $max_marks = floatval($_POST['max_marks'] ?? 100);
    $teacher_remarks = sanitize($_POST['teacher_remarks'] ?? 'Good performance');

    if ($student_id && $subject_id) {
        try {
            $stmt = $db->prepare("INSERT INTO results (student_id, subject_id, exam_term, marks_obtained, max_marks, teacher_remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $subject_id, $exam_term, $marks_obtained, $max_marks, $teacher_remarks]);
            $msg = "Exam grade recorded successfully!";
        } catch (PDOException $e) {
            $err = "Failed to record marks: " . $e->getMessage();
        }
    } else {
        $err = "Please select both student and subject.";
    }
}

$students = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.class_id ASC, s.roll_no ASC")->fetchAll();
$subjects = $db->query("SELECT sub.*, c.class_name FROM subjects sub JOIN classes c ON sub.class_id = c.id ORDER BY sub.class_id ASC")->fetchAll();
$req_subject_id = intval($_GET["subject_id"] ?? 0);
$req_class_id = intval($_GET["class_id"] ?? 0);

$recentResults = $db->query("SELECT r.*, s.student_id as st_code, s.first_name, s.last_name, sub.subject_name, c.class_name FROM results r JOIN students s ON r.student_id = s.id JOIN subjects sub ON r.subject_id = sub.id JOIN classes c ON s.class_id = c.id ORDER BY r.id DESC LIMIT 10")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem;">
  <!-- Mark Entry Form Card -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-pen-to-square"></i> Record Student Marks</h3>
    </div>

    <form method="POST">
      <div class="form-group">
        <label>Select Student *</label>
        <select name="student_id" class="form-control" required>
          <?php foreach ($students as $st): ?>
            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (<?= $st['student_id'] ?> - <?= $st['class_name'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Select Subject *</label>
        <select name="subject_id" class="form-control" required>
          <?php foreach ($subjects as $sb): ?>
            <option value="<?= $sb['id'] ?>" <?= ($req_subject_id == $sb['id']) ? 'selected' : '' ?>><?= htmlspecialchars($sb['subject_name']) ?> (<?= $sb['class_name'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Exam Term *</label>
        <select name="exam_term" class="form-control" required>
          <option value="First Term">First Term</option>
          <option value="Mid Term" selected>Mid Term</option>
          <option value="Final Exam">Final Exam</option>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Marks Obtained *</label>
          <input type="number" name="marks_obtained" step="0.5" class="form-control" placeholder="92.5" required>
        </div>
        <div class="form-group">
          <label>Total Marks *</label>
          <input type="number" name="max_marks" value="100" class="form-control" required>
        </div>
      </div>

      <div class="form-group">
        <label>Teacher Remarks</label>
        <input type="text" name="teacher_remarks" value="Excellent progress" class="form-control">
      </div>

      <button type="submit" name="save_results" class="btn btn-block" style="background: var(--admin-primary); color: #ffffff; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
        <i class="fas fa-save"></i> Save Student Marks
      </button>
    </form>
  </div>

  <!-- Recent Entries Directory -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-list"></i> Recent Recorded Grades</h3>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Class</th>
            <th>Subject</th>
            <th>Term</th>
            <th>Marks</th>
            <th>Grade</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentResults as $rr): ?>
            <?php $gInfo = calculate_grade($rr['marks_obtained'], $rr['max_marks']); ?>
            <tr>
              <td><strong><?= htmlspecialchars($rr['first_name']) ?></strong></td>
              <td><?= htmlspecialchars($rr['class_name']) ?></td>
              <td><?= htmlspecialchars($rr['subject_name']) ?></td>
              <td><span class="badge badge-info"><?= $rr['exam_term'] ?></span></td>
              <td><?= $rr['marks_obtained'] ?> / <?= $rr['max_marks'] ?></td>
              <td><span class="badge <?= $gInfo['class'] ?>"><?= $gInfo['grade'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
