<?php
/**
 * HR, Employee Directory, ID Prefix Generator, Attendance, Leave & Payroll Helpers
 * Formats:
 *   Principal         -> AFI-PR-001
 *   Teacher           -> AFI-TC-001
 *   Assistant Teacher -> AFI-AT-001
 *   HR Officer        -> AFI-HR-001
 *   Accountant        -> AFI-AC-001
 *   Admin Staff       -> AFI-AD-001
 *   Support Staff     -> AFI-ST-001
 */

function hr_role_definitions() {
    return [
        'principal' => [
            'prefix'      => 'AFI-PR-',
            'label'       => 'Principal',
            'department'  => 'Leadership',
            'user_role'   => 'principal',
            'is_teacher'  => true,
        ],
        'teacher' => [
            'prefix'      => 'AFI-TC-',
            'label'       => 'Teacher',
            'department'  => 'Academic',
            'user_role'   => 'teacher',
            'is_teacher'  => true,
        ],
        'assistant_teacher' => [
            'prefix'      => 'AFI-AT-',
            'label'       => 'Assistant Teacher',
            'department'  => 'Academic',
            'user_role'   => 'teacher',
            'is_teacher'  => true,
        ],
        'hr' => [
            'prefix'      => 'AFI-HR-',
            'label'       => 'HR Manager / Officer',
            'department'  => 'Human Resources',
            'user_role'   => 'hr',
            'is_teacher'  => false,
        ],
        'accountant' => [
            'prefix'      => 'AFI-AC-',
            'label'       => 'Accountant',
            'department'  => 'Finance & Accounts',
            'user_role'   => 'accountant',
            'is_teacher'  => false,
        ],
        'admin' => [
            'prefix'      => 'AFI-AD-',
            'label'       => 'Admin / Office Executive',
            'department'  => 'Administration',
            'user_role'   => 'admin',
            'is_teacher'  => false,
        ],
        'support_staff' => [
            'prefix'      => 'AFI-ST-',
            'label'       => 'Support Staff',
            'department'  => 'Support & Operations',
            'user_role'   => null,
            'is_teacher'  => false,
        ],
    ];
}

