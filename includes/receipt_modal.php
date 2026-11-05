<?php
// Unified A4 Split Money Receipt Modal (Student Copy & Office Copy)
// Strictly English (with '৳' currency symbol only).
// Engineered to print on EXACTLY 1 single A4 portrait page with zero blank pages.
?>
<div id="receiptSlipModal" class="admin-modal-wrap" style="display: none;">
  <div class="admin-modal-box" style="max-width: 800px; width: 95%; max-height: 94vh; display: flex; flex-direction: column; padding: 1.1rem 1.4rem;">
    
    <!-- Modal Toolbar (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 0.75rem; flex-shrink: 0;">
      <div>
        <h3 style="margin: 0; font-family: 'Outfit', sans-serif; color: var(--admin-deep-navy, #064878); font-size: 1.2rem; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-receipt" style="color: #f59e0b;"></i> Official Money Receipt (A4 Dual Copy)
        </h3>
        <small style="color: #64748b;">Single A4 Sheet: Top half Student Copy &bull; Bottom half Office Copy with Perforation</small>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn" onclick="printReceiptSlip()" style="background: #0284c7; color: #ffffff; padding: 0.45rem 1.1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(2,132,199,0.25);">
          <i class="fas fa-print"></i> Print Receipt (1 Page)
        </button>
        <button type="button" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #64748b; line-height: 1;" onclick="closeModal('receiptSlipModal')">&times;</button>
      </div>
    </div>

    <!-- Scrollable Preview Container for Modal View -->
    <div style="overflow-y: auto; flex: 1; padding-right: 4px;">
      
      <!-- PRINTABLE DUAL RECEIPT AREA (Captures exact 1 Page A4 Print) -->
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
                <div class="receipt-school-addr">Engg. Edris Building, Kalur Dokan, Tekpara Main Road, Cox's Bazar &bull; Phone: 01611477441, 01632550045 &bull; Email: alfatihacxb@gmail.com</div>
              </div>
              <div class="receipt-badge-col">
                <span class="copy-tag student-tag">STUDENT COPY</span>
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
              <div class="meta-item"><span class="lbl">Session / Month:</span> <span class="val slip-fee-month">Session 2027</span></div>
              <div class="meta-item"><span class="lbl">Trx Ref No:</span> <span class="val slip-ref-no">N/A</span></div>
            </div>

            <!-- Fee Particulars Table -->
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 34px; text-align: center;">SL</th>
                  <th>Fee Particulars / Item Description</th>
                  <th style="width: 130px; text-align: center;">Month / Period</th>
                  <th style="width: 110px; text-align: right;">Amount (৳)</th>
                </tr>
              </thead>
              <tbody class="slip-items-tbody">
                <tr>
                  <td style="text-align: center;">01</td>
                  <td><strong class="slip-item-title">Monthly Tuition Fee</strong></td>
                  <td style="text-align: center;" class="slip-item-period">November 2026</td>
                  <td style="text-align: right;"><strong class="slip-item-amount">৳ 0.00</strong></td>
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

            <!-- Receipt Footer & Signatures (With Dedicated Signing Clearance) -->
            <div class="receipt-footer-row">
              <div class="footer-note">
                <i class="fas fa-check-circle" style="color: #059669;"></i> Official computer-generated receipt.<br>Please preserve for academic records.
              </div>
              <div class="signature-box">
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-title">Student / Guardian</div>
              </div>
              <div class="signature-box">
                <div class="sig-space"></div>
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
            ✂ &nbsp; CUT HERE &bull; (▲ STUDENT COPY &nbsp;|&nbsp; OFFICE COPY ▼) &nbsp; ✂
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
                <div class="receipt-school-addr">Engg. Edris Building, Kalur Dokan, Tekpara Main Road, Cox's Bazar &bull; Phone: 01611477441, 01632550045 &bull; Email: alfatihacxb@gmail.com</div>
              </div>
              <div class="receipt-badge-col">
                <span class="copy-tag office-tag">OFFICE COPY</span>
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
              <div class="meta-item"><span class="lbl">Session / Month:</span> <span class="val slip-fee-month">Session 2027</span></div>
              <div class="meta-item"><span class="lbl">Trx Ref No:</span> <span class="val slip-ref-no">N/A</span></div>
            </div>

            <!-- Fee Particulars Table -->
            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="width: 34px; text-align: center;">SL</th>
                  <th>Fee Particulars / Item Description</th>
                  <th style="width: 130px; text-align: center;">Month / Period</th>
                  <th style="width: 110px; text-align: right;">Amount (৳)</th>
                </tr>
              </thead>
              <tbody class="slip-items-tbody">
                <tr>
                  <td style="text-align: center;">01</td>
                  <td><strong class="slip-item-title">Monthly Tuition Fee</strong></td>
                  <td style="text-align: center;" class="slip-item-period">November 2026</td>
                  <td style="text-align: right;"><strong class="slip-item-amount">৳ 0.00</strong></td>
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

            <!-- Receipt Footer & Signatures (With Dedicated Signing Clearance) -->
            <div class="receipt-footer-row">
              <div class="footer-note">
                <i class="fas fa-university" style="color: #064878;"></i> Accounts &amp; Audit Record.<br>Preserved in School Office Ledger.
              </div>
              <div class="signature-box">
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-title">Depositor / Parent</div>
              </div>
              <div class="signature-box">
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-title">Accounts Officer / Principal</div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- End a4ReceiptSlipPrintArea -->
    </div><!-- End Scroll Container -->

    <!-- Modal Footer Actions (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem; flex-shrink: 0;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('receiptSlipModal')" style="padding: 0.45rem 1.2rem; border-radius: 8px;">Close</button>
      <button type="button" class="btn btn-primary" onclick="printReceiptSlip()" style="background: #0284c7; color: #ffffff; font-weight: 700; padding: 0.45rem 1.4rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fas fa-print"></i> Print Receipt (1 Page)
      </button>
    </div>

  </div>
</div>

<!-- ========================================================
     A4 RECEIPT SLIP STYLING (ON-SCREEN & PRINT ENGINE)
     ======================================================== -->
<style id="receiptModalPrintStyle">
/* Base Styles for A4 Sheet Container */
.a4-receipt-page {
  background: #ffffff;
  width: 100%;
  max-width: 740px;
  margin: 0 auto;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #1e293b;
}

/* Individual Receipt Half (Upper and Lower) */
.receipt-half {
  background: #ffffff;
  border: 1.5px solid #064878;
  border-radius: 6px;
  padding: 8px 12px;
  box-sizing: border-box;
  position: relative;
  page-break-inside: avoid !important;
  break-inside: avoid !important;
}

.student-copy {
  border-color: #0284c7;
  page-break-after: avoid !important;
}

.office-copy {
  border-color: #064878;
  page-break-before: avoid !important;
}

/* Receipt Header */
.receipt-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border-bottom: 1.5px solid #064878;
  padding-bottom: 4px;
  margin-bottom: 5px;
}

.receipt-logo-wrap {
  flex-shrink: 0;
}

.receipt-logo {
  width: 44px;
  height: 44px;
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
  letter-spacing: 0.5px;
  line-height: 1.15;
}

.receipt-school-sub {
  font-size: 0.66rem;
  color: #0284c7;
  font-weight: 700;
  margin-top: 1px;
}

.receipt-school-addr {
  font-size: 0.60rem;
  color: #64748b;
  margin-top: 1px;
  line-height: 1.2;
}

.receipt-badge-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  flex-shrink: 0;
}

