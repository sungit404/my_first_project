<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_actions.php';
$me = require_login(); $role = $me['role']; $uid = (int) $me['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = load_booking((int) ($_POST['booking_id'] ?? 0));
    if (!$b) { flash('error', 'Booking not found.'); }
    else { [$ok, $msg] = booking_action($b, $_POST['action'] ?? '', $_POST['notes'] ?? ''); flash($ok ? 'success' : 'error', $msg); sync_rooms(); }
    redirect('bookings.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
}
$f = ['status' => $_GET['status'] ?? '', 'q' => trim($_GET['q'] ?? '')];
$where = ['1=1']; $p = [];
if ($role === 'tenant') { $where[] = 'b.tenant_id = ?'; $p[] = $uid; } elseif ($role === 'landlord') { $where[] = 'r.created_by = ?'; $p[] = $uid; }
if (in_array($f['status'], ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'], true)) { $where[] = 'b.status = ?'; $p[] = $f['status']; }
if ($f['q'] !== '') { $where[] = '(b.booking_reference LIKE ? OR r.room_number LIKE ?)'; array_push($p, "%{$f['q']}%", "%{$f['q']}%"); }
$W = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE $W", $p);
$pg = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT b.*, r.room_number, r.title, r.created_by AS landlord_id, u.first_name, u.last_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id WHERE $W ORDER BY (b.status = 'pending') DESC, b.id DESC LIMIT 10 OFFSET {$pg['offset']}", $p);
$today = date('Y-m-d');
$title = $role === 'tenant' ? 'My Bookings' : 'Booking Requests'; $nav = 'bookings';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1><?= e($title) ?></h1><?php if ($role === 'tenant'): ?><a class="btn btn-primary" href="../rooms.php">+ Find a room</a><?php endif; ?></div>
<form method="get" class="toolbar">
    <div class="field"><label>Search</label><input name="q" value="<?= e($f['q']) ?>" placeholder="Reference or room #"></div>
    <div class="field"><label>Status</label><select name="status"><option value="">All</option><?php foreach (['pending', 'confirmed', 'completed', 'rejected', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-outline btn-sm">Filter</button><a class="btn btn-ghost btn-sm" href="bookings.php">Reset</a>
</form>
<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Room</th><?php if ($role !== 'tenant'): ?><th>Tenant</th><?php endif; ?><th>Dates</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($list as $b):
    $mgr = $role === 'administrator' || ($role === 'landlord' && (int) $b['landlord_id'] === $uid); ?>
    <tr><td><a href="booking_view.php?id=<?= (int) $b['id'] ?>" style="color:var(--purple-300)"><?= e($b['booking_reference']) ?></a></td>
        <td><?= e($b['room_number']) ?><br><span class="muted tiny"><?= e($b['title']) ?></span></td>
        <?php if ($role !== 'tenant'): ?><td><?= e($b['first_name'] . ' ' . $b['last_name']) ?></td><?php endif; ?>
        <td><?= nice_date($b['start_date']) ?><br><span class="muted tiny">to <?= nice_date($b['end_date']) ?></span></td><td><?= money($b['total_amount']) ?></td><td><?= badge($b['status']) ?></td>
        <td class="row-actions" style="flex-wrap:wrap">
            <a class="btn btn-ghost btn-sm" href="booking_view.php?id=<?= (int) $b['id'] ?>">Details</a>
            <?php if ($mgr && $b['status'] === 'pending'): ?>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-primary btn-sm">Confirm</button></form>
            <?php endif; ?>
            <?php if ($role === 'tenant' && ($b['status'] === 'pending' || ($b['status'] === 'confirmed' && $b['start_date'] > $today))): ?>
                <form method="post" class="inline" onsubmit="return confirm('Cancel this booking?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-danger btn-sm">Cancel</button></form>
            <?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="7" class="muted">No bookings found.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg) ?>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
