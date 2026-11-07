<?php
require __DIR__ . '/includes/bootstrap.php';
if (auth()) { redirect('panel/dashboard.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = clean_name($_POST['first_name'] ?? ''); $last = clean_name($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? ''); $phone = clean_phone($_POST['phone'] ?? '');
    $role = ($_POST['role'] ?? '') === 'landlord' ? 'landlord' : 'tenant';   // admins cannot self-register
    $pw = $_POST['password'] ?? '';
    if ($first === '' || $last === '' || $email === '') { $errors[] = 'Please fill in all required fields.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email address.'; }
    if ($phone !== '' && !valid_phone($phone)) { $errors[] = 'Please enter a valid 11-digit mobile number (0917-123-4567).'; }
    if (strlen($pw) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
    if ($pw !== ($_POST['confirm_password'] ?? '')) { $errors[] = 'Passwords do not match.'; }
    if (!$errors && val('SELECT 1 FROM users WHERE email = ?', [$email])) { $errors[] = 'An account with that email already exists.'; }
    if (!$errors) {
        $needsApproval = $role === 'landlord';   // landlords are staff-level: an administrator approves each one; tenants are active at once
        q('INSERT INTO users (role_id, first_name, last_name, email, phone, password, status, created_at, updated_at) VALUES ((SELECT id FROM roles WHERE slug = ?),?,?,?,?,?,?,?,?)',
          [$role, $first, $last, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $needsApproval ? 'pending' : 'active', now(), now()]);
        $id = insert_id();
        if ($needsApproval) {
            log_activity('user.registered', 'User', $id, "New landlord account awaiting approval: $email");
            notify_admins('user.registered', 'New landlord needs approval', "$first $last registered as a landlord and is waiting for your approval in Users.", 'users.php');
            flash('success', 'Your landlord account was created. An administrator needs to approve it before you can log in — you will be notified once it is approved.');
            redirect('login.php');
        }
        login_user($id);
        log_activity('user.registered', 'User', $id, "New $role account: $email");
        notify_admins('user.registered', 'New ' . $role . ' registered', "$first $last just created an account.", 'users.php');
        flash('success', 'Welcome to StayHaus! Your account is ready.');
        redirect('panel/dashboard.php');
    }
}
$title = 'Create account';
require __DIR__ . '/includes/public_head.php';
?>
<div class="auth-wrap"><div class="auth-card" style="max-width:480px">
    <h2>Create your account</h2><p class="sub">Tenants book rooms. Landlords list rooms for approval.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
        <div class="field"><label>I am a</label><select name="role"><option value="tenant">Tenant (looking for a room)</option><option value="landlord" <?= ($_POST['role'] ?? '') === 'landlord' ? 'selected' : '' ?>>Landlord (I have rooms to rent)</option></select></div>
        <div class="two"><div class="field"><label>First name</label><input name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" required></div>
            <div class="field"><label>Last name</label><input name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" required></div></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
        <div class="field"><label>Phone (11-digit mobile)</label><input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="0917-123-4567"></div>
        <div class="two"><div class="field"><label>Password</label><input type="password" name="password" required></div>
            <div class="field"><label>Confirm</label><input type="password" name="confirm_password" required></div></div>
        <button class="btn btn-primary btn-block">Create account</button>
    </form>
    <div class="form-foot">Already registered? <a href="login.php" style="color:var(--purple-300)">Log in</a></div>
</div></div>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
