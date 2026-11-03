<?php
require_once __DIR__ . '/layout.php';
$db = get_db_connection();

$message = '';
$error = '';

// Ensure voucher upload directory exists
$uploadDir = __DIR__ . '/../uploads/vouchers/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    // Check if voucher file exists to delete
    $stmt = $db->prepare("SELECT voucher_file FROM accounts_transactions WHERE id = ? AND type = 'Expense'");
    $stmt->execute([$deleteId]);
    $tx = $stmt->fetch();
    if ($tx) {
        if (!empty($tx['voucher_file']) && file_exists($uploadDir . $tx['voucher_file'])) {
            unlink($uploadDir . $tx['voucher_file']);
        }
        $delStmt = $db->prepare("DELETE FROM accounts_transactions WHERE id = ? AND type = 'Expense'");
        $delStmt->execute([$deleteId]);
        $message = "Expense record deleted successfully.";
    }
}

// Handle Add Expense Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $title = sanitize($_POST['title'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $transaction_date = sanitize($_POST['transaction_date'] ?? date('Y-m-d'));
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $reference_no = sanitize($_POST['reference_no'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (!empty($title) && !empty($category) && $amount > 0 && !empty($transaction_date)) {
        $voucherFilename = null;

        // Process Voucher File Upload if attached
        if (isset($_FILES['voucher_file']) && $_FILES['voucher_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['voucher_file']['tmp_name'];
            $fileName = $_FILES['voucher_file']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'voucher_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $voucherFilename = $newFileName;
                } else {
                    $error = "Failed to upload voucher file. Please check folder permissions.";
                }
            } else {
                $error = "Invalid voucher file type. Allowed formats: JPG, PNG, WEBP, PDF.";
            }
        }

        if (!$error) {
            $txnNo = 'EXP-' . date('Ym') . '-' . rand(100, 999);
            $stmt = $db->prepare("INSERT INTO accounts_transactions (transaction_no, type, category, title, amount, transaction_date, payment_method, reference_no, voucher_file, description, created_by) VALUES (?, 'Expense', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $txnNo,
                $category,
                $title,
                $amount,
                $transaction_date,
                $payment_method,
                $reference_no,
                $voucherFilename,
                $description,
                get_current_user_id()
            ]);
            $message = "Expense record added successfully with voucher document!";
        }
    } else {
        $error = "Please fill in all required fields (Title, Category, Date, and Amount > 0).";
    }
}

