<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator', 'landlord');
$isAdmin = $me['role'] === 'administrator'; $uid = (int) $me['id'];
$today = date('Y-m-d');

function own_room(int $id): ?array {
    global $me, $isAdmin;
    $r = row('SELECT * FROM rooms WHERE id = ? AND deleted_at IS NULL', [$id]);
    return ($r && ($isAdmin || (int) $r['created_by'] === (int) $me['id'])) ? $r : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['room_id'] ?? 0); $room = own_room($id); $action = $_POST['action'] ?? '';
    if (!$room) { flash('error', 'Room not found.'); redirect('rooms.php'); }

    if ($action === 'submit') {
        [$ok, $msg] = submit_room_for_approval($room);
        flash($ok ? 'success' : 'error', $msg);
    } elseif ($action === 'delete') {
        if (val("SELECT 1 FROM bookings WHERE room_id = ? AND status IN ('pending','confirmed') AND end_date >= ?", [$id, $today])) {
            flash('error', 'This room has pending or upcoming confirmed bookings, so it cannot be deleted.');
        } else {
            q('UPDATE rooms SET deleted_at = ?, is_published = 0, updated_at = ? WHERE id = ?', [now(), now(), $id]);
            log_activity('room.deleted', 'Room', $id, "Deleted room {$room['room_number']}");
            flash('success', "Room {$room['room_number']} was deleted (history is kept).");
        }
    } elseif ($action === 'toggle_publish' && $isAdmin) {
        if ($room['approval_status'] !== 'approved') { flash('error', 'Only approved rooms can be published.'); }
        else {
            $new = $room['is_published'] ? 0 : 1;
            q('UPDATE rooms SET is_published = ?, updated_at = ? WHERE id = ?', [$new, now(), $id]);
            log_activity($new ? 'room.published' : 'room.unpublished', 'Room', $id, ($new ? 'Published' : 'Unpublished') . " room {$room['room_number']}");
            flash('success', 'Room ' . ($new ? 'published' : 'hidden from public search') . '.');
        }
    }
    sync_rooms();
    redirect('rooms.php?' . http_build_query(array_diff_key($_GET, ['page' => 1])));
}