function generate_employee_code($roleType) {
    $db = get_db_connection();
    $defs = hr_role_definitions();
    $roleKey = isset($defs[$roleType]) ? $roleType : 'support_staff';
    $prefix = $defs[$roleKey]['prefix'];

    $used = [];
    foreach (['employees' => 'employee_code', 'employee_role_history' => 'employee_code', 'teachers' => 'teacher_code'] as $tbl => $col) {
        try {
            $st = $db->prepare("SELECT `$col` FROM `$tbl` WHERE `$col` LIKE ?");
            $st->execute([$prefix . '%']);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $code) {
                $num = substr($code, strlen($prefix));
                if (is_numeric($num)) {
                    $used[intval($num)] = true;
                }
            }
        } catch (Exception $e) {}
    }

    $next = empty($used) ? 1 : (max(array_keys($used)) + 1);
    while (isset($used[$next])) {
        $next++;
    }
    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function record_employee_role_history($employeePk, $employeeCode, $roleType, $designation, $effectiveFrom = null, $notes = null) {
    $db = get_db_connection();
    $chk = $db->prepare("SELECT id FROM employee_role_history WHERE employee_code = ? LIMIT 1");
    $chk->execute([$employeeCode]);
    if ($chk->fetch()) return;

    $dateVal = $effectiveFrom ?: date('Y-m-d');
    $db->prepare("INSERT INTO employee_role_history (employee_pk, employee_code, role_type, designation, effective_from, notes) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([intval($employeePk), $employeeCode, $roleType, $designation, $dateVal, $notes]);
}

function get_employee_by_user_id($userId) {
    if (!$userId) return null;
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM employees WHERE user_id = ? LIMIT 1");
    $st->execute([intval($userId)]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Create or update the linked teachers record when an employee is Teacher / Assistant Teacher / Principal.
 */
function sync_teacher_row_from_employee($employeeId) {
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
    $st->execute([intval($employeeId)]);
    $emp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$emp) return null;

    $teachingRoles = ['teacher', 'assistant_teacher', 'principal'];
    $defs = hr_role_definitions();
    $tStatus = ($emp['status'] === 'Active' || $emp['status'] === 'On Leave') ? 'Active' : 'Inactive';
    $qual = $emp['qualification'] ?: ($defs[$emp['role_type']]['label'] ?? 'Faculty');
    $spec = $emp['specialty'] ?: ($emp['designation'] ?: 'General Studies');
    $email = $emp['email'] ?: (strtolower(str_replace('-', '', $emp['employee_code'])) . '@alfatiha.edu.bd');
    $phone = $emp['phone'] ?: 'N/A';

    if (!empty($emp['teacher_id'])) {
        $db->prepare("UPDATE teachers SET user_id = ?, employee_id = ?, teacher_code = ?, designation = ?, full_name = ?, qualification = ?, specialty = ?, email = ?, phone = ?, joining_date = ?, status = ? WHERE id = ?")
           ->execute([
               $emp['user_id'],
               $emp['id'],
               $emp['employee_code'],
               $emp['designation'],
               $emp['full_name'],
               $qual,
               $spec,
               $email,
               $phone,
               $emp['joining_date'],
               $tStatus,
               $emp['teacher_id']
           ]);
        return intval($emp['teacher_id']);
    }

    if (in_array($emp['role_type'], $teachingRoles, true)) {
        $db->prepare("INSERT INTO teachers (user_id, employee_id, teacher_code, designation, full_name, qualification, specialty, email, phone, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([
               $emp['user_id'],
               $emp['id'],
               $emp['employee_code'],
               $emp['designation'],
               $emp['full_name'],
               $qual,
               $spec,
               $email,
               $phone,
               $emp['joining_date'],
               $tStatus
           ]);
        $tId = intval($db->lastInsertId());
        $db->prepare("UPDATE employees SET teacher_id = ? WHERE id = ?")->execute([$tId, $emp['id']]);
        return $tId;
    }

    return null;
}

/**
 * Safely delete an employee (and optionally linked teacher/user account).
 * If payroll slips already exist, sets status to 'Resigned' instead of destroying financial audit trail unless forced.
 */
function safe_delete_employee($employeePk, $forcePurge = false) {
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
    $st->execute([intval($employeePk)]);
    $emp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        return ['success' => false, 'message' => 'Employee record not found.'];
    }

    $slipCnt = intval($db->query("SELECT COUNT(*) FROM payroll_slips WHERE employee_pk = " . intval($emp['id']))->fetchColumn());
    if ($slipCnt > 0 && !$forcePurge) {
        $db->prepare("UPDATE employees SET status = 'Resigned' WHERE id = ?")->execute([$emp['id']]);
        if (!empty($emp['teacher_id'])) {
            $db->prepare("UPDATE teachers SET status = 'Resigned' WHERE id = ?")->execute([$emp['teacher_id']]);
        }
        if (!empty($emp['user_id'])) {
            $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role != 'super_admin'")->execute([$emp['user_id']]);
        }
        return [
            'success' => true,
            'message' => htmlspecialchars($emp['full_name']) . " has existing payroll records, so status was set to <strong>Resigned</strong> and portal access deactivated to preserve audit history."
        ];
    }

    try {
        $db->beginTransaction();

        if (!empty($emp['teacher_id'])) {
            $tid = intval($emp['teacher_id']);
            $db->prepare("UPDATE classes SET primary_teacher_id = NULL WHERE primary_teacher_id = ?")->execute([$tid]);
            $db->prepare("UPDATE classes SET backup_teacher_id = NULL WHERE backup_teacher_id = ?")->execute([$tid]);
            $db->prepare("UPDATE subjects SET teacher_id = NULL WHERE teacher_id = ?")->execute([$tid]);
            $db->prepare("DELETE FROM teacher_attendance WHERE teacher_id = ?")->execute([$tid]);
            $db->prepare("DELETE FROM teachers WHERE id = ?")->execute([$tid]);
        }

        $db->prepare("DELETE FROM employees WHERE id = ?")->execute([$emp['id']]);

        if (!empty($emp['user_id'])) {
            $db->prepare("DELETE FROM users WHERE id = ? AND id != 1 AND role != 'super_admin'")->execute([$emp['user_id']]);
        }

        $db->commit();
        return ['success' => true, 'message' => "Employee <strong>" . htmlspecialchars($emp['full_name']) . "</strong> (<code>" . htmlspecialchars($emp['employee_code']) . "</code>) removed cleanly."];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => 'Failed to delete employee: ' . $e->getMessage()];
    }
}

