<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';
$newCredentials = null;
$currentAdminId = get_current_user_id();

// ========================================================
// 1. POST HANDLER: CREATE NEW USER
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_user'])) {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $username = strtolower(trim(sanitize($_POST['username'] ?? '')));
    $email = strtolower(trim(sanitize($_POST['email'] ?? '')));
    $phone = sanitize($_POST['phone'] ?? '');
    $role = sanitize($_POST['role'] ?? 'parent');
    $password = $_POST['password'] ?? '';
    $status = sanitize($_POST['status'] ?? 'active');
    $linked_student_id = intval($_POST['linked_student_id'] ?? 0);
    $specialty = sanitize($_POST['specialty'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');

    $allowedRoles = ['admin', 'accountant', 'teacher', 'parent'];
    if (!in_array($role, $allowedRoles)) {
        $role = 'parent';
    }

    if (empty($full_name) || empty($username) || empty($password)) {
        $err = "Full name, username, and password are required fields.";
    } elseif (strlen($password) < 6) {
        $err = "Password must be at least 6 characters long.";
    } else {
        // Check uniqueness of username and email
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $err = "Username '<strong>" . htmlspecialchars($username) . "</strong>' is already taken. Please choose another.";
        } else {
            if (!empty($email)) {
                $chkEmail = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $chkEmail->execute([$email]);
                if ($chkEmail->fetch()) {
                    $err = "Email '<strong>" . htmlspecialchars($email) . "</strong>' is already registered to another account.";
                }
            }

            if (empty($err)) {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $db->prepare("
                        INSERT INTO users (username, password, email, role, full_name, phone, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$username, $hashedPassword, $email, $role, $full_name, $phone, $status]);
                    $newUserId = $db->lastInsertId();

                    // Role-specific linkages
                    if ($role === 'parent' && $linked_student_id > 0) {
                        $db->prepare("UPDATE students SET parent_user_id = ? WHERE id = ?")->execute([$newUserId, $linked_student_id]);
                    } elseif ($role === 'teacher') {
                        $tchCode = 'TCH-' . date('Y') . '-' . str_pad($newUserId, 3, '0', STR_PAD_LEFT);
                        $insTch = $db->prepare("
                            INSERT INTO teachers (user_id, teacher_code, full_name, qualification, specialty, email, phone, joining_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insTch->execute([$newUserId, $tchCode, $full_name, $qualification ?: 'B.Ed / M.A', $specialty ?: 'Islamic Studies', $email, $phone, date('Y-m-d')]);
                    }

                    $newCredentials = [
                        'name' => $full_name,
                        'username' => $username,
                        'password' => $password,
                        'role' => $role
                    ];
                    $msg = "New " . ucfirst($role) . " account for <strong>" . htmlspecialchars($full_name) . "</strong> created successfully!";
                } catch (Exception $e) {
                    $err = "Error creating account: " . $e->getMessage();
                }
            }
        }
    }
}

// ========================================================
// 2. POST HANDLER: RESET USER PASSWORD
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset_password'])) {
    $target_user_id = intval($_POST['target_user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    if ($target_user_id > 0 && !empty($new_password)) {
        if (strlen($new_password) < 6) {
            $err = "New password must be at least 6 characters long.";
        } else {
            $uStmt = $db->prepare("SELECT id, username, full_name, role FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$target_user_id]);
            $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$hashedPassword, $target_user_id]);

                $newCredentials = [
                    'name' => $targetUser['full_name'],
                    'username' => $targetUser['username'],
                    'password' => $new_password,
                    'role' => $targetUser['role']
                ];
                $msg = "Password for user <strong>" . htmlspecialchars($targetUser['full_name']) . " (@" . htmlspecialchars($targetUser['username']) . ")</strong> has been successfully reset!";
            } else {
                $err = "Target user account not found.";
            }
        }
    } else {
        $err = "Please enter a valid password.";
    }
}

