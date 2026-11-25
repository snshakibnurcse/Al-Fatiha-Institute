<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';
$defs = hr_role_definitions();

// 1. POST: Add New Employee (All Roles: Principal, Teacher, Assistant Teacher, HR, Accountant, Admin, Support Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_employee'])) {
    $role_type = sanitize($_POST['role_type'] ?? 'support_staff');
    if (!isset($defs[$role_type])) $role_type = 'support_staff';

    $full_name = sanitize($_POST['full_name'] ?? '');
    $designation = sanitize($_POST['designation'] ?? '') ?: $defs[$role_type]['label'];
    $department = sanitize($_POST['department'] ?? '') ?: $defs[$role_type]['department'];
    $employment_type = sanitize($_POST['employment_type'] ?? 'Full-Time');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $nid_no = sanitize($_POST['nid_no'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialty = sanitize($_POST['specialty'] ?? '');
    $joining_date = sanitize($_POST['joining_date'] ?? date('Y-m-d'));

    $basic_salary = max(0, floatval($_POST['basic_salary'] ?? 0));
    $house_rent = max(0, floatval($_POST['house_rent'] ?? 0));
    $medical_allowance = max(0, floatval($_POST['medical_allowance'] ?? 0));
    $transport_allowance = max(0, floatval($_POST['transport_allowance'] ?? 0));
    $other_allowance = max(0, floatval($_POST['other_allowance'] ?? 0));
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $bank_account = sanitize($_POST['bank_account'] ?? '');

    $create_login = isset($_POST['create_login']) && $_POST['create_login'] === '1';
    $username = strtolower(trim(sanitize($_POST['username'] ?? '')));
    $password = $_POST['password'] ?? 'staff123';

    if (empty($full_name) || empty($phone)) {
        $err = "Full name and phone number are required.";
    } elseif ($create_login && empty($username)) {
        $err = "Login username is required when portal access is enabled.";
    } else {
        try {
            $db->beginTransaction();

            $user_id = null;
            if ($create_login) {
                $userRole = $defs[$role_type]['user_role'];
                if (!$userRole) {
                    throw new Exception("Support Staff members do not require a portal login role.");
                }
                $chkU = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
                $chkU->execute([$username]);
                if ($chkU->fetch()) {
                    throw new Exception("Username '$username' is already in use.");
                }
                if (!empty($email)) {
                    $chkE = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                    $chkE->execute([$email]);
                    if ($chkE->fetch()) {
                        throw new Exception("Email '$email' is already registered.");
                    }
                }
                $passHash = password_hash($password ?: 'staff123', PASSWORD_BCRYPT);
                $userEmail = $email ?: ($username . '@alfatiha.edu.bd');
                $uStmt = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $uStmt->execute([$username, $passHash, $userEmail, $userRole, $full_name, $phone]);
                $user_id = intval($db->lastInsertId());
            }

            $empCode = generate_employee_code($role_type);
            $insEmp = $db->prepare("INSERT INTO employees (
                employee_code, user_id, full_name, role_type, designation, department, employment_type, gender,
                phone, email, nid_no, address, qualification, specialty, joining_date,
                basic_salary, house_rent, medical_allowance, transport_allowance, other_allowance,
                payment_method, bank_account, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            $insEmp->execute([
                $empCode, $user_id, $full_name, $role_type, $designation, $department, $employment_type, $gender,
                $phone, $email ?: null, $nid_no ?: null, $address ?: null, $qualification ?: null, $specialty ?: null, $joining_date,
                $basic_salary, $house_rent, $medical_allowance, $transport_allowance, $other_allowance,
                $payment_method, $bank_account ?: null
            ]);
            $empPk = intval($db->lastInsertId());
            record_employee_role_history($empPk, $empCode, $role_type, $designation, $joining_date, 'Added via HR Employee Directory');

            if ($defs[$role_type]['is_teacher']) {
                sync_teacher_row_from_employee($empPk);
            }

            $db->commit();
            $msg = "Employee <strong>" . htmlspecialchars($full_name) . "</strong> added with ID <code>$empCode</code>!";
            if ($create_login) {
                $msg .= " Portal Login: <code>$username</code>";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $err = "Failed to add employee: " . $e->getMessage();
        }
    }
}

// 2. POST: Edit Employee & Compensation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_employee'])) {
    $emp_pk = intval($_POST['employee_pk'] ?? 0);
    $role_type = sanitize($_POST['role_type'] ?? 'support_staff');
    if (!isset($defs[$role_type])) $role_type = 'support_staff';

    $full_name = sanitize($_POST['full_name'] ?? '');
    $designation = sanitize($_POST['designation'] ?? '') ?: $defs[$role_type]['label'];
    $department = sanitize($_POST['department'] ?? '') ?: $defs[$role_type]['department'];
    $employment_type = sanitize($_POST['employment_type'] ?? 'Full-Time');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $nid_no = sanitize($_POST['nid_no'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $specialty = sanitize($_POST['specialty'] ?? '');
    $joining_date = sanitize($_POST['joining_date'] ?? date('Y-m-d'));
    $status = sanitize($_POST['status'] ?? 'Active');

    $basic_salary = max(0, floatval($_POST['basic_salary'] ?? 0));
    $house_rent = max(0, floatval($_POST['house_rent'] ?? 0));
    $medical_allowance = max(0, floatval($_POST['medical_allowance'] ?? 0));
    $transport_allowance = max(0, floatval($_POST['transport_allowance'] ?? 0));
    $other_allowance = max(0, floatval($_POST['other_allowance'] ?? 0));
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $bank_account = sanitize($_POST['bank_account'] ?? '');

    if ($emp_pk <= 0 || empty($full_name) || empty($phone)) {
        $err = "Employee full name and phone number are required.";
    } else {
        try {
            $db->beginTransaction();
            $curStmt = $db->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
            $curStmt->execute([$emp_pk]);
            $cur = $curStmt->fetch(PDO::FETCH_ASSOC);
            if (!$cur) throw new Exception("Employee not found.");

            $newCode = $cur['employee_code'];
            $expectedPrefix = $defs[$role_type]['prefix'];
            if (strpos($newCode, $expectedPrefix) !== 0) {
                $newCode = generate_employee_code($role_type);
                record_employee_role_history($emp_pk, $newCode, $role_type, $designation, date('Y-m-d'), "Role changed from {$cur['designation']} ({$cur['employee_code']})");
            }

            $db->prepare("UPDATE employees SET
                employee_code = ?, full_name = ?, role_type = ?, designation = ?, department = ?,
                employment_type = ?, gender = ?, phone = ?, email = ?, nid_no = ?, address = ?,
                qualification = ?, specialty = ?, joining_date = ?,
                basic_salary = ?, house_rent = ?, medical_allowance = ?, transport_allowance = ?, other_allowance = ?,
                payment_method = ?, bank_account = ?, status = ?
                WHERE id = ?")
               ->execute([
                   $newCode, $full_name, $role_type, $designation, $department,
                   $employment_type, $gender, $phone, $email ?: null, $nid_no ?: null, $address ?: null,
                   $qualification ?: null, $specialty ?: null, $joining_date,
                   $basic_salary, $house_rent, $medical_allowance, $transport_allowance, $other_allowance,
                   $payment_method, $bank_account ?: null, $status, $emp_pk
               ]);

            if (!empty($cur['user_id'])) {
                $uStatus = ($status === 'Active' || $status === 'On Leave') ? 'active' : 'inactive';
                $uRole = $defs[$role_type]['user_role'];
                if ($uRole) {
                    $db->prepare("UPDATE users SET full_name = ?, phone = ?, status = ?, role = CASE WHEN role = 'super_admin' THEN 'super_admin' ELSE ? END WHERE id = ?")
                       ->execute([$full_name, $phone, $uStatus, $uRole, $cur['user_id']]);
                } else {
                    $db->prepare("UPDATE users SET full_name = ?, phone = ?, status = ? WHERE id = ?")
                       ->execute([$full_name, $phone, $uStatus, $cur['user_id']]);
                }
            }

            if ($defs[$role_type]['is_teacher'] || !empty($cur['teacher_id'])) {
                sync_teacher_row_from_employee($emp_pk);
            }

            $db->commit();
            $msg = "Employee <strong>" . htmlspecialchars($full_name) . "</strong> (<code>$newCode</code>) updated successfully!";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $err = "Failed to update employee: " . $e->getMessage();
        }
    }
}

// 3. GET: Safe Delete Employee
if (isset($_GET['delete'])) {
    $del_pk = intval($_GET['delete']);
    $res = safe_delete_employee($del_pk, false);
    if ($res['success']) {
        $msg = $res['message'];
    } else {
        $err = $res['message'];
    }
}

$roleFilter = sanitize($_GET['role_type'] ?? 'all');
$statusFilter = sanitize($_GET['status'] ?? 'all');
$q = sanitize($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];
if ($roleFilter !== 'all') {
    $where[] = "e.role_type = ?";
    $params[] = $roleFilter;
}
if ($statusFilter !== 'all') {
    $where[] = "e.status = ?";
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.designation LIKE ? OR e.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$stEmps = $db->prepare("
    SELECT e.*, u.username,
           (SELECT GROUP_CONCAT(CONCAT(employee_code, ' (', designation, ')') SEPARATOR ' -> ') FROM employee_role_history erh WHERE erh.employee_pk = e.id) as id_history
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.role_type ASC, e.employee_code ASC
");
$stEmps->execute($params);
$employees = $stEmps->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Unified Employee &amp; Staff Directory</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Manage Principal, Teachers, Assistant Teachers, HR, Accountants, Admin, and Support Staff with AFI prefix IDs &amp; salary structures.</p>
  </div>
  <button type="button" class="btn btn-primary" style="background: #0e73b9; color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('addEmployeeModal')">
    <i class="fas fa-user-plus"></i> Add Employee
  </button>
</div>

<!-- Filter Bar -->
<div class="card-box" style="margin-bottom: 1.25rem;">
  <form method="GET" style="display: grid; grid-template-columns: 2fr 1.2fr 1.2fr auto; gap: 12px; align-items: flex-end;">
    <div>
      <label style="font-size: 0.82rem; font-weight: 700;">Search Staff</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control">
    </div>
    <div>
      <label style="font-size: 0.82rem; font-weight: 700;">Role Category</label>
      <select name="role_type" class="form-control" onchange="this.form.submit()">
        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
        <?php foreach ($defs as $k => $info): ?>
          <option value="<?= $k ?>" <?= $roleFilter === $k ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?> (<?= rtrim($info['prefix'], '-') ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size: 0.82rem; font-weight: 700;">Employment Status</label>
      <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
        <option value="On Leave" <?= $statusFilter === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
        <option value="Resigned" <?= $statusFilter === 'Resigned' ? 'selected' : '' ?>>Resigned</option>
        <option value="Terminated" <?= $statusFilter === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
      </select>
    </div>
    <div style="display: flex; gap: 6px;">
      <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; padding: 0.6rem 1.1rem; border-radius: 8px; border: none; font-weight: 700;">Filter</button>
      <a href="employees.php" class="btn btn-secondary" style="padding: 0.6rem 0.9rem; border-radius: 8px; text-decoration: none;">Reset</a>
    </div>
  </form>
</div>

<!-- Directory Table -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-id-badge"></i> Staff Records (<?= count($employees) ?>)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff ID</th>
          <th>Name &amp; Department</th>
          <th>Designation / Role</th>
          <th>Contact</th>
          <th>Gross Salary</th>
          <th>Portal Login</th>
          <th>Status</th>
          <th style="text-align: center;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($employees)): ?>
          <?php foreach ($employees as $e): ?>
            <?php $gross = floatval($e['basic_salary']) + floatval($e['house_rent']) + floatval($e['medical_allowance']) + floatval($e['transport_allowance']) + floatval($e['other_allowance']); ?>
            <tr>
              <td>
                <code style="font-weight: 700; color: #0284c7;"><?= htmlspecialchars($e['employee_code']) ?></code>
                <?php if (!empty($e['id_history']) && substr_count($e['id_history'], '->') > 0): ?>
                  <br><small style="color:#64748b; font-size:0.7rem;" title="<?= htmlspecialchars($e['id_history']) ?>"><i class="fas fa-history"></i> Role History</small>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($e['full_name']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($e['department']) ?> &bull; <?= htmlspecialchars($e['employment_type']) ?></small>
              </td>
              <td>
                <span class="badge badge-info"><?= htmlspecialchars($e['designation']) ?></span>
                <?php if (!empty($e['joining_date'])): ?>
                  <br><small style="color: #64748b;">Joined: <?= format_date($e['joining_date']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($e['phone']) ?>
                <?php if (!empty($e['email'])): ?>
                  <br><small style="color: #64748b;"><?= htmlspecialchars($e['email']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= format_currency($gross) ?></strong>
                <br><small style="color: #64748b;">Basic: <?= format_currency($e['basic_salary']) ?></small>
              </td>
              <td>
                <?php if (!empty($e['username'])): ?>
                  <code>@<?= htmlspecialchars($e['username']) ?></code>
                <?php else: ?>
                  <span style="color: #94a3b8; font-size: 0.8rem;">No Login</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $e['status'] === 'Active' ? 'badge-success' : ($e['status'] === 'On Leave' ? 'badge-warning' : 'badge-danger') ?>">
                  <?= htmlspecialchars($e['status']) ?>
                </span>
              </td>
              <td style="text-align: center; white-space: nowrap;">
                <button type="button" class="btn" style="background: #e0f2fe; color: #0284c7; padding: 0.35rem 0.7rem; font-size: 0.8rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer;" onclick='openEditEmployeeModal(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'>
                  <i class="fas fa-edit"></i> Edit
                </button>
                <a href="employees.php?delete=<?= $e['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.35rem 0.7rem; font-size: 0.8rem; font-weight: 700; border-radius: 6px; text-decoration: none;" onclick="return confirm('Delete or deactivate employee <?= htmlspecialchars(addslashes($e['full_name'])) ?>?')">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No employee records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD EMPLOYEE MODAL -->
<div id="addEmployeeModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 760px; width: 95%; max-height: 92vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem; margin-bottom: 1rem;">
      <div>
        <span style="font-size: 0.72rem; font-weight: 800; color: #0e73b9; text-transform: uppercase;">HR ONBOARDING</span>
        <h3 style="margin: 2px 0 0 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Register New Staff / Employee</h3>
      </div>
      <button type="button" onclick="closeModal('addEmployeeModal')" style="background: none; border: none; font-size: 1.6rem; cursor: pointer;">&times;</button>
    </div>

    <form method="POST">
      <input type="hidden" name="action_add_employee" value="1">
      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Staff Role Category *</label>
          <select name="role_type" id="addEmpRole" class="form-control" required onchange="onAddEmpRoleChange(this.value)">
            <?php foreach ($defs as $k => $info): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($info['label']) ?> (<?= rtrim($info['prefix'], '-') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Exact Designation *</label>
          <input type="text" name="designation" id="addEmpDesig" class="form-control" value="Principal" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Department</label>
          <input type="text" name="department" id="addEmpDept" class="form-control" value="Leadership">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Full Name *</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Phone *</label>
          <input type="text" name="phone" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Gender</label>
          <select name="gender" class="form-control">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Email</label>
          <input type="email" name="email" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">NID Number</label>
          <input type="text" name="nid_no" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Joining Date</label>
          <input type="date" name="joining_date" value="<?= date('Y-m-d') ?>" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Employment Type</label>
          <select name="employment_type" class="form-control">
            <option value="Full-Time">Full-Time</option>
            <option value="Part-Time">Part-Time</option>
            <option value="Contract">Contract</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Qualification</label>
          <input type="text" name="qualification" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.82rem; font-weight: 700;">Specialty / Duty</label>
          <input type="text" name="specialty" class="form-control">
        </div>
      </div>

      <div class="form-group" style="margin-top: 0.75rem;">
        <label style="font-size: 0.82rem; font-weight: 700;">Address</label>
        <input type="text" name="address" class="form-control">
      </div>

      <!-- Salary Setup Box -->
      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 1rem; margin-top: 1rem;">
        <h4 style="margin: 0 0 10px 0; font-size: 0.92rem; color: var(--admin-deep-navy);"><i class="fas fa-coins text-gold"></i> Monthly Salary Structure (৳)</h4>
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Basic Salary</label>
            <input type="number" name="basic_salary" value="0" min="0" step="100" class="form-control" required>
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">House Rent</label>
            <input type="number" name="house_rent" value="0" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Medical</label>
            <input type="number" name="medical_allowance" value="0" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Transport</label>
            <input type="number" name="transport_allowance" value="0" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Other</label>
            <input type="number" name="other_allowance" value="0" min="0" step="50" class="form-control">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-top: 10px;">
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Payment Method</label>
            <select name="payment_method" class="form-control">
              <option value="Cash">Cash</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Bank Account / Mobile Wallet No</label>
            <input type="text" name="bank_account" class="form-control">
          </div>
        </div>
      </div>

      <!-- Optional Portal Login -->
      <div id="addPortalLoginWrap" style="background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 1rem; margin-top: 1rem;">
        <label style="font-weight: 700; color: #0369a1; font-size: 0.85rem; cursor: pointer;">
          <input type="checkbox" name="create_login" id="addCreateLoginCb" value="1" onchange="toggleAddLoginFields()"> Also Create Portal Login Account
        </label>
        <div id="addLoginFields" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Login Username *</label>
            <input type="text" name="username" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Initial Password</label>
            <input type="text" name="password" value="staff123" class="form-control" style="font-family: monospace;">
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addEmployeeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.4rem; border-radius: 8px;">Save Employee</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT EMPLOYEE MODAL -->
<div id="editEmployeeModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 760px; width: 95%; max-height: 92vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem; margin-bottom: 1rem;">
      <div>
        <span style="font-size: 0.72rem; font-weight: 800; color: #0e73b9; text-transform: uppercase;">UPDATE RECORD</span>
        <h3 style="margin: 2px 0 0 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy);">Edit Employee &amp; Salary Structure</h3>
      </div>
      <button type="button" onclick="closeModal('editEmployeeModal')" style="background: none; border: none; font-size: 1.6rem; cursor: pointer;">&times;</button>
    </div>

    <form method="POST">
      <input type="hidden" name="action_edit_employee" value="1">
      <input type="hidden" name="employee_pk" id="editEmpPk">

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Staff ID</label>
          <input type="text" id="editEmpCode" class="form-control" readonly style="background: #f1f5f9; font-weight: 700;">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Role Category *</label>
          <select name="role_type" id="editEmpRole" class="form-control" required>
            <?php foreach ($defs as $k => $info): ?>
              <option value="<?= $k ?>"><?= htmlspecialchars($info['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Designation *</label>
          <input type="text" name="designation" id="editEmpDesig" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Department</label>
          <input type="text" name="department" id="editEmpDept" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Full Name *</label>
          <input type="text" name="full_name" id="editEmpName" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Phone *</label>
          <input type="text" name="phone" id="editEmpPhone" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Status</label>
          <select name="status" id="editEmpStatus" class="form-control">
            <option value="Active">Active</option>
            <option value="On Leave">On Leave</option>
            <option value="Resigned">Resigned</option>
            <option value="Terminated">Terminated</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Email</label>
          <input type="email" name="email" id="editEmpEmail" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">NID No</label>
          <input type="text" name="nid_no" id="editEmpNid" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Joining Date</label>
          <input type="date" name="joining_date" id="editEmpJoin" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Gender</label>
          <select name="gender" id="editEmpGender" class="form-control">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 0.75rem;">
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Employment Type</label>
          <select name="employment_type" id="editEmpType" class="form-control">
            <option value="Full-Time">Full-Time</option>
            <option value="Part-Time">Part-Time</option>
            <option value="Contract">Contract</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Qualification</label>
          <input type="text" name="qualification" id="editEmpQual" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-size: 0.8rem; font-weight: 700;">Specialty / Duty</label>
          <input type="text" name="specialty" id="editEmpSpec" class="form-control">
        </div>
      </div>

      <div class="form-group" style="margin-top: 0.75rem;">
        <label style="font-size: 0.8rem; font-weight: 700;">Address</label>
        <input type="text" name="address" id="editEmpAddr" class="form-control">
      </div>

      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 1rem; margin-top: 1rem;">
        <h4 style="margin: 0 0 10px 0; font-size: 0.92rem; color: var(--admin-deep-navy);"><i class="fas fa-coins text-gold"></i> Monthly Salary Structure (৳)</h4>
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Basic</label>
            <input type="number" name="basic_salary" id="editEmpBasic" min="0" step="100" class="form-control" required>
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">House Rent</label>
            <input type="number" name="house_rent" id="editEmpRent" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Medical</label>
            <input type="number" name="medical_allowance" id="editEmpMed" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Transport</label>
            <input type="number" name="transport_allowance" id="editEmpTrans" min="0" step="50" class="form-control">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700;">Other</label>
            <input type="number" name="other_allowance" id="editEmpOther" min="0" step="50" class="form-control">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-top: 10px;">
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Payment Method</label>
            <select name="payment_method" id="editEmpPayMethod" class="form-control">
              <option value="Cash">Cash</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.78rem; font-weight: 700;">Bank Account / Wallet</label>
            <input type="text" name="bank_account" id="editEmpBank" class="form-control">
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editEmployeeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.4rem; border-radius: 8px;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
const ROLE_DEFS = <?= json_encode($defs) ?>;
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
}
function onAddEmpRoleChange(role) {
  const def = ROLE_DEFS[role];
  if (def) {
    document.getElementById('addEmpDesig').value = def.label;
    document.getElementById('addEmpDept').value = def.department;
    const loginWrap = document.getElementById('addPortalLoginWrap');
    if (role === 'support_staff') {
      document.getElementById('addCreateLoginCb').checked = false;
      loginWrap.style.display = 'none';
      toggleAddLoginFields();
    } else {
      loginWrap.style.display = 'block';
    }
  }
}
function toggleAddLoginFields() {
  const cb = document.getElementById('addCreateLoginCb');
  document.getElementById('addLoginFields').style.display = cb && cb.checked ? 'grid' : 'none';
}
function openEditEmployeeModal(e) {
  document.getElementById('editEmpPk').value = e.id;
  document.getElementById('editEmpCode').value = e.employee_code;
  document.getElementById('editEmpRole').value = e.role_type;
  document.getElementById('editEmpDesig').value = e.designation || '';
  document.getElementById('editEmpDept').value = e.department || '';
  document.getElementById('editEmpName').value = e.full_name || '';
  document.getElementById('editEmpPhone').value = e.phone || '';
  document.getElementById('editEmpStatus').value = e.status || 'Active';
  document.getElementById('editEmpEmail').value = e.email || '';
  document.getElementById('editEmpNid').value = e.nid_no || '';
  document.getElementById('editEmpJoin').value = e.joining_date || '';
  document.getElementById('editEmpGender').value = e.gender || 'Male';
  document.getElementById('editEmpType').value = e.employment_type || 'Full-Time';
  document.getElementById('editEmpQual').value = e.qualification || '';
  document.getElementById('editEmpSpec').value = e.specialty || '';
  document.getElementById('editEmpAddr').value = e.address || '';
  document.getElementById('editEmpBasic').value = e.basic_salary || 0;
  document.getElementById('editEmpRent').value = e.house_rent || 0;
  document.getElementById('editEmpMed').value = e.medical_allowance || 0;
  document.getElementById('editEmpTrans').value = e.transport_allowance || 0;
  document.getElementById('editEmpOther').value = e.other_allowance || 0;
  document.getElementById('editEmpPayMethod').value = e.payment_method || 'Cash';
  document.getElementById('editEmpBank').value = e.bank_account || '';
  openModal('editEmployeeModal');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>

