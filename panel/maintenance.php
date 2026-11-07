<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
$isTenant = $me['role'] === 'tenant';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    [$ok, $msg] = $action === 'create' ? maintenance_create($_POST) : maintenance_update((int) ($_POST['request_id'] ?? 0), $action, $_POST['notes'] ?? '');
    flash($ok ? 'success' : 'error', $msg); redirect('maintenance.php');
}
// tenants see their own reports, landlords see reports on their rooms, admins see everything
if ($isTenant || $me['role'] === 'landlord') { $where = $isTenant ? 'm.tenant_id = ?' : 'r.created_by = ?'; $p = [$me['id']]; }
else { $where = '1=1'; $p = []; }
$list = rows("SELECT m.*, r.room_number, u.first_name, u.last_name, du.first_name AS df, du.last_name AS dl
              FROM maintenance_requests m JOIN rooms r ON r.id = m.room_id JOIN users u ON u.id = m.tenant_id
              LEFT JOIN users du ON du.id = m.resolved_by WHERE $where ORDER BY (m.status != 'resolved') DESC, m.id DESC LIMIT 100", $p);
// tenants pick the room to report about from their own rentals
$myRooms = $isTenant ? rows("SELECT DISTINCT r.id, r.room_number, r.title FROM rooms r JOIN bookings b ON b.room_id = r.id
                             WHERE b.tenant_id = ? AND b.status IN ('confirmed','completed') ORDER BY r.room_number", [$me['id']]) : [];
$cats = maintenance_categories();
$title = 'Maintenance'; $nav = 'maintenance';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Maintenance &amp; Complaints</h1></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($isTenant): ?>
<div class="panel" style="margin-bottom:22px">
    <h3>Report a problem</h3>
    <p class="muted tiny" style="margin:2px 0 12px">Broken facilities, water interruptions, electrical issues — report it here and the landlord is notified at once.</p>
    <?php if (!$myRooms): ?><p class="muted">You have no room rentals yet, so there is nothing to report.</p>
    <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field"><label>Room</label><select name="room_id" required><option value="">Choose…</option><?php foreach ($myRooms as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['room_number'] . ' · ' . $r['title']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Category</label><select name="category" required><?php foreach ($cats as $k => $c): ?><option value="<?= $k ?>"><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="field full"><label>What is the problem? <span class="muted tiny">— where is it, since when, what is affected</span></label><textarea name="description" required placeholder="e.g. The shower has had almost no water pressure since Monday."></textarea></div>
        </div>
        <button class="btn btn-primary btn-sm">Send report</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="table-wrap"><div class="table-head"><strong><?= $isTenant ? 'My reports' : 'Problem reports' ?></strong><span class="muted tiny"><?= count($list) ?> shown — unresolved first</span></div>
    <table><thead><tr><th>Room</th><?= $isTenant ? '' : '<th>Tenant</th>' ?><th>Category</th><th>Problem</th><th>Status</th><th>Resolution</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $m): ?>
        <tr><td><strong><?= e($m['room_number']) ?></strong></td>
        <?= $isTenant ? '' : '<td>' . e($m['first_name'] . ' ' . $m['last_name']) . '</td>' ?>
        <td><?= e($cats[$m['category']] ?? ucfirst($m['category'])) ?></td>
        <td style="max-width:340px"><?= e($m['description']) ?><br><span class="muted tiny">reported <?= e(substr($m['created_at'], 0, 10)) ?></span></td>
        <td><?= badge($m['status'] === 'in_progress' ? 'in_progress' : $m['status']) ?></td>
        <td class="muted tiny" style="max-width:260px"><?= $m['resolution_notes'] ? e($m['resolution_notes']) . '<br>— ' . e($m['df'] ? $m['df'] . ' ' . $m['dl'] : '') : '—' ?></td>
        <td><?php if (!$isTenant && $m['status'] !== 'resolved'): ?>
            <?php if ($m['status'] === 'open'): ?><form method="post" class="inline" style="margin-bottom:6px"><?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="request_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-outline btn-sm">Start working</button></form><?php endif; ?>
            <details class="box"><summary>Resolve…</summary><form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="request_id" value="<?= (int) $m['id'] ?>">
                <div class="field"><label>What was done (required)</label><input name="notes" required placeholder="e.g. Replaced the shower valve today."></div>
                <button class="btn btn-primary btn-sm">Mark resolved</button></form></details>
        <?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="muted">No reports yet.</td></tr><?php endif; ?></tbody></table></div>
<p class="muted tiny">Open reports show first. Tenants are notified when their report is being worked on and when it is resolved.</p>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
