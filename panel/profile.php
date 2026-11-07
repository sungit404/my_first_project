<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login(); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = clean_name($_POST['first_name'] ?? ''); $last = clean_name($_POST['last_name'] ?? ''); $phone = clean_phone($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
    if ($first === '' || $last === '') { $errors[] = 'First and last name are required.'; }
    if ($phone !== '' && !valid_phone($phone)) { $errors[] = 'Please enter a valid 11-digit mobile number (0917-123-4567).'; }
    $newpw = $_POST['new_password'] ?? '';
    if ($newpw !== '') {
        if (!password_verify($_POST['current_password'] ?? '', $me['password'])) { $errors[] = 'Current password is incorrect.'; }
        elseif (strlen($newpw) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
    }
    if (!$errors) {
        q('UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, updated_at = ? WHERE id = ?', [$first, $last, $phone, $address, now(), $me['id']]);
        if ($newpw !== '') { q('UPDATE users SET password = ? WHERE id = ?', [password_hash($newpw, PASSWORD_DEFAULT), $me['id']]); }
        log_activity('user.profile_updated', 'User', (int) $me['id'], 'Updated own profile');
        flash('success', 'Profile updated.'); redirect('profile.php');
    }
}
$title = 'My Profile'; $nav = 'profile';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>My Profile</h1></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="panel" style="max-width:640px"><?= csrf_field() ?>
    <div class="form-grid">
        <div class="field"><label>First name</label><input name="first_name" value="<?= e($me['first_name']) ?>" required></div>
        <div class="field"><label>Last name</label><input name="last_name" value="<?= e($me['last_name']) ?>" required></div>
        <div class="field"><label>Email (cannot be changed)</label><input value="<?= e($me['email']) ?>" disabled></div>
        <div class="field"><label>Phone (11-digit mobile)</label><input name="phone" value="<?= e($me['phone']) ?>" placeholder="0917-123-4567"></div>
        <div class="field"><label>Role</label><input value="<?= e($me['role_name']) ?>" disabled></div>
        <div class="field full"><label><?= $me['role'] === 'landlord' ? 'Property address (shown on your room listings)' : 'Address' ?></label><input name="address" value="<?= e($me['address'] ?? '') ?>" placeholder="Street, barangay, city, zip"></div>
        <div class="field"><label>Current password (only to change it)</label><input type="password" name="current_password"></div>
        <div class="field"><label>New password</label><input type="password" name="new_password"></div>
    </div>
    <button class="btn btn-primary">Save profile</button>
</form>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
