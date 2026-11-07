<?php
require __DIR__ . '/includes/bootstrap.php';
if (auth()) { redirect('panel/dashboard.php'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $u = row('SELECT * FROM users WHERE email = ?', [$email]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        if ($u['status'] === 'pending') {
            $error = 'This landlord account is still waiting for administrator approval. You will be able to log in once it is approved.';
        } elseif ($u['status'] !== 'active') {
            $error = 'This account is ' . $u['status'] . '. Please contact the administrator.';
        } else {
            login_user((int) $u['id']);
            log_activity('auth.login', 'User', (int) $u['id'], 'Logged in');
            redirect(safe_next($_POST['next'] ?? $_GET['next'] ?? null));
        }
    } else { $error = 'Incorrect email or password.'; }
}
$title = 'Log in';
require __DIR__ . '/includes/public_head.php';
?>
<div class="auth-wrap"><div class="auth-card">
    <h2>Welcome back</h2><p class="sub">Log in as an administrator, landlord or tenant.</p>
    <?= flash_html() ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($_GET['next'] ?? '') ?>">
        <div class="field"><label>Email address</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn btn-primary btn-block">Log in</button>
    </form>
    <div class="form-foot"><a href="forgot.php" style="color:var(--purple-300)">Forgot password?</a> · No account? <a href="register.php" style="color:var(--purple-300)">Register</a></div>
    <div class="demo-hint">DEMO ACCOUNTS<br>admin&nbsp;&nbsp;&nbsp;&nbsp;→ admin@stayhaus.test / admin123<br>landlord → landlord@stayhaus.test / landlord123<br>tenant&nbsp;&nbsp;→ bea@example.com / tenant123</div>
</div></div>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
