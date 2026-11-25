<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// Fetch Teacher Details
$tchStmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tchStmt->execute([$user_id]);
$teacherData = $tchStmt->fetch();

// Fallback for admin or unlinked
if (!$teacherData) {
    $teacherData = $db->query("SELECT * FROM teachers ORDER BY id ASC LIMIT 1")->fetch();
}
$teacher_id = $teacherData['id'] ?? 0;

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$classesCount = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Fetch subjects assigned to this teacher
$subStmt = $db->prepare("
    SELECT s.*, c.class_name, c.room_no, c.section,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as student_count
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ?
    ORDER BY c.id ASC, s.subject_name ASC
");
$subStmt->execute([$teacher_id]);
$assignedSubjects = $subStmt->fetchAll();

// Count distinct classes taught
$classesTaughtCount = $db->prepare("SELECT COUNT(DISTINCT class_id) FROM subjects WHERE teacher_id = ?");
$classesTaughtCount->execute([$teacher_id]);
$myClassesCount = $classesTaughtCount->fetchColumn();
?>

<!-- Teacher Overview Header -->
<div class="dash-welcome-card">
  <div>
    <div class="welcome-meta-row">
      <span class="date-badge"><?= date('D, d M Y') ?></span>
      <span class="role-pill-tag"><?= htmlspecialchars($teacherData['teacher_code'] ?? 'Faculty') ?></span>
    </div>
    <h1 class="welcome-title"><?= htmlspecialchars(get_current_user_name()) ?></h1>
    <p class="welcome-sub">
      <?= htmlspecialchars($teacherData['specialty'] ?? 'Faculty Member') ?> · AL FATIHA INSTITUTE
    </p>
  </div>
  <div class="welcome-actions">
    <a href="attendance.php" class="btn-welcome-primary">
      <i class="fas fa-clipboard-user"></i> Mark Attendance
    </a>
    <a href="subjects.php" class="btn-welcome-secondary">
      My Subjects (<?= count($assignedSubjects) ?>)
    </a>
  </div>
</div>

<!-- KPI Stats Grid -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= count($assignedSubjects) ?></h3>
      <p>Assigned subjects</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-book-bookmark"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= $myClassesCount ?> <span style="font-size: 14px; font-weight: 400; color: var(--admin-text-muted);">/ <?= $classesCount ?></span></h3>
      <p>Classes assigned</p>
    </div>
    <div class="kpi-icon gold"><i class="fas fa-school"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= $totalStudents ?></h3>
      <p>Total enrolled students</p>
    </div>
    <div class="kpi-icon green"><i class="fas fa-user-graduate"></i></div>
  </div>
</div>

<!-- Assigned Subjects Section -->
<div class="card-box">
  <div class="card-header">
    <div>
      <h3 class="card-title">My Assigned Subjects &amp; Classes</h3>
      <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--admin-text-muted);">
        Subjects allocated to your account for classroom instruction and grading.
      </p>
    </div>
    <a href="subjects.php" class="btn-welcome-secondary" style="height: 32px; padding: 0 12px; font-size: 13px;">
      View all &rarr;
    </a>
  </div>

  <?php if (empty($assignedSubjects)): ?>
    <div style="padding: 40px 20px; text-align: center; color: var(--admin-text-muted);">
      <h4 style="color: var(--admin-navy); font-size: 16px; font-weight: 600; margin-bottom: 6px;">No Subjects Assigned Yet</h4>
      <p style="max-width: 420px; margin: 0 auto; font-size: 14px;">
        The Principal will allocate your subjects from Admin Panel &gt; Subjects &amp; Teacher Assign.
      </p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
      <?php foreach ($assignedSubjects as $sub): ?>
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; gap: 16px;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="badge badge-info"><?= htmlspecialchars($sub['class_name']) ?></span>
              <code style="font-family: 'IBM Plex Mono', monospace; color: var(--admin-text-secondary); font-size: 12px;">
                <?= htmlspecialchars($sub['subject_code']) ?>
              </code>
            </div>

            <h4 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: var(--admin-navy);">
              <?= htmlspecialchars($sub['subject_name']) ?>
            </h4>

            <div style="display: flex; gap: 12px; font-size: 13px; color: var(--admin-text-secondary); flex-wrap: wrap;">
              <span>Room <?= htmlspecialchars($sub['room_no'] ?? 'TBA') ?></span>
              <span>·</span>
              <span><?= $sub['student_count'] ?> students</span>
              <span>·</span>
              <span style="font-family: 'IBM Plex Mono', monospace; font-size: 12px;"><?= $sub['total_marks'] ?> marks (pass <?= $sub['pass_marks'] ?>)</span>
            </div>
          </div>

          <div style="display: flex; gap: 8px; padding-top: 12px; border-top: 1px solid var(--admin-border);">
            <a href="results.php?class_id=<?= $sub['class_id'] ?>&subject_id=<?= $sub['id'] ?>" class="btn-welcome-primary" style="flex: 1; justify-content: center; height: 34px; font-size: 13px;">
              Input Marks
            </a>
            <a href="attendance.php?class_id=<?= $sub['class_id'] ?>" class="btn-welcome-secondary" style="height: 34px; padding: 0 12px; font-size: 13px;">
              Attendance
            </a>
            <a href="students.php?class_id=<?= $sub['class_id'] ?>" class="btn-welcome-secondary" style="height: 34px; padding: 0 10px;" title="View Students">
              <i class="fas fa-users"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Shortcuts Section -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title">Quick Shortcuts</h3>
  </div>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px;">
    <a href="subjects.php" style="background: var(--admin-bg); padding: 16px; border-radius: 10px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border);">
      <strong style="display: block; font-size: 14px; font-weight: 600; color: var(--admin-navy); margin-bottom: 4px;">My Teaching Subjects</strong>
      <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">Assigned syllabus, evaluation weights, and class lists.</p>
    </a>

    <a href="attendance.php" style="background: var(--admin-bg); padding: 16px; border-radius: 10px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border);">
      <strong style="display: block; font-size: 14px; font-weight: 600; color: var(--admin-navy); margin-bottom: 4px;">Daily Class Attendance</strong>
      <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">Mark Present, Absent, or Late for assigned classes.</p>
    </a>

    <a href="results.php" style="background: var(--admin-bg); padding: 16px; border-radius: 10px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border);">
      <strong style="display: block; font-size: 14px; font-weight: 600; color: var(--admin-navy); margin-bottom: 4px;">Input Exam Marks &amp; Grades</strong>
      <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">Record exam scores for your allocated subjects.</p>
    </a>

    <a href="students.php" style="background: var(--admin-bg); padding: 16px; border-radius: 10px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border);">
      <strong style="display: block; font-size: 14px; font-weight: 600; color: var(--admin-navy); margin-bottom: 4px;">Class Roster</strong>
      <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">View roll numbers and guardian contact details.</p>
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
