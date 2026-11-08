<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$db = get_db_connection();

$msg = '';
$err = '';

$flash = get_flash_msg();
if ($flash) {
    if ($flash['type'] === 'danger') {
        $err = $flash['message'];
    } else {
        $msg = $flash['message'];
    }
}

// Handle Add Student (Comprehensive Admission for Running / New Students)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $dob = sanitize($_POST['dob'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 1);
    
    // Auto or manual roll no
    $roll_no = intval($_POST['roll_no'] ?? 0);
    if ($roll_no <= 0) {
        $roll_no = get_next_class_roll($class_id);
    }
    
    $admission_session = sanitize($_POST['admission_session'] ?? '2027');
    $admission_date = sanitize($_POST['admission_date'] ?? date('Y-m-d'));
    
    // Identity & Health
    $blood_group = sanitize($_POST['blood_group'] ?? '');
    $religion = sanitize($_POST['religion'] ?? 'Islam');
    $nationality = sanitize($_POST['nationality'] ?? 'Bangladeshi');
    $birth_reg_no = sanitize($_POST['birth_reg_no'] ?? '');
    
    // Parents
    $father_name = sanitize($_POST['father_name'] ?? '');
    $father_occupation = sanitize($_POST['father_occupation'] ?? '');
    $father_phone = sanitize($_POST['father_phone'] ?? '');
    $father_nid = sanitize($_POST['father_nid'] ?? '');
    $mother_name = sanitize($_POST['mother_name'] ?? '');
    $mother_occupation = sanitize($_POST['mother_occupation'] ?? '');
    $mother_phone = sanitize($_POST['mother_phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = !empty($father_phone) ? $father_phone : sanitize($_POST['phone'] ?? '');

    // Addresses
    $address = sanitize($_POST['address'] ?? '');
    $permanent_address = sanitize($_POST['permanent_address'] ?? $address);

    // Emergency & Prior Schooling
    $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_relation = sanitize($_POST['emergency_contact_relation'] ?? '');
    $emergency_contact_phone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $previous_school = sanitize($_POST['previous_school'] ?? '');
    $medical_notes = sanitize($_POST['medical_notes'] ?? '');

    // Options
    $auto_parent = isset($_POST['auto_create_parent_account']);

    if (empty($first_name) || empty($dob) || empty($father_name) || (empty($phone) && empty($father_phone))) {
        $err = "Please fill in student first name, date of birth, class, father's name, and phone number.";
    } else {
        try {
            $db->beginTransaction();

            // 1. Generate Class-Based Fixed Student ID (Format: AFI-[CLASS_CODE]-[ROLL])
            $student_id = generate_student_id_for_class($class_id, $roll_no);

            // 2. Parent Account Creation
            $parent_user_id = null;
            $parent_info_msg = '';
            if ($auto_parent) {
                $baseUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name));
                $parent_username = 'parent_' . $baseUser . rand(10, 99);
                $parent_pass_plain = 'parent123';
                $parent_pass_hash = password_hash($parent_pass_plain, PASSWORD_BCRYPT);
                
                $parentStmt = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone) VALUES (?, ?, ?, 'parent', ?, ?)");
                $parentStmt->execute([
                    $parent_username,
                    $parent_pass_hash,
                    $email ?: ($parent_username . '@alfatihainstitute.edu.bd'),
                    $father_name,
                    $phone
                ]);
                $parent_user_id = $db->lastInsertId();
                $parent_info_msg = " &bull; Parent Portal Account: <code>$parent_username</code> (Pass: <code>$parent_pass_plain</code>)";
            }

            // 3. Insert Comprehensive Student Record
            $stmt = $db->prepare("INSERT INTO students (
                student_id, first_name, last_name, gender, dob, class_id, roll_no, parent_user_id,
                father_name, mother_name, phone, address, status, admission_date,
                blood_group, religion, nationality, birth_reg_no, permanent_address,
                father_occupation, father_phone, father_nid, mother_occupation, mother_phone,
                emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                previous_school, medical_notes, admission_session
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, 'Active', ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )");

            $stmt->execute([
                $student_id,
                $first_name,
                $last_name,
                $gender,
                $dob,
                $class_id,
                $roll_no,
                $parent_user_id,
                $father_name,
                $mother_name,
                $phone,
                $address,
                $admission_date,
                $blood_group ?: null,
                $religion ?: 'Islam',
                $nationality ?: 'Bangladeshi',
                $birth_reg_no ?: null,
                $permanent_address ?: $address,
                $father_occupation ?: null,
                $father_phone ?: $phone,
                $father_nid ?: null,
                $mother_occupation ?: null,
                $mother_phone ?: null,
                $emergency_contact_name ?: null,
                $emergency_contact_phone ?: null,
                $emergency_contact_relation ?: null,
                $previous_school ?: null,
                $medical_notes ?: null,
                $admission_session
            ]);

            $db->commit();
            set_flash_msg("Running Student <strong>$first_name $last_name</strong> admitted successfully! Generated Student ID: <strong>$student_id</strong> (Roll: #$roll_no)$parent_info_msg", 'success');
            redirect('students.php');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_msg("Failed to admit student: " . $e->getMessage(), 'danger');
            redirect('students.php');
        }
    }
}

