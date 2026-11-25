<?php
require_once __DIR__ . '/includes/functions.php';
$db = get_db_connection();

$success_msg = '';
$error_msg = '';
$app_no = '';
$submittedData = null;

// Handle Online Admission Form Submission (44 Questions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // 1. Student Information (Questions 1 - 11)
    $child_name = strtoupper(sanitize($_POST['child_name'] ?? ''));
    $birth_reg_or_passport = sanitize($_POST['birth_reg_or_passport'] ?? '');
    $dob = sanitize($_POST['dob'] ?? '');
    $age_jan_1 = sanitize($_POST['age_jan_1'] ?? '');
    $blood_group = sanitize($_POST['blood_group'] ?? '');
    $nationality = sanitize($_POST['nationality'] ?? 'Bangladeshi');
    $religion = sanitize($_POST['religion'] ?? 'Islam');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $previous_school = sanitize($_POST['previous_school'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $permanent_address = sanitize($_POST['permanent_address'] ?? '');
    $target_class_id = intval($_POST['target_class_id'] ?? 1);

    // 2. Guardian Information (Questions 12 - 31)
    $father_name = sanitize($_POST['father_name'] ?? '');
    $father_occupation = sanitize($_POST['father_occupation'] ?? '');
    $father_organization = sanitize($_POST['father_organization'] ?? '');
    $father_location = sanitize($_POST['father_location'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $father_nid_passport = sanitize($_POST['father_nid_passport'] ?? '');
    $father_present_address = sanitize($_POST['father_present_address'] ?? '');

    $mother_name = sanitize($_POST['mother_name'] ?? '');
    $mother_occupation = sanitize($_POST['mother_occupation'] ?? '');
    $mother_organization = sanitize($_POST['mother_organization'] ?? '');
    $mother_location = sanitize($_POST['mother_location'] ?? '');
    $mother_phone = sanitize($_POST['mother_phone'] ?? '');
    $mother_nid_passport = sanitize($_POST['mother_nid_passport'] ?? '');
    $mother_present_address = sanitize($_POST['mother_present_address'] ?? '');

    $guardian_is_other = !empty($_POST['guardian_is_other']) ? 1 : 0;
    $other_guardian_name = sanitize($_POST['other_guardian_name'] ?? '');
    $other_guardian_relationship = sanitize($_POST['other_guardian_relationship'] ?? '');
    $other_guardian_phone = sanitize($_POST['other_guardian_phone'] ?? '');
    $other_guardian_email = sanitize($_POST['other_guardian_email'] ?? '');
    $other_guardian_present_address = sanitize($_POST['other_guardian_present_address'] ?? '');
    $other_guardian_permanent_address = sanitize($_POST['other_guardian_permanent_address'] ?? '');

    $email = sanitize($_POST['email'] ?? ($other_guardian_email ?: ''));

    // 3. Emergency Contact (Questions 32 - 34)
    $emergency_name = sanitize($_POST['emergency_name'] ?? '');
    $emergency_relationship = sanitize($_POST['emergency_relationship'] ?? '');
    $emergency_phone = sanitize($_POST['emergency_phone'] ?? '');

    // 4. Medical Information (Questions 35 - 40)
    $has_allergies = sanitize($_POST['has_allergies'] ?? 'No');
    $allergies_list = sanitize($_POST['allergies_list'] ?? '');
    $regular_medications = sanitize($_POST['regular_medications'] ?? '');
    $has_medical_conditions = sanitize($_POST['has_medical_conditions'] ?? 'No');
    $medical_conditions_details = sanitize($_POST['medical_conditions_details'] ?? '');
    $preferred_hospital = sanitize($_POST['preferred_hospital'] ?? '');

    // 5. Transportation (Questions 41 - 43)
    $transportation_type = sanitize($_POST['transportation_type'] ?? 'Own Transport');
    $pickup_point = sanitize($_POST['pickup_point'] ?? '');
    $dropoff_point = sanitize($_POST['dropoff_point'] ?? '');

    // 6. Documents Submitted
    $docsArr = (array)($_POST['documents_submitted'] ?? []);
    if (!empty($_POST['documents_other'])) {
        $docsArr[] = 'Other: ' . sanitize($_POST['documents_other']);
    }
    $documents_submitted = implode("\n", $docsArr);

    // 7. Consent (Question 44)
    $consent_agreed = !empty($_POST['consent_agreed']) ? 1 : 0;

    if (empty($child_name) || empty($gender) || empty($dob) || (empty($father_name) && empty($mother_name) && empty($other_guardian_name)) || empty($phone) || empty($address)) {
        $error_msg = "Please fill in all mandatory fields marked with an asterisk (*).";
    } elseif (!$consent_agreed) {
        $error_msg = "You must agree to Question 44 (Declaration and Consent) to finalize your application.";
    } else {
        try {
            $app_no = 'AFI-' . date('Y') . '-' . rand(1000, 9999);
            $formData = json_encode($_POST, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("INSERT INTO admissions (
                application_no, child_name, gender, dob, target_class_id, father_name, mother_name, phone, email, address, previous_school, status,
                birth_reg_or_passport, age_jan_1, blood_group, nationality, religion, permanent_address,
                father_occupation, father_organization, father_location, father_nid_passport, father_present_address,
                mother_occupation, mother_organization, mother_location, mother_phone, mother_nid_passport, mother_present_address,
                guardian_is_other, other_guardian_name, other_guardian_relationship, other_guardian_phone, other_guardian_email,
                other_guardian_present_address, other_guardian_permanent_address,
                emergency_name, emergency_relationship, emergency_phone,
                has_allergies, allergies_list, regular_medications, has_medical_conditions, medical_conditions_details, preferred_hospital,
                transportation_type, pickup_point, dropoff_point,
                consent_agreed, documents_submitted, form_data
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending',
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )");

            $stmt->execute([
                $app_no, $child_name, $gender, $dob, $target_class_id, $father_name, $mother_name, $phone, $email, $address, $previous_school,
                $birth_reg_or_passport, $age_jan_1, $blood_group, $nationality, $religion, $permanent_address,
                $father_occupation, $father_organization, $father_location, $father_nid_passport, $father_present_address,
                $mother_occupation, $mother_organization, $mother_location, $mother_phone, $mother_nid_passport, $mother_present_address,
                $guardian_is_other, $other_guardian_name, $other_guardian_relationship, $other_guardian_phone, $other_guardian_email,
                $other_guardian_present_address, $other_guardian_permanent_address,
                $emergency_name, $emergency_relationship, $emergency_phone,
                $has_allergies, $allergies_list, $regular_medications, $has_medical_conditions, $medical_conditions_details, $preferred_hospital,
                $transportation_type, $pickup_point, $dropoff_point,
                $consent_agreed, $documents_submitted, $formData
            ]);

            $success_msg = "Application submitted successfully! Tracking Reference: <strong>$app_no</strong>";
            $submittedData = $_POST;
            $submittedData['app_no'] = $app_no;
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch classes for dropdown
$classesStmt = $db->query("SELECT * FROM classes ORDER BY id ASC");
$classesList = $classesStmt->fetchAll();

// Target class pre-selection from URL
$selected_class_id = isset($_GET['class']) ? intval($_GET['class']) : 1;

include __DIR__ . '/includes/header.php';
?>

<!-- ========================================================
     HERO BANNER SECTION
     ======================================================== -->
<section class="smart-hero-section">
  <div class="smart-hero-particles">
    <div class="particle p-1"></div>
    <div class="particle p-2"></div>
    <div class="particle p-3"></div>
  </div>
  <div class="container" style="position: relative; z-index: 2;">
    <div class="smart-hero-badge">
      <i class="fas fa-certificate text-gold"></i>
      <span>ADMISSION SESSION 2027 &ndash; 2028</span>
      <span class="badge-dot"></span>
      <span class="badge-status">OPEN</span>
    </div>
    <h1 class="smart-hero-title">
      Online Student Admission Portal
    </h1>
    <p class="smart-hero-subtitle">
      Al Fatiha Institute &bull; Cambridge Curriculum &amp; Islamic Ethos &bull; Cox's Bazar
    </p>

    <!-- Quick Feature Chips -->
    <div class="smart-feature-chips">
      <span class="feature-chip"><i class="fas fa-bolt"></i> 5-Step Smart Wizard</span>
      <span class="feature-chip"><i class="fas fa-calculator"></i> Auto-Age Calculation</span>
      <span class="feature-chip"><i class="fas fa-shield-alt"></i> Verified Online Record</span>
      <span class="feature-chip"><i class="fas fa-print"></i> Instant Printable Slip</span>
    </div>
  </div>
</section>

<!-- ========================================================
     TUITION & ELIGIBILITY QUICK TABLE
     ======================================================== -->
<section class="section-padding" style="background: #f8fafc; padding: 2.5rem 0 1.5rem 0;">
  <div class="container">
    <div class="fee-accordion-wrap">
      <details class="smart-fee-details">
        <summary class="smart-fee-summary">
          <div class="summary-left">
            <span class="fee-icon-pill"><i class="fas fa-coins"></i></span>
            <div>
              <strong>Class Eligibility &amp; Tuition Fee Structure</strong>
              <small>Click to view/hide standard monthly tuition and admission fees</small>
            </div>
          </div>
          <span class="summary-arrow"><i class="fas fa-chevron-down"></i></span>
        </summary>
        <div class="smart-fee-table-wrap">
          <table class="smart-data-table">
            <thead>
              <tr>
                <th>Class / Standard</th>
                <th>Age Requirement</th>
                <th>Monthly Tuition</th>
                <th>Admission Fee</th>
                <th>Book Kit &amp; Bag</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classesList as $cls): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($cls['class_name']) ?></strong></td>
                  <td><span class="age-pill"><?= htmlspecialchars($cls['age_group']) ?></span></td>
                  <td><span class="price-highlight"><?= format_currency($cls['tuition_fee']) ?></span></td>
                  <td><strong style="color: #0d9488; font-weight: 700;"><?= format_currency($cls['admission_fee'] ?? 8000) ?></strong></td>
                  <td><strong style="color: #d97706; font-weight: 700;"><?= format_currency($cls['book_kit_fee'] ?? 3500) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    </div>

    <!-- ========================================================
         MAIN SMART APPLICATION CARD
         ======================================================== -->
    <div id="apply-form" class="smart-app-card">

      <!-- Portal Header with Live Stepper -->
      <div class="smart-portal-head">
        <div class="portal-branding-bar">
          <div class="portal-brand-left">
            <div class="brand-crest">
              <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
              <span class="portal-tag">AL FATIHA INSTITUTE &bull; ADMISSION DESK</span>
              <h2 class="portal-main-heading">Official Student Enrollment Form</h2>
              <p class="portal-desc">Fill in the 44 institutional questions accurately. Takes about 4 minutes.</p>
            </div>
          </div>
          <div class="portal-academic-card">
            <span class="acad-label">ACADEMIC YEAR</span>
            <strong class="acad-year">2027 &ndash; 2028</strong>
            <span class="acad-mode"><i class="fas fa-check-circle"></i> Digital Verified</span>
          </div>
        </div>

        <!-- Dynamic Live Progress Indicator -->
        <div class="smart-wizard-tracker">
          <div class="tracker-header">
            <div class="step-counter-text" id="stepCounterDisplay">
              <span class="step-num-pill" id="currentStepPill">Step 1 of 5</span>
              <strong id="stepTitleDisplay">Student Information (Questions 1 &ndash; 11)</strong>
            </div>
            <div class="progress-pill-badge" id="percentPill">
              <i class="fas fa-chart-line"></i> <span id="percentText">20%</span> Completed
            </div>
          </div>
          <div class="tracker-bar">
            <div class="tracker-fill" id="wizardProgressFill" style="width: 20%;"></div>
          </div>
        </div>

        <!-- 5 Interactive Stepper Pills (Tabs) -->
        <nav class="smart-stepper-nav" aria-label="Admission steps">
          <button type="button" class="nav-step-tab active" data-step="1">
            <span class="tab-bubble"><i class="fas fa-user-graduate"></i></span>
            <span class="tab-meta">
              <strong>1. Student Info</strong>
              <small>Personal details</small>
            </span>
          </button>
          <button type="button" class="nav-step-tab" data-step="2">
            <span class="tab-bubble"><i class="fas fa-users"></i></span>
            <span class="tab-meta">
              <strong>2. Guardians</strong>
              <small>Parents &amp; family</small>
            </span>
          </button>
          <button type="button" class="nav-step-tab" data-step="3">
            <span class="tab-bubble"><i class="fas fa-phone-alt"></i></span>
            <span class="tab-meta">
              <strong>3. Emergency</strong>
              <small>Secondary contact</small>
            </span>
          </button>
          <button type="button" class="nav-step-tab" data-step="4">
            <span class="tab-bubble"><i class="fas fa-heartbeat"></i></span>
            <span class="tab-meta">
              <strong>4. Health &amp; Transit</strong>
              <small>Medical &amp; commute</small>
            </span>
          </button>
          <button type="button" class="nav-step-tab" data-step="5">
            <span class="tab-bubble"><i class="fas fa-file-contract"></i></span>
            <span class="tab-meta">
              <strong>5. Declaration</strong>
              <small>Review &amp; submit</small>
            </span>
          </button>
        </nav>
      </div>

      <!-- Success Notification / Receipt Card -->
      <?php if ($success_msg): ?>
        <div class="smart-receipt-card" id="submissionReceipt">
          <div class="receipt-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <span class="receipt-eyebrow">APPLICATION RECEIVED</span>
          <h3 class="receipt-title">Alhamdulillah! Application Submitted</h3>
          <p class="receipt-msg"><?= $success_msg ?></p>
          
          <div class="receipt-ref-container">
            <span class="ref-sub">OFFICIAL TRACKING NUMBER</span>
            <span class="ref-code"><?= htmlspecialchars($app_no) ?></span>
            <span class="ref-copy-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($app_no) ?>'); alert('Reference copied: <?= htmlspecialchars($app_no) ?>');">
              <i class="fas fa-copy"></i> Copy
            </span>
          </div>

          <div class="receipt-guidelines">
            <div class="guide-item">
              <i class="fas fa-envelope-circle-check text-blue"></i>
              <span>An SMS &amp; Email acknowledgment has been triggered with next steps.</span>
            </div>
            <div class="guide-item">
              <i class="fas fa-id-card-clip text-gold"></i>
              <span>Keep your tracking number ready when visiting the institute admission office.</span>
            </div>
          </div>

          <div class="receipt-buttons">
            <button type="button" class="btn-receipt print-btn" onclick="window.print()">
              <i class="fas fa-print"></i> Print Acknowledgment Slip
            </button>
            <a href="admissions.php#apply-form" class="btn-receipt reset-btn">
              <i class="fas fa-user-plus"></i> Submit Another Application
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- Error Notification -->
      <?php if ($error_msg): ?>
        <div class="smart-alert-card error">
          <div class="alert-icon"><i class="fas fa-triangle-exclamation"></i></div>
          <div class="alert-body">
            <strong>Unable to proceed:</strong>
            <p><?= htmlspecialchars($error_msg) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- ========================================================
           MULTI-STEP FORM
           ======================================================== -->
      <form method="POST" action="admissions.php#apply-form" id="smartAdmissionForm" novalidate autocomplete="on">

        <!-- ========================================================
             STEP 1: STUDENT INFORMATION
             ======================================================== -->
        <section class="wizard-step-panel active" id="step-panel-1" data-panel="1">
          <div class="section-banner">
            <div class="banner-pill">STEP 1</div>
            <div class="banner-text">
              <h3>Student Personal Details</h3>
              <p>Enter information as registered on the student's birth certificate</p>
            </div>
          </div>

          <!-- Name & Class Selection Grid -->
          <div class="form-grid col-2">
            <!-- Name (Block Letter) -->
            <div class="form-group">
              <label for="child_name" class="field-label">
                Student Full Name <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-user-graduate shell-icon"></i>
                <input type="text" name="child_name" id="child_name" class="field-input uppercase-field" 
                       placeholder="Student Full Name" required 
                       value="<?= htmlspecialchars($_POST['child_name'] ?? '') ?>" autocomplete="name">
              </div>
            </div>

            <!-- Applying for Class -->
            <div class="form-group">
              <label for="target_class_id" class="field-label">
                Class / Grade <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-layer-group shell-icon"></i>
                <select name="target_class_id" id="target_class_id" class="field-select highlight-field" required>
                  <?php foreach ($classesList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($selected_class_id == $c['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c['class_name']) ?> (<?= htmlspecialchars($c['age_group']) ?>) &bull; <?= format_currency($c['tuition_fee']) ?>/mo
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Birth Reg, DOB, Age Calculator Grid -->
          <div class="form-grid col-3">
            <!-- Birth Registration / Passport Number -->
            <div class="form-group">
              <label for="birth_reg_or_passport" class="field-label">
                Birth Reg. / Passport No. <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-id-card shell-icon"></i>
                <input type="text" name="birth_reg_or_passport" id="birth_reg_or_passport" class="field-input" 
                       placeholder="17-Digit Birth Reg. No." required 
                       value="<?= htmlspecialchars($_POST['birth_reg_or_passport'] ?? '') ?>" maxlength="30">
              </div>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
              <label for="dob" class="field-label">
                Date of Birth <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-calendar-day shell-icon"></i>
                <input type="date" name="dob" id="dob" class="field-input" required 
                       max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
              </div>
            </div>

            <!-- Age (as of 1st January) -->
            <div class="form-group">
              <label for="age_jan_1" class="field-label">
                Age (as of 1st Jan <?= date('Y') ?>)
              </label>
              <div class="input-shell readonly-shell">
                <i class="fas fa-calculator shell-icon text-gold"></i>
                <input type="text" name="age_jan_1" id="age_jan_1" class="field-input readonly-field" 
                       placeholder="Auto-calculated" readonly 
                       value="<?= htmlspecialchars($_POST['age_jan_1'] ?? '') ?>">
              </div>
              <span id="ageCalculatorNote" style="display:none;"></span>
            </div>
          </div>

          <!-- Blood Group, Nationality, Religion, Gender Grid -->
          <div class="form-grid col-4">
            <!-- Blood Group -->
            <div class="form-group">
              <label for="blood_group" class="field-label">
                Blood Group <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-tint shell-icon text-danger"></i>
                <select name="blood_group" id="blood_group" class="field-select" required>
                  <option value="">-- Select --</option>
                  <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-','Unknown'] as $bg): ?>
                    <option value="<?= $bg ?>" <?= (($_POST['blood_group'] ?? '') === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Nationality -->
            <div class="form-group">
              <label for="nationality" class="field-label">
                Nationality <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-flag shell-icon"></i>
                <input type="text" name="nationality" id="nationality" class="field-input" 
                       value="<?= htmlspecialchars($_POST['nationality'] ?? 'Bangladeshi') ?>" required>
              </div>
            </div>

            <!-- Religion -->
            <div class="form-group">
              <label for="religion" class="field-label">
                Religion <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-mosque shell-icon"></i>
                <select name="religion" id="religion" class="field-select" required>
                  <?php foreach (['Islam','Christianity','Hinduism','Buddhism','Other'] as $rel): ?>
                    <option value="<?= $rel ?>" <?= (($_POST['religion'] ?? 'Islam') === $rel) ? 'selected' : '' ?>><?= $rel ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Gender (Card Segment) -->
            <div class="form-group">
              <label class="field-label">
                Gender <span class="req">*</span>
              </label>
              <div class="gender-segmented-cards">
                <label class="gender-card <?= (($_POST['gender'] ?? 'Male') === 'Male') ? 'selected' : '' ?>">
                  <input type="radio" name="gender" value="Male" <?= (($_POST['gender'] ?? 'Male') === 'Male') ? 'checked' : '' ?> required>
                  <span class="gender-icon"><i class="fas fa-mars"></i></span>
                  <span class="gender-label">Male</span>
                </label>
                <label class="gender-card <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>">
                  <input type="radio" name="gender" value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'checked' : '' ?>>
                  <span class="gender-icon"><i class="fas fa-venus"></i></span>
                  <span class="gender-label">Female</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Previous School -->
          <div class="form-group" style="margin-top: 1.2rem;">
            <label for="previous_school" class="field-label">
              Previous School (if any)
            </label>
            <div class="input-shell">
              <i class="fas fa-school shell-icon"></i>
              <input type="text" name="previous_school" id="previous_school" class="field-input" 
                     placeholder="Previous School Name" 
                     value="<?= htmlspecialchars($_POST['previous_school'] ?? '') ?>">
            </div>
          </div>

          <!-- Present Address -->
          <div class="form-group" style="margin-top: 1.2rem;">
            <label for="present_address" class="field-label">
              Present Residential Address <span class="req">*</span>
            </label>
            <div class="input-shell textarea-shell">
              <i class="fas fa-map-marker-alt shell-icon"></i>
              <textarea name="address" id="present_address" class="field-textarea" rows="2" 
                        placeholder="House / Road, Area, District" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Permanent Address with One-Click Copy -->
          <div class="form-group" style="margin-top: 1.2rem;">
            <div class="label-action-row">
              <label for="permanent_address" class="field-label" style="margin-bottom: 0;">
                Permanent Address <span class="req">*</span>
              </label>
              <label class="copy-address-toggle">
                <input type="checkbox" id="sameAddressCheck">
                <span class="toggle-pill-custom">
                  <i class="fas fa-copy"></i> Same as Present Address
                </span>
              </label>
            </div>
            <div class="input-shell textarea-shell" style="margin-top: 6px;">
              <i class="fas fa-home shell-icon"></i>
              <textarea name="permanent_address" id="permanent_address" class="field-textarea" rows="2" 
                        placeholder="Village, Post Office, District" required><?= htmlspecialchars($_POST['permanent_address'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Step 1 Navigation Bar -->
          <div class="step-footer-nav single-right">
            <button type="button" class="btn-step next-step" onclick="goToStep(2)">
              <span>Continue to Guardian Info</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </section>

        <!-- ========================================================
             STEP 2: GUARDIAN INFORMATION
             ======================================================== -->
        <section class="wizard-step-panel" id="step-panel-2" data-panel="2">
          <div class="section-banner">
            <div class="banner-pill">STEP 2</div>
            <div class="banner-text">
              <h3>Parent &amp; Guardian Information</h3>
              <p>Father, Mother, and designated Legal Guardian profiles</p>
            </div>
          </div>

          <!-- Father's Profile Card -->
          <div class="profile-card father-theme">
            <div class="profile-header">
              <div class="profile-badge">
                <span class="avatar-ring father-ring"><i class="fas fa-male"></i></span>
                <div>
                  <h4>Father's Information</h4>
                </div>
              </div>
              <button type="button" class="btn-quick-chip" id="copyStudentToFather">
                <i class="fas fa-map-pin"></i> Use Student's Address
              </button>
            </div>

            <div class="form-grid col-2">
              <!-- Father's Name -->
              <div class="form-group">
                <label for="father_name" class="field-label">
                  Father's Full Name <span class="req">*</span>
                </label>
                <div class="input-shell">
                  <i class="fas fa-user shell-icon"></i>
                  <input type="text" name="father_name" id="father_name" class="field-input" 
                         placeholder="Father's Full Name" required 
                         value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>" autocomplete="name">
                </div>
              </div>

              <!-- Father's Occupation -->
              <div class="form-group">
                <label for="father_occupation" class="field-label">
                  Father's Occupation
                </label>
                <div class="input-shell">
                  <i class="fas fa-briefcase shell-icon"></i>
                  <input type="text" name="father_occupation" id="father_occupation" class="field-input" 
                         placeholder="Occupation" 
                         value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="form-grid col-2" style="margin-top: 1rem;">
              <!-- Organization -->
              <div class="form-group">
                <label for="father_organization" class="field-label">
                  Organization / Workplace
                </label>
                <div class="input-shell">
                  <i class="fas fa-building shell-icon"></i>
                  <input type="text" name="father_organization" id="father_organization" class="field-input" 
                         placeholder="Organization Name" 
                         value="<?= htmlspecialchars($_POST['father_organization'] ?? '') ?>">
                </div>
              </div>

              <!-- Location -->
              <div class="form-group">
                <label for="father_location" class="field-label">
                  Workplace Location
                </label>
                <div class="input-shell">
                  <i class="fas fa-map-marked-alt shell-icon"></i>
                  <input type="text" name="father_location" id="father_location" class="field-input" 
                         placeholder="Workplace Location" 
                         value="<?= htmlspecialchars($_POST['father_location'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="form-grid col-2" style="margin-top: 1rem;">
              <!-- Phone Number -->
              <div class="form-group">
                <label for="father_phone" class="field-label">
                  Father's Mobile Number <span class="req">*</span>
                </label>
                <div class="input-shell">
                  <i class="fas fa-phone-alt shell-icon"></i>
                  <input type="tel" name="phone" id="father_phone" class="field-input highlight-field" 
                         placeholder="01XXXXXXXXX" required 
                         value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="tel">
                </div>
              </div>

              <!-- NID / Passport Number -->
              <div class="form-group">
                <label for="father_nid_passport" class="field-label">
                  Father's NID / Passport Number
                </label>
                <div class="input-shell">
                  <i class="fas fa-id-badge shell-icon"></i>
                  <input type="text" name="father_nid_passport" id="father_nid_passport" class="field-input" 
                         placeholder="NID or Passport Number" 
                         value="<?= htmlspecialchars($_POST['father_nid_passport'] ?? '') ?>">
                </div>
              </div>
            </div>

            <!-- Present Address -->
            <div class="form-group" style="margin-top: 1rem;">
              <label for="father_present_address" class="field-label">
                Father's Present Address
              </label>
              <div class="input-shell">
                <i class="fas fa-location-arrow shell-icon"></i>
                <input type="text" name="father_present_address" id="father_present_address" class="field-input" 
                       placeholder="Present Address" 
                       value="<?= htmlspecialchars($_POST['father_present_address'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Mother's Profile Card -->
          <div class="profile-card mother-theme" style="margin-top: 1.5rem;">
            <div class="profile-header">
              <div class="profile-badge">
                <span class="avatar-ring mother-ring"><i class="fas fa-female"></i></span>
                <div>
                  <h4>Mother's Information</h4>
                </div>
              </div>
              <button type="button" class="btn-quick-chip" id="copyStudentToMother">
                <i class="fas fa-map-pin"></i> Use Student's Address
              </button>
            </div>

            <div class="form-grid col-2">
              <!-- Mother's Name -->
              <div class="form-group">
                <label for="mother_name" class="field-label">
                  Mother's Full Name <span class="req">*</span>
                </label>
                <div class="input-shell">
                  <i class="fas fa-user shell-icon"></i>
                  <input type="text" name="mother_name" id="mother_name" class="field-input" 
                         placeholder="Mother's Full Name" required 
                         value="<?= htmlspecialchars($_POST['mother_name'] ?? '') ?>" autocomplete="name">
                </div>
              </div>

              <!-- Mother's Occupation -->
              <div class="form-group">
                <label for="mother_occupation" class="field-label">
                  Mother's Occupation
                </label>
                <div class="input-shell">
                  <i class="fas fa-briefcase shell-icon"></i>
                  <input type="text" name="mother_occupation" id="mother_occupation" class="field-input" 
                         placeholder="Occupation" 
                         value="<?= htmlspecialchars($_POST['mother_occupation'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="form-grid col-2" style="margin-top: 1rem;">
              <!-- Organization -->
              <div class="form-group">
                <label for="mother_organization" class="field-label">
                  Organization / Workplace
                </label>
                <div class="input-shell">
                  <i class="fas fa-building shell-icon"></i>
                  <input type="text" name="mother_organization" id="mother_organization" class="field-input" 
                         placeholder="Organization Name" 
                         value="<?= htmlspecialchars($_POST['mother_organization'] ?? '') ?>">
                </div>
              </div>

              <!-- Location -->
              <div class="form-group">
                <label for="mother_location" class="field-label">
                  Workplace Location
                </label>
                <div class="input-shell">
                  <i class="fas fa-map-marked-alt shell-icon"></i>
                  <input type="text" name="mother_location" id="mother_location" class="field-input" 
                         placeholder="Workplace Location" 
                         value="<?= htmlspecialchars($_POST['mother_location'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="form-grid col-2" style="margin-top: 1rem;">
              <!-- Phone Number -->
              <div class="form-group">
                <label for="mother_phone" class="field-label">
                  Mother's Phone Number
                </label>
                <div class="input-shell">
                  <i class="fas fa-phone-alt shell-icon"></i>
                  <input type="tel" name="mother_phone" id="mother_phone" class="field-input" 
                         placeholder="01XXXXXXXXX" 
                         value="<?= htmlspecialchars($_POST['mother_phone'] ?? '') ?>">
                </div>
              </div>

              <!-- NID / Passport Number -->
              <div class="form-group">
                <label for="mother_nid_passport" class="field-label">
                  Mother's NID / Passport Number
                </label>
                <div class="input-shell">
                  <i class="fas fa-id-badge shell-icon"></i>
                  <input type="text" name="mother_nid_passport" id="mother_nid_passport" class="field-input" 
                         placeholder="NID or Passport Number" 
                         value="<?= htmlspecialchars($_POST['mother_nid_passport'] ?? '') ?>">
                </div>
              </div>
            </div>

            <!-- Present Address -->
            <div class="form-group" style="margin-top: 1rem;">
              <label for="mother_present_address" class="field-label">
                Mother's Present Address
              </label>
              <div class="input-shell">
                <i class="fas fa-location-arrow shell-icon"></i>
                <input type="text" name="mother_present_address" id="mother_present_address" class="field-input" 
                       placeholder="Present Address" 
                       value="<?= htmlspecialchars($_POST['mother_present_address'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Primary Notification Email Card -->
          <div class="notification-email-card" style="margin-top: 1.5rem;">
            <div class="email-card-icon">
              <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="email-card-body">
              <label for="parent_email" class="field-label" style="margin-bottom: 4px;">
                <strong>Notification Email</strong>
              </label>
              <div class="input-shell">
                <i class="fas fa-at shell-icon"></i>
                <input type="email" name="email" id="parent_email" class="field-input" 
                       placeholder="example@mail.com" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
              </div>
            </div>
          </div>

          <!-- Other Legal Guardian Toggle Card -->
          <div class="profile-card other-guardian-theme" style="margin-top: 1.5rem;">
            <label class="custom-switch-label" for="guardian_is_other">
              <input type="checkbox" name="guardian_is_other" id="guardian_is_other" value="1" <?= !empty($_POST['guardian_is_other']) ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
              <span class="switch-content">
                <strong>Legal Guardian Information (if student resides with someone other than parents)</strong>
              </span>
            </label>

            <div id="otherGuardianSection" class="animated-collapse-box" style="display: <?= !empty($_POST['guardian_is_other']) ? 'block' : 'none' ?>; margin-top: 1.2rem;">
              <div class="form-grid col-2">
                <!-- Guardian Name -->
                <div class="form-group">
                  <label for="other_guardian_name" class="field-label">
                    Guardian Name
                  </label>
                  <div class="input-shell">
                    <i class="fas fa-user-shield shell-icon"></i>
                    <input type="text" name="other_guardian_name" id="other_guardian_name" class="field-input" 
                           placeholder="Guardian's Full Name" 
                           value="<?= htmlspecialchars($_POST['other_guardian_name'] ?? '') ?>">
                  </div>
                </div>

                <!-- Relationship -->
                <div class="form-group">
                  <label for="other_guardian_relationship" class="field-label">
                    Relationship to Student
                  </label>
                  <div class="input-shell">
                    <i class="fas fa-link shell-icon"></i>
                    <input type="text" name="other_guardian_relationship" id="other_guardian_relationship" class="field-input" 
                           placeholder="Relationship (e.g. Uncle, Grandparent)" 
                           value="<?= htmlspecialchars($_POST['other_guardian_relationship'] ?? '') ?>">
                  </div>
                </div>
              </div>

              <div class="form-grid col-2" style="margin-top: 1rem;">
                <!-- Phone Number -->
                <div class="form-group">
                  <label for="other_guardian_phone" class="field-label">
                    Guardian Phone Number
                  </label>
                  <div class="input-shell">
                    <i class="fas fa-phone-alt shell-icon"></i>
                    <input type="tel" name="other_guardian_phone" id="other_guardian_phone" class="field-input" 
                           placeholder="01XXXXXXXXX" 
                           value="<?= htmlspecialchars($_POST['other_guardian_phone'] ?? '') ?>">
                  </div>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                  <label for="other_guardian_email" class="field-label">
                    Guardian Email Address
                  </label>
                  <div class="input-shell">
                    <i class="fas fa-envelope shell-icon"></i>
                    <input type="email" name="other_guardian_email" id="other_guardian_email" class="field-input" 
                           placeholder="guardian@mail.com" 
                           value="<?= htmlspecialchars($_POST['other_guardian_email'] ?? '') ?>">
                  </div>
                </div>
              </div>

              <!-- Present Address -->
              <div class="form-group" style="margin-top: 1rem;">
                <label for="other_guardian_present_address" class="field-label">
                  Guardian Present Address
                </label>
                <div class="input-shell">
                  <i class="fas fa-map-marker-alt shell-icon"></i>
                  <input type="text" name="other_guardian_present_address" id="other_guardian_present_address" class="field-input" 
                         placeholder="Present Address" 
                         value="<?= htmlspecialchars($_POST['other_guardian_present_address'] ?? '') ?>">
                </div>
              </div>

              <!-- Permanent Address -->
              <div class="form-group" style="margin-top: 1rem;">
                <label for="other_guardian_permanent_address" class="field-label">
                  Guardian Permanent Address
                </label>
                <div class="input-shell">
                  <i class="fas fa-home shell-icon"></i>
                  <input type="text" name="other_guardian_permanent_address" id="other_guardian_permanent_address" class="field-input" 
                         placeholder="Permanent Address" 
                         value="<?= htmlspecialchars($_POST['other_guardian_permanent_address'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2 Navigation Bar -->
          <div class="step-footer-nav">
            <button type="button" class="btn-step prev-step" onclick="goToStep(1)">
              <i class="fas fa-arrow-left"></i>
              <span>Back: Student Info</span>
            </button>
            <button type="button" class="btn-step next-step" onclick="goToStep(3)">
              <span>Continue to Emergency Contact</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </section>

        <!-- ========================================================
             STEP 3: EMERGENCY CONTACT
             ======================================================== -->
        <section class="wizard-step-panel" id="step-panel-3" data-panel="3">
          <div class="section-banner">
            <div class="banner-pill">STEP 3</div>
            <div class="banner-text">
              <h3>Emergency Contact</h3>
              <p>Person to contact immediately if parents are unreachable</p>
            </div>
          </div>

          <!-- Quick Fill Action Banner -->
          <div class="quick-fill-card">
            <div class="quick-fill-icon"><i class="fas fa-bolt"></i></div>
            <div class="quick-fill-info">
              <strong>Quick-Fill Emergency Contact:</strong>
              <span>Save time by copying father's or mother's details.</span>
            </div>
            <div class="quick-fill-actions">
              <button type="button" class="btn-fill-pill" id="btnCopyFatherToEmergency">
                <i class="fas fa-user-tie"></i> Use Father's Info
              </button>
              <button type="button" class="btn-fill-pill" id="btnCopyMotherToEmergency">
                <i class="fas fa-user"></i> Use Mother's Info
              </button>
            </div>
          </div>

          <div class="form-grid col-3" style="margin-top: 1.5rem;">
            <!-- Emergency Contact Name -->
            <div class="form-group">
              <label for="emergency_name" class="field-label">
                Contact Person Name <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-user-shield shell-icon"></i>
                <input type="text" name="emergency_name" id="emergency_name" class="field-input" 
                       placeholder="Full Name" required 
                       value="<?= htmlspecialchars($_POST['emergency_name'] ?? '') ?>">
              </div>
            </div>

            <!-- Relationship -->
            <div class="form-group">
              <label for="emergency_relationship" class="field-label">
                Relationship <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-users shell-icon"></i>
                <input type="text" name="emergency_relationship" id="emergency_relationship" class="field-input" 
                       placeholder="Relationship (e.g. Uncle)" required 
                       value="<?= htmlspecialchars($_POST['emergency_relationship'] ?? '') ?>">
              </div>
            </div>

            <!-- Phone Number -->
            <div class="form-group">
              <label for="emergency_phone" class="field-label">
                Emergency Phone Number <span class="req">*</span>
              </label>
              <div class="input-shell">
                <i class="fas fa-phone-volume shell-icon text-danger"></i>
                <input type="tel" name="emergency_phone" id="emergency_phone" class="field-input highlight-field" 
                       placeholder="01XXXXXXXXX" required 
                       value="<?= htmlspecialchars($_POST['emergency_phone'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Step 3 Navigation Bar -->
          <div class="step-footer-nav">
            <button type="button" class="btn-step prev-step" onclick="goToStep(2)">
              <i class="fas fa-arrow-left"></i>
              <span>Back: Guardians</span>
            </button>
            <button type="button" class="btn-step next-step" onclick="goToStep(4)">
              <span>Continue to Health &amp; Transit</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </section>

        <!-- ========================================================
             STEP 4: HEALTH & TRANSPORTATION
             ======================================================== -->
        <section class="wizard-step-panel" id="step-panel-4" data-panel="4">
          <div class="section-banner">
            <div class="banner-pill">STEP 4</div>
            <div class="banner-text">
              <h3>Health Care &amp; Transportation</h3>
              <p>Medical profile, allergies, and daily commute preferences</p>
            </div>
          </div>

          <!-- MEDICAL SECTION -->
          <div class="smart-subcard">
            <div class="subcard-title-bar">
              <span class="subcard-badge medical-badge"><i class="fas fa-stethoscope"></i></span>
              <div>
                <h4>Medical &amp; Health Information</h4>
              </div>
            </div>

            <!-- Allergies -->
            <div class="form-grid col-2" style="margin-top: 1rem;">
              <div class="form-group">
                <label class="field-label">
                  Does the student have any allergies? <span class="req">*</span>
                </label>
                <div class="toggle-segmented-wrap">
                  <label class="toggle-choice-pill <?= (($_POST['has_allergies'] ?? 'No') === 'No') ? 'selected' : '' ?>">
                    <input type="radio" name="has_allergies" value="No" <?= (($_POST['has_allergies'] ?? 'No') === 'No') ? 'checked' : '' ?>>
                    <span class="pill-dot"></span>
                    <span><i class="fas fa-check-circle"></i> No Allergies</span>
                  </label>
                  <label class="toggle-choice-pill <?= (($_POST['has_allergies'] ?? '') === 'Yes') ? 'selected' : '' ?>">
                    <input type="radio" name="has_allergies" value="Yes" <?= (($_POST['has_allergies'] ?? '') === 'Yes') ? 'checked' : '' ?>>
                    <span class="pill-dot warning"></span>
                    <span><i class="fas fa-exclamation-triangle"></i> Yes, Has Allergies</span>
                  </label>
                </div>
              </div>

              <!-- Allergies List -->
              <div class="form-group" id="allergyDetailsGroup" style="display: <?= (($_POST['has_allergies'] ?? 'No') === 'Yes') ? 'block' : 'none' ?>;">
                <label for="allergies_list" class="field-label">
                  If yes, please list allergies:
                </label>
                <div class="input-shell">
                  <i class="fas fa-notes-medical shell-icon"></i>
                  <input type="text" name="allergies_list" id="allergies_list" class="field-input" 
                         placeholder="List allergies (e.g. Peanuts, Dust)" 
                         value="<?= htmlspecialchars($_POST['allergies_list'] ?? '') ?>">
                </div>
              </div>
            </div>

            <!-- Regular Medication -->
            <div class="form-group" style="margin-top: 1.2rem;">
              <label for="regular_medications" class="field-label">
                Regular medication (if any):
              </label>
              <div class="input-shell">
                <i class="fas fa-pills shell-icon"></i>
                <input type="text" name="regular_medications" id="regular_medications" class="field-input" 
                       placeholder="Regular medication name (optional)" 
                       value="<?= htmlspecialchars($_POST['regular_medications'] ?? '') ?>">
              </div>
            </div>

            <!-- Medical Conditions -->
            <div class="form-grid col-2" style="margin-top: 1.2rem;">
              <div class="form-group">
                <label class="field-label">
                  Medical conditions we should be aware of? <span class="req">*</span>
                </label>
                <div class="toggle-segmented-wrap">
                  <label class="toggle-choice-pill <?= (($_POST['has_medical_conditions'] ?? 'No') === 'No') ? 'selected' : '' ?>">
                    <input type="radio" name="has_medical_conditions" value="No" <?= (($_POST['has_medical_conditions'] ?? 'No') === 'No') ? 'checked' : '' ?>>
                    <span class="pill-dot"></span>
                    <span><i class="fas fa-check-circle"></i> No Condition</span>
                  </label>
                  <label class="toggle-choice-pill <?= (($_POST['has_medical_conditions'] ?? '') === 'Yes') ? 'selected' : '' ?>">
                    <input type="radio" name="has_medical_conditions" value="Yes" <?= (($_POST['has_medical_conditions'] ?? '') === 'Yes') ? 'checked' : '' ?>>
                    <span class="pill-dot warning"></span>
                    <span><i class="fas fa-heart-pulse"></i> Yes, Has Condition</span>
                  </label>
                </div>
              </div>

              <!-- Medical Details -->
              <div class="form-group" id="conditionDetailsGroup" style="display: <?= (($_POST['has_medical_conditions'] ?? 'No') === 'Yes') ? 'block' : 'none' ?>;">
                <label for="medical_conditions_details" class="field-label">
                  If yes, please specify condition:
                </label>
                <div class="input-shell">
                  <i class="fas fa-file-medical-alt shell-icon"></i>
                  <input type="text" name="medical_conditions_details" id="medical_conditions_details" class="field-input" 
                         placeholder="Medical condition details (e.g. Asthma)" 
                         value="<?= htmlspecialchars($_POST['medical_conditions_details'] ?? '') ?>">
                </div>
              </div>
            </div>

            <!-- Preferred Hospital -->
            <div class="form-group" style="margin-top: 1.2rem;">
              <label for="preferred_hospital" class="field-label">
                Preferred Hospital (in emergency):
              </label>
              <div class="input-shell">
                <i class="fas fa-hospital-alt shell-icon text-danger"></i>
                <input type="text" name="preferred_hospital" id="preferred_hospital" class="field-input" 
                       placeholder="Preferred Hospital Name (optional)" 
                       value="<?= htmlspecialchars($_POST['preferred_hospital'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- TRANSPORTATION SECTION -->
          <div class="smart-subcard transport-subcard" style="margin-top: 1.5rem;">
            <div class="subcard-title-bar">
              <span class="subcard-badge transport-badge"><i class="fas fa-bus-alt"></i></span>
              <div>
                <h4>Transportation Arrangements</h4>
              </div>
            </div>

            <!-- Transportation Type Cards -->
            <div class="form-group" style="margin-top: 1rem;">
              <label class="field-label">
                Transportation Type <span class="req">*</span>
              </label>
              <div class="vehicle-card-grid">
                <label class="vehicle-card <?= (($_POST['transportation_type'] ?? '') === 'School Bus') ? 'selected' : '' ?>">
                  <input type="radio" name="transportation_type" value="School Bus" <?= (($_POST['transportation_type'] ?? '') === 'School Bus') ? 'checked' : '' ?>>
                  <span class="vehicle-icon"><i class="fas fa-bus-school"></i></span>
                  <div class="vehicle-meta">
                    <strong>School Bus</strong>
                    <small>Official route service</small>
                  </div>
                  <span class="vehicle-check"><i class="fas fa-circle-check"></i></span>
                </label>

                <label class="vehicle-card <?= (($_POST['transportation_type'] ?? 'Own Transport') === 'Own Transport') ? 'selected' : '' ?>">
                  <input type="radio" name="transportation_type" value="Own Transport" <?= (($_POST['transportation_type'] ?? 'Own Transport') === 'Own Transport') ? 'checked' : '' ?>>
                  <span class="vehicle-icon"><i class="fas fa-car-side"></i></span>
                  <div class="vehicle-meta">
                    <strong>Own Transport</strong>
                    <small>Personal commute</small>
                  </div>
                  <span class="vehicle-check"><i class="fas fa-circle-check"></i></span>
                </label>

                <label class="vehicle-card <?= (($_POST['transportation_type'] ?? '') === 'Walking') ? 'selected' : '' ?>">
                  <input type="radio" name="transportation_type" value="Walking" <?= (($_POST['transportation_type'] ?? '') === 'Walking') ? 'checked' : '' ?>>
                  <span class="vehicle-icon"><i class="fas fa-person-walking"></i></span>
                  <div class="vehicle-meta">
                    <strong>Walking</strong>
                    <small>Nearby residence</small>
                  </div>
                  <span class="vehicle-check"><i class="fas fa-circle-check"></i></span>
                </label>
              </div>
            </div>

            <div class="form-grid col-2" style="margin-top: 1.2rem;">
              <!-- Pick-up Point -->
              <div class="form-group">
                <label for="pickup_point" class="field-label">
                  Pick-up Point (Morning)
                </label>
                <div class="input-shell">
                  <i class="fas fa-map-pin shell-icon"></i>
                  <input type="text" name="pickup_point" id="pickup_point" class="field-input" 
                         placeholder="Morning pick-up point" 
                         value="<?= htmlspecialchars($_POST['pickup_point'] ?? '') ?>">
                </div>
              </div>

              <!-- Drop-off Point -->
              <div class="form-group">
                <label for="dropoff_point" class="field-label">
                  Drop-off Point (Afternoon)
                </label>
                <div class="input-shell">
                  <i class="fas fa-map-pin shell-icon"></i>
                  <input type="text" name="dropoff_point" id="dropoff_point" class="field-input" 
                         placeholder="Afternoon drop-off point" 
                         value="<?= htmlspecialchars($_POST['dropoff_point'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Step 4 Navigation Bar -->
          <div class="step-footer-nav">
            <button type="button" class="btn-step prev-step" onclick="goToStep(3)">
              <i class="fas fa-arrow-left"></i>
              <span>Back: Emergency</span>
            </button>
            <button type="button" class="btn-step next-step" onclick="goToStep(5)">
              <span>Continue to Declaration &amp; Docs</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </section>

        <!-- ========================================================
             STEP 5: DOCUMENTS, REVIEW & CONSENT
             ======================================================== -->
        <section class="wizard-step-panel" id="step-panel-5" data-panel="5">
          <div class="section-banner">
            <div class="banner-pill">STEP 5</div>
            <div class="banner-text">
              <h3>Documents Checklist &amp; Declaration</h3>
              <p>Verify information, select ready documents, and submit application</p>
            </div>
          </div>

          <!-- DOCUMENTS SUBMITTED CHECKLIST -->
          <div class="smart-subcard" style="margin-bottom: 1.8rem;">
            <div class="subcard-title-bar">
              <span class="subcard-badge doc-badge"><i class="fas fa-folder-open"></i></span>
              <div>
                <h4>Documents Checklist</h4>
                <small>Select the documents you have ready for verification</small>
              </div>
            </div>

            <div class="doc-checklist-grid" style="margin-top: 1rem;">
              <label class="doc-check-card selected">
                <input type="checkbox" name="documents_submitted[]" value="Student Online Birth Certificate Copy (17 Digits)" checked>
                <span class="doc-icon"><i class="fas fa-id-card"></i></span>
                <span class="doc-details">
                  <strong>Student Birth Certificate Copy</strong>
                  <small>17-Digit Online Verified Copy</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card">
                <input type="checkbox" name="documents_submitted[]" value="Student Passport Copy (if available)">
                <span class="doc-icon"><i class="fas fa-passport"></i></span>
                <span class="doc-details">
                  <strong>Student Passport Copy</strong>
                  <small>If applicable</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card selected">
                <input type="checkbox" name="documents_submitted[]" value="4 Passport-size Color Photographs of Student" checked>
                <span class="doc-icon"><i class="fas fa-camera"></i></span>
                <span class="doc-details">
                  <strong>4 Passport Color Photographs</strong>
                  <small>Recent passport-size photo</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card selected">
                <input type="checkbox" name="documents_submitted[]" value="Father's NID / Passport Copy" checked>
                <span class="doc-icon"><i class="fas fa-id-badge"></i></span>
                <span class="doc-details">
                  <strong>Father's NID / Passport Copy</strong>
                  <small>Identity Card copy</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card selected">
                <input type="checkbox" name="documents_submitted[]" value="Mother's NID / Passport Copy" checked>
                <span class="doc-icon"><i class="fas fa-id-badge"></i></span>
                <span class="doc-details">
                  <strong>Mother's NID / Passport Copy</strong>
                  <small>Identity Card copy</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card">
                <input type="checkbox" name="documents_submitted[]" value="Previous School Transfer Certificate (TC)">
                <span class="doc-icon"><i class="fas fa-file-invoice"></i></span>
                <span class="doc-details">
                  <strong>Transfer Certificate (TC)</strong>
                  <small>From previous school (if any)</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card">
                <input type="checkbox" name="documents_submitted[]" value="Previous Academic Progress Report / Marksheet">
                <span class="doc-icon"><i class="fas fa-graduation-cap"></i></span>
                <span class="doc-details">
                  <strong>Academic Progress Report</strong>
                  <small>Previous marksheet (if any)</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>

              <label class="doc-check-card">
                <input type="checkbox" name="documents_submitted[]" value="Immunization / Vaccine Card Copy">
                <span class="doc-icon"><i class="fas fa-syringe"></i></span>
                <span class="doc-details">
                  <strong>Immunization / Vaccine Card</strong>
                  <small>Health record copy</small>
                </span>
                <span class="doc-check-box"><i class="fas fa-check"></i></span>
              </label>
            </div>

            <!-- Other Attached Documents -->
            <div class="form-group" style="margin-top: 1.2rem;">
              <label for="documents_other" class="field-label">
                Other Attached Documents (if any):
              </label>
              <div class="input-shell">
                <i class="fas fa-paperclip shell-icon"></i>
                <input type="text" name="documents_other" id="documents_other" class="field-input" 
                       placeholder="Other documents (optional)" 
                       value="<?= htmlspecialchars($_POST['documents_other'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- SMART REAL-TIME APPLICATION REVIEW CARD -->
          <div class="smart-review-card">
            <div class="review-header">
              <span class="review-crest"><i class="fas fa-shield-halved"></i></span>
              <div>
                <strong>Application Summary Preview</strong>
                <small>Verify your entered key details before final submission</small>
              </div>
            </div>
            <div class="review-grid">
              <div class="review-item">
                <span class="rev-label">Student Name</span>
                <strong class="rev-val" id="revStudentName">--</strong>
              </div>
              <div class="review-item">
                <span class="rev-label">Target Class</span>
                <strong class="rev-val" id="revTargetClass">--</strong>
              </div>
              <div class="review-item">
                <span class="rev-label">Date of Birth &amp; Age</span>
                <strong class="rev-val" id="revDobAge">--</strong>
              </div>
              <div class="review-item">
                <span class="rev-label">Father &amp; Phone</span>
                <strong class="rev-val" id="revFather">--</strong>
              </div>
              <div class="review-item">
                <span class="rev-label">Mother &amp; Phone</span>
                <strong class="rev-val" id="revMother">--</strong>
              </div>
              <div class="review-item">
                <span class="rev-label">Emergency Contact</span>
                <strong class="rev-val" id="revEmergency">--</strong>
              </div>
            </div>
          </div>

          <!-- CONSENT & AGREEMENT -->
          <div class="smart-seal-box" style="margin-top: 1.5rem;">
            <div class="seal-icon-wrap">
              <i class="fas fa-stamp"></i>
            </div>
            <div class="seal-content">
              <h4>Institutional Declaration by Parent / Guardian</h4>
              <p class="seal-quote">
                &ldquo;In the name of Allah, the Most Gracious, the Most Merciful.&rdquo;
              </p>
              <p>
                I solemnly certify that the information provided in this admission application is true, authentic, and complete to the best of my knowledge and belief. I acknowledge that if any statement, document, or record is found to be fraudulent or intentionally withheld, the application or enrollment will be subject to immediate cancellation.
              </p>
              <p>
                I pledge to respect and uphold the Islamic values, Cambridge curriculum discipline, and institutional regulations of Al Fatiha Institute.
              </p>

              <!-- Interactive Affirmation -->
              <div class="q44-affirmation-row">
                <label class="q44-custom-control" for="consent_agreed">
                  <input type="checkbox" name="consent_agreed" id="consent_agreed" value="1" required 
                         <?= !empty($_POST['consent_agreed']) ? 'checked' : '' ?>>
                  <span class="q44-checkbox-box"></span>
                  <span class="q44-label-text">
                    <strong>I certify that the above information is correct to the best of my knowledge. *</strong>
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- Step 5 Final Submit Navigation Bar -->
          <div class="step-footer-nav final-submit-bar">
            <button type="button" class="btn-step prev-step" onclick="goToStep(4)">
              <i class="fas fa-arrow-left"></i>
              <span>Back: Health &amp; Transit</span>
            </button>
            <button type="submit" name="submit_application" class="btn-step submit-application" id="btnSubmitForm">
              <i class="fas fa-paper-plane"></i>
              <span>Submit Admission Application</span>
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </section>

      </form>
    </div>
  </div>
</section>

<!-- ========================================================
     PREMIUM SMART ADMISSION CSS STYLES
     ======================================================== -->
<style>
/* ----------------------------------------------------
   Fonts & Core Variables
   ---------------------------------------------------- */
:root {
  --portal-primary: #064878;
  --portal-blue: #0e73b9;
  --portal-blue-light: #eaf4fb;
  --portal-cyan: #3ac0f6;
  --portal-gold: #f3b527;
  --portal-gold-dark: #b45309;
  --portal-dark: #032d4e;
  --portal-text: #1e293b;
  --portal-muted: #64748b;
  --portal-border: #cbd5e1;
  --portal-border-light: #e2e8f0;
  --portal-card-bg: #ffffff;
  --portal-emerald: #10b981;
}

/* ----------------------------------------------------
   Hero Banner Styling
   ---------------------------------------------------- */
.smart-hero-section {
  background: linear-gradient(135deg, #032d4e 0%, #064878 50%, #0e73b9 100%);
  color: #ffffff;
  padding: 4.2rem 0 3.8rem 0;
  text-align: center;
  position: relative;
  overflow: hidden;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.smart-hero-particles .particle {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(40px);
}
.particle.p-1 {
  width: 320px;
  height: 320px;
  top: -80px;
  right: -60px;
  background: radial-gradient(circle, rgba(243, 181, 39, 0.22) 0%, transparent 70%);
}
.particle.p-2 {
  width: 280px;
  height: 280px;
  bottom: -60px;
  left: -40px;
  background: radial-gradient(circle, rgba(58, 192, 246, 0.22) 0%, transparent 70%);
}
.particle.p-3 {
  width: 200px;
  height: 200px;
  top: 40%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
}
.smart-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 6px 18px;
  border-radius: 30px;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #ffffff;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  margin-bottom: 1rem;
}
.text-gold { color: var(--portal-gold); }
.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.6);
}
.badge-status {
  background: var(--portal-gold);
  color: #032d4e;
  font-size: 0.68rem;
  padding: 2px 8px;
  border-radius: 12px;
  font-weight: 900;
}
.smart-hero-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2.85rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  margin: 0 0 0.8rem 0;
  color: #ffffff;
}
.smart-hero-subtitle {
  font-size: 1.15rem;
  color: rgba(255, 255, 255, 0.9);
  max-width: 680px;
  margin: 0 auto 1.8rem auto;
  line-height: 1.6;
}
.smart-feature-chips {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}
.feature-chip {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #ffffff;
}
.feature-chip i {
  color: var(--portal-gold);
  margin-right: 4px;
}

/* ----------------------------------------------------
   Tuition Dropdown Accordion
   ---------------------------------------------------- */
.fee-accordion-wrap {
  margin-bottom: 2rem;
}
.smart-fee-details {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid var(--portal-border-light);
  box-shadow: 0 4px 20px rgba(14, 115, 185, 0.05);
  overflow: hidden;
  transition: all 0.3s;
}
.smart-fee-summary {
  padding: 1.2rem 1.6rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #ffffff;
  list-style: none;
  user-select: none;
}
.smart-fee-summary::-webkit-details-marker { display: none; }
.summary-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.fee-icon-pill {
  width: 44px;
  height: 44px;
  background: var(--portal-blue-light);
  color: var(--portal-blue);
  border-radius: 12px;
  display: grid;
  place-items: center;
  font-size: 1.25rem;
}
.summary-left strong {
  display: block;
  font-size: 1.05rem;
  color: var(--portal-dark);
}
.summary-left small {
  color: var(--portal-muted);
  font-size: 0.82rem;
}
.summary-arrow {
  color: var(--portal-muted);
  transition: transform 0.25s ease;
}
details[open] .summary-arrow {
  transform: rotate(180deg);
}
.smart-fee-table-wrap {
  padding: 0 1.6rem 1.5rem 1.6rem;
  overflow-x: auto;
}
.smart-data-table {
  width: 100%;
  border-collapse: collapse;
}
.smart-data-table th {
  background: #f1f5f9;
  padding: 10px 14px;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--portal-dark);
  text-align: left;
  border-bottom: 1px solid var(--portal-border-light);
}
.smart-data-table td {
  padding: 12px 14px;
  font-size: 0.88rem;
  border-bottom: 1px solid #f1f5f9;
  color: var(--portal-text);
}
.age-pill {
  background: #e0f2fe;
  color: #0369a1;
  padding: 2px 8px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.78rem;
}
.price-highlight {
  font-weight: 800;
  color: var(--portal-blue);
}

/* ----------------------------------------------------
   Main Smart Application Portal Card
   ---------------------------------------------------- */
.smart-app-card {
  background: var(--portal-card-bg);
  border-radius: 24px;
  border: 1px solid var(--portal-border-light);
  box-shadow: 0 20px 50px -10px rgba(14, 115, 185, 0.12), 0 0 1px rgba(0, 0, 0, 0.05);
  max-width: 1020px;
  margin: 0 auto;
  overflow: hidden;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

/* Portal Head */
.smart-portal-head {
  background: linear-gradient(135deg, #f8fafc 0%, #eaf4fb 100%);
  border-bottom: 1px solid var(--portal-border-light);
  padding: 2.2rem 2.4rem 1.4rem 2.4rem;
}
.portal-branding-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
  margin-bottom: 1.8rem;
}
.portal-brand-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.brand-crest {
  width: 58px;
  height: 58px;
  background: linear-gradient(135deg, var(--portal-blue), var(--portal-primary));
  color: #ffffff;
  border-radius: 16px;
  display: grid;
  place-items: center;
  font-size: 1.7rem;
  box-shadow: 0 8px 20px rgba(14, 115, 185, 0.28);
}
.portal-tag {
  display: inline-block;
  background: rgba(243, 181, 39, 0.2);
  color: #92400e;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding: 2px 10px;
  border-radius: 14px;
  margin-bottom: 4px;
}
.portal-main-heading {
  font-family: 'Outfit', sans-serif;
  font-size: 1.85rem;
  font-weight: 800;
  color: var(--portal-dark);
  margin: 0;
  letter-spacing: -0.02em;
}
.portal-desc {
  color: var(--portal-muted);
  font-size: 0.9rem;
  margin: 4px 0 0 0;
}
.portal-academic-card {
  background: #ffffff;
  border: 1px solid var(--portal-border);
  border-radius: 14px;
  padding: 10px 18px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.acad-label {
  display: block;
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--portal-muted);
  letter-spacing: 0.08em;
}
.acad-year {
  display: block;
  font-size: 1.25rem;
  color: var(--portal-blue);
  font-weight: 900;
  line-height: 1.2;
}
.acad-mode {
  display: block;
  font-size: 0.72rem;
  color: var(--portal-emerald);
  font-weight: 700;
  margin-top: 2px;
}

/* ----------------------------------------------------
   Live Progress Wizard Tracker
   ---------------------------------------------------- */
.smart-wizard-tracker {
  margin-bottom: 1.5rem;
}
.tracker-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.step-counter-text {
  display: flex;
  align-items: center;
  gap: 10px;
}
.step-num-pill {
  background: var(--portal-blue);
  color: #ffffff;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 800;
}
.step-counter-text strong {
  font-size: 0.95rem;
  color: var(--portal-dark);
}
.progress-pill-badge {
  background: #ffffff;
  border: 1px solid var(--portal-border);
  color: var(--portal-blue);
  padding: 3px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 800;
}
.tracker-bar {
  height: 8px;
  background: var(--portal-border-light);
  border-radius: 20px;
  overflow: hidden;
}
.tracker-fill {
  height: 100%;
  background: linear-gradient(90deg, #3ac0f6, #0e73b9, #f3b527);
  border-radius: 20px;
  transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ----------------------------------------------------
   Interactive Stepper Nav Tabs
   ---------------------------------------------------- */
.smart-stepper-nav {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
}
.nav-step-tab {
  background: #ffffff;
  border: 1.5px solid var(--portal-border-light);
  border-radius: 12px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: left;
}
.nav-step-tab .tab-bubble {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: #f1f5f9;
  color: var(--portal-muted);
  display: grid;
  place-items: center;
  font-size: 0.9rem;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.nav-step-tab .tab-meta strong {
  display: block;
  font-size: 0.82rem;
  color: var(--portal-text);
  line-height: 1.2;
}
.nav-step-tab .tab-meta small {
  display: block;
  font-size: 0.68rem;
  color: var(--portal-muted);
}
.nav-step-tab:hover {
  border-color: var(--portal-blue);
  background: #f8fafc;
}
.nav-step-tab.active {
  background: linear-gradient(135deg, var(--portal-blue) 0%, var(--portal-primary) 100%);
  border-color: var(--portal-primary);
  box-shadow: 0 4px 15px rgba(14, 115, 185, 0.28);
}
.nav-step-tab.active .tab-bubble {
  background: #ffffff;
  color: var(--portal-blue);
}
.nav-step-tab.active .tab-meta strong {
  color: #ffffff;
}
.nav-step-tab.active .tab-meta small {
  color: rgba(255, 255, 255, 0.85);
}
.nav-step-tab.completed {
  border-color: #a7f3d0;
  background: #f0fdf4;
}
.nav-step-tab.completed .tab-bubble {
  background: var(--portal-emerald);
  color: #ffffff;
}
.nav-step-tab.completed .tab-meta strong {
  color: #065f46;
}

/* ----------------------------------------------------
   Wizard Step Panels
   ---------------------------------------------------- */
.wizard-step-panel {
  display: none;
  padding: 2.4rem;
  animation: slidePanel 0.3s ease-out;
}
.wizard-step-panel.active {
  display: block;
}
@keyframes slidePanel {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Step Section Banner */
.section-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: linear-gradient(135deg, #f0f7fb 0%, #ffffff 100%);
  border-left: 5px solid var(--portal-blue);
  border-radius: 12px;
  border-top: 1px solid var(--portal-border-light);
  border-right: 1px solid var(--portal-border-light);
  border-bottom: 1px solid var(--portal-border-light);
  padding: 1.1rem 1.4rem;
  margin-bottom: 2rem;
}
.banner-pill {
  background: var(--portal-blue);
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding: 6px 12px;
  border-radius: 8px;
  flex-shrink: 0;
}
.banner-text h3 {
  font-family: 'Outfit', sans-serif;
  margin: 0;
  font-size: 1.25rem;
  color: var(--portal-dark);
  font-weight: 800;
}
.banner-text p {
  margin: 2px 0 0 0;
  font-size: 0.84rem;
  color: var(--portal-muted);
}

/* ----------------------------------------------------
   Form Grids & Layout
   ---------------------------------------------------- */
.form-grid {
  display: grid;
  gap: 1.2rem;
}
.col-2 { grid-template-columns: repeat(2, 1fr); }
.col-3 { grid-template-columns: repeat(3, 1fr); }
.col-4 { grid-template-columns: repeat(4, 1fr); }

/* ----------------------------------------------------
   Smart Form Fields & Controls
   ---------------------------------------------------- */
.form-group {
  display: flex;
  flex-direction: column;
}
.field-label {
  font-size: 0.86rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.q-tag {
  background: #e0f2fe;
  color: #0369a1;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
}
.req {
  color: #ef4444;
  font-weight: 800;
}
.auto-badge {
  font-size: 0.65rem;
  background: #f1f5f9;
  color: var(--portal-muted);
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: auto;
  font-weight: 600;
}
.input-shell {
  position: relative;
  display: flex;
  align-items: center;
}
.shell-icon {
  position: absolute;
  left: 14px;
  color: #94a3b8;
  font-size: 0.95rem;
  pointer-events: none;
  transition: color 0.2s;
}
.field-input, .field-select, .field-textarea {
  width: 100%;
  padding: 12px 14px 12px 42px;
  border: 1.5px solid var(--portal-border);
  border-radius: 10px;
  font-size: 0.92rem;
  color: var(--portal-text);
  background: #fcfdfe;
  transition: all 0.2s ease;
  font-family: inherit;
}
.field-textarea {
  padding-left: 42px;
  resize: vertical;
}
.textarea-shell .shell-icon {
  top: 15px;
}
.field-input:focus, .field-select:focus, .field-textarea:focus {
  outline: none;
  border-color: var(--portal-blue);
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(14, 115, 185, 0.14);
}
.input-shell:focus-within .shell-icon {
  color: var(--portal-blue);
}
.uppercase-field {
  text-transform: uppercase;
  letter-spacing: 0.03em;
  font-weight: 600;
}
.highlight-field {
  border-color: #93c5fd;
  background: #ffffff;
}
.readonly-shell .readonly-field {
  background: #f8fafc;
  color: var(--portal-dark);
  font-weight: 700;
  border-color: #cbd5e1;
}
.field-help {
  font-size: 0.74rem;
  color: var(--portal-muted);
  margin-top: 4px;
  line-height: 1.3;
}

/* ----------------------------------------------------
   Interactive Gender Cards
   ---------------------------------------------------- */
.gender-segmented-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.gender-card {
  border: 1.5px solid var(--portal-border);
  border-radius: 10px;
  padding: 9px 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  background: #f8fafc;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--portal-text);
  transition: all 0.2s ease;
  margin: 0;
}
.gender-card input { display: none; }
.gender-icon { font-size: 1.1rem; }
.gender-card:hover { border-color: var(--portal-blue); }
.gender-card.selected {
  background: #e0f2fe;
  border-color: var(--portal-blue);
  color: var(--portal-blue);
  box-shadow: 0 2px 10px rgba(14, 115, 185, 0.16);
}

/* ----------------------------------------------------
   Label Action Row & Address Sync
   ---------------------------------------------------- */
.label-action-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.copy-address-toggle {
  cursor: pointer;
  margin: 0;
}
.copy-address-toggle input { display: none; }
.toggle-pill-custom {
  background: #e0f2fe;
  color: #0369a1;
  font-size: 0.76rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 14px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.copy-address-toggle input:checked + .toggle-pill-custom {
  background: var(--portal-blue);
  color: #ffffff;
}

/* ----------------------------------------------------
   Parent Profile Cards (Step 2)
   ---------------------------------------------------- */
.profile-card {
  background: #ffffff;
  border: 1.5px solid var(--portal-border-light);
  border-radius: 16px;
  padding: 1.6rem;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}
.father-theme { border-left: 5px solid var(--portal-blue); }
.mother-theme { border-left: 5px solid var(--portal-gold); }
.other-guardian-theme { border-left: 5px solid #94a3b8; }

.profile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--portal-border-light);
  padding-bottom: 1rem;
  margin-bottom: 1.3rem;
  flex-wrap: wrap;
  gap: 10px;
}
.profile-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}
.avatar-ring {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  font-size: 1.15rem;
}
.father-ring { background: #e0f2fe; color: var(--portal-blue); }
.mother-ring { background: #fef3c7; color: #b45309; }
.profile-badge h4 {
  margin: 0;
  font-size: 1.1rem;
  color: var(--portal-dark);
}
.profile-range {
  font-size: 0.72rem;
  color: var(--portal-muted);
}
.btn-quick-chip {
  background: #f1f5f9;
  border: 1px solid var(--portal-border);
  color: var(--portal-blue);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-quick-chip:hover {
  background: var(--portal-blue);
  color: #ffffff;
  border-color: var(--portal-blue);
}

/* Notification Email Banner */
.notification-email-card {
  background: linear-gradient(135deg, #eef7fc 0%, #ffffff 100%);
  border: 1.5px solid #bae6fd;
  border-radius: 14px;
  padding: 1.3rem;
  display: flex;
  gap: 16px;
  align-items: flex-start;
}
.email-card-icon {
  width: 44px;
  height: 44px;
  background: var(--portal-blue);
  color: #ffffff;
  border-radius: 12px;
  display: grid;
  place-items: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}
.email-card-body {
  flex: 1;
}

/* Custom Switch for Guardian */
.custom-switch-label {
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  margin: 0;
}
.custom-switch-label input {
  width: 20px;
  height: 20px;
  accent-color: var(--portal-blue);
}
.switch-content strong {
  display: block;
  font-size: 0.96rem;
  color: var(--portal-dark);
}
.switch-content small {
  display: block;
  font-size: 0.78rem;
  color: var(--portal-muted);
}

/* ----------------------------------------------------
   Emergency Quick Fill Banner (Step 3)
   ---------------------------------------------------- */
.quick-fill-card {
  background: #fffbeb;
  border: 1.5px solid #fde68a;
  border-radius: 14px;
  padding: 1.1rem 1.4rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}
.quick-fill-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f59e0b;
  color: #ffffff;
  display: grid;
  place-items: center;
  font-size: 1.1rem;
}
.quick-fill-info {
  flex: 1;
}
.quick-fill-info strong {
  display: block;
  font-size: 0.95rem;
  color: #92400e;
}
.quick-fill-info span {
  display: block;
  font-size: 0.8rem;
  color: #b45309;
}
.quick-fill-actions {
  display: flex;
  gap: 8px;
}
.btn-fill-pill {
  background: #ffffff;
  border: 1px solid #d97706;
  color: #92400e;
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-fill-pill:hover {
  background: #d97706;
  color: #ffffff;
}

/* ----------------------------------------------------
   Step 4 Subcards & Widgets
   ---------------------------------------------------- */
.smart-subcard {
  background: #ffffff;
  border: 1.5px solid var(--portal-border-light);
  border-radius: 16px;
  padding: 1.5rem;
}
.subcard-title-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--portal-border-light);
  padding-bottom: 0.8rem;
}
.subcard-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 1.1rem;
}
.medical-badge { background: #fee2e2; color: #dc2626; }
.transport-badge { background: #e0f2fe; color: var(--portal-blue); }
.doc-badge { background: #fef3c7; color: #b45309; }
.subcard-title-bar h4 {
  margin: 0;
  font-size: 1.1rem;
  color: var(--portal-dark);
}
.subcard-title-bar small {
  color: var(--portal-muted);
  font-size: 0.74rem;
}

/* Toggle Choice Pills (Yes/No) */
.toggle-segmented-wrap {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.toggle-choice-pill {
  border: 1.5px solid var(--portal-border);
  border-radius: 10px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  background: #f8fafc;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--portal-text);
  transition: all 0.2s;
  margin: 0;
}
.toggle-choice-pill input { display: none; }
.pill-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #94a3b8;
}
.pill-dot.warning { background: #f59e0b; }
.toggle-choice-pill.selected {
  border-color: var(--portal-blue);
  background: #e0f2fe;
  color: var(--portal-blue);
}
.toggle-choice-pill.selected .pill-dot { background: var(--portal-blue); }

/* Transportation Cards */
.vehicle-card-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.vehicle-card {
  border: 2px solid var(--portal-border);
  border-radius: 14px;
  padding: 16px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  background: #ffffff;
  transition: all 0.2s;
  position: relative;
  margin: 0;
}
.vehicle-card input { display: none; }
.vehicle-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: #f1f5f9;
  color: var(--portal-muted);
  display: grid;
  place-items: center;
  font-size: 1.35rem;
  transition: all 0.2s;
  flex-shrink: 0;
}
.vehicle-meta strong {
  display: block;
  font-size: 0.95rem;
  color: var(--portal-text);
}
.vehicle-meta small {
  display: block;
  font-size: 0.72rem;
  color: var(--portal-muted);
}
.vehicle-check {
  margin-left: auto;
  color: transparent;
  font-size: 1.1rem;
  transition: all 0.2s;
}
.vehicle-card:hover {
  border-color: var(--portal-blue);
}
.vehicle-card.selected {
  border-color: var(--portal-blue);
  background: #f0f7fb;
  box-shadow: 0 4px 18px rgba(14, 115, 185, 0.16);
}
.vehicle-card.selected .vehicle-icon {
  background: var(--portal-blue);
  color: #ffffff;
}
.vehicle-card.selected .vehicle-check {
  color: var(--portal-blue);
}

/* ----------------------------------------------------
   Step 5 Document Checklist Grid
   ---------------------------------------------------- */
.doc-checklist-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.doc-check-card {
  border: 1.5px solid var(--portal-border);
  border-radius: 12px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  background: #f8fafc;
  transition: all 0.2s;
  margin: 0;
}
.doc-check-card input { display: none; }
.doc-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #ffffff;
  color: var(--portal-blue);
  display: grid;
  place-items: center;
  font-size: 1.05rem;
  border: 1px solid var(--portal-border-light);
  flex-shrink: 0;
}
.doc-details { flex: 1; }
.doc-details strong {
  display: block;
  font-size: 0.88rem;
  color: var(--portal-text);
}
.doc-details small {
  display: block;
  font-size: 0.72rem;
  color: var(--portal-muted);
}
.doc-check-box {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 1.5px solid var(--portal-border);
  display: grid;
  place-items: center;
  font-size: 0.75rem;
  color: transparent;
  background: #ffffff;
  transition: all 0.2s;
}
.doc-check-card.selected {
  border-color: var(--portal-blue);
  background: #eef7fc;
}
.doc-check-card.selected .doc-check-box {
  background: var(--portal-blue);
  border-color: var(--portal-blue);
  color: #ffffff;
}

/* ----------------------------------------------------
   Smart Real-Time Review Preview Card
   ---------------------------------------------------- */
.smart-review-card {
  background: #f8fafc;
  border: 1.5px solid #cbd5e1;
  border-radius: 16px;
  padding: 1.5rem;
}
.review-header {
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.8rem;
  margin-bottom: 1rem;
}
.review-crest {
  width: 36px;
  height: 36px;
  background: var(--portal-dark);
  color: var(--portal-gold);
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 1.1rem;
}
.review-header strong {
  display: block;
  font-size: 1.05rem;
  color: var(--portal-dark);
}
.review-header small {
  color: var(--portal-muted);
  font-size: 0.76rem;
}
.review-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}
.review-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 14px;
}
.rev-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--portal-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 2px;
}
.rev-val {
  display: block;
  font-size: 0.92rem;
  color: var(--portal-dark);
  word-break: break-word;
}

/* ----------------------------------------------------
   Question 44 Seal Card
   ---------------------------------------------------- */
.smart-seal-box {
  background: #fffdf5;
  border: 2px solid #fde68a;
  border-radius: 18px;
  padding: 1.8rem;
  display: flex;
  gap: 18px;
  box-shadow: 0 4px 20px rgba(243, 181, 39, 0.12);
}
.seal-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--portal-gold);
  color: var(--portal-dark);
  display: grid;
  place-items: center;
  font-size: 1.6rem;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(243, 181, 39, 0.35);
}
.seal-content h4 {
  margin: 0 0 6px 0;
  color: #92400e;
  font-size: 1.2rem;
}
.seal-quote {
  font-family: 'Amiri', serif;
  font-size: 1.05rem;
  color: #b45309;
  margin: 0 0 8px 0;
  font-style: italic;
}
.seal-content p {
  font-size: 0.88rem;
  line-height: 1.65;
  color: #78350f;
  margin-bottom: 8px;
}
.q44-affirmation-row {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1.5px dashed #f59e0b;
}
.q44-custom-control {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  cursor: pointer;
  margin: 0;
}
.q44-custom-control input {
  width: 22px;
  height: 22px;
  accent-color: var(--portal-blue);
  margin-top: 3px;
}
.q44-label-text strong {
  display: block;
  font-size: 1.05rem;
  color: #451a03;
}
.q44-label-text small {
  display: block;
  font-size: 0.78rem;
  color: #92400e;
}

/* ----------------------------------------------------
   Step Footer Navigation Buttons
   ---------------------------------------------------- */
.step-footer-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  margin-top: 2.2rem;
  padding-top: 1.6rem;
  border-top: 1px solid var(--portal-border-light);
}
.step-footer-nav.single-right {
  justify-content: flex-end;
}
.btn-step {
  padding: 13px 28px;
  border-radius: 12px;
  font-size: 0.95rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.25s ease;
  border: none;
  font-family: inherit;
}
.next-step {
  background: linear-gradient(135deg, var(--portal-blue) 0%, var(--portal-primary) 100%);
  color: #ffffff;
  box-shadow: 0 6px 18px rgba(14, 115, 185, 0.28);
}
.next-step:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(14, 115, 185, 0.38);
}
.prev-step {
  background: #f1f5f9;
  color: var(--portal-text);
  border: 1px solid var(--portal-border);
}
.prev-step:hover {
  background: #e2e8f0;
}
.submit-application {
  background: linear-gradient(135deg, #f3b527 0%, #eab308 100%);
  color: #032d4e;
  font-size: 1.1rem;
  font-weight: 800;
  padding: 15px 36px;
  box-shadow: 0 8px 28px rgba(243, 181, 39, 0.45);
}
.submit-application:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 35px rgba(243, 181, 39, 0.6);
}

/* ----------------------------------------------------
   Success Receipt Card
   ---------------------------------------------------- */
.smart-receipt-card {
  background: #ecfdf5;
  border: 2px solid var(--portal-emerald);
  border-radius: 20px;
  padding: 2.8rem 2.4rem;
  text-align: center;
  margin: 1.8rem;
  animation: slidePanel 0.4s ease;
}
.receipt-icon {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: var(--portal-emerald);
  color: #ffffff;
  display: grid;
  place-items: center;
  font-size: 2.5rem;
  margin: 0 auto 1.2rem auto;
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35);
}
.receipt-eyebrow {
  display: inline-block;
  background: #d1fae5;
  color: #065f46;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding: 3px 12px;
  border-radius: 14px;
  margin-bottom: 6px;
}
.receipt-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2rem;
  color: #065f46;
  margin: 0 0 8px 0;
}
.receipt-msg {
  font-size: 1.05rem;
  color: #047857;
  max-width: 650px;
  margin: 0 auto 1.5rem auto;
}
.receipt-ref-container {
  background: #ffffff;
  border: 2px dashed var(--portal-emerald);
  border-radius: 14px;
  padding: 14px 26px;
  display: inline-flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 1.8rem;
}
.ref-sub {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--portal-muted);
  letter-spacing: 0.06em;
}
.ref-code {
  font-size: 1.7rem;
  font-weight: 900;
  color: var(--portal-dark);
  font-family: monospace;
}
.ref-copy-btn {
  background: #f1f5f9;
  border: 1px solid var(--portal-border);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}
.receipt-guidelines {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 1.8rem;
  flex-wrap: wrap;
}
.guide-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
  color: #065f46;
}
.receipt-buttons {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}
.btn-receipt {
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  border: none;
  font-size: 0.95rem;
}
.print-btn {
  background: var(--portal-gold);
  color: #032d4e;
}
.reset-btn {
  background: #ffffff;
  border: 1px solid var(--portal-border);
  color: var(--portal-text);
}

/* Alert Error */
.smart-alert-card {
  margin: 1.5rem;
  padding: 1.1rem 1.4rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.smart-alert-card.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

/* ----------------------------------------------------
   Responsive Adjustments
   ---------------------------------------------------- */
@media (max-width: 960px) {
  .smart-stepper-nav {
    grid-template-columns: repeat(3, 1fr);
  }
  .col-3, .col-4 {
    grid-template-columns: repeat(2, 1fr);
  }
  .vehicle-card-grid {
    grid-template-columns: 1fr;
  }
  .doc-checklist-grid {
    grid-template-columns: 1fr;
  }
  .review-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 640px) {
  .smart-hero-title { font-size: 2rem; }
  .smart-portal-head { padding: 1.5rem 1.2rem; }
  .wizard-step-panel { padding: 1.4rem 1.2rem; }
  .portal-main-heading { font-size: 1.4rem; }
  .smart-stepper-nav { grid-template-columns: 1fr; }
  .col-2, .col-3, .col-4 { grid-template-columns: 1fr; }
  .gender-segmented-cards { grid-template-columns: 1fr; }
  .review-grid { grid-template-columns: 1fr; }
  .step-footer-nav { flex-direction: column-reverse; }
  .btn-step { width: 100%; justify-content: center; }
  .smart-seal-box { flex-direction: column; }
}
</style>

<!-- ========================================================
     SMART INTERACTIVE JAVASCRIPT ENGINE
     ======================================================== -->
<script>
let currentStep = 1;
const totalSteps = 5;

const stepDetails = [
  { title: "Student Information (Questions 1 – 11)", percent: 20 },
  { title: "Guardian Information (Questions 12 – 31)", percent: 40 },
  { title: "Emergency Contact (Questions 32 – 34)", percent: 60 },
  { title: "Medical & Transit (Questions 35 – 43)", percent: 80 },
  { title: "Documents & Consent (Question 44)", percent: 100 }
];

function updateReviewSummary() {
  const childName = document.getElementById('child_name');
  const classSelect = document.getElementById('target_class_id');
  const dob = document.getElementById('dob');
  const age = document.getElementById('age_jan_1');
  const fatherName = document.getElementById('father_name');
  const fatherPhone = document.getElementById('father_phone');
  const motherName = document.getElementById('mother_name');
  const motherPhone = document.getElementById('mother_phone');
  const emergName = document.getElementById('emergency_name');
  const emergPhone = document.getElementById('emergency_phone');

  const revChild = document.getElementById('revStudentName');
  if (revChild && childName) revChild.textContent = childName.value || '--';

  const revClass = document.getElementById('revTargetClass');
  if (revClass && classSelect) {
    const selectedOption = classSelect.options[classSelect.selectedIndex];
    revClass.textContent = selectedOption ? selectedOption.text.split('•')[0].trim() : '--';
  }

  const revDobAge = document.getElementById('revDobAge');
  if (revDobAge) {
    const dobVal = dob && dob.value ? dob.value : 'N/A';
    const ageVal = age && age.value ? `(${age.value})` : '';
    revDobAge.textContent = `${dobVal} ${ageVal}`.trim() || '--';
  }

  const revFather = document.getElementById('revFather');
  if (revFather) {
    const fName = fatherName && fatherName.value ? fatherName.value : 'N/A';
    const fPhone = fatherPhone && fatherPhone.value ? ` • ${fatherPhone.value}` : '';
    revFather.textContent = `${fName}${fPhone}`;
  }

  const revMother = document.getElementById('revMother');
  if (revMother) {
    const mName = motherName && motherName.value ? motherName.value : 'N/A';
    const mPhone = motherPhone && motherPhone.value ? ` • ${motherPhone.value}` : '';
    revMother.textContent = `${mName}${mPhone}`;
  }

  const revEmerg = document.getElementById('revEmergency');
  if (revEmerg) {
    const eName = emergName && emergName.value ? emergName.value : 'N/A';
    const ePhone = emergPhone && emergPhone.value ? ` • ${emergPhone.value}` : '';
    revEmerg.textContent = `${eName}${ePhone}`;
  }
}

function goToStep(step) {
  if (step < 1 || step > totalSteps) return;

  // Validate current panel before moving forward
  if (step > currentStep) {
    const currentPanel = document.getElementById('step-panel-' + currentStep);
    const requiredInputs = currentPanel.querySelectorAll('input[required], select[required], textarea[required]');
    let hasInvalid = false;
    for (let input of requiredInputs) {
      if (!input.checkValidity()) {
        input.reportValidity();
        input.focus();
        hasInvalid = true;
        break;
      }
    }
    if (hasInvalid) return;
  }

  // Switch Active Panels
  document.querySelectorAll('.wizard-step-panel').forEach(p => p.classList.remove('active'));
  const targetPanel = document.getElementById('step-panel-' + step);
  if (targetPanel) targetPanel.classList.add('active');

  // Update Navigation Stepper Tabs
  document.querySelectorAll('.nav-step-tab').forEach(tab => {
    const tabStep = parseInt(tab.getAttribute('data-step'));
    tab.classList.remove('active');
    if (tabStep === step) {
      tab.classList.add('active');
    }
    if (tabStep < step) {
      tab.classList.add('completed');
    }
  });

  // Update Progress Tracker
  currentStep = step;
  const info = stepDetails[currentStep - 1];
  const percentFill = document.getElementById('wizardProgressFill');
  const percentText = document.getElementById('percentText');
  const currentStepPill = document.getElementById('currentStepPill');
  const stepTitleDisplay = document.getElementById('stepTitleDisplay');

  if (percentFill) percentFill.style.width = info.percent + '%';
  if (percentText) percentText.textContent = info.percent + '%';
  if (currentStepPill) currentStepPill.textContent = `Step ${currentStep} of ${totalSteps}`;
  if (stepTitleDisplay) stepTitleDisplay.textContent = info.title;

  // If entering step 5, update live summary
  if (currentStep === 5) {
    updateReviewSummary();
  }

  // Smooth scroll up to form head
  const formAnchor = document.getElementById('apply-form');
  if (formAnchor) {
    formAnchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

document.addEventListener('DOMContentLoaded', function() {
  // 1. Tab Clicks
  document.querySelectorAll('.nav-step-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      const step = parseInt(this.getAttribute('data-step'));
      goToStep(step);
    });
  });

  // 2. Real-time Block Letters for Student Name
  const childName = document.getElementById('child_name');
  if (childName) {
    childName.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }

  // 3. Gender Card Selection
  document.querySelectorAll('.gender-card input').forEach(radio => {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.gender-card').forEach(c => c.classList.remove('selected'));
      if (this.checked) {
        this.closest('.gender-card').classList.add('selected');
      }
    });
  });

  // 4. Age as of 1st January Calculator
  const dobInput = document.getElementById('dob');
  const ageInput = document.getElementById('age_jan_1');
  const ageNote = document.getElementById('ageCalculatorNote');

  function calculateJan1Age() {
    if (!dobInput.value) return;
    const dob = new Date(dobInput.value);
    const currentYear = new Date().getFullYear();
    const jan1 = new Date(currentYear, 0, 1);

    let years = jan1.getFullYear() - dob.getFullYear();
    let months = jan1.getMonth() - dob.getMonth();
    if (months < 0) {
      years--;
      months += 12;
    }

    let calculatedText = '';
    if (years < 0) {
      calculatedText = 'Under 1 Year';
    } else {
      calculatedText = `${years} Year${years !== 1 ? 's' : ''} ${months} Month${months !== 1 ? 's' : ''}`;
    }

    ageInput.value = calculatedText;
    if (ageNote) {
      ageNote.innerHTML = `<i class="fas fa-check-circle"></i> Computed for Jan 1, ${currentYear}: <strong>${calculatedText}</strong>`;
    }
  }

  if (dobInput && ageInput) {
    dobInput.addEventListener('change', calculateJan1Age);
    if (dobInput.value) calculateJan1Age();
  }

  // 5. Present Address -> Permanent Address Sync
  const sameAddressCheck = document.getElementById('sameAddressCheck');
  const presentAddress = document.getElementById('present_address');
  const permAddress = document.getElementById('permanent_address');

  if (sameAddressCheck && presentAddress && permAddress) {
    sameAddressCheck.addEventListener('change', function() {
      if (this.checked) permAddress.value = presentAddress.value;
    });
    presentAddress.addEventListener('input', function() {
      if (sameAddressCheck.checked) permAddress.value = this.value;
    });
  }

  // 6. Copy Student Address to Father & Mother
  const btnCopyFather = document.getElementById('copyStudentToFather');
  const fatherAddress = document.getElementById('father_present_address');
  if (btnCopyFather && fatherAddress && presentAddress) {
    btnCopyFather.addEventListener('click', function() {
      fatherAddress.value = presentAddress.value;
      this.innerHTML = '<i class="fas fa-check"></i> Copied!';
      setTimeout(() => this.innerHTML = '<i class="fas fa-map-pin"></i> Use Student Address', 1800);
    });
  }

  const btnCopyMother = document.getElementById('copyStudentToMother');
  const motherAddress = document.getElementById('mother_present_address');
  if (btnCopyMother && motherAddress && presentAddress) {
    btnCopyMother.addEventListener('click', function() {
      motherAddress.value = presentAddress.value;
      this.innerHTML = '<i class="fas fa-check"></i> Copied!';
      setTimeout(() => this.innerHTML = '<i class="fas fa-map-pin"></i> Use Student Address', 1800);
    });
  }

  // 7. Toggle Other Legal Guardian Drawer
  const guardianOtherCheck = document.getElementById('guardian_is_other');
  const otherGuardianSec = document.getElementById('otherGuardianSection');
  if (guardianOtherCheck && otherGuardianSec) {
    guardianOtherCheck.addEventListener('change', function() {
      otherGuardianSec.style.display = this.checked ? 'block' : 'none';
    });
  }

  // 8. Emergency Contact 1-Click Copy
  const btnCopyFatherEmerg = document.getElementById('btnCopyFatherToEmergency');
  const btnCopyMotherEmerg = document.getElementById('btnCopyMotherToEmergency');
  const emergName = document.getElementById('emergency_name');
  const emergRel = document.getElementById('emergency_relationship');
  const emergPhone = document.getElementById('emergency_phone');

  if (btnCopyFatherEmerg) {
    btnCopyFatherEmerg.addEventListener('click', function() {
      emergName.value = document.getElementById('father_name').value;
      emergRel.value = 'Father';
      emergPhone.value = document.getElementById('father_phone').value;
      this.innerHTML = '<i class="fas fa-check"></i> Copied!';
      setTimeout(() => this.innerHTML = '<i class="fas fa-user-tie"></i> Use Father\'s Info', 1800);
    });
  }

  if (btnCopyMotherEmerg) {
    btnCopyMotherEmerg.addEventListener('click', function() {
      emergName.value = document.getElementById('mother_name').value;
      emergRel.value = 'Mother';
      emergPhone.value = document.getElementById('mother_phone').value;
      this.innerHTML = '<i class="fas fa-check"></i> Copied!';
      setTimeout(() => this.innerHTML = '<i class="fas fa-user"></i> Use Mother\'s Info', 1800);
    });
  }

  // 9. Allergies Yes/No Toggle Pill
  document.querySelectorAll('input[name="has_allergies"]').forEach(radio => {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.toggle-choice-pill:has(input[name="has_allergies"])').forEach(p => p.classList.remove('selected'));
      this.closest('.toggle-choice-pill').classList.add('selected');
      const box = document.getElementById('allergyDetailsGroup');
      if (box) {
        box.style.display = (this.value === 'Yes') ? 'block' : 'none';
        if (this.value === 'No') document.getElementById('allergies_list').value = '';
      }
    });
  });

  // 10. Medical Condition Yes/No Toggle Pill
  document.querySelectorAll('input[name="has_medical_conditions"]').forEach(radio => {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.toggle-choice-pill:has(input[name="has_medical_conditions"])').forEach(p => p.classList.remove('selected'));
      this.closest('.toggle-choice-pill').classList.add('selected');
      const box = document.getElementById('conditionDetailsGroup');
      if (box) {
        box.style.display = (this.value === 'Yes') ? 'block' : 'none';
        if (this.value === 'No') document.getElementById('medical_conditions_details').value = '';
      }
    });
  });

  // 11. Transportation Vehicle Cards
  document.querySelectorAll('.vehicle-card input').forEach(radio => {
    radio.addEventListener('change', function() {
      document.querySelectorAll('.vehicle-card').forEach(vc => vc.classList.remove('selected'));
      if (this.checked) {
        this.closest('.vehicle-card').classList.add('selected');
      }
    });
  });

  // 12. Documents Checklist Cards
  document.querySelectorAll('.doc-check-card input').forEach(chk => {
    chk.addEventListener('change', function() {
      if (this.checked) {
        this.closest('.doc-check-card').classList.add('selected');
      } else {
        this.closest('.doc-check-card').classList.remove('selected');
      }
    });
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
