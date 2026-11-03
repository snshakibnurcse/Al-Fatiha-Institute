<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// ========================================================
// 1. POST HANDLER: ADD NEW SUBJECT
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_subject'])) {
    $subject_name = sanitize($_POST['subject_name'] ?? '');
    $subject_code = strtoupper(trim(sanitize($_POST['subject_code'] ?? '')));
    $class_id = intval($_POST['class_id'] ?? 0);
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
    $total_marks = intval($_POST['total_marks'] ?? 100);
    $pass_marks = intval($_POST['pass_marks'] ?? 40);
    $description = sanitize($_POST['description'] ?? '');

    if ($class_id > 0 && !empty($subject_name)) {
        // Auto-generate code if empty
        if (empty($subject_code)) {
            $words = explode(' ', $subject_name);
            $prefix = strtoupper(substr($words[0], 0, 3));
            $subject_code = $prefix . '-C' . $class_id;
        }

        try {
            $ins = $db->prepare("
                INSERT INTO subjects (subject_code, subject_name, class_id, teacher_id, total_marks, pass_marks, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$subject_code, $subject_name, $class_id, $teacher_id, $total_marks, $pass_marks, $description]);

            $className = $db->query("SELECT class_name FROM classes WHERE id = $class_id")->fetchColumn();
            $teacherName = $teacher_id ? $db->query("SELECT full_name FROM teachers WHERE id = $teacher_id")->fetchColumn() : null;

            $msg = "Subject '<strong>" . htmlspecialchars($subject_name) . "</strong>' added to Class <strong>" . htmlspecialchars($className) . "</strong>" . ($teacherName ? " and assigned to <strong>" . htmlspecialchars($teacherName) . "</strong>!" : "!");
        } catch (Exception $e) {
            $err = "Error adding subject: " . $e->getMessage();
        }
    } else {
        $err = "Subject name and target class are required fields.";
    }
}

// ========================================================
// 2. POST HANDLER: UPDATE SUBJECT
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_subject'])) {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $subject_name = sanitize($_POST['subject_name'] ?? '');
    $subject_code = strtoupper(trim(sanitize($_POST['subject_code'] ?? '')));
    $class_id = intval($_POST['class_id'] ?? 0);
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
    $total_marks = intval($_POST['total_marks'] ?? 100);
    $pass_marks = intval($_POST['pass_marks'] ?? 40);
    $description = sanitize($_POST['description'] ?? '');

    if ($subject_id > 0 && $class_id > 0 && !empty($subject_name)) {
        try {
            $upd = $db->prepare("
                UPDATE subjects 
                SET subject_code = ?, subject_name = ?, class_id = ?, teacher_id = ?, total_marks = ?, pass_marks = ?, description = ? 
                WHERE id = ?
            ");
            $upd->execute([$subject_code, $subject_name, $class_id, $teacher_id, $total_marks, $pass_marks, $description, $subject_id]);
            $msg = "Subject '<strong>" . htmlspecialchars($subject_name) . "</strong>' updated successfully!";
        } catch (Exception $e) {
            $err = "Error updating subject: " . $e->getMessage();
        }
    } else {
        $err = "Subject name and class are required.";
    }
}

// ========================================================
// 3. POST HANDLER: QUICK ASSIGN TEACHER
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign_teacher'])) {
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

    if ($subject_id > 0) {
        $db->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?")->execute([$teacher_id, $subject_id]);
        $subName = $db->query("SELECT subject_name FROM subjects WHERE id = $subject_id")->fetchColumn();
        $tchName = $teacher_id ? $db->query("SELECT full_name FROM teachers WHERE id = $teacher_id")->fetchColumn() : 'Unassigned';

        $msg = "Subject '<strong>" . htmlspecialchars($subName) . "</strong>' successfully assigned to <strong>" . htmlspecialchars($tchName) . "</strong>!";
    }
}

// ========================================================
// 4. GET HANDLER: DELETE SUBJECT
// ========================================================
if (isset($_GET['delete_subject_id'])) {
    $delId = intval($_GET['delete_subject_id']);
    // Check if marks exist
    $marksCount = $db->query("SELECT COUNT(*) FROM results WHERE subject_id = $delId")->fetchColumn();
    if ($marksCount > 0) {
        $err = "Cannot delete subject: <strong>$marksCount</strong> student examination result records are recorded for this subject.";
    } else {
        $subName = $db->query("SELECT subject_name FROM subjects WHERE id = $delId")->fetchColumn();
        $db->prepare("DELETE FROM subjects WHERE id = ?")->execute([$delId]);
        $msg = "Subject '<strong>" . htmlspecialchars($subName) . "</strong>' deleted successfully.";
    }
}

