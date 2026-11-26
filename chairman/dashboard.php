<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$curMonth = date('Y-m');
$today = date('Y-m-d');

// 1. Student Metrics
$stSummary = $db->query("
    SELECT COUNT(*) as total_active,
           SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) as boys,
           SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) as girls
    FROM students WHERE status = 'Active'
")->fetch(PDO::FETCH_ASSOC);
$totalStudents = intval($stSummary['total_active'] ?? 0);
$boysCount = intval($stSummary['boys'] ?? 0);
$girlsCount = intval($stSummary['girls'] ?? 0);
$totalClasses = intval($db->query("SELECT COUNT(*) FROM classes")->fetchColumn());

// 2. Staff & Workforce Metrics
$empSummary = $db->query("
    SELECT COUNT(*) as total_active,
           SUM(CASE WHEN role_type IN ('principal','teacher','assistant_teacher') THEN 1 ELSE 0 END) as academic_cnt,
           SUM(CASE WHEN role_type NOT IN ('principal','teacher','assistant_teacher') THEN 1 ELSE 0 END) as ops_cnt
    FROM employees WHERE status IN ('Active', 'On Leave')
")->fetch(PDO::FETCH_ASSOC);
$totalEmployees = intval($empSummary['total_active'] ?? 0);
$academicStaff = intval($empSummary['academic_cnt'] ?? 0);
$opsStaff = intval($empSummary['ops_cnt'] ?? 0);

// 3. Financial Metrics (Income & Expense)
$monthIncome = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Income' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$curMonth'")->fetchColumn());
$monthExpense = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$curMonth'")->fetchColumn());
$monthNet = $monthIncome - $monthExpense;

$allIncome = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Income'")->fetchColumn());
$allExpense = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Expense'")->fetchColumn());
$allNet = $allIncome - $allExpense;

$totalPendingDues = floatval($db->query("SELECT COALESCE(SUM(GREATEST(0, amount - COALESCE(paid_amount, 0))), 0) FROM fees WHERE status IN ('Unpaid', 'Partial')")->fetchColumn());

// 4. Today Staff Attendance Metrics
$attRows = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM employee_attendance
    WHERE attendance_date = '$today'
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
$todayPresent = intval($attRows['Present'] ?? 0);
$todayLate = intval($attRows['Late'] ?? 0);
$todayAbsent = intval($attRows['Absent'] ?? 0);
$todayLeave = intval($attRows['On Leave'] ?? 0);
$todayHalf = intval($attRows['Half-Day'] ?? 0);
$todayMarked = $todayPresent + $todayLate + $todayAbsent + $todayLeave + $todayHalf;

$todayAlertStaff = $db->query("
    SELECT e.employee_code, e.full_name, e.designation, ea.status, ea.remarks
    FROM employee_attendance ea
    JOIN employees e ON ea.employee_pk = e.id
    WHERE ea.attendance_date = '$today' AND ea.status IN ('Absent', 'Late', 'On Leave', 'Half-Day')
    ORDER BY ea.status ASC, e.full_name ASC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Class-wise Breakdown (Students, Tuition Potential, Pending Dues)
$classBreakdown = $db->query("
    SELECT c.id, c.class_code, c.class_name, c.tuition_fee,
           COUNT(DISTINCT CASE WHEN s.status='Active' THEN s.id END) as student_cnt,
           SUM(CASE WHEN s.status='Active' AND s.gender='Male' THEN 1 ELSE 0 END) as boys_cnt,
           SUM(CASE WHEN s.status='Active' AND s.gender='Female' THEN 1 ELSE 0 END) as girls_cnt,
           COALESCE((SELECT SUM(GREATEST(0, f.amount - COALESCE(f.paid_amount, 0))) FROM fees f JOIN students st2 ON f.student_id = st2.id WHERE st2.class_id = c.id AND f.status IN ('Unpaid', 'Partial')), 0) as class_due
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    GROUP BY c.id
    ORDER BY c.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. 6-Month Cash Flow Chart Data
$chartMonths = [];
$chartIncome = [];
$chartExpense = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i month"));
    $chartMonths[] = date('M Y', strtotime($ym . '-01'));
    $chartIncome[] = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Income' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$ym'")->fetchColumn());
    $chartExpense[] = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE type='Expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$ym'")->fetchColumn());
}

$classLabels = array_map(function($c) { return $c['class_name']; }, $classBreakdown);
$classCounts = array_map(function($c) { return intval($c['student_cnt']); }, $classBreakdown);

// Recent 6 Financial Transactions
$recentTx = $db->query("SELECT * FROM accounts_transactions ORDER BY transaction_date DESC, id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Executive Banner -->
<div class="exec-hero">
  <div style="position: relative; z-index: 1;">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;">
      <span class="exec-pill"><i class="fas fa-landmark"></i> Board of Governors &bull; Monitoring Mode</span>
      <span style="font-size: 0.82rem; opacity: 0.85;"><?= date('l, d F Y') ?></span>
    </div>
    <h1 style="font-family: 'Outfit', sans-serif; margin: 0; font-size: 1.75rem; font-weight: 800;">
      Welcome, <?= htmlspecialchars($user_name) ?>
    </h1>
    <p style="margin: 6px 0 0 0; opacity: 0.88; font-size: 0.94rem;">
      Real-time institutional overview across student strength, financial ledger, and daily employee attendance.
    </p>
  </div>
  <div style="text-align: right; position: relative; z-index: 1; background: rgba(255,255,255,0.1); padding: 14px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.18);">
    <small style="display: block; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em; color: #fde68a; font-weight: 700;">All-Time Net Fund Position</small>
    <strong style="font-size: 1.55rem; font-weight: 800; color: #ffffff;"><?= format_currency($allNet) ?></strong>
    <small style="display: block; font-size: 0.78rem; opacity: 0.85;">In: <?= format_currency($allIncome) ?> &bull; Out: <?= format_currency($allExpense) ?></small>
  </div>
</div>

<!-- Executive KPI Row -->
<div class="kpi-exec-grid">
  <a href="students.php" style="text-decoration: none; color: inherit;" class="kpi-exec-card">
    <div class="kpi-exec-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fas fa-user-graduate"></i></div>
    <small style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Active Students</small>
    <h2 style="margin: 4px 0 2px 0; font-size: 1.8rem; color: #032d4e;"><?= $totalStudents ?></h2>
    <small style="color: #0284c7; font-weight: 600;"><?= $boysCount ?> Boys &bull; <?= $girlsCount ?> Girls (<?= $totalClasses ?> Classes)</small>
  </a>

  <a href="attendance.php" style="text-decoration: none; color: inherit;" class="kpi-exec-card">
    <div class="kpi-exec-icon" style="background: #ccfbf1; color: #0f766e;"><i class="fas fa-users"></i></div>
    <small style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Active Employees</small>
    <h2 style="margin: 4px 0 2px 0; font-size: 1.8rem; color: #032d4e;"><?= $totalEmployees ?></h2>
    <small style="color: #0f766e; font-weight: 600;"><?= $academicStaff ?> Academic &bull; <?= $opsStaff ?> Admin/Support</small>
  </a>

  <a href="finance.php?type=Income" style="text-decoration: none; color: inherit;" class="kpi-exec-card">
    <div class="kpi-exec-icon" style="background: #dcfce7; color: #16a34a;"><i class="fas fa-arrow-trend-up"></i></div>
    <small style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?= date('M Y') ?> Income</small>
    <h2 style="margin: 4px 0 2px 0; font-size: 1.55rem; color: #15803d;"><?= format_currency($monthIncome) ?></h2>
    <small style="color: #64748b; font-weight: 600;">Fee &amp; Revenue Inflow</small>
  </a>

  <a href="finance.php?type=Expense" style="text-decoration: none; color: inherit;" class="kpi-exec-card">
    <div class="kpi-exec-icon" style="background: #fee2e2; color: #dc2626;"><i class="fas fa-arrow-trend-down"></i></div>
    <small style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?= date('M Y') ?> Expense</small>
    <h2 style="margin: 4px 0 2px 0; font-size: 1.55rem; color: #dc2626;"><?= format_currency($monthExpense) ?></h2>
    <small style="color: #64748b; font-weight: 600;">Salaries &amp; Operations</small>
  </a>

  <a href="finance.php" style="text-decoration: none; color: inherit;" class="kpi-exec-card">
    <div class="kpi-exec-icon" style="background: #fef3c7; color: #b45309;"><i class="fas fa-scale-balanced"></i></div>
    <small style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?= date('M Y') ?> Net Surplus</small>
    <h2 style="margin: 4px 0 2px 0; font-size: 1.55rem; color: <?= $monthNet >= 0 ? '#032d4e' : '#dc2626' ?>;"><?= format_currency($monthNet) ?></h2>
    <small style="color: #b45309; font-weight: 600;">Fee Dues Pending: <?= format_currency($totalPendingDues) ?></small>
  </a>
</div>

<!-- Analytics Charts Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; margin-bottom: 22px;">
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-chart-bar" style="color: #0e73b9;"></i> 6-Month Income vs Expense (৳)</h3>
      <a href="finance.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">Full Ledger &rarr;</a>
    </div>
    <div style="height: 260px;">
      <canvas id="cashflowChart"></canvas>
    </div>
  </div>

  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #f3b527;"></i> Class-Wise Student Distribution</h3>
      <a href="students.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">View Roster &rarr;</a>
    </div>
    <div style="height: 260px;">
      <canvas id="classDistChart"></canvas>
    </div>
  </div>
</div>

<!-- Operational Pulse Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 18px; margin-bottom: 22px;">
  <!-- Today Staff Attendance Monitor -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-user-check" style="color: #0f766e;"></i> Today's Employee Attendance (<?= date('d M') ?>)</h3>
      <a href="attendance.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">Detailed Report &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; text-align: center;">
      <div style="background: #dcfce7; padding: 10px; border-radius: 10px;">
        <strong style="font-size: 1.25rem; color: #15803d; display: block;"><?= $todayPresent ?></strong>
        <small style="color: #166534; font-weight: 700;">Present</small>
      </div>
      <div style="background: #fef3c7; padding: 10px; border-radius: 10px;">
        <strong style="font-size: 1.25rem; color: #b45309; display: block;"><?= $todayLate ?></strong>
        <small style="color: #92400e; font-weight: 700;">Late</small>
      </div>
      <div style="background: #fee2e2; padding: 10px; border-radius: 10px;">
        <strong style="font-size: 1.25rem; color: #dc2626; display: block;"><?= $todayAbsent ?></strong>
        <small style="color: #991b1b; font-weight: 700;">Absent</small>
      </div>
      <div style="background: #e0f2fe; padding: 10px; border-radius: 10px;">
        <strong style="font-size: 1.25rem; color: #0369a1; display: block;"><?= $todayLeave + $todayHalf ?></strong>
        <small style="color: #075985; font-weight: 700;">Leave/Half</small>
      </div>
    </div>

    <?php $attCoverage = $totalEmployees > 0 ? round(($todayMarked / $totalEmployees) * 100) : 0; ?>
    <div style="margin-bottom: 14px;">
      <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; margin-bottom: 4px;">
        <span>Attendance Marked Today</span>
        <span><?= $todayMarked ?> / <?= $totalEmployees ?> (<?= $attCoverage ?>%)</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill" style="width: <?= $attCoverage ?>%; background: #0f766e;"></div>
      </div>
    </div>

    <?php if (!empty($todayAlertStaff)): ?>
      <div style="font-size: 0.84rem; border-top: 1px solid #f1f5f9; padding-top: 10px;">
        <strong style="color: #475569; display: block; margin-bottom: 6px;">Late / Absent / Leave Watch:</strong>
        <?php foreach ($todayAlertStaff as $al): ?>
          <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #f1f5f9;">
            <span><code><?= htmlspecialchars($al['employee_code']) ?></code> <?= htmlspecialchars($al['full_name']) ?></span>
            <span class="badge <?= $al['status'] === 'Absent' ? 'badge-danger' : 'badge-warning' ?>"><?= htmlspecialchars($al['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="font-size: 0.84rem; color: #94a3b8; margin: 8px 0 0 0;">No late, absent, or on-leave alerts logged today.</p>
    <?php endif; ?>
  </div>

  <!-- Class-wise Student & Revenue Table -->
  <div class="card-box">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-school" style="color: #0e73b9;"></i> Class-Wise Strength &amp; Fee Dues</h3>
      <a href="students.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">All Classes &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Class</th>
            <th>Students</th>
            <th>Monthly Tuition/St</th>
            <th>Pending Dues</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classBreakdown as $cb): ?>
            <tr>
              <td>
                <code><?= htmlspecialchars($cb['class_code'] ?: str_pad((string)$cb['id'], 2, '0', STR_PAD_LEFT)) ?></code>
                <strong><?= htmlspecialchars($cb['class_name']) ?></strong>
              </td>
              <td>
                <strong><?= intval($cb['student_cnt']) ?></strong>
                <small style="color: #64748b;">(B:<?= intval($cb['boys_cnt']) ?> / G:<?= intval($cb['girls_cnt']) ?>)</small>
              </td>
              <td><?= format_currency($cb['tuition_fee']) ?></td>
              <td>
                <strong style="color: <?= floatval($cb['class_due']) > 0 ? '#dc2626' : '#059669' ?>;">
                  <?= format_currency($cb['class_due']) ?>
                </strong>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Financial Movements -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-receipt" style="color: #032d4e;"></i> Recent Income &amp; Expense Activity</h3>
    <a href="finance.php" style="font-size: 0.82rem; font-weight: 700; color: #0e73b9; text-decoration: none;">Monitor All Transactions &rarr;</a>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Txn No</th>
          <th>Date</th>
          <th>Type</th>
          <th>Category</th>
          <th>Title</th>
          <th>Amount (৳)</th>
          <th>Method</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($recentTx)): ?>
          <?php foreach ($recentTx as $tx): ?>
            <tr>
              <td><code><?= htmlspecialchars($tx['transaction_no']) ?></code></td>
              <td><?= format_date($tx['transaction_date']) ?></td>
              <td>
                <span class="badge <?= $tx['type'] === 'Income' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($tx['type']) ?></span>
              </td>
              <td><?= htmlspecialchars($tx['category']) ?></td>
              <td><strong><?= htmlspecialchars($tx['title']) ?></strong></td>
              <td style="font-weight: 700; color: <?= $tx['type'] === 'Income' ? '#15803d' : '#dc2626' ?>;">
                <?= ($tx['type'] === 'Income' ? '+ ' : '- ') . format_currency($tx['amount']) ?>
              </td>
              <td><?= htmlspecialchars($tx['payment_method']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 1.5rem;">No transactions recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const cashCtx = document.getElementById('cashflowChart').getContext('2d');
new Chart(cashCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartMonths) ?>,
    datasets: [
      {
        label: 'Income (৳)',
        data: <?= json_encode($chartIncome) ?>,
        backgroundColor: '#10b981',
        borderRadius: 6
      },
      {
        label: 'Expense (৳)',
        data: <?= json_encode($chartExpense) ?>,
        backgroundColor: '#ef4444',
        borderRadius: 6
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: { y: { beginAtZero: true } }
  }
});

const classCtx = document.getElementById('classDistChart').getContext('2d');
new Chart(classCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($classLabels) ?>,
    datasets: [{
      label: 'Active Students',
      data: <?= json_encode($classCounts) ?>,
      backgroundColor: ['#032d4e', '#064878', '#0e73b9', '#38bdf8', '#f3b527', '#10b981'],
      borderRadius: 8
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