// ========================================================
// 3. POST HANDLER: UPDATE USER DETAILS
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_user'])) {
    $edit_user_id = intval($_POST['edit_user_id'] ?? 0);
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = strtolower(trim(sanitize($_POST['email'] ?? '')));
    $phone = sanitize($_POST['phone'] ?? '');
    $role = sanitize($_POST['role'] ?? 'parent');
    $status = sanitize($_POST['status'] ?? 'active');
    $linked_student_id = intval($_POST['linked_student_id'] ?? 0);

    if ($edit_user_id > 0 && !empty($full_name)) {
        // Prevent Super Admin from changing their own active status to inactive or locking self
        if ($edit_user_id === $currentAdminId && $status === 'inactive') {
            $err = "Security restriction: You cannot deactivate your own active session administrator account.";
        } else {
            // Check email uniqueness if modified
            $chkEmail = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chkEmail->execute([$email, $edit_user_id]);
            if (!empty($email) && $chkEmail->fetch()) {
                $err = "Email '<strong>" . htmlspecialchars($email) . "</strong>' is already in use by another user.";
            } else {
                $upd = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                $upd->execute([$full_name, $email, $phone, $role, $status, $edit_user_id]);

                // Update linked student if guardian
                if ($role === 'parent') {
                    // Clear previous link
                    $db->prepare("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?")->execute([$edit_user_id]);
                    if ($linked_student_id > 0) {
                        $db->prepare("UPDATE students SET parent_user_id = ? WHERE id = ?")->execute([$edit_user_id, $linked_student_id]);
                    }
                }

                $msg = "Account details for <strong>" . htmlspecialchars($full_name) . "</strong> updated successfully!";
            }
        }
    } else {
        $err = "Full name is required.";
    }
}

// ========================================================
// 4. GET HANDLER: TOGGLE STATUS (ACTIVE / INACTIVE)
// ========================================================
if (isset($_GET['toggle_status'])) {
    $toggle_id = intval($_GET['toggle_status']);
    if ($toggle_id === $currentAdminId) {
        $err = "Security restriction: You cannot change status on your own logged-in account.";
    } else {
        $uStmt = $db->prepare("SELECT id, status, full_name FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$toggle_id]);
        $target = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($target) {
            $newStatus = ($target['status'] === 'active') ? 'inactive' : 'active';
            $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $toggle_id]);
            $msg = "Account status for <strong>" . htmlspecialchars($target['full_name']) . "</strong> changed to <strong>" . ucfirst($newStatus) . "</strong>.";
        }
    }
}

// ========================================================
// 5. GET HANDLER: DELETE USER ACCOUNT
// ========================================================
if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id === $currentAdminId) {
        $err = "Security restriction: You cannot delete your own logged-in administrator account.";
    } else {
        $uStmt = $db->prepare("SELECT id, full_name, username, role FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$delete_id]);
        $target = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($target) {
            // Unlink from students if parent
            $db->prepare("UPDATE students SET parent_user_id = NULL WHERE parent_user_id = ?")->execute([$delete_id]);
            // Delete teacher record if teacher
            $db->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$delete_id]);
            // Delete user
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$delete_id]);
            $msg = "Account <strong>" . htmlspecialchars($target['full_name']) . " (@" . htmlspecialchars($target['username']) . ")</strong> deleted successfully.";
        }
    }
}

// ========================================================
// DATA QUERY & FILTERS
// ========================================================
$search = sanitize($_GET['q'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? 'all');
$statusFilter = sanitize($_GET['status'] ?? 'all');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.full_name LIKE :q OR u.username LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)";
    $params[':q'] = "%$search%";
}

if ($roleFilter !== 'all') {
    $where[] = "u.role = :role";
    $params[':role'] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $where[] = "u.status = :status";
    $params[':status'] = $statusFilter;
}

$sql = "
    SELECT u.*, 
           st.id as linked_student_id,
           st.first_name as student_first_name, 
           st.last_name as student_last_name, 
           st.student_id as student_code,
           st.roll_no as student_roll,
           c.class_name as student_class,
           t.teacher_code
    FROM users u
    LEFT JOIN students st ON st.parent_user_id = u.id
    LEFT JOIN classes c ON st.class_id = c.id
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall Counts by Role
$countsStmt = $db->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN role='accountant' THEN 1 ELSE 0 END) as total_accountants,
        SUM(CASE WHEN role='teacher' THEN 1 ELSE 0 END) as total_teachers,
        SUM(CASE WHEN role='parent' THEN 1 ELSE 0 END) as total_guardians,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as total_active,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as total_inactive
    FROM users
");
$stats = $countsStmt->fetch(PDO::FETCH_ASSOC);