.copy-tag {
  display: inline-block;
  padding: 2px 7px;
  font-size: 0.60rem;
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
  font-size: 0.60rem;
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

/* Metadata Grid - Column widths optimized to avoid wrapping */
.receipt-meta-grid {
  display: grid;
  grid-template-columns: 1.2fr 1.35fr 1.15fr 0.95fr;
  gap: 3px 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 5px;
  padding: 4px 8px;
  margin-bottom: 5px;
  font-size: 0.72rem;
  line-height: 1.3;
}

.meta-item {
  display: flex;
  align-items: baseline;
  gap: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta-item .lbl {
  color: #64748b;
  font-weight: 600;
  flex-shrink: 0;
}

.meta-item .val {
  color: #0f172a;
  white-space: nowrap;
}

.monospace-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Particulars Table */
.receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.72rem;
  margin-bottom: 5px;
}

.receipt-table th {
  background: #f1f5f9;
  color: #334155;
  font-weight: 700;
  padding: 3px 6px;
  border: 1px solid #cbd5e1;
  font-size: 0.69rem;
  text-transform: uppercase;
}

.receipt-table td {
  padding: 3px 6px;
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
  border-radius: 5px;
  padding: 4px 10px;
  margin-bottom: 5px;
}

.total-box {
  display: flex;
  align-items: baseline;
  gap: 5px;
  font-size: 0.74rem;
}

.total-box .t-lbl {
  color: #64748b;
  font-weight: 700;
}

.total-box .t-val {
  font-weight: 800;
  font-size: 0.82rem;
  color: #0f172a;
}

.paid-box .t-val {
  color: #059669;
}

.due-box .t-val {
  color: #dc2626;
}

/* Receipt Footer & Signatures (Dedicated signing height) */
.receipt-footer-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-top: 2px;
  margin-top: 2px;
}

