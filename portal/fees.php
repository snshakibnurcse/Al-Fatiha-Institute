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

<?php if ($msg): ?><div class="alert-msg alert-success no-print"><i class="fas fa-check-circle"></i> <span><?= $msg ?></span></div><?php endif; ?>
<?php if ($err): ?><div class="alert-msg alert-danger no-print"><i class="fas fa-exclamation-triangle"></i> <span><?= $err ?></span></div><?php endif; ?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-family: 'Newsreader', Georgia, serif; font-size: 26px; font-weight: 500; color: var(--admin-navy); margin: 0;">Fee Statements &amp; Receipts</h2>
    <p style="margin: 4px 0 0 0; font-size: 13.5px; color: var(--admin-text-muted);">Tuition billing history and online advance payment.</p>
  </div>
  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <button class="btn-welcome-primary" style="background: #16a34a; cursor: pointer;" onclick="openModal('parentAdvanceModal')">
      <i class="fas fa-wallet"></i> Pay Advance Fees
    </button>
    <button id="printDocumentBtn" class="btn-welcome-secondary" style="cursor: pointer;">
      <i class="fas fa-print"></i> Print Receipt
    </button>
  </div>
</div>

<div class="card-box" style="padding: 28px;">

  <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--admin-navy); padding-bottom: 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <img src="../assets/images/logo.png?v=2" alt="AFI Logo" style="height: 58px; width: 58px; object-fit: contain;">
      <div>
        <h2 style="font-family: 'Newsreader', Georgia, serif; color: var(--admin-navy); margin: 0; font-size: 24px; font-weight: 500;">AL FATIHA INSTITUTE</h2>
        <span style="font-family: 'IBM Plex Mono', monospace; color: var(--admin-gold-dark); font-size: 12px;">Fees Statement &amp; Money Receipt</span>
        <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--admin-text-muted);">Tekpara Main Road, Cox's Bazar · Est. 2025</p>
      </div>
    </div>
    <span class="badge badge-success" style="font-size: 12px; padding: 6px 12px;">Money Receipt</span>
  </div>

  <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13.5px;">
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Student Name:</span> <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Student ID:</span> <code style="font-family: 'IBM Plex Mono', monospace;"><?= htmlspecialchars($student['student_id']) ?></code></p>
    </div>
    <div>
      <p style="margin: 0 0 6px 0;"><span style="color: var(--admin-text-muted);">Class:</span> <?= htmlspecialchars($student['class_name']) ?> (#<?= $student['roll_no'] ?>)</p>
      <p style="margin: 0;"><span style="color: var(--admin-text-muted);">Guardian:</span> <?= htmlspecialchars($student['father_name']) ?></p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Invoice No</th>
          <th>Fee Description</th>
          <th>Amount</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Payment Details</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($fees)): ?>
          <?php foreach ($fees as $f): ?>
            <tr>
              <td><code style="font-family: 'IBM Plex Mono', monospace; font-size: 12px;"><?= htmlspecialchars($f['invoice_no']) ?></code></td>
              <td><strong><?= htmlspecialchars($f['fee_type']) ?></strong></td>
              <td style="font-family: 'IBM Plex Mono', monospace; font-weight: 600; color: var(--admin-navy);"><?= format_currency($f['amount']) ?></td>
              <td style="font-family: 'IBM Plex Mono', monospace; font-size: 12.5px;"><?= format_date($f['due_date']) ?></td>
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
                  <span style="color: #dc2626; font-weight: 500; font-size: 13px;">Pending Payment</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--admin-text-muted);">No fee records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Parent Advance Fee Payment Modal -->
<div id="parentAdvanceModal" class="admin-modal-wrap" style="display: none; position: fixed; inset: 0; background: rgba(6, 28, 56, 0.55); z-index: 1000; justify-content: center; align-items: center; padding: 16px;">
  <div class="admin-modal-box" style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 14px; padding: 24px; border: 1px solid var(--admin-border); box-shadow: 0 16px 36px rgba(10, 20, 36, 0.14);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--admin-border);">
      <h3 style="color: var(--admin-navy); margin: 0; font-size: 18px; font-weight: 600;">
        Pay Advance Monthly Tuition
      </h3>
      <button type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--admin-text-muted);" onclick="closeModal('parentAdvanceModal')">&times;</button>
    </div>

    <form method="POST">
      <div style="background: var(--admin-bg); border: 1px solid var(--admin-border); padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px;">
        <strong>Student:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (<?= htmlspecialchars($student['class_name']) ?>)<br>
        <strong>Student ID:</strong> <code style="font-family: 'IBM Plex Mono', monospace;"><?= htmlspecialchars($student['student_id']) ?></code>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
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
        <label>Payment Gateway *</label>
        <select name="payment_method" class="form-control" required>
          <option value="bKash Online Payment">bKash Online Payment</option>
          <option value="Nagad Mobile Banking">Nagad Mobile Banking</option>
          <option value="Visa / MasterCard / Banking">Visa / MasterCard / Bank</option>
        </select>
      </div>

      <div class="form-group">
        <label>Transaction ID / Reference (Optional)</label>
        <input type="text" name="trx_id" class="form-control" placeholder="BKASH-991208">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
        <button type="button" class="btn-welcome-secondary" style="cursor: pointer;" onclick="closeModal('parentAdvanceModal')">Cancel</button>
        <button type="submit" name="pay_advance_fee" class="btn-welcome-primary" style="background: #16a34a; cursor: pointer;">
          Pay Now Online
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../admin/layout_footer.php'; ?>
