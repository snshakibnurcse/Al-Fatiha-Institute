<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$user_id = get_current_user_id();
$stStmt = $db->prepare("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.parent_user_id = ? LIMIT 1");
$stStmt->execute([$user_id]);
$student = $stStmt->fetch();
if (!$student) {
    $student = $db->query("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = 1")->fetch();
}

$st_id = $student['id'];
$msg = '';
$err = '';

// Handle Parent Advance Fee Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_advance_fee'])) {
    $start_month = sanitize($_POST['start_month'] ?? date('Y-m'));
    $num_months = intval($_POST['num_months'] ?? 1);
    $payment_method = sanitize($_POST['payment_method'] ?? 'bKash Online');
    $trx_id = sanitize($_POST['trx_id'] ?? ('TRX-' . rand(100000, 999999)));

    $result = generate_advance_fee_invoice($st_id, $start_month, $num_months, true, $payment_method, $trx_id);
    if ($result['success']) {
        $msg = $result['message'];
    } else {
        $err = $result['message'];
    }
}

$fees = $db->query("SELECT * FROM fees WHERE student_id = $st_id ORDER BY id DESC")->fetchAll();
?>

<?php if ($msg): ?><div class="alert-msg alert-success no-print"><i class="fas fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger no-print"><i class="fas fa-exclamation-triangle"></i> <?= $err ?></div><?php endif; ?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <h3 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0;">Fee Statements & Payment Records</h3>
  <div style="display: flex; gap: 0.5rem;">
    <button class="btn" style="background: #10b981; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" onclick="openModal('parentAdvanceModal')">
      <i class="fas fa-wallet"></i> Pay Advance Fees
    </button>
    <button id="printDocumentBtn" class="btn" style="background: var(--admin-primary); color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
      <i class="fas fa-print"></i> Print Payment Receipt
    </button>
  </div>
</div>

<div class="card-box" style="padding: 2.5rem; background: #ffffff;">
  
  <!-- Printable Money Receipt Header -->
  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double var(--admin-primary); padding-bottom: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
      <img src="../assets/images/logo.png" alt="AFI Logo" style="height: 75px;">
      <div>
        <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-primary-dark); margin: 0; font-size: 1.8rem;">AL FATIHA INSTITUTE</h2>
        <span style="color: var(--admin-gold-dark); font-weight: 800; font-size: 0.85rem; letter-spacing: 1px;">FEES STATEMENT & PAYMENT RECEIPT</span>
        <p style="margin: 0; font-size: 0.82rem; color: var(--admin-text-muted);">Main Road, Cox's Bazar, Bangladesh • ESTD 2025</p>
      </div>
    </div>
    <div style="text-align: right;">
      <span style="background: #10b981; color: #ffffff; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 800; font-size: 0.9rem; text-transform: uppercase;">
        MONEY RECEIPT
      </span>
    </div>
  </div>

  <!-- Student Info Box -->
  <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 12px; padding: 1.2rem; margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.92rem;">
    <div>
      <p style="margin-bottom: 0.4rem;"><strong>Student Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
      <p style="margin-bottom: 0.4rem;"><strong>Student ID:</strong> <code><?= htmlspecialchars($student['student_id']) ?></code></p>
    </div>
    <div>
      <p style="margin-bottom: 0.4rem;"><strong>Class:</strong> <?= htmlspecialchars($student['class_name']) ?> (#<?= $student['roll_no'] ?>)</p>
      <p style="margin-bottom: 0.4rem;"><strong>Father / Guardian:</strong> <?= htmlspecialchars($student['father_name']) ?></p>
    </div>
  </div>

  <!-- Invoices Table -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Invoice No</th>
          <th>Fee Description</th>
          <th>Amount</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Payment Date & Method</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($fees)): ?>
          <?php foreach ($fees as $f): ?>
            <tr>
              <td><code><?= htmlspecialchars($f['invoice_no']) ?></code></td>
              <td><strong><?= htmlspecialchars($f['fee_type']) ?></strong></td>
              <td><strong style="color: var(--admin-primary);"><?= format_currency($f['amount']) ?></strong></td>
              <td><?= format_date($f['due_date']) ?></td>
              <td>
                <?php if ($f['status'] == 'Paid'): ?>
                  <span class="badge badge-success">Paid</span>
                <?php else: ?>
                  <span class="badge badge-danger">Unpaid</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($f['status'] == 'Paid'): ?>
                  <?= format_date($f['paid_date']) ?> (<?= htmlspecialchars($f['payment_method'] ?: 'Online') ?>)
                <?php else: ?>
                  <span style="color: #ef4444; font-weight: 700; font-size: 0.85rem;">Pending Payment</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center;">No fee records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Parent Advance Fee Payment Modal -->
<div id="parentAdvanceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
  <div style="background: #ffffff; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-family: 'Outfit', sans-serif; color: #10b981; margin: 0;">
        <i class="fas fa-wallet"></i> Pay Advance Monthly Tuition
      </h3>
      <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('parentAdvanceModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="background: #f8fafc; border: 1px solid var(--admin-border); padding: 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: 0.9rem;">
        <strong>Student:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['class_name']) ?>)<br>
        <strong>Student ID:</strong> <code><?= htmlspecialchars($student['student_id']) ?></code>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label>Starting Month *</label>
          <input type="month" name="start_month" class="form-control" value="<?= date('Y-m') ?>" required>
        </div>
        <div class="form-group">
          <label>Duration *</label>
          <select name="num_months" class="form-control" required>
            <option value="1">1 Month</option>
            <option value="2">2 Months</option>
            <option value="3" selected>3 Months (Quarterly)</option>
            <option value="6">6 Months (Half-Yearly)</option>
            <option value="12">12 Months (Full Year)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Select Payment Gateway *</label>
        <select name="payment_method" class="form-control" required>
          <option value="bKash Online Payment">bKash Online Payment (Recommended)</option>
          <option value="Nagad Mobile Banking">Nagad Mobile Banking</option>
          <option value="Visa / MasterCard / Banking">Visa / MasterCard / Bank</option>
        </select>
      </div>

      <div class="form-group">
        <label>Transaction ID / Reference (Optional)</label>
        <input type="text" name="trx_id" class="form-control" placeholder="e.g. BKASH-991208">
      </div>

      <div style="text-align: right; margin-top: 1.5rem;">
        <button type="button" class="btn" style="background: #e2e8f0; color: var(--admin-text); padding: 0.6rem 1.2rem; border-radius: 8px; border: none; margin-right: 0.5rem; cursor: pointer;" onclick="closeModal('parentAdvanceModal')">Cancel</button>
        <button type="submit" name="pay_advance_fee" class="btn" style="background: #10b981; color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700;">
          <i class="fas fa-lock"></i> Pay Now Online
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
