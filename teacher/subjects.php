<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// Identify logged in teacher
$teacherData = null;
if ($user_role === 'teacher') {
    $tchStmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
    $tchStmt->execute([$user_id]);
    $teacherData = $tchStmt->fetch();
}

// Fallback for admin previewing or unlinked teacher
if (!$teacherData) {
    $tid = intval($_GET['teacher_id'] ?? 1);
    $tchStmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
    $tchStmt->execute([$tid]);
    $teacherData = $tchStmt->fetch();
    if (!$teacherData) {
        $teacherData = $db->query("SELECT * FROM teachers ORDER BY id ASC LIMIT 1")->fetch();
    }
}

$teacher_id = $teacherData['id'] ?? 0;

// Filter options
$filter_class_id = intval($_GET['class_id'] ?? 0);
$search = trim(sanitize($_GET['search'] ?? ''));

// Fetch all classes taught by this teacher (for filter dropdown)
$myClassesStmt = $db->prepare("
    SELECT DISTINCT c.id, c.class_name, c.room_no 
    FROM classes c 
    JOIN subjects s ON s.class_id = c.id 
    WHERE s.teacher_id = ? 
    ORDER BY c.id ASC
");
$myClassesStmt->execute([$teacher_id]);
$myClasses = $myClassesStmt->fetchAll();

// Fetch assigned subjects
$sql = "
    SELECT s.*, c.class_name, c.room_no, c.section,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as student_count,
           (SELECT COUNT(*) FROM results r WHERE r.subject_id = s.id) as graded_count
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ?
";
$params = [$teacher_id];

if ($filter_class_id > 0) {
    $sql .= " AND s.class_id = ?";
    $params[] = $filter_class_id;
}

if (!empty($search)) {
    $sql .= " AND (s.subject_name LIKE ? OR s.subject_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY c.id ASC, s.subject_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assignedSubjects = $stmt->fetchAll();

// Total statistics for this teacher
$allMySubjectsCount = $db->prepare("SELECT COUNT(*) FROM subjects WHERE teacher_id = ?");
$allMySubjectsCount->execute([$teacher_id]);
$totalAssignedCount = $allMySubjectsCount->fetchColumn();

$totalStudentsTaught = $db->prepare("
    SELECT COUNT(DISTINCT st.id) 
    FROM students st 
    JOIN subjects s ON st.class_id = s.class_id 
    WHERE s.teacher_id = ? AND st.status = 'Active'
");
$totalStudentsTaught->execute([$teacher_id]);
$studentsTaughtCount = $totalStudentsTaught->fetchColumn();
?>

<!-- Header Banner -->
<div class="dash-welcome-card">
  <div>
    <div class="welcome-meta-row">
      <span class="date-badge">My Assigned Subjects</span>
      <span class="role-pill-tag"><?= htmlspecialchars($teacherData['teacher_code'] ?? 'TCH') ?></span>
    </div>
    <h1 class="welcome-title"><?= htmlspecialchars($teacherData['full_name']) ?></h1>
    <p class="welcome-sub">
      <?= htmlspecialchars($teacherData['specialty'] ?? 'Faculty Member') ?> · Allocated Curriculum
    </p>
  </div>
  <div class="welcome-actions">
    <a href="results.php" class="btn-welcome-primary">
      <i class="fas fa-pen-to-square"></i> Input Marks &amp; Grades
    </a>
    <a href="attendance.php" class="btn-welcome-secondary">
      Daily Attendance
    </a>
  </div>
</div>

<!-- KPI Stats Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= $totalAssignedCount ?></h3>
      <p>Assigned subjects</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-book-bookmark"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= count($myClasses) ?></h3>
      <p>Classes taught</p>
    </div>
    <div class="kpi-icon gold"><i class="fas fa-chalkboard"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= $studentsTaughtCount ?></h3>
      <p>Students under guidance</p>
    </div>
    <div class="kpi-icon green"><i class="fas fa-user-graduate"></i></div>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card-box">
  <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 220px;">
      <label style="display: block; font-size: 13px; font-weight: 500; color: var(--admin-text); margin-bottom: 6px;">
        Search Subject Name or Code
      </label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Subject name or code" class="form-control">
    </div>

    <div style="flex: 1.5; min-width: 180px;">
      <label style="display: block; font-size: 13px; font-weight: 500; color: var(--admin-text); margin-bottom: 6px;">
        Filter by Class
      </label>
      <select name="class_id" class="form-control">
        <option value="0">All My Classes</option>
        <?php foreach ($myClasses as $mc): ?>
          <option value="<?= $mc['id'] ?>" <?= ($filter_class_id == $mc['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($mc['class_name']) ?> (Room <?= htmlspecialchars($mc['room_no'] ?? 'N/A') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display: flex; gap: 8px;">
      <button type="submit" class="btn-welcome-primary" style="height: 40px;">
        Filter
      </button>
      <?php if (!empty($search) || $filter_class_id > 0): ?>
        <a href="subjects.php" class="btn-welcome-secondary" style="height: 40px;">
          Reset
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Subjects List -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title">Assigned Subjects Roster</h3>
    <span class="badge badge-info"><?= count($assignedSubjects) ?> Subjects</span>
  </div>

  <?php if (empty($assignedSubjects)): ?>
    <div style="padding: 40px 20px; text-align: center; color: var(--admin-text-muted);">
      <h3 style="color: var(--admin-navy); font-size: 18px; font-weight: 600; margin-bottom: 6px;">No Subjects Found</h3>
      <p style="max-width: 440px; margin: 0 auto 16px auto; font-size: 14px;">
        <?= (!empty($search) || $filter_class_id > 0) ? 'No subjects matched your selected filter.' : 'No subjects have been assigned to you yet for this session.' ?>
      </p>
      <?php if (!empty($search) || $filter_class_id > 0): ?>
        <a href="subjects.php" class="btn-welcome-primary">View All My Subjects</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px;">
      <?php foreach ($assignedSubjects as $sub): ?>
        <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;">
          <div style="padding: 16px 20px; border-bottom: 1px solid var(--admin-border); background: var(--admin-card);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
              <span class="badge badge-info"><?= htmlspecialchars($sub['class_name']) ?></span>
              <code style="font-family: 'IBM Plex Mono', monospace; color: var(--admin-text-secondary); font-size: 12px;">
                <?= htmlspecialchars($sub['subject_code']) ?>
              </code>
            </div>

            <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 600; color: var(--admin-navy);">
              <?= htmlspecialchars($sub['subject_name']) ?>
            </h3>

            <div style="display: flex; gap: 12px; font-size: 13px; color: var(--admin-text-muted); flex-wrap: wrap;">
              <span>Room <?= htmlspecialchars($sub['room_no'] ?? 'TBA') ?></span>
              <span>·</span>
              <span><?= $sub['student_count'] ?> enrolled students</span>
            </div>
          </div>

          <div style="padding: 16px 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 16px;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; background: var(--admin-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--admin-border);">
                <div>
                  <span style="display: block; font-size: 12px; color: var(--admin-text-muted);">Total evaluation</span>
                  <strong style="color: var(--admin-navy); font-size: 15px; font-variant-numeric: tabular-nums;"><?= $sub['total_marks'] ?> marks</strong>
                </div>
                <div style="text-align: right;">
                  <span style="display: block; font-size: 12px; color: var(--admin-text-muted);">Pass mark</span>
                  <strong style="color: var(--admin-positive); font-size: 15px; font-variant-numeric: tabular-nums;"><?= $sub['pass_marks'] ?> marks</strong>
                </div>
              </div>

              <?php if (!empty($sub['description'])): ?>
                <div style="font-size: 13px; color: var(--admin-text-secondary); margin-top: 12px; line-height: 1.5; background: #fbf4e3; padding: 10px 12px; border-radius: 8px; border-left: 3px solid var(--admin-gold);">
                  <?= nl2br(htmlspecialchars($sub['description'])) ?>
                </div>
              <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
              <a href="results.php?class_id=<?= $sub['class_id'] ?>&subject_id=<?= $sub['id'] ?>" class="btn-welcome-primary" style="justify-content: center; height: 36px; font-size: 13px;">
                Input Marks
              </a>

              <a href="attendance.php?class_id=<?= $sub['class_id'] ?>" class="btn-welcome-secondary" style="justify-content: center; height: 36px; font-size: 13px;">
                Attendance
              </a>

              <a href="students.php?class_id=<?= $sub['class_id'] ?>" class="btn-welcome-secondary" style="grid-column: span 2; justify-content: center; height: 34px; font-size: 12.5px;">
                View Class Roster (<?= $sub['student_count'] ?>)
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
