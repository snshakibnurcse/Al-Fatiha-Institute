<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$db = get_db_connection();

$msg = '';
$err = '';
$flash = get_flash_msg();
if ($flash) {
    if ($flash['type'] === 'danger') $err = $flash['message']; else $msg = $flash['message'];
}

// Handle Add Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role_type = sanitize($_POST['role_type'] ?? 'teacher');
    if (!in_array($role_type, ['teacher', 'assistant_teacher', 'principal'], true)) {
        $role_type = 'teacher';
    }
    $defs = hr_role_definitions();
    $designation = $defs[$role_type]['label'];
    $user_role = $defs[$role_type]['user_role'] ?? 'teacher';

    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialty = sanitize($_POST['specialty'] ?? '');
    $joining_date = sanitize($_POST['joining_date'] ?? date('Y-m-d'));
    $basic_salary = floatval($_POST['basic_salary'] ?? 0);
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? 'teacher123';

    if (empty($full_name) || empty($email) || empty($phone) || empty($username) || empty($specialty)) {
        set_flash_msg("Please fill in all required fields (Name, Email, Phone, Specialty, Username).", 'danger');
    } else {
        try {
            $db->beginTransaction();

            // Check duplicate username
            $chkU = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $chkU->execute([$username]);
            if ($chkU->fetch()) {
                throw new Exception("Username '$username' is already taken. Please choose another username.");
            }

            // Check duplicate email
            $chkE = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $chkE->execute([$email]);
            if ($chkE->fetch()) {
                throw new Exception("Email '$email' is already registered with another account.");
            }

            // 1. Create User
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            $userStmt = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $userStmt->execute([$username, $pass_hash, $email, $user_role, $full_name, $phone]);
            $user_id = intval($db->lastInsertId());

            // 2. Generate Role-Prefixed ID & Insert into Unified Employees Directory
            $teacher_code = generate_employee_code($role_type);
            $empStmt = $db->prepare("
                INSERT INTO employees (employee_code, user_id, full_name, role_type, designation, department, phone, email, qualification, specialty, joining_date, basic_salary, status)
                VALUES (?, ?, ?, ?, ?, 'Academic', ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $empStmt->execute([$teacher_code, $user_id, $full_name, $role_type, $designation, $phone, $email, $qualification, $specialty, $joining_date, $basic_salary]);
            $employee_pk = intval($db->lastInsertId());
            record_employee_role_history($employee_pk, $teacher_code, $role_type, $designation, $joining_date, 'Initial Faculty Registration');

            // 3. Sync Teacher Record
            $tchStmt = $db->prepare("INSERT INTO teachers (user_id, employee_id, teacher_code, designation, full_name, qualification, specialty, email, phone, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            $tchStmt->execute([$user_id, $employee_pk, $teacher_code, $designation, $full_name, $qualification, $specialty, $email, $phone, $joining_date]);
            $newTeacherPk = intval($db->lastInsertId());
            $db->prepare("UPDATE employees SET teacher_id = ? WHERE id = ?")->execute([$newTeacherPk, $employee_pk]);

            $db->commit();
            set_flash_msg("$designation <strong>" . htmlspecialchars($full_name) . "</strong> (<code>$teacher_code</code>) added successfully! Login Username: <code>$username</code>", 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_msg("Failed to add teacher: " . $e->getMessage(), 'danger');
        }
    }
    redirect('teachers.php');
}

// Handle Edit Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_teacher'])) {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $user_id = intval($_POST['user_id'] ?? 0);
    $role_type = sanitize($_POST['role_type'] ?? 'teacher');
    if (!in_array($role_type, ['teacher', 'assistant_teacher', 'principal'], true)) {
        $role_type = 'teacher';
    }
    $defs = hr_role_definitions();
    $designation = $defs[$role_type]['label'];
    $user_role = $defs[$role_type]['user_role'] ?? 'teacher';

    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialty = sanitize($_POST['specialty'] ?? '');
    $joining_date = sanitize($_POST['joining_date'] ?? date('Y-m-d'));
    $username = sanitize($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');

    if ($teacher_id <= 0 || empty($full_name) || empty($email) || empty($phone) || empty($username) || empty($specialty)) {
        set_flash_msg("Please fill in all required fields (Name, Email, Phone, Specialty, Username).", 'danger');
        redirect('teachers.php');
    } else {
        try {
            $db->beginTransaction();

            $curTchStmt = $db->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
            $curTchStmt->execute([$teacher_id]);
            $curTch = $curTchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$curTch) throw new Exception("Teacher record not found.");

            // Check if username is taken by another user
            $userChk = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1");
            $userChk->execute([$username, $user_id]);
            if ($userChk->fetch()) {
                throw new Exception("Username '$username' is already in use by another account.");
            }

            // Check if email is taken by another user
            $emailChk = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1");
            $emailChk->execute([$email, $user_id]);
            if ($emailChk->fetch()) {
                throw new Exception("Email address '$email' is already in use by another account.");
            }

            // Update code if role prefix changed (e.g., Assistant Teacher -> Teacher)
            $newCode = $curTch['teacher_code'];
            $expectedPrefix = $defs[$role_type]['prefix'];
            if (strpos($newCode, $expectedPrefix) !== 0) {
                $newCode = generate_employee_code($role_type);
            }

            // 1. Update User record
            if ($user_id > 0) {
                if (!empty($password)) {
                    $pass_hash = password_hash($password, PASSWORD_BCRYPT);
                    $updUsr = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?");
                    $updUsr->execute([$full_name, $username, $email, $phone, $user_role, $status, $pass_hash, $user_id]);
                } else {
                    $updUsr = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $updUsr->execute([$full_name, $username, $email, $phone, $user_role, $status, $user_id]);
                }
            } else {
                $defaultPass = !empty($password) ? $password : 'teacher123';
                $pass_hash = password_hash($defaultPass, PASSWORD_BCRYPT);
                $newUsr = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $newUsr->execute([$username, $pass_hash, $email, $user_role, $full_name, $phone, $status]);
                $user_id = intval($db->lastInsertId());
            }

            // 2. Update Employee record (create if missing)
            $empStatus = ($status === 'active') ? 'Active' : 'Resigned';
            $empId = intval($curTch['employee_id'] ?? 0);
            if ($empId > 0) {
                $db->prepare("UPDATE employees SET user_id = ?, employee_code = ?, full_name = ?, role_type = ?, designation = ?, phone = ?, email = ?, qualification = ?, specialty = ?, joining_date = ?, status = ? WHERE id = ?")
                   ->execute([$user_id, $newCode, $full_name, $role_type, $designation, $phone, $email, $qualification, $specialty, $joining_date, $empStatus, $empId]);
            } else {
                $db->prepare("INSERT INTO employees (employee_code, user_id, teacher_id, full_name, role_type, designation, department, phone, email, qualification, specialty, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Academic', ?, ?, ?, ?, ?, ?)")
                   ->execute([$newCode, $user_id, $teacher_id, $full_name, $role_type, $designation, $phone, $email, $qualification, $specialty, $joining_date, $empStatus]);
                $empId = intval($db->lastInsertId());
            }
            if ($newCode !== $curTch['teacher_code']) {
                record_employee_role_history($empId, $newCode, $role_type, $designation, date('Y-m-d'), 'Role / Designation updated from Teachers Directory');
            }

            // 3. Update Teacher record
            $tchStatus = ($status === 'active') ? 'Active' : 'Inactive';
            $updTch = $db->prepare("
                UPDATE teachers 
                SET user_id = ?, employee_id = ?, teacher_code = ?, designation = ?, full_name = ?, qualification = ?, specialty = ?, email = ?, phone = ?, joining_date = ?, status = ?
                WHERE id = ?
            ");
            $updTch->execute([$user_id, $empId, $newCode, $designation, $full_name, $qualification, $specialty, $email, $phone, $joining_date, $tchStatus, $teacher_id]);

            $db->commit();
            set_flash_msg("$designation <strong>" . htmlspecialchars($full_name) . "</strong> (<code>$newCode</code>) updated successfully!", 'success');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_msg("Failed to update teacher: " . $e->getMessage(), 'danger');
        }
    }
    redirect('teachers.php');
}

// Handle Delete Teacher Safely
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $res = safe_delete_teacher_by_id($del_id);
    set_flash_msg($res['message'], $res['success'] ? 'success' : 'danger');
    redirect('teachers.php');
}

require_once __DIR__ . '/layout.php';

$teachers = $db->query("
    SELECT t.*, u.username, u.status as user_status, e.role_type
    FROM teachers t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN employees e ON t.employee_id = e.id
    ORDER BY t.id ASC
")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0; font-size: 1.5rem;">
      <i class="fas fa-chalkboard-teacher text-primary"></i> Teacher Staff Directory
    </h3>
    <p style="color: #64748b; margin: 2px 0 0 0; font-size: 0.85rem;">
      Manage teachers, update contact numbers, qualifications, specialties, and login access.
    </p>
  </div>
  <button class="btn btn-primary" style="background: var(--admin-primary); color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(14, 115, 185, 0.25);" onclick="openModal('addTeacherModal')">
    <i class="fas fa-plus-circle"></i> Add New Teacher
  </button>
</div>

<div class="card-box">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h3 class="card-title"><i class="fas fa-users"></i> Teaching Faculty List (<?= count($teachers) ?>)</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 115px;">Teacher ID</th>
          <th>Full Name &amp; Role</th>
          <th>Specialty</th>
          <th>Qualification</th>
          <th>Email &amp; Phone</th>
          <th>Username</th>
          <th style="width: 90px; text-align: center;">Status</th>
          <th style="width: 150px; text-align: center;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($teachers)): ?>
          <?php foreach ($teachers as $t): ?>
            <tr>
              <td><code style="font-weight: 700; color: #0284c7;"><?= htmlspecialchars($t['teacher_code']) ?></code></td>
              <td>
                <strong style="color: var(--admin-deep-navy); font-size: 0.95rem; display: block;"><?= htmlspecialchars($t['full_name']) ?></strong>
                <span class="badge" style="background: #eef2ff; color: #4338ca; font-size: 0.72rem; font-weight: 700; margin-top: 2px;"><?= htmlspecialchars($t['designation'] ?? 'Teacher') ?></span>
                <?php if (!empty($t['joining_date'])): ?>
                  <small style="color: #64748b; font-size: 0.74rem; margin-left: 4px;"><i class="fas fa-calendar-alt"></i> <?= format_date($t['joining_date']) ?></small>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info" style="font-weight: 700; font-size: 0.8rem;"><?= htmlspecialchars($t['specialty']) ?></span></td>
              <td><span style="font-size: 0.88rem; color: #334155;"><?= htmlspecialchars($t['qualification'] ?: 'N/A') ?></span></td>
              <td>
                <span style="display: block; font-size: 0.85rem; color: #334155;"><i class="fas fa-envelope" style="color: #64748b; font-size: 0.78rem;"></i> <?= htmlspecialchars($t['email']) ?></span>
                <span style="display: block; font-size: 0.85rem; color: #0369a1; font-weight: 600;"><i class="fas fa-phone-alt" style="font-size: 0.78rem;"></i> <?= htmlspecialchars($t['phone']) ?></span>
              </td>
              <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($t['username'] ?: 'N/A') ?></code></td>
              <td style="text-align: center;">
                <?php if (strtolower($t['user_status'] ?? 'active') === 'active'): ?>
                  <span class="badge badge-success" style="background: #dcfce7; color: #15803d; font-size: 0.76rem; padding: 3px 8px; font-weight: 700;">Active</span>
                <?php else: ?>
                  <span class="badge badge-danger" style="background: #fee2e2; color: #991b1b; font-size: 0.76rem; padding: 3px 8px; font-weight: 700;">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center; white-space: nowrap;">
                <div style="display: inline-flex; gap: 6px; align-items: center;">
                  <button type="button" class="btn" onclick='openEditTeacherModal(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)' style="background: #e0f2fe; color: #0284c7; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 700; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <a href="teachers.php?delete=<?= $t['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" onclick="return confirm('Are you sure you want to delete teacher <?= htmlspecialchars(addslashes($t['full_name'])) ?>?')">
                    <i class="fas fa-trash-alt"></i> Delete
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;"><i class="fas fa-user-slash" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.4;"></i> No teachers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========================================================
     MODAL 1: ADD NEW TEACHER
     ======================================================== -->
<div id="addTeacherModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 650px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.72rem; font-weight: 800; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase;">FACULTY ENROLLMENT</span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem; font-weight: 800;">
          Add New Teacher
        </h3>
      </div>
      <button type="button" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addTeacherModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Full Name *</label>
          <input type="text" name="full_name" class="form-control" required style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Faculty Role / Designation *</label>
          <select name="role_type" class="form-control" required style="border-radius: 8px; font-weight: 600;">
            <option value="teacher">Teacher (AFI-TC)</option>
            <option value="assistant_teacher">Assistant Teacher (AFI-AT)</option>
            <option value="principal">Principal (AFI-PR)</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Email Address *</label>
          <input type="email" name="email" class="form-control" required style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Phone / Mobile *</label>
          <input type="text" name="phone" class="form-control" required style="border-radius: 8px;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Qualification</label>
          <input type="text" name="qualification" class="form-control" style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Subject Specialty *</label>
          <input type="text" name="specialty" class="form-control" required style="border-radius: 8px;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Joining Date</label>
          <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Basic Monthly Salary (৳)</label>
          <input type="number" name="basic_salary" class="form-control" value="0" min="0" step="100" style="border-radius: 8px;">
        </div>
      </div>

      <div style="margin-top: 1.2rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
        <label style="font-weight: 800; color: var(--admin-deep-navy); font-size: 0.88rem; display: block; margin-bottom: 0.75rem;">
          <i class="fas fa-key text-primary"></i> Login Credentials
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Login Username *</label>
            <input type="text" name="username" class="form-control" required style="border-radius: 8px; font-weight: 600;">
          </div>
          <div class="form-group">
            <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Login Password *</label>
            <input type="password" name="password" class="form-control" value="teacher123" required style="border-radius: 8px;">
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.5rem; border-top: 1.5px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addTeacherModal')" style="padding: 0.6rem 1.4rem; border-radius: 8px;">Cancel</button>
        <button type="submit" name="add_teacher" class="btn btn-primary" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.6rem; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fas fa-check-circle"></i> Save Teacher
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 2: EDIT TEACHER DETAILS
     ======================================================== -->
<div id="editTeacherModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 650px; width: 95%;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.72rem; font-weight: 800; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase;">TEACHER PROFILE</span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem; font-weight: 800;">
          Edit Teacher Information
        </h3>
      </div>
      <button type="button" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('editTeacherModal')">&times;</button>
    </div>

    <form method="POST">
      <input type="hidden" name="edit_teacher" value="1">
      <input type="hidden" name="teacher_id" id="editTeacherId">
      <input type="hidden" name="user_id" id="editUserId">

      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Full Name *</label>
          <input type="text" name="full_name" id="editFullName" class="form-control" required style="border-radius: 8px; font-weight: 600;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Faculty Role *</label>
          <select name="role_type" id="editRoleType" class="form-control" required style="border-radius: 8px; font-weight: 600;">
            <option value="teacher">Teacher (AFI-TC)</option>
            <option value="assistant_teacher">Assistant Teacher (AFI-AT)</option>
            <option value="principal">Principal (AFI-PR)</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Teacher Code</label>
          <input type="text" id="editTeacherCode" class="form-control" readonly style="background: #f1f5f9; color: #64748b; font-weight: 700; border-radius: 8px;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Email Address *</label>
          <input type="email" name="email" id="editEmail" class="form-control" required style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Phone / Mobile *</label>
          <input type="text" name="phone" id="editPhone" class="form-control" required style="border-radius: 8px; font-weight: 600;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Qualification</label>
          <input type="text" name="qualification" id="editQualification" class="form-control" style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Subject Specialty *</label>
          <input type="text" name="specialty" id="editSpecialty" class="form-control" required style="border-radius: 8px;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.8rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Joining Date</label>
          <input type="date" name="joining_date" id="editJoiningDate" class="form-control" style="border-radius: 8px;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Account Status</label>
          <select name="status" id="editStatus" class="form-control" style="border-radius: 8px; font-weight: 600;">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Login Credentials Section -->
      <div style="margin-top: 1.2rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
        <label style="font-weight: 800; color: var(--admin-deep-navy); font-size: 0.88rem; display: block; margin-bottom: 0.75rem;">
          <i class="fas fa-key text-primary"></i> Login Credentials
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Username *</label>
            <input type="text" name="username" id="editUsername" class="form-control" required style="border-radius: 8px; font-weight: 600;">
          </div>
          <div class="form-group">
            <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">New Password</label>
            <input type="password" name="password" id="editPassword" class="form-control" style="border-radius: 8px;">
            <small style="color: #64748b; font-size: 0.74rem;">Leave blank to keep existing password.</small>
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.5rem; border-top: 1.5px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editTeacherModal')" style="padding: 0.6rem 1.4rem; border-radius: 8px;">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #2563eb; color: #ffffff; padding: 0.6rem 1.6rem; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) {
  const m = document.getElementById(id);
  if (m) {
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) {
    m.style.display = 'none';
    document.body.style.overflow = '';
  }
}

function openEditTeacherModal(tch) {
  if (!tch) return;
  document.getElementById('editTeacherId').value = tch.id || '';
  document.getElementById('editUserId').value = tch.user_id || '';
  document.getElementById('editFullName').value = tch.full_name || '';
  document.getElementById('editTeacherCode').value = tch.teacher_code || '';
  document.getElementById('editRoleType').value = tch.role_type || (tch.designation && tch.designation.toLowerCase().includes('assistant') ? 'assistant_teacher' : (tch.designation && tch.designation.toLowerCase().includes('principal') ? 'principal' : 'teacher'));
  document.getElementById('editEmail').value = tch.email || '';
  document.getElementById('editPhone').value = tch.phone || '';
  document.getElementById('editQualification').value = tch.qualification || '';
  document.getElementById('editSpecialty').value = tch.specialty || '';
  document.getElementById('editJoiningDate').value = tch.joining_date || '';
  document.getElementById('editUsername').value = tch.username || '';
  document.getElementById('editPassword').value = '';
  document.getElementById('editStatus').value = (tch.user_status || 'active').toLowerCase();

  openModal('editTeacherModal');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
