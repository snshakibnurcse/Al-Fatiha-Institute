<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Read flash message from session (PRG Pattern)
$flash = get_flash_msg();
if ($flash) {
    if ($flash['type'] === 'danger') {
        $err = $flash['message'];
    } else {
        $msg = $flash['message'];
    }
}

// Get Student ID from query parameter
$student_pk = intval($_GET['id'] ?? 0);

if (!$student_pk) {
    echo "<div class='alert-msg alert-danger'>Invalid student ID. <a href='students.php'>Back to Directory</a></div>";
    require_once __DIR__ . '/layout_footer.php';
    exit();
}

// --------------------------------------------------------
// 1. Handle Advance Payment Request for this Student
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_advance_payment'])) {
    $start_month = sanitize($_POST['start_month'] ?? date('Y-m'));
    $num_months = intval($_POST['num_months'] ?? 1);
    $mark_paid = isset($_POST['mark_paid']) && $_POST['mark_paid'] == '1';
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $ref_no = sanitize($_POST['reference_no'] ?? '');

    $result = generate_advance_fee_invoice($student_pk, $start_month, $num_months, $mark_paid, $payment_method, $ref_no);
    if ($result['success']) {
        set_flash_msg($result['message'], 'success');
    } else {
        set_flash_msg($result['message'], 'danger');
    }
    redirect("student_profile.php?id=$student_pk");
}