/**
 * Safely delete a teacher by teachers.id (cleans up class/subject assignments and linked employee/user).
 */
function safe_delete_teacher_by_id($teacherId) {
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
    $st->execute([intval($teacherId)]);
    $tch = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tch) {
        return ['success' => false, 'message' => 'Teacher not found.'];
    }
    if (!empty($tch['employee_id'])) {
        return safe_delete_employee($tch['employee_id'], false);
    }

    try {
        $db->beginTransaction();
        $tid = intval($tch['id']);
        $db->prepare("UPDATE classes SET primary_teacher_id = NULL WHERE primary_teacher_id = ?")->execute([$tid]);
        $db->prepare("UPDATE classes SET backup_teacher_id = NULL WHERE backup_teacher_id = ?")->execute([$tid]);
        $db->prepare("UPDATE subjects SET teacher_id = NULL WHERE teacher_id = ?")->execute([$tid]);
        $db->prepare("DELETE FROM teacher_attendance WHERE teacher_id = ?")->execute([$tid]);
        $db->prepare("DELETE FROM teachers WHERE id = ?")->execute([$tid]);
        if (!empty($tch['user_id'])) {
            $db->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'")->execute([$tch['user_id']]);
        }
        $db->commit();
        return ['success' => true, 'message' => "Teacher <strong>" . htmlspecialchars($tch['full_name']) . "</strong> deleted cleanly."];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => 'Failed to delete teacher: ' . $e->getMessage()];
    }
}

/**
 * Calculate inclusive calendar days between two dates.
 */
function hr_count_leave_days($startDate, $endDate) {
    try {
        $d1 = new DateTime($startDate);
        $d2 = new DateTime($endDate);
        if ($d2 < $d1) return 0;
        return intval($d1->diff($d2)->days) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

/**
 * Approve or Reject a leave request. When Approved, marks employee_attendance rows as 'On Leave'.
 */
function hr_review_leave_request($leaveId, $decision, $reviewerUserId, $notes = '') {
    $db = get_db_connection();
    $st = $db->prepare("SELECT lr.*, lt.name AS leave_type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.id WHERE lr.id = ? LIMIT 1");
    $st->execute([intval($leaveId)]);
    $req = $st->fetch(PDO::FETCH_ASSOC);
    if (!$req) return ['success' => false, 'message' => 'Leave request not found.'];
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        return ['success' => false, 'message' => 'Invalid review decision.'];
    }

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?")
           ->execute([$decision, intval($reviewerUserId), $notes, intval($leaveId)]);

        if ($decision === 'Approved') {
            $cur = new DateTime($req['start_date']);
            $end = new DateTime($req['end_date']);
            $end->modify('+1 day');
            $stmtAtt = $db->prepare("
                INSERT INTO employee_attendance (employee_pk, attendance_date, status, remarks, marked_by)
                VALUES (?, ?, 'On Leave', ?, ?)
                ON DUPLICATE KEY UPDATE status = 'On Leave', remarks = VALUES(remarks), marked_by = VALUES(marked_by)
            ");
            $rem = 'Approved Leave: ' . $req['leave_type_name'];
            while ($cur < $end) {
                $dStr = $cur->format('Y-m-d');
                $stmtAtt->execute([$req['employee_pk'], $dStr, $rem, intval($reviewerUserId)]);
                $cur->modify('+1 day');
            }
        }
        $db->commit();
        return ['success' => true, 'message' => "Leave request #{$leaveId} marked as <strong>{$decision}</strong>."];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => 'Failed to review leave: ' . $e->getMessage()];
    }
}

