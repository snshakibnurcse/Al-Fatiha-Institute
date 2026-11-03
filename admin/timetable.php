<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Handle Add / Edit Timetable Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable_slot'])) {
    $class_id = intval($_POST['class_id'] ?? 0);
    $day_of_week = sanitize($_POST['day_of_week'] ?? 'Sunday');
    $period_name = sanitize($_POST['period_name'] ?? 'Period 1');
    $start_time = sanitize($_POST['start_time'] ?? '08:30');
    $end_time = sanitize($_POST['end_time'] ?? '09:15');
    $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
    $selected_teachers = $_POST['teacher_ids'] ?? [];
    $teacher_ids = !empty($selected_teachers) ? implode(',', array_map('intval', $selected_teachers)) : null;
    $room_no = sanitize($_POST['room_no'] ?? '');

    if ($class_id > 0 && !empty($period_name)) {
        try {
            $stmt = $db->prepare("INSERT INTO timetables (class_id, day_of_week, period_name, start_time, end_time, subject_id, teacher_ids, room_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$class_id, $day_of_week, $period_name, $start_time, $end_time, $subject_id, $teacher_ids, $room_no]);
            $msg = "Timetable period slot added successfully!";
        } catch (Exception $e) {
            $err = "Failed to add slot: " . $e->getMessage();
        }
    } else {
        $err = "Please select class and period name.";
    }
}

// Handle Delete Slot
if (isset($_GET['delete_slot'])) {
    $del_id = intval($_GET['delete_slot']);
    $db->prepare("DELETE FROM timetables WHERE id = ?")->execute([$del_id]);
    $msg = "Timetable slot deleted successfully.";
}

// Filter parameters
$selected_class_id = intval($_GET['class_id'] ?? 4);

$classes = $db->query("SELECT * FROM classes ORDER BY id ASC")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
$teachers = $db->query("SELECT * FROM teachers ORDER BY full_name ASC")->fetchAll();
$teacherMap = [];
foreach ($teachers as $t) {
    $teacherMap[$t['id']] = $t['full_name'];
}

