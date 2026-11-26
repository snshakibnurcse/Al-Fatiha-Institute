<?php
// Global Helper Functions and Authentication Logic

// Start output buffering early to guarantee headers can always be modified/redirected cleanly
if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Sanitize user inputs
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Bulletproof Redirect helper (Supports standard header redirect and JS fallback)
function redirect($url) {
    // 1. If buffer has content, clean it so no partial HTML is sent
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 2. If headers have not been sent yet, use fast native 302 HTTP header
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }

    // 3. Fallback: If headers were already flushed to browser, redirect using JavaScript and HTML Meta
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    echo '</head><body><p style="font-family:sans-serif;text-align:center;padding:2rem;">Redirecting to <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url) . '</a>...</p></body></html>';
    exit();
}

// Check session
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_current_user_role() {
    static $synced = false;
    $role = $_SESSION['user_role'] ?? null;
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid == 1 || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')) {
        return 'super_admin';
    }
    if (!$synced && $uid) {
        $synced = true;
        try {
            $db = get_db_connection();
            $st = $db->prepare("
                SELECT u.role, e.role_type
                FROM users u
                LEFT JOIN employees e ON e.user_id = u.id
                WHERE u.id = ? LIMIT 1
            ");
            $st->execute([intval($uid)]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $dbRole = strtolower(trim($row['role'] ?? ''));
                $empRole = strtolower(trim($row['role_type'] ?? ''));
                if ($empRole === 'hr' && $dbRole !== 'super_admin') {
                    $dbRole = 'hr';
                }
                if ($dbRole !== '') {
                    $_SESSION['user_role'] = $dbRole;
                    $role = $dbRole;
                }
            }
        } catch (Exception $e) {}
    }
    return $role;
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_user_name() {
    $name = trim($_SESSION['user_name'] ?? '');
    if ($name !== '') {
        return $name;
    }
    return !empty($_SESSION['username']) ? $_SESSION['username'] : 'User';
}

// Auth Role Requirements
function is_super_admin() {
    if (!is_logged_in()) return false;
    $uid = get_current_user_id();
    $role = get_current_user_role();
    return ($uid == 1 || in_array($role, ['super_admin', 'admin']));
}

function is_principal() {
    if (!is_logged_in()) return false;
    return get_current_user_role() === 'principal';
}

function is_admin_or_principal() {
    if (!is_logged_in()) return false;
    $uid = get_current_user_id();
    $role = get_current_user_role();
    return ($uid == 1 || in_array($role, ['super_admin', 'admin', 'principal']));
}

function get_role_dashboard_path($role = null) {
    $r = $role ?? get_current_user_role();
    if (in_array($r, ['super_admin', 'admin', 'principal'], true)) {
        return 'admin/dashboard.php';
    }
    if ($r === 'hr') {
        return 'hr/dashboard.php';
    }
    if ($r === 'teacher') {
        return 'teacher/dashboard.php';
    }
    if ($r === 'accountant') {
        return 'accountant/dashboard.php';
    }
    if ($r === 'parent') {
        return 'portal/dashboard.php';
    }
    return 'login.php';
}

function redirect_to_role_dashboard($prefix = '') {
    redirect($prefix . get_role_dashboard_path());
}

function require_admin() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!is_admin_or_principal()) {
        redirect_to_role_dashboard('../');
    }
}

function is_hr_or_admin() {
    if (!is_logged_in()) return false;
    $uid = get_current_user_id();
    $role = get_current_user_role();
    return ($uid == 1 || in_array($role, ['hr', 'super_admin', 'admin', 'principal']));
}

function require_hr() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!is_hr_or_admin()) {
        redirect_to_role_dashboard('../');
    }
}

function require_super_admin() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!is_super_admin()) {
        if (function_exists('set_flash_msg')) {
            set_flash_msg("Access Denied: Only Super Administrator can access User Management.", "danger");
        }
        redirect('dashboard.php');
    }
}

function require_teacher() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!in_array(get_current_user_role(), ['teacher', 'super_admin', 'admin', 'principal'])) {
        redirect_to_role_dashboard('../');
    }
}

function require_parent() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!in_array(get_current_user_role(), ['parent', 'super_admin', 'admin', 'principal'])) {
        redirect_to_role_dashboard('../');
    }
}

function require_accountant() {
    if (!is_logged_in()) {
        redirect('../login.php');
    }
    if (!in_array(get_current_user_role(), ['accountant', 'super_admin', 'admin', 'principal'])) {
        redirect_to_role_dashboard('../');
    }
}

// Formatters
function format_date($dateStr) {
    if (!$dateStr) return 'N/A';
    return date('d M, Y', strtotime($dateStr));
}

function format_currency($amount) {
    return '৳ ' . number_format($amount, 2);
}

// Calculate Grade based on Marks
function calculate_grade($marks, $max_marks = 100) {
    $percentage = ($marks / $max_marks) * 100;
    if ($percentage >= 90) return ['grade' => 'A+', 'gpa' => '5.0', 'class' => 'badge-success'];
    if ($percentage >= 80) return ['grade' => 'A', 'gpa' => '4.0', 'class' => 'badge-success'];
    if ($percentage >= 70) return ['grade' => 'B', 'gpa' => '3.0', 'class' => 'badge-info'];
    if ($percentage >= 60) return ['grade' => 'C', 'gpa' => '2.0', 'class' => 'badge-warning'];
    if ($percentage >= 50) return ['grade' => 'D', 'gpa' => '1.0', 'class' => 'badge-warning'];
    return ['grade' => 'F', 'gpa' => '0.0', 'class' => 'badge-danger'];
}

