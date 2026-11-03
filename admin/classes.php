<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// ========================================================
// 1. POST HANDLER: ADD NEW CLASS
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_class'])) {
    $class_name = sanitize($_POST['class_name'] ?? '');
    $age_group = sanitize($_POST['age_group'] ?? '');
    $section = sanitize($_POST['section'] ?? 'A');
    $room_no = sanitize($_POST['room_no'] ?? '');
    $tuition_fee = floatval($_POST['tuition_fee'] ?? 3500);
    $admission_fee = floatval($_POST['admission_fee'] ?? 8000);
    $book_kit_fee = floatval($_POST['book_kit_fee'] ?? 3500);
    $primary_teacher_id = !empty($_POST['primary_teacher_id']) ? intval($_POST['primary_teacher_id']) : null;
    $backup_teacher_id = !empty($_POST['backup_teacher_id']) ? intval($_POST['backup_teacher_id']) : null;

    if (!empty($class_name)) {
        // Check duplicate class name
        $chk = $db->prepare("SELECT id FROM classes WHERE class_name = ? LIMIT 1");
        $chk->execute([$class_name]);
        if ($chk->fetch()) {
            $err = "Class name '<strong>" . htmlspecialchars($class_name) . "</strong>' already exists.";
        } else {
            try {
                $ins = $db->prepare("
                    INSERT INTO classes (class_name, age_group, section, room_no, tuition_fee, admission_fee, book_kit_fee, primary_teacher_id, backup_teacher_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$class_name, $age_group, $section, $room_no, $tuition_fee, $admission_fee, $book_kit_fee, $primary_teacher_id, $backup_teacher_id]);
                $msg = "New class '<strong>" . htmlspecialchars($class_name) . "</strong>' added successfully! You can now assign subjects and enroll students.";
            } catch (Exception $e) {
                $err = "Error adding class: " . $e->getMessage();
            }
        }
    } else {
        $err = "Class name is required.";
    }
}

// ========================================================
// 2. POST HANDLER: UPDATE CLASS CONFIGURATION & FEES
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class_config'])) {
    $class_id = intval($_POST['class_id']);
    $class_name = sanitize($_POST['class_name'] ?? '');
    $age_group = sanitize($_POST['age_group'] ?? '');
    $new_fee = floatval($_POST['tuition_fee']);
    $admission_fee = floatval($_POST['admission_fee'] ?? 8000);
    $book_kit_fee = floatval($_POST['book_kit_fee'] ?? 3500);
    $primary_teacher_id = !empty($_POST['primary_teacher_id']) ? intval($_POST['primary_teacher_id']) : null;
    $backup_teacher_id = !empty($_POST['backup_teacher_id']) ? intval($_POST['backup_teacher_id']) : null;
    $room_no = sanitize($_POST['room_no'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE classes 
            SET class_name = ?, age_group = ?, tuition_fee = ?, admission_fee = ?, book_kit_fee = ?, primary_teacher_id = ?, backup_teacher_id = ?, room_no = ? 
            WHERE id = ?
        ");
        $stmt->execute([$class_name, $age_group, $new_fee, $admission_fee, $book_kit_fee, $primary_teacher_id, $backup_teacher_id, $room_no, $class_id]);
        $msg = "Class '<strong>" . htmlspecialchars($class_name) . "</strong>' configuration & fee structure updated successfully!";
    } catch (Exception $e) {
        $err = "Error updating class: " . $e->getMessage();
    }
}

