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

<div class="card-box" style="margin-bottom: 1.5rem; background: #ffffff; border-radius: 12px; padding: 1.25rem; border: 1px solid var(--admin-border);">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--admin-deep-navy);">
        <i class="fas fa-user-graduate" style="color: var(--admin-primary);"></i> Student Roster &amp; Directory
      </h3>
      <p style="margin: 0.2rem 0 0 0; font-size: 0.85rem; color: var(--admin-text-muted);">
        Viewing <?= count($students) ?> enrolled students across institute branches.
      </p>
    </div>

    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <select name="class_id" class="form-control" onchange="this.form.submit()" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; border-radius: 8px;">
        <option value="0">-- All Classes (সমস্ত ক্লাস) --</option>
        <?php foreach ($allClasses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($selected_class_id == $c['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['class_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="text" id="tableSearch" placeholder="Search by name, roll, phone..." class="form-control" style="width: 220px; font-size: 0.85rem; padding: 0.4rem 0.8rem; border-radius: 8px;">
      
      <?php if ($selected_class_id > 0): ?>
        <a href="students.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
          <i class="fas fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card-box" style="background: #ffffff; border-radius: 12px; border: 1px solid var(--admin-border); overflow: hidden;">
  <div class="table-responsive">
    <table class="data-table" id="studentsTable">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Student Name</th>
          <th>Class</th>
          <th>Roll No</th>
          <th>Gender</th>
          <th>Father / Contact</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--admin-text-muted);">
              No students enrolled in this class yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $st): ?>
            <tr>
              <td><code><?= htmlspecialchars($st['student_id']) ?></code></td>
              <td><strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($st['class_name']) ?></span></td>
              <td><strong>#<?= $st['roll_no'] ?></strong></td>
              <td><?= $st['gender'] ?></td>
              <td>
                <?= htmlspecialchars($st['father_name'] ?? 'N/A') ?><br>
                <small style="color: var(--admin-text-muted);"><i class="fas fa-phone" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($st['phone'] ?? 'N/A') ?></small>
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
