    </div> <!-- End content-area -->
  </div> <!-- End main-wrapper -->

  <?php
  $current_script = basename($_SERVER['PHP_SELF']);
  ?>

  <nav class="mobile-bottom-app-bar" aria-label="Mobile Navigation">
    <a href="dashboard.php" class="mobile-nav-item <?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
      <i class="fas fa-th-large"></i>
      <span>Home</span>
    </a>
    <a href="employees.php" class="mobile-nav-item <?= ($current_script == 'employees.php') ? 'active' : '' ?>">
      <i class="fas fa-id-card-alt"></i>
      <span>Staff</span>
    </a>
    <a href="attendance.php" class="mobile-nav-item <?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
      <i class="fas fa-user-check"></i>
      <span>Attend</span>
    </a>
    <a href="payroll.php" class="mobile-nav-item <?= ($current_script == 'payroll.php' || $current_script == 'payslip.php') ? 'active' : '' ?>">
      <i class="fas fa-money-check-alt"></i>
      <span>Payroll</span>
    </a>
    <button type="button" class="mobile-nav-item mobile-menu-tab" onclick="var m = document.getElementById('mobileMenuToggle'); if(m) m.click();">
      <i class="fas fa-bars"></i>
      <span>Menu</span>
    </button>
  </nav>

  <script src="../assets/js/dashboard.js"></script>
</body>
</html>