.footer-note {
  font-size: 0.60rem;
  color: #64748b;
  max-width: 44%;
  line-height: 1.2;
  padding-bottom: 2px;
}

.signature-box {
  text-align: center;
  width: 155px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.sig-space {
  height: 38px; /* Dedicated empty area for manual pen signature and school seal stamp */
  display: block;
}

.sig-line {
  border-bottom: 1.2px dashed #475569;
  margin-bottom: 3px;
  width: 100%;
}

.sig-title {
  font-size: 0.63rem;
  font-weight: 700;
  color: #334155;
  white-space: nowrap;
}

/* Middle Perforation Cut Line */
.receipt-cut-line {
  position: relative;
  text-align: center;
  margin: 6px 0;
  height: 12px;
  page-break-inside: avoid !important;
  break-inside: avoid !important;
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
  padding: 0 10px;
  font-size: 0.63rem;
  font-weight: 800;
  color: #475569;
  letter-spacing: 0.4px;
}

/* Native Print Fallback (if user presses Ctrl+P while modal is open) */
@page {
  size: A4 portrait;
  margin: 6mm 8mm;
}

@media print {
  html, body {
    height: auto !important;
    min-height: 0 !important;
    max-height: 100% !important;
    overflow: visible !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
  }

  /* Hide entire page tree */
  body > * {
    display: none !important;
  }

  /* Display only the modal and print area */
  body > #receiptSlipModal,
  #receiptSlipModal {
    display: block !important;
    position: static !important;
    inset: auto !important;
    width: 100% !important;
    height: auto !important;
    max-height: none !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    overflow: visible !important;
  }

  .admin-modal-box {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
  }

  #a4ReceiptSlipPrintArea {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    display: block !important;
    page-break-after: avoid !important;
    break-after: avoid !important;
  }

  .no-print {
    display: none !important;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>

<!-- ========================================================
     RECEIPT DATA POPULATION & DEDICATED IFRAME PRINT DRIVER
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

  // Robust Student ID Extraction:
  // 1) First check student_code (e.g. AFI-PG-001, AFI-C1-001)
  var studentId = data.student_code || '';
  
  // 2) If not in student_code, check if student_id is an alphanumeric code
  if (!studentId && data.student_id && String(data.student_id).indexOf('AFI-') !== -1) {
    studentId = String(data.student_id);
  }
  
  // 3) If studentId is missing or purely numeric (e.g. database foreign key "20"), check window.currentStudentData
  if ((!studentId || /^\d+$/.test(String(studentId).trim())) && window.currentStudentData && window.currentStudentData.student_id) {
    studentId = window.currentStudentData.student_id;
  }
  
  // 4) Fallback formatting if still numeric
  if (!studentId) {
    studentId = data.student_id ? ('AFI-ST-' + String(data.student_id).padStart(3, '0')) : 'AFI-ST';
  } else if (/^\d+$/.test(String(studentId).trim())) {
    studentId = 'AFI-ST-' + String(studentId).trim().padStart(3, '0');
  }

  // Student Full Name Resolution:
  var studentName = ((data.first_name || '') + ' ' + (data.last_name || '')).trim() || data.student_name || '';
  if ((!studentName || studentName.toLowerCase() === 'student') && window.currentStudentData && window.currentStudentData.first_name) {
    studentName = ((window.currentStudentData.first_name || '') + ' ' + (window.currentStudentData.last_name || '')).trim();
  }
  if (!studentName) studentName = 'Student';

  // Class Name Resolution:
  var className = data.class_name || '';
  if ((!className || className === 'N/A') && window.currentStudentData && window.currentStudentData.class_name) {
    className = window.currentStudentData.class_name;
  }
  if (!className) className = 'N/A';

  // Roll No Resolution:
  var rawRoll = (data.roll_no !== undefined && data.roll_no !== null && data.roll_no !== '') ? data.roll_no : '';
  if (!rawRoll && window.currentStudentData && window.currentStudentData.roll_no) {
    rawRoll = window.currentStudentData.roll_no;
  }
  var rollNo = rawRoll ? '#' + rawRoll : '-';

  var invNo = data.invoice_no || 'INV-' + Math.floor(Math.random() * 9000 + 1000);
  var paidDate = data.paid_date || (new Date()).toISOString().split('T')[0];
  
  // Clean payment method - strictly English
  var rawMethod = (data.payment_method || 'Cash').toString();
  var payMethod = rawMethod.replace(/[\u0980-\u09FF\(\)]/g, '').trim() || 'Cash';
  if (/bkash/i.test(payMethod)) payMethod = 'bKash';
  else if (/nagad/i.test(payMethod)) payMethod = 'Nagad';
  else if (/rocket/i.test(payMethod)) payMethod = 'Rocket';
  else if (/bank/i.test(payMethod)) payMethod = 'Bank Transfer';
  else if (/cash/i.test(payMethod)) payMethod = 'Cash';

  var refNo = data.reference_no || 'N/A';
  var periodStr = monthName || (data.fee_month ? data.fee_month : 'Session 2027');

  // Format currency helper - English with '৳' sign
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

  // Status Badge - Strictly English
  document.querySelectorAll('.slip-status-pill').forEach(el => {
    el.className = 'status-pill slip-status-pill';
    if (status === 'Paid') {
      el.classList.add('status-paid');
      el.innerText = 'PAID';
    } else if (status === 'Partial') {
      el.classList.add('status-partial');
      el.innerText = 'PARTIAL';
    } else {
      el.classList.add('status-unpaid');
      el.innerText = 'DUE';
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

  // Items Breakdown Table (Robust pattern parsing, unaffected by thousands commas)
  var itemsHtml = '';
  var notes = data.notes || '';
  var itemsMatch = notes.match(/Items:\s*\[(.*?)\]/);
  
  if (itemsMatch && itemsMatch[1]) {
    var content = itemsMatch[1];
    var itemRegex = /([^:|\[\]]+?)\s*:\s*৳\s*([\d,]+(?:\.\d{1,2})?)/g;
    var match;
    var sl = 1;
    var parsedCount = 0;
    while ((match = itemRegex.exec(content)) !== null) {
      var itemTitle = match[1].trim().replace(/^[,\s\|]+|[,\s\|]+$/g, '');
      var itemCost = parseFloat(match[2].replace(/,/g, '')) || 0;
      if (itemTitle && itemCost > 0) {
        itemsHtml += `
          <tr>
            <td style="text-align: center;">${sl < 10 ? '0' + sl : sl}</td>
            <td><strong>${itemTitle}</strong></td>
            <td style="text-align: center;">${periodStr}</td>
            <td style="text-align: right;"><strong>${fmt(itemCost)}</strong></td>
          </tr>
        `;
        sl++;
        parsedCount++;
      }
    }
    
    // Fallback if parsing produced 0 rows
    if (parsedCount === 0) {
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
  } else {
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
 * Isolated A4 Single-Page Print Driver:
 * Clones ONLY the dual-receipt area into a clean, isolated hidden iframe.
 * Absolutely eliminates parent page layout pollution and guarantees 1 single page with 0 blank pages.
 */
function printReceiptSlip() {
  var printArea = document.getElementById('a4ReceiptSlipPrintArea');
  if (!printArea) return;

  // Retrieve or create print iframe
  var iframe = document.getElementById('receiptPrintIframe');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'receiptPrintIframe';
    iframe.style.position = 'fixed';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.width = '0px';
    iframe.style.height = '0px';
    iframe.style.border = 'none';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);
  }

  // Get current styles for the receipt
  var styleTag = document.getElementById('receiptModalPrintStyle');
  var cssRules = styleTag ? styleTag.innerHTML : '';

  // Get absolute URL for the logo so it renders flawlessly inside iframe
  var logoEl = document.querySelector('.receipt-logo');
  var logoUrl = logoEl ? logoEl.src : '../assets/images/logo.png?v=2';

  // Build isolated HTML document strictly sized for 1 A4 portrait page
  var iframeDoc = iframe.contentWindow.document;
  iframeDoc.open();
  iframeDoc.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Money Receipt - Al Fatiha Institute</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 6mm 8mm;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      height: auto !important;
      min-height: 0 !important;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #1e293b;
    }
    ${cssRules}
    .a4-receipt-page {
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
    }
  </style>
</head>
<body>
  ${printArea.outerHTML}
</body>
</html>`);
  iframeDoc.close();

  // Replace any relative logo src inside the iframe with the absolute loaded src
  var iframeLogos = iframeDoc.getElementsByTagName('img');
  for (var i = 0; i < iframeLogos.length; i++) {
    iframeLogos[i].src = logoUrl;
  }

  // Trigger print after iframe renders
  setTimeout(function() {
    try {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    } catch (e) {
      console.error('Iframe print error fallback to window.print:', e);
      window.print();
    }
  }, 250);
}
</script>
