<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$msg = '';
$err = '';

// Handle Approval / Rejection
if (isset($_GET['action']) && isset($_GET['app_id'])) {
    $action = sanitize($_GET['action']);
    $app_id = intval($_GET['app_id']);

    $appStmt = $db->prepare("SELECT a.*, c.class_name FROM admissions a JOIN classes c ON a.target_class_id = c.id WHERE a.id = ?");
    $appStmt->execute([$app_id]);
    $appData = $appStmt->fetch();

    if ($appData) {
        if ($action === 'approve' && $appData['status'] !== 'Approved') {
            try {
                $db->beginTransaction();

                // 1. Create Parent User Account
                $parent_username = 'parent_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', strtok($appData['child_name'], ' '))) . rand(10, 99);
                $parent_password_plain = 'parent123';
                $parent_password_hash = password_hash($parent_password_plain, PASSWORD_BCRYPT);
                
                $parentStmt = $db->prepare("INSERT INTO users (username, password, email, role, full_name, phone) VALUES (?, ?, ?, 'parent', ?, ?)");
                $parentStmt->execute([$parent_username, $parent_password_hash, $appData['email'] ?: 'parent@example.com', $appData['father_name'], $appData['phone']]);
                $parent_user_id = $db->lastInsertId();

                // 2. Generate Student ID (Specific Format: AFI-2027-XXX) & Sequential Roll No
                $student_code = generate_next_student_id('2027');
                $roll_no = get_next_class_roll($appData['target_class_id']);

                // Split child name
                $nameParts = explode(' ', trim($appData['child_name']), 2);
                $first_name = $nameParts[0];
                $last_name = $nameParts[1] ?? '';

                // Extract medical notes
                $medNotes = [];
                if (!empty($appData['has_allergies']) && $appData['has_allergies'] === 'Yes') {
                    $medNotes[] = "Allergies: " . ($appData['allergies_list'] ?? 'None');
                }
                if (!empty($appData['regular_medications'])) {
                    $medNotes[] = "Meds: " . $appData['regular_medications'];
                }
                $medical_notes = !empty($medNotes) ? implode('; ', $medNotes) : null;

                // 3. Insert Student Record with Full Profile Data
                $studentStmt = $db->prepare("INSERT INTO students (
                    student_id, first_name, last_name, gender, dob, class_id, roll_no, parent_user_id,
                    father_name, mother_name, phone, address, status, admission_date,
                    blood_group, religion, nationality, birth_reg_no, permanent_address,
                    father_occupation, father_phone, father_nid, mother_occupation, mother_phone,
                    emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                    previous_school, medical_notes, admission_session
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 'Active', CURRENT_DATE(),
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, '2027'
                )");

                $studentStmt->execute([
                    $student_code,
                    $first_name,
                    $last_name,
                    $appData['gender'],
                    $appData['dob'],
                    $appData['target_class_id'],
                    $roll_no,
                    $parent_user_id,
                    $appData['father_name'],
                    $appData['mother_name'],
                    $appData['phone'],
                    $appData['address'],
                    $appData['blood_group'] ?? null,
                    $appData['religion'] ?? 'Islam',
                    $appData['nationality'] ?? 'Bangladeshi',
                    $appData['birth_reg_or_passport'] ?? null,
                    $appData['permanent_address'] ?? $appData['address'],
                    $appData['father_occupation'] ?? null,
                    $appData['phone'],
                    $appData['father_nid_passport'] ?? null,
                    $appData['mother_occupation'] ?? null,
                    $appData['mother_phone'] ?? null,
                    $appData['emergency_name'] ?? $appData['father_name'],
                    $appData['emergency_phone'] ?? $appData['phone'],
                    $appData['emergency_relationship'] ?? 'Father',
                    $appData['previous_school'] ?? null,
                    $medical_notes
                ]);

                // 4. Update Admission Status
                $db->prepare("UPDATE admissions SET status = 'Approved' WHERE id = ?")->execute([$app_id]);

                $db->commit();
                $msg = "Application approved! Student record created (ID: <strong>$student_code</strong>) and Parent account generated (Username: <strong>$parent_username</strong> / Password: <strong>$parent_password_plain</strong>).";
            } catch (Exception $e) {
                $db->rollBack();
                $err = "Approval failed: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE admissions SET status = 'Rejected' WHERE id = ?")->execute([$app_id]);
            $msg = "Application marked as Rejected.";
        }
    }
}

