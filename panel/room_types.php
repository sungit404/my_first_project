<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
    $cap = (int) ($_POST['default_capacity'] ?? 1); $rate = (float) ($_POST['default_monthly_rate'] ?? 0);
    if ($action === 'delete') {
        if (val('SELECT 1 FROM rooms WHERE room_type_id = ?', [$id])) { flash('error', 'This type is used by rooms, so it cannot be deleted.'); }
        else { q('DELETE FROM room_types WHERE id = ?', [$id]); log_activity('room_type.deleted', 'RoomType', $id, 'Deleted a room type'); flash('success', 'Room type deleted.'); }
    } elseif ($name === '' || $cap < 1 || $rate <= 0) { flash('error', 'Name, a capacity of at least 1 and a rate above zero are required.'); }
    elseif (val('SELECT 1 FROM room_types WHERE name = ? AND id != ?', [$name, $id])) { flash('error', 'A room type with that name already exists.'); }
    elseif ($action === 'add') {
        q('INSERT INTO room_types (name, description, default_capacity, default_monthly_rate, created_at, updated_at) VALUES (?,?,?,?,?,?)', [$name, $desc, $cap, $rate, now(), now()]);
        log_activity('room_type.created', 'RoomType', insert_id(), "Created room type $name"); flash('success', 'Room type added.');
    } elseif ($action === 'edit') {
        q('UPDATE room_types SET name=?, description=?, default_capacity=?, default_monthly_rate=?, updated_at=? WHERE id=?', [$name, $desc, $cap, $rate, now(), $id]);
        log_activity('room_type.updated', 'RoomType', $id, "Updated room type $name"); flash('success', 'Room type updated.');
    }
    redirect('room_types.php');
}
$list = rows('SELECT t.*, (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = t.id AND r.deleted_at IS NULL) AS n FROM room_types t ORDER BY t.name');
$title = 'Room Types'; $nav = 'room_types';
require __DIR__ . '/../includes/panel_head.php';
$fields = '<div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Description</label><textarea name="description"></textarea></div>
<div class="two"><div class="field"><label>Default capacity</label><input type="number" name="default_capacity" min="1" value="1" required></div><div class="field"><label>Suggested monthly rate</label><input type="number" name="default_monthly_rate" min="1" step="0.01" required></div></div>';
?>
<div class="admin-topbar"><h1>Room Types</h1><button class="btn btn-primary" onclick="openModal('addT')">+ Add type</button></div>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Description</th><th>Default capacity</th><th>Suggested rate</th><th>Rooms</th><th></th></tr></thead><tbody>
<?php foreach ($list as $t): ?><tr><td><strong><?= e($t['name']) ?></strong></td><td class="muted"><?= e($t['description']) ?></td><td><?= (int) $t['default_capacity'] ?></td><td><?= money($t['default_monthly_rate']) ?></td><td><?= (int) $t['n'] ?></td>
    <td class="row-actions"><button class="btn btn-ghost btn-sm" onclick='editT(<?= e(json_encode($t)) ?>)'>Edit</button>
        <form method="post" class="inline" onsubmit="return confirm('Delete this room type?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<div class="modal-overlay" id="addT"><div class="modal-box"><button class="modal-close" onclick="closeModal('addT')">✕</button><h3>Add room type</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><?= $fields ?><button class="btn btn-primary btn-block">Save</button></form></div></div>
<div class="modal-overlay" id="editT"><div class="modal-box"><button class="modal-close" onclick="closeModal('editT')">✕</button><h3>Edit room type</h3><form method="post" id="editTF"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id"><?= $fields ?><button class="btn btn-primary btn-block">Update</button></form></div></div>
<script>function editT(t){var f=document.getElementById('editTF');f.id.value=t.id;f.name.value=t.name;f.description.value=t.description||'';f.default_capacity.value=t.default_capacity;f.default_monthly_rate.value=t.default_monthly_rate;openModal('editT');}</script>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