/**
 * Compute attendance & approved leave counts for an employee in YYYY-MM month.
 */
function hr_get_monthly_attendance_summary($employeePk, $monthYm) {
    $db = get_db_connection();
    $monthStart = $monthYm . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $present = 0;
    $absent = 0;
    $late = 0;
    $halfDay = 0;
    $paidLeave = 0;
    $unpaidLeave = 0;

    // 1) Collect approved leave dates mapped by paid vs unpaid
    $paidLeaveDates = [];
    $unpaidLeaveDates = [];
    $lrStmt = $db->prepare("
        SELECT lr.start_date, lr.end_date, lt.is_paid
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_pk = ? AND lr.status = 'Approved'
          AND lr.start_date <= ? AND lr.end_date >= ?
    ");
    $lrStmt->execute([intval($employeePk), $monthEnd, $monthStart]);
    foreach ($lrStmt->fetchAll(PDO::FETCH_ASSOC) as $lr) {
        $d = new DateTime(max($lr['start_date'], $monthStart));
        $e = new DateTime(min($lr['end_date'], $monthEnd));
        $e->modify('+1 day');
        while ($d < $e) {
            $ds = $d->format('Y-m-d');
            if ((int)$lr['is_paid'] === 1) {
                $paidLeaveDates[$ds] = true;
            } else {
                $unpaidLeaveDates[$ds] = true;
            }
            $d->modify('+1 day');
        }
    }

    // 2) Attendance rows for month
    $attStmt = $db->prepare("SELECT attendance_date, status FROM employee_attendance WHERE employee_pk = ? AND attendance_date BETWEEN ? AND ?");
    $attStmt->execute([intval($employeePk), $monthStart, $monthEnd]);
    $attByDate = [];
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attByDate[$row['attendance_date']] = $row['status'];
    }

    foreach ($attByDate as $dStr => $status) {
        if ($status === 'Present') {
            $present++;
        } elseif ($status === 'Late') {
            $late++;
            $present++;
        } elseif ($status === 'Half-Day') {
            $halfDay++;
        } elseif ($status === 'Absent') {
            $absent++;
        } elseif ($status === 'On Leave') {
            if (isset($unpaidLeaveDates[$dStr])) {
                $unpaidLeave++;
            } else {
                $paidLeave++;
            }
        }
    }

    // Also include approved unpaid/paid leave days even if attendance row wasn't explicitly inserted
    foreach ($paidLeaveDates as $dStr => $_) {
        if (!isset($attByDate[$dStr])) $paidLeave++;
    }
    foreach ($unpaidLeaveDates as $dStr => $_) {
        if (!isset($attByDate[$dStr])) $unpaidLeave++;
    }

    return [
        'present_days'      => $present,
        'absent_days'       => $absent,
        'late_days'         => $late,
        'half_days'         => $halfDay,
        'paid_leave_days'   => $paidLeave,
        'unpaid_leave_days' => $unpaidLeave,
    ];
}

/**
 * Generate or refresh a Monthly Payroll Run & Slips for all Active / On-Leave employees.
 * - Unpaid Absent + Unpaid Leave days are deducted automatically.
 * - Suggested Late Deduction: floor(late_days / late_threshold) days (default 3 lates = 1 day).
 * - HR can later manually adjust or waive late_deduction / bonus / other_deduction before paying!
 */
