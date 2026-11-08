<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';
$autoOpenReceipt = null;

// ========================================================
// 1. POST HANDLER: ISSUE CUSTOM INVOICE (Khata, Books, Diary, Fees, etc.)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_issue_custom_invoice'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $item_category = sanitize($_POST['item_category'] ?? 'General');
    $fee_type = sanitize($_POST['fee_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $fee_month = sanitize($_POST['fee_month'] ?? date('Y-m'));
    $payment_status = sanitize($_POST['payment_status'] ?? 'Paid'); // 'Paid' or 'Unpaid'
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_date = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
    $reference_no = sanitize($_POST['reference_no'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($fee_type)) {
        $fee_type = $item_category;
    }

    if ($student_id > 0 && $amount > 0) {
        try {
            // Intelligent invoice numbering prefix
            $inv_prefix = 'INV-';
            if (stripos($fee_type, 'khata') !== false || stripos($fee_type, 'book') !== false) {
                $inv_prefix = 'INV-BK-';
            } elseif (stripos($fee_type, 'diary') !== false) {
                $inv_prefix = 'INV-DRY-';
            } elseif (stripos($fee_type, 'uniform') !== false) {
                $inv_prefix = 'INV-UNI-';
            } elseif (stripos($fee_type, 'exam') !== false) {
                $inv_prefix = 'INV-EXM-';
            }

            $inv_no = $inv_prefix . date('Ym') . '-' . rand(100, 999);

            if ($payment_status === 'Paid') {
                $stmt = $db->prepare("
                    INSERT INTO fees 
                    (invoice_no, student_id, fee_type, fee_month, amount, due_date, status, paid_date, payment_method, reference_no, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $inv_no, $student_id, $fee_type, $fee_month, $amount, $paid_date, $paid_date, $payment_method, $reference_no, $notes
                ]);
                $newFeeId = $db->lastInsertId();

                // Auto-sync into accounts income ledger
                mark_fee_as_paid($newFeeId, $payment_method, $reference_no, $paid_date);

                // Fetch details for automatic receipt slip modal popup
                $stStmt = $db->prepare("SELECT s.*, s.student_id as student_code, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
                $stStmt->execute([$student_id]);
                $stInfo = $stStmt->fetch(PDO::FETCH_ASSOC);
                if ($stInfo) {
                    $stInfo['invoice_no'] = $inv_no;
                    $stInfo['fee_type'] = $fee_type;
                    $stInfo['effective_amount'] = $amount;
                    $stInfo['amount'] = $amount;
                    $stInfo['paid_date'] = $paid_date;
                    $stInfo['payment_method'] = $payment_method;
                    $stInfo['reference_no'] = $reference_no;
                    $stInfo['notes'] = $notes;
                    $stInfo['fee_month'] = $fee_month;
                    $autoOpenReceipt = $stInfo;
                }

                $msg = "Invoice <strong>$inv_no</strong> issued and payment of <strong>৳" . number_format($amount, 2) . "</strong> received successfully! Official receipt slip is ready.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO fees 
                    (invoice_no, student_id, fee_type, fee_month, amount, due_date, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?)
                ");
                $stmt->execute([
                    $inv_no, $student_id, $fee_type, $fee_month, $amount, $due_date, $notes
                ]);
                $msg = "Invoice <strong>$inv_no</strong> issued as Due for <strong>৳" . number_format($amount, 2) . "</strong> (Due Date: " . format_date($due_date) . ").";
            }
        } catch (Exception $e) {
            $err = "Error issuing invoice: " . $e->getMessage();
        }
    } else {
        $err = "Please select a student and enter a valid positive amount.";
    }
}

// ========================================================
// 2. POST HANDLER: COLLECT PAYMENT ON UNPAID/PARTIAL INVOICE
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_collect_unpaid_invoice'])) {
    $fee_id = intval($_POST['fee_id'] ?? 0);
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_date = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $reference_no = sanitize($_POST['reference_no'] ?? '');

    if ($fee_id > 0 && $payment_amount > 0) {
        $res = record_fee_payment($fee_id, $payment_amount, $payment_method, $reference_no, $paid_date);
        if ($res['success']) {
            $autoOpenReceipt = $res['receipt'];
            $msg = $res['message'];
        } else {
            $err = $res['message'];
        }
    } else {
        $err = "Invalid invoice or payment amount specified.";
    }
}

// ========================================================
// 3. POST HANDLER: QUICK COLLECT MONTHLY TUITION FEE
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_collect_monthly_fee'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $fee_month = sanitize($_POST['fee_month'] ?? date('Y-m'));
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $paid_date = sanitize($_POST['paid_date'] ?? date('Y-m-d'));
    $reference_no = sanitize($_POST['reference_no'] ?? '');

    if ($student_id > 0 && $amount > 0) {
        try {
            $chk = $db->prepare("SELECT id, invoice_no, status FROM fees WHERE student_id = ? AND (fee_month = ? OR due_date LIKE ?) LIMIT 1");
            $chk->execute([$student_id, $fee_month, $fee_month . '%']);
            $existingFee = $chk->fetch();

            $monthTimestamp = strtotime($fee_month . '-01');
            $monthName = date('F Y', $monthTimestamp);

            if ($existingFee && $existingFee['status'] === 'Paid') {
                $err = "Access Denied: Payment has already been received and locked for <strong>$monthName</strong> (Invoice: " . htmlspecialchars($existingFee['invoice_no']) . "). Received payments cannot be modified or re-collected by Accountant without Principal Admin authorization.";
            } elseif ($existingFee) {
                $fee_id = $existingFee['id'];
                $db->prepare("UPDATE fees SET status = 'Paid', amount = ?, paid_date = ?, payment_method = ?, fee_month = ?, reference_no = ? WHERE id = ?")
                   ->execute([$amount, $paid_date, $payment_method, $fee_month, $reference_no, $fee_id]);
                mark_fee_as_paid($fee_id, $payment_method, $reference_no, $paid_date);
                $msg = "Fee for <strong>$monthName</strong> collected successfully! (Invoice: " . htmlspecialchars($existingFee['invoice_no']) . ")";
            } else {
                $inv_no = 'INV-' . date('Ym', $monthTimestamp) . '-' . rand(100, 999);
                $fee_title = "Monthly Tuition Fee - " . $monthName;
                $ins = $db->prepare("INSERT INTO fees (invoice_no, student_id, fee_type, fee_month, amount, due_date, status, paid_date, payment_method, reference_no) VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?, ?, ?)");
                $ins->execute([$inv_no, $student_id, $fee_title, $fee_month, $amount, $paid_date, $paid_date, $payment_method, $reference_no]);
                $newFeeId = $db->lastInsertId();
                mark_fee_as_paid($newFeeId, $payment_method, $reference_no, $paid_date);
                $msg = "Payment of <strong>৳" . number_format($amount, 2) . "</strong> recorded for $monthName! Invoice: <strong>$inv_no</strong>";
            }
        } catch (Exception $e) {
            $err = "Error recording payment: " . $e->getMessage();
        }
    } else {
        $err = "Invalid student or fee amount.";
    }
}

// RESTRICTION: Accountant CANNOT cancel or modify received payments!
if (isset($_GET['mark_unpaid'])) {
    $err = "Access Denied: Received payments cannot be cancelled or modified by Accountant. Principal Admin authorization is required.";
}

// Handle Advance Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_advance_payment'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $start_month = sanitize($_POST['start_month'] ?? date('Y-m'));
    $num_months = intval($_POST['num_months'] ?? 1);
    $mark_paid = isset($_POST['mark_paid']) && $_POST['mark_paid'] == '1';
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $ref_no = sanitize($_POST['reference_no'] ?? '');

    $result = generate_advance_fee_invoice($student_id, $start_month, $num_months, $mark_paid, $payment_method, $ref_no);
    if ($result['success']) {
        $msg = $result['message'];
    } else {
        $err = $result['message'];
    }
}

// ----------------------------------------------------
// VIEW & FILTER PARAMETERS
// ----------------------------------------------------
$activeView = sanitize($_GET['view'] ?? 'tracker'); // 'tracker' or 'invoices'

