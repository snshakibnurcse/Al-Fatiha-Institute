<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Get Student ID from query parameter
$student_pk = intval($_GET['id'] ?? 0);

if (!$student_pk) {
    echo "<div class='alert-msg alert-danger'>Invalid student ID. <a href='students.php'>Back to Directory</a></div>";
    require_once __DIR__ . '/layout_footer.php';
    exit();
}

// Handle Advance Payment Request for this Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_advance_payment'])) {
    $start_month = sanitize($_POST['start_month'] ?? date('Y-m'));
    $num_months = intval($_POST['num_months'] ?? 1);
    $mark_paid = isset($_POST['mark_paid']) && $_POST['mark_paid'] == '1';
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $ref_no = sanitize($_POST['reference_no'] ?? '');

    $result = generate_advance_fee_invoice($student_pk, $start_month, $num_months, $mark_paid, $payment_method, $ref_no);
    if ($result['success']) {
        $msg = $result['message'];
    } else {
        $err = $result['message'];
    }
}

// Handle Single Invoice Issue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_single_invoice'])) {
    $fee_type = sanitize($_POST['fee_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));

    if ($amount > 0 && !empty($fee_type)) {
        $inv_no = 'INV-' . date('Ym') . '-' . rand(100, 999);
        $db->prepare("INSERT INTO fees (invoice_no, student_id, fee_type, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'Unpaid')")
           ->execute([$inv_no, $student_pk, $fee_type, $amount, $due_date]);
        $msg = "Fee Invoice <strong>$inv_no</strong> issued successfully!";
    } else {
        $err = "Please enter valid fee description and amount.";
    }
}

// Handle Mark Paid for an invoice
if (isset($_GET['mark_paid'])) {
    $fee_id = intval($_GET['mark_paid']);
    mark_fee_as_paid($fee_id, 'Cash / Admin');
    $msg = "Fee invoice marked as Paid and synced to Income Ledger!";
}

// Fetch Student Data
$stStmt = $db->prepare("
    SELECT s.*, c.class_name, c.tuition_fee, u.username as parent_username, u.email as parent_email 
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    LEFT JOIN users u ON s.parent_user_id = u.id 
    WHERE s.id = ? LIMIT 1
");
$stStmt->execute([$student_pk]);
$student = $stStmt->fetch();

if (!$student) {
    echo "<div class='alert-msg alert-danger'>Student record not found. <a href='students.php'>Back to Directory</a></div>";
    require_once __DIR__ . '/layout_footer.php';
    exit();
}

// Fetch Fees & Payment History
$feesStmt = $db->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY id DESC");
$feesStmt->execute([$student_pk]);
$fees = $feesStmt->fetchAll();

$totalPaid = 0;
$totalDue = 0;
foreach ($fees as $f) {
    if ($f['status'] === 'Paid') {
        $totalPaid += $f['amount'];
    } else {
        $totalDue += $f['amount'];
    }
}

// Fetch Exam Results
$resultsStmt = $db->prepare("
    SELECT r.*, sub.subject_name 
    FROM results r 
    JOIN subjects sub ON r.subject_id = sub.id 
    WHERE r.student_id = ? 
    ORDER BY r.exam_term ASC, r.id ASC
");
$resultsStmt->execute([$student_pk]);
$results = $resultsStmt->fetchAll();

// Calculate Result Stats
$totalObtained = 0;
$totalMax = 0;
foreach ($results as $r) {
    $totalObtained += $r['marks_obtained'];
    $totalMax += $r['max_marks'];
}
$avgPercentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
$overallGrade = calculate_grade($totalObtained, $totalMax ?: 100);

// Fetch Attendance Stats
$attTotal = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_pk")->fetchColumn() ?: 0;
$attPresent = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_pk AND status IN ('Present', 'Late')")->fetchColumn() ?: 0;
$attRate = $attTotal > 0 ? round(($attPresent / $attTotal) * 100, 1) : 100;
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<!-- Top Action Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <a href="students.php" style="color: var(--admin-primary); font-weight: 700; text-decoration: none; font-size: 0.9rem;">
      <i class="fas fa-arrow-left"></i> Back to Students Directory
    </a>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0.3rem 0 0 0;">
      Student 360° Comprehensive Profile
    </h2>
  </div>
  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <button class="btn" style="background: #10b981; color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('advancePaymentModal')">
      <i class="fas fa-calendar-plus"></i> Advance Payment
    </button>
    <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('singleInvoiceModal')">
      <i class="fas fa-file-invoice"></i> Issue Invoice
    </button>
    <a href="../portal/results.php" target="_blank" class="btn" style="background: #f59e0b; color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none;">
      <i class="fas fa-print"></i> Report Card
    </a>
  </div>
</div>

<!-- Student Header Card -->
<div style="background: linear-gradient(135deg, #032d4e 0%, #064878 100%); color: #ffffff; border-radius: 18px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(3, 45, 78, 0.25); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
  <div style="display: flex; align-items: center; gap: 1.5rem;">
    <div style="width: 80px; height: 80px; background: #f3b527; color: #032d4e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; font-family: 'Outfit', sans-serif;">
      <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
    </div>
    <div>
      <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.3rem;">
        <h1 style="font-family: 'Outfit', sans-serif; margin: 0; font-size: 1.8rem;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
        <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 700;">
          ID: <?= htmlspecialchars($student['student_id']) ?>
        </span>
      </div>
      <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
        <i class="fas fa-school" style="color: #f3b527;"></i> Class: <strong><?= htmlspecialchars($student['class_name']) ?></strong> &nbsp;|&nbsp; 
        <i class="fas fa-hashtag" style="color: #f3b527;"></i> Roll No: <strong>#<?= $student['roll_no'] ?></strong> &nbsp;|&nbsp;
        <i class="fas fa-venus-mars" style="color: #f3b527;"></i> Gender: <strong><?= $student['gender'] ?></strong>
      </p>
      <p style="margin: 0.4rem 0 0 0; opacity: 0.8; font-size: 0.85rem;">
        Father: <strong><?= htmlspecialchars($student['father_name']) ?></strong> (<?= htmlspecialchars($student['phone']) ?>)
      </p>
    </div>
  </div>

  <div>
    <span class="badge" style="background: #10b981; color: #ffffff; padding: 0.5rem 1.2rem; border-radius: 30px; font-size: 0.95rem; font-weight: 800;">
      <i class="fas fa-check-circle"></i> Status: <?= $student['status'] ?>
    </span>
    <p style="margin: 0.5rem 0 0 0; text-align: right; font-size: 0.82rem; opacity: 0.8;">
      Monthly Tuition Fee: <strong><?= format_currency($student['tuition_fee']) ?></strong>
    </p>
  </div>
</div>

<!-- KPI Summary Stat Grid -->
<div class="stats-grid" style="margin-bottom: 2rem;">
  <div class="kpi-card">
    <div class="kpi-icon green"><i class="fas fa-check-double"></i></div>
    <div class="kpi-data">
      <h3><?= format_currency($totalPaid) ?></h3>
      <p>Total Fees Paid</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon" style="background: #fee2e2; color: #dc2626;"><i class="fas fa-exclamation-circle"></i></div>
    <div class="kpi-data">
      <h3><?= format_currency($totalDue) ?></h3>
      <p>Outstanding / Due Fees</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon gold"><i class="fas fa-award"></i></div>
    <div class="kpi-data">
      <h3><?= $avgPercentage ?>% <small style="font-size: 0.9rem; color: #10b981;">(<?= $overallGrade['grade'] ?>)</small></h3>
      <p>Exam Average Score</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon purple"><i class="fas fa-user-check"></i></div>
    <div class="kpi-data">
      <h3><?= $attRate ?>%</h3>
      <p>Attendance Rate</p>
    </div>
  </div>
</div>

<!-- Detailed Sections -->
<div style="display: flex; flex-direction: column; gap: 2rem; width: 100%; min-width: 0; box-sizing: border-box;">

  <!-- 1. Fee Payments & Advance Statement Card -->
  <div class="card-box" style="min-width: 0; max-width: 100%; overflow: hidden;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
      <h3 class="card-title"><i class="fas fa-file-invoice-dollar" style="color: var(--admin-primary);"></i> Fee Payment Statements & Advance Invoices</h3>
      <button class="btn" style="background: #10b981; color: #ffffff; padding: 0.5rem 1rem; font-size: 0.82rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="openModal('advancePaymentModal')">
        <i class="fas fa-plus"></i> Collect Advance Fee
      </button>
    </div>

    <div class="table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto; display: block; -webkit-overflow-scrolling: touch;">
      <table class="data-table" style="min-width: 720px; width: 100%;">
        <thead>
          <tr>
            <th>Invoice No</th>
            <th>Fee Description</th>
            <th>Amount</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Paid Date & Method</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($fees)): ?>
            <?php foreach ($fees as $f): ?>
              <tr>
                <td><code><?= htmlspecialchars($f['invoice_no']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($f['fee_type']) ?></strong>
                  <?php if (strpos($f['fee_type'], 'Advance') !== false): ?>
                    <span class="badge badge-info" style="font-size: 0.7rem;">Advance</span>
                  <?php endif; ?>
                </td>
                <td><strong style="color: var(--admin-primary);"><?= format_currency($f['amount']) ?></strong></td>
                <td><?= format_date($f['due_date']) ?></td>
                <td>
                  <?php if ($f['status'] == 'Paid'): ?>
                    <span class="badge badge-success">Paid</span>
                  <?php else: ?>
                    <span class="badge badge-danger">Unpaid</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($f['status'] == 'Paid'): ?>
                    <?= format_date($f['paid_date']) ?> (<?= htmlspecialchars($f['payment_method'] ?: 'Cash') ?>)
                  <?php else: ?>
                    <span style="color: #ef4444; font-weight: 700; font-size: 0.82rem;">Pending</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($f['status'] == 'Unpaid'): ?>
                    <a href="student_profile.php?id=<?= $student_pk ?>&mark_paid=<?= $f['id'] ?>" class="btn" style="background: #10b981; color: #ffffff; padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Mark this invoice as Paid?')">
                      <i class="fas fa-check"></i> Collect Payment
                    </a>
                  <?php else: ?>
                    <small style="color: var(--admin-text-muted);"><i class="fas fa-sync-alt" style="color: #10b981;"></i> Synced</small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align: center; color: var(--admin-text-muted);">No fee records found for this student.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 2. Academic Exam Performance Card -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-award" style="color: var(--admin-gold);"></i> Academic Exam Performance & Marks Breakdown</h3>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Exam Term</th>
            <th>Subject / Curriculum Area</th>
            <th>Max Marks</th>
            <th>Marks Obtained</th>
            <th>Letter Grade</th>
            <th>GPA</th>
            <th>Teacher Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($results)): ?>
            <?php foreach ($results as $r): ?>
              <?php $g = calculate_grade($r['marks_obtained'], $r['max_marks']); ?>
              <tr>
                <td><span class="badge badge-info"><?= htmlspecialchars($r['exam_term']) ?></span></td>
                <td><strong><?= htmlspecialchars($r['subject_name']) ?></strong></td>
                <td><?= $r['max_marks'] ?></td>
                <td><strong style="color: var(--admin-primary);"><?= $r['marks_obtained'] ?></strong></td>
                <td><span class="badge <?= $g['class'] ?>"><?= $g['grade'] ?></span></td>
                <td><strong><?= $g['gpa'] ?></strong></td>
                <td><?= htmlspecialchars($r['teacher_remarks']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align: center; color: var(--admin-text-muted);">No exam grades recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 3. Guardian & Personal Information Card -->
  <div class="card-box" style="min-width: 0; max-width: 100%; overflow: hidden;">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-id-card" style="color: var(--admin-primary);"></i> Student &amp; Guardian Detailed Information</h3>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; font-size: 0.92rem;">
      <!-- Column 1: Student Identity & Academic Profile -->
      <div style="background: #f8fafc; padding: 1.25rem; border-radius: 14px; border: 1px solid var(--admin-border);">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin-top: 0; border-bottom: 2px solid var(--admin-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-user-graduate" style="color: var(--admin-primary);"></i> Student Identity &amp; Bio Data
        </h4>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.4rem; margin-top: 0.75rem;">
          <p style="margin: 0.2rem 0;"><strong>Full Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
          <p style="margin: 0.2rem 0;"><strong>Student ID:</strong> <code><?= htmlspecialchars($student['student_id']) ?></code></p>
          <p style="margin: 0.2rem 0;"><strong>Class / Standard:</strong> <span class="badge badge-info"><?= htmlspecialchars($student['class_name']) ?></span> (Roll: <strong>#<?= $student['roll_no'] ?></strong>)</p>
          <p style="margin: 0.2rem 0;"><strong>Admission Session:</strong> <?= htmlspecialchars($student['admission_session'] ?? '2027') ?></p>
          <p style="margin: 0.2rem 0;"><strong>Gender:</strong> <?= $student['gender'] ?></p>
          <p style="margin: 0.2rem 0;"><strong>Date of Birth:</strong> <?= format_date($student['dob']) ?></p>
          <p style="margin: 0.2rem 0;"><strong>Blood Group:</strong> <span class="badge" style="background: #fee2e2; color: #dc2626; font-weight: 800;"><?= htmlspecialchars($student['blood_group'] ?: 'N/A') ?></span></p>
          <p style="margin: 0.2rem 0;"><strong>Birth Reg. / Passport:</strong> <?= htmlspecialchars($student['birth_reg_no'] ?: 'N/A') ?></p>
          <p style="margin: 0.2rem 0;"><strong>Religion &amp; Nationality:</strong> <?= htmlspecialchars($student['religion'] ?? 'Islam') ?> &bull; <?= htmlspecialchars($student['nationality'] ?? 'Bangladeshi') ?></p>
          <p style="margin: 0.2rem 0;"><strong>Admission Date:</strong> <?= format_date($student['admission_date']) ?></p>
          <?php if (!empty($student['previous_school'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Previous School:</strong> <?= htmlspecialchars($student['previous_school']) ?></p>
          <?php endif; ?>
          <?php if (!empty($student['medical_notes'])): ?>
            <p style="margin: 0.2rem 0; color: #b45309;"><strong><i class="fas fa-heartbeat"></i> Medical Notes:</strong> <?= htmlspecialchars($student['medical_notes']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Column 2: Guardian, Contact & Family Details -->
      <div style="background: #f8fafc; padding: 1.25rem; border-radius: 14px; border: 1px solid var(--admin-border);">
        <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin-top: 0; border-bottom: 2px solid var(--admin-border); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-users" style="color: var(--admin-gold-dark);"></i> Parents, Guardian &amp; Address
        </h4>
        <div style="display: grid; grid-template-columns: 1fr; gap: 0.4rem; margin-top: 0.75rem;">
          <p style="margin: 0.2rem 0;"><strong>Father's Name:</strong> <?= htmlspecialchars($student['father_name']) ?></p>
          <p style="margin: 0.2rem 0;"><strong>Father's Mobile (SMS):</strong> <a href="tel:<?= htmlspecialchars($student['phone']) ?>" style="color: var(--admin-primary); font-weight: 700; text-decoration: none;"><?= htmlspecialchars($student['phone']) ?></a></p>
          <?php if (!empty($student['father_occupation'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Father's Occupation:</strong> <?= htmlspecialchars($student['father_occupation']) ?></p>
          <?php endif; ?>
          <?php if (!empty($student['father_nid'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Father's NID / Passport:</strong> <?= htmlspecialchars($student['father_nid']) ?></p>
          <?php endif; ?>
          <p style="margin: 0.2rem 0;"><strong>Mother's Name:</strong> <?= htmlspecialchars($student['mother_name'] ?: 'N/A') ?></p>
          <?php if (!empty($student['mother_phone'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Mother's Mobile:</strong> <?= htmlspecialchars($student['mother_phone']) ?></p>
          <?php endif; ?>
          <?php if (!empty($student['mother_occupation'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Mother's Occupation:</strong> <?= htmlspecialchars($student['mother_occupation']) ?></p>
          <?php endif; ?>
          <p style="margin: 0.2rem 0;"><strong>Present Address:</strong> <?= htmlspecialchars($student['address']) ?></p>
          <?php if (!empty($student['permanent_address'])): ?>
            <p style="margin: 0.2rem 0;"><strong>Permanent Address:</strong> <?= htmlspecialchars($student['permanent_address']) ?></p>
          <?php endif; ?>
          <?php if (!empty($student['emergency_contact_name'])): ?>
            <p style="margin: 0.2rem 0; border-top: 1px dashed var(--admin-border); padding-top: 0.4rem;">
              <strong>Emergency Contact:</strong> <?= htmlspecialchars($student['emergency_contact_name']) ?> (<?= htmlspecialchars($student['emergency_contact_relation'] ?? 'Guardian') ?>) &bull; <?= htmlspecialchars($student['emergency_contact_phone'] ?? 'N/A') ?>
            </p>
          <?php endif; ?>
          <p style="margin: 0.2rem 0; border-top: 1px dashed var(--admin-border); padding-top: 0.4rem;">
            <strong>Parent Portal Login:</strong> 
            <?php if ($student['parent_username']): ?>
              <code><?= htmlspecialchars($student['parent_username']) ?></code> (<?= htmlspecialchars($student['parent_email']) ?>)
            <?php else: ?>
              <span style="color: var(--admin-text-muted);">Default Account Linked</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Modal 1: Issue Advance Payment Modal -->
<div id="advancePaymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: #10b981; margin: 0;">
        <i class="fas fa-calendar-plus"></i> Collect Advance Fee Payment
      </h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('advancePaymentModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: 0.9rem; color: #047857;">
        <strong>Student:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?><br>
        <strong>Class Tuition Fee:</strong> <?= format_currency($student['tuition_fee']) ?> / month
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Starting Month *</label>
          <input type="month" name="start_month" class="form-control" value="<?= date('Y-m') ?>" required>
        </div>
        <div class="form-group">
          <label>Number of Months *</label>
          <select name="num_months" class="form-control" id="advanceNumMonths" onchange="calculateAdvanceTotal()" required>
            <option value="1">1 Month</option>
            <option value="2">2 Months</option>
            <option value="3" selected>3 Months (Quarterly)</option>
            <option value="6">6 Months (Half-Yearly)</option>
            <option value="12">12 Months (Full Year)</option>
          </select>
        </div>
      </div>

      <div style="background: #f8fafc; padding: 1rem; border-radius: 10px; margin: 1rem 0; text-align: center; border: 1px solid #e2e8f0;">
        <span style="font-size: 0.85rem; color: #64748b; font-weight: 700;">TOTAL ADVANCE AMOUNT TO COLLECT:</span>
        <h2 id="advanceCalculatedTotal" style="font-family: 'Outfit', sans-serif; color: #10b981; margin: 0.2rem 0 0 0;">
          <?= format_currency($student['tuition_fee'] * 3) ?>
        </h2>
      </div>

      <div class="form-group">
        <label>Payment Method</label>
        <select name="payment_method" class="form-control">
          <option value="Cash">Cash</option>
          <option value="bKash / Mobile Banking">bKash / Mobile Banking</option>
          <option value="Bank Transfer">Bank Transfer</option>
        </select>
      </div>

      <div class="form-group">
        <label>Receipt / Transaction Ref No</label>
        <input type="text" name="reference_no" class="form-control" placeholder="ADV-REC-101">
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: #047857;">
          <input type="checkbox" name="mark_paid" value="1" checked style="width: 18px; height: 18px;">
          Mark as Paid immediately & Sync to Income Ledger
        </label>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('advancePaymentModal')">Cancel</button>
        <button type="submit" name="process_advance_payment" class="btn" style="background: #10b981; color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Confirm Advance Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Issue Single Custom Invoice Modal -->
<div id="singleInvoiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Issue Custom Invoice</h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('singleInvoiceModal')">&times;</button>
    </div>

    <form method="POST">
      <div class="form-group">
        <label>Fee Description / Title *</label>
        <input type="text" name="fee_type" class="form-control" placeholder="Annual Exam Fee / Sports Fee" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Amount (BDT ৳) *</label>
          <input type="number" name="amount" class="form-control" value="<?= $student['tuition_fee'] ?>" required>
        </div>
        <div class="form-group">
          <label>Due Date *</label>
          <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+10 days')) ?>" required>
        </div>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('singleInvoiceModal')">Cancel</button>
        <button type="submit" name="issue_single_invoice" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Issue Invoice</button>
      </div>
    </form>
  </div>
</div>

<script>
  function calculateAdvanceTotal() {
    var monthlyFee = <?= $student['tuition_fee'] ?>;
    var months = parseInt(document.getElementById('advanceNumMonths').value);
    var total = monthlyFee * months;
    document.getElementById('advanceCalculatedTotal').innerText = '৳ ' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
