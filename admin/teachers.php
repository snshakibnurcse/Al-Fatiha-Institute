<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Handle Add Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialty = sanitize($_POST['specialty'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? 'teacher123';

    if (empty($full_name) || empty($email) || empty($phone) || empty($username)) {
        $err = "Please fill in all required fields.";
    } else {
        try {
            $db->beginTransaction();

            // 1. Create User
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            $userStmt = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone) VALUES (?, ?, ?, 'teacher', ?, ?)");
            $userStmt->execute([$username, $pass_hash, $email, $full_name, $phone]);
            $user_id = $db->lastInsertId();

            // 2. Create Teacher
            $teacher_code = 'TCH-' . date('Y') . '-' . rand(10, 99);
            $tchStmt = $db->prepare("INSERT INTO teachers (user_id, teacher_code, full_name, qualification, specialty, email, phone, joining_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE())");
            $tchStmt->execute([$user_id, $teacher_code, $full_name, $qualification, $specialty, $email, $phone]);

            $db->commit();
            $msg = "Teacher <strong>$full_name</strong> ($teacher_code) added successfully! Login Username: <code>$username</code>";
        } catch (Exception $e) {
            $db->rollBack();
            $err = "Failed to add teacher: " . $e->getMessage();
        }
    }
}

// Handle Delete Teacher
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $db->prepare("DELETE FROM teachers WHERE id = ?")->execute([$del_id]);
    $msg = "Teacher deleted successfully.";
}

$teachers = $db->query("SELECT t.*, u.username FROM teachers t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.id ASC")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
  <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Teacher Staff Directory</h3>
  <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('addTeacherModal')">
    <i class="fas fa-plus"></i> Add New Teacher
  </button>
</div>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Teaching Faculty List</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Full Name</th>
          <th>Specialty</th>
          <th>Qualification</th>
          <th>Email & Phone</th>
          <th>Login User</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($teachers)): ?>
          <?php foreach ($teachers as $t): ?>
            <tr>
              <td><code><?= htmlspecialchars($t['teacher_code']) ?></code></td>
              <td><strong><?= htmlspecialchars($t['full_name']) ?></strong></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($t['specialty']) ?></span></td>
              <td><?= htmlspecialchars($t['qualification']) ?></td>
              <td><?= htmlspecialchars($t['email']) ?><br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($t['phone']) ?></small></td>
              <td><code><?= htmlspecialchars($t['username']) ?></code></td>
              <td>
                <a href="teachers.php?delete=<?= $t['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Delete teacher?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center;">No teachers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Teacher Modal -->
<div id="addTeacherModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 600px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Add New Teacher</h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addTeacherModal')">&times;</button>
    </div>

    <form method="POST">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" class="form-control" placeholder="e.g. Ustadh Ahmad Rahman" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" class="form-control" placeholder="teacher@example.com" required>
        </div>
        <div class="form-group">
          <label>Phone *</label>
          <input type="text" name="phone" class="form-control" placeholder="01800000000" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Qualification</label>
          <input type="text" name="qualification" class="form-control" placeholder="B.Ed, M.A. in Arabic">
        </div>
        <div class="form-group">
          <label>Subject Specialty *</label>
          <input type="text" name="specialty" class="form-control" placeholder="Arabic & Tajweed" required>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Login Username *</label>
          <input type="text" name="username" class="form-control" placeholder="teacher_username" required>
        </div>
        <div class="form-group">
          <label>Login Password *</label>
          <input type="password" name="password" class="form-control" value="teacher123" required>
        </div>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('addTeacherModal')">Cancel</button>
        <button type="submit" name="add_teacher" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Save Teacher</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
