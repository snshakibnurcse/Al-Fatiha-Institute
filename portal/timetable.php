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

// RESTRICTED QUERY: Fetch Timetable ONLY for this specific child's class!
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

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">
      <i class="fas fa-calendar-alt" style="color: var(--admin-gold);"></i> Class Weekly Timetable & Routine
    </h2>
    <p style="color: var(--admin-text-muted); margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Official class routine for <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong> (Class: <?= htmlspecialchars($student['class_name']) ?>)
    </p>
  </div>
  <div>
    <button onclick="window.print()" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
      <i class="fas fa-print"></i> Print Class Routine
    </button>
  </div>
</div>

<div class="card-box" style="padding: 2.5rem; background: #ffffff;">

  <!-- Header Banner -->
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double var(--admin-primary); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <img src="../assets/images/logo.svg" alt="AFI Logo" style="height: 70px;">
      <div>
        <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0; font-size: 1.7rem;">AL FATIHA INSTITUTE</h2>
        <span style="color: var(--admin-gold-dark); font-weight: 800; font-size: 0.85rem; letter-spacing: 1px;">OFFICIAL CLASS TIMETABLE & ROUTINE</span>
      </div>
    </div>
    <div style="text-align: right;">
      <span style="background: var(--admin-primary); color: #ffffff; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">
        <?= htmlspecialchars($student['class_name']) ?>
      </span>
    </div>
  </div>

  <!-- Class & Teacher Details Box -->
  <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 12px; padding: 1.2rem; margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.92rem;">
    <div>
      <p style="margin-bottom: 0.4rem;"><strong>Student Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
      <p style="margin-bottom: 0.4rem;"><strong>Class Grade:</strong> <?= htmlspecialchars($student['class_name']) ?> (#<?= $student['roll_no'] ?>)</p>
      <p style="margin: 0;"><strong>Classroom:</strong> Room <?= htmlspecialchars($student['room_no']) ?></p>
    </div>
    <div>
      <p style="margin-bottom: 0.4rem;">
        <strong>Primary Class Teacher:</strong> 
        <span style="color: #10b981; font-weight: 700;"><?= htmlspecialchars($student['primary_teacher_name'] ?: 'Unassigned') ?></span>
      </p>
      <p style="margin-bottom: 0.4rem;">
        <strong>Backup Class Teacher:</strong> 
        <span style="color: #0284c7; font-weight: 700;"><?= htmlspecialchars($student['backup_teacher_name'] ?: 'None') ?></span>
      </p>
      <p style="margin: 0;"><strong>Academic Year:</strong> 2025 - 2026</p>
    </div>
  </div>

  <!-- Timetable Table -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 120px;">Day</th>
          <th>Class Period & Subject Routine</th>
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
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
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
                    <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-left: 4px solid var(--admin-primary); border-radius: 10px; padding: 0.85rem;">
                      <span style="background: #f1f5f9; color: #334155; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                        <i class="far fa-clock"></i> <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                      </span>
                      
                      <h4 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0.4rem 0 0.2rem 0; font-size: 0.95rem;">
                        <?= htmlspecialchars($slot['period_name']) ?>
                      </h4>

                      <p style="margin: 0; font-size: 0.85rem; color: var(--admin-gold-dark); font-weight: 700;">
                        <i class="fas fa-book"></i> <?= htmlspecialchars($slot['subject_name'] ?: 'General Activity') ?>
                      </p>

                      <p style="margin: 0.3rem 0 0 0; font-size: 0.82rem; color: #475569;">
                        <i class="fas fa-chalkboard-teacher" style="color: #10b981;"></i> 
                        Teacher: 
                        <strong><?= !empty($tNames) ? htmlspecialchars(implode(', ', $tNames)) : 'Class Educator' ?></strong>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span style="color: var(--admin-text-muted); font-size: 0.85rem;">No class routine scheduled for <?= $day ?>.</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