// Get Site Settings from DB
function get_setting($key, $default = '') {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return $res ? $res['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Auto-Generate Monthly Fee Invoices for all Active Students on 1st of Month
 */
function auto_generate_monthly_invoices() {
    try {
        $db = get_db_connection();
        $currentYearMonth = date('Y-m'); // e.g. 2026-10
        $monthName = date('F Y');       // e.g. October 2026
        $feeType = "Monthly Tuition Fee - " . $monthName;
        $dueDate = date('Y-m-10');       // 10th of current month

        // Fetch all active students with class tuition fees
        $stmt = $db->query("
            SELECT s.id as student_id, s.first_name, s.last_name, s.student_id as st_code, c.tuition_fee, c.class_name 
            FROM students s 
            JOIN classes c ON s.class_id = c.id 
            WHERE s.status = 'Active'
        ");
        $students = $stmt->fetchAll();

        foreach ($students as $st) {
            $studentId = $st['student_id'];
            $tuitionFee = $st['tuition_fee'];

            // Check if invoice already exists for this student for current month
            $checkStmt = $db->prepare("
                SELECT id FROM fees 
                WHERE student_id = ? 
                AND (fee_type = ? OR (invoice_no LIKE ? AND fee_type LIKE 'Monthly Tuition Fee%'))
                LIMIT 1
            ");
            $likeInvoice = 'INV-' . str_replace('-', '', $currentYearMonth) . '%';
            $checkStmt->execute([$studentId, $feeType, $likeInvoice]);
            $exists = $checkStmt->fetch();

            if (!$exists && $tuitionFee > 0) {
                // Generate Invoice
                $invNo = 'INV-' . str_replace('-', '', $currentYearMonth) . '-' . sprintf('%03d', $studentId);
                $insertStmt = $db->prepare("
                    INSERT INTO fees (invoice_no, student_id, fee_type, amount, due_date, status) 
                    VALUES (?, ?, ?, ?, ?, 'Unpaid')
                ");
                $insertStmt->execute([$invNo, $studentId, $feeType, $tuitionFee, $dueDate]);
            }
        }
    } catch (Exception $e) {
        // Silent catch
    }
}

/**
 * Generate Advance Fee Invoices for a Student for 1 or more upcoming months
 */
function generate_advance_fee_invoice($student_id, $start_year_month, $num_months = 1, $mark_paid = false, $payment_method = 'Cash', $ref_no = '') {
    try {
        $db = get_db_connection();
        
        // Fetch student & tuition fee
        $stStmt = $db->prepare("
            SELECT s.id, s.first_name, s.last_name, c.tuition_fee, c.class_name 
            FROM students s 
            JOIN classes c ON s.class_id = c.id 
            WHERE s.id = ? LIMIT 1
        ");
        $stStmt->execute([$student_id]);
        $student = $stStmt->fetch();
        if (!$student) return ['success' => false, 'message' => 'Student not found.'];

        $tuitionFee = floatval($student['tuition_fee']);
        if ($tuitionFee <= 0) $tuitionFee = 3500.00;

        $createdInvoices = [];
        $totalAmount = 0;

        for ($i = 0; $i < $num_months; $i++) {
            $currentMonthTime = strtotime("+$i month", strtotime($start_year_month . "-01"));
            $monthName = date('F Y', $currentMonthTime);
            $ymStr = date('Ym', $currentMonthTime);
            $dueDate = date('Y-m-10', $currentMonthTime);

            $feeType = "Monthly Tuition Fee - " . $monthName . " (Advance)";
            $invNo = 'INV-ADV-' . $ymStr . '-' . $student['id'] . '-' . rand(10, 99);

            // Check if invoice already paid/exists for this month
            $chk = $db->prepare("SELECT id, status FROM fees WHERE student_id = ? AND fee_type LIKE ? LIMIT 1");
            $chk->execute([$student_id, "%" . $monthName . "%"]);
            $existing = $chk->fetch();

            if ($existing) {
                if ($existing['status'] === 'Paid') {
                    continue; // Skip if already paid
                } else {
                    $feeId = $existing['id'];
                }
            } else {
                $ins = $db->prepare("INSERT INTO fees (invoice_no, student_id, fee_type, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'Unpaid')");
                $ins->execute([$invNo, $student_id, $feeType, $tuitionFee, $dueDate]);
                $feeId = $db->lastInsertId();
            }

            $totalAmount += $tuitionFee;
            $createdInvoices[] = $feeId;

            if ($mark_paid) {
                mark_fee_as_paid($feeId, $payment_method, $ref_no ?: 'ADVANCE-PAYMENT');
            }
        }

        if (empty($createdInvoices)) {
            return ['success' => false, 'message' => 'Advance fees for selected months were already paid or generated.'];
        }

        return [
            'success' => true, 
            'count' => count($createdInvoices), 
            'total_amount' => $totalAmount,
            'message' => 'Advance payment of ' . format_currency($totalAmount) . ' for ' . count($createdInvoices) . ' month(s) processed successfully!'
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Record fee payment (supports Full and Partial payment)
 * Updates fees.paid_amount, fees.status ('Paid' or 'Partial'), and syncs to accounts_transactions
 */
function record_fee_payment($fee_id, $payment_amount, $payment_method = 'Cash', $ref_no = '', $paid_date = null) {
    try {
        $db = get_db_connection();
        $paidDate = $paid_date ?: date('Y-m-d');
        $paymentAmount = round(floatval($payment_amount), 2);
        
        if ($paymentAmount <= 0) {
            return ['success' => false, 'message' => 'Payment amount must be greater than zero.'];
        }
        
        $stmt = $db->prepare("
            SELECT f.*, s.first_name, s.last_name, s.student_id as student_code, s.roll_no, c.class_name 
            FROM fees f 
            JOIN students s ON f.student_id = s.id 
            JOIN classes c ON s.class_id = c.id 
            WHERE f.id = ? LIMIT 1
        ");
        $stmt->execute([$fee_id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fee) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }
        
        $totalAmount = round(floatval($fee['amount']), 2);
        $currentPaid = round(floatval($fee['paid_amount'] ?? 0), 2);
        
        // If already marked Paid in legacy DB without paid_amount, set currentPaid to totalAmount
        if ($fee['status'] === 'Paid' && $currentPaid <= 0) {
            $currentPaid = $totalAmount;
        }
        
        $currentDue = round(max(0, $totalAmount - $currentPaid), 2);
        
        if ($currentDue <= 0.001) {
            return ['success' => false, 'message' => 'Invoice #' . htmlspecialchars($fee['invoice_no']) . ' is already fully paid.'];
        }
        
        // Cap payment at current due
        if ($paymentAmount > $currentDue) {
            $paymentAmount = $currentDue;
        }
        
        $newTotalPaid = round($currentPaid + $paymentAmount, 2);
        $newDue = round(max(0, $totalAmount - $newTotalPaid), 2);
        $newStatus = ($newDue <= 0.001) ? 'Paid' : 'Partial';
        
        // Update fees table
        $combinedRef = $ref_no ? ($fee['reference_no'] ? $fee['reference_no'] . ', ' . $ref_no : $ref_no) : $fee['reference_no'];
        $upd = $db->prepare("
            UPDATE fees 
            SET paid_amount = ?, status = ?, paid_date = ?, payment_method = ?, reference_no = ? 
            WHERE id = ?
        ");
        $upd->execute([$newTotalPaid, $newStatus, $paidDate, $payment_method, $combinedRef, $fee_id]);
        
        // Sync paid amount to accounts_transactions
        $studentName = $fee['first_name'] . ' ' . $fee['last_name'];
        $title = $fee['fee_type'] . " - " . $studentName . " (" . $fee['class_name'] . ", Roll: " . $fee['roll_no'] . ")";
        $ref = $ref_no ? ($fee['invoice_no'] . ' / ' . $ref_no) : $fee['invoice_no'];
        $txnNo = 'TXN-INC-' . date('Ym') . '-' . sprintf('%03d', $fee_id) . '-' . rand(10, 99);
        
        $incomeCategory = 'Student Tuition & Fees';
        if (stripos($fee['fee_type'], 'khata') !== false || stripos($fee['fee_type'], 'book') !== false || stripos($fee['fee_type'], 'diary') !== false || stripos($fee['fee_type'], 'stationery') !== false || stripos($fee['fee_type'], 'uniform') !== false || stripos($fee['fee_type'], 'kit') !== false) {
            $incomeCategory = 'Book & Uniform Sales';
        } elseif (stripos($fee['fee_type'], 'exam') !== false) {
            $incomeCategory = 'Event & Competition Fees';
        } elseif (stripos($fee['fee_type'], 'admission') !== false || stripos($fee['fee_type'], 'registration') !== false) {
            $incomeCategory = 'Admission Form & Registration';
        }
        
        $paymentNote = ($newStatus === 'Paid') ? "Fully paid" : ("Partial payment (Due: ৳" . number_format($newDue, 2) . ")");
        
        $insTx = $db->prepare("
            INSERT INTO accounts_transactions 
            (transaction_no, type, category, title, amount, transaction_date, payment_method, reference_no, description, created_by) 
            VALUES (?, 'Income', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insTx->execute([
            $txnNo,
            $incomeCategory,
            $title,
            $paymentAmount,
            $paidDate,
            $payment_method,
            $ref,
            "Received ৳" . number_format($paymentAmount, 2) . " for Invoice #" . $fee['invoice_no'] . " [$paymentNote]",
            get_current_user_id()
        ]);
        
        // Prepare receipt data
        $receiptData = [
            'id' => $fee['id'],
            'invoice_no' => $fee['invoice_no'],
            'student_id' => $fee['student_id'],
            'student_code' => $fee['student_code'],
            'first_name' => $fee['first_name'],
            'last_name' => $fee['last_name'],
            'class_name' => $fee['class_name'],
            'roll_no' => $fee['roll_no'],
            'fee_type' => $fee['fee_type'],
            'notes' => $fee['notes'],
            'fee_month' => $fee['fee_month'],
            'total_amount' => $totalAmount,
            'paid_amount_now' => $paymentAmount,
            'paid_amount' => $newTotalPaid,
            'due_amount' => $newDue,
            'status' => $newStatus,
            'paid_date' => $paidDate,
            'payment_method' => $payment_method,
            'reference_no' => $ref_no
        ];
        
        return [
            'success' => true,
            'message' => ($newStatus === 'Paid')
                ? "Full payment of <strong>৳" . number_format($paymentAmount, 2) . "</strong> received for Invoice <strong>" . htmlspecialchars($fee['invoice_no']) . "</strong>!"
                : "Partial payment of <strong>৳" . number_format($paymentAmount, 2) . "</strong> received for Invoice <strong>" . htmlspecialchars($fee['invoice_no']) . "</strong>. Remaining due: <strong>৳" . number_format($newDue, 2) . "</strong>.",
            'receipt' => $receiptData
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Payment failed: ' . $e->getMessage()];
    }
}

/**
 * Mark Fee Invoice as Paid (Full Settlement) & Auto-Sync to Accounts Income Ledger
 */
function mark_fee_as_paid($fee_id, $payment_method = 'Cash', $ref_no = '', $paid_date = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT amount, paid_amount FROM fees WHERE id = ? LIMIT 1");
        $stmt->execute([$fee_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        
        $total = floatval($row['amount']);
        $currentPaid = floatval($row['paid_amount'] ?? 0);
        $due = max(0, $total - $currentPaid);
        if ($due <= 0.001) $due = $total;
        
        $res = record_fee_payment($fee_id, $due, $payment_method, $ref_no, $paid_date);
        return $res['success'] ?? false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Ensure latest Database Schema updates (Class teachers, Teacher Attendance, Timetable tables)
 */
function ensure_table_column($db, $table, $column, $definition) {
    try {
        $chk = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
        if ($chk && !$chk->fetch()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition;");
        }
    } catch (Exception $e) {}
}

function ensure_latest_db_schema() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db = get_db_connection();

        // Ensure users.role supports all roles (e.g., 'hr', 'principal', 'super_admin') and is not stuck on legacy ENUM
        try {
            $roleCol = $db->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
            if ($roleCol && stripos($roleCol['Type'], 'enum') !== false) {
                $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'parent';");
            }
        } catch (Exception $e) {}

        // Ensure classes table has numeric 2-digit class_code and standard names
        try {
            $db->exec("UPDATE `classes` SET `class_name` = 'Class One' WHERE id = 4 OR `class_name` = 'Year One';");
            $db->exec("UPDATE `classes` SET `class_name` = 'Class Two' WHERE id = 5 OR `class_name` = 'Year Two';");
            $db->exec("UPDATE `classes` SET `class_name` = 'Class Three' WHERE id = 6 OR `class_name` LIKE 'Year Three%' OR `class_name` = 'Year 3';");
        } catch (Exception $e) {}

        ensure_table_column($db, 'classes', 'class_code', 'VARCHAR(10) DEFAULT NULL AFTER `id`');
        try {
            $defaultClassCodes = [1 => '01', 2 => '02', 3 => '03', 4 => '04', 5 => '05', 6 => '06'];
            foreach ($defaultClassCodes as $cid => $cCode) {
                $stmtC = $db->prepare("UPDATE `classes` SET `class_code` = ? WHERE `id` = ? AND (`class_code` IS NULL OR `class_code` = '')");
                $stmtC->execute([$cCode, $cid]);
            }
            $allCls = $db->query("SELECT id, class_code FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allCls as $cRow) {
                if (empty($cRow['class_code'])) {
                    $codeVal = str_pad((string)$cRow['id'], 2, '0', STR_PAD_LEFT);
                    $db->prepare("UPDATE `classes` SET `class_code` = ? WHERE id = ?")->execute([$codeVal, $cRow['id']]);
                }
            }
            $idxChk = $db->query("SHOW INDEX FROM `classes` WHERE Key_name = 'uniq_class_code'")->fetch();
            if (!$idxChk) {
                $db->exec("ALTER TABLE `classes` ADD UNIQUE KEY `uniq_class_code` (`class_code`);");
            }
        } catch (Exception $e) {}

        // Add paid_amount and session/class tagging columns to fees table
        ensure_table_column($db, 'fees', 'paid_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`');
        ensure_table_column($db, 'fees', 'session_year', 'VARCHAR(10) DEFAULT NULL AFTER `fee_month`');
        ensure_table_column($db, 'fees', 'class_id', 'INT DEFAULT NULL AFTER `session_year`');
        try {
            $db->exec("UPDATE `fees` SET `paid_amount` = `amount` WHERE `status` = 'Paid' AND (`paid_amount` IS NULL OR `paid_amount` <= 0);");
        } catch (Exception $e) {}

        // Add session_year and class_id columns to results table for promotion history preservation
        ensure_table_column($db, 'results', 'session_year', 'VARCHAR(10) DEFAULT NULL AFTER `exam_term`');
        ensure_table_column($db, 'results', 'class_id', 'INT DEFAULT NULL AFTER `session_year`');

        // Add extended student details to students table if missing
        $studentCols = [
            'blood_group' => 'VARCHAR(10) DEFAULT NULL',
            'religion' => "VARCHAR(50) DEFAULT 'Islam'",
            'nationality' => "VARCHAR(50) DEFAULT 'Bangladeshi'",
            'birth_reg_no' => 'VARCHAR(100) DEFAULT NULL',
            'permanent_address' => 'TEXT DEFAULT NULL',
            'father_occupation' => 'VARCHAR(100) DEFAULT NULL',
            'father_phone' => 'VARCHAR(40) DEFAULT NULL',
            'father_nid' => 'VARCHAR(100) DEFAULT NULL',
            'mother_occupation' => 'VARCHAR(100) DEFAULT NULL',
            'mother_phone' => 'VARCHAR(40) DEFAULT NULL',
            'emergency_contact_name' => 'VARCHAR(100) DEFAULT NULL',
            'emergency_contact_phone' => 'VARCHAR(40) DEFAULT NULL',
            'emergency_contact_relation' => 'VARCHAR(50) DEFAULT NULL',
            'previous_school' => 'VARCHAR(150) DEFAULT NULL',
            'medical_notes' => 'TEXT DEFAULT NULL',
            'admission_session' => "VARCHAR(20) DEFAULT '2027'",
            'current_session' => "VARCHAR(10) DEFAULT '2027'"
        ];
        foreach ($studentCols as $colName => $colDef) {
            ensure_table_column($db, 'students', $colName, $colDef);
        }

        // Student Academic History (locks old class/roll/ID on admission & promotion)
        $db->exec("CREATE TABLE IF NOT EXISTS `student_academic_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_pk` INT NOT NULL,
            `session_year` VARCHAR(10) NOT NULL,
            `class_id` INT NOT NULL,
            `roll_no` INT NOT NULL,
            `student_id_code` VARCHAR(30) NOT NULL UNIQUE,
            `status` ENUM('Active','Promoted','Graduated','Transferred') DEFAULT 'Active',
            `remarks` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_sah_student` (`student_pk`),
            KEY `idx_sah_sess_cls` (`session_year`, `class_id`),
            FOREIGN KEY (`student_pk`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 1. Add primary_teacher_id & backup_teacher_id to classes table if missing
        ensure_table_column($db, 'classes', 'primary_teacher_id', 'INT DEFAULT NULL');
        ensure_table_column($db, 'classes', 'backup_teacher_id', 'INT DEFAULT NULL');

        // Extend teachers table with designation, employee link, status
        ensure_table_column($db, 'teachers', 'designation', "VARCHAR(60) NOT NULL DEFAULT 'Teacher' AFTER `teacher_code`");
        ensure_table_column($db, 'teachers', 'employee_id', 'INT DEFAULT NULL AFTER `user_id`');
        ensure_table_column($db, 'teachers', 'status', "ENUM('Active','Inactive','Resigned') DEFAULT 'Active'");

        // Unified Employees Directory & HR Tables
        $db->exec("CREATE TABLE IF NOT EXISTS `employees` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_code` VARCHAR(30) NOT NULL UNIQUE,
            `user_id` INT DEFAULT NULL,
            `teacher_id` INT DEFAULT NULL,
            `full_name` VARCHAR(120) NOT NULL,
            `role_type` ENUM('principal','teacher','assistant_teacher','hr','accountant','admin','support_staff') NOT NULL DEFAULT 'support_staff',
            `designation` VARCHAR(100) NOT NULL,
            `department` VARCHAR(80) DEFAULT 'General',
            `employment_type` ENUM('Full-Time','Part-Time','Contract') DEFAULT 'Full-Time',
            `gender` ENUM('Male','Female','Other') DEFAULT 'Male',
            `phone` VARCHAR(30) DEFAULT NULL,
            `email` VARCHAR(120) DEFAULT NULL,
            `nid_no` VARCHAR(60) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `qualification` VARCHAR(150) DEFAULT NULL,
            `specialty` VARCHAR(120) DEFAULT NULL,
            `joining_date` DATE DEFAULT NULL,
            `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `house_rent` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `medical_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `transport_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `other_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `payment_method` VARCHAR(50) DEFAULT 'Cash',
            `bank_account` VARCHAR(120) DEFAULT NULL,
            `status` ENUM('Active','On Leave','Resigned','Terminated') DEFAULT 'Active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_emp_role` (`role_type`),
            KEY `idx_emp_status` (`status`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `employee_role_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_pk` INT NOT NULL,
            `employee_code` VARCHAR(30) NOT NULL UNIQUE,
            `role_type` VARCHAR(40) NOT NULL,
            `designation` VARCHAR(100) NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE DEFAULT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`employee_pk`) REFERENCES `employees`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `employee_attendance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_pk` INT NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` ENUM('Present','Absent','Late','Half-Day','On Leave') NOT NULL DEFAULT 'Present',
            `check_in` TIME DEFAULT NULL,
            `check_out` TIME DEFAULT NULL,
            `remarks` VARCHAR(200) DEFAULT NULL,
            `marked_by` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_emp_att_date` (`employee_pk`, `attendance_date`),
            FOREIGN KEY (`employee_pk`) REFERENCES `employees`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `leave_types` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(60) NOT NULL UNIQUE,
            `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
            `annual_quota` INT NOT NULL DEFAULT 10,
            `description` VARCHAR(200) DEFAULT NULL,
            `status` ENUM('Active','Inactive') DEFAULT 'Active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        if ((int)$db->query("SELECT COUNT(*) FROM leave_types")->fetchColumn() === 0) {
            $db->exec("INSERT INTO `leave_types` (`name`, `is_paid`, `annual_quota`, `description`, `status`) VALUES
                ('Casual Leave', 1, 10, 'Paid personal or urgent short leave', 'Active'),
                ('Sick Leave', 1, 12, 'Paid medical or illness leave', 'Active'),
                ('Earned Leave', 1, 14, 'Paid annual earned vacation leave', 'Active'),
                ('Unpaid Leave', 0, 30, 'Leave without pay (deducted in payroll)', 'Active');");
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `leave_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employee_pk` INT NOT NULL,
            `leave_type_id` INT NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `total_days` INT NOT NULL DEFAULT 1,
            `reason` TEXT NOT NULL,
            `status` ENUM('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
            `reviewed_by` INT DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `review_notes` VARCHAR(255) DEFAULT NULL,
            `applied_by_user_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`employee_pk`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `payroll_runs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `payroll_month` VARCHAR(7) NOT NULL UNIQUE,
            `month_label` VARCHAR(40) NOT NULL,
            `working_days` INT NOT NULL DEFAULT 26,
            `late_threshold` INT NOT NULL DEFAULT 3,
            `status` ENUM('Draft','Finalized','Paid') DEFAULT 'Draft',
            `generated_by` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `payroll_slips` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slip_no` VARCHAR(30) NOT NULL UNIQUE,
            `payroll_run_id` INT NOT NULL,
            `payroll_month` VARCHAR(7) NOT NULL,
            `employee_pk` INT NOT NULL,
            `employee_code_snapshot` VARCHAR(30) NOT NULL,
            `employee_name_snapshot` VARCHAR(120) NOT NULL,
            `designation_snapshot` VARCHAR(100) NOT NULL,
            `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `allowances` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `gross_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `working_days` INT NOT NULL DEFAULT 26,
            `present_days` INT NOT NULL DEFAULT 0,
            `paid_leave_days` INT NOT NULL DEFAULT 0,
            `absent_days` INT NOT NULL DEFAULT 0,
            `unpaid_leave_days` INT NOT NULL DEFAULT 0,
            `late_days` INT NOT NULL DEFAULT 0,
            `suggested_late_deduct_days` INT NOT NULL DEFAULT 0,
            `applied_late_deduct_days` INT NOT NULL DEFAULT 0,
            `unpaid_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `late_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `other_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `bonus_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `net_payable` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `adjustment_note` VARCHAR(255) DEFAULT NULL,
            `payment_status` ENUM('Unpaid','Paid') DEFAULT 'Unpaid',
            `paid_date` DATE DEFAULT NULL,
            `payment_method` VARCHAR(50) DEFAULT 'Cash',
            `expense_tx_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_slip_month_emp` (`payroll_month`, `employee_pk`),
            FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`employee_pk`) REFERENCES `employees`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Legacy teacher_attendance table
        $db->exec("CREATE TABLE IF NOT EXISTS `teacher_attendance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `teacher_id` INT NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
            `remarks` VARCHAR(150) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // 3. Create timetables table
        $db->exec("CREATE TABLE IF NOT EXISTS `timetables` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `class_id` INT NOT NULL,
            `day_of_week` ENUM('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
            `period_name` VARCHAR(50) NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `subject_id` INT DEFAULT NULL,
            `teacher_ids` VARCHAR(255) DEFAULT NULL,
            `room_no` VARCHAR(20) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // 4. Create migration tracker table
        $db->exec("CREATE TABLE IF NOT EXISTS `system_migrations` (
            `migration_key` VARCHAR(100) PRIMARY KEY,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. Repair root admin (#1) and any HR users coerced by legacy ENUM BEFORE cleanup deletes 'parent' rows
        $hrFixKey = '2026_11_26_fix_hr_role_v2';
        $hrFixChk = $db->prepare("SELECT 1 FROM system_migrations WHERE migration_key = ? LIMIT 1");
        $hrFixChk->execute([$hrFixKey]);
        if (!$hrFixChk->fetchColumn()) {
            try {
                $db->exec("UPDATE users SET role = 'super_admin' WHERE (id = 1 OR LOWER(username) = 'admin') AND role != 'super_admin';");
                $db->exec("UPDATE users u JOIN employees e ON e.user_id = u.id SET u.role = 'hr' WHERE e.role_type = 'hr' AND u.id != 1 AND u.role != 'super_admin' AND u.role != 'hr';");
                $db->exec("UPDATE users SET role = 'hr' WHERE id != 1 AND (role = '' OR role IS NULL OR role = 'parent') AND (LOWER(username) LIKE '%hr%' OR LOWER(full_name) LIKE '%hr manager%' OR LOWER(full_name) LIKE '%hr officer%');");
            } catch (Exception $e) {}
            $db->prepare("INSERT INTO system_migrations (migration_key) VALUES (?)")->execute([$hrFixKey]);
        }

        // 6. One-Time Clean-Slate Migration for Auto-ID & HR/Payroll Rollout
        $migKey = '2026_11_25_clean_slate_v1';
        $migChk = $db->prepare("SELECT 1 FROM system_migrations WHERE migration_key = ? LIMIT 1");
        $migChk->execute([$migKey]);
        if (!$migChk->fetchColumn()) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            foreach ([
                'results', 'fees', 'attendance', 'student_academic_history', 'students', 'admissions',
                'timetables', 'subjects', 'teacher_attendance', 'teachers',
                'payroll_slips', 'payroll_runs', 'leave_requests', 'employee_attendance', 'employee_role_history', 'employees'
            ] as $tbl) {
                try {
                    $db->exec("TRUNCATE TABLE `$tbl`;");
                } catch (Exception $e) {}
            }
            $db->exec("UPDATE classes SET primary_teacher_id = NULL, backup_teacher_id = NULL;");
            foreach ([1 => '01', 2 => '02', 3 => '03', 4 => '04', 5 => '05', 6 => '06'] as $cid => $cCode) {
                $db->prepare("UPDATE classes SET class_code = ? WHERE id = ?")->execute([$cCode, $cid]);
            }
            $db->exec("DELETE FROM users WHERE role IN ('teacher', 'parent');");
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $db->prepare("INSERT INTO system_migrations (migration_key) VALUES (?)")->execute([$migKey]);
        }

        // 7. Ensure every existing principal / accountant / hr user is registered in unified employees directory
        $syncKey = '2026_11_26_sync_core_employees_v1';
        $syncChk = $db->prepare("SELECT 1 FROM system_migrations WHERE migration_key = ? LIMIT 1");
        $syncChk->execute([$syncKey]);
        if (!$syncChk->fetchColumn()) {
            try {
                require_once __DIR__ . '/hr_helpers.php';
                $defs = hr_role_definitions();
                $coreUsers = $db->query("SELECT u.id, u.full_name, u.email, u.phone, u.role FROM users u LEFT JOIN employees e ON e.user_id = u.id WHERE u.role IN ('principal', 'accountant', 'hr') AND e.id IS NULL ORDER BY u.id ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($coreUsers as $u) {
                    $rType = $u['role'];
                    $code = generate_employee_code($rType);
                    $desig = $defs[$rType]['label'] ?? ucfirst($rType);
                    $dept = $defs[$rType]['department'] ?? 'General';
                    $db->prepare("INSERT INTO employees (employee_code, user_id, full_name, role_type, designation, department, email, phone, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Active')")
                       ->execute([$code, $u['id'], $u['full_name'], $rType, $desig, $dept, $u['email'], $u['phone'] ?: '']);
                    $empPk = intval($db->lastInsertId());
                    record_employee_role_history($empPk, $code, $rType, $desig, date('Y-m-d'), 'Initial employee registration');
                }
            } catch (Exception $e) {}
            $db->prepare("INSERT INTO system_migrations (migration_key) VALUES (?)")->execute([$syncKey]);
        }

    } catch (Exception $ex) {
        // Silent catch
    }
}

require_once __DIR__ . '/hr_helpers.php';

// Automatically trigger schema migration check & monthly invoice generation
ensure_latest_db_schema();
auto_generate_monthly_invoices();

/**
 * Get the 2-digit numeric class code from classes.class_code (with fallback mapping).
 */
function get_class_numeric_code($classIdOrName) {
    $db = get_db_connection();
    if (is_numeric($classIdOrName)) {
        $stmt = $db->prepare("SELECT id, class_name, class_code FROM classes WHERE id = ? LIMIT 1");
        $stmt->execute([intval($classIdOrName)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!empty($row['class_code'])) {
                return str_pad(preg_replace('/\D/', '', $row['class_code']) ?: (string)$row['id'], 2, '0', STR_PAD_LEFT);
            }
            return str_pad((string)$row['id'], 2, '0', STR_PAD_LEFT);
        }
    }
    $lower = strtolower(trim((string)$classIdOrName));
    if (strpos($lower, 'play') !== false || strpos($lower, 'pg') !== false) return '01';
    if (strpos($lower, 'nurs') !== false) return '02';
    if (strpos($lower, 'kg') !== false || strpos($lower, 'kinder') !== false) return '03';
    if (preg_match('/one|1\b/i', $lower)) return '04';
    if (preg_match('/two|2\b/i', $lower)) return '05';
    if (preg_match('/three|3\b/i', $lower)) return '06';
    if (preg_match('/four|4\b/i', $lower)) return '07';
    if (preg_match('/five|5\b/i', $lower)) return '08';
    return '01';
}

/**
 * Legacy helper kept for display tags if needed.
 */
function get_class_short_code($className) {
    return get_class_numeric_code($className);
}

/**
 * Resolve active default admission/academic session year (next calendar year after Nov, or current + 1 in test).
 */
function get_default_academic_session() {
    $yr = (int)date('Y');
    return (date('n') >= 11) ? (string)($yr + 1) : (string)$yr;
}

/**
 * Check whether a student ID already exists in students OR student_academic_history.
 */
function is_student_id_taken($candidateId, $excludeStudentPk = null) {
    $db = get_db_connection();
    $sql1 = "SELECT id FROM students WHERE student_id = ?";
    $p1 = [$candidateId];
    if ($excludeStudentPk) {
        $sql1 .= " AND id != ?";
        $p1[] = intval($excludeStudentPk);
    }
    $s1 = $db->prepare($sql1);
    $s1->execute($p1);
    if ($s1->fetch()) return true;

    $sql2 = "SELECT id FROM student_academic_history WHERE student_id_code = ?";
    $p2 = [$candidateId];
    if ($excludeStudentPk) {
        $sql2 .= " AND student_pk != ?";
        $p2[] = intval($excludeStudentPk);
    }
    $s2 = $db->prepare($sql2);
    $s2->execute($p2);
    return (bool)$s2->fetch();
}

/**
 * Generate Unique 9-Digit Student ID: [SESSION_YEAR(4)][CLASS_CODE(2)][ROLL(3)]
 * Example: Session 2027, Play Group (01), Roll 001 -> 202701001
 * Guarantees zero duplicates across active students AND historical enrollments.
 */
function generate_student_id_for_class($classIdOrName, $rollNo = null, $excludeStudentPk = null, $sessionYear = null) {
    $db = get_db_connection();
    $year = preg_replace('/\D/', '', (string)($sessionYear ?: get_default_academic_session()));
    if (strlen($year) !== 4) {
        $year = get_default_academic_session();
    }

    $classCode = get_class_numeric_code($classIdOrName);
    $prefix = $year . $classCode;

    if ($rollNo !== null && intval($rollNo) > 0) {
        $targetId = $prefix . str_pad(intval($rollNo), 3, '0', STR_PAD_LEFT);
        if (!is_student_id_taken($targetId, $excludeStudentPk)) {
            return $targetId;
        }
    }

    // Collect used 3-digit numbers for this [YEAR][CLASS_CODE] prefix across active + history
    $usedNumbers = [];
    $s1 = $db->prepare("SELECT student_id FROM students WHERE student_id LIKE ?");
    $s1->execute([$prefix . '%']);
    foreach ($s1->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $part = substr($sid, strlen($prefix));
        if (is_numeric($part)) $usedNumbers[intval($part)] = true;
    }

    $s2 = $db->prepare("SELECT student_id_code FROM student_academic_history WHERE student_id_code LIKE ?");
    $s2->execute([$prefix . '%']);
    foreach ($s2->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        $part = substr($sid, strlen($prefix));
        if (is_numeric($part)) $usedNumbers[intval($part)] = true;
    }

    $candidate = ($rollNo && intval($rollNo) > 0) ? intval($rollNo) : 1;
    while (isset($usedNumbers[$candidate])) {
        $candidate++;
    }

    return $prefix . str_pad($candidate, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate next sequential Student ID for session & class.
 */
function generate_next_student_id($sessionYear = null, $classId = null) {
    return generate_student_id_for_class($classId ?: 1, null, null, $sessionYear);
}

/**
 * Get the next sequential roll number for a given class (and optional session),
 * checking both active students and academic history in that session+class.
 */
function get_next_class_roll($classId, $sessionYear = null) {
    $db = get_db_connection();
    $cls = intval($classId);
    $sess = $sessionYear ?: get_default_academic_session();

    $s1 = $db->prepare("SELECT COALESCE(MAX(roll_no), 0) FROM students WHERE class_id = ? AND COALESCE(current_session, admission_session, ?) = ?");
    $s1->execute([$cls, $sess, $sess]);
    $max1 = intval($s1->fetchColumn());

    $s2 = $db->prepare("SELECT COALESCE(MAX(roll_no), 0) FROM student_academic_history WHERE class_id = ? AND session_year = ?");
    $s2->execute([$cls, $sess]);
    $max2 = intval($s2->fetchColumn());

    return max($max1, $max2) + 1;
}

/**
 * Record an academic enrollment snapshot in student_academic_history if not yet stored.
 */
function record_student_academic_snapshot($studentPk, $sessionYear, $classId, $rollNo, $studentCode, $status = 'Active', $remarks = null) {
    $db = get_db_connection();
    $chk = $db->prepare("SELECT id FROM student_academic_history WHERE student_id_code = ? LIMIT 1");
    $chk->execute([$studentCode]);
    if ($chk->fetch()) {
        return;
    }
    $ins = $db->prepare("INSERT INTO student_academic_history (student_pk, session_year, class_id, roll_no, student_id_code, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([intval($studentPk), $sessionYear, intval($classId), intval($rollNo), $studentCode, $status, $remarks]);
}

/**
 * Flash Notification System for PRG (Post/Redirect/Get)
 */
function set_flash_msg($message, $type = 'success') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_notification'] = [
        'message' => $message,
        'type' => $type
    ];
}

function get_flash_msg() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_notification'])) {
        $flash = $_SESSION['flash_notification'];
        unset($_SESSION['flash_notification']);
        return $flash;
    }
    return null;
}

/**
 * Delete Fee Invoice and clean up associated income ledger transaction
 */
function delete_fee_invoice($fee_id) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM fees WHERE id = ? LIMIT 1");
        $stmt->execute([$fee_id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fee) {
            return ['success' => false, 'message' => 'Invoice not found or already deleted.'];
        }

        // Delete from accounts_transactions if synced
        if (!empty($fee['invoice_no'])) {
            $db->prepare("DELETE FROM accounts_transactions WHERE reference_no LIKE ? AND type = 'Income'")
               ->execute(['%' . $fee['invoice_no'] . '%']);
        }

        // Delete invoice
        $del = $db->prepare("DELETE FROM fees WHERE id = ?");
        $del->execute([$fee_id]);

        return [
            'success' => true,
            'message' => "Invoice <strong>" . htmlspecialchars($fee['invoice_no']) . "</strong> (" . htmlspecialchars($fee['fee_type']) . ") deleted successfully.",
            'student_id' => $fee['student_id']
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to delete invoice: ' . $e->getMessage()];
    }
}

/**
 * Update Fee Invoice details (title, amount, due_date, fee_month, notes)
 */
function update_fee_invoice($fee_id, $data) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM fees WHERE id = ? LIMIT 1");
        $stmt->execute([$fee_id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fee) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $fee_type = sanitize($data['fee_type'] ?? $fee['fee_type']);
        $amount = floatval($data['amount'] ?? $fee['amount']);
        $due_date = sanitize($data['due_date'] ?? $fee['due_date']);
        $fee_month = sanitize($data['fee_month'] ?? $fee['fee_month']);
        $notes = sanitize($data['notes'] ?? $fee['notes']);

        if ($amount <= 0 || empty($fee_type)) {
            return ['success' => false, 'message' => 'Please provide valid fee title and amount.'];
        }

        $upd = $db->prepare("UPDATE fees SET fee_type = ?, amount = ?, due_date = ?, fee_month = ?, notes = ? WHERE id = ?");
        $upd->execute([$fee_type, $amount, $due_date, $fee_month, $notes, $fee_id]);

        // If paid, update income transaction amount and title
        if ($fee['status'] === 'Paid' && !empty($fee['invoice_no'])) {
            $db->prepare("UPDATE accounts_transactions SET amount = ?, title = ? WHERE reference_no LIKE ? AND type = 'Income'")
               ->execute([$amount, "Fee: " . $fee_type, '%' . $fee['invoice_no'] . '%']);
        }

        return [
            'success' => true,
            'message' => "Invoice <strong>" . htmlspecialchars($fee['invoice_no']) . "</strong> updated successfully.",
            'student_id' => $fee['student_id']
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to update invoice: ' . $e->getMessage()];
    }
}
