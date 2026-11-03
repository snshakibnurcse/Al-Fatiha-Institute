/* ==========================================================================
   AL FATIHA INSTITUTE - Dashboard JavaScript
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  // Simple table search filter
  const tableSearch = document.getElementById('tableSearch');
  if (tableSearch) {
    tableSearch.addEventListener('keyup', function () {
      const value = this.value.toLowerCase();
      const rows = document.querySelectorAll('.data-table tbody tr');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(value) > -1 ? '' : 'none';
      });
    });
  }

  // Print triggers
  const printBtn = document.getElementById('printDocumentBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      window.print();
    });
  }
});

// Modal helper functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'flex';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'none';
  }
}
