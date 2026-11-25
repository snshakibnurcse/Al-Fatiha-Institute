<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();

// Fetch student for logged in parent
$stStmt = $db->prepare("
    SELECT s.*, c.class_name, c.room_no,
           pt.full_name as primary_teacher_name,
           bt.full_name as backup_teacher_name
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    LEFT JOIN teachers pt ON c.primary_teacher_id = pt.id
    LEFT JOIN teachers bt ON c.backup_teacher_id = bt.id
    WHERE s.parent_user_id = ? LIMIT 1
");
$stStmt->execute([$user_id]);
$student = $stStmt->fetch();

if (!$student) {
    $student = $db->query("
        SELECT s.*, c.class_name, c.room_no,
               pt.full_name as primary_teacher_name,
               bt.full_name as backup_teacher_name
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        LEFT JOIN teachers pt ON c.primary_teacher_id = pt.id
        LEFT JOIN teachers bt ON c.backup_teacher_id = bt.id
        WHERE s.id = 1
    ")->fetch();
}

$child_class_id = $student['class_id'];

// Map Teachers for lookup
$teachers = $db->query("SELECT id, full_name FROM teachers")->fetchAll();
$teacherMap = [];
foreach ($teachers as $t) {
    $teacherMap[$t['id']] = $t['full_name'];
}

// Fetch Timetable ONLY for this child's class
$slotsStmt = $db->prepare("
    SELECT t.*, sub.subject_name 
    FROM timetables t 
    LEFT JOIN subjects sub ON t.subject_id = sub.id 
    WHERE t.class_id = ? 
    ORDER BY FIELD(t.day_of_week, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), t.start_time ASC
");
$slotsStmt->execute([$child_class_id]);
$slots = $slotsStmt->fetchAll();

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
$slotsByDay = [];
foreach ($slots as $s) {
    $slotsByDay[$s['day_of_week']][] = $s;
}
?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 26px; font-weight: 500; color: var(--admin-navy); margin: 0;">
      Class Weekly Timetable
    </h2>
    <p style="color: var(--admin-text-muted); margin: 4px 0 0 0; font-size: 13.5px;">
      Routine for <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong> (<?= htmlspecialchars($student['class_name']) ?>)
    </p>
  </div>
  <button onclick="window.print()" class="btn-welcome-primary" style="cursor: pointer;">
    <i class="fas fa-print"></i> Print Routine
  </button>
</div>

<div class="card-box" style="padding: 28px;">

  <!-- Header Banner -->
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--admin-navy); padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo" style="height: 56px; width: 56px; object-fit: contain;">
      <div>
        <h2 style="font-family: 'Newsreader', Georgia, serif; color: var(--admin-navy); margin: 0; font-size: 24px; font-weight: 500;">AL FATIHA INSTITUTE</h2>
        <span style="font-family: 'IBM Plex Mono', monospace; color: var(--admin-gold-dark); font-size: 12px;">Official Class Timetable &amp; Routine</span>
      </div>
    </div>
    <span class="badge badge-info" style="font-size: 12px; padding: 6px 12px;">
      <?= htmlspecialchars($student['class_name']) ?>
    </span>
  </div>

  <!-- Class & Teacher Details -->
  <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13.5px;">
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Student Name:</span> <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></p>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Class:</span> <?= htmlspecialchars($student['class_name']) ?> (#<?= $student['roll_no'] ?>)</p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Classroom:</span> Room <?= htmlspecialchars($student['room_no']) ?></p>
    </div>
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Primary Teacher:</span> <strong><?= htmlspecialchars($student['primary_teacher_name'] ?: 'Unassigned') ?></strong></p>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Backup Teacher:</span> <?= htmlspecialchars($student['backup_teacher_name'] ?: 'None') ?></p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Academic Year:</span> <span style="font-family: 'IBM Plex Mono', monospace;">2026–2027</span></p>
    </div>
  </div>

  <!-- Timetable Table -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 120px;">Day</th>
          <th>Scheduled Periods &amp; Subjects</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($days as $day): ?>
          <tr>
            <td style="vertical-align: top; background: var(--admin-bg); font-weight: 600; color: var(--admin-navy);">
              <?= $day ?>
            </td>
            <td>
              <?php if (!empty($slotsByDay[$day])): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                  <?php foreach ($slotsByDay[$day] as $slot): ?>
                    <?php
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
                    <div style="background: #ffffff; border: 1px solid var(--admin-border); border-left: 3px solid var(--admin-navy); border-radius: 8px; padding: 12px;">
                      <span style="font-family: 'IBM Plex Mono', monospace; background: var(--admin-raised); color: var(--admin-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                        <?= date('h:i A', strtotime($slot['start_time'])) ?> – <?= date('h:i A', strtotime($slot['end_time'])) ?>
                      </span>

                      <h4 style="color: var(--admin-navy); margin: 8px 0 4px 0; font-size: 14px; font-weight: 600;">
                        <?= htmlspecialchars($slot['period_name']) ?>
                      </h4>

                      <p style="margin: 0; font-size: 13px; color: var(--admin-gold-dark); font-weight: 500;">
                        <?= htmlspecialchars($slot['subject_name'] ?: 'General Activity') ?>
                      </p>

                      <p style="margin: 4px 0 0 0; font-size: 12.5px; color: var(--admin-text-muted);">
                        Teacher: <strong style="color: var(--admin-text-secondary);"><?= !empty($tNames) ? htmlspecialchars(implode(', ', $tNames)) : 'Class Educator' ?></strong>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span style="color: var(--admin-text-muted); font-size: 13px;">No periods scheduled for <?= $day ?>.</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