function hr_generate_monthly_payroll($monthYm, $workingDays = 26, $lateThreshold = 3, $generatedByUserId = null) {
    $db = get_db_connection();
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYm)) {
        return ['success' => false, 'message' => 'Invalid month format. Use YYYY-MM.'];
    }
    $workingDays = max(1, intval($workingDays));
    $lateThreshold = max(1, intval($lateThreshold));
    $monthLabel = date('F Y', strtotime($monthYm . '-01'));

    try {
        $db->beginTransaction();

        $runStmt = $db->prepare("SELECT * FROM payroll_runs WHERE payroll_month = ? LIMIT 1");
        $runStmt->execute([$monthYm]);
        $run = $runStmt->fetch(PDO::FETCH_ASSOC);

        if (!$run) {
            $db->prepare("INSERT INTO payroll_runs (payroll_month, month_label, working_days, late_threshold, status, generated_by) VALUES (?, ?, ?, ?, 'Draft', ?)")
               ->execute([$monthYm, $monthLabel, $workingDays, $lateThreshold, $generatedByUserId]);
            $runId = intval($db->lastInsertId());
        } else {
            $runId = intval($run['id']);
            if ($run['status'] === 'Paid') {
                $db->rollBack();
                return ['success' => false, 'message' => "Payroll for <strong>$monthLabel</strong> is already marked Paid."];
            }
            $db->prepare("UPDATE payroll_runs SET working_days = ?, late_threshold = ? WHERE id = ?")
               ->execute([$workingDays, $lateThreshold, $runId]);
        }

        $emps = $db->query("SELECT * FROM employees WHERE status IN ('Active', 'On Leave') ORDER BY employee_code ASC")->fetchAll(PDO::FETCH_ASSOC);
        $created = 0;
        $refreshed = 0;

        foreach ($emps as $emp) {
            $basic = round(floatval($emp['basic_salary']), 2);
            $allow = round(floatval($emp['house_rent']) + floatval($emp['medical_allowance']) + floatval($emp['transport_allowance']) + floatval($emp['other_allowance']), 2);
            $gross = round($basic + $allow, 2);
            $dailyRate = $workingDays > 0 ? ($gross / $workingDays) : 0;

            $att = hr_get_monthly_attendance_summary($emp['id'], $monthYm);
            $unpaidDays = $att['absent_days'] + $att['unpaid_leave_days'] + ($att['half_days'] * 0.5);
            $unpaidDed = round($unpaidDays * $dailyRate, 2);

            $suggestedLateDeductDays = intdiv($att['late_days'], $lateThreshold);
            $suggestedLateDed = round($suggestedLateDeductDays * $dailyRate, 2);

            $exStmt = $db->prepare("SELECT * FROM payroll_slips WHERE payroll_month = ? AND employee_pk = ? LIMIT 1");
            $exStmt->execute([$monthYm, $emp['id']]);
            $existing = $exStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['payment_status'] === 'Paid') {
                continue; // Never overwrite already-paid slip
            }

            if ($existing) {
                // Keep manual HR override if HR changed adjustment_note, otherwise refresh attendance numbers cleanly
                $appliedLateDays = $suggestedLateDeductDays;
                $lateDed = $suggestedLateDed;
                if (!empty($existing['adjustment_note']) && strpos($existing['adjustment_note'], '[MANUAL]') !== false) {
                    $appliedLateDays = intval($existing['applied_late_deduct_days']);
                    $lateDed = floatval($existing['late_deduction']);
                }
                $bonus = floatval($existing['bonus_amount']);
                $otherDed = floatval($existing['other_deduction']);
                $net = max(0, round($gross + $bonus - $unpaidDed - $lateDed - $otherDed, 2));

                $db->prepare("UPDATE payroll_slips SET
                    employee_code_snapshot = ?, employee_name_snapshot = ?, designation_snapshot = ?,
                    basic_salary = ?, allowances = ?, gross_salary = ?, working_days = ?,
                    present_days = ?, paid_leave_days = ?, absent_days = ?, unpaid_leave_days = ?, late_days = ?,
                    suggested_late_deduct_days = ?, applied_late_deduct_days = ?,
                    unpaid_deduction = ?, late_deduction = ?, net_payable = ?, payment_method = ?
                    WHERE id = ?")
                   ->execute([
                       $emp['employee_code'], $emp['full_name'], $emp['designation'],
                       $basic, $allow, $gross, $workingDays,
                       $att['present_days'], $att['paid_leave_days'], $att['absent_days'], $att['unpaid_leave_days'], $att['late_days'],
                       $suggestedLateDeductDays, $appliedLateDays,
                       $unpaidDed, $lateDed, $net, $emp['payment_method'] ?: 'Cash',
                       $existing['id']
                   ]);
                $refreshed++;
            } else {
                $slipNo = 'PAY-' . str_replace('-', '', $monthYm) . '-' . str_pad((string)$emp['id'], 3, '0', STR_PAD_LEFT);
                $net = max(0, round($gross - $unpaidDed - $suggestedLateDed, 2));
                $db->prepare("INSERT INTO payroll_slips (
                    slip_no, payroll_run_id, payroll_month, employee_pk,
                    employee_code_snapshot, employee_name_snapshot, designation_snapshot,
                    basic_salary, allowances, gross_salary, working_days,
                    present_days, paid_leave_days, absent_days, unpaid_leave_days, late_days,
                    suggested_late_deduct_days, applied_late_deduct_days,
                    unpaid_deduction, late_deduction, other_deduction, bonus_amount, net_payable, payment_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)")
                   ->execute([
                       $slipNo, $runId, $monthYm, $emp['id'],
                       $emp['employee_code'], $emp['full_name'], $emp['designation'],
                       $basic, $allow, $gross, $workingDays,
                       $att['present_days'], $att['paid_leave_days'], $att['absent_days'], $att['unpaid_leave_days'], $att['late_days'],
                       $suggestedLateDeductDays, $suggestedLateDeductDays,
                       $unpaidDed, $suggestedLateDed, $net, $emp['payment_method'] ?: 'Cash'
                   ]);
                $created++;
            }
        }

        $db->commit();
        return ['success' => true, 'message' => "Payroll sheet for <strong>$monthLabel</strong> generated! ($created new, $refreshed refreshed)"];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => 'Failed to generate payroll: ' . $e->getMessage()];
    }
}

