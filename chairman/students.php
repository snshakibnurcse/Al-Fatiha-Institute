<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$selectedClassId = intval($_GET['class_id'] ?? 0);
$q = sanitize($_GET['q'] ?? '');

$classCards = $db->query("
    SELECT c.id, c.class_code, c.class_name, c.tuition_fee,
           COUNT(DISTINCT CASE WHEN s.status='Active' THEN s.id END) as active_cnt,
           SUM(CASE WHEN s.status='Active' AND s.gender='Male' THEN 1 ELSE 0 END) as boys_cnt,
           SUM(CASE WHEN s.status='Active' AND s.gender='Female' THEN 1 ELSE 0 END) as girls_cnt
    FROM classes c
    LEFT JOIN students s ON c.id = s.class_id
    GROUP BY c.id
    ORDER BY c.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalAll = array_sum(array_map(function($c) { return intval($c['active_cnt']); }, $classCards));

$where = ["s.status = 'Active'"];
$params = [];
if ($selectedClassId > 0) {
    $where[] = "s.class_id = ?";
    $params[] = $selectedClassId;
}
if ($q !== '') {
    $where[] = "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.father_name LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$stStmt = $db->prepare("
    SELECT s.*, c.class_name, c.class_code,
           COALESCE((SELECT SUM(GREATEST(0, f.amount - COALESCE(f.paid_amount, 0))) FROM fees f WHERE f.student_id = s.id AND f.status IN ('Unpaid', 'Partial')), 0) as due_amount,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id) as att_total,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status IN ('Present','Late')) as att_present
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.id ASC, s.roll_no ASC
");
$stStmt->execute($params);
$students = $stStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy); margin: 0;">Class-Wise Student Monitor</h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 0.92rem;">Monitor active student strength across all grades, attendance rates, and fee balances.</p>
  </div>
</div>

<!-- Clickable Class Filter Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 1.5rem;">
  <a href="students.php" style="text-decoration: none; background: <?= $selectedClassId === 0 ? '#032d4e' : '#ffffff' ?>; color: <?= $selectedClassId === 0 ? '#ffffff' : '#0f172a' ?>; border: 1.5px solid <?= $selectedClassId === 0 ? '#032d4e' : '#e2e8f0' ?>; border-radius: 12px; padding: 14px;">
    <small style="opacity: 0.8; font-weight: 700; text-transform: uppercase;">All Classes</small>
    <h3 style="margin: 4px 0 0 0; font-size: 1.65rem;"><?= $totalAll ?></h3>
    <small style="opacity: 0.8;">Total Active</small>
  </a>
  <?php foreach ($classCards as $cc): ?>
    <?php $isAct = ($selectedClassId === intval($cc['id'])); ?>
    <a href="students.php?class_id=<?= $cc['id'] ?>" style="text-decoration: none; background: <?= $isAct ? '#0e73b9' : '#ffffff' ?>; color: <?= $isAct ? '#ffffff' : '#0f172a' ?>; border: 1.5px solid <?= $isAct ? '#0e73b9' : '#e2e8f0' ?>; border-radius: 12px; padding: 14px;">
      <small style="opacity: 0.8; font-weight: 700;">Code <?= htmlspecialchars($cc['class_code'] ?: str_pad((string)$cc['id'], 2, '0', STR_PAD_LEFT)) ?></small>
      <div style="font-weight: 800; font-size: 0.98rem; margin-top: 2px;"><?= htmlspecialchars($cc['class_name']) ?></div>
      <div style="margin-top: 6px; font-size: 1.35rem; font-weight: 800;"><?= intval($cc['active_cnt']) ?> <small style="font-size: 0.72rem; font-weight: 600; opacity: 0.8;">(B:<?= intval($cc['boys_cnt']) ?> G:<?= intval($cc['girls_cnt']) ?>)</small></div>
    </a>
  <?php endforeach; ?>
</div>

<!-- Search Filter -->
<div class="card-box" style="margin-bottom: 1.25rem;">
  <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
    <div style="flex: 1; min-width: 240px;">
      <label style="font-size: 0.82rem; font-weight: 700;">Search by Student ID, Name, Father or Phone</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary" style="background: #0e73b9; color: #fff; font-weight: 700; padding: 0.6rem 1.2rem; border-radius: 8px; border: none;">Filter</button>
    <a href="students.php" class="btn btn-secondary" style="padding: 0.6rem 1rem; border-radius: 8px; text-decoration: none;">Reset</a>
  </form>
</div>

<!-- Read-Only Student Roster -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-graduate"></i> Student Roster (<?= count($students) ?>)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Class &amp; Roll</th>
          <th>Session</th>
          <th>Guardian &amp; Contact</th>
          <th>Attendance</th>
          <th>Pending Due</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($students)): ?>
          <?php foreach ($students as $st): ?>
            <?php
              $attPct = intval($st['att_total']) > 0 ? round((intval($st['att_present']) / intval($st['att_total'])) * 100, 1) : 100;
              $due = floatval($st['due_amount']);
            ?>
            <tr>
              <td><code><?= htmlspecialchars($st['student_id']) ?></code></td>
              <td>
                <strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong>
                <br><small style="color: #64748b;"><?= htmlspecialchars($st['gender']) ?></small>
              </td>
              <td>
                <span class="badge badge-info"><?= htmlspecialchars($st['class_name']) ?></span>
                <strong>#<?= intval($st['roll_no']) ?></strong>
              </td>
              <td><?= htmlspecialchars($st['current_session'] ?? $st['admission_session'] ?? '2027') ?></td>
              <td>
                <?= htmlspecialchars($st['father_name']) ?>
                <br><small style="color: #64748b;"><?= htmlspecialchars($st['phone']) ?></small>
              </td>
              <td><strong><?= $attPct ?>%</strong></td>
              <td style="font-weight: 700; color: <?= $due > 0 ? '#dc2626' : '#059669' ?>;">
                <?= format_currency($due) ?>
              </td>
              <td><span class="badge badge-success"><?= htmlspecialchars($st['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #94a3b8;">No active students found matching criteria.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
