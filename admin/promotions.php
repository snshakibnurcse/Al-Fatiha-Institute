<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Get parameters
$source_class_id = intval($_GET['source_class_id'] ?? 1);
$target_class_id = $_GET['target_class_id'] ?? ($source_class_id + 1);
if ($target_class_id !== 'graduate') {
    $target_class_id = intval($target_class_id);
}
$defaultNextSession = (string)(intval(get_default_academic_session()) + 1);
$target_session = preg_replace('/\D/', '', sanitize($_GET['target_session'] ?? $defaultNextSession));
if (strlen($target_session) !== 4) {
    $target_session = $defaultNextSession;
}

// Handle Batch Student Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_promotion'])) {
    $src_id = intval($_POST['source_class_id']);
    $tgt_id = sanitize($_POST['target_class_id']); // Can be integer or 'graduate'
    $post_target_session = preg_replace('/\D/', '', sanitize($_POST['target_session'] ?? $target_session));
    if (strlen($post_target_session) !== 4) {
        $post_target_session = $defaultNextSession;
    }
    $target_session = $post_target_session;
    $selected_students = $_POST['student_ids'] ?? [];
    $reassign_rolls = isset($_POST['reassign_rolls']) && $_POST['reassign_rolls'] == '1';

    if (empty($selected_students)) {
        $err = "Please select at least one student to promote.";
    } else {
        try {
            $db->beginTransaction();
            $count = 0;

            $fetchCurrStmt = $db->prepare("SELECT id, student_id, class_id, roll_no, admission_session, current_session FROM students WHERE id = ? LIMIT 1");
            $srcClassName = $db->query("SELECT class_name FROM classes WHERE id = " . intval($src_id))->fetchColumn() ?: 'Previous Class';

            if ($tgt_id === 'graduate') {
                $updStmt = $db->prepare("UPDATE students SET status = 'Graduated' WHERE id = ?");
                foreach ($selected_students as $st_id) {
                    $st_pk = intval($st_id);
                    $fetchCurrStmt->execute([$st_pk]);
                    $curr = $fetchCurrStmt->fetch(PDO::FETCH_ASSOC);
                    if ($curr) {
                        $oldSess = $curr['current_session'] ?: ($curr['admission_session'] ?: get_default_academic_session());
                        record_student_academic_snapshot($st_pk, $oldSess, $curr['class_id'], $curr['roll_no'], $curr['student_id'], 'Graduated', "Graduated from $srcClassName");
                        $db->prepare("UPDATE student_academic_history SET status = 'Graduated', remarks = ? WHERE student_pk = ? AND student_id_code = ?")
                           ->execute(["Graduated from $srcClassName", $st_pk, $curr['student_id']]);
                    }
                    $updStmt->execute([$st_pk]);
                    $count++;
                }
                $msg = "Successfully graduated <strong>$count</strong> student(s)! Academic history preserved.";
            } else {
                $target_id = intval($tgt_id);
                $targetClassName = $db->query("SELECT class_name FROM classes WHERE id = $target_id")->fetchColumn() ?: 'Next Class';
                $updStmt = $db->prepare("UPDATE students SET class_id = ?, roll_no = ?, student_id = ?, current_session = ?, status = 'Active' WHERE id = ?");

                $rollCounter = get_next_class_roll($target_id, $target_session);
                if ($reassign_rolls) {
                    // Check if any existing students in target session+class already exist; if empty starts at 1, else continues from next available roll
                    $rollCounter = get_next_class_roll($target_id, $target_session);
                }

                foreach ($selected_students as $st_id) {
                    $st_pk = intval($st_id);
                    $fetchCurrStmt->execute([$st_pk]);
                    $curr = $fetchCurrStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$curr) continue;

                    $oldSess = $curr['current_session'] ?: ($curr['admission_session'] ?: get_default_academic_session());
                    // Preserve historical enrollment snapshot & mark old record as Promoted
                    record_student_academic_snapshot($st_pk, $oldSess, $curr['class_id'], $curr['roll_no'], $curr['student_id'], 'Promoted', "Promoted from $srcClassName ($oldSess) to $targetClassName ($target_session)");
                    $db->prepare("UPDATE student_academic_history SET status = 'Promoted', remarks = ? WHERE student_pk = ? AND student_id_code = ?")
                       ->execute(["Promoted from $srcClassName ($oldSess) to $targetClassName ($target_session)", $st_pk, $curr['student_id']]);

                    $newRoll = $reassign_rolls ? $rollCounter : (intval($curr['roll_no']) ?: $rollCounter);
                    $newStudentId = generate_student_id_for_class($target_id, $newRoll, $st_pk, $target_session);
                    // Derive actual assigned roll from generated 3-digit suffix in case requested roll collided in target session+class
                    $assignedRoll = intval(substr($newStudentId, -3)) ?: $newRoll;

                    $updStmt->execute([$target_id, $assignedRoll, $newStudentId, $target_session, $st_pk]);
                    record_student_academic_snapshot($st_pk, $target_session, $target_id, $assignedRoll, $newStudentId, 'Active', "Promoted into $targetClassName ($target_session)");

                    $rollCounter = max($rollCounter, $assignedRoll) + 1;
                    $count++;
                }

                $msg = "Successfully promoted <strong>$count</strong> student(s) to <strong>" . htmlspecialchars($targetClassName) . " (Session $target_session)</strong> with updated IDs &amp; preserved history!";
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $err = "Promotion failed: " . $e->getMessage();
        }
    }
}