/**
 * Allow HR to manually adjust or waive late deduction, bonus, or other deduction on an Unpaid slip.
 */
function hr_update_payroll_slip_adjustments($slipId, $appliedLateDays, $lateDeductionAmount, $bonusAmount, $otherDeduction, $note = '') {
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM payroll_slips WHERE id = ? LIMIT 1");
    $st->execute([intval($slipId)]);
    $slip = $st->fetch(PDO::FETCH_ASSOC);
    if (!$slip) return ['success' => false, 'message' => 'Payslip not found.'];
    if ($slip['payment_status'] === 'Paid') {
        return ['success' => false, 'message' => 'Cannot modify a payslip that is already Paid.'];
    }

    $appliedLateDays = max(0, intval($appliedLateDays));
    $lateDed = max(0, round(floatval($lateDeductionAmount), 2));
    $bonus = max(0, round(floatval($bonusAmount), 2));
    $otherDed = max(0, round(floatval($otherDeduction), 2));
    $unpaidDed = floatval($slip['unpaid_deduction']);
    $gross = floatval($slip['gross_salary']);
    $net = max(0, round($gross + $bonus - $unpaidDed - $lateDed - $otherDed, 2));

    $taggedNote = trim($note);
    if (strpos($taggedNote, '[MANUAL]') === false) {
        $taggedNote = $taggedNote !== '' ? '[MANUAL] ' . $taggedNote : '[MANUAL] Adjusted by HR';
    }

    $db->prepare("UPDATE payroll_slips SET applied_late_deduct_days = ?, late_deduction = ?, bonus_amount = ?, other_deduction = ?, net_payable = ?, adjustment_note = ? WHERE id = ?")
       ->execute([$appliedLateDays, $lateDed, $bonus, $otherDed, $net, $taggedNote, intval($slipId)]);

    return ['success' => true, 'message' => "Payslip <code>" . htmlspecialchars($slip['slip_no']) . "</code> adjusted. Net Payable: <strong>" . format_currency($net) . "</strong>"];
}