// All active students for Guardian mapping dropdown
$allStudents = $db->query("
    SELECT s.id, s.student_id as student_code, s.first_name, s.last_name, s.roll_no, c.class_name 
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    WHERE s.status = 'Active' 
    ORDER BY c.id ASC, s.roll_no ASC, s.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
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
     CREDENTIALS DISPLAY CARD (SHOWN AFTER CREATE OR RESET)
     ======================================================== -->
<?php if ($newCredentials): ?>
  <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.8rem; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border: 2px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 0.6rem;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="background: #10b981; color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">
          <i class="fas fa-key"></i> CREDENTIALS READY
        </span>
        <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.2rem; color: #f8fafc;">
          Account Login Credentials Generated
        </h4>
      </div>
      <button onclick="this.parentElement.parentElement.style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 1.3rem; cursor: pointer;">&times;</button>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; font-size: 0.95rem;">
      <div>
        <span style="color: #94a3b8; font-size: 0.78rem; text-transform: uppercase;">Account Holder:</span>
        <strong style="display: block; color: #ffffff; font-size: 1.05rem;"><?= htmlspecialchars($newCredentials['name']) ?></strong>
      </div>
      <div>
        <span style="color: #94a3b8; font-size: 0.78rem; text-transform: uppercase;">Username:</span>
        <strong style="display: block; color: #38bdf8; font-family: monospace; font-size: 1.15rem;"><code><?= htmlspecialchars($newCredentials['username']) ?></code></strong>
      </div>
      <div>
        <span style="color: #94a3b8; font-size: 0.78rem; text-transform: uppercase;">Password:</span>
        <strong style="display: block; color: #4ade80; font-family: monospace; font-size: 1.15rem;"><code id="credPass"><?= htmlspecialchars($newCredentials['password']) ?></code></strong>
      </div>
      <div>
        <span style="color: #94a3b8; font-size: 0.78rem; text-transform: uppercase;">Role Assigned:</span>
        <span style="display: inline-block; background: rgba(255,255,255,0.15); padding: 2px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; color: #fbbf24;">
          <?= ucfirst($newCredentials['role']) ?>
        </span>
      </div>
    </div>
    <div style="margin-top: 1rem; padding-top: 0.8rem; border-top: 1px dashed rgba(255,255,255,0.15); display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #94a3b8;">
      <span>Please copy or safely share these login details with the user.</span>
      <button onclick="copyCredentials('<?= htmlspecialchars($newCredentials['username']) ?>', '<?= htmlspecialchars($newCredentials['password']) ?>')" class="btn btn-sm" style="background: #3b82f6; color: #ffffff; padding: 4px 12px; border-radius: 6px; font-weight: 700; border: none; cursor: pointer;">
        <i class="fas fa-copy"></i> Copy Credentials
      </button>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================
     HEADER BANNER & QUICK ACTIONS
     ======================================================== -->
<div class="user-manager-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 1.8rem;">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #ede9fe; color: #6d28d9; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;">
      <i class="fas fa-shield-alt text-primary"></i>
      <span>SUPER ADMIN SYSTEM OVERSIGHT &bull; ACCESS CONTROL</span>
    </div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0; font-size: 1.85rem; font-weight: 800;">
      User Account Management &amp; Access Control
    </h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.95rem;">
      Create, oversee, and reset credentials for Principal Admins, Accountants, Teachers, and Guardians.
    </p>
  </div>

  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="btn btn-primary" style="background: #6366f1; color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 12px rgba(99,102,241,0.3);" onclick="openModal('addUserModal')">
      <i class="fas fa-user-plus"></i> Add New User Account
    </button>
    <button class="btn btn-outline" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #1e293b; padding: 0.65rem 1.1rem; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="window.print()">
      <i class="fas fa-print"></i> Print User Directory
    </button>
  </div>
</div>

<!-- ========================================================
     KPI STATS CARDS (ROLES & ACTIVE COUNTS)
     ======================================================== -->
<div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 1.8rem;">
  <!-- Total Users -->
  <div class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-users"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase;">Total Users</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: var(--admin-deep-navy); font-weight: 800;"><?= $stats['total_users'] ?></h3>
      <small style="color: #059669; font-weight: 700; font-size: 0.72rem;"><?= $stats['total_active'] ?> Active Accounts</small>
    </div>
  </div>

  <!-- Principal / Admins -->
  <a href="users.php?role=admin" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $roleFilter === 'admin' ? '#8b5cf6' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #ede9fe; color: #7c3aed; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-crown"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #7c3aed; text-transform: uppercase;">Principal / Admins</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #5b21b6; font-weight: 800;"><?= $stats['total_admins'] ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;">Full System Control</small>
    </div>
  </a>

  <!-- Accountants -->
  <a href="users.php?role=accountant" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $roleFilter === 'accountant' ? '#10b981' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #dcfce7; color: #16a34a; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-wallet"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #16a34a; text-transform: uppercase;">Accountants</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #166534; font-weight: 800;"><?= $stats['total_accountants'] ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;">Finance &amp; Invoicing</small>
    </div>
  </a>

  <!-- Teachers -->
  <a href="users.php?role=teacher" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $roleFilter === 'teacher' ? '#0ea5e9' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-chalkboard-teacher"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #0284c7; text-transform: uppercase;">Teachers</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #0369a1; font-weight: 800;"><?= $stats['total_teachers'] ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;">Academic Staff</small>
    </div>
  </a>

  <!-- Guardians / Parents -->
  <a href="users.php?role=parent" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $roleFilter === 'parent' ? '#f59e0b' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: #fef3c7; color: #d97706; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
      <i class="fas fa-user-friends"></i>
    </div>
    <div>
      <span style="font-size: 0.75rem; font-weight: 700; color: #d97706; text-transform: uppercase;">Guardians / Parents</span>
      <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #92400e; font-weight: 800;"><?= $stats['total_guardians'] ?></h3>
      <small style="color: #64748b; font-size: 0.72rem;">Parent Portal Access</small>
    </div>
  </a>
</div>

<!-- ========================================================
     SEARCH & FILTER BAR
     ======================================================== -->
<div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.6rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
  <form method="GET" action="users.php" style="display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 14px; align-items: flex-end;">
    <!-- 1. Search Query -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-search text-primary"></i> Search User Accounts
      </label>
      <div style="position: relative;">
        <input type="text" name="q" class="form-control" placeholder="Search by name, username, email, phone..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2rem;">
        <i class="fas fa-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8; font-size: 0.85rem;"></i>
      </div>
    </div>

    <!-- 2. Role Filter -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-user-tag text-primary"></i> Filter by Role
      </label>
      <select name="role" class="form-control" onchange="this.form.submit()">
        <option value="all" <?= ($roleFilter === 'all') ? 'selected' : '' ?>>All Account Roles</option>
        <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>👑 Principal / Admins</option>
        <option value="accountant" <?= ($roleFilter === 'accountant') ? 'selected' : '' ?>>💼 Accountants</option>
        <option value="teacher" <?= ($roleFilter === 'teacher') ? 'selected' : '' ?>>🎓 Teachers</option>
        <option value="parent" <?= ($roleFilter === 'parent') ? 'selected' : '' ?>>👨‍👩‍👧 Guardians / Parents</option>
      </select>
    </div>

    <!-- 3. Status Filter -->
    <div>
      <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-toggle-on text-primary"></i> Account Status
      </label>
      <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="all" <?= ($statusFilter === 'all') ? 'selected' : '' ?>>All Statuses</option>
        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>🟢 Active Accounts Only</option>
        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>🔴 Inactive / Disabled</option>
      </select>
    </div>

    <!-- 4. Submit & Reset -->
    <div style="display: flex; gap: 6px;">
      <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.1rem; border-radius: 8px; font-weight: 700;">
        Filter
      </button>
      <a href="users.php" class="btn btn-secondary" style="padding: 0.65rem 0.9rem; border-radius: 8px;" title="Reset Filters">
        <i class="fas fa-redo"></i>
      </a>
    </div>
  </form>
</div>

<!-- ========================================================
     USERS DIRECTORY TABLE
     ======================================================== -->
<div class="card-box" style="background: #ffffff; border-radius: 16px; border: 1.5px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
  <div class="card-header" style="padding: 1.2rem 1.6rem; border-bottom: 1.5px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div>
      <h3 class="card-title" style="margin: 0; font-size: 1.15rem; color: var(--admin-deep-navy); font-weight: 800;">
        <i class="fas fa-users-cog text-primary"></i> Registered Users Directory (<?= count($usersList) ?>)
      </h3>
      <small style="color: var(--admin-text-muted);">
        All authorized accounts permitted to access the AL FATIHA school portal.
      </small>
    </div>

    <div style="font-size: 0.85rem; color: var(--admin-text-muted);">
      Displaying <strong><?= count($usersList) ?></strong> of <strong><?= $stats['total_users'] ?></strong> users
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 70px;">ID</th>
          <th>User Account</th>
          <th>System Role</th>
          <th>Contact &amp; Email</th>
          <th>Linked Student / Details</th>
          <th>Account Status</th>
          <th>Registered</th>
          <th style="text-align: right; width: 180px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($usersList)): ?>
          <?php foreach ($usersList as $u): ?>
            <tr class="<?= ($u['status'] === 'inactive') ? 'row-inactive-highlight' : '' ?>">
              <td><span class="roll-badge">#<?= $u['id'] ?></span></td>
              <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                  <div class="user-avatar-badge role-<?= $u['role'] ?>">
                    <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)) ?>
                  </div>
                  <div>
                    <strong style="color: var(--admin-deep-navy); font-size: 0.96rem; display: block;">
                      <?= htmlspecialchars($u['full_name']) ?>
                    </strong>
                    <code style="color: #0284c7; font-size: 0.8rem;">@<?= htmlspecialchars($u['username']) ?></code>
                    <?php if ($u['id'] == $currentAdminId): ?>
                      <span style="background: #ede9fe; color: #7c3aed; font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 8px; margin-left: 4px;">YOU</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <?php if ($u['role'] === 'admin'): ?>
                  <span class="badge" style="background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; padding: 5px 10px; font-weight: 800; font-size: 0.78rem;">
                    <i class="fas fa-crown text-gold"></i> Principal Admin
                  </span>
                <?php elseif ($u['role'] === 'accountant'): ?>
                  <span class="badge" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 5px 10px; font-weight: 800; font-size: 0.78rem;">
                    <i class="fas fa-wallet"></i> Accountant
                  </span>
                <?php elseif ($u['role'] === 'teacher'): ?>
                  <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 5px 10px; font-weight: 800; font-size: 0.78rem;">
                    <i class="fas fa-chalkboard-teacher"></i> Teacher
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 5px 10px; font-weight: 800; font-size: 0.78rem;">
                    <i class="fas fa-user-friends"></i> Guardian
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size: 0.85rem; line-height: 1.4;">
                  <?php if ($u['email']): ?>
                    <div><i class="fas fa-envelope" style="color: #94a3b8; width: 14px;"></i> <?= htmlspecialchars($u['email']) ?></div>
                  <?php else: ?>
                    <span style="color: #94a3b8; font-style: italic;">No email</span>
                  <?php endif; ?>
                  <?php if ($u['phone']): ?>
                    <div style="margin-top: 2px;"><i class="fas fa-phone-alt" style="color: #94a3b8; width: 14px;"></i> <?= htmlspecialchars($u['phone']) ?></div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($u['role'] === 'parent'): ?>
                  <?php if (!empty($u['student_first_name'])): ?>
                    <div style="font-size: 0.82rem;">
                      <span style="color: #475569; font-weight: 700;">Child:</span> <strong><?= htmlspecialchars($u['student_first_name'] . ' ' . $u['student_last_name']) ?></strong>
                      <br><small style="color: #64748b;"><?= htmlspecialchars($u['student_class']) ?> &bull; Roll: <?= $u['student_roll'] ?: '-' ?> &bull; <code><?= $u['student_code'] ?></code></small>
                    </div>
                  <?php else: ?>
                    <span style="color: #94a3b8; font-size: 0.78rem; font-style: italic;">No student mapped</span>
                  <?php endif; ?>
                <?php elseif ($u['role'] === 'teacher'): ?>
                  <span style="font-size: 0.82rem; color: #475569;">
                    Staff Code: <code><?= htmlspecialchars($u['teacher_code'] ?: 'TCH-' . $u['id']) ?></code>
                  </span>
                <?php else: ?>
                  <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Internal Staff</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['status'] === 'active'): ?>
                  <span class="badge" style="background: #d1fae5; color: #065f46; font-size: 0.78rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-check-circle"></i> Active
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; font-weight: 800; padding: 4px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-ban"></i> Inactive
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <small style="color: #64748b; font-size: 0.8rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></small>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 5px; align-items: center;">
                  <!-- 1. Reset Password Button -->
                  <button type="button" class="btn btn-action-key" onclick="openResetPasswordModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Reset Account Password">
                    <i class="fas fa-key"></i>
                  </button>

                  <!-- 2. Edit User Button -->
                  <button type="button" class="btn btn-action-edit" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit User Information">
                    <i class="fas fa-pencil-alt"></i>
                  </button>

                  <!-- 3. Toggle Status (Active / Inactive) -->
                  <?php if ($u['id'] != $currentAdminId): ?>
                    <a href="users.php?toggle_status=<?= $u['id'] ?>" class="btn btn-action-toggle" title="<?= $u['status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>" onclick="return confirm('Change status for this user?');">
                      <i class="fas <?= $u['status'] === 'active' ? 'fa-toggle-on text-emerald' : 'fa-toggle-off text-muted' ?>"></i>
                    </a>

                    <!-- 4. Delete Account -->
                    <a href="users.php?delete_user=<?= $u['id'] ?>" class="btn btn-action-delete" title="Delete Account" onclick="return confirm('Warning: Are you sure you want to completely delete this user account? This cannot be undone.');">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  <?php else: ?>
                    <span class="badge" title="Active Logged-in Account" style="background: #f1f5f9; color: #94a3b8; font-size: 0.72rem; padding: 4px 6px;">
                      <i class="fas fa-lock"></i>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: var(--admin-text-muted);">
              <i class="fas fa-user-slash" style="font-size: 2.2rem; opacity: 0.3; margin-bottom: 0.6rem; display: block;"></i>
              No user accounts found matching your search or filter.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========================================================
     MODAL 1: ADD NEW USER ACCOUNT
     ======================================================== -->
