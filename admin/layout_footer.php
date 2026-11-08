    </div> <!-- End content-area -->
  </div> <!-- End main-wrapper -->

  <?php
  $current_script = basename($_SERVER['PHP_SELF']);
  $current_role = function_exists('get_current_user_role') ? get_current_user_role() : 'admin';
  ?>

  <!-- Mobile Bottom App Bar Navigation (Native App Style) -->
  <nav class="mobile-bottom-app-bar" aria-label="Mobile Navigation">
    <?php if ($current_role === 'teacher'): ?>
      <a href="dashboard.php" class="mobile-nav-item <?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i>
        <span>Home</span>
      </a>
      <a href="subjects.php" class="mobile-nav-item <?= ($current_script == 'subjects.php') ? 'active' : '' ?>">
        <i class="fas fa-book-open"></i>
        <span>Subjects</span>
      </a>
      <a href="attendance.php" class="mobile-nav-item <?= ($current_script == 'attendance.php') ? 'active' : '' ?>">
        <i class="fas fa-clipboard-user"></i>
        <span>Attendance</span>
      </a>
      <a href="students.php" class="mobile-nav-item <?= ($current_script == 'students.php') ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i>
        <span>Students</span>
      </a>
    <?php else: ?>
      <a href="dashboard.php" class="mobile-nav-item <?= ($current_script == 'dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-th-large"></i>
        <span>Home</span>
      </a>
      <a href="students.php" class="mobile-nav-item <?= ($current_script == 'students.php' || $current_script == 'student_profile.php') ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i>
        <span>Students</span>
      </a>
      <a href="fees.php" class="mobile-nav-item <?= ($current_script == 'fees.php') ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Fees</span>
      </a>
      <a href="admissions.php" class="mobile-nav-item <?= ($current_script == 'admissions.php') ? 'active' : '' ?>">
        <i class="fas fa-file-signature"></i>
        <span>Admissions</span>
      </a>
    <?php endif; ?>
    <button type="button" class="mobile-nav-item mobile-menu-tab" id="mobileBottomMenuBtn" onclick="var m = document.getElementById('mobileMenuToggle'); if(m) m.click();">
      <i class="fas fa-bars"></i>
      <span>Menu</span>
    </button>
  </nav>

  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