// Search & Filter Parameters
$search = sanitize($_GET['search'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');

$whereClauses = ["type = 'Expense'"];
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

$expStmt = $db->prepare("SELECT * FROM accounts_transactions WHERE $whereSql ORDER BY transaction_date DESC, id DESC");
$expStmt->execute($params);
$expenses = $expStmt->fetchAll();

// Expense Categories
$categories = [
    'Staff Salaries & Honorarium',
    'Electricity & Utilities',
    'Office Stationery & Supplies',
    'Building Rent & Maintenance',
    'Library Books & Exam Papers',
    'Computer & IT Infrastructure',
    'Refreshment & Catering',
    'Transportation & Conveyance',
    'Miscellaneous Expense'
];

$totalFilteredExpense = array_sum(array_column($expenses, 'amount'));
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; color: #032d4e; margin: 0;">
      <i class="fas fa-receipt" style="color: #ef4444;"></i> School Expenses & Vouchers Ledger
    </h1>
    <p style="color: #64748b; margin: 0.2rem 0 0 0; font-size: 0.95rem;">
      Manage operational costs, staff payments, bills, and upload supporting receipt memos.
    </p>
  </div>

  <button onclick="toggleAddForm()" class="btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff; padding: 0.8rem 1.4rem; border-radius: 12px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35);">
    <i class="fas fa-plus-circle"></i> Add New Expense & Voucher
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

<!-- Add Expense Form Panel (Toggleable / Modal) -->
<div id="addExpenseCard" style="display: <?= (isset($_GET['action']) && $_GET['action'] === 'add') || $error ? 'block' : 'none' ?>; background: #ffffff; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 2px solid #fee2e2; margin-bottom: 2rem;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
    <h3 style="font-family: 'Outfit', sans-serif; color: #b91c1c; margin: 0;">
      <i class="fas fa-file-signature"></i> Record New Expense with Voucher
    </h3>
    <button onclick="toggleAddForm()" style="background: none; border: none; font-size: 1.4rem; color: #94a3b8; cursor: pointer;">&times;</button>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
      
      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Expense Title / Purpose *</label>
        <input type="text" name="title" class="form-control" placeholder="e.g., October Electricity Bill, Phonics Books Purchase" required style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

      <div class="form-group">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Expense Category *</label>
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
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Date of Expense *</label>
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
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Bill / Memo / Voucher Reference No.</label>
        <input type="text" name="reference_no" class="form-control" placeholder="e.g., MEMO-9012, BILL-402" style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;">
      </div>

      <div class="form-group" style="grid-column: span 2;">
        <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">
          <i class="fas fa-paperclip" style="color: #ef4444;"></i> Attach Bill Voucher / Receipt Document (JPG, PNG, PDF)
        </label>
        <input type="file" name="voucher_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1.5px dashed #cbd5e1; background: #f8fafc;">
        <small style="color: #64748b; font-size: 0.75rem; display: block; margin-top: 0.25rem;">Optional: Upload supporting voucher memo image or bill scan copy.</small>
      </div>

    </div>

    <div class="form-group" style="margin-top: 1.25rem;">
      <label style="font-weight: 700; color: #334155; font-size: 0.85rem;">Additional Notes / Description</label>
      <textarea name="description" class="form-control" rows="2" placeholder="Enter details about this expense item..." style="width: 100%; padding: 0.8rem; border-radius: 10px; border: 1.5px solid #cbd5e1;"></textarea>
    </div>

    <div style="margin-top: 1.5rem; text-align: right;">
      <button type="button" onclick="toggleAddForm()" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.75rem 1.4rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; margin-right: 0.5rem;">Cancel</button>
      <button type="submit" name="add_expense" class="btn" style="background: #ef4444; color: #ffffff; padding: 0.75rem 1.8rem; border-radius: 10px; font-weight: 800; border: none; cursor: pointer;">
        <i class="fas fa-save"></i> Save Expense Record
      </button>
    </div>
  </form>
</div>

<!-- Filter & Search Bar -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
  <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
    <div style="flex: 1; min-width: 200px;">
      <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Search Keyword</label>
      <input type="text" name="search" class="form-control" placeholder="Search title, voucher no..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
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
      <a href="expenses.php" class="btn" style="background: #f1f5f9; color: #475569; padding: 0.65rem 1rem; border-radius: 8px; font-weight: 700; text-decoration: none; margin-left: 0.3rem;">Reset</a>
    </div>
  </form>
</div>

<!-- Expense Ledger Table -->
<div style="background: #ffffff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <span style="font-weight: 700; color: #475569; font-size: 0.9rem;">Total Found: <strong><?= count($expenses) ?> records</strong></span>
    <span style="font-weight: 800; color: #dc2626; font-size: 1.05rem;">Filtered Total Expense: <?= format_currency($totalFilteredExpense) ?></span>
  </div>

  <div style="overflow-x: auto;">
    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
      <thead>
        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
          <th style="padding: 0.85rem;">Txn ID</th>
          <th style="padding: 0.85rem;">Date</th>
          <th style="padding: 0.85rem;">Category</th>
          <th style="padding: 0.85rem;">Expense Title & Memo Ref</th>
          <th style="padding: 0.85rem;">Amount (৳)</th>
          <th style="padding: 0.85rem;">Method</th>
          <th style="padding: 0.85rem;">Voucher Attachment</th>
          <th style="padding: 0.85rem; text-align: center;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($expenses)): ?>
          <tr><td colspan="8" style="padding: 2.5rem; text-align: center; color: #94a3b8;">No expense records match your filter criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($expenses as $exp): ?>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 0.85rem; font-weight: 700; color: #0e73b9;"><code><?= htmlspecialchars($exp['transaction_no']) ?></code></td>
              <td style="padding: 0.85rem; font-weight: 600; color: #475569;"><?= format_date($exp['transaction_date']) ?></td>
              <td style="padding: 0.85rem;">
                <span style="background: #f1f5f9; color: #334155; padding: 0.25rem 0.65rem; border-radius: 12px; font-weight: 700; font-size: 0.75rem;">
                  <?= htmlspecialchars($exp['category']) ?>
                </span>
              </td>
              <td style="padding: 0.85rem;">
                <strong><?= htmlspecialchars($exp['title']) ?></strong>
                <?php if ($exp['reference_no']): ?>
                  <br><small style="color: #64748b;"><i class="fas fa-barcode"></i> Voucher Ref: <strong><?= htmlspecialchars($exp['reference_no']) ?></strong></small>
                <?php endif; ?>
                <?php if ($exp['description']): ?>
                  <br><small style="color: #94a3b8; font-style: italic;"><?= htmlspecialchars($exp['description']) ?></small>
                <?php endif; ?>
              </td>
              <td style="padding: 0.85rem; font-weight: 800; color: #dc2626; font-size: 0.95rem;">
                - <?= format_currency($exp['amount']) ?>
              </td>
              <td style="padding: 0.85rem; font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($exp['payment_method']) ?></td>
              <td style="padding: 0.85rem;">
                <?php if ($exp['voucher_file']): ?>
                  <button onclick="previewVoucher('../uploads/vouchers/<?= htmlspecialchars($exp['voucher_file']) ?>', '<?= htmlspecialchars($exp['title']) ?>')" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.35rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fas fa-file-image"></i> View Voucher Memo
                  </button>
                <?php else: ?>
                  <span style="color: #cbd5e1; font-size: 0.8rem;">No voucher</span>
                <?php endif; ?>
              </td>
              <td style="padding: 0.85rem; text-align: center;">
                <a href="expenses.php?delete_id=<?= $exp['id'] ?>" onclick="return confirm('Are you sure you want to delete this expense record?');" style="color: #ef4444; font-size: 1rem; padding: 0.4rem;" title="Delete Expense">
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

<!-- Modal for Viewing Voucher Document -->
<div id="voucherModal" class="modal-overlay">
  <div class="modal-content">
    <button class="close-modal" onclick="closeVoucherModal()">&times;</button>
    <h3 id="voucherModalTitle" style="font-family: 'Outfit', sans-serif; color: #032d4e; margin-top: 0; margin-bottom: 1rem;">
      Supporting Voucher / Document
    </h3>
    <div id="voucherMediaContainer" style="text-align: center; margin-bottom: 1.5rem;">
      <!-- Image or Link inserted by JS -->
    </div>
    <div style="text-align: right;">
      <a id="voucherDownloadBtn" href="#" download class="btn" style="background: #0e73b9; color: #ffffff; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
        <i class="fas fa-download"></i> Download Voucher
      </a>
      <button onclick="closeVoucherModal()" class="btn" style="background: #f1f5f9; color: #334155; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; margin-left: 0.5rem;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
  function toggleAddForm() {
    const card = document.getElementById('addExpenseCard');
    if(card.style.display === 'none' || card.style.display === '') {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  }

  function previewVoucher(filePath, title) {
    const modal = document.getElementById('voucherModal');
    const container = document.getElementById('voucherMediaContainer');
    const downloadBtn = document.getElementById('voucherDownloadBtn');
    document.getElementById('voucherModalTitle').innerText = 'Voucher Memo: ' + title;
    
    downloadBtn.href = filePath;
    
    const ext = filePath.split('.').pop().toLowerCase();
    if(['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) {
      container.innerHTML = `<img src="${filePath}" alt="Voucher Memo" style="max-width: 100%; max-height: 450px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">`;
    } else {
      container.innerHTML = `<div style="padding: 3rem; background: #f8fafc; border-radius: 12px; color: #475569;">
        <i class="fas fa-file-pdf" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
        <p>Document format: <strong>.${ext.toUpperCase()}</strong></p>
        <a href="${filePath}" target="_blank" class="btn" style="background: #0284c7; color: #ffffff; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700;">
          <i class="fas fa-external-link-alt"></i> Open Document in New Tab
        </a>
      </div>`;
    }
    
    modal.style.display = 'flex';
  }

  function closeVoucherModal() {
    document.getElementById('voucherModal').style.display = 'none';
  }
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
