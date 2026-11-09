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
    // If Super Admin / Principal / Admin previews
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

    // Strict Authorization Verification:
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
                // Verify student belongs to the class of the selected subject
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
                        // Check if grade already recorded for this student, subject, and term (Upsert)
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
                            $msg = "Exam marks updated successfully!";
                        } else {
                            $ins = $db->prepare("
                                INSERT INTO results (student_id, subject_id, exam_term, marks_obtained, max_marks, teacher_remarks) 
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $ins->execute([$student_id, $subject_id, $exam_term, $marks_obtained, $max_marks, $teacher_remarks]);
                            $msg = "Exam marks recorded successfully!";
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

// Find selected subject item
$selected_subject_item = null;
foreach ($subjects as $sb) {
    if ($sb['id'] == $selected_subject_id) {
        $selected_subject_item = $sb;
        break;
    }
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

<!-- Teacher Header Overview Banner -->
<div style="background: linear-gradient(135deg, #032d4e 0%, #064878 100%); color: #ffffff; padding: 1.6rem 2rem; border-radius: 16px; margin-bottom: 1.8rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; box-shadow: 0 8px 24px rgba(3, 45, 78, 0.15);">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.15); padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.4px; margin-bottom: 0.5rem;">
      <i class="fas fa-pen-to-square" style="color: #f3b527;"></i> FACULTY MARKS ENTRY PORTAL
    </div>
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.65rem; font-weight: 800; margin: 0 0 0.25rem 0; color: #ffffff;">
      <?= htmlspecialchars($teacherData ? $teacherData['full_name'] : get_current_user_name()) ?>
    </h2>
    <p style="margin: 0; color: rgba(255, 255, 255, 0.85); font-size: 0.9rem;">
      <?= $teacherData ? 'Teacher ID: <code>' . htmlspecialchars($teacherData['teacher_code']) . '</code> &bull; Specialty: ' . htmlspecialchars($teacherData['specialty']) : 'Administrative Evaluation Mode' ?>
    </p>
  </div>
  <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
    <span class="badge" style="background: #f3b527; color: #032d4e; font-weight: 800; padding: 0.55rem 1rem; border-radius: 10px; font-size: 0.85rem;">
      <i class="fas fa-book-bookmark"></i> <?= count($subjects) ?> Assigned <?= count($subjects) === 1 ? 'Subject' : 'Subjects' ?>
    </span>
    <a href="subjects.php" class="btn" style="background: rgba(255, 255, 255, 0.15); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); padding: 0.55rem 0.95rem; border-radius: 10px; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
      <i class="fas fa-arrow-left"></i> My Curriculum
    </a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert-msg alert-success" style="background: #dcfce7; border: 1.5px solid #86efac; color: #166534; padding: 0.9rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
    <i class="fas fa-check-circle" style="font-size: 1.15rem;"></i> <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert-msg alert-danger" style="background: #fee2e2; border: 1.5px solid #fca5a5; color: #991b1b; padding: 0.9rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
    <i class="fas fa-exclamation-triangle" style="font-size: 1.15rem;"></i> <?= htmlspecialchars($err) ?>
  </div>
<?php endif; ?>

<?php if ($user_role === 'teacher' && empty($subjects)): ?>
  <!-- Warning when teacher has zero assigned subjects -->
  <div class="card-box" style="background: #ffffff; border-radius: 16px; padding: 3rem 1.5rem; text-align: center; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
    <div style="width: 72px; height: 72px; border-radius: 50%; background: #fef3c7; color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem;">
      <i class="fas fa-lock"></i>
    </div>
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; color: #032d4e; margin-bottom: 0.5rem; font-weight: 800;">No Subjects Assigned Yet</h3>
    <p style="color: #64748b; max-width: 500px; margin: 0 auto 1.5rem auto; line-height: 1.5; font-size: 0.95rem;">
      You currently do not have any subjects assigned to your faculty profile. Student mark entry is restricted exclusively to allocated subjects. Please contact the Head of School / Principal to assign your curriculum subjects.
    </p>
    <a href="dashboard.php" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
      <i class="fas fa-th-large"></i> Return to Teacher Overview
    </a>
  </div>
<?php else: ?>

  <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.8rem; align-items: start;">
    
    <!-- Mark Entry Form Card -->
    <div class="card-box" style="background: #ffffff; border-radius: 16px; padding: 1.75rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
      <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1.4rem;">
        <h3 style="font-family: 'Outfit', sans-serif; margin: 0 0 0.3rem 0; font-size: 1.25rem; color: #032d4e; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-pen-nib" style="color: var(--admin-primary);"></i> Record Student Marks
        </h3>
        <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
          Select your assigned subject and target student to record or update examination scores.
        </p>
      </div>

      <form method="POST" id="marksEntryForm">
        
        <!-- Subject Selection (Strictly Assigned Subjects Only) -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label style="display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem;">
            <span><i class="fas fa-book" style="color: var(--admin-primary); margin-right: 4px;"></i> Assigned Subject *</span>
            <?php if (count($subjects) === 1): ?>
              <span style="font-size: 0.74rem; font-weight: 700; color: #059669; background: #d1fae5; padding: 2px 8px; border-radius: 12px;">
                <i class="fas fa-check-circle"></i> 1 Assigned Subject
              </span>
            <?php endif; ?>
          </label>
          
          <select name="subject_id" id="subjectSelect" class="form-control" required onchange="handleSubjectChange()" style="border-radius: 10px; font-weight: 600; padding: 0.7rem 0.85rem;">
            <?php foreach ($subjects as $sb): ?>
              <option value="<?= $sb['id'] ?>" 
                      data-class-id="<?= $sb['class_id'] ?>" 
                      data-class-name="<?= htmlspecialchars($sb['class_name']) ?>"
                      data-max-marks="<?= $sb['total_marks'] ?? 100 ?>"
                      <?= ($selected_subject_id == $sb['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sb['subject_name']) ?> &bull; <?= htmlspecialchars($sb['class_name']) ?> (<?= htmlspecialchars($sb['subject_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color: #64748b; font-size: 0.78rem; display: block; margin-top: 4px;">
            <i class="fas fa-shield-alt" style="color: #10b981;"></i> Only subjects allocated to your account are accessible.
          </small>
        </div>

        <!-- Student Selection (Auto-filtered to the chosen subject's class) -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label style="display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem;">
            <span><i class="fas fa-user-graduate" style="color: var(--admin-primary); margin-right: 4px;"></i> Enrolled Student *</span>
            <span id="studentCountBadge" style="font-size: 0.74rem; font-weight: 700; color: #032d4e; background: #e0f2fe; padding: 2px 8px; border-radius: 12px;">
              Filtering Class...
            </span>
          </label>
          
          <select name="student_id" id="studentSelect" class="form-control" required style="border-radius: 10px; padding: 0.7rem 0.85rem;">
            <option value="">-- Choose Enrolled Student --</option>
            <?php foreach ($students as $st): ?>
              <option value="<?= $st['id'] ?>" data-class-id="<?= $st['class_id'] ?>">
                <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (Roll #<?= $st['roll_no'] ?> &bull; <?= $st['student_id'] ?> &bull; <?= htmlspecialchars($st['class_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div id="noStudentNotice" style="display: none; color: #b91c1c; font-size: 0.8rem; margin-top: 5px; font-weight: 600;">
            <i class="fas fa-info-circle"></i> No active students currently enrolled in this subject's class.
          </div>
        </div>

        <!-- Exam Term Selection -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
          <label style="font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem; display: block;">
            <i class="fas fa-calendar-alt" style="color: var(--admin-primary); margin-right: 4px;"></i> Examination Term *
          </label>
          <select name="exam_term" id="examTerm" class="form-control" required style="border-radius: 10px; padding: 0.7rem 0.85rem;">
            <option value="First Term">First Term Examination</option>
            <option value="Mid Term" selected>Mid Term Examination</option>
            <option value="Final Exam">Final Examination</option>
          </select>
        </div>

        <!-- Score Inputs -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
          <div class="form-group" style="margin-bottom: 0;">
            <label style="font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem; display: block;">
              <i class="fas fa-calculator" style="color: var(--admin-primary); margin-right: 4px;"></i> Marks Obtained *
            </label>
            <input type="number" name="marks_obtained" id="marksObtained" step="0.5" min="0" class="form-control" placeholder="e.g. 85.5" required style="border-radius: 10px; font-weight: 700; font-size: 1.05rem;">
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label style="font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem; display: block;">
              <i class="fas fa-hashtag" style="color: var(--admin-primary); margin-right: 4px;"></i> Maximum Marks *
            </label>
            <input type="number" name="max_marks" id="maxMarks" value="100" min="1" class="form-control" required style="border-radius: 10px; font-weight: 700; font-size: 1.05rem;">
          </div>
        </div>

        <!-- Teacher Remarks -->
        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label style="font-weight: 700; font-size: 0.86rem; color: #1e293b; margin-bottom: 0.45rem; display: block;">
            <i class="fas fa-comment-dots" style="color: var(--admin-primary); margin-right: 4px;"></i> Teacher Assessment Remarks
          </label>
          <input type="text" name="teacher_remarks" id="teacherRemarks" value="Excellent progress & active class participation" class="form-control" placeholder="Enter constructive feedback or remarks" style="border-radius: 10px;">
        </div>

        <button type="submit" name="save_results" id="submitBtn" class="btn btn-block" style="background: linear-gradient(135deg, #f3b527 0%, #cd8707 100%); color: #032d4e; font-family: 'Outfit', sans-serif; font-size: 1rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; padding: 0.85rem; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(205, 135, 7, 0.3); transition: all 0.2s ease;">
          <i class="fas fa-save"></i> Save Student Marks
        </button>
      </form>
    </div>

    <!-- Recent Recorded Grades Directory -->
    <div class="card-box" style="background: #ffffff; border-radius: 16px; padding: 1.75rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
      <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
          <h3 style="font-family: 'Outfit', sans-serif; margin: 0 0 0.2rem 0; font-size: 1.25rem; color: #032d4e; font-weight: 800; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-clipboard-list" style="color: var(--admin-primary);"></i> My Recorded Grades
          </h3>
          <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
            Grades submitted for your assigned curriculum subjects.
          </p>
        </div>
        <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; border-radius: 8px;">
          <?= count($recentResults) ?> Entries
        </span>
      </div>

      <div class="table-responsive">
        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
          <thead>
            <tr style="background: #f8fafc; text-align: left;">
              <th style="padding: 0.7rem 0.6rem;">Student</th>
              <th style="padding: 0.7rem 0.6rem;">Class</th>
              <th style="padding: 0.7rem 0.6rem;">Subject</th>
              <th style="padding: 0.7rem 0.6rem;">Term</th>
              <th style="padding: 0.7rem 0.6rem;">Marks</th>
              <th style="padding: 0.7rem 0.6rem;">Grade</th>
              <th style="padding: 0.7rem 0.6rem; text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recentResults)): ?>
              <?php foreach ($recentResults as $rr): ?>
                <?php $gInfo = calculate_grade($rr['marks_obtained'], $rr['max_marks']); ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 0.75rem 0.6rem;">
                    <strong><?= htmlspecialchars($rr['first_name'] . ' ' . $rr['last_name']) ?></strong>
                    <div style="font-size: 0.75rem; color: #64748b;"><code><?= htmlspecialchars($rr['st_code']) ?></code> (#<?= $rr['roll_no'] ?>)</div>
                  </td>
                  <td style="padding: 0.75rem 0.6rem;"><?= htmlspecialchars($rr['class_name']) ?></td>
                  <td style="padding: 0.75rem 0.6rem;"><?= htmlspecialchars($rr['subject_name']) ?></td>
                  <td style="padding: 0.75rem 0.6rem;">
                    <span class="badge badge-info" style="font-size: 0.74rem; font-weight: 700;"><?= htmlspecialchars($rr['exam_term']) ?></span>
                  </td>
                  <td style="padding: 0.75rem 0.6rem;">
                    <strong><?= $rr['marks_obtained'] ?></strong> <span style="color: #94a3b8; font-size: 0.78rem;">/ <?= $rr['max_marks'] ?></span>
                  </td>
                  <td style="padding: 0.75rem 0.6rem;">
                    <span class="badge <?= $gInfo['class'] ?>" style="font-size: 0.75rem; font-weight: 800;"><?= $gInfo['grade'] ?></span>
                  </td>
                  <td style="padding: 0.75rem 0.6rem; text-align: center;">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this grade record?');">
                      <input type="hidden" name="result_id" value="<?= $rr['id'] ?>">
                      <button type="submit" name="delete_result" class="btn" style="background: #fee2e2; color: #dc2626; border: none; padding: 4px 8px; border-radius: 6px; font-size: 0.78rem; cursor: pointer;" title="Delete Record">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                  <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; color: #cbd5e1;"></i>
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

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      handleSubjectChange();
    });
  </script>

<?php endif; ?>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
