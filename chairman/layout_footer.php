    </div> <!-- End content-area -->
  </div> <!-- End main-wrapper -->

  <?php
  $current_script = basename($_SERVER['PHP_SELF']);
  ?>

  <nav class="mobile-bottom-app-bar" aria-label="Mobile Navigation">
    <a href="dashboard.php" class="mobile-nav-item <?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
      <i class="fas fa-chart-pie"></i>
      <span>Overview</span>
    </a>
    <a href="students.php" class="mobile-nav-item <?= ($current_script == 'students.php') ? 'active' : '' ?>">
      <i class="fas fa-user-graduate"></i>
      <span>Students</span>
    </a>
    <a href="finance.php" class="mobile-nav-item <?= ($current_script == 'finance.php') ? 'active' : '' ?>">
      <i class="fas fa-coins"></i>
      <span>Finance</span>
    </a>
    <a href="attendance.php" class="mobile-nav-item <?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
      <i class="fas fa-user-check"></i>
      <span>Staff</span>
    </a>
    <button type="button" class="mobile-nav-item mobile-menu-tab" onclick="var m = document.getElementById('mobileMenuToggle'); if(m) m.click();">
      <i class="fas fa-bars"></i>
      <span>Menu</span>
    </button>
  </nav>

  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
