<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator', 'landlord');
$isAdmin = $me['role'] === 'administrator';
$id = (int) ($_GET['id'] ?? $_POST['room_id'] ?? 0);
$room = null;
if ($id) {
    $room = row('SELECT * FROM rooms WHERE id = ? AND deleted_at IS NULL', [$id]);
    if (!$room || (!$isAdmin && (int) $room['created_by'] !== (int) $me['id'])) { flash('error', 'Room not found.'); redirect('rooms.php'); }
}
$types = rows('SELECT * FROM room_types ORDER BY name');
$allAm = rows('SELECT * FROM amenities ORDER BY category, name');
$owners = rows("SELECT u.id, u.first_name, u.last_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'landlord' AND u.status = 'active' ORDER BY u.first_name");
$errors = [];
$cur = [];  // amenity_id => price for this room
if ($room) { foreach (rows('SELECT amenity_id, price, note FROM amenity_room WHERE room_id = ?', [$id]) as $a) { $cur[(int) $a['amenity_id']] = ['price' => (float) $a['price'], 'note' => (string) ($a['note'] ?? '')]; } }

function add_images(int $roomId): int {
    $added = 0;
    $next = (int) val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM room_images WHERE room_id = ?', [$roomId]);
    $hasPrimary = (bool) val('SELECT 1 FROM room_images WHERE room_id = ? AND is_primary = 1', [$roomId]);
    $paths = [];
    foreach ((array) ($_POST['sample'] ?? []) as $s) { if (in_array($s, sample_images(), true)) { $paths[] = $s; } }
    if (!empty($_FILES['photos']['name'][0])) {
        foreach ($_FILES['photos']['name'] as $i => $n) {
            $saved = save_upload(['name' => $n, 'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i], 'size' => $_FILES['photos']['size'][$i]]);
            if ($saved) { $paths[] = $saved; }
        }
    }
    foreach ($paths as $path) {
        q('INSERT INTO room_images (room_id, image_path, alt_text, is_primary, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
          [$roomId, $path, 'Room photo', $hasPrimary ? 0 : 1, $next++, now(), now()]);
        $hasPrimary = true; $added++;
    }
    return $added;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($room && $action === 'add_images') {
        $n = add_images($id);
        log_activity('room.photos_added', 'Room', $id, "Added $n photo(s) to room {$room['room_number']}");
        flash($n ? 'success' : 'error', $n ? "$n photo(s) added." : 'Pick a sample photo or choose a JPG, PNG or WEBP file (max 5 MB).');
        redirect("room_form.php?id=$id#photos");
    }
    if ($room && $action === 'set_primary') {
        q('UPDATE room_images SET is_primary = 0 WHERE room_id = ?', [$id]);
        q('UPDATE room_images SET is_primary = 1, updated_at = ? WHERE id = ? AND room_id = ?', [now(), (int) $_POST['image_id'], $id]);
        flash('success', 'Main photo updated.'); redirect("room_form.php?id=$id#photos");
    }
    if ($room && $action === 'delete_image') {
        $im = row('SELECT * FROM room_images WHERE id = ? AND room_id = ?', [(int) $_POST['image_id'], $id]);
        if ($im) {
            q('DELETE FROM room_images WHERE id = ?', [$im['id']]);
            if (str_starts_with($im['image_path'], 'uploads/') && !val('SELECT 1 FROM room_images WHERE image_path = ?', [$im['image_path']])) { @unlink(__DIR__ . '/../assets/images/' . $im['image_path']); }
            if (!val('SELECT 1 FROM room_images WHERE room_id = ? AND is_primary = 1', [$id])) { q('UPDATE room_images SET is_primary = 1 WHERE id = (SELECT id FROM room_images WHERE room_id = ? ORDER BY sort_order, id LIMIT 1)', [$id]); }
            flash('success', 'Photo removed.');
        }
        redirect("room_form.php?id=$id#photos");
    }

    // ---- save room details ----
    $d = ['room_type_id' => (int) ($_POST['room_type_id'] ?? 0), 'room_number' => trim($_POST['room_number'] ?? ''), 'floor_number' => ($_POST['floor_number'] ?? '') === '' ? null : (int) $_POST['floor_number'],
          'title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''), 'occupancy_type' => ($_POST['occupancy_type'] ?? '') === 'shared' ? 'shared' : 'private',
          'capacity' => (int) ($_POST['capacity'] ?? 0), 'monthly_rate' => (float) ($_POST['monthly_rate'] ?? 0), 'security_deposit' => (float) ($_POST['security_deposit'] ?? 0)];
    if (!val('SELECT 1 FROM room_types WHERE id = ?', [$d['room_type_id']])) { $errors[] = 'Choose a room type.'; }
    if ($d['room_number'] === '' || strlen($d['room_number']) > 20) { $errors[] = 'Room number is required (max 20 characters).'; }
    if ($d['title'] === '' || strlen($d['title']) > 150) { $errors[] = 'Listing title is required (max 150 characters).'; }
    if ($d['capacity'] < 1 || $d['capacity'] > 50) { $errors[] = 'Capacity must be at least 1.'; }
    if ($d['monthly_rate'] <= 0) { $errors[] = 'Monthly rate must be greater than zero.'; }
    if ($d['security_deposit'] < 0) { $errors[] = 'Deposit cannot be negative.'; }
    if (!$errors && val('SELECT 1 FROM rooms WHERE room_number = ? AND id != ?', [$d['room_number'], $id])) { $errors[] = 'That room number is already used (rooms are never reused, even deleted ones).'; }
    $owner = $isAdmin ? (int) ($_POST['created_by'] ?? 0) : (int) $me['id'];
    if (!$room && !val('SELECT 1 FROM users WHERE id = ?', [$owner])) { $errors[] = 'Choose the landlord who owns this room.'; }

    if (!$errors) {
        $pickedAm = [];
        foreach ((array) ($_POST['am'] ?? []) as $aid) { $aid = (int) $aid; $pickedAm[$aid] = ['price' => max(0, (float) ($_POST['am_price'][$aid] ?? 0)), 'note' => trim((string) ($_POST['am_note'][$aid] ?? ''))]; }
        // a brand-new amenity typed straight into the room form — created and attached on save
        if (trim((string) ($_POST['new_amenity_name'] ?? '')) !== '') {
            $nName = trim((string) $_POST['new_amenity_name']);
            $nCat = trim((string) ($_POST['new_amenity_category'] ?? '')) ?: 'utility';
            $nPrice = max(0, (float) ($_POST['new_amenity_price'] ?? 0));
            $ex = row('SELECT id FROM amenities WHERE LOWER(name) = LOWER(?)', [$nName]);
            if ($ex) { $newId = (int) $ex['id']; }
            else {
                q('INSERT INTO amenities (name, description, category, created_at, updated_at) VALUES (?,?,?,?,?)', [$nName, '', $nCat, now(), now()]);
                $newId = insert_id();
                log_activity('amenity.created', 'Amenity', $newId, 'Added amenity "' . $nName . '" from a room form');
            }
            $pickedAm[$newId] = ['price' => $nPrice, 'note' => ''];
        }
        if ($room) {
            q('UPDATE rooms SET room_type_id=?, room_number=?, floor_number=?, title=?, description=?, occupancy_type=?, capacity=?, monthly_rate=?, security_deposit=?, updated_at=? WHERE id=?',
              [$d['room_type_id'], $d['room_number'], $d['floor_number'], $d['title'], $d['description'], $d['occupancy_type'], $d['capacity'], $d['monthly_rate'], $d['security_deposit'], now(), $id]);
            $msg = 'Room saved.';
            $status = $room['approval_status'];
            if (!$isAdmin && $status === 'approved') {   // landlords re-submit after editing an approved listing
                $rateNote = (float) $room['monthly_rate'] !== (float) $d['monthly_rate'] ? ' — monthly rate ' . money($room['monthly_rate']) . ' → ' . money($d['monthly_rate']) : '';
                q("UPDATE rooms SET approval_status = 'pending', is_published = 0, approved_by = NULL, approved_at = NULL WHERE id = ?", [$id]);
                q('INSERT INTO room_approval_logs (room_id, submitted_by, previous_status, new_status, remarks, created_at, updated_at) VALUES (?,?,?,?,?,?,?)', [$id, $me['id'], 'approved', 'pending', 'Edited after approval' . $rateNote . ' - re-submitted', now(), now()]);
                notify_admins('room.submitted', 'Edited room needs re-approval', "Room {$d['room_number']} was edited by " . full_name($me) . "$rateNote.", 'approvals.php');
                $msg = 'Room saved. Because it was already approved, it is hidden until the administrator approves the changes.';
            }
        } else {
            q("INSERT INTO rooms (room_type_id, created_by, room_number, floor_number, title, description, occupancy_type, capacity, monthly_rate, security_deposit, approval_status, room_status, is_published, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'draft','unavailable',0,?,?)",
              [$d['room_type_id'], $owner, $d['room_number'], $d['floor_number'], $d['title'], $d['description'], $d['occupancy_type'], $d['capacity'], $d['monthly_rate'], $d['security_deposit'], now(), now()]);
            $id = insert_id(); $msg = 'Room created as a draft. Add photos below, then submit it for approval.';
        }
        q('DELETE FROM amenity_room WHERE room_id = ?', [$id]);
        foreach ($pickedAm as $aid => $v) {
            if (val('SELECT 1 FROM amenities WHERE id = ?', [$aid])) { q('INSERT INTO amenity_room (room_id, amenity_id, price, note, created_at, updated_at) VALUES (?,?,?,?,?,?)', [$id, $aid, $v['price'], $v['note'], now(), now()]); }
        }
        log_activity($room ? 'room.updated' : 'room.created', 'Room', $id, ($room ? 'Updated' : 'Created') . " room {$d['room_number']}");
        if (($_POST['submit_mode'] ?? '') === 'submit' && $room) {
            $fresh = row('SELECT * FROM rooms WHERE id = ?', [$id]);
            [$ok, $m2] = submit_room_for_approval($fresh);
            $msg = $ok ? "Saved. $m2" : "Saved, but not submitted: $m2";
        }
        sync_rooms(); flash('success', $msg); redirect("room_form.php?id=$id");
    }
    $cur = []; foreach ((array) ($_POST['am'] ?? []) as $aid) { $cur[(int) $aid] = ['price' => (float) ($_POST['am_price'][(int) $aid] ?? 0), 'note' => trim((string) ($_POST['am_note'][(int) $aid] ?? ''))]; }
    $room = array_merge($room ?? ['id' => 0, 'approval_status' => 'draft', 'rejection_reason' => null], $d);
}
$images = $id ? rows('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, sort_order, id', [$id]) : [];
$v = fn(string $k, $def = '') => e($room[$k] ?? $def);
$title = $id ? 'Edit Room ' . ($room['room_number'] ?? '') : 'Add Room'; $nav = 'rooms';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1><?= e($title) ?></h1><a href="rooms.php" class="btn btn-ghost btn-sm">← Back to rooms</a></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($id): ?><div class="alert alert-info">Approval status: <?= badge($room['approval_status']) ?>
    <?php if ($room['approval_status'] === 'rejected' && $room['rejection_reason']): ?> — Reason: <?= e($room['rejection_reason']) ?><?php endif; ?>
    <?php if (!$isAdmin && $room['approval_status'] === 'approved'): ?><br>Editing the details of an approved room sends it back to the administrator for re-approval.<?php endif; ?></div><?php endif; ?>
<?php
if ($id) {
    $ownerInfo = row('SELECT u.first_name, u.last_name, u.address FROM users u WHERE u.id = ?', [(int) $room['created_by']]);
    if ($ownerInfo && ($ownerInfo['address'] ?? '')): ?>
<div class="panel" style="margin-bottom:26px;padding:18px 24px">
    <strong style="color:var(--violet)">Landlord address</strong>
    <p style="margin:6px 0 0" class="muted"><?= e($ownerInfo['first_name'] . ' ' . $ownerInfo['last_name']) ?> — <?= e($ownerInfo['address']) ?></p>
    <p class="tiny muted" style="margin:6px 0 0">This address is shown on every listing this landlord creates. Change it on <a href="profile.php" style="color:var(--violet)">My Profile</a>.</p>
</div>
<?php endif; } ?>
<?php if ($id): $occ = room_occupants_today($id); ?>
<div class="panel" style="margin-bottom:26px;padding:18px 24px"><?php if ($room['occupancy_type'] === 'shared'):
            $used = beds_taken_today($id); ?><strong style="color:var(--violet)">Occupancy</strong>
            <p class="muted tiny" style="margin:6px 0 0"><?= $used ? "$used of " . (int) $room['capacity'] . " bed spaces taken — " . ((int) $room['capacity'] - $used) . " free for another tenant." : "All " . (int) $room['capacity'] . " bed spaces free." ?></p>
<?php endif; if ($occ) { echo '<div style="margin-top:10px">' . occupancy_sentence($occ, (int) $room['capacity'], true, $room['occupancy_type']) . '</div>'; }
      elseif ($room['occupancy_type'] !== 'shared') { echo '<p class="muted" style="margin:0">No tenants right now — the room is free.</p>'; } ?></div>
<?php endif; ?>

<form method="post" class="panel" style="margin-bottom:26px"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="room_id" value="<?= $id ?>">
    <div class="form-grid">
        <div class="field"><label>Room type</label><select name="room_type_id" id="roomType" required><option value="">Choose…</option>
            <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>" data-cap="<?= (int) $t['default_capacity'] ?>" data-rate="<?= e($t['default_monthly_rate']) ?>" <?= (int) ($room['room_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Room number</label><input name="room_number" value="<?= $v('room_number') ?>" maxlength="20" placeholder="e.g. B-106" required></div>
        <div class="field full"><label>Listing title</label><input name="title" value="<?= $v('title') ?>" maxlength="150" required></div>
        <div class="field full"><label>Description</label><textarea name="description"><?= $v('description') ?></textarea></div>
        <div class="field"><label>Occupancy</label><select name="occupancy_type"><option value="private" <?= ($room['occupancy_type'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option><option value="shared" <?= ($room['occupancy_type'] ?? '') === 'shared' ? 'selected' : '' ?>>Shared</option></select></div>
        <div class="field"><label>Floor</label><input type="number" name="floor_number" value="<?= $v('floor_number') ?>"></div>
        <div class="field"><label>Capacity (max occupants)</label><input type="number" id="cap" name="capacity" min="1" value="<?= $v('capacity') ?>" required></div>
        <div class="field"><label>Monthly rate</label><input type="number" id="rate" name="monthly_rate" min="1" step="0.01" value="<?= $v('monthly_rate') ?>" required></div>
        <div class="field"><label>Security deposit</label><input type="number" name="security_deposit" min="0" step="0.01" value="<?= $v('security_deposit', '0') ?>"></div>
        <?php if ($isAdmin && !$id): ?><div class="field"><label>Landlord (owner)</label><select name="created_by"><?php foreach ($owners as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <h3 style="margin-top:14px">Amenities <span class="muted tiny">— tick what the room has. Price 0 means included; any amount is an extra monthly charge. Use the text box for a custom label like "Free" or "Free until Dec".</span></h3>
    <div class="amen-pick" style="margin-bottom:20px">
    <?php foreach ($allAm as $a): $on = array_key_exists((int) $a['id'], $cur); ?>
        <div class="row"><input type="checkbox" name="am[]" value="<?= (int) $a['id'] ?>" id="am<?= (int) $a['id'] ?>" <?= $on ? 'checked' : '' ?>>
            <label for="am<?= (int) $a['id'] ?>"><?= e($a['name']) ?> <span class="muted tiny"><?= e($a['category']) ?></span></label>
            <input type="number" name="am_price[<?= (int) $a['id'] ?>]" min="0" step="1" value="<?= $on ? e($cur[(int) $a['id']]['price']) : '0' ?>" title="Extra monthly price">
            <input type="text" name="am_note[<?= (int) $a['id'] ?>]" maxlength="100" value="<?= $on ? e($cur[(int) $a['id']]['note']) : '' ?>" placeholder="e.g. Free" title="Custom label shown when the price is 0" style="max-width:120px"></div>
    <?php endforeach; ?></div>
    <details class="box amen-add" style="margin:0 0 20px">
        <summary>＋ Add new amenity</summary>
        <p class="muted tiny" style="margin:8px 0 10px">Not in the list? Type it here — it will be created, and attached to this room as an extra, when you save.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field" style="flex:2;min-width:180px;margin:0"><label>Amenity name</label><input type="text" name="new_amenity_name" maxlength="60" placeholder="e.g. Water heater"></div>
            <div class="field" style="margin:0"><label>Category</label><select name="new_amenity_category"><option value="utility">utility</option><option value="comfort">comfort</option><option value="security">security</option><option value="study">study</option></select></div>
            <div class="field" style="margin:0"><label>Extra ₱ / month (0 = included)</label><input type="number" name="new_amenity_price" min="0" step="1" value="0"></div>
        </div>
    </details>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" name="submit_mode" value="save"><?= $id ? 'Save changes' : 'Create room (draft)' ?></button>
        <?php if ($id && in_array($room['approval_status'], ['draft', 'rejected'], true)): ?><button class="btn btn-outline" name="submit_mode" value="submit">Save &amp; submit for approval</button><?php endif; ?>
    </div>
</form>
<script>
(function(){ var t=document.getElementById('roomType'); if(!t) return;
  <?php if (!$id): ?>t.addEventListener('change',function(){var o=t.options[t.selectedIndex]; if(!o.dataset.cap) return; var c=document.getElementById('cap'),r=document.getElementById('rate'); if(!c.value) c.value=o.dataset.cap; if(!r.value) r.value=o.dataset.rate;});<?php endif; ?>
})();
</script>

<div class="panel" id="photos">
    <h3>Room photos</h3>
    <?php if (!$id): ?><p class="muted">Save the room first, then you can add photos here.</p>
    <?php else: ?>
        <?php if (!$images): ?><p class="muted">No photos yet. A room needs at least one photo before it can be submitted.</p><?php endif; ?>
        <div class="img-manage"><?php foreach ($images as $im): ?>
            <figure><img src="<?= img_url($im['image_path']) ?>" alt="">
                <figcaption><?php if ($im['is_primary']): ?><span class="pill status-approved">Main photo</span><?php else: ?>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="room_id" value="<?= $id ?>"><input type="hidden" name="image_id" value="<?= (int) $im['id'] ?>"><button class="btn btn-ghost btn-sm">Make main</button></form><?php endif; ?>
                    <form method="post" class="inline" onsubmit="return confirm('Remove this photo?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_image"><input type="hidden" name="room_id" value="<?= $id ?>"><input type="hidden" name="image_id" value="<?= (int) $im['id'] ?>"><button class="btn btn-danger btn-sm">Remove</button></form>
                </figcaption></figure><?php endforeach; ?></div>
        <details class="box" open><summary>Add photos</summary>
            <form method="post" enctype="multipart/form-data" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="add_images"><input type="hidden" name="room_id" value="<?= $id ?>">
                <div class="field"><label>Upload your own (JPG, PNG, WEBP · max 5 MB each · you can pick many)</label><input type="file" name="photos[]" accept="image/*" multiple></div>
                <div class="field"><label>Or pick from sample photos</label>
                    <div class="room-thumb-picker" style="grid-template-columns:repeat(6,1fr)"><?php foreach (sample_images() as $s): ?>
                        <label><input type="checkbox" name="sample[]" value="<?= e($s) ?>" onchange="this.closest('label').classList.toggle('picked', this.checked)"><img src="<?= img_url($s) ?>" alt=""></label><?php endforeach; ?></div></div>
                <button class="btn btn-primary">Add selected photos</button>
            </form></details>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
