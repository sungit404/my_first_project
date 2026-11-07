<?php
require __DIR__ . '/includes/bootstrap.php';
$link = null; $sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $sent = true;
    if (val('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $token = bin2hex(random_bytes(20));
        q('DELETE FROM password_reset_tokens WHERE email = ?', [$email]);
        q('INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?,?,?)', [$email, password_hash($token, PASSWORD_DEFAULT), now()]);
        // No mail server in this demo, so the reset link is shown on screen.
        $link = 'reset.php?email=' . urlencode($email) . '&token=' . $token;
    }
}
$title = 'Forgot password';
require __DIR__ . '/includes/public_head.php';
?>
<div class="auth-wrap"><div class="auth-card">
    <h2>Reset password</h2><p class="sub">Enter your email to get a reset link.</p>
    <?php if ($sent): ?>
        <div class="alert alert-info">If that email is registered, a reset link was created.
        <?php if ($link): ?><br><strong>Demo mode (no email server):</strong> <a href="<?= e($link) ?>" style="text-decoration:underline">open your reset link</a>. It works for 60 minutes.<?php endif; ?></div>
    <?php endif; ?>
    <form method="post"><?= csrf_field() ?>
        <div class="field"><label>Email address</label><input type="email" name="email" required></div>
        <button class="btn btn-primary btn-block">Create reset link</button>
    </form>
    <div class="form-foot"><a href="login.php" style="color:var(--purple-300)">Back to login</a></div>
</div></div>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
