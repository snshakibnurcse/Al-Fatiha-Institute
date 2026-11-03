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

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, var(--admin-deep-navy) 0%, var(--admin-primary-dark) 100%); color: #ffffff; padding: 2rem; border-radius: 16px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(10, 25, 47, 0.25);">
  <div>
    <span style="color: var(--admin-gold); font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">WELCOME BACK • FACULTY PORTAL</span>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2rem; color: #ffffff; margin: 0.2rem 0; font-weight: 700;"><?= htmlspecialchars(get_current_user_name()) ?></h1>
    <p style="color: rgba(255,255,255,0.85); margin: 0; font-size: 0.95rem;">
      Specialty: <strong><?= htmlspecialchars($teacherData['specialty'] ?? 'Faculty Member') ?></strong> &nbsp;•&nbsp; Teacher ID: <code><?= htmlspecialchars($teacherData['teacher_code'] ?? 'TCH-2025-01') ?></code>
    </p>
  </div>
  <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
    <a href="subjects.php" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.25); padding: 0.75rem 1.3rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
      <i class="fas fa-book-open"></i> My Subjects (<?= count($assignedSubjects) ?>)
    </a>
    <a href="attendance.php" class="btn" style="background: var(--admin-gold); color: var(--admin-deep-navy); padding: 0.75rem 1.4rem; border-radius: 10px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);">
      <i class="fas fa-clipboard-user"></i> Mark Attendance
    </a>
  </div>
</div>

<!-- KPI Stats Grid -->
<div class="stats-grid" style="margin-bottom: 2rem;">
  <div class="kpi-card">
    <div class="kpi-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;"><i class="fas fa-book-bookmark"></i></div>
    <div class="kpi-data">
      <h3><?= count($assignedSubjects) ?></h3>
      <p>My Assigned Subjects (নির্ধারিত বিষয়)</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon gold"><i class="fas fa-school"></i></div>
    <div class="kpi-data">
      <h3><?= $myClassesCount ?> <span style="font-size: 0.9rem; font-weight: 400; color: var(--admin-text-muted);">/ <?= $classesCount ?></span></h3>
      <p>Classes Assigned (পাঠদান ক্লাস)</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-user-graduate"></i></div>
    <div class="kpi-data">
      <h3><?= $totalStudents ?></h3>
      <p>Total Enrolled Students</p>
    </div>
  </div>
</div>

