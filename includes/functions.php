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
    return $_SESSION['user_role'] ?? null;
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_user_name() {
    return $_SESSION['user_name'] ?? 'User';
}

// Auth Role Requirements
function is_super_admin() {
    return is_logged_in() && in_array(get_current_user_role(), ['super_admin', 'admin']);
}

function is_principal() {
    return is_logged_in() && get_current_user_role() === 'principal';
}

function is_admin_or_principal() {
    return is_logged_in() && in_array(get_current_user_role(), ['super_admin', 'admin', 'principal']);
}

function require_admin() {
    if (!is_admin_or_principal()) {
        redirect('../login.php');
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
    if (!is_logged_in() || (!in_array(get_current_user_role(), ['teacher', 'super_admin', 'admin', 'principal']))) {
        redirect('../login.php');
    }
}

function require_parent() {
    if (!is_logged_in() || (!in_array(get_current_user_role(), ['parent', 'super_admin', 'admin', 'principal']))) {
        redirect('../login.php');
    }
}

function require_accountant() {
    if (!is_logged_in() || (!in_array(get_current_user_role(), ['accountant', 'super_admin', 'admin', 'principal']))) {
        redirect('../login.php');
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
function ensure_latest_db_schema() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db = get_db_connection();

        // Rename Year classes to Class One, Class Two, Class Three
        try {
            $db->exec("UPDATE `classes` SET `class_name` = 'Class One' WHERE id = 4 OR `class_name` = 'Year One';");
            $db->exec("UPDATE `classes` SET `class_name` = 'Class Two' WHERE id = 5 OR `class_name` = 'Year Two';");
            $db->exec("UPDATE `classes` SET `class_name` = 'Class Three' WHERE id = 6 OR `class_name` LIKE 'Year Three%' OR `class_name` = 'Year 3';");
        } catch (Exception $e) {}

        // Standardize legacy student IDs to official format (AFI-C1-001, AFI-C2-001)
        try {
            $db->exec("UPDATE `students` SET `student_id` = 'AFI-C1-001' WHERE id = 1 AND `student_id` != 'AFI-C1-001';");
            $db->exec("UPDATE `students` SET `student_id` = 'AFI-C1-002' WHERE id = 16 AND `student_id` != 'AFI-C1-002';");
            $db->exec("UPDATE `students` SET `student_id` = 'AFI-C1-003' WHERE id = 17 AND `student_id` != 'AFI-C1-003';");
            $db->exec("UPDATE `students` SET `student_id` = 'AFI-C2-001' WHERE id = 18 AND `student_id` != 'AFI-C2-001';");
            $db->exec("UPDATE `students` SET `student_id` = 'AFI-C2-002' WHERE id = 19 AND `student_id` != 'AFI-C2-002';");
        } catch (Exception $e) {}

        // Add paid_amount column to fees table for partial payment support
        try {
            $hasPaidCol = $db->query("SHOW COLUMNS FROM `fees` LIKE 'paid_amount'")->fetch();
            if (!$hasPaidCol) {
                $db->exec("ALTER TABLE `fees` ADD COLUMN `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`;");
            }
            $db->exec("UPDATE `fees` SET `paid_amount` = `amount` WHERE `status` = 'Paid' AND (`paid_amount` IS NULL OR `paid_amount` <= 0);");
        } catch (Exception $e) {}

        // Add extended student details to students table if missing
        $studentCols = [
            "blood_group VARCHAR(10) DEFAULT NULL",
            "religion VARCHAR(50) DEFAULT 'Islam'",
            "nationality VARCHAR(50) DEFAULT 'Bangladeshi'",
            "birth_reg_no VARCHAR(100) DEFAULT NULL",
            "permanent_address TEXT DEFAULT NULL",
            "father_occupation VARCHAR(100) DEFAULT NULL",
            "father_phone VARCHAR(40) DEFAULT NULL",
            "father_nid VARCHAR(100) DEFAULT NULL",
            "mother_occupation VARCHAR(100) DEFAULT NULL",
            "mother_phone VARCHAR(40) DEFAULT NULL",
            "emergency_contact_name VARCHAR(100) DEFAULT NULL",
            "emergency_contact_phone VARCHAR(40) DEFAULT NULL",
            "emergency_contact_relation VARCHAR(50) DEFAULT NULL",
            "previous_school VARCHAR(150) DEFAULT NULL",
            "medical_notes TEXT DEFAULT NULL",
            "admission_session VARCHAR(20) DEFAULT '2027'"
        ];
        foreach ($studentCols as $colDef) {
            try {
                $db->exec("ALTER TABLE `students` ADD COLUMN IF NOT EXISTS $colDef;");
            } catch (Exception $e) {}
        }

        // 1. Add primary_teacher_id & backup_teacher_id to classes table if missing
        try {
            $db->exec("ALTER TABLE `classes` ADD COLUMN `primary_teacher_id` INT DEFAULT NULL;");
        } catch (Exception $e) {}

        try {
            $db->exec("ALTER TABLE `classes` ADD COLUMN `backup_teacher_id` INT DEFAULT NULL;");
        } catch (Exception $e) {}

        // Auto-assign default primary teacher (Ustadh Ahmad Rahman = ID 1) to Class One (ID 4) if null
        try {
            $db->exec("UPDATE `classes` SET `primary_teacher_id` = 1 WHERE `id` = 4 AND `primary_teacher_id` IS NULL;");
        } catch (Exception $e) {}

        // 2. Create teacher_attendance table
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

        // Seed demo timetable routines if empty
        $tCnt = $db->query("SELECT COUNT(*) FROM timetables")->fetchColumn();
        if ($tCnt == 0) {
            $db->exec("INSERT INTO `timetables` (`class_id`, `day_of_week`, `period_name`, `start_time`, `end_time`, `subject_id`, `teacher_ids`, `room_no`) VALUES
            (4, 'Sunday', 'Period 1 (Morning Quran)', '08:30:00', '09:15:00', 7, '1', '201'),
            (4, 'Sunday', 'Period 2 (Cambridge Literacy)', '09:15:00', '10:00:00', 4, '1', '201'),
            (4, 'Sunday', 'Period 3 (Math & Numeracy)', '10:15:00', '11:00:00', 5, '1', '201'),
            (4, 'Monday', 'Period 1 (Arabic & Tajweed)', '08:30:00', '09:15:00', 7, '1', '201'),
            (4, 'Monday', 'Period 2 (Science Lab)', '09:15:00', '10:00:00', 6, '1', '201'),
            (4, 'Tuesday', 'Period 1 (Cambridge Literacy)', '08:30:00', '09:15:00', 4, '1', '201'),
            (4, 'Wednesday', 'Period 1 (Islamic Ethics & Hifz)', '08:30:00', '09:15:00', 7, '1', '201'),
            (4, 'Thursday', 'Period 1 (Recitation & Art)', '08:30:00', '09:15:00', 7, '1', '201');");
        }

    } catch (Exception $ex) {
        // Silent catch
    }
}

// Automatically trigger schema migration check & monthly invoice generation
ensure_latest_db_schema();
auto_generate_monthly_invoices();

/**
 * Map Class Name to official standard short code:
 * Play Group -> PG
 * Nursery -> NUR
 * KG (Kindergarten) -> KG
 * Class One -> C1
 * Class Two -> C2
 * Class Three -> C3
 * Class Four -> C4
 * Class Five -> C5
 * Class Six -> C6
 * Hifz / Quran -> HFZ
 */
function get_class_short_code($className) {
    $name = trim($className ?? '');
    $lower = strtolower($name);

    if (strpos($lower, 'play') !== false || strpos($lower, 'pg') !== false) {
        return 'PG';
    }
    if (strpos($lower, 'nurs') !== false || strpos($lower, 'nur') !== false) {
        return 'NUR';
    }
    if (strpos($lower, 'kg') !== false || strpos($lower, 'kinder') !== false) {
        return 'KG';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:one|1|i\b)/i', $name)) {
        return 'C1';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:two|2|ii\b)/i', $name)) {
        return 'C2';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:three|3|iii\b)/i', $name)) {
        return 'C3';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:four|4|iv\b)/i', $name)) {
        return 'C4';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:five|5|v\b)/i', $name)) {
        return 'C5';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:six|6|vi\b)/i', $name)) {
        return 'C6';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:seven|7|vii\b)/i', $name)) {
        return 'C7';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:eight|8|viii\b)/i', $name)) {
        return 'C8';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:nine|9|ix\b)/i', $name)) {
        return 'C9';
    }
    if (preg_match('/(?:class|year|grade)\s*(?:ten|10|x\b)/i', $name)) {
        return 'C10';
    }
    if (strpos($lower, 'hifz') !== false || strpos($lower, 'tahfeez') !== false || strpos($lower, 'quran') !== false) {
        return 'HFZ';
    }
    
    // Generic fallback: first letters or uppercase alphanumeric
    $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    $code = '';
    foreach ($words as $w) {
        if (!empty($w)) $code .= strtoupper($w[0]);
    }
    if (strlen($code) < 2) {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));
    }
    return !empty($code) ? substr($code, 0, 4) : 'GEN';
}