/**
 * Mark a payslip as Paid AND automatically record an Expense entry in accounts_transactions.
 */
function hr_mark_slip_paid($slipId, $paymentMethod = 'Cash', $paidDate = null, $actorUserId = null) {
    $db = get_db_connection();
    $st = $db->prepare("SELECT * FROM payroll_slips WHERE id = ? LIMIT 1");
    $st->execute([intval($slipId)]);
    $slip = $st->fetch(PDO::FETCH_ASSOC);
    if (!$slip) return ['success' => false, 'message' => 'Payslip not found.'];
    if ($slip['payment_status'] === 'Paid') {
        return ['success' => false, 'message' => 'This payslip is already paid.'];
    }

    $pDate = $paidDate ?: date('Y-m-d');
    $pMethod = $paymentMethod ?: ($slip['payment_method'] ?: 'Cash');
    $amount = floatval($slip['net_payable']);

    try {
        $db->beginTransaction();

        $txId = $slip['expense_tx_id'];
        if (empty($txId) && $amount > 0) {
            $txnNo = 'SAL-' . date('Ym', strtotime($pDate)) . '-' . str_pad((string)$slip['id'], 4, '0', STR_PAD_LEFT);
            $monthTitle = date('M Y', strtotime($slip['payroll_month'] . '-01'));
            $title = "Salary ({$monthTitle}): {$slip['employee_name_snapshot']} ({$slip['employee_code_snapshot']})";
            $desc = "Auto-synced from HR Payroll Slip {$slip['slip_no']} | Designation: {$slip['designation_snapshot']}";

            $insTx = $db->prepare("INSERT INTO accounts_transactions (transaction_no, type, category, title, amount, transaction_date, payment_method, reference_no, description, created_by) VALUES (?, 'Expense', 'Staff Salaries & Honorarium', ?, ?, ?, ?, ?, ?, ?)");
            $insTx->execute([$txnNo, $title, $amount, $pDate, $pMethod, $slip['slip_no'], $desc, $actorUserId]);
            $txId = intval($db->lastInsertId());
        }

        $db->prepare("UPDATE payroll_slips SET payment_status = 'Paid', paid_date = ?, payment_method = ?, expense_tx_id = ? WHERE id = ?")
           ->execute([$pDate, $pMethod, $txId, intval($slipId)]);

        // If all slips in the payroll_run are Paid, update payroll_run status to Paid
        $unpaidCnt = intval($db->query("SELECT COUNT(*) FROM payroll_slips WHERE payroll_run_id = " . intval($slip['payroll_run_id']) . " AND payment_status != 'Paid'")->fetchColumn());
        if ($unpaidCnt === 0) {
            $db->prepare("UPDATE payroll_runs SET status = 'Paid' WHERE id = ?")->execute([$slip['payroll_run_id']]);
        }

        $db->commit();
        return ['success' => true, 'message' => "Salary paid for <strong>" . htmlspecialchars($slip['employee_name_snapshot']) . "</strong> &amp; synced to Expense Ledger."];
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => 'Failed to mark salary as paid: ' . $e->getMessage()];
    }
}

