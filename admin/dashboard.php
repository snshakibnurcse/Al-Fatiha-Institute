<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$pendingApps = $db->query("SELECT COUNT(*) FROM admissions WHERE status='Pending'")->fetchColumn();
$totalFeesCollected = $db->query("SELECT SUM(amount) FROM fees WHERE status='Paid'")->fetchColumn() ?: 0;

// Recent Pending Applications
$appsStmt = $db->query("SELECT a.*, c.class_name FROM admissions a JOIN classes c ON a.target_class_id = c.id ORDER BY a.applied_at DESC LIMIT 5");
$recentApps = $appsStmt->fetchAll();

$flash = function_exists('get_flash_msg') ? get_flash_msg() : null;
$isSuperAdmin = is_super_admin();
$isPrincipal = is_principal();
$roleTag = $isSuperAdmin ? 'Super Administrator' : ($isPrincipal ? 'Principal' : 'Administrator');
?>

<?php if ($flash): ?>
  <div class="alert-msg alert-<?= $flash['type'] === 'danger' ? 'danger' : 'success' ?>" style="justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class="fas <?= $flash['type'] === 'danger' ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
      <span><?= htmlspecialchars($flash['message']) ?></span>
    </div>
    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; font-size:18px; cursor:pointer; color:inherit;">&times;</button>
  </div>
<?php endif; ?>

<!-- Executive Header Card -->
<div class="dash-welcome-card">
  <div>
    <div class="welcome-meta-row">
      <span class="date-badge"><?= date('D, d M Y') ?></span>
      <span class="role-pill-tag"><?= $roleTag ?></span>
    </div>
    <h2 class="welcome-title">Welcome, <?= htmlspecialchars($user_name) ?></h2>
    <p class="welcome-sub">AL FATIHA INSTITUTE · Academic &amp; Financial Overview</p>
  </div>
  <div class="welcome-actions">
    <a href="fees.php?action=issue_invoice" class="btn-welcome-primary">
      Issue Invoice
    </a>
    <a href="students.php" class="btn-welcome-secondary">
      Students Directory
    </a>
  </div>
</div>

<!-- Quick Links Strip -->
<div class="dash-quick-strip">
  <a href="admissions.php" class="quick-chip <?= ($pendingApps > 0) ? 'chip-pending' : '' ?>">
    <i class="fas fa-file-signature"></i>
    <span>Applications</span>
    <?php if ($pendingApps > 0): ?>
      <span class="chip-badge"><?= $pendingApps ?></span>
    <?php endif; ?>
  </a>
  <a href="fees.php?view=tracker" class="quick-chip">
    <i class="fas fa-calendar-check"></i>
    <span>Due Tracker</span>
  </a>
  <a href="expenses.php" class="quick-chip">
    <i class="fas fa-receipt"></i>
    <span>Expenses</span>
  </a>
  <a href="income.php" class="quick-chip">
    <i class="fas fa-hand-holding-usd"></i>
    <span>Income</span>
  </a>
  <a href="promotions.php" class="quick-chip">
    <i class="fas fa-level-up-alt"></i>
    <span>Promotions</span>
  </a>
  <a href="subjects.php" class="quick-chip">
    <i class="fas fa-book-open"></i>
    <span>Subjects</span>
  </a>
  <?php if (is_super_admin()): ?>
  <a href="users.php" class="quick-chip">
    <i class="fas fa-users-cog"></i>
    <span>Users</span>
  </a>
  <?php endif; ?>
</div>

<!-- KPI Stat Cards Grid -->
<div class="stats-grid">
  <a href="students.php" class="kpi-card" style="text-decoration: none; color: inherit;">
    <div class="kpi-data">
      <h3><?= number_format($totalStudents) ?></h3>
      <p>Active students</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-user-graduate"></i></div>
  </a>

  <a href="teachers.php" class="kpi-card" style="text-decoration: none; color: inherit;">
    <div class="kpi-data">
      <h3><?= number_format($totalTeachers) ?></h3>
      <p>Faculty teachers</p>
    </div>
    <div class="kpi-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
  </a>

  <a href="admissions.php" class="kpi-card" style="text-decoration: none; color: inherit;">
    <div class="kpi-data">
      <h3><?= number_format($pendingApps) ?></h3>
      <p>Pending admissions</p>
    </div>
    <div class="kpi-icon"><i class="fas fa-file-signature"></i></div>
  </a>

  <a href="fees.php" class="kpi-card" style="text-decoration: none; color: inherit;" title="View Fees & Due Tracker">
    <div class="kpi-data">
      <h3><?= format_currency($totalFeesCollected) ?></h3>
      <p>Total fees collected</p>
    </div>
    <div class="kpi-icon green"><i class="fas fa-money-bill-wave"></i></div>
  </a>
</div>

<!-- Recent Applications Table -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title">Recent Online Applications</h3>
    <a href="admissions.php" class="btn-welcome-secondary" style="height: 32px; padding: 0 12px; font-size: 13px;">View all</a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Student Name</th>
          <th>Target Class</th>
          <th>Parent / Phone</th>
          <th>Applied Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($recentApps)): ?>
          <?php foreach ($recentApps as $app): ?>
            <tr>
              <td><code style="font-family: 'IBM Plex Mono', monospace; font-size: 12px;"><?= htmlspecialchars($app['application_no']) ?></code></td>
              <td><strong><?= htmlspecialchars($app['child_name']) ?></strong> <span style="color: var(--admin-text-muted); font-size: 12px;">(<?= htmlspecialchars($app['gender']) ?>)</span></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($app['class_name']) ?></span></td>
              <td><?= htmlspecialchars($app['father_name']) ?><br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($app['phone']) ?></small></td>
              <td style="font-family: 'IBM Plex Mono', monospace; font-size: 12.5px;"><?= format_date($app['applied_at']) ?></td>
              <td>
                <?php if ($app['status'] == 'Approved'): ?>
                  <span class="badge badge-success">Approved</span>
                <?php elseif ($app['status'] == 'Rejected'): ?>
                  <span class="badge badge-danger">Rejected</span>
                <?php else: ?>
                  <span class="badge badge-warning">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="admissions.php?id=<?= $app['id'] ?>" class="btn-welcome-secondary" style="height: 30px; padding: 0 10px; font-size: 12.5px;">Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--admin-text-muted); padding: 32px;">No admission applications received yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
