<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $dob = sanitize($_POST['dob'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 1);
    $roll_no = intval($_POST['roll_no'] ?? 1);
    $father_name = sanitize($_POST['father_name'] ?? '');
    $mother_name = sanitize($_POST['mother_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if (empty($first_name) || empty($dob) || empty($father_name) || empty($phone)) {
        $err = "Please fill in all required fields.";
    } else {
        try {
            $student_id = 'AFI-' . date('Y') . '-' . rand(100, 999);
            $stmt = $db->prepare("INSERT INTO students (student_id, first_name, last_name, gender, dob, class_id, roll_no, father_name, mother_name, phone, address, status, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', CURRENT_DATE())");
            $stmt->execute([$student_id, $first_name, $last_name, $gender, $dob, $class_id, $roll_no, $father_name, $mother_name, $phone, $address]);
            $msg = "New Student <strong>$first_name $last_name</strong> ($student_id) added successfully!";
        } catch (PDOException $e) {
            $err = "Failed to add student: " . $e->getMessage();
        }
    }
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([$del_id]);
    $msg = "Student deleted successfully.";
}

// Fetch Students with Class info
$students = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.class_id ASC, s.roll_no ASC")->fetchAll();
$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Students Directory</h3>
  <div style="display: flex; gap: 0.5rem;">
    <a href="promotions.php" class="btn" style="background: #f3b527; color: #032d4e; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 800; text-decoration: none;">
      <i class="fas fa-user-graduate"></i> Class Promotion Engine
    </a>
    <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('addStudentModal')">
      <i class="fas fa-plus"></i> Add New Student
    </button>
  </div>
</div>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-graduate"></i> Enrolled Students List</h3>
    <input type="text" id="tableSearch" placeholder="Search student name or ID..." class="form-control" style="width: 250px; padding: 0.4rem 0.8rem; font-size: 0.85rem;">
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Class</th>
          <th>Roll No</th>
          <th>Gender</th>
          <th>Father / Phone</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($students)): ?>
          <?php foreach ($students as $st): ?>
            <tr>
              <td><code><?= htmlspecialchars($st['student_id']) ?></code></td>
              <td>
                <a href="student_profile.php?id=<?= $st['id'] ?>" style="color: var(--admin-primary); text-decoration: none; font-weight: 700;">
                  <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?>
                </a>
              </td>
              <td><span class="badge badge-info"><?= htmlspecialchars($st['class_name']) ?></span></td>
              <td><strong>#<?= $st['roll_no'] ?></strong></td>
              <td><?= $st['gender'] ?></td>
              <td><?= htmlspecialchars($st['father_name']) ?><br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($st['phone']) ?></small></td>
              <td><span class="badge badge-success"><?= $st['status'] ?></span></td>
              <td>
                <a href="student_profile.php?id=<?= $st['id'] ?>" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none; margin-right: 0.2rem;">
                  <i class="fas fa-eye"></i> Profile
                </a>
                <a href="students.php?delete=<?= $st['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Delete this student?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center;">No students found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addStudentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Add New Student</h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addStudentModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>First Name *</label>
          <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Class *</label>
          <select name="class_id" class="form-control" required>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Roll No *</label>
          <input type="number" name="roll_no" class="form-control" value="1" required>
        </div>
        <div class="form-group">
          <label>Gender *</label>
          <select name="gender" class="form-control" required>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Date of Birth *</label>
          <input type="date" name="dob" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Phone Number *</label>
          <input type="text" name="phone" class="form-control" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Father's Name *</label>
          <input type="text" name="father_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Mother's Name</label>
          <input type="text" name="mother_name" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label>Address</label>
        <textarea name="address" class="form-control" rows="2"></textarea>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('addStudentModal')">Cancel</button>
        <button type="submit" name="add_student" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Save Student</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
