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
$results = $db->query("SELECT r.*, sub.subject_name FROM results r JOIN subjects sub ON r.subject_id = sub.id WHERE r.student_id = $st_id ORDER BY r.exam_term ASC")->fetchAll();
?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 26px; font-weight: 500; color: var(--admin-navy); margin: 0;">Academic Report Card</h2>
    <p style="margin: 4px 0 0 0; font-size: 13.5px; color: var(--admin-text-muted);">Official term marks and grading record.</p>
  </div>
  <button id="printDocumentBtn" class="btn-welcome-primary" style="cursor: pointer;">
    <i class="fas fa-print"></i> Print Report Card
  </button>
</div>

<!-- Printable Report Card -->
<div class="card-box" style="padding: 28px;">

  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--admin-navy); padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo" style="height: 58px; width: 58px; object-fit: contain;">
      <div>
        <h2 style="font-family: 'Newsreader', Georgia, serif; color: var(--admin-navy); margin: 0; font-size: 24px; font-weight: 500;">AL FATIHA INSTITUTE</h2>
        <span style="font-family: 'IBM Plex Mono', monospace; color: var(--admin-gold-dark); font-size: 12px;">Cambridge Curriculum &amp; Islamic Ethos</span>
        <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--admin-text-muted);">Tekpara Main Road, Cox's Bazar · Est. 2025</p>
      </div>
    </div>
    <span class="badge badge-info" style="font-size: 12px; padding: 6px 12px;">Official Report Card</span>
  </div>

  <!-- Student Info Box -->
  <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13.5px;">
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Student Name:</span> <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></p>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Student ID:</span> <code style="font-family: 'IBM Plex Mono', monospace;"><?= htmlspecialchars($student['student_id']) ?></code></p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Class:</span> <?= htmlspecialchars($student['class_name']) ?></p>
    </div>
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Roll Number:</span> <span style="font-family: 'IBM Plex Mono', monospace;">#<?= $student['roll_no'] ?></span></p>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Father's Name:</span> <?= htmlspecialchars($student['father_name']) ?></p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Academic Year:</span> <span style="font-family: 'IBM Plex Mono', monospace;">2026–2027</span></p>
    </div>
  </div>

  <!-- Marks Table -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Exam Term</th>
          <th>Subject</th>
          <th>Max Marks</th>
          <th>Obtained</th>
          <th>Grade</th>
          <th>GPA</th>
          <th>Teacher Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($results)): ?>
          <?php foreach ($results as $r): ?>
            <?php $g = calculate_grade($r['marks_obtained'], $r['max_marks']); ?>
            <tr>
              <td><span class="badge badge-info"><?= htmlspecialchars($r['exam_term']) ?></span></td>
              <td><strong><?= htmlspecialchars($r['subject_name']) ?></strong></td>
              <td style="font-family: 'IBM Plex Mono', monospace;"><?= $r['max_marks'] ?></td>
              <td style="font-family: 'IBM Plex Mono', monospace;"><strong style="color: var(--admin-navy);"><?= $r['marks_obtained'] ?></strong></td>
              <td><span class="badge <?= $g['class'] ?>"><?= $g['grade'] ?></span></td>
              <td style="font-family: 'IBM Plex Mono', monospace;"><strong><?= $g['gpa'] ?></strong></td>
              <td style="color: var(--admin-text-secondary);"><?= htmlspecialchars($r['teacher_remarks']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center; padding: 32px; color: var(--admin-text-muted);">No exam grades recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Signatures Block -->
  <div style="margin-top: 56px; display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap;">
    <div style="text-align: center; border-top: 1px solid var(--admin-border-strong); width: 190px; padding-top: 6px; font-weight: 500; font-size: 13px; color: var(--admin-text-secondary);">
      Class Teacher Signature
    </div>
    <div style="text-align: center; border-top: 1px solid var(--admin-border-strong); width: 190px; padding-top: 6px; font-weight: 500; font-size: 13px; color: var(--admin-text-secondary);">
      Parent / Guardian Signature
    </div>
    <div style="text-align: center; border-top: 1px solid var(--admin-navy); width: 190px; padding-top: 6px; font-weight: 600; font-size: 13px; color: var(--admin-navy);">
      Principal Seal &amp; Stamp
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
