<?php
require_once __DIR__ . '/includes/functions.php';

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
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="icon" type="image/png" href="assets/images/logo.png?v=2">
  <style>
    body {
      background: linear-gradient(135deg, #032d4e 0%, #064878 50%, #0e73b9 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      margin: 0;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .auth-wrapper {
      padding: 3rem 1.25rem;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-grow: 1;
    }
    .unified-auth-card {
      background: #ffffff;
      border-radius: 24px;
      width: 100%;
      max-width: 420px;
      padding: 2.75rem 2.25rem;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
      text-align: center;
      position: relative;
      border: 1px solid rgba(255, 255, 255, 0.4);
    }
    .auth-logo {
      height: 76px;
      width: auto;
      margin: 0 auto 1.2rem auto;
      display: block;
      transition: transform 0.25s ease;
    }
    .auth-logo:hover {
      transform: scale(1.04);
    }
    .unified-auth-card h2 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.7rem;
      color: #032d4e;
      margin: 0 0 0.4rem 0;
      font-weight: 800;
      letter-spacing: -0.3px;
    }
    .unified-auth-card .subtitle {
      color: #64748b;
      font-size: 0.92rem;
      margin: 0 0 1.8rem 0;
      line-height: 1.45;
    }
    .form-group {
      text-align: left;
      margin-bottom: 1.35rem;
    }
    .form-group label {
      display: flex;
      align-items: center;
      gap: 7px;
      font-weight: 700;
      font-size: 0.85rem;
      color: #1e293b;
      margin-bottom: 0.5rem;
    }
    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .form-control {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid #cbd5e1;
      border-radius: 12px;
      font-size: 0.95rem;
      font-family: inherit;
      background: #f8fafc;
      color: #0f172a;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }
    .form-control:focus {
      outline: none;
      background: #ffffff;
      border-color: #0e73b9;
      box-shadow: 0 0 0 4px rgba(14, 115, 185, 0.12);
    }
    .password-toggle-btn {
      position: absolute;
      right: 12px;
      background: transparent;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      font-size: 1rem;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s ease;
    }
    .password-toggle-btn:hover {
      color: #0e73b9;
    }
    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, #f3b527 0%, #cd8707 100%);
      color: #032d4e;
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.05rem;
      padding: 0.95rem;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.25s ease;
      box-shadow: 0 6px 18px rgba(205, 135, 7, 0.35);
      margin-top: 0.6rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
    }
    .btn-login:hover {
      background: linear-gradient(135deg, #e5a71a 0%, #ba7703 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(205, 135, 7, 0.45);
    }
    .btn-login:active {
      transform: translateY(0);
    }
    .login-footer-link {
      margin-top: 1.8rem;
      padding-top: 1.25rem;
      border-top: 1px solid #f1f5f9;
      font-size: 0.88rem;
    }
    .login-footer-link a {
      color: #0e73b9;
      text-decoration: none;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: color 0.2s ease;
    }
    .login-footer-link a:hover {
      color: #032d4e;
      text-decoration: underline;
    }
    @media (max-width: 480px) {
      .unified-auth-card {
        padding: 2.2rem 1.5rem;
        border-radius: 20px;
      }
      .unified-auth-card h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>

  <!-- Minimal Top Header -->
  <header style="background: rgba(255,255,255,0.06); backdrop-filter: blur(10px); padding: 0.9rem 0; border-bottom: 1px solid rgba(255,255,255,0.08);">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 1.25rem;">
      <a href="./" style="color: #ffffff; text-decoration: none; font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.5rem; opacity: 0.95;">
        <i class="fas fa-arrow-left" style="color: #f3b527;"></i> Back to School Website
      </a>
      <span style="color: rgba(255,255,255,0.75); font-size: 0.82rem; font-weight: 600; letter-spacing: 0.3px;">
        AL FATIHA INSTITUTE
      </span>
    </div>
  </header>

  <!-- Clean Auth Card Section -->
  <div class="auth-wrapper">
    <div class="unified-auth-card">
      <a href="./" title="AL FATIHA INSTITUTE">
        <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Logo" class="auth-logo">
      </a>
      <h2>Portal Login</h2>
      <p class="subtitle">Enter your credentials to access your portal</p>

      <?php if ($error): ?>
        <div class="alert-msg alert-danger" style="background: #fef2f2; color: #b91c1c; border: 1.5px solid #fecaca; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.35rem; font-size: 0.86rem; font-weight: 600; text-align: left; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-exclamation-circle" style="font-size: 1rem; flex-shrink: 0;"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="username"><i class="fas fa-user-circle" style="color: #0e73b9;"></i> Username or Email</label>
          <div class="input-wrapper">
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter username or email" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="password"><i class="fas fa-lock" style="color: #0e73b9;"></i> Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required style="padding-right: 42px;">
            <button type="button" class="password-toggle-btn" id="passwordToggleBtn" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="login" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Sign In to Portal
        </button>
      </form>

      <div class="login-footer-link">
        <a href="./">
          <i class="fas fa-home"></i> Return to Homepage
        </a>
      </div>
    </div>
  </div>

  <!-- Minimal Footer -->
  <footer style="text-align: center; padding: 1.25rem 1rem; color: rgba(255,255,255,0.65); font-size: 0.82rem;">
    &copy; <?= date('Y') ?> AL FATIHA INSTITUTE. All Rights Reserved.
  </footer>

  <script>
    function togglePasswordVisibility() {
      const pwd = document.getElementById('password');
      const icon = document.getElementById('toggleIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>

</body>
</html>
