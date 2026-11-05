/* ==========================================================================
   AL FATIHA INSTITUTE - Dashboard & Mobile Navigation JavaScript
   High-performance, touch-friendly interactions for all portals
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  // --- 1. Mobile Sidebar Navigation Drawer ---
  const mobileMenuToggle = document.getElementById('mobileMenuToggle');
  const sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('show');
    if (sidebarBackdrop) sidebarBackdrop.classList.add('show');
    document.body.classList.add('sidebar-open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('show');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
  }

  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openSidebar();
    });
  }

  if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener('click', function (e) {
      e.preventDefault();
      closeSidebar();
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', function () {
      closeSidebar();
    });
  }

  // Close sidebar on link click (mobile friendly UX)
  if (sidebar) {
    const sidebarLinks = sidebar.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth < 992) {
          closeSidebar();
        }
      });
    });
  }

  // Close on Escape key press
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeSidebar();
      // Also close any open modals
      document.querySelectorAll('div[id$="Modal"], .admin-modal-wrap').forEach(function (m) {
        if (m.style.display === 'flex' || m.classList.contains('show')) {
          m.style.display = 'none';
          document.body.style.overflow = '';
        }
      });
    }
  });

  // Auto-cleanup on window resize to desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) {
      closeSidebar();
    }
  });

  // --- 2. Live Table Search Filter ---
  const tableSearch = document.getElementById('tableSearch');
  if (tableSearch) {
    tableSearch.addEventListener('keyup', function () {
      const value = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('.data-table tbody tr');
      rows.forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(value) > -1 ? '' : 'none';
      });
    });
  }

  // --- 3. Print triggers ---
  const printBtn = document.getElementById('printDocumentBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      window.print();
    });
  }

  // --- 4. Modal Backdrop Click to Close ---
  document.querySelectorAll('div[id$="Modal"], .admin-modal-wrap').forEach(function (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        closeModal(modal.id);
      }
    });
  });
});

// --- Modal Helper Functions ---
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    // Focus first input if available
    const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput && window.innerWidth >= 768) {
      setTimeout(function () { firstInput.focus(); }, 100);
    }
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}
