<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
$f = ['action' => trim($_GET['action'] ?? ''), 'user' => (int) ($_GET['user'] ?? 0), 'q' => trim($_GET['q'] ?? '')];
$where = ['1=1']; $p = [];
if ($f['action'] !== '') { $where[] = 'a.action = ?'; $p[] = $f['action']; }
if ($f['user']) { $where[] = 'a.user_id = ?'; $p[] = $f['user']; }
if ($f['q'] !== '') { $where[] = 'a.description LIKE ?'; $p[] = "%{$f['q']}%"; }
$W = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM activity_logs a WHERE $W", $p);
$pg = paginate($total, 20, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT a.*, u.first_name, u.last_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $W ORDER BY a.id DESC LIMIT 20 OFFSET {$pg['offset']}", $p);
$actions = rows('SELECT DISTINCT action FROM activity_logs ORDER BY action');
$users = rows("SELECT id, first_name, last_name FROM users ORDER BY first_name");
$title = 'Activity Logs'; $nav = 'logs';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Activity Logs</h1><span class="muted"><?= $total ?> entries</span></div>
<form method="get" class="toolbar">
    <div class="field"><label>Action</label><select name="action"><option value="">All</option><?php foreach ($actions as $a): ?><option <?= $f['action'] === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>User</label><select name="user"><option value="">All</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $f['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Text</label><input name="q" value="<?= e($f['q']) ?>"></div>
    <button class="btn btn-outline btn-sm">Filter</button><a class="btn btn-ghost btn-sm" href="logs.php">Reset</a></form>
<div class="table-wrap"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead><tbody>
<?php foreach ($list as $l): ?><tr><td class="muted tiny"><?= e(substr($l['created_at'], 0, 16)) ?></td><td><?= e(trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'System') ?></td><td><span class="chip"><?= e($l['action']) ?></span></td>
    <td class="muted tiny"><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td><td><?= e($l['description']) ?></td><td class="muted tiny"><?= e($l['ip_address']) ?></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="6" class="muted">No log entries.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg) ?>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
