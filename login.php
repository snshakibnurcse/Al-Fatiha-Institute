<?php
require_once __DIR__ . '/includes/functions.php';

// Handle Logout action
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    redirect('login.php');
}

// Redirect if already logged in
if (is_logged_in()) {
    $role = get_current_user_role();
    if ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    } elseif ($role === 'parent') {
        redirect('portal/dashboard.php');
    } elseif ($role === 'accountant') {
        redirect('accountant/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND (status = 'active' OR status IS NULL) LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];

            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($user['role'] === 'teacher') {
                redirect('teacher/dashboard.php');
            } elseif ($user['role'] === 'parent') {
                redirect('portal/dashboard.php');
            } elseif ($user['role'] === 'accountant') {
                redirect('accountant/dashboard.php');
            } else {
                redirect('index.php');
            }
        } else {
            $error = 'Invalid User ID / Username or Password. Please try again.';
        }
    } else {
        $error = 'Please enter both User ID / Username and Password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Portal Login | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Unified Login Portal for Admin, Teachers, Parents, and Accountants at AL FATIHA INSTITUTE">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="icon" type="image/png" href="assets/images/logo.png?v=2">
  <style>
    body {
      background: linear-gradient(135deg, #032d4e 0%, #064878 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      margin: 0;
    }
    .auth-wrapper {
      padding: 3rem 1rem;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-grow: 1;
    }
    .unified-auth-card {
      background: #ffffff;
      border-radius: 24px;
      width: 100%;
      max-width: 440px;
      padding: 2.5rem;
      box-shadow: 0 20px 50px rgba(0,0,0,0.25);
      text-align: center;
      position: relative;
    }
    .auth-logo {
      height: 75px;
      width: auto;
      margin: 0 auto 1.2rem auto;
    }
    .unified-auth-card h2 {
      font-size: 1.6rem;
      color: #032d4e;
      margin-bottom: 0.3rem;
      font-weight: 800;
    }
    .unified-auth-card .subtitle {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 1.8rem;
    }
    .role-pills {
      display: flex;
      justify-content: center;
      gap: 0.4rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .role-pill {
      background: #f1f5f9;
      color: #475569;
      padding: 0.3rem 0.7rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }
    .role-pill i {
      font-size: 0.8rem;
    }
    .role-pill.admin i { color: #064878; }
    .role-pill.teacher i { color: #cd8707; }
    .role-pill.parent i { color: #0ea5e9; }
    .role-pill.accountant i { color: #10b981; }
    
    .form-group {
      text-align: left;
      margin-bottom: 1.25rem;
    }
    .form-group label {
      display: block;
      font-weight: 700;
      font-size: 0.85rem;
      color: #334155;
      margin-bottom: 0.4rem;
    }
    .form-control {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid #cbd5e1;
      border-radius: 12px;
      font-size: 0.95rem;
      font-family: inherit;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }
    .form-control:focus {
      outline: none;
      border-color: #0e73b9;
      box-shadow: 0 0 0 4px rgba(14, 115, 185, 0.12);
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
      transition: all 0.3s ease;
      box-shadow: 0 6px 18px rgba(205, 135, 7, 0.35);
      margin-top: 0.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    .btn-login:hover {
      background: linear-gradient(135deg, #cd8707 0%, #a66a00 100%);
      color: #ffffff;
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(205, 135, 7, 0.45);
    }
    .demo-hint {
      margin-top: 1.5rem;
      padding: 0.85rem;
      background: #f8fafc;
      border-radius: 12px;
      border: 1px dashed #cbd5e1;
      font-size: 0.8rem;
      color: #64748b;
      text-align: left;
    }
    .demo-hint strong {
      color: #032d4e;
    }
    .demo-hint ul {
      margin: 0.3rem 0 0 1.2rem;
      padding: 0;
    }
  </style>
</head>
<body>

  <!-- Minimal Header -->
  <header style="background: rgba(255,255,255,0.08); padding: 1rem 0;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
      <a href="index.php" style="color: #ffffff; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-arrow-left" style="color: #f3b527;"></i> Back to Website
      </a>
      <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem; font-weight: 600;">
        AL FATIHA INSTITUTE
      </span>
    </div>
  </header>

  <!-- Auth Card Section -->
  <div class="auth-wrapper">
    <div class="unified-auth-card">
      <a href="index.php">
        <img src="assets/images/logo.png?v=2" alt="AL FATIHA INSTITUTE Logo" class="auth-logo">
      </a>
      <h2>Portal Login</h2>
      <p class="subtitle">Enter your User ID and Password to sign in</p>

      <div class="role-pills">
        <span class="role-pill admin"><i class="fas fa-user-shield"></i> Admin</span>
        <span class="role-pill teacher"><i class="fas fa-chalkboard-teacher"></i> Teacher</span>
        <span class="role-pill parent"><i class="fas fa-user-graduate"></i> Parent</span>
        <span class="role-pill accountant"><i class="fas fa-calculator"></i> Accountant</span>
      </div>

      <?php if ($error): ?>
        <div class="alert-msg alert-danger" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 1.2rem; font-size: 0.85rem; font-weight: 600; text-align: left;">
          <i class="fas fa-exclamation-circle" style="margin-right: 0.4rem;"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="username"><i class="fas fa-user" style="color: #0e73b9;"></i> User ID / Username</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="Enter username or email" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="password"><i class="fas fa-key" style="color: #0e73b9;"></i> Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>

        <button type="submit" name="login" class="btn-login">
          <i class="fas fa-sign-in-alt"></i> Sign In to Portal
        </button>
      </form>

      <div class="demo-hint">
        <strong><i class="fas fa-info-circle"></i> Quick Demo Logins:</strong>
        <ul>
          <li><strong>Admin:</strong> <code>admin</code> / <code>admin123</code></li>
          <li><strong>Teacher:</strong> <code>teacher</code> / <code>teacher123</code></li>
          <li><strong>Parent:</strong> <code>parent</code> / <code>parent123</code></li>
          <li><strong>Accountant:</strong> <code>accountant</code> / <code>accountant123</code></li>
        </ul>
      </div>

      <div style="margin-top: 1.5rem; font-size: 0.8rem; color: #94a3b8;">
        <a href="index.php" style="color: #0e73b9; text-decoration: none; font-weight: 700;">
          <i class="fas fa-home"></i> Return to Homepage
        </a>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer style="text-align: center; padding: 1rem; color: rgba(255,255,255,0.6); font-size: 0.8rem;">
    &copy; <?= date('Y') ?> AL FATIHA INSTITUTE. All Rights Reserved.
  </footer>

</body>
</html>
