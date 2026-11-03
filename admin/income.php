<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$message = '';
$error = '';

// Handle Delete Income Entry
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $delStmt = $db->prepare("DELETE FROM accounts_transactions WHERE id = ? AND type = 'Income'");
    $delStmt->execute([$deleteId]);
    $message = "Income entry deleted successfully.";
}

// Handle Add Income Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $transaction_date = sanitize($_POST['transaction_date'] ?? date('Y-m-d'));
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $reference_no = sanitize($_POST['reference_no'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (!empty($title) && !empty($category) && $amount > 0 && !empty($transaction_date)) {
        $txnNo = 'INC-' . date('Ym') . '-' . rand(100, 999);
        $stmt = $db->prepare("INSERT INTO accounts_transactions (transaction_no, type, category, title, amount, transaction_date, payment_method, reference_no, description, created_by) VALUES (?, 'Income', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $txnNo,
            $category,
            $title,
            $amount,
            $transaction_date,
            $payment_method,
            $reference_no,
            $description,
            get_current_user_id()
        ]);
        $message = "Income record added successfully!";
    } else {
        $error = "Please fill in all required fields (Title, Category, Date, and Amount > 0).";
    }
}

// Search & Filters
$search = sanitize($_GET['search'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');

$whereClauses = ["type = 'Income'"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(title LIKE ? OR reference_no LIKE ? OR description LIKE ? OR transaction_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterCategory)) {
    $whereClauses[] = "category = ?";
    $params[] = $filterCategory;
}

if (!empty($startDate)) {
    $whereClauses[] = "transaction_date >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereClauses[] = "transaction_date <= ?";
    $params[] = $endDate;
}

$whereSql = implode(' AND ', $whereClauses);

$incStmt = $db->prepare("SELECT * FROM accounts_transactions WHERE $whereSql ORDER BY transaction_date DESC, id DESC");
$incStmt->execute($params);
$incomes = $incStmt->fetchAll();

$categories = [
    'Student Tuition & Fees',
    'Admission Form & Registration',
    'Donations & Grants',
    'Event & Competition Fees',
    'Book & Uniform Sales',
    'Miscellaneous Income'
];

$totalFilteredIncome = array_sum(array_column($incomes, 'amount'));
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; color: #032d4e; margin: 0;">
      <i class="fas fa-hand-holding-usd" style="color: #10b981;"></i> Income & Revenue Ledger
    </h1>
    <p style="color: #64748b; margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Track student tuition fees, grants, donations, form sales, and school revenues.
    </p>
  </div>

  <button onclick="toggleAddForm()" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; padding: 0.8rem 1.4rem; border-radius: 12px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);">
    <i class="fas fa-plus-circle"></i> Add New Income Entry
  </button>
</div>

<?php if ($message): ?>
  <div class="alert-msg alert-success" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700;">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert-msg alert-danger" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<!-- Add Income Form Panel -->
<div id="addIncomeCard" style="display: <?= (isset($_GET['action']) && $_GET['action'] === 'add') || $error ? 'block' : 'none' ?>; background: #ffffff; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 2px solid #a7f3d0; margin-bottom: 2rem;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
    <h3 style="font-family: 'Outfit', sans-serif; color: #047857; margin: 0;">
      <i class="fas fa-plus-circle"></i> Record New Income Entry
    </h3>
    <button onclick="toggleAddForm()" style="background: none; border: none; font-size: 1.4rem; color: #94a3b8; cursor: pointer;">&times;</button>
  </div>

  <form method="POST">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
      
      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Income Title / Source *</label>
        <input type="text" name="title" class="form-control" placeholder="e.g., Monthly Tuition Fee Collection, Islamic Trust Grant" required style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Income Category *</label>
        <select name="category" class="form-control" required style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
          <option value="">-- Select Category --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>"><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Amount (৳) *</label>
        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Date Received *</label>
        <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Payment Method</label>
        <select name="payment_method" class="form-control" style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
          <option value="Cash">Cash</option>
          <option value="bKash / Mobile Banking">bKash / Mobile Banking</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="Cheque">Cheque</option>
        </select>
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Reference / Deposit Receipt No.</label>
        <input type="text" name="reference_no" class="form-control" placeholder="e.g., REF-OCT-102, TRF-889" style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

    </div>

    <div class="form-group" style="margin-top: 1.25rem;">
      <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Description / Remarks</label>
      <textarea name="description" class="form-control" rows="2" placeholder="Notes about this income entry..." style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;"></textarea>
    </div>

    <div style="margin-top: 1.5rem; text-align: right;">
      <button type="button" onclick="toggleAddForm()" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.75rem 1.4rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; margin-right: 0.5rem;">Cancel</button>
      <button type="submit" name="add_income" class="btn" style="background: #10b981; color: #ffffff; padding: 0.75rem 1.8rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer;">
        <i class="fas fa-save"></i> Save Income Record
      </button>
    </div>
  </form>
</div>

<!-- Filter & Search Bar -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
    <div style="flex: 1; min-width: 200px;">
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Search Keyword</label>
      <input type="text" name="search" class="form-control" placeholder="Search title, ref no..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
    </div>

    <div style="min-width: 180px;">
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Category</label>
      <select name="category" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">From Date</label>
      <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
    </div>

    <div>
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">To Date</label>
      <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
    </div>

    <div>
      <button type="submit" class="btn" style="background: #0e73b9; color: #ffffff; padding: 0.65rem 1.2rem; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
        <i class="fas fa-filter"></i> Filter
      </button>
      <a href="income.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.65rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none; margin-left: 0.3rem;">Reset</a>
    </div>
  </form>
</div>

<!-- Income Table -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <span style="font-weight: 700; color: #475569; font-size: 0.9rem;">Total Found: <strong><?= count($incomes) ?> records</strong></span>
    <span style="font-weight: 800; color: #16a34a; font-size: 1.05rem;">Filtered Total Income: <?= format_currency($totalFilteredIncome) ?></span>
  </div>

  <div style="overflow-x: auto;">
    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
          <th style="padding: 0.85rem;">Txn ID</th>
          <th style="padding: 0.85rem;">Date</th>
          <th style="padding: 0.85rem;">Category</th>
          <th style="padding: 0.85rem;">Income Source & Ref</th>
          <th style="padding: 0.85rem;">Amount (৳)</th>
          <th style="padding: 0.85rem;">Payment Method</th>
          <th style="padding: 0.85rem; text-align: center;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($incomes)): ?>
          <tr><td colspan="7" style="padding: 2.5rem; text-align: center; color: #94a3b8;">No income records match your filter criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($incomes as $inc): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.85rem; font-weight: 700; color: #0e73b9;"><code><?= htmlspecialchars($inc['transaction_no']) ?></code></td>
              <td style="padding: 0.85rem; font-weight: 600; color: #475569;"><?= format_date($inc['transaction_date']) ?></td>
              <td style="padding: 0.85rem;">
                <span style="background: #dcfce7; color: #15803d; padding: 0.25rem 0.65rem; border-radius: 12px; font-weight: 700; font-size: 0.75rem;">
                  <?= htmlspecialchars($inc['category']) ?>
                </span>
              </td>
              <td style="padding: 0.85rem;">
                <strong><?= htmlspecialchars($inc['title']) ?></strong>
                <?php if ($inc['reference_no']): ?>
                  <br><small style="color: #64748b;"><i class="fas fa-barcode"></i> Ref: <strong><?= htmlspecialchars($inc['reference_no']) ?></strong></small>
                <?php endif; ?>
                <?php if ($inc['description']): ?>
                  <br><small style="color: #94a3b8; font-style: italic;"><?= htmlspecialchars($inc['description']) ?></small>
                <?php endif; ?>
              </td>
              <td style="padding: 0.85rem; font-weight: 800; color: #16a34a; font-size: 0.95rem;">
                + <?= format_currency($inc['amount']) ?>
              </td>
              <td style="padding: 0.85rem; font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($inc['payment_method']) ?></td>
              <td style="padding: 0.85rem; text-align: center;">
                <a href="income.php?delete_id=<?= $inc['id'] ?>" onclick="return confirm('Are you sure you want to delete this income record?');" style="color: #ef4444; font-size: 1rem; padding: 0.4rem;" title="Delete Income Record">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  function toggleAddForm() {
    const card = document.getElementById('addIncomeCard');
    if(card.style.display === 'none' || card.style.display === '') {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
