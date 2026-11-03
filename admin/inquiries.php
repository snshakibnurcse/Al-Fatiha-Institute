<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

if (isset($_GET['mark_read'])) {
    $inq_id = intval($_GET['mark_read']);
    $db->prepare("UPDATE inquiries SET status = 'Read' WHERE id = ?")->execute([$inq_id]);
}

$inquiries = $db->query("SELECT * FROM inquiries ORDER BY created_at DESC")->fetchAll();
?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-envelope-open-text"></i> Website Contact Inquiries</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Sender Name</th>
          <th>Phone / Email</th>
          <th>Subject</th>
          <th>Message</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($inquiries)): ?>
          <?php foreach ($inquiries as $inq): ?>
            <tr>
              <td><?= format_date($inq['created_at']) ?></td>
              <td><strong><?= htmlspecialchars($inq['name']) ?></strong></td>
              <td><?= htmlspecialchars($inq['phone']) ?><br><small style="color: var(--admin-text-muted);"><?= htmlspecialchars($inq['email']) ?></small></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($inq['subject']) ?></span></td>
              <td><?= nl2br(htmlspecialchars($inq['message'])) ?></td>
              <td>
                <?php if ($inq['status'] == 'Unread'): ?>
                  <a href="inquiries.php?mark_read=<?= $inq['id'] ?>" class="badge badge-warning" style="text-decoration: none;">Mark Read</a>
                <?php else: ?>
                  <span class="badge badge-success">Read</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center;">No website inquiries received yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
