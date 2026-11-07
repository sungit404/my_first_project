<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['room_id'] ?? 0); $action = $_POST['action'] ?? '';
    $room = row("SELECT * FROM rooms WHERE id = ? AND approval_status = 'pending' AND deleted_at IS NULL", [$id]);
    if (!$room) { flash('error', 'That room is no longer waiting for approval.'); redirect('approvals.php'); }
    $remarks = trim($_POST['remarks'] ?? '');
    if ($action === 'approve') {
        q("UPDATE rooms SET approval_status = 'approved', is_published = 1, approved_by = ?, approved_at = ?, rejection_reason = NULL, updated_at = ? WHERE id = ?", [$me['id'], now(), now(), $id]);
        q('INSERT INTO room_approval_logs (room_id, submitted_by, reviewed_by, previous_status, new_status, remarks, reviewed_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
          [$id, $room['created_by'], $me['id'], 'pending', 'approved', $remarks ?: 'Approved', now(), now(), now()]);
        notify((int) $room['created_by'], 'room.approved', 'Room approved', "Your room {$room['room_number']} is now live on the public site.", 'rooms.php');
        log_activity('room.approved', 'Room', $id, "Approved room {$room['room_number']}");
        flash('success', "Room {$room['room_number']} approved and published.");
    } elseif ($action === 'reject') {
        if ($remarks === '') { flash('error', 'A reason is required when rejecting a room.'); redirect('approvals.php'); }
        q("UPDATE rooms SET approval_status = 'rejected', is_published = 0, approved_by = NULL, approved_at = NULL, rejection_reason = ?, updated_at = ? WHERE id = ?", [$remarks, now(), $id]);
        q('INSERT INTO room_approval_logs (room_id, submitted_by, reviewed_by, previous_status, new_status, remarks, reviewed_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
          [$id, $room['created_by'], $me['id'], 'pending', 'rejected', $remarks, now(), now(), now()]);
        notify((int) $room['created_by'], 'room.rejected', 'Room rejected', "Room {$room['room_number']} was rejected: $remarks", 'rooms.php');
        log_activity('room.rejected', 'Room', $id, "Rejected room {$room['room_number']}: $remarks");
        flash('success', "Room {$room['room_number']} was rejected and the landlord was notified.");
    }
    sync_rooms(); redirect('approvals.php');
}
$pending = rows("SELECT r.*, rt.name AS type_name, u.first_name, u.last_name,
    (SELECT image_path FROM room_images i WHERE i.room_id = r.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image,
    (SELECT COUNT(*) FROM room_images i WHERE i.room_id = r.id) AS img_count
    FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id JOIN users u ON u.id = r.created_by WHERE r.approval_status = 'pending' AND r.deleted_at IS NULL ORDER BY r.updated_at");
$history = rows('SELECT l.*, r.room_number, s.first_name AS sf, s.last_name AS sl, v.first_name AS vf, v.last_name AS vl FROM room_approval_logs l JOIN rooms r ON r.id = l.room_id JOIN users s ON s.id = l.submitted_by LEFT JOIN users v ON v.id = l.reviewed_by ORDER BY l.id DESC LIMIT 15');
$title = 'Room Approvals'; $nav = 'approvals';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Room Approvals</h1><span class="muted"><?= count($pending) ?> waiting</span></div>
<?php if (!$pending): ?><div class="panel empty-state">No rooms are waiting for approval. 🎉</div><?php endif; ?>
<?php foreach ($pending as $r): ?>
<div class="panel" style="margin-bottom:16px;display:grid;grid-template-columns:200px 1fr;gap:20px">
    <img src="<?= img_url($r['image']) ?>" alt="" style="width:100%;border-radius:10px;aspect-ratio:16/10;object-fit:cover">
    <div>
        <h3 style="margin-bottom:4px"><?= e($r['room_number']) ?> · <?= e($r['title']) ?></h3>
        <p class="muted tiny" style="margin:0 0 8px">By <?= e($r['first_name'] . ' ' . $r['last_name']) ?> · <?= e($r['type_name']) ?> · <?= e(ucfirst($r['occupancy_type'])) ?> · <?= (int) $r['capacity'] ?> pax · <?= money($r['monthly_rate']) ?>/mo · deposit <?= money($r['security_deposit']) ?> · <?= (int) $r['img_count'] ?> photo(s)</p>
        <p style="margin:0 0 12px"><?= e($r['description']) ?></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
            <a class="btn btn-ghost btn-sm" href="../room.php?id=<?= (int) $r['id'] ?>&amp;from=approvals">Preview listing</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-primary btn-sm">Approve &amp; publish</button></form>
            <details class="box" style="margin:0;padding:8px 14px"><summary>Reject…</summary>
                <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="room_id" value="<?= (int) $r['id'] ?>">
                    <div class="field"><label>Reason (required, shown to the landlord)</label><textarea name="remarks" required></textarea></div><button class="btn btn-danger btn-sm">Reject room</button></form></details>
        </div>
    </div>
</div>
<?php endforeach; ?>
<div class="table-wrap"><div class="table-head"><strong>Approval history</strong></div><table><thead><tr><th>When</th><th>Room</th><th>Change</th><th>Landlord</th><th>Reviewed by</th><th>Remarks</th></tr></thead><tbody>
<?php foreach ($history as $h): ?><tr><td class="muted"><?= e(substr($h['created_at'], 0, 16)) ?></td><td><?= e($h['room_number']) ?></td><td><?= badge($h['previous_status']) ?> → <?= badge($h['new_status']) ?></td>
    <td><?= e($h['sf'] . ' ' . $h['sl']) ?></td><td><?= e(trim(($h['vf'] ?? '') . ' ' . ($h['vl'] ?? '')) ?: '-') ?></td><td><?= e($h['remarks']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
