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
  <meta name="description" content="AL FATIHA INSTITUTE is an English medium Cambridge curriculum kindergarten and early primary school in Cox's Bazar integrating Islamic values, Tajweed, and Arabic from Play Group to Class 2.">

  <!-- Google Fonts: Newsreader (Editorial Headlines), Plus Jakarta Sans (UI), IBM Plex Mono (Meta/Indices), Amiri (Arabic) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=IBM+Plex+Mono:wght@400;500&family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome Icons CDN (kept for backward compatibility on multi-step forms) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Main Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css?v=2">

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/logo.png?v=2">
</head>
<body>

  <!-- Utility Bar -->
  <div class="top-bar">
    <div class="container">
      <div class="top-info">
        <span>Tekpara Main Road, Cox's Bazar</span>
        <span class="top-sep" aria-hidden="true">/</span>
        <span>01611477441 · 01632550045</span>
        <span class="top-sep" aria-hidden="true">/</span>
        <span>alfatihacxb@gmail.com</span>
      </div>
      <div class="top-actions">
        <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
          <?php
            $user_role = function_exists('get_current_user_role') ? get_current_user_role() : ($_SESSION['user_role'] ?? 'user');
            $dash_url = in_array($user_role, ['super_admin', 'admin', 'principal'])
                ? 'admin/dashboard.php'
                : (($user_role === 'teacher')
                    ? 'teacher/dashboard.php'
                    : (($user_role === 'accountant')
                        ? 'accountant/dashboard.php'
                        : 'portal/dashboard.php'));
            $roleLabel = ($user_role === 'super_admin') ? 'Super Admin' : (($user_role === 'principal') ? 'Principal' : ucfirst($user_role));
          ?>
          <a href="<?= $dash_url ?>" class="portal-link">Dashboard (<?= htmlspecialchars($roleLabel) ?>)</a>
          <a href="login.php?logout=1" class="top-logout-link">Logout</a>
        <?php else: ?>
          <a href="login.php" class="portal-link">Portal Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Header Navigation -->
  <header class="site-header">
    <div class="container navbar">
      <a href="./" class="brand-logo">
        <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Logo">
        <div class="brand-text">
          <span class="brand-name">AL FATIHA INSTITUTE</span>
          <span class="brand-sub">Est. 2025 · Cox's Bazar</span>
        </div>
      </a>

      <button class="menu-toggle" aria-label="Toggle Menu" aria-expanded="false">
        <i class="fas fa-bars" aria-hidden="true"></i>
      </button>

      <ul class="nav-menu">
        <li><a href="./" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">Home</a></li>
        <li><a href="about" class="nav-link <?= ($current_page == 'about.php') ? 'active' : '' ?>">About</a></li>
        <li><a href="academics" class="nav-link <?= ($current_page == 'academics.php') ? 'active' : '' ?>">Academics</a></li>
        <li><a href="islamic-values" class="nav-link <?= ($current_page == 'islamic-values.php') ? 'active' : '' ?>">Islamic Ethos</a></li>
        <li><a href="admissions" class="nav-link <?= ($current_page == 'admissions.php') ? 'active' : '' ?>">Admissions</a></li>
        <li><a href="notices" class="nav-link <?= ($current_page == 'notices.php') ? 'active' : '' ?>">Notices</a></li>
        <li><a href="gallery" class="nav-link <?= ($current_page == 'gallery.php') ? 'active' : '' ?>">Gallery</a></li>
        <li><a href="contact" class="nav-link <?= ($current_page == 'contact.php') ? 'active' : '' ?>">Contact</a></li>
        <li class="nav-cta">
          <a href="admissions#apply-form" class="btn btn-primary btn-nav">Apply Now</a>
        </li>
      </ul>
    </div>
  </header>
