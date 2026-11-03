<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? 'General');
    $content = sanitize($_POST['content'] ?? '');

    if ($title && $content) {
        $db->prepare("INSERT INTO notices (title, category, content, published_date) VALUES (?, ?, ?, CURRENT_DATE())")->execute([$title, $category, $content]);
        $msg = "Notice published successfully!";
    }
}

if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $db->prepare("DELETE FROM notices WHERE id = ?")->execute([$del_id]);
    $msg = "Notice deleted.";
}

$notices = $db->query("SELECT * FROM notices ORDER BY published_date DESC")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
  <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">School Notice Board Manager</h3>
  <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('addNoticeModal')">
    <i class="fas fa-plus"></i> Publish New Notice
  </button>
</div>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-bullhorn"></i> Active Announcements</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Published Date</th>
          <th>Title</th>
          <th>Category</th>
          <th>Content Snippet</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notices as $n): ?>
          <tr>
            <td><?= format_date($n['published_date']) ?></td>
            <td><strong><?= htmlspecialchars($n['title']) ?></strong></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($n['category']) ?></span></td>
            <td><?= htmlspecialchars(substr($n['content'], 0, 70)) ?>...</td>
            <td>
              <a href="notices.php?delete=<?= $n['id'] ?>" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.25rem 0.6rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Delete notice?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Notice Modal -->
<div id="addNoticeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 550px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Publish Notice</h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addNoticeModal')">&times;</button>
    </div>

    <form method="POST">
      <div class="form-group">
        <label>Notice Title *</label>
        <input type="text" name="title" class="form-control" placeholder="Notice Title" required>
      </div>

      <div class="form-group">
        <label>Category *</label>
        <select name="category" class="form-control" required>
          <option value="Admission">Admission</option>
          <option value="Event">Event</option>
          <option value="Academic">Academic</option>
          <option value="Holiday">Holiday</option>
          <option value="General">General</option>
        </select>
      </div>

      <div class="form-group">
        <label>Notice Content *</label>
        <textarea name="content" class="form-control" rows="4" placeholder="Detailed notice description..." required></textarea>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('addNoticeModal')">Cancel</button>
        <button type="submit" name="add_notice" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Publish Notice</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