/**
 * Generate Fixed Student ID by Class: AFI-[CODE]-[ROLL]
 * Example: AFI-PG-001, AFI-NUR-002, AFI-KG-001, AFI-C1-001, AFI-C2-001, AFI-C3-001
 */
function generate_student_id_for_class($classIdOrName, $rollNo = null, $excludeStudentPk = null) {
    $db = get_db_connection();
    
    // Resolve class name
    $className = '';
    if (is_numeric($classIdOrName)) {
        $stmt = $db->prepare("SELECT class_name FROM classes WHERE id = ? LIMIT 1");
        $stmt->execute([intval($classIdOrName)]);
        $className = $stmt->fetchColumn() ?: 'Class One';
    } else {
        $className = (string)$classIdOrName;
    }
    
    $code = get_class_short_code($className);
    $prefix = "AFI-" . $code . "-";
    
    if ($rollNo !== null && intval($rollNo) > 0) {
        $targetId = $prefix . str_pad(intval($rollNo), 3, '0', STR_PAD_LEFT);
        // Verify uniqueness
        $chkQuery = "SELECT id FROM students WHERE student_id = ?";
        $chkParams = [$targetId];
        if ($excludeStudentPk) {
            $chkQuery .= " AND id != ?";
            $chkParams[] = intval($excludeStudentPk);
        }
        $chkStmt = $db->prepare($chkQuery);
        $chkStmt->execute($chkParams);
        if (!$chkStmt->fetch()) {
            return $targetId;
        }
    }
    
    // Find next available sequential number
    $stmt = $db->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id ASC");
    $stmt->execute([$prefix . '%']);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $usedNumbers = [];
    foreach ($existing as $sid) {
        $part = substr($sid, strlen($prefix));
        if (is_numeric($part)) {
            $usedNumbers[intval($part)] = true;
        }
    }
    
    $candidate = ($rollNo && intval($rollNo) > 0) ? intval($rollNo) : 1;
    while (isset($usedNumbers[$candidate])) {
        $candidate++;
    }
    
    return $prefix . str_pad($candidate, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate next sequential Student ID with specific format: AFI-YYYY-XXX or AFI-[CODE]-[ROLL]
 */
function generate_next_student_id($sessionYear = '2027', $classId = null) {
    if ($classId !== null) {
        return generate_student_id_for_class($classId);
    }
    $db = get_db_connection();
    $prefix = "AFI-" . $sessionYear . "-";
    
    // Find highest serial in that year
    $stmt = $db->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 50");
    $stmt->execute([$prefix . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $maxSerial = 0;
    foreach ($rows as $sId) {
        $numPart = intval(substr($sId, strlen($prefix)));
        if ($numPart > $maxSerial) {
            $maxSerial = $numPart;
        }
    }
    
    $nextSerial = $maxSerial + 1;
    return $prefix . str_pad($nextSerial, 3, '0', STR_PAD_LEFT);
}

/**
 * Get the next sequential roll number for a given class
 */
function get_next_class_roll($classId) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT COALESCE(MAX(roll_no), 0) + 1 FROM students WHERE class_id = ?");
    $stmt->execute([intval($classId)]);
    return intval($stmt->fetchColumn()) ?: 1;
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
