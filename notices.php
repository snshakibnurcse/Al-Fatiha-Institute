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
<section class="page-banner centered">
  <div class="container">
    <span class="eyebrow eyebrow-light">Official Announcements</span>
    <h1>School Notice Board</h1>
    <p>
      Academic schedules, school events, holiday calendars, and administrative updates from AL FATIHA INSTITUTE.
    </p>
  </div>
</section>

<!-- Notice Board Listing -->
<section class="section-padding">
  <div class="container">

    <!-- Filter Buttons -->
    <div class="filter-bar">
      <a href="notices.php" class="btn btn-nav <?= empty($category) ? 'btn-primary' : 'btn-white' ?>">All Notices</a>
      <a href="notices.php?category=Admission" class="btn btn-nav <?= ($category == 'Admission') ? 'btn-primary' : 'btn-white' ?>">Admissions</a>
      <a href="notices.php?category=Event" class="btn btn-nav <?= ($category == 'Event') ? 'btn-primary' : 'btn-white' ?>">Events</a>
      <a href="notices.php?category=Academic" class="btn btn-nav <?= ($category == 'Academic') ? 'btn-primary' : 'btn-white' ?>">Academic</a>
      <a href="notices.php?category=Holiday" class="btn btn-nav <?= ($category == 'Holiday') ? 'btn-primary' : 'btn-white' ?>">Holidays</a>
    </div>

    <div class="notices-list">
      <?php if (!empty($allNotices)): ?>
        <?php foreach ($allNotices as $n): ?>
          <article id="notice-<?= $n['id'] ?>" class="notice-card-full">
            <div class="notice-card-meta">
              <span class="category-tag"><?= htmlspecialchars($n['category']) ?></span>
              <span class="mono-meta" style="color: var(--text-muted);">
                Published <?= format_date($n['published_date']) ?>
              </span>
            </div>

            <h3><?= htmlspecialchars($n['title']) ?></h3>
            <p><?= nl2br(htmlspecialchars($n['content'])) ?></p>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="content-card" style="text-align: center; padding: 48px 24px;">
          <p class="section-desc">No notices found in this category.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