// Fetch all applications
$applications = $db->query("SELECT a.*, c.class_name FROM admissions a JOIN classes c ON a.target_class_id = c.id ORDER BY a.applied_at DESC")->fetchAll();
?>

<?php if ($msg): ?>
  <div class="alert-msg alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert-msg alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div>
<?php endif; ?>

<div class="card-box">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-file-signature"></i> Online Admission Applications (44 Questions)</h3>
    <input type="text" id="tableSearch" placeholder="Search applications..." class="form-control" style="width: 250px; padding: 0.4rem 0.8rem; font-size: 0.85rem;">
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref No</th>
          <th>Child Name (Block Letter)</th>
          <th>Target Class</th>
          <th>Parents Info</th>
          <th>Phone</th>
          <th>Applied Date</th>
          <th>Status</th>
          <th style="min-width: 170px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($applications)): ?>
          <?php foreach ($applications as $a): ?>
            <tr>
              <td><code><?= htmlspecialchars($a['application_no']) ?></code></td>
              <td>
                <strong style="color: var(--primary);"><?= htmlspecialchars($a['child_name']) ?></strong><br>
                <small style="color: var(--admin-text-muted);"><?= $a['gender'] ?> &bull; DOB: <?= format_date($a['dob']) ?><?= !empty($a['blood_group']) ? ' ('.$a['blood_group'].')' : '' ?></small>
              </td>
              <td><span class="badge badge-info"><?= htmlspecialchars($a['class_name']) ?></span></td>
              <td>
                Father: <?= htmlspecialchars($a['father_name']) ?><br>
                Mother: <?= htmlspecialchars($a['mother_name'] ?: 'N/A') ?>
              </td>
              <td><?= htmlspecialchars($a['phone']) ?></td>
              <td><?= format_date($a['applied_at']) ?></td>
              <td>
                <?php if ($a['status'] == 'Approved'): ?>
                  <span class="badge badge-success">Approved</span>
                <?php elseif ($a['status'] == 'Rejected'): ?>
                  <span class="badge badge-danger">Rejected</span>
                <?php else: ?>
                  <span class="badge badge-warning">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                  <button type="button" class="btn" style="background: var(--sky-blue); color: #fff; padding: 0.25rem 0.5rem; font-size: 0.78rem; border-radius: 6px; border: none; cursor: pointer;" onclick="toggleDetails(<?= $a['id'] ?>)">
                    <i class="fas fa-eye"></i> Details
                  </button>

                  <?php if ($a['status'] == 'Pending'): ?>
                    <a href="admissions.php?action=approve&app_id=<?= $a['id'] ?>" class="btn" style="background: #10b981; color: #ffffff; padding: 0.25rem 0.5rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Approve this application and create student/parent accounts?')">Approve</a>
                    <a href="admissions.php?action=reject&app_id=<?= $a['id'] ?>" class="btn" style="background: #ef4444; color: #ffffff; padding: 0.25rem 0.5rem; font-size: 0.78rem; border-radius: 6px; text-decoration: none;" onclick="return confirm('Reject this application?')">Reject</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>

            <!-- Expandable Details Row for All 44 Questions -->
            <tr id="details-row-<?= $a['id'] ?>" style="display: none; background: #f8fafc;">
              <td colspan="8" style="padding: 1.5rem; border-left: 4px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.6rem; margin-bottom: 1.2rem;">
                  <h4 style="color: var(--primary-dark); margin: 0;">Full Application Details &bull; <?= htmlspecialchars($a['application_no']) ?></h4>
                  <button type="button" class="btn" style="background: #e2e8f0; color: #334155; font-size: 0.8rem; padding: 0.2rem 0.6rem;" onclick="toggleDetails(<?= $a['id'] ?>)">&times; Close</button>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; font-size: 0.88rem;">
                  <!-- Section 1 -->
                  <div style="background: #fff; padding: 1.2rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-bottom: 0.8rem;">1. STUDENT INFORMATION</h5>
                    <div><strong>1. Name:</strong> <?= htmlspecialchars($a['child_name']) ?></div>
                    <div><strong>2. Birth Reg / Passport:</strong> <?= htmlspecialchars($a['birth_reg_or_passport'] ?? 'N/A') ?></div>
                    <div><strong>3. Date of Birth:</strong> <?= format_date($a['dob']) ?></div>
                    <div><strong>4. Age (as of Jan 1):</strong> <?= htmlspecialchars($a['age_jan_1'] ?? 'N/A') ?></div>
                    <div><strong>5. Blood Group:</strong> <?= htmlspecialchars($a['blood_group'] ?? 'N/A') ?></div>
                    <div><strong>6. Nationality:</strong> <?= htmlspecialchars($a['nationality'] ?? 'Bangladeshi') ?></div>
                    <div><strong>7. Religion:</strong> <?= htmlspecialchars($a['religion'] ?? 'Islam') ?></div>
                    <div><strong>8. Gender:</strong> <?= htmlspecialchars($a['gender']) ?></div>
                    <div><strong>9. Previous School:</strong> <?= htmlspecialchars($a['previous_school'] ?? 'N/A') ?></div>
                    <div><strong>10. Present Address:</strong> <?= nl2br(htmlspecialchars($a['address'])) ?></div>
                    <div><strong>11. Permanent Address:</strong> <?= nl2br(htmlspecialchars($a['permanent_address'] ?? 'Same as Present')) ?></div>
                  </div>

                  <!-- Section 2 -->
                  <div style="background: #fff; padding: 1.2rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-bottom: 0.8rem;">2. GUARDIAN INFORMATION</h5>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 4px;">Father's Information</div>
                    <div><strong>12. Name:</strong> <?= htmlspecialchars($a['father_name']) ?></div>
                    <div><strong>13. Occupation:</strong> <?= htmlspecialchars($a['father_occupation'] ?? '—') ?></div>
                    <div><strong>14. Organization:</strong> <?= htmlspecialchars($a['father_organization'] ?? '—') ?></div>
                    <div><strong>15. Location:</strong> <?= htmlspecialchars($a['father_location'] ?? '—') ?></div>
                    <div><strong>16. Phone:</strong> <?= htmlspecialchars($a['phone']) ?></div>
                    <div><strong>17. NID / Passport:</strong> <?= htmlspecialchars($a['father_nid_passport'] ?? '—') ?></div>
                    <div><strong>18. Present Address:</strong> <?= htmlspecialchars($a['father_present_address'] ?? 'Same as Student') ?></div>

                    <div style="font-weight: 700; color: #0f172a; margin-top: 8px;">Mother's Information</div>
                    <div><strong>19. Name:</strong> <?= htmlspecialchars($a['mother_name'] ?? '—') ?></div>
                    <div><strong>20. Occupation:</strong> <?= htmlspecialchars($a['mother_occupation'] ?? '—') ?></div>
                    <div><strong>21. Organization:</strong> <?= htmlspecialchars($a['mother_organization'] ?? '—') ?></div>
                    <div><strong>22. Location:</strong> <?= htmlspecialchars($a['mother_location'] ?? '—') ?></div>
                    <div><strong>23. Phone:</strong> <?= htmlspecialchars($a['mother_phone'] ?? '—') ?></div>
                    <div><strong>24. NID / Passport:</strong> <?= htmlspecialchars($a['mother_nid_passport'] ?? '—') ?></div>
                    <div><strong>25. Present Address:</strong> <?= htmlspecialchars($a['mother_present_address'] ?? 'Same as Student') ?></div>

                    <?php if (!empty($a['guardian_is_other'])): ?>
                    <div style="font-weight: 700; color: #0f172a; margin-top: 8px;">Other Legal Guardian</div>
                    <div><strong>26. Name:</strong> <?= htmlspecialchars($a['other_guardian_name'] ?? '—') ?></div>
                    <div><strong>27. Relationship:</strong> <?= htmlspecialchars($a['other_guardian_relationship'] ?? '—') ?></div>
                    <div><strong>28. Phone:</strong> <?= htmlspecialchars($a['other_guardian_phone'] ?? '—') ?></div>
                    <div><strong>29. Email:</strong> <?= htmlspecialchars($a['other_guardian_email'] ?? '—') ?></div>
                    <div><strong>30. Present Address:</strong> <?= htmlspecialchars($a['other_guardian_present_address'] ?? '—') ?></div>
                    <div><strong>31. Permanent Address:</strong> <?= htmlspecialchars($a['other_guardian_permanent_address'] ?? '—') ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Section 3 & 4 -->
                  <div style="background: #fff; padding: 1.2rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-bottom: 0.8rem;">3. EMERGENCY CONTACT</h5>
                    <div><strong>32. Name:</strong> <?= htmlspecialchars($a['emergency_name'] ?? '—') ?></div>
                    <div><strong>33. Relationship:</strong> <?= htmlspecialchars($a['emergency_relationship'] ?? '—') ?></div>
                    <div><strong>34. Phone:</strong> <?= htmlspecialchars($a['emergency_phone'] ?? '—') ?></div>

                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-top: 1rem; margin-bottom: 0.8rem;">4. MEDICAL INFORMATION</h5>
                    <div><strong>35. Allergies:</strong> <?= htmlspecialchars($a['has_allergies'] ?? 'No') ?></div>
                    <?php if (!empty($a['allergies_list'])): ?>
                    <div><strong>36. Allergy List:</strong> <?= htmlspecialchars($a['allergies_list']) ?></div>
                    <?php endif; ?>
                    <div><strong>37. Medication:</strong> <?= htmlspecialchars($a['regular_medications'] ?? 'None') ?></div>
                    <div><strong>38. Medical Conditions:</strong> <?= htmlspecialchars($a['has_medical_conditions'] ?? 'No') ?></div>
                    <?php if (!empty($a['medical_conditions_details'])): ?>
                    <div><strong>39. Condition Details:</strong> <?= htmlspecialchars($a['medical_conditions_details']) ?></div>
                    <?php endif; ?>
                    <div><strong>40. Preferred Hospital:</strong> <?= htmlspecialchars($a['preferred_hospital'] ?? '—') ?></div>
                  </div>

                  <!-- Section 5, 6, 7 -->
                  <div style="background: #fff; padding: 1.2rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-bottom: 0.8rem;">5. TRANSPORTATION</h5>
                    <div><strong>41. Type:</strong> <?= htmlspecialchars($a['transportation_type'] ?? 'Own Transport') ?></div>
                    <div><strong>42. Pick-up Point:</strong> <?= htmlspecialchars($a['pickup_point'] ?? '—') ?></div>
                    <div><strong>43. Drop-off Point:</strong> <?= htmlspecialchars($a['dropoff_point'] ?? '—') ?></div>

                    <h5 style="color: var(--primary); border-bottom: 2px solid var(--accent-gold); padding-bottom: 0.3rem; margin-top: 1rem; margin-bottom: 0.8rem;">6. DOCUMENTS & CONSENT</h5>
                    <div><strong>Documents Attached:</strong></div>
                    <div style="font-size: 0.82rem; color: #334155; margin-left: 10px;">
                      <?= nl2br(htmlspecialchars($a['documents_submitted'] ?? 'Standard verification documents')) ?>
                    </div>
                    <div style="margin-top: 8px;"><strong>44. Consent Certified:</strong> <?= !empty($a['consent_agreed']) ? '✓ Yes, Certified by Parent' : 'No' ?></div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" style="text-align: center;">No applications found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleDetails(id) {
  const row = document.getElementById('details-row-' + id);
  if (row) {
    row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
  }
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