// Handle Delete Student
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([$del_id]);
    set_flash_msg("Student deleted successfully.", 'success');
    redirect('students.php');
}

// Fetch Students with Class info
$students = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.class_id ASC, s.roll_no ASC")->fetchAll();
$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();

// Load Layout for HTML Rendering
require_once __DIR__ . '/layout.php';
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

<!-- Add Student Modal (Comprehensive Running & New Student Admission) -->
<div id="addStudentModal" class="admin-modal-wrap" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 10000; justify-content: center; align-items: center; padding: 1.5rem; box-sizing: border-box;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 820px; border-radius: 20px; padding: 2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.35); max-height: 92vh; overflow-y: auto; box-sizing: border-box;">
    
    <!-- Modal Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1.5px solid var(--admin-border); padding-bottom: 1rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); font-size: 1.35rem; margin: 0;">
            <i class="fas fa-user-plus" style="color: var(--admin-primary);"></i> Running &amp; New Student Admission
          </h3>
          <span class="badge" style="background: #dbeafe; color: #1e40af; font-weight: 800;">SESSION 2027</span>
        </div>
        <p style="color: var(--admin-text-muted); font-size: 0.85rem; margin: 0.35rem 0 0 0;">
          Directly admit running school students with full 360° bio-data. Auto-generates Student ID (<code>AFI-2027-XXX</code>).
        </p>
      </div>
      <button type="button" style="background: #f1f5f9; border: none; font-size: 1.3rem; cursor: pointer; color: var(--admin-text-muted); width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center;" onclick="closeModal('addStudentModal')">&times;</button>
    </div>

    <form method="POST">
      
      <!-- 1. Academic Placement Section -->
      <div style="background: #f8fafc; border: 1.5px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0 0 0.85rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 7px;">
          <i class="fas fa-school" style="color: var(--admin-primary);"></i> 1. Academic Placement &amp; Session
        </h4>
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Class / Standard *</label>
            <select name="class_id" class="form-control" required style="font-weight: 600;">
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= htmlspecialchars($c['class_name']) ?> (<?= htmlspecialchars($c['age_group']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Roll No</label>
            <input type="number" name="roll_no" class="form-control" placeholder="Auto" min="1">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Session</label>
            <input type="text" name="admission_session" class="form-control" value="2027" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Admission Date</label>
            <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
      </div>

      <!-- 2. Student Identity & Personal Info -->
      <div style="background: #ffffff; border: 1.5px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0 0 0.85rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 7px;">
          <i class="fas fa-user-graduate" style="color: var(--admin-primary);"></i> 2. Student Identity &amp; Personal Info
        </h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">First Name *</label>
            <input type="text" name="first_name" class="form-control" placeholder="First Name" required style="text-transform: uppercase;">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Last Name</label>
            <input type="text" name="last_name" class="form-control" placeholder="Last Name" style="text-transform: uppercase;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Gender *</label>
            <select name="gender" class="form-control" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Date of Birth *</label>
            <input type="date" name="dob" class="form-control" required max="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Blood Group</label>
            <select name="blood_group" class="form-control">
              <option value="">Unknown</option>
              <option value="A+">A+</option>
              <option value="A-">A-</option>
              <option value="B+">B+</option>
              <option value="B-">B-</option>
              <option value="O+">O+</option>
              <option value="O-">O-</option>
              <option value="AB+">AB+</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Birth Reg. No</label>
            <input type="text" name="birth_reg_no" class="form-control" placeholder="17-Digit Birth Reg No">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Religion</label>
            <select name="religion" class="form-control">
              <option value="Islam" selected>Islam</option>
              <option value="Hinduism">Hinduism</option>
              <option value="Christianity">Christianity</option>
              <option value="Buddhism">Buddhism</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Nationality</label>
            <input type="text" name="nationality" class="form-control" value="Bangladeshi">
          </div>
        </div>
      </div>

      <!-- 3. Parents & Family Contact Section -->
      <div style="background: #f8fafc; border: 1.5px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0 0 0.85rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 7px;">
          <i class="fas fa-users" style="color: var(--admin-gold-dark);"></i> 3. Parents &amp; Family Contact
        </h4>
        
        <!-- Father -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Father's Name *</label>
            <input type="text" name="father_name" class="form-control" placeholder="Father's Full Name" required>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Father's Mobile *</label>
            <input type="tel" name="father_phone" class="form-control" placeholder="01XXXXXXXXX" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Father's Occupation</label>
            <input type="text" name="father_occupation" class="form-control" placeholder="Occupation">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Father's NID / Passport</label>
            <input type="text" name="father_nid" class="form-control" placeholder="NID or Passport Number">
          </div>
        </div>

        <!-- Mother -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Mother's Name</label>
            <input type="text" name="mother_name" class="form-control" placeholder="Mother's Full Name">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Mother's Mobile</label>
            <input type="tel" name="mother_phone" class="form-control" placeholder="01XXXXXXXXX">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Mother's Occupation</label>
            <input type="text" name="mother_occupation" class="form-control" placeholder="Occupation">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Family Email</label>
            <input type="email" name="email" class="form-control" placeholder="parent@mail.com">
          </div>
        </div>
      </div>

      <!-- 4. Address Details -->
      <div style="background: #ffffff; border: 1.5px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0 0 0.85rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 7px;">
          <i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> 4. Residential Addresses
        </h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Present Address *</label>
            <textarea name="address" class="form-control" rows="2" placeholder="House / Road, Area, District" required></textarea>
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Permanent Address</label>
            <textarea name="permanent_address" class="form-control" rows="2" placeholder="Permanent Address"></textarea>
          </div>
        </div>
      </div>

      <!-- 5. Emergency, Medical & Prior Schooling -->
      <div style="background: #f8fafc; border: 1.5px solid var(--admin-border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0 0 0.85rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 7px;">
          <i class="fas fa-heartbeat" style="color: #10b981;"></i> 5. Emergency Contact &amp; Health Info
        </h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" class="form-control" placeholder="Contact Person Name">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Relationship</label>
            <input type="text" name="emergency_contact_relation" class="form-control" placeholder="Relationship (e.g. Uncle)">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Emergency Phone</label>
            <input type="tel" name="emergency_contact_phone" class="form-control" placeholder="01XXXXXXXXX">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Previous School / Madrasah</label>
            <input type="text" name="previous_school" class="form-control" placeholder="Previous School (optional)">
          </div>
          <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.82rem; font-weight: 700;">Medical Notes / Allergies</label>
            <input type="text" name="medical_notes" class="form-control" placeholder="Medical Notes or Allergies (optional)">
          </div>
        </div>
      </div>

      <!-- 6. Account Creation Toggle -->
      <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <input type="checkbox" name="auto_create_parent_account" id="autoParentAcc" value="1" checked style="width: 18px; height: 18px; cursor: pointer;">
        <label for="autoParentAcc" style="cursor: pointer; font-size: 0.88rem; font-weight: 700; color: #1e3a8a; margin: 0;">
          <i class="fas fa-user-lock"></i> Auto-create Parent Portal Account (Password: <code>parent123</code>)
        </label>
      </div>

      <!-- Action Buttons -->
      <div style="display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.7rem 1.4rem; border-radius: 10px; border: none; font-weight: 700; cursor: pointer;" onclick="closeModal('addStudentModal')">Cancel</button>
        <button type="submit" name="add_student" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.7rem 1.8rem; border-radius: 10px; border: none; cursor: pointer; font-weight: 800; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 15px rgba(14, 115, 185, 0.35);">
          <i class="fas fa-check-circle"></i> Save &amp; Admit Student
        </button>
      </div>

    </form>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
