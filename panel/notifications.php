<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login(); $uid = (int) $me['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'read_all') { q("UPDATE notifications SET read_at = ? WHERE notifiable_type = 'User' AND notifiable_id = ? AND read_at IS NULL", [now(), $uid]); }
    elseif (($_POST['action'] ?? '') === 'read') { q("UPDATE notifications SET read_at = ? WHERE id = ? AND notifiable_id = ?", [now(), $_POST['id'] ?? '', $uid]); }
    redirect('notifications.php');
}
$list = rows("SELECT * FROM notifications WHERE notifiable_type = 'User' AND notifiable_id = ? ORDER BY created_at DESC, rowid DESC LIMIT 50", [$uid]);
$title = 'Notifications'; $nav = 'notifications';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Notifications</h1>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline btn-sm">Mark all as read</button></form></div>
<div class="table-wrap">
<?php foreach ($list as $n): $d = json_decode($n['data'], true) ?: []; ?>
    <div class="notif <?= $n['read_at'] ? '' : 'unread' ?>">
        <div style="flex:1"><div class="t"><?= e($d['title'] ?? $n['type']) ?></div><div class="muted"><?= e($d['message'] ?? '') ?></div>
            <div class="tiny muted" style="margin-top:4px"><?= e(substr($n['created_at'], 0, 16)) ?><?php if (!empty($d['url'])): ?> · <a href="<?= e($d['url']) ?>" style="color:var(--purple-300)">Open</a><?php endif; ?></div></div>
        <?php if (!$n['read_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= e($n['id']) ?>"><button class="btn btn-ghost btn-sm">Mark read</button></form><?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$list): ?><div class="empty-state">No notifications yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
