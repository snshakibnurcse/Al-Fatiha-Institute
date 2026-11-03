<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();

// Fetch Associated Student
$stStmt = $db->prepare("SELECT s.*, c.class_name, c.tuition_fee FROM students s JOIN classes c ON s.class_id = c.id WHERE s.parent_user_id = ? LIMIT 1");
$stStmt->execute([$user_id]);
$student = $stStmt->fetch();

// If no student mapped directly, fallback to demo student 1
if (!$student) {
    $stStmt2 = $db->query("SELECT s.*, c.class_name, c.tuition_fee FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = 1");
    $student = $stStmt2->fetch();
}

// Stats for child
$student_db_id = $student['id'];
$totalAttDays = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_db_id")->fetchColumn() ?: 1;
$presentDays = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_db_id AND status = 'Present'")->fetchColumn();
$attPercentage = round(($presentDays / $totalAttDays) * 100);

$unpaidFees = $db->query("SELECT SUM(amount) FROM fees WHERE student_id = $student_db_id AND status = 'Unpaid'")->fetchColumn() ?: 0;
?>

<div style="background: linear-gradient(135deg, var(--admin-deep-navy) 0%, var(--admin-primary-dark) 100%); color: #ffffff; padding: 2.2rem; border-radius: 20px; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
  <div style="display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
    <div style="width: 80px; height: 80px; background: var(--admin-gold); color: var(--admin-deep-navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 900;">
      <i class="fas fa-child"></i>
    </div>
    <div style="flex: 1;">
      <span style="background: rgba(243,181,39,0.2); color: var(--admin-gold); padding: 0.2rem 0.8rem; border-radius: 50px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase;">
        ENROLLED STUDENT
      </span>
      <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; color: #ffffff; margin: 0.3rem 0;">
        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
      </h1>
      <p style="color: rgba(255,255,255,0.85); font-size: 0.98rem; margin: 0;">
        Student ID: <code><?= htmlspecialchars($student['student_id']) ?></code> • Class: <strong><?= htmlspecialchars($student['class_name']) ?></strong> • Roll No: <strong>#<?= $student['roll_no'] ?></strong>
      </p>
    </div>
  </div>
</div>

<?php if ($unpaidFees > 0): ?>
  <div class="alert-msg alert-warning" style="display: flex; justify-content: space-between; align-items: center; padding: 1.2rem;">
    <div>
      <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem; margin-right: 0.5rem;"></i>
      <strong>Pending Fee Dues Notice:</strong> You have outstanding fee invoices totaling <strong><?= format_currency($unpaidFees) ?></strong>.
    </div>
    <a href="fees.php" class="btn" style="background: var(--admin-gold); color: var(--admin-deep-navy); padding: 0.4rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 800;">View Invoices</a>
  </div>
<?php endif; ?>

<div class="stats-grid">
  <div class="kpi-card">
    <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
    <div class="kpi-data">
      <h3><?= $attPercentage ?>%</h3>
      <p>Attendance Record</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon gold"><i class="fas fa-award"></i></div>
    <div class="kpi-data">
      <h3>A+</h3>
      <p>Latest Overall Grade</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon purple"><i class="fas fa-money-bill"></i></div>
    <div class="kpi-data">
      <h3><?= format_currency($student['tuition_fee']) ?></h3>
      <p>Monthly Tuition Fee</p>
    </div>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-user"></i> Student Personal Profile</h3>
    </div>
    <table class="data-table">
      <tr><td><strong>Father's Name:</strong></td><td><?= htmlspecialchars($student['father_name']) ?></td></tr>
      <tr><td><strong>Mother's Name:</strong></td><td><?= htmlspecialchars($student['mother_name']) ?></td></tr>
      <tr><td><strong>Contact Phone:</strong></td><td><?= htmlspecialchars($student['phone']) ?></td></tr>
      <tr><td><strong>Date of Birth:</strong></td><td><?= format_date($student['dob']) ?></td></tr>
      <tr><td><strong>Address:</strong></td><td><?= htmlspecialchars($student['address']) ?></td></tr>
    </table>
  </div>

  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Links</h3>
    </div>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <a href="results.php" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.85rem; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700;">
        <i class="fas fa-file-pdf"></i> Download / Print Academic Report Card
      </a>
      <a href="fees.php" class="btn" style="background: #10b981; color: #ffffff; padding: 0.85rem; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700;">
        <i class="fas fa-receipt"></i> View Fee Statements & Receipts
      </a>
      <a href="attendance.php" class="btn" style="background: var(--admin-bg); color: var(--admin-text); padding: 0.85rem; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700;">
        <i class="fas fa-calendar-alt"></i> View Attendance History Calendar
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
