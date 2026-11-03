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

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
  <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Academic Performance & Report Card</h3>
  <button id="printDocumentBtn" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
    <i class="fas fa-print"></i> Print Official Report Card
  </button>
</div>

<!-- Printable Report Card Container -->
<div class="card-box" style="padding: 2.5rem; background: #ffffff;">
  
  <!-- Official Report Card Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double var(--admin-primary); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <img src="../assets/images/logo.svg" alt="AFI Logo" style="height: 75px;">
      <div>
        <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0; font-size: 1.8rem;">AL FATIHA INSTITUTE</h2>
        <span style="color: var(--admin-gold-dark); font-weight: 800; font-size: 0.85rem; letter-spacing: 1px;">CAMBRIDGE CURRICULUM & ISLAMIC ETHOS SCHOOL</span>
        <p style="margin: 0; font-size: 0.82rem; color: var(--admin-text-muted);">Main Road, Cox's Bazar, Bangladesh • ESTD 2025</p>
      </div>
    </div>
    <div style="text-align: right;">
      <span style="background: var(--admin-primary); color: #ffffff; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 800; font-size: 0.9rem; text-transform: uppercase;">
        OFFICIAL REPORT CARD
      </span>
    </div>
  </div>

  <!-- Student Info Box -->
  <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 12px; padding: 1.2rem; margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.92rem;">
    <div>
      <p style="margin-bottom: 0.4rem;"><strong>Student Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
      <p style="margin-bottom: 0.4rem;"><strong>Student ID:</strong> <code><?= htmlspecialchars($student['student_id']) ?></code></p>
      <p style="margin: 0;"><strong>Class & Grade:</strong> <?= htmlspecialchars($student['class_name']) ?></p>
    </div>
    <div>
      <p style="margin-bottom: 0.4rem;"><strong>Roll Number:</strong> #<?= $student['roll_no'] ?></p>
      <p style="margin-bottom: 0.4rem;"><strong>Father's Name:</strong> <?= htmlspecialchars($student['father_name']) ?></p>
      <p style="margin: 0;"><strong>Academic Year:</strong> 2025 - 2026</p>
    </div>
  </div>

  <!-- Marks Table -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Exam Term</th>
          <th>Subject / Curriculum Area</th>
          <th>Max Marks</th>
          <th>Marks Obtained</th>
          <th>Letter Grade</th>
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
              <td><?= $r['max_marks'] ?></td>
              <td><strong style="color: var(--admin-primary);"><?= $r['marks_obtained'] ?></strong></td>
              <td><span class="badge <?= $g['class'] ?>"><?= $g['grade'] ?></span></td>
              <td><strong><?= $g['gpa'] ?></strong></td>
              <td><?= htmlspecialchars($r['teacher_remarks']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center;">No exam grades recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Signatures Block -->
  <div style="margin-top: 4rem; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 1rem;">
    <div style="text-align: center; border-top: 2px solid var(--admin-border); width: 200px; padding-top: 0.4rem; font-weight: 700; font-size: 0.88rem;">
      Class Teacher Signature
    </div>
    <div style="text-align: center; border-top: 2px solid var(--admin-border); width: 200px; padding-top: 0.4rem; font-weight: 700; font-size: 0.88rem;">
      Parent / Guardian Signature
    </div>
    <div style="text-align: center; border-top: 2px solid var(--admin-primary); width: 200px; padding-top: 0.4rem; font-weight: 800; font-size: 0.88rem; color: var(--admin-primary-dark);">
      Principal Seal & Stamp
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