// Fetch all available classes
$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();

// Fetch students in Source Class with Result & Fee summary
$srcStudentsStmt = $db->prepare("
    SELECT s.*, c.class_name,
           (SELECT SUM(amount) FROM fees WHERE student_id = s.id AND status = 'Unpaid') as due_amount,
           (SELECT AVG(marks_obtained) FROM results WHERE student_id = s.id) as avg_marks
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    WHERE s.class_id = ? AND s.status = 'Active'
    ORDER BY s.roll_no ASC
");
$srcStudentsStmt->execute([$source_class_id]);
$srcStudents = $srcStudentsStmt->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<!-- Header Banner -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">
      <i class="fas fa-user-graduate" style="color: var(--admin-gold);"></i> Year-End Academic Promotion Engine
    </h2>
    <p style="color: var(--admin-text-muted); margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Promote students to the next academic grade or graduate passing cohorts at session completion.
    </p>
  </div>
</div>

<!-- Step 1: Select Source and Target Classes -->
<div class="card-box" style="margin-bottom: 2rem;">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-random"></i> Step 1: Select Session Classes</h3>
  </div>

  <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 160px auto; gap: 1.2rem; align-items: flex-end;">
    <div class="form-group" style="margin: 0;">
      <label style="font-weight: 700; color: var(--admin-primary-dark);">Source Class (Current) *</label>
      <select name="source_class_id" class="form-control" onchange="this.form.submit()" style="padding: 0.75rem;">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $source_class_id == $c['id'] ? 'selected' : '' ?>>
            [<?= htmlspecialchars($c['class_code'] ?: str_pad((string)$c['id'], 2, '0', STR_PAD_LEFT)) ?>] <?= htmlspecialchars($c['class_name']) ?> (Section: <?= $c['section'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin: 0;">
      <label style="font-weight: 700; color: var(--admin-primary-dark);">Target Class (Promotion Grade) *</label>
      <select name="target_class_id" class="form-control" style="padding: 0.75rem;">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $target_class_id == $c['id'] ? 'selected' : '' ?>>
            Promote to: [<?= htmlspecialchars($c['class_code'] ?: str_pad((string)$c['id'], 2, '0', STR_PAD_LEFT)) ?>] <?= htmlspecialchars($c['class_name']) ?>
          </option>
        <?php endforeach; ?>
        <option value="graduate" <?= $target_class_id === 'graduate' ? 'selected' : '' ?>>Graduate Students (Final Year)</option>
      </select>
    </div>

    <div class="form-group" style="margin: 0;">
      <label style="font-weight: 700; color: var(--admin-primary-dark);">New Session Year *</label>
      <input type="text" name="target_session" value="<?= htmlspecialchars($target_session) ?>" maxlength="4" pattern="\d{4}" class="form-control" style="padding: 0.75rem; font-weight: 700; font-family: monospace;" required>
    </div>

    <div>
      <button type="submit" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.75rem 1.5rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer;">
        <i class="fas fa-filter"></i> Load Students
      </button>
    </div>
  </form>
</div>

<!-- Step 2: Student Promotion List -->
<form method="POST">
  <input type="hidden" name="source_class_id" value="<?= $source_class_id ?>">
  <input type="hidden" name="target_class_id" value="<?= htmlspecialchars($target_class_id) ?>">
  <input type="hidden" name="target_session" value="<?= htmlspecialchars($target_session) ?>">

  <div class="card-box">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h3 class="card-title">
        <i class="fas fa-users-check"></i> Step 2: Select Students to Promote (Total Found: <?= count($srcStudents) ?>)
      </h3>
      <div style="display: flex; gap: 1rem; align-items: center;">
        <label style="font-size: 0.88rem; font-weight: 700; cursor: pointer; color: var(--admin-primary-dark);">
          <input type="checkbox" id="selectAllCheckbox" checked onclick="toggleSelectAll(this)"> Select All Students
        </label>
      </div>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 50px; text-align: center;">Promote</th>
            <th>Roll No</th>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Exam Average (%)</th>
            <th>Academic Status</th>
            <th>Due Fees</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($srcStudents)): ?>
            <?php foreach ($srcStudents as $st): ?>
              <?php 
                $avg = $st['avg_marks'] !== null ? round($st['avg_marks'], 1) : null; 
                $due = floatval($st['due_amount'] ?: 0);
              ?>
              <tr>
                <td style="text-align: center;">
                  <input type="checkbox" name="student_ids[]" value="<?= $st['id'] ?>" class="st-checkbox" checked style="width: 18px; height: 18px; cursor: pointer;">
                </td>
                <td><strong>#<?= $st['roll_no'] ?></strong></td>
                <td><code><?= htmlspecialchars($st['student_id']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong>
                </td>
                <td>
                  <?php if ($avg !== null): ?>
                    <strong style="color: <?= $avg >= 50 ? '#10b981' : '#ef4444' ?>;"><?= $avg ?>%</strong>
                  <?php else: ?>
                    <span style="color: var(--admin-text-muted);">N/A</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($avg === null || $avg >= 50): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Passed</span>
                  <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-exclamation"></i> Needs Attention</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($due > 0): ?>
                    <span style="color: #ef4444; font-weight: 700; font-size: 0.85rem;"><?= format_currency($due) ?></span>
                  <?php else: ?>
                    <span style="color: #10b981; font-weight: 700; font-size: 0.85rem;"><i class="fas fa-check-circle"></i> Clear</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="student_profile.php?id=<?= $st['id'] ?>" target="_blank" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;">
                    <i class="fas fa-eye"></i> Profile
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8" style="text-align: center; color: var(--admin-text-muted);">No active students currently enrolled in this class.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($srcStudents)): ?>
      <div style="background: #f8fafc; padding: 1.5rem; border-top: 1px solid var(--admin-border); margin-top: 1.5rem; border-radius: 0 0 16px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <label style="font-weight: 700; color: var(--admin-primary-dark); font-size: 0.95rem;">
            <input type="checkbox" name="reassign_rolls" value="1" checked style="width: 16px; height: 16px;">
            Automatically reassign Roll numbers sequentially (1, 2, 3...) in the new class
          </label>
        </div>

        <div>
          <button type="submit" name="process_promotion" class="btn" style="background: #082447; color: #ffffff; height: 42px; padding: 0 24px; font-weight: 500; font-size: 14px; border: none; border-radius: 8px; cursor: pointer;" onclick="return confirm('Are you sure you want to promote selected students?')">
            <i class="fas fa-level-up-alt"></i> Promote Selected Students
          </button>
        </div>
      </div>
    <?php endif; ?>

  </div>
</form>

<script>
  function toggleSelectAll(master) {
    var checkboxes = document.querySelectorAll('.st-checkbox');
    checkboxes.forEach(function(cb) {
      cb.checked = master.checked;
    });
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
