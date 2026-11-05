<?php
// Unified A4 Split Money Receipt Modal (Student Copy & Office Copy)
// Fits perfectly on a single A4 page with center cutting line.
?>
<div id="receiptSlipModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 780px; width: 95%; max-height: 92vh; display: flex; flex-direction: column; padding: 1.2rem 1.5rem;">
    
    <!-- Modal Toolbar (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 0.8rem; flex-shrink: 0;">
      <div>
        <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy, #064878); font-size: 1.25rem;">
          <i class="fas fa-receipt" style="color: #f59e0b;"></i> Official Money Receipt (A4 Dual Copy)
        </h3>
        <small style="color: #64748b;">A4 Split Page: Top half Student Copy &bull; Bottom half Office Copy with Perforation</small>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn" onclick="printReceiptSlip()" style="background: #0284c7; color: #ffffff; padding: 0.45rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
          <i class="fas fa-print"></i> Print A4 Receipt
        </button>
        <button type="button" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #64748b; line-height: 1;" onclick="closeModal('receiptSlipModal')">&times;</button>
      </div>
    </div>

    <!-- Scrollable Preview Container for Modal View -->
    <div style="overflow-y: auto; flex: 1; padding-right: 4px;">
      
      <!-- PRINTABLE DUAL RECEIPT AREA (Captures exact A4 Print) -->
      <div id="a4ReceiptSlipPrintArea" class="a4-receipt-page">
        
        <!-- ==============================================
             PART 1: STUDENT COPY (TOP HALF)
             ============================================== -->
        <div class="receipt-half student-copy">
          <div class="receipt-inner">
            <!-- Header -->
            <div class="receipt-header">
              <div class="receipt-logo-wrap">
                <img src="../assets/images/logo.png?v=2" onerror="if(this.src.indexOf('../')!==-1)this.src='assets/images/logo.png?v=2';" alt="Al Fatiha Institute Logo" class="receipt-logo">
              </div>
              <div class="receipt-school-info">
                <h2 class="receipt-school-name">AL FATIHA INSTITUTE</h2>
                <div class="receipt-school-sub">An Islamic Cambridge Integrated Curriculum School</div>
                <div class="receipt-school-addr">Engg. Edris Building, Kalur Dokan, Tekpara Main Road, Cox's Bazar &bull; ☎ 01611477441, 01632550045 &bull; ✉ alfatihacxb@gmail.com</div>
              </div>
              <div class="receipt-badge-col">
                <span class="copy-tag student-tag">STUDENT COPY (ছাত্র কপি)</span>
                <span class="status-pill slip-status-pill">PAID</span>
              </div>
            </div>

            <!-- Student & Invoice Meta Grid -->
            <div class="receipt-meta-grid">
              <div class="meta-item"><span class="lbl">Student Name:</span> <strong class="val slip-student-name">--</strong></div>
              <div class="meta-item"><span class="lbl">Receipt / Inv:</span> <strong class="val slip-inv-no monospace-text">--</strong></div>
              <div class="meta-item"><span class="lbl">Student ID:</span> <strong class="val slip-student-code monospace-text">--</strong></div>
              <div class="meta-item"><span class="lbl">Date:</span> <strong class="val slip-paid-date">--</strong></div>
              <div class="meta-item"><span class="lbl">Class &amp; Roll:</span> <span class="val"><strong class="slip-class-name">--</strong> (Roll: <strong class="slip-roll-no">--</strong>)</span></div>
              <div class="meta-item"><span class="lbl">Pay Method:</span> <span class="val slip-pay-method">Cash</span></div>
              <div class="meta-item"><span class="lbl">Session / Month:</span> <span class="val slip-fee-month">2027</span></div>
              <div class="meta-item"><span class="lbl">Trx Ref No:</span> <span class="val slip-ref-no">N/A</span></div>
            </div>

            <!-- Fee Particulars Table -->
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 32px; text-align: center;">SL</th>
                  <th>Fee Particulars / Item Description</th>
                  <th style="width: 130px; text-align: center;">Month / Term</th>
                  <th style="width: 110px; text-align: right;">Amount (৳)</th>
                </tr>
              </thead>
              <tbody class="slip-items-tbody">
                <tr>
                  <td style="text-align: center;">01</td>
                  <td><strong class="slip-item-title">Monthly Tuition Fee</strong></td>
                  <td style="text-align: center;" class="slip-item-period">November 2026</td>
                  <td style="text-align: right;"><strong class="slip-item-amount">0.00</strong></td>
                </tr>
              </tbody>
            </table>

            <!-- Financial Summary Bar -->
            <div class="receipt-totals-bar">
              <div class="total-box">
                <span class="t-lbl">Total Invoice:</span>
                <span class="t-val slip-total-bill">৳ 0.00</span>
              </div>
              <div class="total-box paid-box">
                <span class="t-lbl">Amount Paid:</span>
                <span class="t-val slip-total-paid">৳ 0.00</span>
              </div>
              <div class="total-box due-box">
                <span class="t-lbl">Remaining Due:</span>
                <span class="t-val slip-due-balance">৳ 0.00</span>
              </div>
            </div>

            <!-- Receipt Footer & Signatures -->
            <div class="receipt-footer-row">
              <div class="footer-note">
                <i class="fas fa-check-double" style="color: #059669;"></i> Valid computer-generated official receipt. Please preserve for records.
              </div>
              <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-title">Student / Guardian</div>
              </div>
              <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-title">Authorized Cashier / Principal</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==============================================
             PERFORATION CUT LINE (MIDDLE DIVIDER)
             ============================================== -->
        <div class="receipt-cut-line">
          <div class="cut-line-dash"></div>
          <span class="cut-line-badge">
            ✂ &nbsp; CUT HERE / মাঝখানে কাটুন &nbsp; (▲ STUDENT COPY &nbsp;|&nbsp; OFFICE COPY ▼) &nbsp; ✂
          </span>
        </div>

        <!-- ==============================================
             PART 2: OFFICE COPY (BOTTOM HALF)
             ============================================== -->
        <div class="receipt-half office-copy">
          <div class="receipt-inner">
            <!-- Header -->
            <div class="receipt-header">
              <div class="receipt-logo-wrap">
                <img src="../assets/images/logo.png?v=2" onerror="if(this.src.indexOf('../')!==-1)this.src='assets/images/logo.png?v=2';" alt="Al Fatiha Institute Logo" class="receipt-logo">
              </div>
              <div class="receipt-school-info">
                <h2 class="receipt-school-name">AL FATIHA INSTITUTE</h2>
                <div class="receipt-school-sub">An Islamic Cambridge Integrated Curriculum School</div>
                <div class="receipt-school-addr">Engg. Edris Building, Kalur Dokan, Tekpara Main Road, Cox's Bazar &bull; ☎ 01611477441, 01632550045 &bull; ✉ alfatihacxb@gmail.com</div>
              </div>
              <div class="receipt-badge-col">
                <span class="copy-tag office-tag">OFFICE COPY (অফিস কপি)</span>
                <span class="status-pill slip-status-pill">PAID</span>
              </div>
            </div>

            <!-- Student & Invoice Meta Grid -->
            <div class="receipt-meta-grid">
              <div class="meta-item"><span class="lbl">Student Name:</span> <strong class="val slip-student-name">--</strong></div>
              <div class="meta-item"><span class="lbl">Receipt / Inv:</span> <strong class="val slip-inv-no monospace-text">--</strong></div>
              <div class="meta-item"><span class="lbl">Student ID:</span> <strong class="val slip-student-code monospace-text">--</strong></div>
              <div class="meta-item"><span class="lbl">Date:</span> <strong class="val slip-paid-date">--</strong></div>
              <div class="meta-item"><span class="lbl">Class &amp; Roll:</span> <span class="val"><strong class="slip-class-name">--</strong> (Roll: <strong class="slip-roll-no">--</strong>)</span></div>
              <div class="meta-item"><span class="lbl">Pay Method:</span> <span class="val slip-pay-method">Cash</span></div>
              <div class="meta-item"><span class="lbl">Session / Month:</span> <span class="val slip-fee-month">2027</span></div>
              <div class="meta-item"><span class="lbl">Trx Ref No:</span> <span class="val slip-ref-no">N/A</span></div>
            </div>

            <!-- Fee Particulars Table -->
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 32px; text-align: center;">SL</th>
                  <th>Fee Particulars / Item Description</th>
                  <th style="width: 130px; text-align: center;">Month / Term</th>
                  <th style="width: 110px; text-align: right;">Amount (৳)</th>
                </tr>
              </thead>
              <tbody class="slip-items-tbody">
                <tr>
                  <td style="text-align: center;">01</td>
                  <td><strong class="slip-item-title">Monthly Tuition Fee</strong></td>
                  <td style="text-align: center;" class="slip-item-period">November 2026</td>
                  <td style="text-align: right;"><strong class="slip-item-amount">0.00</strong></td>
                </tr>
              </tbody>
            </table>

            <!-- Financial Summary Bar -->
            <div class="receipt-totals-bar">
              <div class="total-box">
                <span class="t-lbl">Total Invoice:</span>
                <span class="t-val slip-total-bill">৳ 0.00</span>
              </div>
              <div class="total-box paid-box">
                <span class="t-lbl">Amount Paid:</span>
                <span class="t-val slip-total-paid">৳ 0.00</span>
              </div>
              <div class="total-box due-box">
                <span class="t-lbl">Remaining Due:</span>
                <span class="t-val slip-due-balance">৳ 0.00</span>
              </div>
            </div>

            <!-- Receipt Footer & Signatures -->
            <div class="receipt-footer-row">
              <div class="footer-note">
                <i class="fas fa-university" style="color: #064878;"></i> Accounts &amp; Audit Copy &bull; Preserved in School Ledger.
              </div>
              <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-title">Depositor / Parent</div>
              </div>
              <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-title">Accounts Officer Seal</div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- End a4ReceiptSlipPrintArea -->
    </div><!-- End Scroll Container -->

    <!-- Modal Footer Actions (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 0.8rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem; flex-shrink: 0;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('receiptSlipModal')" style="padding: 0.5rem 1.2rem; border-radius: 8px;">Close</button>
      <button type="button" class="btn btn-primary" onclick="printReceiptSlip()" style="background: #0284c7; color: #ffffff; font-weight: 700; padding: 0.5rem 1.4rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fas fa-print"></i> Print Receipt Slip (A4)
      </button>
    </div>

  </div>
</div>

<!-- ========================================================
     A4 RECEIPT SLIP STYLING (ON-SCREEN & PRINT ENGINE)
     ======================================================== -->
<style>
/* Clean Base Styles for A4 Sheet Container */
.a4-receipt-page {
  background: #ffffff;
  width: 100%;
  max-width: 720px;
  margin: 0 auto;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #1e293b;
}

/* Individual Receipt Half (Upper and Lower) */
.receipt-half {
  background: #ffffff;
  border: 1.5px solid #064878;
  border-radius: 8px;
  padding: 10px 14px;
  box-sizing: border-box;
  position: relative;
}

.student-copy {
  border-color: #0284c7;
}

.office-copy {
  border-color: #064878;
}

/* Receipt Header */
.receipt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border-bottom: 1.5px solid #064878;
  padding-bottom: 6px;
  margin-bottom: 8px;
}

.receipt-logo-wrap {
  flex-shrink: 0;
}

.receipt-logo {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: contain;
  display: block;
}

.receipt-school-info {
  flex: 1;
  text-align: center;
}

.receipt-school-name {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #064878;
  letter-spacing: 0.6px;
  line-height: 1.2;
}

.receipt-school-sub {
  font-size: 0.68rem;
  color: #0284c7;
  font-weight: 700;
  margin-top: 1px;
}

.receipt-school-addr {
  font-size: 0.62rem;
  color: #64748b;
  margin-top: 1px;
  line-height: 1.25;
}

.receipt-badge-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}

