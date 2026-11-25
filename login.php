<?php
require_once __DIR__ . '/includes/functions.php';

// Handle explicit logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    redirect('login.php');
}

if (is_logged_in()) {
    $role = get_current_user_role();
    if (in_array($role, ['super_admin', 'admin', 'principal'])) {
        redirect('admin/dashboard.php');
    } elseif ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    } elseif ($role === 'parent') {
        redirect('portal/dashboard.php');
    } elseif ($role === 'accountant') {
        redirect('accountant/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = get_db_connection();
        // Case-insensitive username or email lookup
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            // Check status
            if (!empty($user['status']) && strtolower($user['status']) === 'inactive') {
                $error = 'Your account (@' . htmlspecialchars($user['username']) . ') is currently inactive. Please contact the administrator.';
            } elseif (password_verify($password, $user['password'])) {
                // Fail-safe role normalization
                $role = $user['role'] ?? '';
                if ($user['id'] == 1 || strtolower($user['username']) === 'admin') {
                    $role = 'super_admin';
                } elseif (empty($role) && stripos($user['username'], 'principal') !== false) {
                    $role = 'principal';
                } elseif (empty($role)) {
                    $role = 'admin';
                }

                // If role was empty or outdated in the database, repair it immediately
                if (($user['role'] ?? '') !== $role) {
                    try {
                        $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $user['id']]);
                    } catch (Exception $e) {}
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_name'] = $user['full_name'];

                if (in_array($role, ['super_admin', 'admin', 'principal'])) {
                    redirect('admin/dashboard.php');
                } elseif ($role === 'teacher') {
                    redirect('teacher/dashboard.php');
                } elseif ($role === 'parent') {
                    redirect('portal/dashboard.php');
                } elseif ($role === 'accountant') {
                    redirect('accountant/dashboard.php');
                } else {
                    redirect('admin/dashboard.php');
                }
            } else {
                $error = 'Invalid username or password. Please verify your credentials.';
            }
        } else {
            $error = 'No registered account found matching this username or email.';
        }
    } else {
        $error = 'Please enter both your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Portal Login | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Unified Login Portal for AL FATIHA INSTITUTE">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="assets/images/logo.png?v=2">
  <style>
    :root {
      --navy-deep: #061c38;
      --navy: #082447;
      --accent-gold: #e5a922;
      --accent-gold-dark: #9a6704;
      --bg-body: #f8f7f4;
      --bg-surface: #ffffff;
      --bg-raised: #f1efe9;
      --text-main: #161a20;
      --text-secondary: #4a515d;
      --text-muted: #666d7a;
      --border-color: rgba(22, 26, 32, 0.1);
      --border-strong: rgba(22, 26, 32, 0.18);
      --ease: cubic-bezier(0.22, 1, 0.36, 1);
    }

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: var(--bg-body);
      color: var(--text-main);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      -webkit-font-smoothing: antialiased;
    }

    a {
      color: inherit;
      text-decoration: none;
      transition: color 150ms var(--ease), border-color 150ms var(--ease), background-color 150ms var(--ease);
    }

    /* Top Utility Header */
    .auth-topbar {
      background: var(--bg-surface);
      border-bottom: 1px solid var(--border-color);
      height: 56px;
      display: flex;
      align-items: center;
    }

    .auth-topbar-inner {
      width: 100%;
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-secondary);
    }

    .back-link:hover {
      color: var(--navy);
    }

    .topbar-meta {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 12px;
      color: var(--text-muted);
    }

    /* Main Split Card */
    .auth-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
    }

    .auth-shell {
      width: 100%;
      max-width: 840px;
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(10, 20, 36, 0.06);
      overflow: hidden;
      display: grid;
      grid-template-columns: 0.9fr 1.1fr;
    }

    /* Left Identity Panel */
    .auth-aside {
      background: var(--navy);
      color: #f7f5f0;
      padding: 40px 32px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 32px;
    }

    .aside-logo {
      width: 56px;
      height: 56px;
      object-fit: contain;
      margin-bottom: 20px;
    }

    .aside-eyebrow {
      display: block;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 12px;
      color: var(--accent-gold);
      margin-bottom: 8px;
    }

    .aside-title {
      font-family: 'Newsreader', Georgia, serif;
      font-size: 28px;
      font-weight: 500;
      line-height: 1.15;
      letter-spacing: -0.02em;
      color: #f7f5f0;
      margin-bottom: 12px;
    }

    .aside-desc {
      font-size: 14px;
      line-height: 1.6;
      color: rgba(247, 245, 240, 0.74);
    }

    .aside-footer {
      padding-top: 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.12);
      font-size: 13px;
      color: rgba(247, 245, 240, 0.68);
      line-height: 1.5;
    }

    .aside-footer strong {
      display: block;
      font-weight: 500;
      color: #f7f5f0;
      margin-bottom: 2px;
    }

    /* Right Form Panel */
    .auth-form-panel {
      padding: 40px 36px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .auth-form-panel h1 {
      font-family: 'Newsreader', Georgia, serif;
      font-size: 30px;
      font-weight: 500;
      letter-spacing: -0.02em;
      color: var(--navy);
      margin-bottom: 6px;
    }

    .auth-subtitle {
      font-size: 14px;
      color: var(--text-secondary);
      margin-bottom: 28px;
    }

    .alert-error {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid rgba(220, 38, 38, 0.25);
      padding: 12px 14px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 13.5px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      line-height: 1.45;
    }

    .alert-error svg {
      flex-shrink: 0;
      margin-top: 2px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .form-control {
      width: 100%;
      height: 44px;
      padding: 0 12px;
      border: 1px solid var(--border-strong);
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      background: var(--bg-surface);
      color: var(--text-main);
      transition: border-color 150ms var(--ease), box-shadow 150ms var(--ease);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--navy);
      box-shadow: 0 0 0 3px rgba(8, 36, 71, 0.09);
    }

    .password-toggle-btn {
      position: absolute;
      right: 10px;
      width: 28px;
      height: 28px;
      background: transparent;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
    }

    .password-toggle-btn:hover {
      color: var(--navy);
    }

    .btn-login {
      width: 100%;
      height: 44px;
      background: var(--navy);
      color: #ffffff;
      font-family: inherit;
      font-weight: 500;
      font-size: 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background-color 150ms var(--ease), transform 150ms var(--ease);
    }

    .btn-login:hover {
      background: var(--navy-deep);
    }

    .btn-login:active {
      transform: scale(0.98);
    }

    .auth-footer-note {
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid var(--border-color);
      font-size: 13px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .auth-footer-note a {
      color: var(--navy);
      font-weight: 500;
    }

    .auth-footer-note a:hover {
      text-decoration: underline;
    }

    .page-footer {
      text-align: center;
      padding: 16px 24px;
      color: var(--text-muted);
      font-size: 12.5px;
    }

    @media (max-width: 760px) {
      .auth-shell {
        grid-template-columns: 1fr;
        max-width: 440px;
      }

      .auth-aside {
        padding: 28px 24px;
        gap: 16px;
      }

      .aside-footer {
        display: none;
      }

      .auth-form-panel {
        padding: 28px 24px;
      }

      .topbar-meta {
        display: none;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navigation -->
  <header class="auth-topbar">
    <div class="auth-topbar-inner">
      <a href="./" class="back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to School Website
      </a>
      <span class="topbar-meta">AL FATIHA INSTITUTE · COX'S BAZAR</span>
    </div>
  </header>

  <!-- Main Sign-In Container -->
  <main class="auth-wrapper">
    <div class="auth-shell">
      <aside class="auth-aside">
        <div>
          <a href="./" title="AL FATIHA INSTITUTE">
            <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Crest" class="aside-logo">
          </a>
          <span class="aside-eyebrow">Established 2025 · Portal</span>
          <h2 class="aside-title">AL FATIHA INSTITUTE</h2>
          <p class="aside-desc">
            Sign in with your school credentials to open your dashboard.
          </p>
        </div>

        <div class="aside-footer">
          <strong>Tekpara Main Road, Cox's Bazar</strong>
          <span>Office hours: Sun–Thu · 8:00 AM – 2:00 PM</span>
        </div>
      </aside>

      <section class="auth-form-panel">
        <h1>Sign in</h1>
        <p class="auth-subtitle">Enter your username or email and password.</p>

        <?php if ($error): ?>
          <div class="alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-group">
            <label for="username">Username or Email</label>
            <div class="input-wrapper">
              <input type="text" id="username" name="username" class="form-control" placeholder="Username or email" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
              <input type="password" id="password" name="password" class="form-control" placeholder="Password" required style="padding-right: 42px;">
              <button type="button" class="password-toggle-btn" id="passwordToggleBtn" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                <svg id="toggleIconShow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="toggleIconHide" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" name="login" class="btn-login">
            Sign in
          </button>
        </form>

        <div class="auth-footer-note">
          <span>Need access help?</span>
          <a href="contact.php">Contact school office</a>
        </div>
      </section>
    </div>
  </main>

  <footer class="page-footer">
    &copy; <?= date('Y') ?> AL FATIHA INSTITUTE · Cambridge Early Years &amp; Islamic Primary
  </footer>

  <script>
    function togglePasswordVisibility() {
      const pwd = document.getElementById('password');
      const iconShow = document.getElementById('toggleIconShow');
      const iconHide = document.getElementById('toggleIconHide');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        iconShow.style.display = 'none';
        iconHide.style.display = 'block';
      } else {
        pwd.type = 'password';
        iconShow.style.display = 'block';
        iconHide.style.display = 'none';
      }
    }
  </script>

</body>
</html>