<!-- Assigned Subjects Section -->
<div class="card-box" style="margin-bottom: 2rem; background: #ffffff; border-radius: 14px; border: 1px solid var(--admin-border); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <div class="card-header" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--admin-deep-navy); display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chalkboard-user" style="color: var(--admin-primary);"></i> My Assigned Subjects & Classes (আমার নির্ধারিত বিষয় ও ক্লাসসমূহ)
      </h3>
      <p style="margin: 0.2rem 0 0 0; font-size: 0.85rem; color: var(--admin-text-muted);">
        Principal has allocated the following subjects to you for instruction and marks grading.
      </p>
    </div>
    <div>
      <a href="subjects.php" style="color: var(--admin-primary); font-weight: 700; font-size: 0.88rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;">
        View Detailed Syllabus <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </div>

  <?php if (empty($assignedSubjects)): ?>
    <div style="padding: 3rem 1.5rem; text-align: center; color: var(--admin-text-muted);">
      <div style="width: 70px; height: 70px; border-radius: 50%; background: #f8fafc; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; color: #cbd5e1; margin-bottom: 1rem;">
        <i class="fas fa-book-open"></i>
      </div>
      <h4 style="color: var(--admin-deep-navy); font-size: 1.15rem; font-weight: 700; margin-bottom: 0.4rem;">No Subjects Assigned Yet</h4>
      <p style="max-width: 420px; margin: 0 auto; font-size: 0.9rem;">
        Your teaching subjects have not been configured yet for this session. The Principal will assign subjects from <strong>Admin Panel &gt; Subjects &amp; Teacher Assign</strong>.
      </p>
    </div>
  <?php else: ?>
    <div style="padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
      <?php foreach ($assignedSubjects as $sub): ?>
        <div style="background: #ffffff; border: 1px solid var(--admin-border); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.02); transition: all 0.2s;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
              <span class="badge badge-info" style="font-size: 0.78rem; font-weight: 800; padding: 0.3rem 0.65rem; border-radius: 6px;">
                <i class="fas fa-school" style="margin-right: 3px;"></i> <?= htmlspecialchars($sub['class_name']) ?>
              </span>
              <code style="background: #f1f5f9; color: #334155; font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 5px;">
                <?= htmlspecialchars($sub['subject_code']) ?>
              </code>
            </div>

            <h4 style="margin: 0.3rem 0 0.5rem 0; font-size: 1.1rem; font-weight: 700; color: var(--admin-deep-navy);">
              <?= htmlspecialchars($sub['subject_name']) ?>
            </h4>

            <div style="display: flex; gap: 0.8rem; font-size: 0.82rem; color: var(--admin-text-muted); margin-bottom: 0.8rem; flex-wrap: wrap;">
              <span><i class="fas fa-door-open"></i> Room: <strong><?= htmlspecialchars($sub['room_no'] ?? 'TBA') ?></strong></span>
              <span><i class="fas fa-user-graduate"></i> <strong><?= $sub['student_count'] ?></strong> Enrolled</span>
              <span><i class="fas fa-award"></i> <strong><?= $sub['total_marks'] ?></strong> Marks (Pass: <?= $sub['pass_marks'] ?>)</span>
            </div>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; border-top: 1px dashed #e2e8f0; padding-top: 0.75rem;">
            <a href="results.php?class_id=<?= $sub['class_id'] ?>&subject_id=<?= $sub['id'] ?>" class="btn" style="flex: 1; background: var(--admin-primary); color: #fff; padding: 0.45rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.82rem; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem;">
              <i class="fas fa-pen-to-square"></i> Input Marks
            </a>
            <a href="attendance.php?class_id=<?= $sub['class_id'] ?>" class="btn" style="background: #f1f5f9; color: #334155; padding: 0.45rem 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.82rem; text-decoration: none; text-align: center; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem;">
              <i class="fas fa-clipboard-check"></i> Attendance
            </a>
            <a href="students.php?class_id=<?= $sub['class_id'] ?>" style="background: #ffffff; color: #64748b; padding: 0.45rem 0.6rem; border-radius: 6px; font-size: 0.82rem; text-decoration: none; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; justify-content: center;" title="View Students">
              <i class="fas fa-users"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Shortcuts Section -->
<div class="card-box" style="background: #ffffff; border-radius: 14px; border: 1px solid var(--admin-border); padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <div class="card-header" style="border-bottom: 1px solid var(--admin-border); padding-bottom: 1rem; margin-bottom: 1.25rem;">
    <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--admin-deep-navy);"><i class="fas fa-rocket"></i> Teacher Quick Shortcuts</h3>
  </div>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.2rem;">
    <a href="subjects.php" style="background: #f8fafc; padding: 1.4rem; border-radius: 12px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border); transition: all 0.2s;">
      <i class="fas fa-book-open" style="font-size: 1.8rem; color: #2563eb; margin-bottom: 0.75rem; display: block;"></i>
      <strong>My Teaching Subjects</strong>
      <p style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 0.3rem;">View assigned syllabus, course materials & class lists.</p>
    </a>

    <a href="attendance.php" style="background: #f8fafc; padding: 1.4rem; border-radius: 12px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border); transition: all 0.2s;">
      <i class="fas fa-clipboard-check" style="font-size: 1.8rem; color: var(--admin-primary); margin-bottom: 0.75rem; display: block;"></i>
      <strong>Daily Class Attendance</strong>
      <p style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 0.3rem;">Mark present/absent for assigned class students.</p>
    </a>

    <a href="results.php" style="background: #f8fafc; padding: 1.4rem; border-radius: 12px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border); transition: all 0.2s;">
      <i class="fas fa-pen-to-square" style="font-size: 1.8rem; color: var(--admin-gold-dark); margin-bottom: 0.75rem; display: block;"></i>
      <strong>Input Exam Marks & Grades</strong>
      <p style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 0.3rem;">Record student exam marks & generate report cards.</p>
    </a>

    <a href="students.php" style="background: #f8fafc; padding: 1.4rem; border-radius: 12px; text-decoration: none; color: var(--admin-text); border: 1px solid var(--admin-border); transition: all 0.2s;">
      <i class="fas fa-list-check" style="font-size: 1.8rem; color: #10b981; margin-bottom: 0.75rem; display: block;"></i>
      <strong>View Class Roster</strong>
      <p style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 0.3rem;">Access student roll list and parent contact details.</p>
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>