.copy-tag {
  display: inline-block;
  padding: 2px 7px;
  font-size: 0.62rem;
  font-weight: 800;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  white-space: nowrap;
}

.student-tag {
  background: #0284c7;
  color: #ffffff;
}

.office-tag {
  background: #064878;
  color: #ffffff;
}

.status-pill {
  display: inline-block;
  padding: 2px 7px;
  font-size: 0.62rem;
  font-weight: 800;
  border-radius: 12px;
  text-transform: uppercase;
}

.status-paid {
  background: #d1fae5;
  color: #065f46;
}

.status-partial {
  background: #fef3c7;
  color: #b45309;
}

.status-unpaid {
  background: #fee2e2;
  color: #991b1b;
}

/* Metadata Grid */
.receipt-meta-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr 1.3fr 1fr;
  gap: 4px 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 6px 10px;
  margin-bottom: 8px;
  font-size: 0.76rem;
  line-height: 1.3;
}

.meta-item .lbl {
  color: #64748b;
  font-weight: 600;
}

.meta-item .val {
  color: #0f172a;
}

.monospace-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Particulars Table */
.receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.74rem;
  margin-bottom: 6px;
}

.receipt-table th {
  background: #f1f5f9;
  color: #334155;
  font-weight: 700;
  padding: 4px 6px;
  border: 1px solid #cbd5e1;
  font-size: 0.72rem;
  text-transform: uppercase;
}