<div id="addUserModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 620px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #6366f1; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-user-plus"></i> ACCOUNT CREATION
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.4rem;">
          Register New User Account
        </h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: #64748b;">
          Create credentials for Principal, Accountant, Teacher, or Guardian.
        </p>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addUserModal')">&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action_create_user" value="1">

      <!-- 1. Role Selection Radio Pills -->
      <div style="margin-top: 1.2rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem; display: block; margin-bottom: 8px;">
          Select System Role *
        </label>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
          <label class="role-selector-card">
            <input type="radio" name="role" value="admin" onchange="onRoleChange(this.value)">
            <div>
              <strong style="color: #6d28d9; display: block;"><i class="fas fa-crown"></i> Principal / Admin</strong>
              <small style="color: #64748b; font-size: 0.72rem;">Full school administrative control</small>
            </div>
          </label>

          <label class="role-selector-card">
            <input type="radio" name="role" value="accountant" onchange="onRoleChange(this.value)">
            <div>
              <strong style="color: #16a34a; display: block;"><i class="fas fa-wallet"></i> Accountant</strong>
              <small style="color: #64748b; font-size: 0.72rem;">Fees, Invoicing, Expenses &amp; Ledger</small>
            </div>
          </label>

          <label class="role-selector-card">
            <input type="radio" name="role" value="teacher" onchange="onRoleChange(this.value)">
            <div>
              <strong style="color: #0284c7; display: block;"><i class="fas fa-chalkboard-teacher"></i> Teacher</strong>
              <small style="color: #64748b; font-size: 0.72rem;">Attendance, Marks &amp; Academic Log</small>
            </div>
          </label>

          <label class="role-selector-card active-card">
            <input type="radio" name="role" value="parent" checked onchange="onRoleChange(this.value)">
            <div>
              <strong style="color: #d97706; display: block;"><i class="fas fa-user-friends"></i> Guardian / Parent</strong>
              <small style="color: #64748b; font-size: 0.72rem;">Parent portal &amp; Student tracking</small>
            </div>
          </label>
        </div>
      </div>

      <!-- 2. Full Name & Username -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Full Name *</label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. Mahmudul Hasan" required>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Login Username *</label>
          <input type="text" name="username" class="form-control" placeholder="e.g. mahmudul or principal2" required style="font-weight: 700;">
        </div>
      </div>

      <!-- 3. Email & Phone -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="e.g. user@alfatiha.edu.bd">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="e.g. 01812345678">
        </div>
      </div>

      <!-- 4. Initial Password -->
      <div class="form-group" style="margin-top: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem; margin: 0;">Initial Password *</label>
          <button type="button" onclick="generateRandomPass('newPassInput')" style="background: none; border: none; color: #6366f1; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
            <i class="fas fa-magic"></i> Generate Strong Password
          </button>
        </div>
        <div style="position: relative;">
          <input type="text" name="password" id="newPassInput" class="form-control" value="Afi#2026!" required style="font-family: monospace; font-size: 1.05rem; font-weight: 700; color: #0284c7;">
        </div>
        <small style="color: #64748b; font-size: 0.72rem;">Minimum 6 characters. You will be able to reset this password at any time.</small>
      </div>

      <!-- 5. Dynamic Guardian Student Selector -->
      <div id="guardianSection" style="margin-top: 1rem; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 12px;">
        <label style="font-weight: 700; color: #92400e; font-size: 0.85rem; display: block; margin-bottom: 4px;">
          <i class="fas fa-child text-gold"></i> Link with Student (For Guardian Portal)
        </label>
        <select name="linked_student_id" class="form-control" style="background: #ffffff;">
          <option value="0">-- No Student Mapped Yet (Can link later) --</option>
          <?php foreach ($allStudents as $st): ?>
            <option value="<?= $st['id'] ?>">
              <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (Roll: <?= $st['roll_no'] ?: '-' ?> &bull; <?= htmlspecialchars($st['class_name']) ?> &bull; <?= htmlspecialchars($st['student_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: #92400e; font-size: 0.72rem; display: block; margin-top: 4px;">
          Linking a student allows the guardian to view their child's marks, attendance, and fee status.
        </small>
      </div>

      <!-- 6. Dynamic Teacher Details Selector -->
      <div id="teacherSection" style="display: none; margin-top: 1rem; background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 12px;">
        <label style="font-weight: 700; color: #0369a1; font-size: 0.85rem; display: block; margin-bottom: 6px;">
          <i class="fas fa-graduation-cap"></i> Teacher Profile Details
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <input type="text" name="specialty" class="form-control" placeholder="Subject / Specialty (e.g. Quran, English)" style="background: #ffffff; font-size: 0.85rem;">
          <input type="text" name="qualification" class="form-control" placeholder="Qualification (e.g. Kamil, B.Ed)" style="background: #ffffff; font-size: 0.85rem;">
        </div>
      </div>

      <!-- Status Selection -->
      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Account Status</label>
        <select name="status" class="form-control">
          <option value="active" selected>🟢 Active (Authorized to Login)</option>
          <option value="inactive">🔴 Inactive (Account Suspended)</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #6366f1; color: #ffffff; font-weight: 800; padding: 0.7rem 1.6rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Create Account
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 2: RESET PASSWORD MODAL (EXPLICIT USER FEATURE)
     ======================================================== -->
<div id="resetPasswordModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 520px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #ef4444; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-key"></i> SECURITY CREDENTIAL RESET
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">
          Reset Account Password
        </h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('resetPasswordModal')">&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action_reset_password" value="1">
      <input type="hidden" name="target_user_id" id="resetUserId">

      <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-top: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <span style="font-size: 0.82rem; color: #64748b;">Target User:</span>
          <strong id="resetTargetName" style="color: var(--admin-deep-navy); font-size: 0.95rem;">--</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <span style="font-size: 0.82rem; color: #64748b;">Username:</span>
          <code id="resetTargetUsername" style="color: #0284c7; font-weight: 700;">@--</code>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.82rem; color: #64748b;">Role:</span>
          <span id="resetTargetRole" style="font-weight: 700; font-size: 0.8rem; color: #7c3aed;">--</span>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1.2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem; margin: 0;">Set New Password *</label>
          <button type="button" onclick="generateRandomPass('resetPassInput')" style="background: none; border: none; color: #6366f1; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
            <i class="fas fa-magic"></i> Generate Password
          </button>
        </div>
        <input type="text" name="new_password" id="resetPassInput" class="form-control" required style="font-family: monospace; font-size: 1.1rem; font-weight: 700; color: #0284c7;">
        <small style="color: #64748b; font-size: 0.74rem;">The user will use this new password immediately on next login.</small>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #ef4444; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-lock-open"></i> Apply New Password
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 3: EDIT USER PROFILE MODAL
     ======================================================== -->
<div id="editUserModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 600px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #0284c7; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-user-edit"></i> MODIFY ACCOUNT
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">
          Edit User Account
        </h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('editUserModal')">&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action_update_user" value="1">
      <input type="hidden" name="edit_user_id" id="editUserId">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1.2rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Full Name *</label>
          <input type="text" name="full_name" id="editFullName" class="form-control" required>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Login Username (Fixed)</label>
          <input type="text" id="editUsername" class="form-control" readonly style="background: #f1f5f9; font-weight: 700;">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Email Address</label>
          <input type="email" name="email" id="editEmail" class="form-control">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Phone Number</label>
          <input type="text" name="phone" id="editPhone" class="form-control">
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Role Assigned</label>
          <select name="role" id="editRole" class="form-control" onchange="onEditRoleChange(this.value)">
            <option value="admin">👑 Principal / Admin</option>
            <option value="accountant">💼 Accountant</option>
            <option value="teacher">🎓 Teacher</option>
            <option value="parent">👨‍👩‍👧 Guardian / Parent</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Account Status</label>
          <select name="status" id="editStatus" class="form-control">
            <option value="active">🟢 Active</option>
            <option value="inactive">🔴 Inactive</option>
          </select>
        </div>
      </div>

      <!-- Linked Student (if Guardian) -->
      <div id="editGuardianSection" style="margin-top: 1rem; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 12px;">
        <label style="font-weight: 700; color: #92400e; font-size: 0.85rem; display: block; margin-bottom: 4px;">
          <i class="fas fa-child text-gold"></i> Linked Student (For Guardian Portal)
        </label>
        <select name="linked_student_id" id="editLinkedStudentId" class="form-control" style="background: #ffffff;">
          <option value="0">-- No Student Mapped --</option>
          <?php foreach ($allStudents as $st): ?>
            <option value="<?= $st['id'] ?>">
              <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (Roll: <?= $st['roll_no'] ?: '-' ?> &bull; <?= htmlspecialchars($st['class_name']) ?> &bull; <?= htmlspecialchars($st['student_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0284c7; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL STYLES & INTERACTION SCRIPTS
     ======================================================== -->
<style>
.user-avatar-badge {
  width: 40px; height: 40px; border-radius: 12px;
  display: grid; place-items: center; font-size: 1.1rem;
  font-weight: 800; flex-shrink: 0;
}
.user-avatar-badge.role-admin { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
.user-avatar-badge.role-accountant { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.user-avatar-badge.role-teacher { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.user-avatar-badge.role-parent { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }

.btn-action-key {
  background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
  width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
  display: grid; place-items: center; font-size: 0.85rem; transition: all 0.2s;
}
.btn-action-key:hover { background: #ef4444; color: #ffffff; }

.btn-action-edit {
  background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;
  width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
  display: grid; place-items: center; font-size: 0.85rem; transition: all 0.2s;
}
.btn-action-edit:hover { background: #0284c7; color: #ffffff; }

.btn-action-toggle {
  background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;
  width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
  display: grid; place-items: center; font-size: 1.1rem; text-decoration: none;
}
.btn-action-delete {
  background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3;
  width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
  display: grid; place-items: center; font-size: 0.85rem; text-decoration: none;
}
.btn-action-delete:hover { background: #be123c; color: #ffffff; }

.role-selector-card {
  display: flex; align-items: center; gap: 8px;
  background: #f8fafc; border: 1.5px solid #cbd5e1;
  border-radius: 10px; padding: 10px 12px; cursor: pointer;
  transition: all 0.2s;
}
.role-selector-card input { transform: scale(1.2); }
.role-selector-card.active-card {
  border-color: #6366f1; background: #eef2ff;
}
.row-inactive-highlight {
  background: rgba(241, 245, 249, 0.6);
  opacity: 0.75;
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

function onRoleChange(role) {
  document.querySelectorAll('.role-selector-card').forEach(c => c.classList.remove('active-card'));
  const radio = document.querySelector(`input[name="role"][value="${role}"]`);
  if (radio) radio.parentElement.classList.add('active-card');

  const gSec = document.getElementById('guardianSection');
  const tSec = document.getElementById('teacherSection');
  if (role === 'parent') {
    gSec.style.display = 'block';
    tSec.style.display = 'none';
  } else if (role === 'teacher') {
    gSec.style.display = 'none';
    tSec.style.display = 'block';
  } else {
    gSec.style.display = 'none';
    tSec.style.display = 'none';
  }
}

function onEditRoleChange(role) {
  const gSec = document.getElementById('editGuardianSection');
  if (role === 'parent') {
    gSec.style.display = 'block';
  } else {
    gSec.style.display = 'none';
  }
}

function generateRandomPass(targetInputId) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
  let pass = 'Afi#';
  for (let i = 0; i < 6; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  document.getElementById(targetInputId).value = pass;
}

function copyCredentials(user, pass) {
  const text = `AL FATIHA INSTITUTE Login Credentials:\nUsername: ${user}\nPassword: ${pass}\nPortal: ${window.location.origin}/alfatiha/login.php`;
  navigator.clipboard.writeText(text).then(() => {
    alert('Credentials copied to clipboard!');
  }).catch(() => {
    prompt('Copy login credentials:', text);
  });
}

function openResetPasswordModal(user) {
  document.getElementById('resetUserId').value = user.id;
  document.getElementById('resetTargetName').innerText = user.full_name;
  document.getElementById('resetTargetUsername').innerText = '@' + user.username;
  document.getElementById('resetTargetRole').innerText = user.role.toUpperCase();
  generateRandomPass('resetPassInput');

  openModal('resetPasswordModal');
}

function openEditUserModal(user) {
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editFullName').value = user.full_name;
  document.getElementById('editUsername').value = user.username;
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editPhone').value = user.phone || '';
  document.getElementById('editRole').value = user.role;
  document.getElementById('editStatus').value = user.status || 'active';

  const gSelect = document.getElementById('editLinkedStudentId');
  if (gSelect) {
    gSelect.value = user.linked_student_id || 0;
  }
  onEditRoleChange(user.role);

  openModal('editUserModal');
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>