$selected_month = sanitize($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_status = sanitize($_GET['status'] ?? 'all');
$search_query = sanitize($_GET['q'] ?? '');

// Invoices Ledger specific filters
$invFilterStatus = sanitize($_GET['inv_status'] ?? 'all');
$invFilterCategory = sanitize($_GET['inv_cat'] ?? 'all');
$invSearch = sanitize($_GET['inv_q'] ?? '');

$currentMonthTime = strtotime($selected_month . '-01');
$selectedMonthName = date('F Y', $currentMonthTime);
$prevMonth = date('Y-m', strtotime('-1 month', $currentMonthTime));
$nextMonth = date('Y-m', strtotime('+1 month', $currentMonthTime));

// ----------------------------------------------------
// DATA FETCHING
// ----------------------------------------------------
$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT 
        s.id, 
        s.student_id as student_code, 
        s.first_name, 
        s.last_name, 
        s.roll_no, 
        s.gender, 
        s.father_name, 
        s.mother_name, 
        s.phone, 
        s.class_id,
        c.class_name, 
        c.tuition_fee as class_tuition_fee,
        f.id as fee_id,
        f.invoice_no,
        f.fee_type,
        f.fee_month,
        f.amount as billed_amount,
        f.status as fee_status,
        f.paid_date,
        f.payment_method,
        f.reference_no,
        f.notes
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN fees f ON (
        f.student_id = s.id 
        AND (
            f.fee_month = :sel_month 
            OR (f.due_date LIKE :sel_month_like AND (f.fee_type LIKE '%Tuition%' OR f.fee_type LIKE '%Monthly%'))
        )
    )
    WHERE s.status = 'Active'
";

$params = [
    ':sel_month' => $selected_month,
    ':sel_month_like' => $selected_month . '%'
];

if ($selected_class_id > 0) {
    $sql .= " AND s.class_id = :class_id";
    $params[':class_id'] = $selected_class_id;
}

if (!empty($search_query)) {
    $sql .= " AND (s.first_name LIKE :sq OR s.last_name LIKE :sq OR s.student_id LIKE :sq OR s.roll_no LIKE :sq OR s.phone LIKE :sq)";
    $params[':sq'] = "%$search_query%";
}

$sql .= " ORDER BY c.id ASC, s.roll_no ASC, s.first_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rawStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process statuses & metrics
$totalStudents = count($rawStudents);
$paidCount = 0;
$dueCount = 0;
$totalExpectedAmount = 0;
$totalCollectedAmount = 0;
$totalDueAmount = 0;

$displayStudents = [];
$classSummaries = [];

foreach ($classes as $cls) {
    $classSummaries[$cls['id']] = [
        'name' => $cls['class_name'],
        'total' => 0,
        'paid' => 0,
        'due' => 0,
        'expected' => 0,
        'collected' => 0,
        'due_amount' => 0
    ];
}

foreach ($rawStudents as $st) {
    $effectiveAmount = !empty($st['billed_amount']) && floatval($st['billed_amount']) > 0 
        ? floatval($st['billed_amount']) 
        : (floatval($st['class_tuition_fee']) > 0 ? floatval($st['class_tuition_fee']) : 2000.00);

    $isPaid = (!empty($st['fee_status']) && strtolower($st['fee_status']) === 'paid');
    $status = $isPaid ? 'Paid' : 'Due';

    $st['computed_status'] = $status;
    $st['effective_amount'] = $effectiveAmount;

    $cid = $st['class_id'];
    if (isset($classSummaries[$cid])) {
        $classSummaries[$cid]['total']++;
        $classSummaries[$cid]['expected'] += $effectiveAmount;
        if ($isPaid) {
            $classSummaries[$cid]['paid']++;
            $classSummaries[$cid]['collected'] += $effectiveAmount;
        } else {
            $classSummaries[$cid]['due']++;
            $classSummaries[$cid]['due_amount'] += $effectiveAmount;
        }
    }

    $totalExpectedAmount += $effectiveAmount;
    if ($isPaid) {
        $paidCount++;
        $totalCollectedAmount += $effectiveAmount;
    } else {
        $dueCount++;
        $totalDueAmount += $effectiveAmount;
    }

    if ($selected_status === 'all' || strtolower($selected_status) === strtolower($status)) {
        $displayStudents[] = $st;
    }
}

$collectionRate = $totalStudents > 0 ? round(($paidCount / $totalStudents) * 100, 1) : 0;

foreach ($classSummaries as $cid => &$cs) {
    $cs['rate'] = $cs['total'] > 0 ? round(($cs['paid'] / $cs['total']) * 100, 1) : 0;
}

// All active students for invoice issuance
$allActiveStudents = $db->query("
    SELECT s.id, s.student_id as student_code, s.first_name, s.last_name, s.roll_no, c.class_name, c.tuition_fee 
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    WHERE s.status = 'Active' 
    ORDER BY c.id ASC, s.roll_no ASC, s.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Invoices Ledger (for Invoices View)
$invWhere = ["1=1"];
$invParams = [];
if ($invFilterStatus !== 'all') {
    $invWhere[] = "f.status = :inv_status";
    $invParams[':inv_status'] = $invFilterStatus;
}
if ($invFilterCategory !== 'all') {
    if ($invFilterCategory === 'khata_book') {
        $invWhere[] = "(f.fee_type LIKE '%Khata%' OR f.fee_type LIKE '%Book%' OR f.fee_type LIKE '%Exercise%' OR f.fee_type LIKE '%Textbook%')";
    } elseif ($invFilterCategory === 'diary') {
        $invWhere[] = "(f.fee_type LIKE '%Diary%')";
    } elseif ($invFilterCategory === 'uniform') {
        $invWhere[] = "(f.fee_type LIKE '%Uniform%' OR f.fee_type LIKE '%Kit%' OR f.fee_type LIKE '%Dress%')";
    } elseif ($invFilterCategory === 'tuition') {
        $invWhere[] = "(f.fee_type LIKE '%Tuition%' OR f.fee_type LIKE '%Monthly%')";
    }
}
if (!empty($invSearch)) {
    $invWhere[] = "(f.invoice_no LIKE :isq OR f.fee_type LIKE :isq OR s.first_name LIKE :isq OR s.last_name LIKE :isq OR s.student_id LIKE :isq OR s.phone LIKE :isq)";
    $invParams[':isq'] = "%$invSearch%";
}

$invSql = "
    SELECT f.*, s.student_id as student_code, s.first_name, s.last_name, s.roll_no, s.phone, s.gender, c.class_name 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    JOIN classes c ON s.class_id = c.id 
    WHERE " . implode(' AND ', $invWhere) . " 
    ORDER BY f.id DESC LIMIT 150
";
$invStmt = $db->prepare($invSql);
$invStmt->execute($invParams);
$allInvoicesList = $invStmt->fetchAll(PDO::FETCH_ASSOC);

// Totals for Invoices Ledger view
$invTotalAmount = 0;
$invPaidAmount = 0;
$invDueAmount = 0;
foreach ($allInvoicesList as &$iv) {
    $totalAmt = floatval($iv['amount']);
    $paidAmt = floatval($iv['paid_amount'] ?? 0);
    if ($iv['status'] === 'Paid' && $paidAmt <= 0) {
        $paidAmt = $totalAmt;
    }
    $dueAmt = max(0, $totalAmt - $paidAmt);
    $iv['calculated_paid'] = $paidAmt;
    $iv['calculated_due'] = $dueAmt;

    $invTotalAmount += $totalAmt;
    $invPaidAmount += $paidAmt;
    $invDueAmount += $dueAmt;
}
unset($iv);
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
     HEADER BANNER & QUICK ACTIONS
     ======================================================== -->
<div class="fee-manager-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 1.8rem;">
  <div>
    <div style="display: inline-flex; align-items: center; gap: 8px; background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;">
      <i class="fas fa-user-tie text-primary"></i>
      <span>ACCOUNTANT DESK &bull; FEE INVOICING &amp; COLLECTION</span>
      <span style="background: #0284c7; color: #ffffff; padding: 1px 7px; border-radius: 10px; font-size: 0.68rem;"><i class="fas fa-lock"></i> AUDIT LOCKED</span>
    </div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0; font-size: 1.85rem; font-weight: 800;">
      Fees Collection, Invoicing &amp; Due Tracker
    </h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.95rem;">
      Issue invoices for monthly fees, Khata, Books, Diaries &amp; extra items. Collect payments and print instant money receipts.
    </p>
  </div>

  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <!-- ISSUE INVOICE BUTTON (PRIMARY FEATURE) -->
    <button class="btn btn-primary" style="background: #2563eb; color: #ffffff; padding: 0.65rem 1.3rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);" onclick="openModal('issueInvoiceModal')">
      <i class="fas fa-file-invoice"></i> Issue Invoice (Khata/Book/Fees)
    </button>
    <button class="btn btn-emerald" style="background: #10b981; color: #fff; padding: 0.65rem 1.2rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="openModal('advanceFeeModal')">
      <i class="fas fa-calendar-plus"></i> Collect Advance Fee
    </button>
    <button class="btn btn-outline" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #1e293b; padding: 0.65rem 1.1rem; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="window.print()">
      <i class="fas fa-print"></i> Print Report
    </button>
  </div>
</div>

<!-- ========================================================
     VIEW SWITCHER TABS
     ======================================================== -->
<div style="display: flex; gap: 10px; margin-bottom: 1.5rem; background: #ffffff; padding: 6px; border-radius: 14px; border: 1.5px solid #e2e8f0; width: fit-content;">
  <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>" 
     style="padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; <?= ($activeView === 'tracker') ? 'background: #0284c7; color: #ffffff; box-shadow: 0 2px 8px rgba(2,132,199,0.3);' : 'color: #64748b; background: transparent;' ?>">
    <i class="fas fa-calendar-alt"></i> 1-Click Monthly Tuition Tracker (Class &amp; Month)
  </a>
  <a href="fees.php?view=invoices&month=<?= $selected_month ?>" 
     style="padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; <?= ($activeView === 'invoices') ? 'background: #0284c7; color: #ffffff; box-shadow: 0 2px 8px rgba(2,132,199,0.3);' : 'color: #64748b; background: transparent;' ?>">
    <i class="fas fa-file-invoice-dollar"></i> All Issued Invoices &amp; Item Sales Ledger (Khata, Books, Diary)
    <span style="background: <?= ($activeView === 'invoices') ? 'rgba(255,255,255,0.25)' : '#e2e8f0' ?>; color: <?= ($activeView === 'invoices') ? '#ffffff' : '#334155' ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.76rem; font-weight: 800;">
      <?= count($allInvoicesList) ?>
    </span>
  </a>
</div>

<?php if ($activeView === 'tracker'): ?>
  <!-- ========================================================
       TRACKER VIEW: KPI STATS CARDS (LIVE METRICS)
       ======================================================== -->
  <div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 1.8rem;">
    <!-- 1. Total Students -->
    <div class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; display: flex; align-items: center; gap: 14px;">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <span style="font-size: 0.78rem; font-weight: 700; color: var(--admin-text-muted); text-transform: uppercase;">Active Students</span>
        <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: var(--admin-deep-navy); font-weight: 800;"><?= $totalStudents ?></h3>
        <small style="color: #64748b; font-size: 0.72rem;"><?= $selected_class_id ? htmlspecialchars($classSummaries[$selected_class_id]['name']) : 'All 5 Classes' ?></small>
      </div>
    </div>

    <!-- 2. Paid Students -->
    <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=paid" class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $selected_status === 'paid' ? '#10b981' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: #d1fae5; color: #059669; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
        <i class="fas fa-check-circle"></i>
      </div>
      <div>
        <span style="font-size: 0.78rem; font-weight: 700; color: #059669; text-transform: uppercase;">Paid Students</span>
        <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #065f46; font-weight: 800;"><?= $paidCount ?></h3>
        <small style="color: #059669; font-weight: 700; font-size: 0.75rem;"><?= $collectionRate ?>% Completed</small>
      </div>
    </a>

    <!-- 3. Due Students -->
    <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=due" class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid <?= $selected_status === 'due' ? '#ef4444' : '#e2e8f0' ?>; text-decoration: none; display: flex; align-items: center; gap: 14px;">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: #fee2e2; color: #dc2626; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <div>
        <span style="font-size: 0.78rem; font-weight: 700; color: #dc2626; text-transform: uppercase;">Due / Unpaid</span>
        <h3 style="margin: 2px 0 0 0; font-size: 1.55rem; color: #991b1b; font-weight: 800;"><?= $dueCount ?></h3>
        <small style="color: #b91c1c; font-weight: 700; font-size: 0.75rem;"><?= $totalStudents ? round(($dueCount / $totalStudents) * 100, 1) : 0 ?>% Pending</small>
      </div>
    </a>

    <!-- 4. Collected Revenue -->
    <div class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; display: flex; align-items: center; gap: 14px;">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; color: #047857; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
        <i class="fas fa-coins"></i>
      </div>
      <div>
        <span style="font-size: 0.78rem; font-weight: 700; color: #047857; text-transform: uppercase;">Collected (<?= date('M', $currentMonthTime) ?>)</span>
        <h3 style="margin: 2px 0 0 0; font-size: 1.45rem; color: #065f46; font-weight: 800;"><?= format_currency($totalCollectedAmount) ?></h3>
        <small style="color: #64748b; font-size: 0.72rem;">Target: <?= format_currency($totalExpectedAmount) ?></small>
      </div>
    </div>

    <!-- 5. Total Due Amount -->
    <div class="kpi-box" style="background: #ffffff; border-radius: 14px; padding: 1.1rem 1.3rem; border: 1.5px solid #e2e8f0; display: flex; align-items: center; gap: 14px;">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: #fff1f2; color: #e11d48; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0;">
        <i class="fas fa-hand-holding-usd"></i>
      </div>
      <div>
        <span style="font-size: 0.78rem; font-weight: 700; color: #e11d48; text-transform: uppercase;">Total Due Balance</span>
        <h3 style="margin: 2px 0 0 0; font-size: 1.45rem; color: #be123c; font-weight: 800;"><?= format_currency($totalDueAmount) ?></h3>
        <small style="color: #e11d48; font-size: 0.72rem; font-weight: 700;"><?= $dueCount ?> Students Unpaid</small>
      </div>
    </div>
  </div>

  <!-- ========================================================
       CLASS-WISE SNAPSHOT CARDS
       ======================================================== -->
  <?php if ($selected_class_id === 0): ?>
    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.3rem 1.6rem; margin-bottom: 1.8rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
          <h4 style="margin: 0; color: var(--admin-deep-navy); font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
            <i class="fas fa-layer-group text-primary"></i> Class-wise Collection Snapshot &bull; <?= htmlspecialchars($selectedMonthName) ?>
          </h4>
          <small style="color: var(--admin-text-muted);">Click on any class card to filter its students directly.</small>
        </div>
        <span class="badge badge-info" style="font-size: 0.8rem; padding: 6px 12px;">5 Classes Active</span>
      </div>

      <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">
        <?php foreach ($classes as $cls): 
          $cs = $classSummaries[$cls['id']] ?? ['total' => 0, 'paid' => 0, 'due' => 0, 'rate' => 0];
        ?>
          <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $cls['id'] ?>&status=<?= $selected_status ?>" style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 12px 14px; text-decoration: none; color: inherit; display: flex; flex-direction: column; transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <strong style="font-size: 0.92rem; color: var(--admin-deep-navy);"><?= htmlspecialchars($cls['class_name']) ?></strong>
              <span style="font-size: 0.74rem; font-weight: 800; color: #059669;"><?= $cs['rate'] ?>%</span>
            </div>
            <div style="height: 5px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-bottom: 8px;">
              <div style="height: 100%; background: #10b981; width: <?= min(100, $cs['rate']) ?>%;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 6px;">
              <span style="color: #059669;"><i class="fas fa-check-circle"></i> <strong><?= $cs['paid'] ?></strong> Paid</span>
              <span style="color: #dc2626;"><i class="fas fa-exclamation-circle"></i> <strong><?= $cs['due'] ?></strong> Due</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 6px; border-top: 1px dashed #e2e8f0; font-size: 0.74rem; color: #64748b;">
              <span>Total: <?= $cs['total'] ?></span>
              <span style="color: #0369a1; font-weight: 700;">Filter &rarr;</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ========================================================
       TRACKER FILTER CONTROLS
       ======================================================== -->
  <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.6rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <form method="GET" action="fees.php" style="display: grid; grid-template-columns: 2fr 1.5fr 1.8fr 1.8fr auto; gap: 14px; align-items: flex-end;">
      <input type="hidden" name="view" value="tracker">

      <!-- 1. Academic Month Filter -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-calendar-alt text-primary"></i> Academic Month
        </label>
        <div style="display: flex; align-items: center; gap: 6px;">
          <a href="fees.php?view=tracker&month=<?= $prevMonth ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>&q=<?= urlencode($search_query) ?>" class="btn-step" style="width: 34px; height: 38px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; display: grid; place-items: center; color: #1e293b; text-decoration: none;" title="Previous Month">
            <i class="fas fa-chevron-left"></i>
          </a>
          <input type="month" name="month" class="form-control" value="<?= $selected_month ?>" onchange="this.form.submit()" style="font-weight: 700; color: #0284c7;">
          <a href="fees.php?view=tracker&month=<?= $nextMonth ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>&q=<?= urlencode($search_query) ?>" class="btn-step" style="width: 34px; height: 38px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; display: grid; place-items: center; color: #1e293b; text-decoration: none;" title="Next Month">
            <i class="fas fa-chevron-right"></i>
          </a>
        </div>
        <div style="display: flex; gap: 6px; margin-top: 6px;">
          <a href="fees.php?view=tracker&month=<?= date('Y-m') ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>" style="font-size: 0.72rem; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 12px; text-decoration: none; font-weight: 600; <?= ($selected_month === date('Y-m')) ? 'background: #0284c7; color: #fff;' : '' ?>">
            This Month (<?= date('M') ?>)
          </a>
          <a href="fees.php?view=tracker&month=<?= date('Y-m', strtotime('-1 month')) ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>" style="font-size: 0.72rem; color: #0369a1; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 12px; text-decoration: none; font-weight: 600; <?= ($selected_month === date('Y-m', strtotime('-1 month'))) ? 'background: #0284c7; color: #fff;' : '' ?>">
            Last Month (<?= date('M', strtotime('-1 month')) ?>)
          </a>
        </div>
      </div>

      <!-- 2. Class Filter -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-school text-primary"></i> Class / Grade
        </label>
        <select name="class_id" class="form-control" onchange="this.form.submit()" style="font-weight: 600;">
          <option value="0">All Classes (Whole School)</option>
          <?php foreach ($classes as $cls): ?>
            <option value="<?= $cls['id'] ?>" <?= ($selected_class_id == $cls['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cls['class_name']) ?> (৳<?= number_format($cls['tuition_fee']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small style="color: var(--admin-text-muted); font-size: 0.72rem; margin-top: 4px; display: block;">Select a specific class to narrow list</small>
      </div>

      <!-- 3. Status Filter (All / Paid / Due) -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-filter text-primary"></i> Payment Status Filter
        </label>
        <div style="display: flex; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 10px; border: 1px solid #cbd5e1;">
          <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=all&q=<?= urlencode($search_query) ?>" style="flex: 1; text-align: center; padding: 6px 4px; font-size: 0.78rem; font-weight: 700; border-radius: 7px; text-decoration: none; <?= ($selected_status === 'all') ? 'background: #ffffff; color: var(--admin-deep-navy); box-shadow: 0 2px 4px rgba(0,0,0,0.06);' : 'color: #64748b;' ?>">
            All (<?= $totalStudents ?>)
          </a>
          <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=paid&q=<?= urlencode($search_query) ?>" style="flex: 1; text-align: center; padding: 6px 4px; font-size: 0.78rem; font-weight: 700; border-radius: 7px; text-decoration: none; <?= ($selected_status === 'paid') ? 'background: #10b981; color: #ffffff;' : 'color: #059669;' ?>">
            Paid (<?= $paidCount ?>)
          </a>
          <a href="fees.php?view=tracker&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=due&q=<?= urlencode($search_query) ?>" style="flex: 1; text-align: center; padding: 6px 4px; font-size: 0.78rem; font-weight: 700; border-radius: 7px; text-decoration: none; <?= ($selected_status === 'due') ? 'background: #ef4444; color: #ffffff;' : 'color: #dc2626;' ?>">
            Due (<?= $dueCount ?>)
          </a>
        </div>
        <small style="color: var(--admin-text-muted); font-size: 0.72rem; margin-top: 4px; display: block;">1-Click isolate Paid or Due students</small>
      </div>

      <!-- 4. Search Filter -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-search text-primary"></i> Search Student
        </label>
        <div style="position: relative;">
          <input type="text" name="q" class="form-control" placeholder="Search name, code, roll..." value="<?= htmlspecialchars($search_query) ?>" style="padding-left: 2rem;">
          <i class="fas fa-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8; font-size: 0.85rem;"></i>
        </div>
        <small style="color: var(--admin-text-muted); font-size: 0.72rem; margin-top: 4px; display: block;">Instant student name / ID lookup</small>
      </div>

      <!-- 5. Submit / Reset -->
      <div style="display: flex; gap: 6px;">
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.1rem; border-radius: 8px; font-weight: 700;">
          Filter
        </button>
        <a href="fees.php?view=tracker" class="btn btn-secondary" style="padding: 0.65rem 0.9rem; border-radius: 8px;" title="Reset Filters">
          <i class="fas fa-redo"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- ========================================================
       TRACKER STUDENTS TABLE
       ======================================================== -->
  <div class="card-box" style="background: #ffffff; border-radius: 16px; border: 1.5px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
    <div class="card-header" style="padding: 1.2rem 1.6rem; border-bottom: 1.5px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <div>
        <h3 class="card-title" style="margin: 0; font-size: 1.15rem; color: var(--admin-deep-navy); font-weight: 800;">
          <i class="fas fa-list-alt text-primary"></i> Student Fee Collection List &bull; <?= htmlspecialchars($selectedMonthName) ?>
        </h3>
        <small style="color: var(--admin-text-muted);">
          Showing <?= ($selected_status !== 'all') ? ucfirst($selected_status) . ' only' : 'All' ?> students for <?= $selected_class_id ? htmlspecialchars($classSummaries[$selected_class_id]['name']) : 'all classes' ?>
        </small>
      </div>

      <div style="font-size: 0.85rem; color: var(--admin-text-muted);">
        Displaying <strong><?= count($displayStudents) ?></strong> of <strong><?= $totalStudents ?></strong> students
      </div>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 70px;">Roll</th>
            <th>Student Information</th>
            <th>Class &amp; Level</th>
            <th>Parent &amp; Phone</th>
            <th>Monthly Fee</th>
            <th>Status (<?= date('M Y', $currentMonthTime) ?>)</th>
            <th>Payment Details</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($displayStudents)): ?>
            <?php foreach ($displayStudents as $st): ?>
              <tr class="<?= ($st['computed_status'] === 'Due') ? 'row-due-highlight' : '' ?>">
                <td><span class="roll-badge"><?= $st['roll_no'] ?: '-' ?></span></td>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="st-avatar <?= (strtolower($st['gender']) === 'female') ? 'female' : 'male' ?>">
                      <i class="fas <?= (strtolower($st['gender']) === 'female') ? 'fa-female' : 'fa-male' ?>"></i>
                    </div>
                    <div>
                      <strong style="color: var(--admin-deep-navy); font-size: 0.94rem;"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong>
                      <br><small style="color: var(--admin-text-muted); font-size: 0.74rem;"><code><?= htmlspecialchars($st['student_code']) ?></code></small>
                    </div>
                  </div>
                </td>
                <td><span class="badge badge-info" style="font-weight: 700;"><?= htmlspecialchars($st['class_name']) ?></span></td>
                <td>
                  <div style="font-size: 0.85rem;">
                    <strong><?= htmlspecialchars($st['father_name'] ?: ($st['mother_name'] ?: 'N/A')) ?></strong>
                    <br>
                    <a href="tel:<?= htmlspecialchars($st['phone']) ?>" style="color: var(--admin-primary); text-decoration: none; font-size: 0.82rem;">
                      <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($st['phone']) ?>
                    </a>
                  </div>
                </td>
                <td><strong style="color: var(--admin-primary); font-size: 0.95rem;"><?= format_currency($st['effective_amount']) ?></strong></td>
                <td>
                  <?php if ($st['computed_status'] === 'Paid'): ?>
                    <span class="badge badge-success" style="background: #d1fae5; color: #065f46; font-size: 0.82rem; padding: 4px 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                      <i class="fas fa-check-circle"></i> Paid
                    </span>
                  <?php else: ?>
                    <span class="badge badge-danger" style="background: #fee2e2; color: #991b1b; font-size: 0.82rem; padding: 4px 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                      <i class="fas fa-exclamation-triangle"></i> Due / Unpaid
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($st['computed_status'] === 'Paid'): ?>
                    <div style="font-size: 0.82rem; line-height: 1.35;">
                      <span style="color: #065f46; font-weight: 700;">
                        <i class="fas fa-calendar-check"></i> <?= format_date($st['paid_date']) ?>
                      </span>
                      <br>
                      <small style="color: var(--admin-text-muted);">
                        via <?= htmlspecialchars($st['payment_method'] ?: 'Cash') ?> &bull; <code><?= htmlspecialchars($st['invoice_no'] ?: '') ?></code>
                      </small>
                    </div>
                  <?php else: ?>
                    <span style="color: #b91c1c; font-size: 0.82rem; font-weight: 600;">
                      <i class="fas fa-hourglass-half"></i> Payment Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px; align-items: center;">
                    <?php if ($st['computed_status'] === 'Due'): ?>
                      <button type="button" class="btn btn-quick-collect" onclick="openCollectModal(<?= htmlspecialchars(json_encode($st)) ?>, '<?= $selected_month ?>', '<?= addslashes($selectedMonthName) ?>')">
                        <i class="fas fa-bolt"></i> Collect Fee
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-receipt-view" onclick="openReceiptModal(<?= htmlspecialchars(json_encode($st)) ?>, '<?= addslashes($selectedMonthName) ?>')">
                        <i class="fas fa-receipt"></i> Slip
                      </button>
                      <!-- STRICT LOCK FOR ACCOUNTANT -->
                      <span class="badge" title="Payment confirmed and locked. Only Principal Admin can modify or cancel." style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; font-size: 0.72rem; padding: 3px 6px;">
                        <i class="fas fa-lock"></i> Locked
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 3rem 1rem;">
                <div style="color: var(--admin-text-muted);">
                  <i class="fas fa-inbox" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.8rem; display: block;"></i>
                  <strong>No students found for the current filter criteria.</strong>
                  <p style="font-size: 0.85rem; margin: 4px 0 1rem 0;">Try resetting the class or status filter, or picking another academic month.</p>
                  <a href="fees.php?view=tracker" class="btn btn-secondary btn-sm">Reset All Filters</a>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- ========================================================
       INVOICES & ITEM SALES LEDGER VIEW (Khata, Books, Diary & All Fees)
       ======================================================== -->
  <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.6rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <form method="GET" action="fees.php" style="display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 14px; align-items: flex-end;">
      <input type="hidden" name="view" value="invoices">

      <!-- 1. Search Query -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-search text-primary"></i> Search Invoices / Sales
        </label>
        <div style="position: relative;">
          <input type="text" name="inv_q" class="form-control" placeholder="Search Invoice #, Student Name, Item..." value="<?= htmlspecialchars($invSearch) ?>" style="padding-left: 2rem;">
          <i class="fas fa-search" style="position: absolute; left: 10px; top: 12px; color: #94a3b8; font-size: 0.85rem;"></i>
        </div>
      </div>

      <!-- 2. Category Filter -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-tags text-primary"></i> Item / Fee Category
        </label>
        <select name="inv_cat" class="form-control" onchange="this.form.submit()">
          <option value="all" <?= ($invFilterCategory === 'all') ? 'selected' : '' ?>>All Item Categories</option>
          <option value="khata_book" <?= ($invFilterCategory === 'khata_book') ? 'selected' : '' ?>>📚 Exercise Books &amp; Textbooks</option>
          <option value="diary" <?= ($invFilterCategory === 'diary') ? 'selected' : '' ?>>📓 School Diary</option>
          <option value="uniform" <?= ($invFilterCategory === 'uniform') ? 'selected' : '' ?>>🎒 Uniform &amp; Accessories</option>
          <option value="tuition" <?= ($invFilterCategory === 'tuition') ? 'selected' : '' ?>>📅 Monthly Tuition Fees</option>
        </select>
      </div>

      <!-- 3. Status Filter -->
      <div>
        <label style="font-size: 0.82rem; font-weight: 700; color: var(--admin-deep-navy); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-filter text-primary"></i> Payment Status
        </label>
        <select name="inv_status" class="form-control" onchange="this.form.submit()">
          <option value="all" <?= ($invFilterStatus === 'all') ? 'selected' : '' ?>>All Statuses</option>
          <option value="Paid" <?= ($invFilterStatus === 'Paid') ? 'selected' : '' ?>>Paid</option>
          <option value="Unpaid" <?= ($invFilterStatus === 'Unpaid') ? 'selected' : '' ?>>Unpaid / Due</option>
        </select>
      </div>

      <!-- 4. Submit & Reset -->
      <div style="display: flex; gap: 6px;">
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.1rem; border-radius: 8px; font-weight: 700;">
          Filter
        </button>
        <a href="fees.php?view=invoices" class="btn btn-secondary" style="padding: 0.65rem 0.9rem; border-radius: 8px;" title="Reset Filters">
          <i class="fas fa-redo"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- INVOICES LEDGER TABLE -->
  <div class="card-box" style="background: #ffffff; border-radius: 16px; border: 1.5px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
    <div class="card-header" style="padding: 1.2rem 1.6rem; border-bottom: 1.5px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
      <div>
        <h3 class="card-title" style="margin: 0; font-size: 1.15rem; color: var(--admin-deep-navy); font-weight: 800;">
          <i class="fas fa-receipt text-primary"></i> All School Invoices, Book Sales &amp; Item Ledgers
        </h3>
        <small style="color: var(--admin-text-muted);">
          Records of Books, Khata, School Diary, Uniforms, Kits, and Fees issued to students.
        </small>
      </div>

      <div style="display: flex; gap: 14px; font-size: 0.85rem; align-items: center;">
        <span style="color: #059669; font-weight: 800;">Total Paid: <?= format_currency($invPaidAmount) ?></span>
        <span style="color: #dc2626; font-weight: 800;">Total Due: <?= format_currency($invDueAmount) ?></span>
        <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 10px; font-weight: 700; color: #475569;"><?= count($allInvoicesList) ?> Invoices</span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 130px;">Invoice No</th>
            <th>Student</th>
            <th>Class &amp; Roll</th>
            <th>Item / Fee Description</th>
            <th>Amount (৳)</th>
            <th>Status</th>
            <th>Date / Method</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($allInvoicesList)): ?>
            <?php foreach ($allInvoicesList as $iv): ?>
              <tr>
                <td>
                  <strong style="color: #0284c7; font-family: monospace; font-size: 0.88rem;"><?= htmlspecialchars($iv['invoice_no']) ?></strong>
                  <?php if ($iv['fee_month']): ?>
                    <br><small style="color: #64748b; font-size: 0.72rem;"><?= date('M Y', strtotime($iv['fee_month'] . '-01')) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= htmlspecialchars($iv['first_name'] . ' ' . $iv['last_name']) ?></strong>
                  <br><small style="color: #64748b; font-size: 0.75rem;"><code><?= htmlspecialchars($iv['student_code']) ?></code></small>
                </td>
                <td>
                  <span class="badge badge-info"><?= htmlspecialchars($iv['class_name']) ?></span>
                  <br><small style="color: #64748b;">Roll: <?= $iv['roll_no'] ?: '-' ?></small>
                </td>
                <td>
                  <strong style="color: var(--admin-deep-navy); font-size: 0.9rem;">
                    <?php 
                      if (stripos($iv['fee_type'], 'diary') !== false) {
                        echo '<i class="fas fa-book-open" style="color: #8b5cf6;"></i> ';
                      } elseif (stripos($iv['fee_type'], 'khata') !== false || stripos($iv['fee_type'], 'book') !== false) {
                        echo '<i class="fas fa-book" style="color: #0ea5e9;"></i> ';
                      } elseif (stripos($iv['fee_type'], 'uniform') !== false) {
                        echo '<i class="fas fa-tshirt" style="color: #f59e0b;"></i> ';
                      } else {
                        echo '<i class="fas fa-file-invoice" style="color: #10b981;"></i> ';
                      }
                      echo htmlspecialchars($iv['fee_type']);
                    ?>
                  </strong>
                  <?php if (!empty($iv['notes'])): ?>
                    <br><small style="color: #64748b; font-style: italic;"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($iv['notes']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color: #0284c7; font-size: 0.95rem;"><?= format_currency($iv['amount']) ?></strong>
                  <?php if (strtolower($iv['status']) === 'partial'): ?>
                    <br><small style="color: #059669; font-weight: 700;">Paid: <?= format_currency($iv['calculated_paid']) ?></small>
                    <br><small style="color: #dc2626; font-weight: 700;">Due: <?= format_currency($iv['calculated_due']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (strtolower($iv['status']) === 'paid'): ?>
                    <span class="badge badge-success" style="background: #d1fae5; color: #065f46; font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                      <i class="fas fa-check-circle"></i> Paid
                    </span>
                  <?php elseif (strtolower($iv['status']) === 'partial'): ?>
                    <span class="badge" style="background: #fef3c7; color: #b45309; font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                      <i class="fas fa-adjust"></i> Partial
                    </span>
                  <?php else: ?>
                    <span class="badge badge-danger" style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; padding: 3px 8px; font-weight: 700;">
                      <i class="fas fa-exclamation-triangle"></i> Due
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (strtolower($iv['status']) === 'paid' || strtolower($iv['status']) === 'partial'): ?>
                    <div style="font-size: 0.8rem; line-height: 1.35;">
                      <span style="color: #065f46; font-weight: 600;"><i class="fas fa-calendar-check"></i> <?= format_date($iv['paid_date']) ?></span>
                      <br><small style="color: #64748b;">via <?= htmlspecialchars($iv['payment_method'] ?: 'Cash') ?></small>
                      <?php if ($iv['reference_no']): ?>
                        <br><small style="color: #0284c7; font-family: monospace;">Ref: <?= htmlspecialchars($iv['reference_no']) ?></small>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="font-size: 0.8rem; color: #b91c1c;">
                      <i class="fas fa-clock"></i> Due: <?= format_date($iv['due_date']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px; align-items: center;">
                    <?php if (strtolower($iv['status']) === 'unpaid' || strtolower($iv['status']) === 'partial'): ?>
                      <button type="button" class="btn btn-quick-collect" onclick="openCollectUnpaidModal(<?= htmlspecialchars(json_encode($iv)) ?>)">
                        <i class="fas fa-hand-holding-usd"></i> Receive Pay
                      </button>
                      <button type="button" class="btn btn-receipt-view" onclick="openReceiptModal(<?= htmlspecialchars(json_encode($iv)) ?>, '<?= htmlspecialchars($iv['fee_month'] ? date('F Y', strtotime($iv['fee_month'].'-01')) : date('F Y')) ?>')">
                        <i class="fas fa-receipt"></i> Slip
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-receipt-view" onclick="openReceiptModal(<?= htmlspecialchars(json_encode($iv)) ?>, '<?= htmlspecialchars($iv['fee_month'] ? date('F Y', strtotime($iv['fee_month'].'-01')) : date('F Y')) ?>')">
                        <i class="fas fa-receipt"></i> Slip
                      </button>
                      <span class="badge" title="Payment locked for audit integrity" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; font-size: 0.72rem; padding: 3px 6px;">
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
                <i class="fas fa-inbox" style="font-size: 2.2rem; opacity: 0.3; margin-bottom: 0.6rem; display: block;"></i>
                No invoices found for this search or category filter.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================
     MODAL 1: ISSUE CUSTOM INVOICE (Khata, Books, Diary, Fees)
     ======================================================== -->
<div id="issueInvoiceModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 680px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #2563eb; letter-spacing: 0.08em; text-transform: uppercase;">
          <i class="fas fa-file-invoice"></i> INVOICE ISSUANCE &amp; SALES DESK
        </span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.4rem;">
          Issue Fee Invoice / School Item Sale
        </h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: #64748b;">
          Issue invoice for Extra Khata, Books, Diary, Uniform, Kits, or Monthly Fees.
        </p>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('issueInvoiceModal')">&times;</button>
    </div>

    <form method="POST" action="fees.php?view=<?= $activeView ?>&month=<?= $selected_month ?>">
      <input type="hidden" name="action_issue_custom_invoice" value="1">
      <input type="hidden" name="item_category" id="issueItemCategory" value="Khata / Books">

      <!-- 1. Select Student -->
      <div class="form-group" style="margin-top: 1.2rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">
          <i class="fas fa-user-graduate text-primary"></i> Select Student *
        </label>
        <select name="student_id" id="issueStudentSelect" class="form-control" required onchange="handleStudentSelect(this)" style="font-size: 0.92rem; padding: 0.65rem;">
          <option value="">-- Choose Student (Type to search) --</option>
          <?php foreach ($allActiveStudents as $st): ?>
            <option value="<?= $st['id'] ?>" 
                    data-name="<?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?>" 
                    data-class="<?= htmlspecialchars($st['class_name']) ?>" 
                    data-roll="<?= $st['roll_no'] ?>" 
                    data-code="<?= htmlspecialchars($st['student_code']) ?>" 
                    data-tuition="<?= $st['tuition_fee'] ?>">
              <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> &bull; <?= htmlspecialchars($st['class_name']) ?> &bull; Roll: <?= $st['roll_no'] ?: '-' ?> &bull; (<?= htmlspecialchars($st['student_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 2. Fast Item Category Presets -->
      <div style="margin-top: 1rem;">
        <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem; display: block; margin-bottom: 6px;">
          <i class="fas fa-tag text-primary"></i> Select Item / Fee Type Preset:
        </label>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
          <button type="button" class="preset-pill active" onclick="setPreset('Khata / Books', 'Exercise Book / Khata', 150, this)">
            📚 Exercise Book
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('School Diary', 'Official School Diary 2026', 150, this)">
            📓 School Diary
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('Textbooks', 'Curriculum Books &amp; Syllabus Kit', 500, this)">
            📖 Textbooks &amp; Syllabus
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('Uniform', 'School Uniform &amp; Accessories Kit', 400, this)">
            🎒 Uniform &amp; Kit
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('Tuition Fee', 'Monthly Tuition Fee - <?= date('F Y') ?>', 'tuition', this)">
            📅 Monthly Tuition Fee
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('Exam Fee', 'Term Assessment &amp; Examination Fee', 300, this)">
            📝 Exam Fee
          </button>
          <button type="button" class="preset-pill" onclick="setPreset('Other', 'Custom School Item / Service', 100, this)">
            🏷️ Other Item / Custom
          </button>
        </div>
      </div>

      <!-- 3. Title & Amount -->
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Item Description / Invoice Title *</label>
          <input type="text" name="fee_type" id="issueFeeType" class="form-control" value="Exercise Book / Khata" placeholder="e.g. 5 Pcs Khata, School Diary, Book set..." required style="font-weight: 600;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Amount (BDT ৳) *</label>
          <input type="number" name="amount" id="issueAmount" step="10" min="10" class="form-control" value="150" required style="font-weight: 800; font-size: 1.1rem; color: #065f46;">
        </div>
      </div>

      <!-- 4. Month & Notes -->
      <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Month (For Ledger)</label>
          <input type="month" name="fee_month" class="form-control" value="<?= $selected_month ?>" style="font-weight: 600;">
        </div>
        <div class="form-group">
          <label style="font-weight: 700; color: var(--admin-deep-navy); font-size: 0.85rem;">Particulars / Notes (Optional)</label>
          <input type="text" name="notes" class="form-control" placeholder="e.g. 5 Pcs ruled khata, 1 pc diary, guardian received">
        </div>
      </div>

      <!-- 5. Payment Received or Due Selector -->
      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 14px; padding: 1.1rem; margin-top: 1.2rem;">
        <label style="font-weight: 800; color: var(--admin-deep-navy); font-size: 0.9rem; display: block; margin-bottom: 8px;">
          <i class="fas fa-money-check-alt text-primary"></i> Payment Collection Status *
        </label>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
          <label style="display: flex; align-items: center; gap: 8px; background: #ffffff; border: 1.5px solid #10b981; border-radius: 10px; padding: 10px 14px; cursor: pointer;">
            <input type="radio" name="payment_status" value="Paid" checked onchange="togglePaymentBox('Paid')" style="accent-color: #10b981; transform: scale(1.2);">
            <div>
              <strong style="color: #065f46; font-size: 0.92rem; display: block;">🟢 Receive Payment Now (Paid)</strong>
              <small style="color: #64748b; font-size: 0.74rem;">Money received now, print instant slip</small>
            </div>
          </label>

          <label style="display: flex; align-items: center; gap: 8px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 10px 14px; cursor: pointer;">
            <input type="radio" name="payment_status" value="Unpaid" onchange="togglePaymentBox('Unpaid')" style="accent-color: #ef4444; transform: scale(1.2);">
            <div>
              <strong style="color: #991b1b; font-size: 0.92rem; display: block;">🟡 Issue as Due (Unpaid)</strong>
              <small style="color: #64748b; font-size: 0.74rem;">Bill will remain due for future collection</small>
            </div>
          </label>
        </div>

        <!-- Paid Details Box -->
        <div id="boxPaidDetails" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 12px;">
          <div>
            <label style="font-size: 0.78rem; font-weight: 700; color: #065f46;">Payment Method</label>
            <select name="payment_method" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;">
              <option value="Cash">Cash</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Cheque">Cheque</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.78rem; font-weight: 700; color: #065f46;">Payment Date</label>
            <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>" style="padding: 0.5rem; font-size: 0.85rem;">
          </div>
          <div>
            <label style="font-size: 0.78rem; font-weight: 700; color: #065f46;">Receipt / Trx Ref</label>
            <input type="text" name="reference_no" class="form-control" placeholder="e.g. TR-902, bKash ID" style="padding: 0.5rem; font-size: 0.85rem;">
          </div>
        </div>

        <!-- Unpaid Due Date Box -->
        <div id="boxUnpaidDetails" style="display: none; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px; padding: 12px;">
          <label style="font-size: 0.78rem; font-weight: 700; color: #9f1239;">Due Date for Payment</label>
          <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" style="padding: 0.5rem; font-size: 0.85rem;">
          <small style="color: #9f1239; font-size: 0.74rem;">Student/guardian must clear this bill on or before this date.</small>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('issueInvoiceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #2563eb; color: #ffffff; font-weight: 800; padding: 0.7rem 1.6rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Issue Invoice &amp; Save
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 2: QUICK COLLECT MONTHLY TUITION MODAL
     ======================================================== -->
<div id="quickCollectModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #10b981; letter-spacing: 0.08em;">FEE COLLECTION</span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">Collect Monthly Tuition Fee</h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('quickCollectModal')">&times;</button>
    </div>

    <form method="POST" action="fees.php?view=<?= $activeView ?>&month=<?= $selected_month ?>&class_id=<?= $selected_class_id ?>&status=<?= $selected_status ?>">
      <input type="hidden" name="action_collect_monthly_fee" value="1">
      <input type="hidden" name="student_id" id="collectStudentId">
      <input type="hidden" name="fee_month" id="collectFeeMonth" value="<?= $selected_month ?>">

      <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; margin-top: 1rem;">
        <div style="width: 42px; height: 42px; background: #e0f2fe; color: #0284c7; border-radius: 10px; display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0;"><i class="fas fa-user-graduate"></i></div>
        <div>
          <strong id="collectStudentName" style="color: var(--admin-deep-navy); font-size: 1rem;">--</strong>
          <small style="color: var(--admin-text-muted); font-size: 0.78rem; display: block;"><span id="collectStudentCode">--</span> &bull; Class: <span id="collectClassName">--</span> &bull; Roll: <span id="collectRollNo">--</span></small>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1.2rem;">
        <label>Fee Month</label>
        <input type="text" id="collectMonthDisplay" class="form-control" readonly style="background: #f1f5f9; font-weight: 700; color: var(--admin-deep-navy);">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label>Fee Amount (BDT ৳) * <small style="color: #64748b; font-weight: normal;">(Locked by Principal)</small></label>
          <input type="number" name="amount" id="collectAmount" step="50" class="form-control" readonly required style="background: #f8fafc; font-weight: 800; font-size: 1.1rem; color: #065f46; cursor: not-allowed;" title="Tuition fee amount is set by Principal Admin">
        </div>

        <div class="form-group">
          <label>Payment Method *</label>
          <select name="payment_method" class="form-control" required>
            <option value="Cash">Cash</option>
            <option value="bKash">bKash</option>
            <option value="Nagad">Nagad</option>
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="Cheque">Cheque</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label>Collection Date *</label>
          <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
          <label>Deposit Slip / Trx ID (Optional)</label>
          <input type="text" name="reference_no" class="form-control" placeholder="e.g. bKash TrxID or MR-908">
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('quickCollectModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #10b981; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Confirm &amp; Collect Fee
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 3: COLLECT UNPAID CUSTOM INVOICE MODAL
     ======================================================== -->
<div id="collectUnpaidModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 520px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #0284c7; letter-spacing: 0.08em;">RECEIVE PAYMENT</span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">
          Collect Bill Payment (Full / Partial)
        </h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('collectUnpaidModal')">&times;</button>
    </div>

    <form method="POST" action="fees.php?view=<?= $activeView ?>&month=<?= $selected_month ?>">
      <input type="hidden" name="action_collect_unpaid_invoice" value="1">
      <input type="hidden" name="fee_id" id="unpaidFeeId">

      <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-top: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.8rem; color: #64748b;">Invoice No:</span>
          <strong id="unpaidInvNo" style="font-family: monospace; color: #0284c7;">--</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.8rem; color: #64748b;">Student Name:</span>
          <strong id="unpaidStudentName">--</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.8rem; color: #64748b;">Item / Fee:</span>
          <strong id="unpaidFeeType" style="color: var(--admin-deep-navy);">--</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-bottom: 6px;">
          <span style="font-size: 0.82rem; color: #64748b;">Total Bill:</span>
          <strong id="unpaidTotalBill" style="color: #1e293b;">৳ 0.00</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <span style="font-size: 0.82rem; color: #64748b;">Already Paid:</span>
          <strong id="unpaidAlreadyPaid" style="color: #059669;">৳ 0.00</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #cbd5e1; padding-top: 6px;">
          <span style="font-size: 0.88rem; font-weight: 800; color: #334155;">Current Due:</span>
          <strong id="unpaidAmount" style="font-size: 1.25rem; color: #dc2626;">৳ 0.00</strong>
        </div>
      </div>

      <!-- Payment Option Selection: Full vs Partial -->
      <div style="margin-top: 1rem; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 10px;">
        <label style="font-size: 0.82rem; font-weight: 800; color: var(--admin-deep-navy); display: block; margin-bottom: 6px;">
          Payment Type *
        </label>
        <div style="display: flex; gap: 15px;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: #065f46;">
            <input type="radio" name="payment_type_toggle" id="toggleFullPay" value="full" checked onchange="handlePaymentTypeChange()" style="accent-color: #10b981; transform: scale(1.15);">
            <span>Full Payment</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: #b45309;">
            <input type="radio" name="payment_type_toggle" id="togglePartialPay" value="partial" onchange="handlePaymentTypeChange()" style="accent-color: #f59e0b; transform: scale(1.15);">
            <span>Partial Payment</span>
          </label>
        </div>
      </div>

      <!-- Amount to Pay Now -->
      <div class="form-group" style="margin-top: 1rem;">
        <label style="font-weight: 800; color: var(--admin-deep-navy); font-size: 0.88rem;">
          Amount to Pay Now *
        </label>
        <div style="position: relative;">
          <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 800; color: #0284c7; font-size: 1.1rem;">৳</span>
          <input type="number" step="0.01" min="1" name="payment_amount" id="unpaidPayAmount" class="form-control" required style="padding-left: 32px; font-weight: 800; font-size: 1.15rem; color: #065f46;" oninput="updateRemainingHelp()">
        </div>
        <small id="unpaidRemainingHelp" style="color: #64748b; font-size: 0.78rem; margin-top: 4px; display: block;">
          Remaining due balance after payment: <strong id="unpaidRemainingDisplay">৳ 0.00</strong>
        </small>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label>Payment Method *</label>
          <select name="payment_method" class="form-control" required>
            <option value="Cash">Cash</option>
            <option value="bKash">bKash</option>
            <option value="Nagad">Nagad</option>
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="Rocket">Rocket</option>
            <option value="Cheque">Cheque</option>
          </select>
        </div>
        <div class="form-group">
          <label>Paid Date *</label>
          <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div class="form-group" style="margin-top: 0.8rem;">
        <label>Deposit Slip / Transaction Ref (Optional)</label>
        <input type="text" name="reference_no" class="form-control" placeholder="e.g. bKash TrxID or MR-102">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('collectUnpaidModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #10b981; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-check-circle"></i> Confirm Payment &amp; Print Slip
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 4: ADVANCE FEE COLLECTION MODAL
     ======================================================== -->
<div id="advanceFeeModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.8rem;">
      <div>
        <span style="font-size: 0.7rem; font-weight: 800; color: #047857; letter-spacing: 0.08em;">ADVANCE REVENUE</span>
        <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 2px 0 0 0; font-size: 1.35rem;">Collect Advance Tuition Fees</h3>
      </div>
      <button style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('advanceFeeModal')">&times;</button>
    </div>

    <form method="POST" action="fees.php?view=<?= $activeView ?>&month=<?= $selected_month ?>">
      <input type="hidden" name="process_advance_payment" value="1">

      <div class="form-group" style="margin-top: 1.2rem;">
        <label>Select Student *</label>
        <select name="student_id" class="form-control" required>
          <option value="">-- Choose Student --</option>
          <?php foreach ($allActiveStudents as $st): ?>
            <option value="<?= $st['id'] ?>">
              <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?> (Roll: <?= $st['roll_no'] ?: '-' ?> &bull; <?= htmlspecialchars($st['class_name']) ?> &bull; <?= htmlspecialchars($st['student_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 1rem;">
        <div class="form-group">
          <label>Start Month *</label>
          <input type="month" name="start_month" class="form-control" value="<?= date('Y-m', strtotime('+1 month')) ?>" required>
        </div>
        <div class="form-group">
          <label>Number of Months *</label>
          <select name="num_months" class="form-control" required>
            <option value="1">1 Month</option>
            <option value="2">2 Months</option>
            <option value="3">3 Months (Quarterly)</option>
            <option value="6">6 Months (Half Yearly)</option>
            <option value="12">12 Months (Full Year)</option>
          </select>
        </div>
      </div>

      <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 14px; margin-top: 1rem;">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: #065f46;">
          <input type="checkbox" name="mark_paid" value="1" checked style="accent-color: #10b981; transform: scale(1.3);">
          <span>Mark as Paid immediately (Deposit Collected)</span>
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
          <div>
            <label style="font-size: 0.78rem; color: #475569;">Payment Method</label>
            <select name="payment_method" class="form-control" style="padding: 0.45rem;">
              <option value="Cash">Cash</option>
              <option value="bKash / Mobile Banking">bKash</option>
              <option value="Bank Transfer">Bank Transfer</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.78rem; color: #475569;">Reference / Note</label>
            <input type="text" name="reference_no" class="form-control" placeholder="Trx ID or Receipt #" style="padding: 0.45rem;">
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('advanceFeeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: #0284c7; color: #ffffff; font-weight: 800; padding: 0.65rem 1.4rem; border-radius: 8px;">
          <i class="fas fa-layer-group"></i> Generate Invoices
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     MODAL 5: A4 DUAL MONEY RECEIPT SLIP MODAL (STUDENT & OFFICE COPY)
     ======================================================== -->
<?php require_once __DIR__ . '/../includes/receipt_modal.php'; ?>

<!-- ========================================================
     MODAL STYLES & INTERACTION SCRIPTS
     ======================================================== -->
<style>
.admin-modal-wrap {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(4px);
  z-index: 99999;
  display: flex; justify-content: center; align-items: center;
  padding: 1rem;
}
.admin-modal-box {
  background: #ffffff; border-radius: 16px;
  width: 100%; max-width: 580px;
  max-height: 90vh; overflow-y: auto;
  padding: 1.8rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
  animation: modalFadeIn 0.2s ease-out;
}
@keyframes modalFadeIn {
  from { opacity: 0; transform: translateY(-15px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.btn-quick-collect {
  background: #10b981; color: #ffffff; border: none; padding: 6px 12px;
  border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer;
  display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s;
}
.btn-quick-collect:hover { background: #059669; transform: translateY(-1px); }
.btn-receipt-view {
  background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;
  padding: 5px 10px; border-radius: 8px; font-weight: 700; font-size: 0.78rem;
  cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
}
.btn-receipt-view:hover { background: #0284c7; color: #ffffff; }
.roll-badge {
  display: inline-block; width: 30px; height: 30px; line-height: 30px;
  text-align: center; border-radius: 8px; background: #f1f5f9; color: var(--admin-deep-navy);
  font-weight: 800; font-size: 0.82rem;
}
.st-avatar {
  width: 36px; height: 36px; border-radius: 10px; display: grid;
  place-items: center; font-size: 1rem; flex-shrink: 0;
}
.st-avatar.male { background: #e0f2fe; color: #0284c7; }
.st-avatar.female { background: #fce7f3; color: #db2777; }
.row-due-highlight { background: rgba(254, 242, 242, 0.45); }
.preset-pill {
  background: #f1f5f9; border: 1.5px solid #cbd5e1; color: #334155;
  padding: 6px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700;
  cursor: pointer; transition: all 0.2s;
}
.preset-pill:hover, .preset-pill.active {
  background: #2563eb; color: #ffffff; border-color: #2563eb;
}
@media (max-width: 1024px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr) !important; }
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

// Preset Item Categories for Issue Invoice
function setPreset(category, title, amount, el) {
  document.querySelectorAll('.preset-pill').forEach(p => p.classList.remove('active'));
  if (el) el.classList.add('active');

  document.getElementById('issueItemCategory').value = category;
  document.getElementById('issueFeeType').value = title;

  if (amount === 'tuition') {
    // Look up selected student tuition fee
    const sel = document.getElementById('issueStudentSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.tuition) {
      document.getElementById('issueAmount').value = opt.dataset.tuition;
    } else {
      document.getElementById('issueAmount').value = 2000;
    }
  } else {
    document.getElementById('issueAmount').value = amount;
  }
}

function handleStudentSelect(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return;

  const currentCat = document.getElementById('issueItemCategory').value;
  if (currentCat === 'Tuition Fee' && opt.dataset.tuition) {
    document.getElementById('issueAmount').value = opt.dataset.tuition;
  }
}

function togglePaymentBox(status) {
  const paidBox = document.getElementById('boxPaidDetails');
  const unpaidBox = document.getElementById('boxUnpaidDetails');
  if (status === 'Paid') {
    paidBox.style.display = 'grid';
    unpaidBox.style.display = 'none';
  } else {
    paidBox.style.display = 'none';
    unpaidBox.style.display = 'block';
  }
}

// Quick Collect Monthly Fee Modal
function openCollectModal(st, month, monthName) {
  document.getElementById('collectStudentId').value = st.id;
  document.getElementById('collectFeeMonth').value = month;
  document.getElementById('collectStudentName').innerText = (st.first_name || '') + ' ' + (st.last_name || '');
  document.getElementById('collectStudentCode').innerText = st.student_code || st.student_id || 'N/A';
  document.getElementById('collectClassName').innerText = st.class_name || 'N/A';
  document.getElementById('collectRollNo').innerText = st.roll_no || 'N/A';
  document.getElementById('collectMonthDisplay').value = monthName;
  document.getElementById('collectAmount').value = st.effective_amount || st.class_tuition_fee || 2000;

  openModal('quickCollectModal');
}

// Collect Unpaid / Partial Custom Invoice Modal
var currentUnpaidMaxDue = 0;

function openCollectUnpaidModal(iv) {
  document.getElementById('unpaidFeeId').value = iv.id;
  document.getElementById('unpaidInvNo').innerText = iv.invoice_no;
  document.getElementById('unpaidStudentName').innerText = (iv.first_name || '') + ' ' + (iv.last_name || '');
  document.getElementById('unpaidFeeType').innerText = iv.fee_type;

  var total = parseFloat(iv.amount || 0);
  var paid = parseFloat(iv.paid_amount !== undefined ? iv.paid_amount : (iv.status === 'Paid' ? total : 0));
  var due = Math.max(0, total - paid);
  currentUnpaidMaxDue = due;

  function fmt(val) {
    return '৳ ' + parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  document.getElementById('unpaidTotalBill').innerText = fmt(total);
  document.getElementById('unpaidAlreadyPaid').innerText = fmt(paid);
  document.getElementById('unpaidAmount').innerText = fmt(due);

  // Default to Full Payment
  document.getElementById('toggleFullPay').checked = true;
  var payInput = document.getElementById('unpaidPayAmount');
  payInput.value = due.toFixed(2);
  payInput.max = due.toFixed(2);
  payInput.readOnly = true;

  updateRemainingHelp();
  openModal('collectUnpaidModal');
}

function handlePaymentTypeChange() {
  var isFull = document.getElementById('toggleFullPay').checked;
  var payInput = document.getElementById('unpaidPayAmount');
  if (isFull) {
    payInput.value = currentUnpaidMaxDue.toFixed(2);
    payInput.readOnly = true;
  } else {
    payInput.readOnly = false;
    payInput.focus();
    payInput.select();
  }
  updateRemainingHelp();
}

function updateRemainingHelp() {
  var payInput = document.getElementById('unpaidPayAmount');
  var entered = parseFloat(payInput.value) || 0;
  if (entered > currentUnpaidMaxDue) {
    entered = currentUnpaidMaxDue;
    payInput.value = currentUnpaidMaxDue.toFixed(2);
  }
  var rem = Math.max(0, currentUnpaidMaxDue - entered);
  var disp = document.getElementById('unpaidRemainingDisplay');
  disp.innerText = '৳ ' + rem.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (rem <= 0.01) {
    disp.style.color = '#059669';
    disp.innerText += ' (Full Settlement)';
  } else {
    disp.style.color = '#dc2626';
    disp.innerText += ' (Partial Balance Remaining)';
  }
}

// Auto open modal if action=issue_invoice in URL
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('action') === 'issue_invoice') {
    openModal('issueInvoiceModal');
  }
});
</script>

<?php if ($autoOpenReceipt): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  openReceiptModal(<?= json_encode($autoOpenReceipt) ?>, '<?= addslashes($selectedMonthName) ?>');
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout_footer.php'; ?>