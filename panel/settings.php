<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $new = (array) ($_POST['s'] ?? []);
    if (isset($new['min_booking_days']) && (!ctype_digit((string) $new['min_booking_days']) || (int) $new['min_booking_days'] < 1)) { $errors[] = 'Minimum booking days must be a whole number of at least 1.'; }
    if (isset($new['rooms_per_page']) && (!ctype_digit((string) $new['rooms_per_page']) || (int) $new['rooms_per_page'] < 3 || (int) $new['rooms_per_page'] > 48)) { $errors[] = 'Rooms per page must be between 3 and 48.'; }
    if (isset($new['site_name']) && trim($new['site_name']) === '') { $errors[] = 'Site name cannot be empty.'; }
    $nk = trim($_POST['new_key'] ?? '');
    if ($nk !== '' && (!preg_match('/^[a-z][a-z0-9_]{1,60}$/', $nk) || val('SELECT 1 FROM settings WHERE "key" = ?', [$nk]))) { $errors[] = 'New key must be lowercase letters, numbers or underscores, and not already exist.'; }
    if ($errors) { flash('error', implode(' ', $errors)); redirect('settings.php'); }
    foreach ($new as $k => $v) { q('UPDATE settings SET value = ?, updated_at = ? WHERE "key" = ?', [trim((string) $v), now(), $k]); }
    if ($nk !== '') { q('INSERT INTO settings ("key", value, description, created_at, updated_at) VALUES (?,?,?,?,?)', [$nk, trim($_POST['new_value'] ?? ''), trim($_POST['new_desc'] ?? ''), now(), now()]); }
    log_activity('settings.updated', 'Setting', null, 'Updated system settings'); flash('success', 'Settings saved.'); redirect('settings.php');
}
$list = rows('SELECT * FROM settings ORDER BY "key"');
$title = 'Settings'; $nav = 'settings';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>System Settings</h1></div>
<form method="post" class="panel" style="max-width:720px"><?= csrf_field() ?>
    <?php foreach ($list as $s): ?><div class="field"><label><?= e($s['key']) ?> <span class="muted tiny">— <?= e($s['description']) ?></span></label><input name="s[<?= e($s['key']) ?>]" value="<?= e($s['value']) ?>"></div><?php endforeach; ?>
    <details class="box"><summary>Add a new setting</summary><div class="form-grid" style="margin-top:12px">
        <div class="field"><label>Key</label><input name="new_key" placeholder="e.g. whatsapp_number"></div><div class="field"><label>Value</label><input name="new_value"></div><div class="field full"><label>Description</label><input name="new_desc"></div></div></details>
    <button class="btn btn-primary">Save settings</button>
</form>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