.receipt-table td {
  padding: 4px 6px;
  border: 1px solid #e2e8f0;
  color: #1e293b;
}

/* Totals Summary Bar */
.receipt-totals-bar {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 5px 12px;
  margin-bottom: 8px;
}

.total-box {
  display: flex;
  align-items: baseline;
  gap: 5px;
  font-size: 0.75rem;
}

.total-box .t-lbl {
  color: #64748b;
  font-weight: 700;
}

.total-box .t-val {
  font-weight: 800;
  font-size: 0.85rem;
  color: #0f172a;
}

.paid-box .t-val {
  color: #059669;
}

.due-box .t-val {
  color: #dc2626;
}

/* Receipt Footer & Signatures */
.receipt-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-top: 6px;
  margin-top: 4px;
}

.footer-note {
  font-size: 0.65rem;
  color: #64748b;
  max-width: 50%;
  line-height: 1.25;
}

.signature-box {
  text-align: center;
  width: 125px;
}

.sig-line {
  border-bottom: 1px dashed #64748b;
  margin-bottom: 3px;
  width: 100%;
}

.sig-title {
  font-size: 0.64rem;
  font-weight: 700;
  color: #475569;
}

/* Middle Perforation Cut Line */
.receipt-cut-line {
  position: relative;
  text-align: center;
  margin: 10px 0;
  height: 14px;
}

