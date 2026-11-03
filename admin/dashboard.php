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
?>

<!-- Quick Finance & Academic Actions -->
<div style="display: flex; gap: 10px; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
  <a href="users.php" class="btn" style="background: #6366f1; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(99,102,241,0.25);">
    <i class="fas fa-users-cog"></i> User Management
  </a>
  <a href="fees.php?action=issue_invoice" class="btn" style="background: #2563eb; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
    <i class="fas fa-file-invoice"></i> Issue Invoice (Khata/Book/Fees)
  </a>
  <a href="fees.php?view=tracker" class="btn" style="background: #0284c7; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
    <i class="fas fa-calendar-alt"></i> Monthly Tuition &amp; Due Tracker
  </a>
  <a href="expenses.php" class="btn" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #334155; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
    <i class="fas fa-receipt"></i> Expenses &amp; Vouchers
  </a>
  <a href="subjects.php" class="btn" style="background: #0f766e; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(15,118,110,0.25);">
    <i class="fas fa-book-open"></i> Subjects &amp; Teacher Assign
  </a>
  <a href="classes.php" class="btn" style="background: #ffffff; border: 1.5px solid #cbd5e1; color: #334155; padding: 0.65rem 1.25rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
    <i class="fas fa-school"></i> Classes &amp; Fees
  </a>
</div>

<!-- KPI Stat Cards Grid -->
<div class="stats-grid">
  <div class="kpi-card">
    <div class="kpi-icon"><i class="fas fa-user-graduate"></i></div>
    <div class="kpi-data">
      <h3><?= $totalStudents ?></h3>
      <p>Active Students</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon gold"><i class="fas fa-chalkboard-teacher"></i></div>
    <div class="kpi-data">
      <h3><?= $totalTeachers ?></h3>
      <p>Teaching Staff</p>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon purple"><i class="fas fa-file-signature"></i></div>
    <div class="kpi-data">
      <h3><?= $pendingApps ?></h3>
      <p>Pending Admissions</p>
    </div>
  </div>

  <a href="fees.php" class="kpi-card" style="text-decoration: none; color: inherit; cursor: pointer; transition: transform 0.2s;" title="Click to view Monthly Fees & Due Tracker">
    <div class="kpi-icon green"><i class="fas fa-money-bill-wave"></i></div>
    <div class="kpi-data">
      <h3><?= format_currency($totalFeesCollected) ?></h3>
      <p>Total Revenue Collected &bull; <span style="color: var(--admin-primary); font-weight: 700; font-size: 0.78rem;">Tracker &rarr;</span></p>
    </div>
  </a>
</div>

<!-- Recent Applications Card -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clock" style="color: var(--admin-primary);"></i> Recent Online Admission Applications</h3>
    <a href="admissions.php" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.4rem 0.8rem; font-size: 0.82rem; border-radius: 6px; text-decoration: none;">View All Applications</a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>App Ref No</th>
          <th>Child Name</th>
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
              <td><strong><?= htmlspecialchars($app['child_name']) ?></strong> (<?= $app['gender'] ?>)</td>
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
                <a href="admissions.php?id=<?= $app['id'] ?>" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;">Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--admin-text-muted);">No admission applications received yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
