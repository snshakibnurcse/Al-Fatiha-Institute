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
$totalMySubjects = count($assignedSubjects);
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
<div style="background: linear-gradient(135deg, var(--admin-deep-navy) 0%, #1e3a8a 100%); color: #ffffff; padding: 2.2rem; border-radius: 16px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(10, 25, 47, 0.3);">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(217, 119, 6, 0.2); border: 1px solid rgba(217, 119, 6, 0.4); padding: 0.25rem 0.75rem; border-radius: 20px; margin-bottom: 0.6rem;">
      <i class="fas fa-book-open" style="color: var(--admin-gold); font-size: 0.85rem;"></i>
      <span style="color: var(--admin-gold); font-size: 0.82rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">MY ASSIGNED SUBJECTS & CURRICULUM</span>
    </div>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; color: #ffffff; margin: 0.2rem 0; font-weight: 700;">
      <?= htmlspecialchars($teacherData['full_name']) ?>
    </h1>
    <p style="color: rgba(255,255,255,0.85); margin: 0; font-size: 0.95rem;">
      Specialty: <strong><?= htmlspecialchars($teacherData['specialty'] ?? 'Faculty Member') ?></strong> &nbsp;•&nbsp; 
      Teacher ID: <code><?= htmlspecialchars($teacherData['teacher_code'] ?? 'TCH') ?></code>
    </p>
  </div>
  <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
    <a href="results.php" class="btn" style="background: var(--admin-gold); color: var(--admin-deep-navy); padding: 0.8rem 1.4rem; border-radius: 10px; font-weight: 800; text-decoration: none; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); display: inline-flex; align-items: center; gap: 0.5rem;">
      <i class="fas fa-pen-to-square"></i> Input Marks & Grades
    </a>
    <a href="attendance.php" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.25); padding: 0.8rem 1.4rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
      <i class="fas fa-clipboard-user"></i> Daily Attendance
    </a>
  </div>
</div>