// --------------------------------------------------------
// 2. Handle Multi-Item Invoice Issue (Admission, Books, Diary, etc.)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_multi_invoice'])) {
    $item_titles = $_POST['item_title'] ?? [];
    $item_amounts = $_POST['item_amount'] ?? [];
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d', strtotime('+10 days')));
    $fee_month = sanitize($_POST['fee_month'] ?? date('Y-m'));
    $payment_status = sanitize($_POST['payment_status'] ?? 'Unpaid');
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_date = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $reference_no = sanitize($_POST['reference_no'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    $valid_items = [];
    $total_amount = 0;

    if (is_array($item_titles) && is_array($item_amounts)) {
        for ($i = 0; $i < count($item_titles); $i++) {
            $t = sanitize($item_titles[$i] ?? '');
            $a = floatval($item_amounts[$i] ?? 0);
            if (!empty($t) && $a > 0) {
                $valid_items[] = ['title' => $t, 'amount' => $a];
                $total_amount += $a;
            }
        }
    }

    if (empty($valid_items) || $total_amount <= 0) {
        set_flash_msg("Please add at least one fee item with a valid positive amount.", 'danger');
        redirect("student_profile.php?id=$student_pk");
    }

    try {
        $inv_prefix = 'INV-';
        $itemTitlesList = array_map(function($it) { return $it['title']; }, $valid_items);
        $primaryTitle = implode(' + ', $itemTitlesList);
        if (strlen($primaryTitle) > 95) {
            $primaryTitle = substr($primaryTitle, 0, 92) . '...';
        }

        $inv_no = $inv_prefix . date('Ym') . '-' . rand(100, 999);

        // Build itemized breakdown text for notes
        $breakdownParts = [];
        foreach ($valid_items as $vi) {
            $breakdownParts[] = $vi['title'] . ": ৳" . number_format($vi['amount'], 2);
        }
        $fullBreakdown = "Items: [" . implode(', ', $breakdownParts) . "]";
        if (!empty($notes)) {
            $fullBreakdown .= " | Notes: " . $notes;
        }

        if ($payment_status === 'Paid') {
            $stmt = $db->prepare("
                INSERT INTO fees (invoice_no, student_id, fee_type, amount, due_date, fee_month, status, paid_date, payment_method, reference_no, notes) 
                VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $inv_no, $student_pk, $primaryTitle, $total_amount, $paid_date, $fee_month, $paid_date, $payment_method, $reference_no, $fullBreakdown
            ]);
            $feeId = $db->lastInsertId();
            mark_fee_as_paid($feeId, $payment_method, $reference_no, $paid_date);
            set_flash_msg("Invoice <strong>$inv_no</strong> created and payment of <strong>৳" . number_format($total_amount, 2) . "</strong> received successfully!", 'success');
        } else {
            $stmt = $db->prepare("
                INSERT INTO fees (invoice_no, student_id, fee_type, amount, due_date, fee_month, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?)
            ");
            $stmt->execute([
                $inv_no, $student_pk, $primaryTitle, $total_amount, $due_date, $fee_month, $fullBreakdown
            ]);
            set_flash_msg("Invoice <strong>$inv_no</strong> for <strong>৳" . number_format($total_amount, 2) . "</strong> issued as Unpaid (Due Date: " . format_date($due_date) . ").", 'success');
        }
        redirect("student_profile.php?id=$student_pk");
    } catch (Exception $e) {
        set_flash_msg("Error creating invoice: " . $e->getMessage(), 'danger');
        redirect("student_profile.php?id=$student_pk");
    }
}

// --------------------------------------------------------
// 3. Handle Update / Edit Invoice
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_invoice'])) {
    $fee_id = intval($_POST['fee_id'] ?? 0);
    $res = update_fee_invoice($fee_id, [
        'fee_type' => $_POST['fee_type'] ?? '',
        'amount' => $_POST['amount'] ?? 0,
        'due_date' => $_POST['due_date'] ?? '',
        'fee_month' => $_POST['fee_month'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ]);
    set_flash_msg($res['message'], $res['success'] ? 'success' : 'danger');
    redirect("student_profile.php?id=$student_pk");
}

// --------------------------------------------------------
// 4. Handle Delete Invoice
// --------------------------------------------------------
if (isset($_GET['delete_invoice'])) {
    $delFeeId = intval($_GET['delete_invoice']);
    $res = delete_fee_invoice($delFeeId);
    set_flash_msg($res['message'], $res['success'] ? 'success' : 'danger');
    redirect("student_profile.php?id=$student_pk");
}

// --------------------------------------------------------
// 5. Handle Mark Paid for an invoice
// --------------------------------------------------------
if (isset($_GET['mark_paid'])) {
    $fee_id = intval($_GET['mark_paid']);
    mark_fee_as_paid($fee_id, 'Cash / Admin');
    set_flash_msg("Fee invoice marked as Paid and synced to Income Ledger!", 'success');
    redirect("student_profile.php?id=$student_pk");
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
    <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('multiInvoiceModal')">
      <i class="fas fa-file-invoice"></i> Issue Multi-Item Invoice
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
      <div>
        <h3 class="card-title" style="margin: 0;"><i class="fas fa-file-invoice-dollar" style="color: var(--admin-primary);"></i> Fee Payment Statements &amp; Invoices</h3>
        <small style="color: var(--admin-text-muted);">Manage multi-item invoices, advance collections, and print receipts</small>
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 0.9rem; font-size: 0.82rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="openModal('multiInvoiceModal')">
          <i class="fas fa-file-invoice"></i> Issue Multi-Item Invoice
        </button>
        <button class="btn" style="background: #10b981; color: #ffffff; padding: 0.5rem 0.9rem; font-size: 0.82rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="openModal('advancePaymentModal')">
          <i class="fas fa-calendar-plus"></i> Collect Advance Fee
        </button>
      </div>
    </div>

    <div class="table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto; display: block; -webkit-overflow-scrolling: touch;">
      <table class="data-table" style="min-width: 720px; width: 100%;">
        <thead>
          <tr>
            <th>Invoice No</th>
            <th>Fee Description / Items</th>
            <th>Amount</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Paid Date &amp; Method</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($fees)): ?>
            <?php foreach ($fees as $f): ?>
              <tr>
                <td>
                  <strong style="color: #0284c7; font-family: monospace; font-size: 0.88rem;"><?= htmlspecialchars($f['invoice_no']) ?></strong>
                  <?php if ($f['fee_month']): ?>
                    <br><small style="color: #64748b; font-size: 0.72rem;"><?= date('M Y', strtotime($f['fee_month'] . '-01')) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= htmlspecialchars($f['fee_type']) ?></strong>
                  <?php if (strpos($f['fee_type'], 'Advance') !== false): ?>
                    <span class="badge badge-info" style="font-size: 0.7rem;">Advance</span>
                  <?php endif; ?>
                  <?php if (!empty($f['notes'])): ?>
                    <br><small style="color: #64748b; font-style: italic;"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($f['notes']) ?></small>
                  <?php endif; ?>
                </td>
                <td><strong style="color: var(--admin-primary); font-size: 0.95rem;"><?= format_currency($f['amount']) ?></strong></td>
                <td><?= format_date($f['due_date']) ?></td>
                <td>
                  <?php if ($f['status'] == 'Paid'): ?>
                    <span class="badge badge-success" style="background: #d1fae5; color: #065f46; font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                      <i class="fas fa-check-circle"></i> Paid
                    </span>
                  <?php else: ?>
                    <span class="badge badge-danger" style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                      <i class="fas fa-exclamation-triangle"></i> Unpaid
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($f['status'] == 'Paid'): ?>
                    <div style="font-size: 0.8rem; line-height: 1.35;">
                      <span style="color: #065f46; font-weight: 600;"><i class="fas fa-calendar-check"></i> <?= format_date($f['paid_date']) ?></span>
                      <br><small style="color: #64748b;">via <?= htmlspecialchars($f['payment_method'] ?: 'Cash') ?></small>
                      <?php if (!empty($f['reference_no'])): ?>
                        <br><small style="color: #0284c7; font-family: monospace;">Ref: <?= htmlspecialchars($f['reference_no']) ?></small>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <span style="color: #ef4444; font-weight: 700; font-size: 0.82rem;"><i class="fas fa-clock"></i> Due: <?= format_date($f['due_date']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: flex-end; flex-wrap: nowrap;">
                    <?php if ($f['status'] == 'Unpaid'): ?>
                      <a href="student_profile.php?id=<?= $student_pk ?>&mark_paid=<?= $f['id'] ?>" class="btn" style="background: #10b981; color: #ffffff; padding: 0.3rem 0.6rem; font-size: 0.76rem; border-radius: 6px; text-decoration: none; font-weight: 700; white-space: nowrap;" onclick="return confirm('Confirm payment received for invoice <?= htmlspecialchars($f['invoice_no']) ?>?')" title="Receive Payment">
                        <i class="fas fa-hand-holding-usd"></i> Pay
                      </a>
                      <button type="button" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 700;" onclick="openEditInvoiceModal(<?= htmlspecialchars(json_encode($f)) ?>)" title="Edit Invoice Before Payment">
                        <i class="fas fa-edit"></i> Edit
                      </button>
                      <a href="student_profile.php?id=<?= $student_pk ?>&delete_invoice=<?= $f['id'] ?>" class="btn" style="background: #ef4444; color: #ffffff; padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px; text-decoration: none; font-weight: 700;" onclick="return confirm('Are you sure you want to permanently delete Invoice #<?= htmlspecialchars($f['invoice_no']) ?>?')" title="Delete Invoice">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    <?php else: ?>
                      <button type="button" class="btn" style="background: #f1f5f9; color: #0284c7; border: 1px solid #cbd5e1; padding: 0.3rem 0.55rem; font-size: 0.76rem; border-radius: 6px; cursor: pointer; font-weight: 700;" onclick="openProfileReceiptModal(<?= htmlspecialchars(json_encode($f)) ?>, '<?= htmlspecialchars($f['fee_month'] ? date('F Y', strtotime($f['fee_month'].'-01')) : date('F Y')) ?>')" title="Print Official Money Receipt">
                        <i class="fas fa-receipt"></i> Slip
                      </button>
                      <button type="button" class="btn" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 0.3rem 0.5rem; font-size: 0.76rem; border-radius: 6px; cursor: pointer;" onclick="openEditInvoiceModal(<?= htmlspecialchars(json_encode($f)) ?>)" title="Edit Invoice Details">
                        <i class="fas fa-edit"></i>
                      </button>
                      <a href="student_profile.php?id=<?= $student_pk ?>&delete_invoice=<?= $f['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.3rem 0.5rem; font-size: 0.76rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Warning: This invoice is marked as Paid. Deleting it will also reverse its Income Ledger entry. Are you sure?')" title="Delete Invoice">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    <?php endif; ?>
                  </div>
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

<!-- Modal 2: Issue Multi-Item Fee Invoice Modal (Admission, Books, Diary, etc.) -->
<div id="multiInvoiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; justify-content: center; align-items: center; padding: 1rem; overflow-y: auto;">
  <div style="background: #ffffff; width: 100%; max-width: 680px; border-radius: 18px; padding: 2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.35); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.2rem;">
      <div>
        <span style="font-size: 0.72rem; font-weight: 800; color: #0284c7; text-transform: uppercase; letter-spacing: 0.05em;">
          <i class="fas fa-layer-group"></i> Multi-Item Invoicing Desk
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0.2rem 0 0 0; font-size: 1.4rem;">
          Issue Multi-Item Fee Invoice / Bill
        </h3>
        <small style="color: var(--admin-text-muted);">
          Student: <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong> &bull; Class: <?= htmlspecialchars($student['class_name']) ?> &bull; ID: <code><?= htmlspecialchars($student['student_id']) ?></code>
        </small>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted); line-height: 1;" onclick="closeModal('multiInvoiceModal')">&times;</button>
    </div>

    <!-- Quick Item Presets -->
    <div style="margin-bottom: 1.2rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem;">
      <label style="font-size: 0.8rem; font-weight: 800; color: #334155; display: block; margin-bottom: 0.5rem;">
        <i class="fas fa-magic" style="color: #f59e0b;"></i> 1-Click Fast Add Items:
      </label>
      <div style="display: flex; flex-wrap: wrap; gap: 6px;">
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Admission Fee (ভর্তি ফি)', 5000)">
          🎓 Admission Fee (৳5000)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Khata &amp; Exercise Books (খাতা)', 1500)">
          📚 Khata &amp; Books (৳1500)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Official School Diary (ডায়েরি)', 250)">
          📓 School Diary (৳250)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Monthly Tuition Fee - <?= date('F Y') ?>', <?= $student['tuition_fee'] ?: 2000 ?>)">
          📅 Monthly Tuition (৳<?= $student['tuition_fee'] ?: 2000 ?>)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('School Uniform &amp; Badge Kit', 800)">
          🎒 Uniform Kit (৳800)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Exam Assessment Fee', 500)">
          📝 Exam Fee (৳500)
        </button>
        <button type="button" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.35rem 0.65rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer;" onclick="quickAddPreset('Session Fee &amp; Student ID Card', 500)">
          🏷️ Session &amp; ID Card (৳500)
        </button>
      </div>
    </div>

    <form method="POST">
      <!-- Items Container Table -->
      <div style="margin-bottom: 1rem;">
        <label style="font-weight: 700; color: #1e293b; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">
          <i class="fas fa-list-check" style="color: var(--admin-primary);"></i> Line Items Breakdown *
        </label>
        <div id="multiItemsContainer" style="display: flex; flex-direction: column; gap: 8px;">
          <!-- Item Row 1 (Default) -->
          <div class="multi-item-row" style="display: grid; grid-template-columns: 2fr 1fr 40px; gap: 8px; align-items: center;">
            <input type="text" name="item_title[]" class="form-control" placeholder="Item description (e.g. Admission Fee)" value="Admission Fee" required>
            <input type="number" name="item_amount[]" class="form-control item-amount-input" step="10" min="10" placeholder="Amount (৳)" value="5000" required oninput="recalcMultiTotal()" style="font-weight: 700; color: #065f46;">
            <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; border-radius: 8px; padding: 0.6rem 0.7rem; cursor: pointer;" onclick="removeMultiItemRow(this)" title="Remove item">&times;</button>
          </div>
          <!-- Item Row 2 (Default) -->
          <div class="multi-item-row" style="display: grid; grid-template-columns: 2fr 1fr 40px; gap: 8px; align-items: center;">
            <input type="text" name="item_title[]" class="form-control" placeholder="Item description (e.g. Khata & Books)" value="Khata &amp; Books" required>
            <input type="number" name="item_amount[]" class="form-control item-amount-input" step="10" min="10" placeholder="Amount (৳)" value="1500" required oninput="recalcMultiTotal()" style="font-weight: 700; color: #065f46;">
            <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; border-radius: 8px; padding: 0.6rem 0.7rem; cursor: pointer;" onclick="removeMultiItemRow(this)" title="Remove item">&times;</button>
          </div>
        </div>

        <button type="button" class="btn" style="margin-top: 8px; background: #e0f2fe; color: #0369a1; border: 1px dashed #7dd3fc; border-radius: 8px; padding: 0.45rem 1rem; font-size: 0.82rem; font-weight: 700; cursor: pointer;" onclick="addMultiItemRow()">
          <i class="fas fa-plus"></i> Add Another Item Row
        </button>
      </div>

      <!-- Live Calculated Total Banner -->
      <div style="background: linear-gradient(135deg, #032d4e 0%, #064878 100%); color: #ffffff; border-radius: 12px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
        <div>
          <span style="font-size: 0.78rem; text-transform: uppercase; color: #cbd5e1; font-weight: 700;">TOTAL INVOICE AMOUNT</span>
          <div style="font-size: 0.8rem; color: #94a3b8;">Sum of all items included above</div>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; font-weight: 800; color: #f3b527;">
          ৳ <span id="multiTotalDisplay">6,500.00</span>
        </div>
      </div>

      <!-- Month & Due Date -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; font-size: 0.85rem;">For Month (Ledger)</label>
          <input type="month" name="fee_month" class="form-control" value="<?= date('Y-m') ?>">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; font-size: 0.85rem;">Due Date *</label>
          <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+10 days')) ?>" required>
        </div>
      </div>

      <!-- Notes / Particulars -->
      <div class="form-group" style="margin-bottom: 1rem;">
        <label style="font-weight: 700; font-size: 0.85rem;">Additional Notes / Remarks (Optional)</label>
        <input type="text" name="notes" class="form-control" placeholder="e.g. Paid in full by father at school counter">
      </div>

      <!-- Payment Status Selector -->
      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 1rem; margin-bottom: 1.2rem;">
        <label style="font-weight: 800; color: #1e293b; font-size: 0.88rem; display: block; margin-bottom: 0.5rem;">
          <i class="fas fa-money-check-dollar" style="color: #10b981;"></i> Payment Collection Option:
        </label>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 700; color: #991b1b;">
            <input type="radio" name="payment_status" value="Unpaid" checked onchange="toggleProfilePayBox('Unpaid')" style="accent-color: #ef4444; transform: scale(1.2);">
            <span>🟡 Issue as Unpaid (Due Bill)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 700; color: #065f46;">
            <input type="radio" name="payment_status" value="Paid" onchange="toggleProfilePayBox('Paid')" style="accent-color: #10b981; transform: scale(1.2);">
            <span>🟢 Payment Received Now (Paid)</span>
          </label>
        </div>

        <div id="profilePaidFields" style="display: none; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 0.75rem; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.8rem; border-radius: 8px;">
          <div>
            <label style="font-size: 0.75rem; font-weight: 700; color: #065f46;">Payment Method</label>
            <select name="payment_method" class="form-control" style="padding: 0.45rem; font-size: 0.85rem;">
              <option value="Cash">Cash (নগদ)</option>
              <option value="bKash / Mobile Banking">bKash / Mobile</option>
              <option value="Bank Transfer">Bank Transfer</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700; color: #065f46;">Paid Date</label>
            <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>" style="padding: 0.45rem; font-size: 0.85rem;">
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 700; color: #065f46;">Reference No</label>
            <input type="text" name="reference_no" class="form-control" placeholder="TrxID / MR-101" style="padding: 0.45rem; font-size: 0.85rem;">
          </div>
        </div>
      </div>

      <div style="text-align: right; border-top: 1.5px solid #e2e8f0; padding-top: 1.2rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.65rem 1.4rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer; font-weight: 600;" onclick="closeModal('multiInvoiceModal')">Cancel</button>
        <button type="submit" name="issue_multi_invoice" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.65rem 1.8rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 800;">
          <i class="fas fa-check-circle"></i> Generate Invoice &amp; Save
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Edit Invoice Modal -->
<div id="editInvoiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1060; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.35);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0; font-size: 1.3rem;">
          <i class="fas fa-edit" style="color: #0284c7;"></i> Edit Fee Invoice Details
        </h3>
        <small style="color: var(--admin-text-muted);">Invoice No: <strong id="editInvoiceNoDisplay" style="color: #0284c7; font-family: monospace;">--</strong></small>
      </div>
      <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('editInvoiceModal')">&times;</button>
    </div>

    <form method="POST">
      <input type="hidden" name="action_update_invoice" value="1">
      <input type="hidden" name="fee_id" id="editFeeId" value="">

      <div class="form-group" style="margin-bottom: 1rem;">
        <label style="font-weight: 700; font-size: 0.85rem;">Fee Title / Item Description *</label>
        <input type="text" name="fee_type" id="editFeeType" class="form-control" required style="font-weight: 600;">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; font-size: 0.85rem;">Amount (BDT ৳) *</label>
          <input type="number" name="amount" id="editAmount" step="10" min="10" class="form-control" required style="font-weight: 800; color: #065f46; font-size: 1.1rem;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; font-size: 0.85rem;">Month</label>
          <input type="month" name="fee_month" id="editFeeMonth" class="form-control">
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 1rem;">
        <label style="font-weight: 700; font-size: 0.85rem;">Due Date *</label>
        <input type="date" name="due_date" id="editDueDate" class="form-control" required>
      </div>

      <div class="form-group" style="margin-bottom: 1.2rem;">
        <label style="font-weight: 700; font-size: 0.85rem;">Notes / Remarks (Optional)</label>
        <textarea name="notes" id="editNotes" class="form-control" rows="2" placeholder="Item breakdown, remarks..."></textarea>
      </div>

      <div style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer; font-weight: 600;" onclick="closeModal('editInvoiceModal')">Cancel</button>
        <button type="submit" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.6rem 1.6rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 800;">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Official Money Receipt Slip Modal (Profile View) -->
<div id="profileReceiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1070; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 520px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.35); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.8rem; margin-bottom: 1rem;">
      <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); font-size: 1.25rem;">
        <i class="fas fa-receipt" style="color: #f59e0b;"></i> Official Money Receipt Slip
      </h3>
      <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('profileReceiptModal')">&times;</button>
    </div>

    <div id="profilePrintSlipArea" style="background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 1.5rem;">
      <div style="text-align: center; border-bottom: 2px solid #064878; padding-bottom: 0.8rem; margin-bottom: 1rem;">
        <h3 style="margin: 0; color: #064878; font-family: 'Outfit', sans-serif; font-size: 1.35rem;">AL FATIHA INSTITUTE</h3>
        <p style="margin: 2px 0; font-size: 0.78rem; color: #64748b;">Cambridge Curriculum &bull; Islamic Ethos &bull; Cox's Bazar</p>
        <span style="display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 800; padding: 2px 10px; border-radius: 20px; margin-top: 4px;">
          OFFICIAL MONEY RECEIPT / CASH MEMO
        </span>
      </div>

      <table style="width: 100%; font-size: 0.88rem; line-height: 1.8; border-collapse: collapse;">
        <tr>
          <td style="color: #64748b; width: 40%;">Invoice No:</td>
          <td><strong id="slipInvNo" style="font-family: monospace; color: #0284c7;">--</strong></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Student Name:</td>
          <td><strong id="slipStudent"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Student ID / Roll:</td>
          <td><code><?= htmlspecialchars($student['student_id']) ?></code> &bull; Roll: #<?= $student['roll_no'] ?></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Class / Standard:</td>
          <td><strong><?= htmlspecialchars($student['class_name']) ?></strong></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Description / Items:</td>
          <td><strong id="slipItemTitle">--</strong></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Paid Date &amp; Method:</td>
          <td><span id="slipPaidInfo">--</span></td>
        </tr>
        <tr>
          <td style="color: #64748b;">Reference No:</td>
          <td><span id="slipRef">--</span></td>
        </tr>
        <tr style="border-top: 2px solid #064878; border-bottom: 2px solid #064878;">
          <td style="font-weight: 800; font-size: 1rem; color: #064878; padding: 6px 0;">Total Amount Paid:</td>
          <td style="font-weight: 800; font-size: 1.25rem; color: #10b981; padding: 6px 0;"><span id="slipAmount">--</span></td>
        </tr>
      </table>

      <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 1.5rem; border-top: 1px dashed #cbd5e1; font-size: 0.75rem; color: #64748b;">
        <div style="text-align: center;">
          <div style="width: 120px; border-bottom: 1px solid #94a3b8; margin-bottom: 4px;"></div>
          <span>Student / Guardian</span>
        </div>
        <div style="text-align: center;">
          <div style="width: 120px; border-bottom: 1px solid #94a3b8; margin-bottom: 4px;"></div>
          <span>Authorized Cashier</span>
        </div>
      </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 1.2rem;">
      <button type="button" class="btn" style="background: #e2e8f0; color: #334155; padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer;" onclick="closeModal('profileReceiptModal')">Close</button>
      <button type="button" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.5rem 1.4rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;" onclick="printProfileSlip()">
        <i class="fas fa-print"></i> Print Receipt
      </button>
    </div>
  </div>
