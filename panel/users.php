<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {   // approve a pending (landlord) registration
        $aid = (int) ($_POST['user_id'] ?? 0); $u = row('SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$aid]);
        if (!$u) { $errors[] = 'User not found.'; }
        elseif ($u['status'] !== 'pending') { $errors[] = 'Only pending accounts can be approved.'; }
        else {
            q('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', ['active', now(), $aid]);
            notify((int) $aid, 'account.approved', 'Account approved', 'Your account was approved by the administrator — you can now log in to StayHaus.', 'login.php');
            log_activity('user.approved', 'User', $aid, "Approved the pending account {$u['email']}");
            flash('success', 'Account approved — the user can now log in.');
            redirect('users.php');
        }
    }
    $first = clean_name($_POST['first_name'] ?? ''); $last = clean_name($_POST['last_name'] ?? ''); $phone = clean_phone($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
    $roleId = (int) val('SELECT id FROM roles WHERE slug = ?', [$_POST['role'] ?? '']);
    $status = in_array($_POST['status'] ?? '', ['active', 'pending', 'inactive', 'suspended'], true) ? $_POST['status'] : 'active';
    if ($first === '' || $last === '' || !$roleId) { $errors[] = 'First name, last name and role are required.'; }
    if ($phone !== '' && !valid_phone($phone)) { $errors[] = 'Please enter a valid 11-digit mobile number (0917-123-4567).'; }
    if ($action === 'add' && !$errors) {
        $email = trim($_POST['email'] ?? ''); $pw = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email.'; }
        elseif (val('SELECT 1 FROM users WHERE email = ?', [$email])) { $errors[] = 'That email is already registered.'; }
        if (strlen($pw) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
        if (!$errors) {
            q('INSERT INTO users (role_id, first_name, last_name, email, phone, address, password, status, email_verified_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$roleId, $first, $last, $email, $phone, $address, password_hash($pw, PASSWORD_DEFAULT), $status, now(), now(), now()]);
            log_activity('user.created', 'User', insert_id(), "Created user $email"); flash('success', 'User created.'); redirect('users.php');
        }
    } elseif ($action === 'edit' && !$errors) {
        $id = (int) $_POST['user_id']; $u = row('SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
        if (!$u) { $errors[] = 'User not found.'; }
        else {
            $newRole = val('SELECT slug FROM roles WHERE id = ?', [$roleId]);
            if ($id === (int) $me['id'] && ($newRole !== 'administrator' || $status !== 'active')) { $errors[] = 'You cannot demote or deactivate your own account.'; }
            elseif ($u['role'] === 'administrator' && ($newRole !== 'administrator' || $status !== 'active') && (int) val("SELECT COUNT(*) FROM users x JOIN roles r ON r.id = x.role_id WHERE r.slug = 'administrator' AND x.status = 'active'") <= 1) { $errors[] = 'At least one active administrator is required.'; }
            if (!$errors) {
                q('UPDATE users SET first_name=?, last_name=?, phone=?, address=?, role_id=?, status=?, updated_at=? WHERE id=?', [$first, $last, $phone, $address, $roleId, $status, now(), $id]);
                if (($_POST['password'] ?? '') !== '') { if (strlen($_POST['password']) < 8) { $errors[] = 'New password must be at least 8 characters.'; } else { q('UPDATE users SET password = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $id]); } }
                if (!$errors) { log_activity('user.updated', 'User', $id, "Updated user {$u['email']} (role $newRole, status $status)"); flash('success', 'User updated.'); redirect('users.php'); }
            }
        }
    }
}
$f = ['q' => trim($_GET['q'] ?? ''), 'role' => $_GET['role'] ?? '', 'status' => $_GET['status'] ?? ''];
$where = ['1=1']; $p = [];
if ($f['q'] !== '') { $where[] = "(u.first_name || ' ' || u.last_name LIKE ? OR u.email LIKE ?)"; array_push($p, "%{$f['q']}%", "%{$f['q']}%"); }
if (in_array($f['role'], ['administrator', 'landlord', 'tenant'], true)) { $where[] = 'r.slug = ?'; $p[] = $f['role']; }
if (in_array($f['status'], ['active', 'pending', 'inactive', 'suspended'], true)) { $where[] = 'u.status = ?'; $p[] = $f['status']; }
$W = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE $W", $p);
$pg = paginate($total, 12, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.address, u.status, u.last_login_at, u.created_at, r.slug AS role, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE $W ORDER BY r.id, u.first_name LIMIT 12 OFFSET {$pg['offset']}", $p);
$roles = rows('SELECT * FROM roles ORDER BY id');
$title = 'Users'; $nav = 'users';
require __DIR__ . '/../includes/panel_head.php';
$roleOpts = function ($sel = '') use ($roles) { $o = ''; foreach ($roles as $r) { $o .= '<option value="' . e($r['slug']) . '"' . ($sel === $r['slug'] ? ' selected' : '') . '>' . e($r['name']) . '</option>'; } return $o; };
$statusOpts = '<option value="active">Active</option><option value="pending">Pending approval</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option>';
?>
<div class="admin-topbar"><h1>Users</h1><div style="display:flex;gap:8px;align-items:center"><?php if ($pendU = (int) val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.status = 'pending'")): ?><a class="btn btn-outline btn-sm" href="users.php?status=pending">⏳ <?= $pendU ?> awaiting approval</a><?php endif; ?><button class="btn btn-primary" onclick="openModal('addUser')">+ Add user</button></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="get" class="toolbar">
    <div class="field"><label>Search</label><input name="q" value="<?= e($f['q']) ?>" placeholder="Name or email"></div>
    <div class="field"><label>Role</label><select name="role"><option value="">All</option><?= $roleOpts($f['role']) ?></select></div>
    <div class="field"><label>Status</label><select name="status"><option value="">All</option><?php foreach (['active', 'pending', 'inactive', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s === 'pending' ? 'Pending approval' : ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-outline btn-sm">Filter</button><a class="btn btn-ghost btn-sm" href="users.php">Reset</a></form>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Address</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($list as $u): ?><tr><td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['phone']) ?></td><td class="muted tiny" style="max-width:220px"><?= e($u['address'] ?? '') ?></td>
    <td><span class="pill pill-<?= e($u['role']) ?>"><?= e($u['role_name']) ?></span></td><td><?= badge($u['status']) ?></td><td class="muted tiny"><?= e(substr((string) $u['last_login_at'], 0, 16) ?: 'never') ?></td>
    <td><?= $u['status'] === 'pending' ? '<form method="post" class="inline" style="display:inline;margin-right:6px">' . csrf_field() . '<input type="hidden" name="action" value="approve"><input type="hidden" name="user_id" value="' . (int) $u['id'] . '"><button class="btn btn-primary btn-sm">Approve</button></form>' : '' ?><button class="btn btn-ghost btn-sm" onclick='editUser(<?= e(json_encode($u)) ?>)'>Edit</button></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="8" class="muted">No users found.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg) ?>
<p class="muted tiny">Users are deactivated or suspended instead of deleted, so booking and payment history is never lost.</p>

<div class="modal-overlay" id="addUser"><div class="modal-box"><button class="modal-close" onclick="closeModal('addUser')">✕</button><h3>Add user</h3>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="two"><div class="field"><label>First name</label><input name="first_name" required></div><div class="field"><label>Last name</label><input name="last_name" required></div></div>
    <div class="field"><label>Email</label><input type="email" name="email" required></div><div class="field"><label>Phone</label><input name="phone"></div>
    <div class="field"><label>Address</label><input name="address"></div>
    <div class="two"><div class="field"><label>Role</label><select name="role"><?= $roleOpts('tenant') ?></select></div><div class="field"><label>Status</label><select name="status"><?= $statusOpts ?></select></div></div>
    <div class="field"><label>Temporary password (min 8)</label><input type="password" name="password" required></div><button class="btn btn-primary btn-block">Create user</button></form></div></div>
<div class="modal-overlay" id="editUser"><div class="modal-box"><button class="modal-close" onclick="closeModal('editUser')">✕</button><h3>Edit user</h3>
<form method="post" id="editUserForm"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="user_id">
    <div class="two"><div class="field"><label>First name</label><input name="first_name" required></div><div class="field"><label>Last name</label><input name="last_name" required></div></div>
    <div class="field"><label>Email (fixed)</label><input id="eEmail" disabled></div><div class="field"><label>Phone</label><input name="phone"></div>
    <div class="field"><label>Address</label><input name="address"></div>
    <div class="two"><div class="field"><label>Role</label><select name="role"><?= $roleOpts() ?></select></div><div class="field"><label>Status</label><select name="status"><?= $statusOpts ?></select></div></div>
    <div class="field"><label>New password (leave blank to keep)</label><input type="password" name="password"></div><button class="btn btn-primary btn-block">Save user</button></form></div></div>
<script>function editUser(u){var f=document.getElementById('editUserForm');f.user_id.value=u.id;f.first_name.value=u.first_name;f.last_name.value=u.last_name;f.phone.value=u.phone||'';f.address.value=u.address||'';f.role.value=u.role;f.status.value=u.status;document.getElementById('eEmail').value=u.email;openModal('editUser');}</script>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
