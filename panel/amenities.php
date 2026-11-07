<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? ''); $cat = trim($_POST['category'] ?? '') ?: null;
    if ($action === 'delete') {
        $n = (int) val('SELECT COUNT(*) FROM amenity_room WHERE amenity_id = ?', [$id]);
        if ($n) { flash('error', "This amenity is attached to $n room(s). Remove it from those rooms first."); }
        else { q('DELETE FROM amenities WHERE id = ?', [$id]); log_activity('amenity.deleted', 'Amenity', $id, 'Deleted an amenity'); flash('success', 'Amenity deleted.'); }
    } elseif ($name === '') { flash('error', 'Amenity name is required.'); }
    elseif (val('SELECT 1 FROM amenities WHERE name = ? AND id != ?', [$name, $id])) { flash('error', 'That amenity already exists.'); }
    elseif ($action === 'add') { q('INSERT INTO amenities (name, description, category, created_at, updated_at) VALUES (?,?,?,?,?)', [$name, $desc, $cat, now(), now()]); log_activity('amenity.created', 'Amenity', insert_id(), "Created amenity $name"); flash('success', 'Amenity added.'); }
    elseif ($action === 'edit') { q('UPDATE amenities SET name=?, description=?, category=?, updated_at=? WHERE id=?', [$name, $desc, $cat, now(), $id]); log_activity('amenity.updated', 'Amenity', $id, "Updated amenity $name"); flash('success', 'Amenity updated.'); }
    redirect('amenities.php');
}
$list = rows('SELECT a.*, (SELECT COUNT(*) FROM amenity_room x WHERE x.amenity_id = a.id) AS n FROM amenities a ORDER BY a.category, a.name');
$title = 'Amenities'; $nav = 'amenities';
require __DIR__ . '/../includes/panel_head.php';
$fields = '<div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Category (utility, comfort, security, study…)</label><input name="category"></div><div class="field"><label>Description</label><textarea name="description"></textarea></div>';
?>
<div class="admin-topbar"><h1>Amenities</h1><button class="btn btn-primary" onclick="openModal('addA')">+ Add amenity</button></div>
<p class="muted">This is the master list. Each landlord attaches amenities to a room and sets an optional extra monthly price there.</p>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Category</th><th>Description</th><th>Used in rooms</th><th></th></tr></thead><tbody>
<?php foreach ($list as $a): ?><tr><td><strong><?= e($a['name']) ?></strong></td><td><span class="chip"><?= e($a['category'] ?: '-') ?></span></td><td class="muted"><?= e($a['description']) ?></td><td><?= (int) $a['n'] ?></td>
    <td class="row-actions"><button class="btn btn-ghost btn-sm" onclick='editA(<?= e(json_encode($a)) ?>)'>Edit</button>
        <form method="post" class="inline" onsubmit="return confirm('Delete this amenity?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<div class="modal-overlay" id="addA"><div class="modal-box"><button class="modal-close" onclick="closeModal('addA')">✕</button><h3>Add amenity</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><?= $fields ?><button class="btn btn-primary btn-block">Save</button></form></div></div>
<div class="modal-overlay" id="editA"><div class="modal-box"><button class="modal-close" onclick="closeModal('editA')">✕</button><h3>Edit amenity</h3><form method="post" id="editAF"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id"><?= $fields ?><button class="btn btn-primary btn-block">Update</button></form></div></div>
<script>function editA(a){var f=document.getElementById('editAF');f.id.value=a.id;f.name.value=a.name;f.category.value=a.category||'';f.description.value=a.description||'';openModal('editA');}</script>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