// ---- list + filters ----
$f = ['q' => trim($_GET['q'] ?? ''), 'approval' => $_GET['approval'] ?? '', 'status' => $_GET['status'] ?? '', 'type' => (int) ($_GET['type'] ?? 0), 'owner' => (int) ($_GET['owner'] ?? 0)];
$where = ['r.deleted_at IS NULL']; $p = [];
if (!$isAdmin) { $where[] = 'r.created_by = ?'; $p[] = $uid; }
elseif ($f['owner']) { $where[] = 'r.created_by = ?'; $p[] = $f['owner']; }
if ($f['q'] !== '') { $where[] = '(r.room_number LIKE ? OR r.title LIKE ?)'; array_push($p, "%{$f['q']}%", "%{$f['q']}%"); }
if (in_array($f['approval'], ['draft', 'pending', 'approved', 'rejected'], true)) { $where[] = 'r.approval_status = ?'; $p[] = $f['approval']; }
if (in_array($f['status'], ['available', 'occupied', 'unavailable'], true)) { $where[] = 'r.room_status = ?'; $p[] = $f['status']; }
if ($f['type']) { $where[] = 'r.room_type_id = ?'; $p[] = $f['type']; }
$W = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM rooms r WHERE $W", $p);
$pg = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT r.*, rt.name AS type_name, u.first_name AS lf, u.last_name AS ll, u.address AS lf_address,
    (SELECT image_path FROM room_images i WHERE i.room_id = r.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image,
    (SELECT t.first_name || ' ' || t.last_name FROM bookings b JOIN users t ON t.id = b.tenant_id WHERE b.room_id = r.id AND b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ? LIMIT 1) AS tenant_name
    FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id JOIN users u ON u.id = r.created_by WHERE $W ORDER BY r.id DESC LIMIT 10 OFFSET {$pg['offset']}", array_merge([$today, $today], $p));
$types = rows('SELECT * FROM room_types ORDER BY name');
$owners = $isAdmin ? rows("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'landlord' ORDER BY u.first_name") : [];
$title = $isAdmin ? 'All Rooms' : 'My Rooms'; $nav = 'rooms';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1><?= e($title) ?></h1><?php if (!$isAdmin): ?><a href="room_form.php" class="btn btn-primary">+ Add Room</a><?php endif; ?></div>
<form method="get" class="toolbar">
    <div class="field"><label>Search</label><input name="q" value="<?= e($f['q']) ?>" placeholder="Room # or title"></div>
    <div class="field"><label>Approval</label><select name="approval"><option value="">All</option><?php foreach (['draft', 'pending', 'approved', 'rejected'] as $s): ?><option value="<?= $s ?>" <?= $f['approval'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Room status</label><select name="status"><option value="">All</option><?php foreach (['available', 'occupied', 'unavailable'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Type</label><select name="type"><option value="">All</option><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $f['type'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($isAdmin): ?><div class="field"><label>Landlord</label><select name="owner"><option value="">All</option><?php foreach ($owners as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $f['owner'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['first_name'] . ' ' . $o['last_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <button class="btn btn-outline btn-sm">Filter</button><a href="rooms.php" class="btn btn-ghost btn-sm">Reset</a>
</form>
<div class="table-wrap"><table>
    <thead><tr><th>Photo</th><th>Room</th><th>Type</th><?php if ($isAdmin): ?><th>Landlord</th><?php endif; ?><th>Rate</th><th>Approval</th><th>Room status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($list as $r): ?><tr>
        <td><img class="thumb" src="<?= img_url($r['image']) ?>" alt=""></td>
        <td><strong><?= e($r['room_number']) ?></strong><br><span class="muted tiny"><?= e($r['title']) ?></span></td>
        <td><?= e($r['type_name']) ?><br><span class="muted tiny"><?= e(ucfirst($r['occupancy_type'])) ?> · <?= (int) $r['capacity'] ?> pax</span></td>
        <?php if ($isAdmin): ?><td><?= e($r['lf'] . ' ' . $r['ll']) ?><?= $r['lf_address'] ? '<br><span class="muted tiny">' . e($r['lf_address']) . '</span>' : '' ?></td><?php endif; ?>
        <td><?= money($r['monthly_rate']) ?></td>
        <td><?= badge($r['approval_status']) ?><?php if ($r['approval_status'] === 'approved' && !$r['is_published']): ?><br><span class="tiny muted">hidden</span><?php endif; ?>
            <?php if ($r['approval_status'] === 'rejected' && $r['rejection_reason']): ?><br><span class="tiny" style="color:var(--danger)"><?= e($r['rejection_reason']) ?></span><?php endif; ?></td>
        <td><?= badge($r['room_status']) ?><?php if ($r['tenant_name']): ?><br><span class="tiny muted">Tenant: <?= e($r['tenant_name']) ?></span><?php endif; ?></td>
        <td class="row-actions" style="flex-wrap:wrap">
            <?php if (!$isAdmin): ?><a class="btn btn-ghost btn-sm" href="room_form.php?id=<?= (int) $r['id'] ?>">Edit / Photos</a><?php endif; ?>
            <a class="btn btn-ghost btn-sm" href="../room.php?id=<?= (int) $r['id'] ?>">View</a>
            <?php if (in_array($r['approval_status'], ['draft', 'rejected'], true)): ?>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="submit"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-outline btn-sm">Submit</button></form><?php endif; ?>
            <?php if ($isAdmin && $r['approval_status'] === 'approved'): ?>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_publish"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-ghost btn-sm"><?= $r['is_published'] ? 'Hide' : 'Publish' ?></button></form><?php endif; ?>
            <form method="post" class="inline" onsubmit="return confirm('Delete room <?= e($r['room_number']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form>
        </td></tr><?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="8" class="muted">No rooms found.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg) ?>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
