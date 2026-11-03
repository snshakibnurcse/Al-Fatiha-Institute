<?php
require_once __DIR__ . '/includes/functions.php';
$db = get_db_connection();

// Category Filter
$category = sanitize($_GET['category'] ?? '');
if (!empty($category)) {
    $stmt = $db->prepare("SELECT * FROM notices WHERE category = ? ORDER BY published_date DESC");
    $stmt->execute([$category]);
} else {
    $stmt = $db->query("SELECT * FROM notices ORDER BY published_date DESC");
}
$allNotices = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Banner -->
<section style="background: linear-gradient(135deg, var(--primary-deep) 0%, var(--primary-dark) 100%); color: #ffffff; padding: 4rem 0 3.5rem 0; text-align: center;">
  <div class="container">
    <span class="sub-badge gold"><i class="fas fa-bullhorn"></i> Official Announcements</span>
    <h1 style="color: #ffffff; font-size: 2.8rem; margin-top: 0.5rem;">School Notice Board</h1>
    <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 650px; margin: 0.8rem auto 0 auto;">
      Stay updated with academic schedules, events, holiday announcements, and administrative notices.
    </p>
  </div>
</section>

<!-- Notice Board Listing -->
<section class="section-padding">
  <div class="container">
    
    <!-- Filter Buttons -->
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center; margin-bottom: 2.5rem;">
      <a href="notices.php" class="btn <?= empty($category) ? 'btn-primary' : 'btn-white' ?>"><i class="fas fa-th-list"></i> All Notices</a>
      <a href="notices.php?category=Admission" class="btn <?= ($category == 'Admission') ? 'btn-primary' : 'btn-white' ?>"><i class="fas fa-user-plus"></i> Admissions</a>
      <a href="notices.php?category=Event" class="btn <?= ($category == 'Event') ? 'btn-primary' : 'btn-white' ?>"><i class="fas fa-calendar-star"></i> Events</a>
      <a href="notices.php?category=Academic" class="btn <?= ($category == 'Academic') ? 'btn-primary' : 'btn-white' ?>"><i class="fas fa-book"></i> Academic</a>
      <a href="notices.php?category=Holiday" class="btn <?= ($category == 'Holiday') ? 'btn-primary' : 'btn-white' ?>"><i class="fas fa-umbrella-beach"></i> Holidays</a>
    </div>

    <div style="max-width: 900px; margin: 0 auto;">
      <?php if (!empty($allNotices)): ?>
        <?php foreach ($allNotices as $n): ?>
          <div id="notice-<?= $n['id'] ?>" style="background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--border-color); padding: 1.8rem; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; transition: var(--transition);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.8rem;">
              <span style="background: var(--sky-blue-light); color: var(--primary); font-weight: 800; padding: 0.25rem 0.8rem; border-radius: var(--radius-full); font-size: 0.78rem; text-transform: uppercase;">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($n['category']) ?>
              </span>
              <span style="font-size: 0.85rem; color: var(--text-light); font-weight: 600;">
                <i class="far fa-calendar-alt"></i> Published: <?= format_date($n['published_date']) ?>
              </span>
            </div>

            <h3 style="color: var(--primary-dark); font-size: 1.35rem; margin-bottom: 0.8rem;"><?= htmlspecialchars($n['title']) ?></h3>
            <p style="color: var(--text-muted); font-size: 0.98rem; line-height: 1.7;"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align: center; padding: 3rem; background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <i class="fas fa-info-circle" style="font-size: 2.5rem; color: var(--text-light); margin-bottom: 1rem;"></i>
          <p style="color: var(--text-muted);">No notices found for this category.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