.cut-line-dash {
  position: absolute;
  top: 50%;
  left: 0;
  width: 100%;
  border-top: 1.5px dashed #94a3b8;
  z-index: 1;
}

.cut-line-badge {
  position: relative;
  z-index: 2;
  background: #ffffff;
  padding: 0 12px;
  font-size: 0.66rem;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.5px;
}

/* ========================================================
   EXACT A4 PRINT ENGINE (NEVER BREAKS ACROSS PAGES)
   ======================================================== */
@page {
  size: A4 portrait;
  margin: 6mm 8mm;
}

@media print {
  /* Hide all dashboard chrome and active page elements when printing */
  body.printing-receipt-slip * {
    visibility: hidden !important;
  }

  body.printing-receipt-slip #a4ReceiptSlipPrintArea,
  body.printing-receipt-slip #a4ReceiptSlipPrintArea * {
    visibility: visible !important;
  }

  body.printing-receipt-slip #a4ReceiptSlipPrintArea {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
    box-shadow: none !important;
    border: none !important;
    display: block !important;
  }

  body.printing-receipt-slip .receipt-half {
    border-width: 1.5px !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .no-print {
    display: none !important;
  }
}
</style>

<!-- ========================================================
     RECEIPT DATA POPULATION & PRINT SCRIPT
     ======================================================== -->
<script>
/**
 * Open and Populate the A4 Split Money Receipt Slip
 */
