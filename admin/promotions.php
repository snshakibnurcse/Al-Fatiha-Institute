<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Get parameters
$source_class_id = intval($_GET['source_class_id'] ?? 1);
$target_class_id = intval($_GET['target_class_id'] ?? ($source_class_id + 1));

// Handle Batch Student Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_promotion'])) {
    $src_id = intval($_POST['source_class_id']);
    $tgt_id = sanitize($_POST['target_class_id']); // Can be integer or 'graduate'
    $selected_students = $_POST['student_ids'] ?? [];
    $reassign_rolls = isset($_POST['reassign_rolls']) && $_POST['reassign_rolls'] == '1';

    if (empty($selected_students)) {
        $err = "Please select at least one student to promote.";
    } else {
        try {
            $db->beginTransaction();
            $count = 0;
            $rollCounter = 1;

            if ($tgt_id === 'graduate') {
                $updStmt = $db->prepare("UPDATE students SET status = 'Graduated' WHERE id = ?");
                foreach ($selected_students as $st_id) {
                    $updStmt->execute([intval($st_id)]);
                    $count++;
                }
                $msg = "Successfully graduated <strong>$count</strong> student(s)!";
            } else {
                $target_id = intval($tgt_id);
                $updStmt = $db->prepare("UPDATE students SET class_id = ?, roll_no = ?, status = 'Active' WHERE id = ?");
                
                // Get starting roll number for target class
                if (!$reassign_rolls) {
                    $maxRoll = $db->query("SELECT MAX(roll_no) FROM students WHERE class_id = $target_id")->fetchColumn() ?: 0;
                    $rollCounter = $maxRoll + 1;
                }

                foreach ($selected_students as $st_id) {
                    $st_pk = intval($st_id);
                    $newRoll = $reassign_rolls ? $rollCounter : $rollCounter++;
                    $updStmt->execute([$target_id, $newRoll, $st_pk]);
                    if ($reassign_rolls) $rollCounter++;
                    $count++;
                }
                
                $targetClassName = $db->query("SELECT class_name FROM classes WHERE id = $target_id")->fetchColumn();
                $msg = "Successfully promoted <strong>$count</strong> student(s) to <strong>" . htmlspecialchars($targetClassName) . "</strong>!";
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

  <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1.5rem; align-items: flex-end;">
    <div class="form-group" style="margin: 0;">
      <label style="font-weight: 700; color: var(--admin-primary-dark);">Source Class (Current) *</label>
      <select name="source_class_id" class="form-control" onchange="this.form.submit()" style="padding: 0.75rem;">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $source_class_id == $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['class_name']) ?> (Section: <?= $c['section'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin: 0;">
      <label style="font-weight: 700; color: var(--admin-primary-dark);">Target Class (Promotion Grade) *</label>
      <select name="target_class_id" class="form-control" style="padding: 0.75rem;">
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $target_class_id == $c['id'] ? 'selected' : '' ?>>
            Promote to: <?= htmlspecialchars($c['class_name']) ?>
          </option>
        <?php endforeach; ?>
        <option value="graduate" <?= $target_class_id === 'graduate' ? 'selected' : '' ?>>🎓 Graduate Students (Final Year)</option>
      </select>
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
  <input type="hidden" name="target_class_id" value="<?= $target_class_id ?>">

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
          <button type="submit" name="process_promotion" class="btn" style="background: linear-gradient(135deg, #f3b527 0%, #cd8707 100%); color: #032d4e; padding: 0.8rem 2rem; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1rem; border: none; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 15px rgba(243, 181, 39, 0.4);" onclick="return confirm('Are you sure you want to promote selected students?')">
            <i class="fas fa-level-up-alt"></i> Promote Selected Students Now
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
