<?php
// login.php
/*session_start();
if (!empty($_SESSION['logged_in'])) {
    header('Location: dashboard.php'); exit;
}*/
require_once 'includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (Auth::login($email, $password)) {
        header('Location: dashboard.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BHMS — Login</title>
<link rel="stylesheet" href="css/style.css">
<style>
body {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: var(--bg-deep);
  background-image:
    radial-gradient(ellipse 60% 50% at 50% 0%, rgba(201,168,76,0.06) 0%, transparent 70%);
}
.login-box {
  width: 100%;
  max-width: 400px;
  padding: 24px;
}
.login-header {
  text-align: center;
  margin-bottom: 40px;
}
.login-brand {
  font-family: 'Cormorant Garamond', serif;
  font-size: 40px;
  font-weight: 300;
  color: var(--gold);
  letter-spacing: 0.1em;
  display: block;
}
.login-sub {
  font-size: 11px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin-top: 4px;
}
.login-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-top: 2px solid var(--gold);
  border-radius: var(--radius);
  padding: 32px;
}
.login-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 22px;
  font-weight: 500;
  margin-bottom: 6px;
}
.login-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 28px;
}
.alert-error {
  background: var(--red-dim);
  border: 1px solid rgba(224,92,92,0.3);
  color: var(--red);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 20px;
}
.login-footer {
  text-align: center;
  margin-top: 24px;
  font-size: 12px;
  color: var(--text-dim);
}
.gold-divider {
  width: 40px;
  height: 1px;
  background: var(--gold);
  margin: 28px auto;
  opacity: 0.4;
}
</style>
</head>
<body>
<div class="login-box">
  <div class="login-header">
    <span class="login-brand">BHMS</span>
    <span class="login-sub">Boutique Hotel Management Suite</span>
  </div>

  <div class="login-card">
    <div class="login-title">Welcome back</div>
    <div class="login-desc">Sign in with your staff credentials</div>

    <?php if ($error): ?>
    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email address</label>
        <input type="email" name="email" class="form-control"
               placeholder="staff@hotel.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-gold btn-lg" style="width:100%;justify-content:center;margin-top:8px">
        Sign In →
      </button>
    </form>
  </div>

  <div class="login-footer">
    Capital University · CS251 Software Engineering 1
  </div>
</div>
</body>
</html>
