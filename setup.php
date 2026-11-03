<?php
// One-Click Database & System Setup Script for AL FATIHA INSTITUTE (afidb)

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$error = '';

if (isset($_POST['run_setup'])) {
    try {
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'afidb';

        // 1. Connect to MySQL host
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 2. Create Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");

        // 3. Create Tables
        
        // Users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `role` ENUM('admin', 'teacher', 'parent', 'accountant') NOT NULL,
            `full_name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;");
        
        // Ensure role column supports accountant
        try {
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'teacher', 'parent', 'accountant') NOT NULL;");
        } catch (Exception $e) {}

        // Accounts Transactions table (Income & Expense Ledger)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `accounts_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transaction_no` VARCHAR(30) NOT NULL UNIQUE,
            `type` ENUM('Income', 'Expense') NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `transaction_date` DATE NOT NULL,
            `payment_method` VARCHAR(50) DEFAULT 'Cash',
            `reference_no` VARCHAR(100) DEFAULT NULL,
            `voucher_file` VARCHAR(255) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `created_by` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;");

        // Classes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `classes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `class_name` VARCHAR(50) NOT NULL,
            `age_group` VARCHAR(50) NOT NULL,
            `section` VARCHAR(10) DEFAULT 'A',
            `room_no` VARCHAR(20) DEFAULT '101',
            `tuition_fee` DECIMAL(10,2) NOT NULL DEFAULT 3500.00
        ) ENGINE=InnoDB;");

        // Teachers table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `teachers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT DEFAULT NULL,
            `teacher_code` VARCHAR(20) NOT NULL UNIQUE,
            `full_name` VARCHAR(100) NOT NULL,
            `qualification` VARCHAR(150) NOT NULL,
            `specialty` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `joining_date` DATE DEFAULT NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;");

        // Students table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` VARCHAR(20) NOT NULL UNIQUE,
            `first_name` VARCHAR(50) NOT NULL,
            `last_name` VARCHAR(50) NOT NULL,
            `gender` ENUM('Male', 'Female') NOT NULL,
            `dob` DATE NOT NULL,
            `class_id` INT NOT NULL,
            `roll_no` INT NOT NULL,
            `parent_user_id` INT DEFAULT NULL,
            `father_name` VARCHAR(100) NOT NULL,
            `mother_name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `address` TEXT NOT NULL,
            `status` ENUM('Active', 'Graduated', 'Suspended') DEFAULT 'Active',
            `admission_date` DATE DEFAULT NULL,
            FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Subjects table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `subject_code` VARCHAR(20) NOT NULL,
            `subject_name` VARCHAR(100) NOT NULL,
            `class_id` INT NOT NULL,
            `teacher_id` INT DEFAULT NULL,
            `total_marks` INT DEFAULT 100,
            `pass_marks` INT DEFAULT 40,
            FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Admissions Online Applications table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `application_no` VARCHAR(30) NOT NULL UNIQUE,
            `child_name` VARCHAR(100) NOT NULL,
            `gender` ENUM('Male', 'Female') NOT NULL,
            `dob` DATE NOT NULL,
            `target_class_id` INT NOT NULL,
            `father_name` VARCHAR(100) NOT NULL,
            `mother_name` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `address` TEXT NOT NULL,
            `previous_school` VARCHAR(150) DEFAULT NULL,
            `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`target_class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Attendance table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `class_id` INT NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
            `remarks` VARCHAR(150) DEFAULT NULL,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Results / Exam Grades table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `results` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `subject_id` INT NOT NULL,
            `exam_term` ENUM('First Term', 'Mid Term', 'Final Exam') NOT NULL,
            `marks_obtained` DECIMAL(5,2) NOT NULL,
            `max_marks` DECIMAL(5,2) DEFAULT 100.00,
            `teacher_remarks` VARCHAR(255) DEFAULT 'Good performance',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Fees Invoices table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `fees` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_no` VARCHAR(30) NOT NULL UNIQUE,
            `student_id` INT NOT NULL,
            `fee_type` VARCHAR(100) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `due_date` DATE NOT NULL,
            `status` ENUM('Unpaid', 'Paid', 'Partial') DEFAULT 'Unpaid',
            `paid_date` DATE DEFAULT NULL,
            `payment_method` VARCHAR(50) DEFAULT NULL,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;");

        // Notices table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'General',
            `content` TEXT NOT NULL,
            `target` ENUM('all', 'parent', 'teacher') DEFAULT 'all',
            `published_date` DATE NOT NULL
        ) ENGINE=InnoDB;");

        // Inquiries table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `inquiries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `subject` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('Unread', 'Read') DEFAULT 'Unread',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;");

        // 4. Populate Default Seed Data
        
        // Seed Classes
        $pdo->exec("INSERT IGNORE INTO `classes` (`id`, `class_name`, `age_group`, `section`, `room_no`, `tuition_fee`) VALUES
        (1, 'Play Group', '2.5 - 3.5 Years', 'A', '101', 3500.00),
        (2, 'Nursery', '3.5 - 4.5 Years', 'A', '102', 3800.00),
        (3, 'KG (Kindergarten)', '4.5 - 5.5 Years', 'A', '103', 4000.00),
        (4, 'Year One', '5.5 - 6.5 Years', 'A', '201', 4500.00),
        (5, 'Year Two', '6.5 - 7.5 Years', 'A', '202', 4800.00);");

        // Seed Users (Admin, Teacher, Parent, Accountant)
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $teacherPass = password_hash('teacher123', PASSWORD_BCRYPT);
        $parentPass = password_hash('parent123', PASSWORD_BCRYPT);
        $accountantPass = password_hash('accountant123', PASSWORD_BCRYPT);

        $pdo->exec("INSERT IGNORE INTO `users` (`id`, `username`, `password`, `email`, `role`, `full_name`, `phone`) VALUES
        (1, 'admin', '$adminPass', 'admin@alfatihainstitute.edu.bd', 'admin', 'Principal Admin', '+880 1800-000000'),
        (2, 'teacher', '$teacherPass', 'teacher@alfatihainstitute.edu.bd', 'teacher', 'Ustadh Ahmad Rahman', '+880 1811-111111'),
        (3, 'parent', '$parentPass', 'parent@alfatihainstitute.edu.bd', 'parent', 'Mohammad Tariqul Islam', '+880 1822-222222'),
        (4, 'accountant', '$accountantPass', 'accountant@alfatihainstitute.edu.bd', 'accountant', 'Shahidul Alam (Chief Accountant)', '+880 1833-333333');");

        // Seed Accounts Transactions
        $today = date('Y-m-d');
        $prevMonth = date('Y-m-d', strtotime('-1 month'));
        $twoMonthsAgo = date('Y-m-d', strtotime('-2 month'));

        $pdo->exec("INSERT IGNORE INTO `accounts_transactions` (`id`, `transaction_no`, `type`, `category`, `title`, `amount`, `transaction_date`, `payment_method`, `reference_no`, `voucher_file`, `description`, `created_by`) VALUES
        (1, 'TXN-2025-001', 'Income', 'Student Tuition & Fees', 'Monthly Tuition Fees Collection', 145000.00, '$today', 'bKash / Bank', 'REF-OCT-FEES', NULL, 'Total student monthly tuition fee received for current term.', 4),
        (2, 'TXN-2025-002', 'Income', 'Admission Form & Registration', 'New Session Admission Form Sales', 35000.00, '$today', 'Cash', 'ADM-FORM-101', NULL, 'Collected from 35 new applicants.', 4),
        (3, 'TXN-2025-003', 'Income', 'Donations & Grants', 'Islamic Trust Education Grant', 100000.00, '$prevMonth', 'Bank Transfer', 'TRF-GRANT-99', NULL, 'Special grant for Cambridge Quran & Tajweed lab.', 4),
        (4, 'TXN-2025-004', 'Expense', 'Staff Salaries & Honorarium', 'Faculty & Staff Monthly Payroll', 85000.00, '$today', 'Bank Transfer', 'PAY-2025-10', NULL, 'Monthly salary disbursement for teachers and support staff.', 4),
        (5, 'TXN-2025-005', 'Expense', 'Electricity & Utilities', 'Electricity & Internet Bill Payment', 14200.00, '$today', 'bKash', 'BILL-ELEC-402', NULL, 'PDB monthly power bill & high-speed fiber internet.', 4),
        (6, 'TXN-2025-006', 'Expense', 'Office Stationery & Supplies', 'Cambridge Early Years Workbooks & Printing', 18500.00, '$prevMonth', 'Cash', 'VOUCH-MEMO-88', NULL, 'Purchased 200 sets of phonics workbooks and printing paper.', 4),
        (7, 'TXN-2025-007', 'Expense', 'Building Rent & Maintenance', 'Campus Maintenance & AC Service', 12000.00, '$twoMonthsAgo', 'Cash', 'MAINT-V-001', NULL, 'Servicing air conditioners in classrooms and electrical repair.', 4);");

        // Seed Teacher Record
        $pdo->exec("INSERT IGNORE INTO `teachers` (`id`, `user_id`, `teacher_code`, `full_name`, `qualification`, `specialty`, `email`, `phone`, `joining_date`) VALUES
        (1, 2, 'TCH-2025-01', 'Ustadh Ahmad Rahman', 'M.A. in Arabic & Islamic Studies (Al-Azhar), B.Ed', 'Arabic, Tajweed & Islamic Studies', 'teacher@alfatihainstitute.edu.bd', '+880 1811-111111', '2025-01-01');");

        // Seed Subjects
        $pdo->exec("INSERT IGNORE INTO `subjects` (`id`, `subject_code`, `subject_name`, `class_id`, `teacher_id`, `total_marks`, `pass_marks`) VALUES
        (1, 'ENG-P1', 'Cambridge Literacy & Phonics', 1, 1, 100, 40),
        (2, 'ARA-P1', 'Arabic Vocabulary & Tajweed', 1, 1, 100, 40),
        (3, 'MAT-P1', 'Early Numeracy & Shapes', 1, 1, 100, 40),
        (4, 'ENG-Y1', 'Cambridge Primary English', 4, 1, 100, 40),
        (5, 'MAT-Y1', 'Cambridge Primary Mathematics', 4, 1, 100, 40),
        (6, 'SCI-Y1', 'Cambridge Primary Science', 4, 1, 100, 40),
        (7, 'ISL-Y1', 'Islamic Studies & Hifz', 4, 1, 100, 40);");

        // Seed Demo Student
        $pdo->exec("INSERT IGNORE INTO `students` (`id`, `student_id`, `first_name`, `last_name`, `gender`, `dob`, `class_id`, `roll_no`, `parent_user_id`, `father_name`, `mother_name`, `phone`, `address`, `status`, `admission_date`) VALUES
        (1, 'AFI-2025-001', 'Abdullah', 'Al-Mamun', 'Male', '2019-05-12', 4, 1, 3, 'Mohammad Tariqul Islam', 'Fatima Khatun', '+880 1822-222222', 'Block-C, Main Road, Cox\'s Bazar', 'Active', '2025-01-10');");

        // Seed Demo Attendance
        $pdo->exec("INSERT IGNORE INTO `attendance` (`id`, `student_id`, `class_id`, `attendance_date`, `status`, `remarks`) VALUES
        (1, 1, 4, CURRENT_DATE(), 'Present', 'On time');");

        // Seed Demo Result
        $pdo->exec("INSERT IGNORE INTO `results` (`id`, `student_id`, `subject_id`, `exam_term`, `marks_obtained`, `max_marks`, `teacher_remarks`) VALUES
        (1, 1, 4, 'Mid Term', 92.50, 100.00, 'Outstanding English reading & phonics performance'),
        (2, 1, 7, 'Mid Term', 98.00, 100.00, 'Excellent recitation of Surah Al-Fatiha & Duas');");

        // Seed Demo Fee Invoice
        $pdo->exec("INSERT IGNORE INTO `fees` (`id`, `invoice_no`, `student_id`, `fee_type`, `amount`, `due_date`, `status`, `paid_date`, `payment_method`) VALUES
        (1, 'INV-2025-101', 1, 'Monthly Tuition Fee - October', 4500.00, '2025-10-10', 'Paid', '2025-10-05', 'bKash Online');");

        // Seed Demo Notices
        $pdo->exec("INSERT IGNORE INTO `notices` (`id`, `title`, `category`, `content`, `target`, `published_date`) VALUES
        (1, 'Admission Open for Session 2026', 'Admission', 'Admissions are now open for Play Group, Nursery, KG, Year 1 and Year 2 for the academic session 2026. Limited seats available.', 'all', CURRENT_DATE()),
        (2, 'Annual Quran Recitation Competition', 'Event', 'The Annual Quran Recitation & Tajweed Competition for students will be held on Thursday next week.', 'all', CURRENT_DATE());");

        $message = "Database 'afidb' and all tables & sample data created successfully!";
    } catch (Exception $ex) {
        $error = "Setup failed: " . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Setup & Installer | AL FATIHA INSTITUTE</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #032d4e; color: #1e293b; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 1rem; }
    .setup-card { background: #ffffff; border-radius: 20px; width: 100%; max-width: 650px; padding: 2.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center; }
    .setup-card img { height: 90px; margin-bottom: 1rem; }
    h1 { font-family: 'Outfit', sans-serif; color: #064878; font-size: 1.8rem; margin-bottom: 0.5rem; }
    p { font-size: 0.95rem; color: #64748b; line-height: 1.6; }
    .btn-setup { background: #f3b527; color: #032d4e; font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.1rem; padding: 0.9rem 2rem; border: none; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; }
    .btn-setup:hover { background: #cd8707; color: #ffffff; transform: translateY(-2px); }
    .alert { padding: 1rem; border-radius: 10px; margin: 1.5rem 0; font-weight: 600; text-align: left; }
    .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .creds-table { width: 100%; margin-top: 1.5rem; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
    .creds-table th, .creds-table td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; }
    .creds-table th { background: #f8fafc; color: #64748b; }
  </style>
</head>
<body>

  <div class="setup-card">
    <img src="assets/images/logo.svg" alt="AL FATIHA INSTITUTE Logo">
    <h1>AL FATIHA INSTITUTE</h1>
    <p>Database Installer & System Initializer (MySQL: <strong>afidb</strong>)</p>

    <?php if ($message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $message ?>
      </div>

      <div style="text-align: left; margin-top: 1.5rem;">
        <h3 style="font-family: 'Outfit', sans-serif; color: #064878;">Default System Credentials:</h3>
        <table class="creds-table">
          <thead>
            <tr><th>Portal / Role</th><th>Username</th><th>Password</th><th>Link</th></tr>
          </thead>
          <tbody>
            <tr><td><strong>Admin Dashboard</strong></td><td><code>admin</code></td><td><code>admin123</code></td><td><a href="admin/index.php">Login Admin</a></td></tr>
            <tr><td><strong>Teacher Portal</strong></td><td><code>teacher</code></td><td><code>teacher123</code></td><td><a href="teacher/index.php">Login Teacher</a></td></tr>
            <tr><td><strong>Parent / Student Portal</strong></td><td><code>parent</code></td><td><code>parent123</code></td><td><a href="portal/index.php">Login Parent</a></td></tr>
            <tr><td><strong>Accountant Module</strong></td><td><code>accountant</code></td><td><code>accountant123</code></td><td><a href="login.php">Login Accountant</a></td></tr>
          </tbody>
        </table>
        <div style="margin-top: 2rem; text-align: center;">
          <a href="index.php" class="btn-setup" style="text-decoration: none; display: inline-block;">Go to School Website</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <?php if (!$message): ?>
      <form method="POST" style="margin-top: 2rem;">
        <button type="submit" name="run_setup" class="btn-setup">
          <i class="fas fa-cogs"></i> Initialize Database & Create Sample Data
        </button>
      </form>
    <?php endif; ?>
  </div>

</body>
</html>
