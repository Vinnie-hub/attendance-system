<?php
// index.php  – Login page
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in?
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . (is_admin() ? 'admin' : 'employee') . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = auth_login($email, $password);
        if ($user) {
            $dest = BASE_URL . '/' . ($user['role'] === 'admin' ? 'admin' : 'employee') . '/dashboard.php';
            header("Location: $dest");
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-card">
  <div class="login-logo"><i class="bi bi-clock-fill"></i></div>

  <h1 class="text-center fw-700 mb-1" style="font-family:'Space Grotesk',sans-serif;font-size:1.5rem">
    Welcome back
  </h1>
  <p class="text-center text-muted mb-4" style="font-size:.875rem">
    Sign in to <?= APP_NAME ?>
  </p>

  <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2" style="font-size:.875rem">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
    <div class="alert alert-warning py-2" style="font-size:.875rem">Access denied.</div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="mb-3">
      <label class="form-label">Email address</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
        <input type="email" name="email" class="form-control"
          placeholder="you@company.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required autofocus>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label">Password</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
        <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required>
        <button type="button" class="input-group-text bg-white border-start-0"
          onclick="const p=document.getElementById('pwd');p.type=p.type==='password'?'text':'password'">
          <i class="bi bi-eye text-muted"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
      <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
    </button>
  </form>

</div>
</body>
</html>
