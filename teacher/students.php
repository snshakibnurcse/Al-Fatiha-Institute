<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$selected_class_id = intval($_GET['class_id'] ?? 0);
$allClasses = $db->query("SELECT id, class_name FROM classes ORDER BY id ASC")->fetchAll();

if ($selected_class_id > 0) {
    $stmt = $db->prepare("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.class_id = ? ORDER BY s.roll_no ASC");
    $stmt->execute([$selected_class_id]);
    $students = $stmt->fetchAll();
} else {
    $students = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id ORDER BY s.class_id ASC, s.roll_no ASC")->fetchAll();
}
?>

<div class="card-box">
  <div class="card-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">
    <div>
      <h3 class="card-title">Student Roster &amp; Directory</h3>
      <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--admin-text-muted);">
        Viewing <?= count($students) ?> enrolled students.
      </p>
    </div>

    <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <select name="class_id" class="form-control" onchange="this.form.submit()" style="width: 170px; height: 38px; padding: 0 10px;">
        <option value="0">All Classes</option>
        <?php foreach ($allClasses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($selected_class_id == $c['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['class_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="text" id="tableSearch" placeholder="Search" class="form-control" style="width: 210px; height: 38px; padding: 0 12px;">

      <?php if ($selected_class_id > 0): ?>
        <a href="students.php" class="btn-welcome-secondary" style="height: 38px;">
          Reset
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card-box">
  <div class="table-responsive">
    <table class="data-table" id="studentsTable">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Class</th>
          <th>Roll</th>
          <th>Gender</th>
          <th>Father / Contact</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 32px; color: var(--admin-text-muted);">
              No students enrolled in this class yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $st): ?>
            <tr>
              <td><code style="font-family: 'IBM Plex Mono', monospace; font-size: 12px;"><?= htmlspecialchars($st['student_id']) ?></code></td>
              <td><strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($st['class_name']) ?></span></td>
              <td style="font-family: 'IBM Plex Mono', monospace;">#<?= $st['roll_no'] ?></td>
              <td><?= $st['gender'] ?></td>
              <td>
                <?= htmlspecialchars($st['father_name'] ?? 'N/A') ?><br>
                <small style="color: var(--admin-text-muted);"><?= htmlspecialchars($st['phone'] ?? 'N/A') ?></small>
              </td>
              <td><span class="badge badge-success"><?= $st['status'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('tableSearch')?.addEventListener('keyup', function() {
  const query = this.value.toLowerCase();
  const rows = document.querySelectorAll('#studentsTable tbody tr');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
});
</script>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
