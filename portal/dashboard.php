<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();

// Fetch Associated Student
$stStmt = $db->prepare("SELECT s.*, c.class_name, c.tuition_fee FROM students s JOIN classes c ON s.class_id = c.id WHERE s.parent_user_id = ? LIMIT 1");
$stStmt->execute([$user_id]);
$student = $stStmt->fetch();

if (!$student) {
    $stStmt2 = $db->query("SELECT s.*, c.class_name, c.tuition_fee FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = 1");
    $student = $stStmt2->fetch();
}

$student_db_id = $student['id'];
$totalAttDays = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_db_id")->fetchColumn() ?: 1;
$presentDays = $db->query("SELECT COUNT(*) FROM attendance WHERE student_id = $student_db_id AND status = 'Present'")->fetchColumn();
$attPercentage = round(($presentDays / $totalAttDays) * 100);

$unpaidFees = $db->query("SELECT SUM(amount) FROM fees WHERE student_id = $student_db_id AND status = 'Unpaid'")->fetchColumn() ?: 0;
?>

<!-- Student Overview Header -->
<div class="dash-welcome-card">
  <div style="display: flex; gap: 16px; align-items: center;">
    <div style="width: 52px; height: 52px; background: var(--admin-gold); color: var(--admin-navy-deep); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; flex-shrink: 0;">
      <?= strtoupper(substr($student['first_name'] ?: 'S', 0, 1)) ?>
    </div>
    <div>
      <div class="welcome-meta-row">
        <span class="date-badge"><?= htmlspecialchars($student['student_id']) ?></span>
        <span class="role-pill-tag"><?= htmlspecialchars($student['class_name']) ?> · Roll #<?= $student['roll_no'] ?></span>
      </div>
      <h1 class="welcome-title"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
      <p class="welcome-sub">Enrolled Student · AL FATIHA INSTITUTE</p>
    </div>
  </div>
  <div class="welcome-actions">
    <a href="results.php" class="btn-welcome-primary">Report Card</a>
    <a href="timetable.php" class="btn-welcome-secondary">Class Timetable</a>
  </div>
</div>

<?php if ($unpaidFees > 0): ?>
  <div class="alert-msg" style="background: #fbf4e3; color: #9a6704; border: 1px solid rgba(154, 103, 4, 0.25); justify-content: space-between; flex-wrap: wrap;">
    <div>
      <strong>Pending Fee Dues:</strong> Outstanding invoices totaling <strong style="font-family: 'IBM Plex Mono', monospace;"><?= format_currency($unpaidFees) ?></strong>.
    </div>
    <a href="fees.php" class="btn-welcome-primary" style="height: 32px; padding: 0 12px; font-size: 12.5px;">View Invoices</a>
  </div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= $attPercentage ?>%</h3>
      <p>Attendance record</p>
    </div>
    <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3>A+</h3>
      <p>Overall grade</p>
    </div>
    <div class="kpi-icon gold"><i class="fas fa-award"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <h3><?= format_currency($student['tuition_fee']) ?></h3>
      <p>Monthly tuition fee</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-money-bill"></i></div>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px;">
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title">Student Personal Profile</h3>
    </div>
    <table class="data-table">
      <tbody>
        <tr><td style="color: var(--admin-text-muted); width: 160px;">Father's Name</td><td><strong><?= htmlspecialchars($student['father_name']) ?></strong></td></tr>
        <tr><td style="color: var(--admin-text-muted);">Mother's Name</td><td><strong><?= htmlspecialchars($student['mother_name']) ?></strong></td></tr>
        <tr><td style="color: var(--admin-text-muted);">Contact Phone</td><td style="font-family: 'IBM Plex Mono', monospace;"><?= htmlspecialchars($student['phone']) ?></td></tr>
        <tr><td style="color: var(--admin-text-muted);">Date of Birth</td><td style="font-family: 'IBM Plex Mono', monospace;"><?= format_date($student['dob']) ?></td></tr>
        <tr><td style="color: var(--admin-text-muted);">Address</td><td><?= htmlspecialchars($student['address']) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title">Quick Access</h3>
    </div>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <a href="results.php" class="btn-welcome-primary" style="justify-content: center;">
        Academic Report Card
      </a>
      <a href="fees.php" class="btn-welcome-secondary" style="justify-content: center;">
        Fee Statements &amp; Receipts
      </a>
      <a href="attendance.php" class="btn-welcome-secondary" style="justify-content: center;">
        Attendance History Log
      </a>
      <a href="timetable.php" class="btn-welcome-secondary" style="justify-content: center;">
        Weekly Class Routine
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
