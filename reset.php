<?php
require __DIR__ . '/includes/bootstrap.php';
$email = trim($_GET['email'] ?? $_POST['email'] ?? ''); $token = $_GET['token'] ?? $_POST['token'] ?? '';
$rec = row('SELECT * FROM password_reset_tokens WHERE email = ?', [$email]);
$valid = $rec && password_verify((string) $token, $rec['token']) && strtotime($rec['created_at']) > time() - 3600;
$error = null;
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) { $error = 'Password must be at least 8 characters.'; }
    elseif ($pw !== ($_POST['confirm_password'] ?? '')) { $error = 'Passwords do not match.'; }
    else {
        q('UPDATE users SET password = ?, updated_at = ? WHERE email = ?', [password_hash($pw, PASSWORD_DEFAULT), now(), $email]);
        q('DELETE FROM password_reset_tokens WHERE email = ?', [$email]);
        flash('success', 'Password updated. You can log in now.');
        redirect('login.php');
    }
}
$title = 'Set new password';
require __DIR__ . '/includes/public_head.php';
?>
<div class="auth-wrap"><div class="auth-card">
    <h2>Set a new password</h2>
    <?php if (!$valid): ?><div class="alert alert-error">This reset link is invalid or has expired.</div><a href="forgot.php" class="btn btn-primary btn-block">Request a new link</a>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($email) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="field"><label>New password</label><input type="password" name="password" required></div>
            <div class="field"><label>Confirm password</label><input type="password" name="confirm_password" required></div>
            <button class="btn btn-primary btn-block">Update password</button></form>
    <?php endif; ?>
</div></div>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
