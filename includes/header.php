<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AL FATIHA INSTITUTE | Cambridge Curriculum & Islamic Ethos Kindergarten</title>
  <meta name="description" content="AL FATIHA INSTITUTE is a premier English medium Cambridge curriculum kindergarten school in Cox's Bazar prioritizing Islamic values, Tajweed, Arabic language, Play, Nursery, KG, Year 1 and Year 2.">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome Icons CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg">
</head>
<body>

  <!-- Top Bar -->
  <div class="top-bar">
    <div class="container">
      <div class="top-info">
        <span><i class="fas fa-map-marker-alt" style="color: var(--accent-gold);"></i> Main Road, Cox's Bazar, Bangladesh</span>
        <span><i class="fas fa-phone-alt" style="color: var(--accent-gold);"></i> +880 1800-000000</span>
        <span><i class="fas fa-envelope" style="color: var(--accent-gold);"></i> info@alfatihainstitute.edu.bd</span>
      </div>
      <div class="top-actions">
        <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
          <?php 
            $user_role = $_SESSION['user_role'] ?? 'user';
            $dash_url = ($user_role === 'admin') ? 'admin/dashboard.php' : (($user_role === 'teacher') ? 'teacher/dashboard.php' : 'portal/dashboard.php');
          ?>
          <a href="<?= $dash_url ?>" class="portal-link"><i class="fas fa-user-circle"></i> Dashboard (<?= ucfirst($user_role) ?>)</a>
          <a href="login.php?logout=1" style="color: #f87171; font-size: 0.8rem; font-weight: 700; margin-left: 0.5rem;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
          <a href="login.php" class="portal-link"><i class="fas fa-user-lock"></i> Login</a>
        <?php endif; ?>
        <a href="setup.php" title="One Click Database Installer" style="color: var(--accent-gold); font-size: 0.8rem; font-weight: 700;"><i class="fas fa-database"></i> Setup DB</a>
      </div>
    </div>
  </div>

  <!-- Header Navigation -->
  <header class="site-header">
    <div class="container navbar">
      <a href="index.php" class="brand-logo">
        <img src="assets/images/logo.svg" alt="AL FATIHA INSTITUTE Logo">
        <div class="brand-text">
          <h1>AL FATIHA INSTITUTE</h1>
          <span>ESTD 2025 • COX'S BAZAR</span>
        </div>
      </a>

      <button class="menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
      </button>

      <ul class="nav-menu">
        <li><a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">Home</a></li>
        <li><a href="about.php" class="nav-link <?= ($current_page == 'about.php') ? 'active' : '' ?>">About Us</a></li>
        <li><a href="academics.php" class="nav-link <?= ($current_page == 'academics.php') ? 'active' : '' ?>">Academics</a></li>
        <li><a href="islamic-values.php" class="nav-link <?= ($current_page == 'islamic-values.php') ? 'active' : '' ?>">Islamic Ethos</a></li>
        <li><a href="admissions.php" class="nav-link <?= ($current_page == 'admissions.php') ? 'active' : '' ?>">Admissions</a></li>
        <li><a href="notices.php" class="nav-link <?= ($current_page == 'notices.php') ? 'active' : '' ?>">Notices</a></li>
        <li><a href="gallery.php" class="nav-link <?= ($current_page == 'gallery.php') ? 'active' : '' ?>">Gallery</a></li>
        <li><a href="contact.php" class="nav-link <?= ($current_page == 'contact.php') ? 'active' : '' ?>">Contact</a></li>
        <li class="nav-cta">
          <a href="admissions.php#apply-form" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Apply Now</a>
        </li>
      </ul>
    </div>
  </header>
