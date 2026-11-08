    </main> <!-- End page-body / content-area -->
  </div> <!-- End main-content / main-wrapper -->

  <?php
  $current_script = basename($_SERVER['PHP_SELF']);
  ?>

  <!-- Mobile Bottom App Bar Navigation (Native App Style) -->
  <nav class="mobile-bottom-app-bar" aria-label="Mobile Navigation">
    <a href="dashboard.php" class="mobile-nav-item <?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
      <i class="fas fa-chart-line"></i>
      <span>Home</span>
    </a>
    <a href="fees.php" class="mobile-nav-item <?= ($current_script == 'fees.php') ? 'active' : '' ?>">
      <i class="fas fa-file-invoice-dollar"></i>
      <span>Fees</span>
    </a>
    <a href="expenses.php" class="mobile-nav-item <?= ($current_script == 'expenses.php') ? 'active' : '' ?>">
      <i class="fas fa-receipt"></i>
      <span>Expenses</span>
    </a>
    <a href="income.php" class="mobile-nav-item <?= ($current_script == 'income.php') ? 'active' : '' ?>">
      <i class="fas fa-hand-holding-usd"></i>
      <span>Income</span>
    </a>
    <button type="button" class="mobile-nav-item mobile-menu-tab" id="mobileBottomMenuBtnAcc" onclick="var m = document.getElementById('mobileMenuToggle'); if(m) m.click();">
      <i class="fas fa-bars"></i>
      <span>Menu</span>
    </button>
  </nav>

  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