// ========================================================
// 3. GET HANDLER: DELETE CLASS (WITH SAFETY CHECKS)
// ========================================================
if (isset($_GET['delete_class_id'])) {
    $delId = intval($_GET['delete_class_id']);
    $stCount = $db->query("SELECT COUNT(*) FROM students WHERE class_id = $delId")->fetchColumn();
    $subCount = $db->query("SELECT COUNT(*) FROM subjects WHERE class_id = $delId")->fetchColumn();

    if ($stCount > 0) {
        $err = "Cannot delete class: <strong>$stCount</strong> active students are currently enrolled. Please transfer or promote them first.";
    } elseif ($subCount > 0) {
        $err = "Cannot delete class: <strong>$subCount</strong> subjects are assigned to this class. Remove or re-assign subjects first.";
    } else {
        $clsName = $db->query("SELECT class_name FROM classes WHERE id = $delId")->fetchColumn();
        $db->prepare("DELETE FROM classes WHERE id = ?")->execute([$delId]);
        $msg = "Class '<strong>" . htmlspecialchars($clsName) . "</strong>' deleted successfully.";
    }
}

// Fetch all classes with student count, subject count, and teacher details
$classes = $db->query("
    SELECT c.*, 
           COUNT(DISTINCT s.id) as student_count,
           COUNT(DISTINCT sub.id) as subject_count,
           pt.full_name as primary_teacher_name,
           bt.full_name as backup_teacher_name
    FROM classes c 
    LEFT JOIN students s ON c.id = s.class_id AND s.status = 'Active'
    LEFT JOIN subjects sub ON c.id = sub.class_id
    LEFT JOIN teachers pt ON c.primary_teacher_id = pt.id
    LEFT JOIN teachers bt ON c.backup_teacher_id = bt.id
    GROUP BY c.id 
    ORDER BY c.id ASC
")->fetchAll();

$teachers = $db->query("SELECT * FROM teachers ORDER BY full_name ASC")->fetchAll();
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

<!-- Header Banner -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 1.8rem;">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;">
      <i class="fas fa-school text-primary"></i>
      <span>ACADEMIC SETUP &bull; CLASS &amp; FEE MANAGEMENT</span>
    </div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0; font-size: 1.85rem; font-weight: 800;">
      Academic Classes &amp; Fee Structure
    </h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.95rem;">
      Configure classes, admission fees, monthly tuition, room allocations, and primary class teachers.
    </p>
  </div>

  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" style="background: #2563eb; color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);" onclick="openModal('addClassModal')">
      <i class="fas fa-plus-circle"></i> Add New Class
    </button>
    <a href="subjects.php" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(2,132,199,0.25);">
      <i class="fas fa-book-open"></i> Manage Class Subjects &rarr;
    </a>
  </div>
</div>

<!-- Class Cards Grid Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.2rem; margin-bottom: 2rem;">
  <?php foreach ($classes as $c): ?>
    <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.4rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
      <div>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
          <div>
            <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.35rem; color: var(--admin-deep-navy); font-weight: 800;">
              <?= htmlspecialchars($c['class_name']) ?>
            </h3>
            <span style="font-size: 0.78rem; color: var(--admin-text-muted); font-weight: 600;">
              Age: <?= htmlspecialchars($c['age_group']) ?> &bull; Room: <strong><?= htmlspecialchars($c['room_no'] ?: 'TBA') ?></strong>
            </span>
          </div>
          <span class="badge badge-info" style="font-weight: 700; font-size: 0.78rem;">Section <?= htmlspecialchars($c['section']) ?></span>
        </div>

        <div style="background: #f8fafc; border-radius: 10px; padding: 10px 12px; margin: 12px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.85rem;">
          <div>
            <span style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; display: block;">Enrolled Students</span>
            <strong style="color: #0284c7; font-size: 1.1rem;"><i class="fas fa-user-graduate"></i> <?= $c['student_count'] ?></strong>
          </div>
          <div>
            <span style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; font-weight: 700; display: block;">Assigned Subjects</span>
            <strong style="color: #059669; font-size: 1.1rem;"><i class="fas fa-book-reader"></i> <?= $c['subject_count'] ?></strong>
          </div>
        </div>

        <div style="font-size: 0.82rem; margin-bottom: 8px;">
          <span style="color: #64748b;">Class Teacher:</span>
          <?php if ($c['primary_teacher_name']): ?>
            <strong style="color: #10b981; display: block;"><i class="fas fa-user-check"></i> <?= htmlspecialchars($c['primary_teacher_name']) ?></strong>
          <?php else: ?>
            <span style="color: #d97706; font-weight: 700; display: block;"><i class="fas fa-exclamation-triangle"></i> Not Assigned</span>
          <?php endif; ?>
        </div>

        <div style="display: flex; justify-content: space-between; border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-top: 8px; font-size: 0.85rem;">
          <div>
            <small style="color: #64748b; display: block;">Monthly Tuition</small>
            <strong style="color: var(--admin-primary); font-size: 1rem;"><?= format_currency($c['tuition_fee']) ?></strong>
          </div>
          <div style="text-align: right;">
            <small style="color: #64748b; display: block;">Admission Fee</small>
            <strong style="color: #0d9488; font-size: 1rem;"><?= format_currency($c['admission_fee']) ?></strong>
          </div>
        </div>
      </div>

      <div style="display: flex; gap: 8px; margin-top: 1.2rem; border-top: 1px solid #f1f5f9; padding-top: 10px;">
        <button onclick="editClassConfig(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn btn-sm" style="flex: 1; background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; font-weight: 700; border-radius: 8px; padding: 6px 10px; cursor: pointer;">
          <i class="fas fa-edit"></i> Edit Class
        </button>
        <a href="subjects.php?class_id=<?= $c['id'] ?>" class="btn btn-sm" style="flex: 1; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; border-radius: 8px; padding: 6px 10px; text-decoration: none; text-align: center;">
          <i class="fas fa-book-open"></i> Subjects (<?= $c['subject_count'] ?>)
        </a>
        <?php if ($c['student_count'] == 0 && $c['subject_count'] == 0): ?>
          <a href="classes.php?delete_class_id=<?= $c['id'] ?>" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-weight: 700; border-radius: 8px; padding: 6px 10px; text-decoration: none;" onclick="return confirm('Are you sure you want to delete this empty class?');" title="Delete Class">
            <i class="fas fa-trash-alt"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ========================================================
     MODAL 1: ADD NEW CLASS MODAL
     ======================================================== -->
<div id="addClassModal" class="admin-modal-wrap" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 1rem;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 620px; border-radius: 18px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #2563eb; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-school"></i> ACADEMIC EXPANSION
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.4rem;">
          Add New Academic Class
        </h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: #64748b;">
          Register a new class level (e.g. Year Three, Year Four, Hifz Section) for AL FATIHA.
        </p>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addClassModal')">&times;</button>
    </div>

    <form method="POST" action="classes.php">
      <input type="hidden" name="action_create_class" value="1">

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-top: 1.2rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Class Name *</label>
          <input type="text" name="class_name" class="form-control" placeholder="e.g. Year Three, Year Four, Hifz A" required style="font-weight: 700;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Section</label>
          <input type="text" name="section" class="form-control" value="A" placeholder="A, B, C">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Target Age Group *</label>
          <input type="text" name="age_group" class="form-control" placeholder="e.g. 7 - 8 Years" required>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Room Number</label>
          <input type="text" name="room_no" class="form-control" placeholder="e.g. Room 204">
        </div>
      </div>

      <!-- Fees Configuration Box -->
      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 14px; padding: 1.2rem; margin-top: 1.2rem;">
        <h4 style="margin: 0 0 10px 0; font-size: 0.92rem; color: var(--admin-deep-navy); font-weight: 800;">
          <i class="fas fa-coins text-gold"></i> Prescribed Class Fee Structure (BDT ৳)
        </h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700;">Monthly Tuition (৳) *</label>
            <input type="number" name="tuition_fee" value="3500" step="50" class="form-control" required style="font-weight: 800; color: var(--admin-primary);">
          </div>
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700;">Admission Fee (৳) *</label>
            <input type="number" name="admission_fee" value="8000" step="100" class="form-control" required style="font-weight: 800; color: #0d9488;">
          </div>
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700;">Book Kit Fee (৳) *</label>
            <input type="number" name="book_kit_fee" value="3500" step="50" class="form-control" required style="font-weight: 800; color: #d97706;">
          </div>
        </div>
      </div>

      <!-- Teachers Assignment -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1.2rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #10b981; font-size: 0.85rem;">Primary Class Teacher</label>
          <select name="primary_teacher_id" class="form-control">
            <option value="">-- Select Teacher --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #0284c7; font-size: 0.85rem;">Co-Teacher (Backup)</label>
          <select name="backup_teacher_id" class="form-control">
            <option value="">-- Select Co-Teacher (Optional) --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addClassModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #2563eb; color: #ffffff; font-weight: 800; padding: 0.7rem 1.6rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Create Class
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 2: EDIT CLASS CONFIG MODAL
     ======================================================== -->
<div id="editClassModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 18px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0; font-size: 1.35rem;">
        Edit Class Configuration (<span id="modalClassName" style="color: var(--admin-primary);"></span>)
      </h3>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted); line-height: 1;" onclick="closeModal('editClassModal')">&times;</button>
    </div>

    <form method="POST" action="classes.php">
      <input type="hidden" name="update_class_config" value="1">
      <input type="hidden" name="class_id" id="modalClassId">

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Class Title</label>
          <input type="text" name="class_name" id="modalClassNameInput" class="form-control" required style="font-weight: 700;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Age Group</label>
          <input type="text" name="age_group" id="modalAgeGroup" class="form-control" required>
        </div>
      </div>

      <!-- Fee Structure Box -->
      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 14px; padding: 1.2rem; margin-bottom: 1.2rem;">
        <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; color: var(--admin-deep-navy); display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-coins text-gold"></i> Class Fee Configuration
        </h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e293b;">Tuition (৳) *</label>
            <input type="number" name="tuition_fee" id="modalTuitionFee" step="50" class="form-control" required style="font-weight: 700; color: var(--admin-primary);">
          </div>
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e293b;">Admission (৳) *</label>
            <input type="number" name="admission_fee" id="modalAdmissionFee" step="100" class="form-control" required style="font-weight: 700; color: #0d9488;">
          </div>
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e293b;">Book Kit (৳) *</label>
            <input type="number" name="book_kit_fee" id="modalBookKitFee" step="50" class="form-control" required style="font-weight: 700; color: #d97706;">
          </div>
        </div>
      </div>

      <!-- Class Teacher Assignment -->
      <div class="form-group">
        <label style="font-weight: 700; color: #10b981;">Primary Class Teacher</label>
        <select name="primary_teacher_id" id="modalPrimaryTeacher" class="form-control">
          <option value="">-- Select Primary Class Teacher --</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['teacher_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 700; color: #0284c7;">Co-Teacher (Backup)</label>
        <select name="backup_teacher_id" id="modalBackupTeacher" class="form-control">
          <option value="">-- Select Backup Class Teacher (Optional) --</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['teacher_code'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 700;">Classroom Room No</label>
        <input type="text" name="room_no" id="modalRoomNo" class="form-control" required placeholder="e.g. 101, 102">
      </div>

      <div style="text-align: right; margin-top: 1.8rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('editClassModal')">Cancel</button>
        <button type="submit" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.8rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'flex';
  }
  function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
  }

  function editClassConfig(cls) {
    document.getElementById('modalClassId').value = cls.id;
    document.getElementById('modalClassName').innerText = cls.class_name;
    document.getElementById('modalClassNameInput').value = cls.class_name;
    document.getElementById('modalAgeGroup').value = cls.age_group || '';
    document.getElementById('modalPrimaryTeacher').value = cls.primary_teacher_id || '';
    document.getElementById('modalBackupTeacher').value = cls.backup_teacher_id || '';
    document.getElementById('modalTuitionFee').value = cls.tuition_fee;
    document.getElementById('modalAdmissionFee').value = cls.admission_fee || 8000;
    document.getElementById('modalBookKitFee').value = cls.book_kit_fee || 3500;
    document.getElementById('modalRoomNo').value = cls.room_no;
    openModal('editClassModal');
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>