</div>

<script>
  function calculateAdvanceTotal() {
    var monthlyFee = <?= floatval($student['tuition_fee'] ?: 2000) ?>;
    var months = parseInt(document.getElementById('advanceNumMonths').value);
    var total = monthlyFee * months;
    document.getElementById('advanceCalculatedTotal').innerText = '৳ ' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
  }

  function toggleProfilePayBox(val) {
    var box = document.getElementById('profilePaidFields');
    if (box) {
      box.style.display = (val === 'Paid') ? 'grid' : 'none';
    }
  }

  function addMultiItemRow(title = '', amount = '') {
    var container = document.getElementById('multiItemsContainer');
    var row = document.createElement('div');
    row.className = 'multi-item-row';
    row.style = 'display: grid; grid-template-columns: 2fr 1fr 40px; gap: 8px; align-items: center;';
    row.innerHTML = `
      <input type="text" name="item_title[]" class="form-control" placeholder="Item description" value="${title}" required>
      <input type="number" name="item_amount[]" class="form-control item-amount-input" step="10" min="10" placeholder="Amount (৳)" value="${amount}" required oninput="recalcMultiTotal()" style="font-weight: 700; color: #065f46;">
      <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; border-radius: 8px; padding: 0.6rem 0.7rem; cursor: pointer;" onclick="removeMultiItemRow(this)" title="Remove item">&times;</button>
    `;
    container.appendChild(row);
    recalcMultiTotal();
  }

  function removeMultiItemRow(btn) {
    var rows = document.querySelectorAll('.multi-item-row');
    if (rows.length > 1) {
      btn.closest('.multi-item-row').remove();
      recalcMultiTotal();
    } else {
      alert('Invoice must have at least one item.');
    }
  }

  function quickAddPreset(title, amount) {
    addMultiItemRow(title, amount);
  }

  function recalcMultiTotal() {
    var inputs = document.querySelectorAll('.item-amount-input');
    var sum = 0;
    inputs.forEach(function(inp) {
      var val = parseFloat(inp.value) || 0;
      sum += val;
    });
    var display = document.getElementById('multiTotalDisplay');
    if (display) {
      display.innerText = sum.toLocaleString('en-US', {minimumFractionDigits: 2});
    }
  }

  function openEditInvoiceModal(fee) {
    document.getElementById('editFeeId').value = fee.id;
    document.getElementById('editInvoiceNoDisplay').innerText = fee.invoice_no;
    document.getElementById('editFeeType').value = fee.fee_type;
    document.getElementById('editAmount').value = fee.amount;
    document.getElementById('editFeeMonth').value = fee.fee_month || '';
    document.getElementById('editDueDate').value = fee.due_date || '';
    document.getElementById('editNotes').value = fee.notes || '';
    openModal('editInvoiceModal');
  }

  function openProfileReceiptModal(f, monthName) {
    document.getElementById('slipInvNo').innerText = f.invoice_no;
    document.getElementById('slipItemTitle').innerText = f.fee_type;
    document.getElementById('slipAmount').innerText = '৳ ' + parseFloat(f.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('slipPaidInfo').innerText = (f.paid_date || 'Paid') + ' (' + (f.payment_method || 'Cash') + ')';
    document.getElementById('slipRef').innerText = f.reference_no || 'N/A';
    openModal('profileReceiptModal');
  }

  function printProfileSlip() {
    var printContent = document.getElementById('profilePrintSlipArea').innerHTML;
    var origContent = document.body.innerHTML;
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = origContent;
    window.location.reload();
  }

  // Recalculate on load
  document.addEventListener('DOMContentLoaded', function() {
    recalcMultiTotal();
  });
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
