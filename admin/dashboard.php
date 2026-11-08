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
?>

<?php if ($flash): ?>
  <div class="alert-msg alert-<?= $flash['type'] === 'danger' ? 'danger' : 'success' ?>" style="background: <?= $flash['type'] === 'danger' ? '#fee2e2' : '#dcfce7' ?>; border: 1.5px solid <?= $flash['type'] === 'danger' ? '#fca5a5' : '#86efac' ?>; color: <?= $flash['type'] === 'danger' ? '#991b1b' : '#166534' ?>; padding: 1rem 1.4rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class="fas <?= $flash['type'] === 'danger' ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>" style="font-size: 1.3rem;"></i> 
      <div><?= htmlspecialchars($flash['message']) ?></div>
    </div>
    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; font-size:1.3rem; cursor:pointer; color:inherit;">&times;</button>
  </div>
<?php endif; ?>

<?php
$isSuperAdmin = is_super_admin();
$isPrincipal = is_principal();
$bannerBg = $isSuperAdmin 
    ? 'linear-gradient(135deg, #032d4e 0%, #064878 50%, #0e73b9 100%)' 
    : 'linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%)';
$roleTag = $isSuperAdmin ? 'Super Administrator' : ($isPrincipal ? 'Principal / Head of School' : 'Administrator');
$roleBadgeBg = $isSuperAdmin ? '#f59e0b' : '#a855f7';
$roleBadgeText = $isSuperAdmin ? '#032d4e' : '#ffffff';
$roleIcon = $isSuperAdmin ? 'fa-crown' : 'fa-user-shield';
?>

<!-- Executive Welcome Banner -->
<div class="dash-welcome-card <?= $isPrincipal ? 'principal-theme' : '' ?>" style="background: <?= $bannerBg ?> !important; border-radius: 18px; padding: 1.6rem 2rem; color: #ffffff !important; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem; box-shadow: 0 10px 25px rgba(0,0,0,0.18); position: relative; overflow: hidden;">
  <div class="welcome-left" style="position: relative; z-index: 2;">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 0.6rem;">
      <div class="date-badge" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.16); color: #ffffff !important; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.3px;">
        <i class="fas fa-calendar-day" style="color: #f3b527;"></i> <?= date('l, d F Y') ?>
      </div>
      <span style="display: inline-flex; align-items: center; gap: 5px; background: <?= $roleBadgeBg ?>; color: <?= $roleBadgeText ?>; padding: 3px 10px; border-radius: 20px; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.3px;">
        <i class="fas <?= $roleIcon ?>"></i> <?= $roleTag ?>
      </span>
    </div>
    <h2 class="welcome-title" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem; font-weight: 800; margin: 0 0 0.4rem 0; color: #ffffff !important; line-height: 1.25; text-shadow: 0 2px 4px rgba(0,0,0,0.35);">
      <span style="color: #f3b527;">Welcome,</span> <span style="color: #ffffff !important;"><?= htmlspecialchars($user_name) ?></span>
    </h2>
    <p class="welcome-sub" style="margin: 0; color: rgba(255, 255, 255, 0.92) !important; font-size: 0.95rem; font-weight: 500;">
      AL FATIHA INSTITUTE &bull; <?= $isSuperAdmin ? 'Super Administrative' : ($isPrincipal ? 'Principal Executive' : 'Administrative') ?> Portal Overview
    </p>
  </div>
  <div class="welcome-actions" style="display: flex; gap: 0.75rem; flex-wrap: wrap; position: relative; z-index: 2;">
    <a href="fees.php?action=issue_invoice" class="btn btn-welcome-primary">
      <i class="fas fa-plus-circle"></i> Issue Invoice
    </a>
    <a href="students.php" class="btn btn-welcome-secondary">
      <i class="fas fa-user-graduate"></i> Students Directory
    </a>
  </div>
</div>

<!-- Modern Quick Action Strip -->
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
    <div class="kpi-icon"><i class="fas fa-user-graduate"></i></div>
    <div class="kpi-data">
      <h3><?= number_format($totalStudents) ?></h3>
      <p>Active Students</p>
    </div>
  </a>

  <a href="teachers.php" class="kpi-card" style="text-decoration: none; color: inherit;">
    <div class="kpi-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
    <div class="kpi-data">
      <h3><?= number_format($totalTeachers) ?></h3>
      <p>Faculty Teachers</p>
    </div>
  </a>

  <a href="admissions.php" class="kpi-card" style="text-decoration: none; color: inherit;">
    <div class="kpi-icon purple"><i class="fas fa-file-signature"></i></div>
    <div class="kpi-data">
      <h3><?= number_format($pendingApps) ?></h3>
      <p>Pending Admissions</p>
    </div>
  </a>

  <a href="fees.php" class="kpi-card" style="text-decoration: none; color: inherit;" title="View Fees & Due Tracker">
    <div class="kpi-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="kpi-data">
      <h3><?= format_currency($totalFeesCollected) ?></h3>
      <p>Total Revenue Paid</p>
    </div>
  </a>
</div>

<!-- Recent Applications Card -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clock" style="color: var(--admin-primary);"></i> Recent Online Applications</h3>
    <a href="admissions.php" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.4rem 0.85rem; font-size: 0.82rem; border-radius: 8px; text-decoration: none; font-weight: 700;">View All</a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>App Ref</th>
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
              <td><code><?= htmlspecialchars($app['application_no']) ?></code></td>
              <td><strong><?= htmlspecialchars($app['child_name']) ?></strong> (<?= htmlspecialchars($app['gender']) ?>)</td>
              <td><span class="badge badge-info"><?= htmlspecialchars($app['class_name']) ?></span></td>
              <td><?= htmlspecialchars($app['father_name']) ?><br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($app['phone']) ?></small></td>
              <td><?= format_date($app['applied_at']) ?></td>
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
                <a href="admissions.php?id=<?= $app['id'] ?>" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.3rem 0.75rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none; font-weight: 600;">Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--admin-text-muted); padding: 2rem;">No admission applications received yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
