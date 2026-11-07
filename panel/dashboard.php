<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
$role = $me['role']; $uid = (int) $me['id'];
$title = 'Dashboard'; $nav = 'dashboard';
$today = date('Y-m-d');
$cards = []; $recentBookings = []; $extraBlocks = '';

if ($role === 'administrator') {
    $cards = [
        ['Total rooms', val('SELECT COUNT(*) FROM rooms WHERE deleted_at IS NULL')],
        ['Available', val("SELECT COUNT(*) FROM rooms WHERE room_status = 'available' AND deleted_at IS NULL")],
        ['Occupied', val("SELECT COUNT(*) FROM rooms WHERE room_status = 'occupied' AND deleted_at IS NULL")],
        ['Pending approvals', val("SELECT COUNT(*) FROM rooms WHERE approval_status = 'pending' AND deleted_at IS NULL")],
        ['Pending bookings', val("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")],
        ['Landlords', val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'landlord'")],
        ['Tenants', val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'tenant'")],
        ['Income recorded', money(val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'"))],
    ];
    $recentBookings = rows('SELECT b.*, r.room_number, u.first_name, u.last_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id ORDER BY b.id DESC LIMIT 6');
} elseif ($role === 'landlord') {
    $c = fn(string $w) => val("SELECT COUNT(*) FROM rooms WHERE created_by = ? AND deleted_at IS NULL $w", [$uid]);
    $cards = [
        ['My rooms', $c('')], ['Approved', $c("AND approval_status = 'approved'")], ['Waiting for approval', $c("AND approval_status = 'pending'")],
        ['Rejected', $c("AND approval_status = 'rejected'")], ['Available', $c("AND room_status = 'available'")], ['Occupied', $c("AND room_status = 'occupied'")],
        ['Pending requests', val("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE r.created_by = ? AND b.status = 'pending'", [$uid])],
        ['Income recorded', money(val("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN rooms r ON r.id = b.room_id WHERE r.created_by = ? AND p.status = 'paid'", [$uid]))],
    ];
    $recentBookings = rows('SELECT b.*, r.room_number, u.first_name, u.last_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id WHERE r.created_by = ? ORDER BY b.id DESC LIMIT 6', [$uid]);
} else {
    $cur = row("SELECT b.*, r.room_number, r.title FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.tenant_id = ? AND b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ? LIMIT 1", [$uid, $today, $today]);
    $cards = [
        ['My bookings', val('SELECT COUNT(*) FROM bookings WHERE tenant_id = ?', [$uid])],
        ['Pending requests', val("SELECT COUNT(*) FROM bookings WHERE tenant_id = ? AND status = 'pending'", [$uid])],
        ['Confirmed', val("SELECT COUNT(*) FROM bookings WHERE tenant_id = ? AND status = 'confirmed'", [$uid])],
        ['Total paid', money(val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'paid'", [$uid]))],
    ];
    $recentBookings = rows('SELECT b.*, r.room_number, r.title FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.tenant_id = ? ORDER BY b.id DESC LIMIT 6', [$uid]);
}
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Dashboard</h1><span class="muted">Welcome back, <?= e($me['first_name']) ?> 👋</span></div>

<?php if ($role === 'tenant'): ?>
    <?php if ($cur): ?><div class="alert alert-success">You are currently staying in <strong><?= e($cur['room_number']) ?> · <?= e($cur['title']) ?></strong> until <?= nice_date($cur['end_date']) ?>.</div>
    <?php else: ?><div class="alert alert-info">You have no active stay. <a href="../rooms.php" style="text-decoration:underline">Find a room</a> and send a booking request.</div><?php endif; ?>
<?php endif; ?>

<div class="stat-cards"><?php foreach ($cards as $c): ?><div class="stat-card"><div class="num"><?= e($c[1]) ?></div><div class="label"><?= e($c[0]) ?></div></div><?php endforeach; ?></div>

<?php if ($role === 'administrator'): ?>
    <?php $pend = rows("SELECT r.id, r.room_number, r.title, u.first_name, u.last_name FROM rooms r JOIN users u ON u.id = r.created_by WHERE r.approval_status = 'pending' AND r.deleted_at IS NULL ORDER BY r.id DESC LIMIT 5");
          $logs = rows('SELECT a.*, u.first_name, u.last_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 6'); ?>
    <div class="table-wrap"><div class="table-head"><strong>Rooms waiting for approval</strong><a class="btn btn-outline btn-sm" href="approvals.php">Open approvals</a></div>
        <table><thead><tr><th>Room</th><th>Title</th><th>Landlord</th></tr></thead><tbody>
        <?php foreach ($pend as $p): ?><tr><td><?= e($p['room_number']) ?></td><td><?= e($p['title']) ?></td><td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td></tr><?php endforeach; ?>
        <?php if (!$pend): ?><tr><td colspan="3" class="muted">Nothing waiting. 🎉</td></tr><?php endif; ?></tbody></table></div>
<?php endif; ?>

<div class="table-wrap"><div class="table-head"><strong><?= $role === 'tenant' ? 'My recent bookings' : 'Recent booking requests' ?></strong><a class="btn btn-outline btn-sm" href="bookings.php">View all</a></div>
    <table><thead><tr><th>Reference</th><th>Room</th><?php if ($role !== 'tenant'): ?><th>Tenant</th><?php endif; ?><th>Dates</th><th>Total</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recentBookings as $b): ?><tr>
        <td><a href="booking_view.php?id=<?= (int) $b['id'] ?>" style="color:var(--purple-300)"><?= e($b['booking_reference']) ?></a></td><td><?= e($b['room_number']) ?></td>
        <?php if ($role !== 'tenant'): ?><td><?= e($b['first_name'] . ' ' . $b['last_name']) ?></td><?php endif; ?>
        <td><?= nice_date($b['start_date']) ?> – <?= nice_date($b['end_date']) ?></td><td><?= money($b['total_amount']) ?></td><td><?= badge($b['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$recentBookings): ?><tr><td colspan="6" class="muted">No bookings yet.</td></tr><?php endif; ?></tbody></table></div>

<?php if ($role === 'administrator'): ?>
<div class="table-wrap"><div class="table-head"><strong>Recent activity</strong><a class="btn btn-outline btn-sm" href="logs.php">All logs</a></div>
    <table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?><tr><td class="muted"><?= e(substr($l['created_at'], 0, 16)) ?></td><td><?= e(trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'System') ?></td><td><?= e($l['action']) ?></td><td><?= e($l['description']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