<!-- KPI Stats Cards -->
<div class="stats-grid" style="margin-bottom: 2rem;">
  <div class="kpi-card">
    <div class="kpi-icon" style="background: rgba(59, 130, 246, 0.1); color: #2563eb;"><i class="fas fa-book-bookmark"></i></div>
    <div class="kpi-data">
      <h3><?= $totalAssignedCount ?></h3>
      <p>Assigned Subjects</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon gold"><i class="fas fa-chalkboard"></i></div>
    <div class="kpi-data">
      <h3><?= count($myClasses) ?></h3>
      <p>Classes Taught</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-user-graduate"></i></div>
    <div class="kpi-data">
      <h3><?= $studentsTaughtCount ?></h3>
      <p>Students Under My Guidance</p>
    </div>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card-box" style="margin-bottom: 2rem; background: #ffffff; border-radius: 14px; border: 1px solid var(--admin-border); padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 220px;">
      <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--admin-text-muted); margin-bottom: 0.4rem;">
        <i class="fas fa-search"></i> Search Subject Name or Code
      </label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. English, Arabic, ENG-P1..." class="form-control" style="width: 100%; border-radius: 8px;">
    </div>

    <div style="flex: 1.5; min-width: 180px;">
      <label style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--admin-text-muted); margin-bottom: 0.4rem;">
        <i class="fas fa-filter"></i> Filter by Class
      </label>
      <select name="class_id" class="form-control" style="width: 100%; border-radius: 8px;">
        <option value="0">All My Classes</option>
        <?php foreach ($myClasses as $mc): ?>
          <option value="<?= $mc['id'] ?>" <?= ($filter_class_id == $mc['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($mc['class_name']) ?> (Room <?= htmlspecialchars($mc['room_no'] ?? 'N/A') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <button type="submit" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
        <i class="fas fa-filter"></i> Filter
      </button>
      <?php if (!empty($search) || $filter_class_id > 0): ?>
        <a href="subjects.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.65rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
          <i class="fas fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Subjects List -->
<div class="card-box" style="background: #ffffff; border-radius: 14px; border: 1px solid var(--admin-border); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <div class="card-header" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--admin-deep-navy); display: flex; align-items: center; gap: 0.6rem;">
      <i class="fas fa-graduation-cap" style="color: var(--admin-primary);"></i> Assigned Subjects Roster
      <span class="badge badge-info" style="font-size: 0.8rem;"><?= count($assignedSubjects) ?> Subjects</span>
    </h3>
  </div>

  <?php if (empty($assignedSubjects)): ?>
    <div style="padding: 3.5rem 1.5rem; text-align: center; color: var(--admin-text-muted);">
      <div style="width: 80px; height: 80px; border-radius: 50%; background: #f8fafc; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #cbd5e1; margin-bottom: 1.2rem;">
        <i class="fas fa-book"></i>
      </div>
      <h3 style="color: var(--admin-deep-navy); font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">No Subjects Found</h3>
      <p style="max-width: 450px; margin: 0 auto 1.5rem auto; font-size: 0.95rem; line-height: 1.5;">
        <?= (!empty($search) || $filter_class_id > 0) ? 'No subjects matched your selected filter. Try clearing filters.' : 'You have not been assigned any subjects yet for this academic session. The Principal will allocate your subjects from the Admin Panel.' ?>
      </p>
      <?php if (!empty($search) || $filter_class_id > 0): ?>
        <a href="subjects.php" class="btn" style="background: var(--admin-primary); color: #fff; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 700;">
          <i class="fas fa-rotate-left"></i> View All My Subjects
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <!-- Subject Cards Grid -->
    <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem;">
      <?php foreach ($assignedSubjects as $sub): ?>
        <div style="background: #ffffff; border: 1px solid var(--admin-border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;">
          <!-- Card Top Bar -->
          <div style="padding: 1.25rem 1.25rem 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; background: #fafbfc;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;">
              <span class="badge badge-info" style="font-weight: 800; font-size: 0.8rem; padding: 0.35rem 0.75rem; border-radius: 6px;">
                <i class="fas fa-school" style="margin-right: 4px;"></i> <?= htmlspecialchars($sub['class_name']) ?>
              </span>
              <code style="background: #e2e8f0; color: #334155; padding: 0.25rem 0.55rem; border-radius: 6px; font-weight: 700; font-size: 0.82rem;">
                <?= htmlspecialchars($sub['subject_code']) ?>
              </code>
            </div>

            <h3 style="margin: 0.4rem 0 0.2rem 0; font-size: 1.2rem; font-weight: 700; color: var(--admin-deep-navy); line-height: 1.35;">
              <?= htmlspecialchars($sub['subject_name']) ?>
            </h3>

            <div style="display: flex; gap: 1rem; font-size: 0.82rem; color: var(--admin-text-muted); margin-top: 0.5rem; flex-wrap: wrap;">
              <span><i class="fas fa-door-open" style="color: #64748b;"></i> Room: <strong><?= htmlspecialchars($sub['room_no'] ?? 'TBA') ?></strong></span>
              <span><i class="fas fa-users" style="color: #64748b;"></i> <strong><?= $sub['student_count'] ?></strong> Enrolled Students</span>
            </div>
          </div>

          <!-- Card Body -->
          <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.9rem; border: 1px solid #e2e8f0;">
                <div>
                  <span style="display: block; font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Evaluation</span>
                  <strong style="color: var(--admin-deep-navy); font-size: 1.05rem;"><?= $sub['total_marks'] ?> Marks</strong>
                </div>
                <div style="text-align: right;">
                  <span style="display: block; font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Pass Mark</span>
                  <strong style="color: #10b981; font-size: 1.05rem;"><?= $sub['pass_marks'] ?> Marks</strong>
                </div>
              </div>

              <?php if (!empty($sub['description'])): ?>
                <div style="font-size: 0.85rem; color: #475569; margin-bottom: 1rem; line-height: 1.5; background: #fffbeb; padding: 0.6rem 0.8rem; border-radius: 6px; border-left: 3px solid var(--admin-gold);">
                  <i class="fas fa-circle-info" style="color: var(--admin-gold); margin-right: 4px;"></i>
                  <?= nl2br(htmlspecialchars($sub['description'])) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Card Actions -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-top: 0.8rem;">
              <a href="results.php?class_id=<?= $sub['class_id'] ?>&subject_id=<?= $sub['id'] ?>" class="btn" style="background: var(--admin-primary); color: #fff; padding: 0.55rem 0.8rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <i class="fas fa-pen-to-square"></i> Input Marks
              </a>

              <a href="attendance.php?class_id=<?= $sub['class_id'] ?>" class="btn" style="background: #f1f5f9; color: #334155; padding: 0.55rem 0.8rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; border: 1px solid #cbd5e1;">
                <i class="fas fa-clipboard-check"></i> Attendance
              </a>

              <a href="students.php?class_id=<?= $sub['class_id'] ?>" style="grid-column: span 2; background: #ffffff; color: #64748b; padding: 0.45rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.82rem; text-decoration: none; text-align: center; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <i class="fas fa-user-graduate"></i> View Class Roster (<?= $sub['student_count'] ?> Students)
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>