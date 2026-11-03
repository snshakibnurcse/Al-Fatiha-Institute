<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $role = get_current_user_role();
    if ($role === 'teacher') redirect('dashboard.php');
    if ($role === 'admin') redirect('../admin/dashboard.php');
    if ($role === 'parent') redirect('../portal/dashboard.php');
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

            if ($user['role'] === 'teacher') {
                redirect('dashboard.php');
            } elseif ($user['role'] === 'admin') {
                redirect('../admin/dashboard.php');
            } elseif ($user['role'] === 'parent') {
                redirect('../portal/dashboard.php');
            } else {
                redirect('../index.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Teacher Portal Login | AL FATIHA INSTITUTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-card">
    <a href="../index.php">
      <img src="../assets/images/logo.svg" alt="AL FATIHA INSTITUTE Logo" class="auth-logo">
    </a>
    <h2>Teacher Portal</h2>
    <p class="subtitle">AL FATIHA INSTITUTE • Faculty Access</p>

    <?php if ($error): ?>
      <div class="alert-msg alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label><i class="fas fa-chalkboard-teacher" style="color: var(--admin-gold-dark);"></i> Teacher Username</label>
        <input type="text" name="username" class="form-control" placeholder="teacher" required value="teacher">
      </div>

      <div class="form-group">
        <label><i class="fas fa-key" style="color: var(--admin-gold-dark);"></i> Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required value="teacher123">
      </div>

      <button type="submit" name="login" class="btn btn-block" style="background: var(--admin-gold); color: var(--admin-deep-navy); border-radius: 10px; border: none; font-weight: 800; cursor: pointer;">
        <i class="fas fa-sign-in-alt"></i> Login to Teacher Portal
      </button>
    </form>

    <div style="margin-top: 1.8rem; font-size: 0.8rem; color: var(--admin-text-muted);">
      <p><a href="../index.php" style="color: var(--admin-primary); text-decoration: none; font-weight: 700;"><i class="fas fa-arrow-left"></i> Back to School Website</a></p>
    </div>
  </div>
</div>

</body>
</html>