// ========================================================
// DATA QUERY & FILTERS
// ========================================================
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_teacher_id = sanitize($_GET['teacher_id'] ?? 'all');
$search = sanitize($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if ($selected_class_id > 0) {
    $where[] = "s.class_id = :cid";
    $params[':cid'] = $selected_class_id;
}

if ($selected_teacher_id !== 'all') {
    if ($selected_teacher_id === 'unassigned') {
        $where[] = "(s.teacher_id IS NULL OR s.teacher_id = 0)";
    } else {
        $where[] = "s.teacher_id = :tid";
        $params[':tid'] = intval($selected_teacher_id);
    }
}

if (!empty($search)) {
    $where[] = "(s.subject_name LIKE :q OR s.subject_code LIKE :q OR c.class_name LIKE :q OR t.full_name LIKE :q)";
    $params[':q'] = "%$search%";
}

$sql = "
    SELECT s.*, 
           c.class_name, 
           c.room_no,
           t.full_name as teacher_name, 
           t.teacher_code, 
           t.specialty as teacher_specialty,
           COUNT(st.id) as student_count
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN students st ON st.class_id = c.id AND st.status = 'Active'
    WHERE " . implode(' AND ', $where) . "
    GROUP BY s.id
    ORDER BY c.id ASC, s.subject_name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$subjectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// General Stats
$totalSubjects = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$totalClasses = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalAssigned = $db->query("SELECT COUNT(*) FROM subjects WHERE teacher_id IS NOT NULL AND teacher_id > 0")->fetchColumn();
$totalUnassigned = $totalSubjects - $totalAssigned;

$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $db->query("SELECT * FROM teachers ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($msg): ?>
  <div class="alert-msg alert-success" style="background: #dcfce7; border: 1.5px solid #86efac; color: #166534; padding: 1rem 1.4rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px;">
    <i class="fas fa-check-circle" style="font-size: 1.3rem;"></i> <div><?= $msg ?></div>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert-msg alert-danger" style="background: #fee2e2; border: 1.5px solid #fca5a5; color: #991b1b; padding: 1rem 1.4rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px;">
    <i class="fas fa-exclamation-triangle" style="font-size: 1.3rem;"></i> <div><?= $err ?></div>
  </div>
<?php endif; ?>

<!-- ========================================================
     HEADER BANNER & QUICK ACTIONS
     ======================================================== -->
<div class="subject-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 1.8rem;">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;">
      <i class="fas fa-book-open text-primary"></i>
      <span>ACADEMIC CURRICULUM &bull; SUBJECTS &amp; TEACHER ALLOCATION</span>
    </div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0; font-size: 1.85rem; font-weight: 800;">
      Class Subjects &amp; Teacher Assignment
    </h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.95rem;">
      Add subjects by class, set syllabus grading schemes, and assign specialized educators.
    </p>
  </div>

  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" style="background: #2563eb; color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);" onclick="openModal('addSubjectModal')">
      <i class="fas fa-plus-circle"></i> Add New Subject
    </button>
    <a href="classes.php" class="btn" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #1e293b; padding: 0.65rem 1.1rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
      <i class="fas fa-school"></i> Classes &amp; Fees
    </a>
    <button class="btn btn-outline" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #1e293b; padding: 0.65rem 1.1rem; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="window.print()">
      <i class="fas fa-print"></i> Print Directory
    </button>
  </div>
</div>

<!-- ========================================================
     KPI STATS CARDS
     ======================================================== -->
<div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 1.8rem;">
  <!-- Total Subjects -->
  <div class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-book"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase;">Total Subjects</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: var(--admin-deep-navy); font-weight: 800;"><?= $totalSubjects ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;">Across <?= $totalClasses ?> Classes</small>
    </div>
  </div>

  <!-- Active Classes -->
  <a href="classes.php" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #f0fdf4; color: #16a34a; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-school"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #16a34a; text-transform: uppercase;">Active Classes</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #14532d; font-weight: 800;"><?= $totalClasses ?></h3>
      <small style="color: #16a34a; font-size: 0.72rem; font-weight: 700;">Curriculum Setup &rarr;</small>
    </div>
  </a>

  <!-- Assigned Subjects -->
  <a href="subjects.php?teacher_id=all" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #ede9fe; color: #7c3aed; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-chalkboard-teacher"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #7c3aed; text-transform: uppercase;">Assigned to Teachers</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #5b21b6; font-weight: 800;"><?= $totalAssigned ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;"><?= round(($totalAssigned / max(1, $totalSubjects)) * 100) ?>% Allocated</small>
    </div>
  </a>

  <!-- Unassigned Subjects -->
  <a href="subjects.php?teacher_id=unassigned" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $totalUnassigned > 0 ? '#f59e0b' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #fef3c7; color: #d97706; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-exclamation-triangle"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #d97706; text-transform: uppercase;">Pending Assignment</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #92400e; font-weight: 800;"><?= $totalUnassigned ?></h3>
      <small style="color: #b45309; font-size: 0.72rem; font-weight: 700;"><?= $totalUnassigned > 0 ? 'Action Needed &rarr;' : 'All Allocated' ?></small>
    </div>
  </a>
</div>

<!-- ========================================================
     FILTER & SEARCH BAR
     ======================================================== -->
<div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.6rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <form method="GET" action="subjects.php" style="display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 14px; align-items: flex-end;">
    <!-- 1. Search Query -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-search text-primary"></i> Search Subjects
      </label>
      <div style="position: relative;">
        <input type="text" name="q" class="form-control" placeholder="Search subject name, code, teacher..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2rem;">
        <i class="fas fa-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8; font-size: 0.85rem;"></i>
      </div>
    </div>

    <!-- 2. Class Filter -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-school text-primary"></i> Filter by Class
      </label>
      <select name="class_id" class="form-control" onchange="this.form.submit()">
        <option value="0">All Classes (Whole School)</option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= $cls['id'] ?>" <?= ($selected_class_id == $cls['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cls['class_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 3. Teacher Filter -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-chalkboard-teacher text-primary"></i> Filter by Teacher
      </label>
      <select name="teacher_id" class="form-control" onchange="this.form.submit()">
        <option value="all" <?= ($selected_teacher_id === 'all') ? 'selected' : '' ?>>All Teachers</option>
        <option value="unassigned" <?= ($selected_teacher_id === 'unassigned') ? 'selected' : '' ?>>⚠️ Unassigned Subjects Only</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($selected_teacher_id == $t['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['full_name']) ?> (<?= $t['teacher_code'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 4. Submit & Reset -->
    <div style="display: flex; gap: 6px;">
      <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.1rem; border-radius: 8px; font-weight: 700;">
        Filter
      </button>
      <a href="subjects.php" class="btn btn-secondary" style="padding: 0.65rem 0.9rem; border-radius: 8px;" title="Reset Filters">
        <i class="fas fa-redo"></i>
      </a>
    </div>
  </form>
</div>

<!-- ========================================================
     SUBJECTS DIRECTORY TABLE
     ======================================================== -->
<div class="card-box" style="background: #ffffff; border-radius: 16px; border: 1.5px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
  <div class="card-header" style="padding: 1.2rem 1.6rem; border-bottom: 1.5px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div>
      <h3 class="card-title" style="margin: 0; font-size: 1.15rem; color: var(--admin-deep-navy); font-weight: 800;">
        <i class="fas fa-book-reader text-primary"></i> Class Curriculum &amp; Subject Allotment (<?= count($subjectsList) ?>)
      </h3>
      <small style="color: var(--admin-text-muted);">
        Subjects assigned to each grade level and their designated educators.
      </small>
    </div>

    <div style="font-size: 0.85rem; color: var(--admin-text-muted);">
      Displaying <strong><?= count($subjectsList) ?></strong> of <strong><?= $totalSubjects ?></strong> subjects
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 100px;">Code</th>
          <th>Subject Name</th>
          <th>Class / Grade</th>
          <th>Assigned Educator</th>
          <th>Evaluation Scheme</th>
          <th>Enrolled Class Roster</th>
          <th style="text-align: right; width: 170px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($subjectsList)): ?>
          <?php foreach ($subjectsList as $sub): ?>
            <tr>
              <td>
                <span class="badge" style="background: #f1f5f9; color: #0284c7; font-family: monospace; font-size: 0.85rem; padding: 4px 8px; border: 1px solid #cbd5e1; font-weight: 800;">
                  <?= htmlspecialchars($sub['subject_code']) ?>
                </span>
              </td>
              <td>
                <strong style="color: var(--admin-deep-navy); font-size: 0.98rem; display: block;">
                  <?= htmlspecialchars($sub['subject_name']) ?>
                </strong>
                <?php if (!empty($sub['description'])): ?>
                  <small style="color: #64748b; font-style: italic;"><?= htmlspecialchars($sub['description']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-info" style="font-weight: 700; font-size: 0.82rem;">
                  <?= htmlspecialchars($sub['class_name']) ?>
                </span>
                <?php if ($sub['room_no']): ?>
                  <br><small style="color: #64748b; font-size: 0.72rem;">Room: <?= htmlspecialchars($sub['room_no']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($sub['teacher_name']): ?>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: grid; place-items: center; font-weight: 800; font-size: 0.85rem;">
                      <?= strtoupper(substr($sub['teacher_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong style="color: #10b981; font-size: 0.9rem; display: block;">
                        <?= htmlspecialchars($sub['teacher_name']) ?>
                      </strong>
                      <small style="color: #64748b; font-size: 0.72rem;"><code><?= htmlspecialchars($sub['teacher_code']) ?></code></small>
                    </div>
                  </div>
                <?php else: ?>
                  <button type="button" onclick="openAssignModal(<?= htmlspecialchars(json_encode($sub)) ?>)" class="btn btn-sm" style="background: #fef3c7; color: #b45309; border: 1px dashed #f59e0b; font-size: 0.78rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-plus"></i> Assign Teacher
                  </button>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size: 0.82rem; line-height: 1.4;">
                  <span>Total Marks: <strong><?= $sub['total_marks'] ?></strong></span><br>
                  <small style="color: #64748b;">Pass: <?= $sub['pass_marks'] ?></small>
                </div>
              </td>
              <td>
                <a href="students.php?class_id=<?= $sub['class_id'] ?>" style="text-decoration: none; font-size: 0.85rem; color: #0284c7; font-weight: 700;">
                  <i class="fas fa-user-graduate"></i> <?= $sub['student_count'] ?> Students &rarr;
                </a>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px; align-items: center;">
                  <!-- 1. Quick Assign Teacher Button -->
                  <button type="button" class="btn btn-sm" onclick="openAssignModal(<?= htmlspecialchars(json_encode($sub)) ?>)" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; border-radius: 6px; padding: 4px 8px;" title="Assign / Change Teacher">
                    <i class="fas fa-chalkboard-teacher"></i>
                  </button>

                  <!-- 2. Edit Subject Button -->
                  <button type="button" class="btn btn-sm" onclick="openEditSubjectModal(<?= htmlspecialchars(json_encode($sub)) ?>)" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;" title="Edit Subject">
                    <i class="fas fa-pencil-alt"></i>
                  </button>

                  <!-- 3. Delete Subject Button -->
                  <a href="subjects.php?delete_subject_id=<?= $sub['id'] ?>" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; border-radius: 6px; padding: 4px 8px; text-decoration: none;" onclick="return confirm('Are you sure you want to delete this subject?');" title="Delete Subject">
                    <i class="fas fa-trash-alt"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: var(--admin-text-muted);">
              <i class="fas fa-book-open" style="font-size: 2.2rem; opacity: 0.3; margin-bottom: 0.6rem; display: block;"></i>
              No subjects found matching your filter criteria.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========================================================
     MODAL 1: ADD NEW SUBJECT MODAL
     ======================================================== -->
<div id="addSubjectModal" class="admin-modal-wrap" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 1rem;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 620px; border-radius: 18px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #2563eb; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-book-medical"></i> CURRICULUM REGISTRATION
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.4rem;">
          Add Academic Subject &amp; Assign Teacher
        </h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: #64748b;">
          Introduce a new subject for a class and designate its faculty educator.
        </p>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addSubjectModal')">&times;</button>
    </div>

    <form method="POST" action="subjects.php">
      <input type="hidden" name="action_create_subject" value="1">

      <!-- Target Class (CRITICAL) -->
      <div class="form-group" style="margin-top: 1.2rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">
          <i class="fas fa-school text-primary"></i> Target Class / Grade Level *
        </label>
        <select name="class_id" id="addSubjectClass" class="form-control" required style="font-weight: 700; font-size: 0.95rem; color: #0284c7;" onchange="updateSuggestedCode()">
          <option value="">-- Choose Class --</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" data-name="<?= htmlspecialchars($cls['class_name']) ?>" <?= ($selected_class_id == $cls['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cls['class_name']) ?> (Section <?= htmlspecialchars($cls['section']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #64748b; font-size: 0.72rem;">Select the exact class where this subject will be taught.</small>
      </div>

      <!-- Quick Subject Presets -->
      <div style="margin-top: 1rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.82rem; display: block; margin-bottom: 6px;">
          Common Subject Suggestions (Click to fill):
        </label>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Quran & Tajweed', 'QUR')">📖 Quran &amp; Tajweed</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Cambridge Primary English', 'ENG')">🔤 Cambridge English</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Primary Mathematics', 'MAT')">📐 Mathematics</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Islamic Studies & Hadith', 'ISL')">🕌 Islamic Studies</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Arabic Language & Spoken', 'ARA')">🗣️ Arabic Language</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Cambridge Science', 'SCI')">🔬 Science</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Bengali Language', 'BEN')">🇧🇩 Bengali</button>
          <button type="button" class="preset-pill" onclick="fillSubjectPreset('Art & Creative Crafts', 'ART')">🎨 Art &amp; Craft</button>
        </div>
      </div>

      <!-- Subject Name & Code -->
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Subject Name *</label>
          <input type="text" name="subject_name" id="addSubjectName" class="form-control" placeholder="e.g. Cambridge Primary English" required style="font-weight: 700;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Subject Code *</label>
          <input type="text" name="subject_code" id="addSubjectCode" class="form-control" placeholder="e.g. ENG-Y1" required style="font-family: monospace; font-weight: 700; text-transform: uppercase;">
        </div>
      </div>

      <!-- Assign Teacher -->
      <div class="form-group" style="margin-top: 1rem; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 12px;">
        <label style="font-weight: 700; color: #166534; font-size: 0.85rem; display: block; margin-bottom: 4px;">
          <i class="fas fa-chalkboard-teacher"></i> Assign Faculty Educator (Teacher)
        </label>
        <select name="teacher_id" class="form-control" style="background: #ffffff;">
          <option value="">-- Assign Later (Leave Unassigned) --</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>">
              <?= htmlspecialchars($t['full_name']) ?> (<?= $t['teacher_code'] ?> &bull; <?= htmlspecialchars($t['specialty']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #166534; font-size: 0.72rem; display: block; margin-top: 4px;">
          The assigned teacher will see this subject on their personal Teacher Portal to record exam marks and track syllabus.
        </small>
      </div>

      <!-- Marks Scheme -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Total Assessment Marks</label>
          <input type="number" name="total_marks" class="form-control" value="100" required>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Passing Marks</label>
          <input type="number" name="pass_marks" class="form-control" value="40" required>
        </div>
      </div>

      <!-- Description / Syllabus Note -->
      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Syllabus Notes / Description (Optional)</label>
        <input type="text" name="description" class="form-control" placeholder="e.g. Cambridge Curriculum Stage 1 Workbook + Reader">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addSubjectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #2563eb; color: #ffffff; font-weight: 800; padding: 0.7rem 1.6rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Save &amp; Assign Subject
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 2: QUICK ASSIGN TEACHER MODAL
     ======================================================== -->
<div id="assignTeacherModal" class="admin-modal-wrap" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 1rem;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 520px; border-radius: 18px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #7c3aed; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-user-check"></i> TEACHER ALLOCATION
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">
          Assign Educator to Subject
        </h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('assignTeacherModal')">&times;</button>
    </div>

    <form method="POST" action="subjects.php">
      <input type="hidden" name="action_assign_teacher" value="1">
      <input type="hidden" name="subject_id" id="assignSubjectId">

      <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-top: 1rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
          <span style="color: #64748b; font-size: 0.82rem;">Subject:</span>
          <strong id="assignSubjectTitle" style="color: var(--admin-deep-navy);">--</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
          <span style="color: #64748b; font-size: 0.82rem;">Class:</span>
          <span id="assignClassBadge" class="badge badge-info" style="font-weight: 700;">--</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
          <span style="color: #64748b; font-size: 0.82rem;">Subject Code:</span>
          <code id="assignSubjectCode" style="color: #0284c7; font-weight: 700;">--</code>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1.2rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Select Teacher to Assign *</label>
        <select name="teacher_id" id="assignTeacherSelect" class="form-control" required style="font-size: 0.92rem; padding: 0.65rem;">
          <option value="">-- Choose Teacher --</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>">
              <?= htmlspecialchars($t['full_name']) ?> (<?= $t['teacher_code'] ?> &bull; <?= htmlspecialchars($t['specialty']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #64748b; font-size: 0.72rem; display: block; margin-top: 4px;">
          This subject will immediately be visible on this teacher's personal portal dashboard.
        </small>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('assignTeacherModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #7c3aed; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Confirm Assignment
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 3: EDIT SUBJECT MODAL
     ======================================================== -->
<div id="editSubjectModal" class="admin-modal-wrap" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 1rem;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 18px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-edit"></i> MODIFY CURRICULUM
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">
          Edit Subject Details
        </h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('editSubjectModal')">&times;</button>
    </div>

    <form method="POST" action="subjects.php">
      <input type="hidden" name="action_update_subject" value="1">
      <input type="hidden" name="subject_id" id="editSubjectId">

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-top: 1.2rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Subject Name *</label>
          <input type="text" name="subject_name" id="editSubjectName" class="form-control" required style="font-weight: 700;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Subject Code *</label>
          <input type="text" name="subject_code" id="editSubjectCode" class="form-control" required style="font-family: monospace; font-weight: 700;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Target Class *</label>
          <select name="class_id" id="editSubjectClass" class="form-control" required>
            <?php foreach ($classes as $cls): ?>
              <option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Assigned Teacher</label>
          <select name="teacher_id" id="editSubjectTeacher" class="form-control">
            <option value="">-- Unassigned --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Total Marks</label>
          <input type="number" name="total_marks" id="editTotalMarks" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Pass Marks</label>
          <input type="number" name="pass_marks" id="editPassMarks" class="form-control" required>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Description / Notes</label>
        <input type="text" name="description" id="editDescription" class="form-control">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editSubjectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0284c7; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.preset-pill {
  background: #f1f5f9; border: 1.5px solid #cbd5e1; color: #334155;
  padding: 5px 10px; border-radius: 16px; font-size: 0.76rem; font-weight: 700;
  cursor: pointer; transition: all 0.2s;
}
.preset-pill:hover {
  background: #2563eb; color: #ffffff; border-color: #2563eb;
}
</style>

<script>
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
}

function fillSubjectPreset(name, codePrefix) {
  document.getElementById('addSubjectName').value = name;
  const selClass = document.getElementById('addSubjectClass');
  const classId = selClass.value;
  if (classId) {
    document.getElementById('addSubjectCode').value = codePrefix + '-C' + classId;
  } else {
    document.getElementById('addSubjectCode').value = codePrefix;
  }
}

function updateSuggestedCode() {
  const name = document.getElementById('addSubjectName').value;
  const selClass = document.getElementById('addSubjectClass');
  if (name && selClass.value) {
    const words = name.split(' ');
    const prefix = words[0].substring(0, 3).toUpperCase();
    document.getElementById('addSubjectCode').value = prefix + '-C' + selClass.value;
  }
}

function openAssignModal(sub) {
  document.getElementById('assignSubjectId').value = sub.id;
  document.getElementById('assignSubjectTitle').innerText = sub.subject_name;
  document.getElementById('assignClassBadge').innerText = sub.class_name;
  document.getElementById('assignSubjectCode').innerText = sub.subject_code;
  document.getElementById('assignTeacherSelect').value = sub.teacher_id || '';
  openModal('assignTeacherModal');
}

function openEditSubjectModal(sub) {
  document.getElementById('editSubjectId').value = sub.id;
  document.getElementById('editSubjectName').value = sub.subject_name;
  document.getElementById('editSubjectCode').value = sub.subject_code;
  document.getElementById('editSubjectClass').value = sub.class_id;
  document.getElementById('editSubjectTeacher').value = sub.teacher_id || '';
  document.getElementById('editTotalMarks').value = sub.total_marks || 100;
  document.getElementById('editPassMarks').value = sub.pass_marks || 40;
  document.getElementById('editDescription').value = sub.description || '';
  openModal('editSubjectModal');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>