function openReceiptModal(data, monthName) {
  if (!data) return;

  var totalAmt = parseFloat(data.amount || data.total_amount || 0);
  var paidAmt = parseFloat(data.paid_amount !== undefined ? data.paid_amount : (data.status === 'Paid' ? totalAmt : (data.paid_amount_now || 0)));
  var dueAmt = Math.max(0, totalAmt - paidAmt);
  
  var status = data.status || (dueAmt <= 0.01 ? 'Paid' : (paidAmt > 0 ? 'Partial' : 'Unpaid'));
  if (dueAmt <= 0.01 && paidAmt >= totalAmt && totalAmt > 0) {
    status = 'Paid';
  }

  var studentName = ((data.first_name || '') + ' ' + (data.last_name || '')).trim() || data.student_name || 'Student';
  var studentId = data.student_code || data.student_id || 'AFI-ST';
  var className = data.class_name || 'N/A';
  var rollNo = data.roll_no ? '#' + data.roll_no : '-';
  var invNo = data.invoice_no || 'INV-' + Math.floor(Math.random() * 9000 + 1000);
  var paidDate = data.paid_date || (new Date()).toISOString().split('T')[0];
  var payMethod = data.payment_method || 'Cash (নগদ)';
  var refNo = data.reference_no || 'N/A';
  var periodStr = monthName || (data.fee_month ? data.fee_month : 'Session 2027');

  // Format currency helper
  function fmt(num) {
    return '৳ ' + parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // Set all values across both Student and Office copies
  document.querySelectorAll('.slip-student-name').forEach(el => el.innerText = studentName);
  document.querySelectorAll('.slip-inv-no').forEach(el => el.innerText = invNo);
  document.querySelectorAll('.slip-student-code').forEach(el => el.innerText = studentId);
  document.querySelectorAll('.slip-paid-date').forEach(el => el.innerText = paidDate);
  document.querySelectorAll('.slip-class-name').forEach(el => el.innerText = className);
  document.querySelectorAll('.slip-roll-no').forEach(el => el.innerText = rollNo);
  document.querySelectorAll('.slip-pay-method').forEach(el => el.innerText = payMethod);
  document.querySelectorAll('.slip-fee-month').forEach(el => el.innerText = periodStr);
  document.querySelectorAll('.slip-ref-no').forEach(el => el.innerText = refNo);

  // Status Badge
  document.querySelectorAll('.slip-status-pill').forEach(el => {
    el.className = 'status-pill slip-status-pill';
    if (status === 'Paid') {
      el.classList.add('status-paid');
      el.innerText = 'PAID (পরিশোধিত)';
    } else if (status === 'Partial') {
      el.classList.add('status-partial');
      el.innerText = 'PARTIAL (আংশিক)';
    } else {
      el.classList.add('status-unpaid');
      el.innerText = 'DUE (বকেয়া)';
    }
  });

  // Totals
  document.querySelectorAll('.slip-total-bill').forEach(el => el.innerText = fmt(totalAmt));
  document.querySelectorAll('.slip-total-paid').forEach(el => el.innerText = fmt(paidAmt));
  document.querySelectorAll('.slip-due-balance').forEach(el => {
    el.innerText = fmt(dueAmt);
    if (dueAmt <= 0.01) {
      el.style.color = '#059669';
      el.innerText = '৳ 0.00 (Cleared)';
    } else {
      el.style.color = '#dc2626';
    }
  });

  // Items Breakdown Table
  // Check if items list is serialized in notes or fee_type
  var itemsHtml = '';
  var notes = data.notes || '';
  var itemsMatch = notes.match(/Items:\s*\[(.*?)\]/);
  
  if (itemsMatch && itemsMatch[1]) {
    var rawItems = itemsMatch[1].split(',');
    var sl = 1;
    rawItems.forEach(function(itemStr) {
      var parts = itemStr.split(': ৳');
      var itemTitle = parts[0] ? parts[0].trim() : 'Fee Item';
      var itemCost = parts[1] ? parseFloat(parts[1].replace(/,/g, '')) : (totalAmt / rawItems.length);
      itemsHtml += `
        <tr>
          <td style="text-align: center;">${sl < 10 ? '0' + sl : sl}</td>
          <td><strong>${itemTitle}</strong></td>
          <td style="text-align: center;">${periodStr}</td>
          <td style="text-align: right;"><strong>${fmt(itemCost)}</strong></td>
        </tr>
      `;
      sl++;
    });
  } else {
    // Single fee item
    var primaryTitle = data.fee_type || 'Monthly Tuition Fee';
    itemsHtml = `
      <tr>
        <td style="text-align: center;">01</td>
        <td><strong>${primaryTitle}</strong></td>
        <td style="text-align: center;">${periodStr}</td>
        <td style="text-align: right;"><strong>${fmt(totalAmt)}</strong></td>
      </tr>
    `;
  }

  document.querySelectorAll('.slip-items-tbody').forEach(el => el.innerHTML = itemsHtml);

  // Show Modal
  openModal('receiptSlipModal');
}

/**
 * Native Print Driver: Prints only the dual A4 receipt area without modifying the DOM
 */
function printReceiptSlip() {
  document.body.classList.add('printing-receipt-slip');
  window.print();
  setTimeout(function() {
    document.body.classList.remove('printing-receipt-slip');
  }, 600);
}
</script>
