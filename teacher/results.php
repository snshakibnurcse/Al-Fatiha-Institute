<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

$user_id = get_current_user_id();
$user_role = get_current_user_role();

// 1. Identify logged in teacher
$teacherData = null;
if ($user_role === 'teacher') {
    $tchStmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
    $tchStmt->execute([$user_id]);
    $teacherData = $tchStmt->fetch();
} else {
    $tid = intval($_GET['teacher_id'] ?? 0);
    if ($tid > 0) {
        $tchStmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
        $tchStmt->execute([$tid]);
        $teacherData = $tchStmt->fetch();
    }
}

$is_admin_preview = in_array($user_role, ['super_admin', 'admin', 'principal']) && !$teacherData;

// 2. Fetch assigned subjects strictly
if ($teacherData) {
    $teacher_id = intval($teacherData['id']);
    $subStmt = $db->prepare("
        SELECT sub.*, c.class_name, c.section 
        FROM subjects sub 
        JOIN classes c ON sub.class_id = c.id 
        WHERE sub.teacher_id = ? 
        ORDER BY c.id ASC, sub.subject_name ASC
    ");
    $subStmt->execute([$teacher_id]);
    $subjects = $subStmt->fetchAll();
} elseif ($is_admin_preview) {
    $subjects = $db->query("
        SELECT sub.*, c.class_name, c.section, t.full_name as teacher_name 
        FROM subjects sub 
        JOIN classes c ON sub.class_id = c.id 
        LEFT JOIN teachers t ON sub.teacher_id = t.id 
        ORDER BY c.id ASC, sub.subject_name ASC
    ")->fetchAll();
} else {
    $subjects = [];
}

$assignedSubjectIds = array_map('intval', array_column($subjects, 'id'));

// 3. Handle deletion of an existing result (if belongs to this teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_result'])) {
    $del_id = intval($_POST['result_id'] ?? 0);
    if ($del_id > 0) {
        if ($user_role === 'teacher' && $teacherData) {
            $delStmt = $db->prepare("
                DELETE r FROM results r 
                JOIN subjects s ON r.subject_id = s.id 
                WHERE r.id = ? AND s.teacher_id = ?
            ");
            $delStmt->execute([$del_id, $teacherData['id']]);
            $msg = "Result record deleted successfully.";
        } elseif ($is_admin_preview) {
            $db->prepare("DELETE FROM results WHERE id = ?")->execute([$del_id]);
            $msg = "Result record deleted successfully.";
        }
    }
}

// 4. Handle POST save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $exam_term = sanitize($_POST['exam_term'] ?? 'Mid Term');
    $marks_obtained = floatval($_POST['marks_obtained'] ?? 0);
    $max_marks = floatval($_POST['max_marks'] ?? 100);
    $teacher_remarks = sanitize($_POST['teacher_remarks'] ?? 'Good performance');

    if ($user_role === 'teacher') {
        if (!$teacherData) {
            $err = "Unauthorized: No teacher profile found for your account.";
        } elseif (!in_array($subject_id, $assignedSubjectIds)) {
            $err = "Access Denied: You are not authorized to submit marks for this subject.";
        }
    }

    if (empty($err)) {
        if ($student_id > 0 && $subject_id > 0) {
            if ($marks_obtained < 0 || $marks_obtained > $max_marks) {
                $err = "Marks obtained must be between 0 and maximum marks (" . $max_marks . ").";
            } else {
                $chkStudent = $db->prepare("
                    SELECT s.id 
                    FROM students s 
                    JOIN subjects sub ON s.class_id = sub.class_id 
                    WHERE s.id = ? AND sub.id = ?
                ");
                $chkStudent->execute([$student_id, $subject_id]);
                if (!$chkStudent->fetch()) {
                    $err = "The selected student does not belong to the class of this subject.";
                } else {
                    try {
                        $existingStmt = $db->prepare("
                            SELECT id FROM results 
                            WHERE student_id = ? AND subject_id = ? AND exam_term = ?
                        ");
                        $existingStmt->execute([$student_id, $subject_id, $exam_term]);
                        $existingId = $existingStmt->fetchColumn();

                        if ($existingId) {
                            $upd = $db->prepare("
                                UPDATE results 
                                SET marks_obtained = ?, max_marks = ?, teacher_remarks = ? 
                                WHERE id = ?
                            ");
                            $upd->execute([$marks_obtained, $max_marks, $teacher_remarks, $existingId]);
                            $msg = "Exam marks updated successfully.";
                        } else {
                            $ins = $db->prepare("
                                INSERT INTO results (student_id, subject_id, exam_term, marks_obtained, max_marks, teacher_remarks) 
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $ins->execute([$student_id, $subject_id, $exam_term, $marks_obtained, $max_marks, $teacher_remarks]);
                            $msg = "Exam marks recorded successfully.";
                        }
                    } catch (PDOException $e) {
                        $err = "Failed to record marks: " . $e->getMessage();
                    }
                }
            }
        } else {
            $err = "Please select both an enrolled student and an assigned subject.";
        }
    }
}

// 5. Determine active subject selection
$req_subject_id = intval($_GET["subject_id"] ?? $_POST["subject_id"] ?? 0);
if ($req_subject_id > 0 && in_array($req_subject_id, $assignedSubjectIds)) {
    $selected_subject_id = $req_subject_id;
} else {
    $selected_subject_id = !empty($subjects) ? $subjects[0]['id'] : 0;
}

// 6. Fetch eligible students (strictly for classes of assigned subjects)
if ($teacherData) {
    $teacher_id = intval($teacherData['id']);
    $stStmt = $db->prepare("
        SELECT s.*, c.class_name 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.status = 'Active' 
          AND s.class_id IN (SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ?)
        ORDER BY s.class_id ASC, s.roll_no ASC, s.first_name ASC
    ");
    $stStmt->execute([$teacher_id]);
    $students = $stStmt->fetchAll();
} elseif ($is_admin_preview) {
    $students = $db->query("
        SELECT s.*, c.class_name 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.status = 'Active' 
        ORDER BY s.class_id ASC, s.roll_no ASC, s.first_name ASC
    ")->fetchAll();
} else {
    $students = [];
}

// 7. Fetch recent results (strictly for this teacher's assigned subjects)
if ($teacherData) {
    $resStmt = $db->prepare("
        SELECT r.*, s.student_id as st_code, s.first_name, s.last_name, s.roll_no, sub.subject_name, c.class_name 
        FROM results r 
        JOIN students s ON r.student_id = s.id 
        JOIN subjects sub ON r.subject_id = sub.id 
        JOIN classes c ON s.class_id = c.id 
        WHERE sub.teacher_id = ? 
        ORDER BY r.id DESC 
        LIMIT 25
    ");
    $resStmt->execute([$teacherData['id']]);
    $recentResults = $resStmt->fetchAll();
} elseif ($is_admin_preview) {
    $recentResults = $db->query("
        SELECT r.*, s.student_id as st_code, s.first_name, s.last_name, s.roll_no, sub.subject_name, c.class_name 
        FROM results r 
        JOIN students s ON r.student_id = s.id 
        JOIN subjects sub ON r.subject_id = sub.id 
        JOIN classes c ON s.class_id = c.id 
        ORDER BY r.id DESC 
        LIMIT 25
    ")->fetchAll();
} else {
    $recentResults = [];
}
?>

<!-- Teacher Header Overview Card -->
<div class="dash-welcome-card">
  <div>
    <div class="welcome-meta-row">
      <span class="date-badge">Marks Entry Portal</span>
      <span class="role-pill-tag"><?= count($subjects) ?> Assigned <?= count($subjects) === 1 ? 'Subject' : 'Subjects' ?></span>
    </div>
    <h2 class="welcome-title">
      <?= htmlspecialchars($teacherData ? $teacherData['full_name'] : get_current_user_name()) ?>
    </h2>
    <p class="welcome-sub">
      <?= $teacherData ? htmlspecialchars($teacherData['teacher_code']) . ' · ' . htmlspecialchars($teacherData['specialty']) : 'Administrative Evaluation Mode' ?>
    </p>
  </div>
  <div class="welcome-actions">
    <a href="subjects.php" class="btn-welcome-secondary">
      &larr; My Curriculum
    </a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert-msg alert-success">
    <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($msg) ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert-msg alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <span><?= htmlspecialchars($err) ?></span>
  </div>
<?php endif; ?>

<?php if ($user_role === 'teacher' && empty($subjects)): ?>
  <div class="card-box" style="padding: 48px 24px; text-align: center;">
    <h3 style="font-size: 20px; color: var(--admin-navy); margin-bottom: 8px; font-weight: 600;">No Subjects Assigned Yet</h3>
    <p style="color: var(--admin-text-muted); max-width: 480px; margin: 0 auto 20px auto; font-size: 14px;">
      Student mark entry is restricted exclusively to allocated subjects. Please contact the Principal to assign your curriculum subjects.
    </p>
    <a href="dashboard.php" class="btn-welcome-primary">Return to Teacher Overview</a>
  </div>
<?php else: ?>

  <div style="display: grid; grid-template-columns: 1fr 1.4fr; gap: 20px; align-items: start;">

    <!-- Mark Entry Form Card -->
    <div class="card-box">
      <div class="card-header">
        <div>
          <h3 class="card-title">Record Student Marks</h3>
          <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--admin-text-muted);">
            Select assigned subject and enrolled student.
          </p>
        </div>
      </div>

      <form method="POST" id="marksEntryForm">

        <!-- Subject Selection (Strictly Assigned Subjects Only) -->
        <div class="form-group">
          <label style="display: flex; justify-content: space-between; align-items: center;">
            <span>Assigned Subject *</span>
            <?php if (count($subjects) === 1): ?>
              <span class="badge badge-success">1 assigned</span>
            <?php endif; ?>
          </label>

          <select name="subject_id" id="subjectSelect" class="form-control" required onchange="handleSubjectChange()">
            <?php foreach ($subjects as $sb): ?>
              <option value="<?= $sb['id'] ?>" 
                      data-class-id="<?= $sb['class_id'] ?>" 
                      data-class-name="<?= htmlspecialchars($sb['class_name']) ?>"
                      data-max-marks="<?= $sb['total_marks'] ?? 100 ?>"
                      <?= ($selected_subject_id == $sb['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sb['subject_name']) ?> · <?= htmlspecialchars($sb['class_name']) ?> (<?= htmlspecialchars($sb['subject_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color: var(--admin-text-muted); font-size: 12px; display: block; margin-top: 4px;">
            Only subjects allocated to your account are shown.
          </small>
        </div>

        <!-- Student Selection (Auto-filtered to the chosen subject's class) -->
        <div class="form-group">
          <label style="display: flex; justify-content: space-between; align-items: center;">
            <span>Enrolled Student *</span>
            <span id="studentCountBadge" class="badge badge-info">Filtering class</span>
          </label>

          <select name="student_id" id="studentSelect" class="form-control" required>
            <option value="">Choose enrolled student</option>
            <?php foreach ($students as $st): ?>
              <option value="<?= $st['id'] ?>" data-class-id="<?= $st['class_id'] ?>">
                <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (Roll #<?= $st['roll_no'] ?> · <?= $st['student_id'] ?> · <?= htmlspecialchars($st['class_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div id="noStudentNotice" style="display: none; color: var(--admin-negative); font-size: 12.5px; margin-top: 6px; font-weight: 500;">
            No active students currently enrolled in this subject's class.
          </div>
        </div>

        <!-- Exam Term Selection -->
        <div class="form-group">
          <label>Examination Term *</label>
          <select name="exam_term" id="examTerm" class="form-control" required>
            <option value="First Term">First Term Examination</option>
            <option value="Mid Term" selected>Mid Term Examination</option>
            <option value="Final Exam">Final Examination</option>
          </select>
        </div>

        <!-- Score Inputs -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div class="form-group" style="margin-bottom: 0;">
            <label>Marks Obtained *</label>
            <input type="number" name="marks_obtained" id="marksObtained" step="0.5" min="0" class="form-control" required style="font-family: 'IBM Plex Mono', monospace; font-weight: 500;">
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label>Maximum Marks *</label>
            <input type="number" name="max_marks" id="maxMarks" value="100" min="1" class="form-control" required style="font-family: 'IBM Plex Mono', monospace; font-weight: 500;">
          </div>
        </div>

        <!-- Teacher Remarks -->
        <div class="form-group">
          <label>Teacher Assessment Remarks</label>
          <input type="text" name="teacher_remarks" id="teacherRemarks" value="Excellent progress & active class participation" class="form-control">
        </div>

        <button type="submit" name="save_results" id="submitBtn" class="btn-welcome-primary btn-block" style="height: 42px; justify-content: center; cursor: pointer;">
          <i class="fas fa-save"></i> Save Student Marks
        </button>
      </form>
    </div>

    <!-- Recent Recorded Grades Directory -->
    <div class="card-box">
      <div class="card-header">
        <div>
          <h3 class="card-title">My Recorded Grades</h3>
          <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--admin-text-muted);">
            Grades submitted for your assigned subjects.
          </p>
        </div>
        <span class="badge badge-info"><?= count($recentResults) ?> Entries</span>
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
              <th style="text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recentResults)): ?>
              <?php foreach ($recentResults as $rr): ?>
                <?php $gInfo = calculate_grade($rr['marks_obtained'], $rr['max_marks']); ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($rr['first_name'] . ' ' . $rr['last_name']) ?></strong>
                    <div style="font-family: 'IBM Plex Mono', monospace; font-size: 11.5px; color: var(--admin-text-muted);"><?= htmlspecialchars($rr['st_code']) ?> (#<?= $rr['roll_no'] ?>)</div>
                  </td>
                  <td><?= htmlspecialchars($rr['class_name']) ?></td>
                  <td><?= htmlspecialchars($rr['subject_name']) ?></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($rr['exam_term']) ?></span></td>
                  <td style="font-family: 'IBM Plex Mono', monospace;">
                    <strong><?= $rr['marks_obtained'] ?></strong> <span style="color: var(--admin-text-muted);">/ <?= $rr['max_marks'] ?></span>
                  </td>
                  <td>
                    <span class="badge <?= $gInfo['class'] ?>"><?= $gInfo['grade'] ?></span>
                  </td>
                  <td style="text-align: center;">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this grade record?');">
                      <input type="hidden" name="result_id" value="<?= $rr['id'] ?>">
                      <button type="submit" name="delete_result" class="btn" style="background: #fef2f2; color: #dc2626; border: 1px solid rgba(220,38,38,0.2); padding: 4px 8px; border-radius: 6px; font-size: 12px; cursor: pointer;" title="Delete Record">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 36px 16px; color: var(--admin-text-muted);">
                  No examination marks recorded yet for your assigned subjects.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <script>
    function handleSubjectChange() {
      const subjectSelect = document.getElementById('subjectSelect');
      const studentSelect = document.getElementById('studentSelect');
      const maxMarksInput = document.getElementById('maxMarks');
      const noStudentNotice = document.getElementById('noStudentNotice');
      const submitBtn = document.getElementById('submitBtn');
      const studentCountBadge = document.getElementById('studentCountBadge');

      if (!subjectSelect || !studentSelect) return;

      const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
      if (!selectedOption) return;

      const targetClassId = selectedOption.getAttribute('data-class-id');
      const defaultMax = selectedOption.getAttribute('data-max-marks');
      if (defaultMax && maxMarksInput) {
        maxMarksInput.value = defaultMax;
      }

      let matchCount = 0;
      let firstMatch = null;

      for (let i = 0; i < studentSelect.options.length; i++) {
        const opt = studentSelect.options[i];
        if (opt.value === "") continue;

        const optClassId = opt.getAttribute('data-class-id');
        if (optClassId === targetClassId) {
          opt.style.display = '';
          opt.disabled = false;
          matchCount++;
          if (!firstMatch) firstMatch = opt.value;
        } else {
          opt.style.display = 'none';
          opt.disabled = true;
        }
      }

      if (studentCountBadge) {
        studentCountBadge.textContent = matchCount + ' Enrolled ' + (matchCount === 1 ? 'Student' : 'Students');
      }

      if (matchCount === 0) {
        studentSelect.value = "";
        studentSelect.disabled = true;
        noStudentNotice.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
      } else {
        studentSelect.disabled = false;
        noStudentNotice.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
        if (firstMatch) {
          studentSelect.value = firstMatch;
        }
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      handleSubjectChange();
    });
  </script>

<?php endif; ?>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