// Fetch Timetables for selected class
$slotsStmt = $db->prepare("
    SELECT t.*, c.class_name, sub.subject_name 
    FROM timetables t 
    JOIN classes c ON t.class_id = c.id 
    LEFT JOIN subjects sub ON t.subject_id = sub.id 
    WHERE t.class_id = ? 
    ORDER BY FIELD(t.day_of_week, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time ASC
");
$slotsStmt->execute([$selected_class_id]);
$slots = $slotsStmt->fetchAll();

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
$slotsByDay = [];
foreach ($slots as $s) {
    $slotsByDay[$s['day_of_week']][] = $s;
}
?>

<?php if ($msg): ?><div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<!-- Top Header Banner -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">
      <i class="fas fa-calendar-alt" style="color: var(--admin-gold);"></i> Class Timetable & Routine Master
    </h2>
    <p style="color: var(--admin-text-muted); margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Configure period schedules, assigned subject educators, and classroom rooms. Multiple teachers can be assigned to a period.
    </p>
  </div>
  <div style="display: flex; gap: 0.5rem;">
    <button class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('addSlotModal')">
      <i class="fas fa-plus"></i> Add Period Slot
    </button>
  </div>
</div>

<!-- Class Filter Bar -->
<div class="card-box" style="margin-bottom: 1.5rem; padding: 1.2rem;">
  <form method="GET" style="display: flex; gap: 1rem; align-items: center;">
    <label style="font-weight: 700; color: var(--admin-primary-dark); font-size: 0.95rem;">Select Class Schedule:</label>
    <select name="class_id" class="form-control" style="width: 250px; padding: 0.5rem;" onchange="this.form.submit()">
      <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $selected_class_id == $c['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['class_name']) ?> (Room: <?= $c['room_no'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- Weekly Schedule Grid View -->
<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clock"></i> Weekly Class Routine Grid</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 130px;">Day</th>
          <th>Class Schedule Periods & Assigned Teachers</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($days as $day): ?>
          <tr>
            <td style="vertical-align: top; background: #f8fafc; font-weight: 800; color: var(--admin-primary-dark);">
              <?= $day ?>
            </td>
            <td>
              <?php if (!empty($slotsByDay[$day])): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
                  <?php foreach ($slotsByDay[$day] as $slot): ?>
                    <?php
                      // Parse teacher names
                      $tNames = [];
                      if (!empty($slot['teacher_ids'])) {
                          $tIds = explode(',', $slot['teacher_ids']);
                          foreach ($tIds as $tid) {
                              if (isset($teacherMap[$tid])) {
                                  $tNames[] = $teacherMap[$tid];
                              }
                          }
                      }
                    ?>
                    <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-left: 4px solid var(--admin-primary); border-radius: 10px; padding: 0.85rem; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                      <a href="timetable.php?class_id=<?= $selected_class_id ?>&delete_slot=<?= $slot['id'] ?>" style="position: absolute; top: 0.5rem; right: 0.5rem; color: #ef4444; font-size: 0.85rem;" onclick="return confirm('Delete this slot?')">
                        <i class="fas fa-trash-alt"></i>
                      </a>

                      <span style="background: #f1f5f9; color: #334155; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                        <i class="far fa-clock"></i> <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                      </span>
                      
                      <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0.4rem 0 0.2rem 0; font-size: 0.95rem;">
                        <?= htmlspecialchars($slot['period_name']) ?>
                      </h4>

                      <p style="margin: 0; font-size: 0.85rem; color: var(--admin-gold-dark); font-weight: 700;">
                        <i class="fas fa-book"></i> Subject: <?= htmlspecialchars($slot['subject_name'] ?: 'General / Activity') ?>
                      </p>

                      <p style="margin: 0.3rem 0 0 0; font-size: 0.82rem; color: #475569;">
                        <i class="fas fa-chalkboard-teacher" style="color: #10b981;"></i> 
                        Teachers: 
                        <strong><?= !empty($tNames) ? htmlspecialchars(implode(', ', $tNames)) : 'Unassigned' ?></strong>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span style="color: var(--admin-text-muted); font-size: 0.85rem;">No classes scheduled for <?= $day ?>.</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Timetable Slot Modal -->
<div id="addSlotModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 550px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Add Period Slot to Routine</h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('addSlotModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Select Class *</label>
          <select name="class_id" class="form-control" required>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $selected_class_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Day of Week *</label>
          <select name="day_of_week" class="form-control" required>
            <option value="Sunday">Sunday</option>
            <option value="Monday">Monday</option>
            <option value="Tuesday">Tuesday</option>
            <option value="Wednesday">Wednesday</option>
            <option value="Thursday">Thursday</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Period Title / Name *</label>
        <input type="text" name="period_name" class="form-control" placeholder="Period 1 (Morning Quran & Tajweed)" required>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Start Time *</label>
          <input type="time" name="start_time" class="form-control" value="08:30" required>
        </div>
        <div class="form-group">
          <label>End Time *</label>
          <input type="time" name="end_time" class="form-control" value="09:15" required>
        </div>
      </div>

      <div class="form-group">
        <label>Subject</label>
        <select name="subject_id" class="form-control">
          <option value="">-- Select Subject (Optional) --</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Multiple Teachers Checkbox Options -->
      <div class="form-group">
        <label style="font-weight: 700; color: #047857;">Assign Teachers (Select 1 or more Co-teachers):</label>
        <div style="max-height: 140px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem; background: #f8fafc;">
          <?php foreach ($teachers as $t): ?>
            <label style="display: block; margin-bottom: 0.4rem; cursor: pointer; font-size: 0.88rem;">
              <input type="checkbox" name="teacher_ids[]" value="<?= $t['id'] ?>" style="margin-right: 0.4rem;">
              <strong><?= htmlspecialchars($t['full_name']) ?></strong> (<?= htmlspecialchars($t['specialty']) ?>)
            </label>
          <?php endforeach; ?>
        </div>
        <small style="color: var(--admin-text-muted);">You can check multiple teachers to assign them simultaneously to this class period.</small>
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('addSlotModal')">Cancel</button>
        <button type="submit" name="save_timetable_slot" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">Save Period Slot</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
