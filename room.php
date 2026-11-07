<?php
require __DIR__ . '/includes/bootstrap.php';
$me = auth();
$id = (int) ($_GET['id'] ?? 0);
$room = row('SELECT ' . ROOM_SELECT . ' WHERE r.id = ? AND r.deleted_at IS NULL', [$id]);
$owner = $room && $me && ($me['role'] === 'administrator' || (int) $me['id'] === (int) $room['created_by']);
$public = $room && $room['approval_status'] === 'approved' && $room['is_published'];
if (!$room || (!$public && !$owner)) {
    http_response_code(404); $title = 'Room not found';
    require __DIR__ . '/includes/public_head.php';
    echo '<div class="container section"><div class="panel empty-state"><h2>Room not found</h2><p>This room does not exist or is not published yet.</p><a class="btn btn-primary" href="rooms.php">Browse rooms</a></div></div>';
    require __DIR__ . '/includes/public_foot.php'; exit;
}
$title = $room['title']; $activePage = 'rooms';
$images = rows('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, sort_order, id', [$id]);
$ams = room_amenities($id);
$extra = amenities_total($id);
$landlord = row('SELECT first_name, last_name, phone, address FROM users WHERE id = ?', [(int) $room['created_by']]);
// Date ranges and current occupants are shown publicly so future roommates know who lives here
$busy = rows("SELECT start_date, end_date FROM bookings WHERE room_id = ? AND status = 'confirmed' AND end_date >= ? ORDER BY start_date", [$id, date('Y-m-d')]);
$occupants = room_occupants_today($id);
require __DIR__ . '/includes/public_head.php';
?>
<div class="container">
<?php if (!$public): ?><div class="alert alert-info" style="margin-top:24px">Preview only: this listing is <strong><?= e($room['approval_status']) ?></strong> and is not visible to the public.
    <?php if ($room['rejection_reason']): ?> Reason: <?= e($room['rejection_reason']) ?><?php endif; ?></div><?php endif; ?>
<div class="detail-layout">
    <div>
        <div class="gallery-main"><img id="galleryMain" src="<?= img_url($images[0]['image_path'] ?? null) ?>" alt="<?= e($room['title']) ?>"></div>
        <?php if (count($images) > 1): ?><div class="gallery-thumbs"><?php foreach ($images as $i => $im): ?>
            <img src="<?= img_url($im['image_path']) ?>" data-full="<?= img_url($im['image_path']) ?>" class="<?= $i === 0 ? 'on' : '' ?>" alt=""><?php endforeach; ?></div><?php endif; ?>
        <div style="margin-top:28px">
            <div class="chips"><span class="chip"><?= e($room['room_number']) ?></span><span class="chip"><?= e($room['type_name']) ?></span>
                <span class="chip"><?= e(ucfirst($room['occupancy_type'])) ?> room</span><span class="chip">Up to <?= (int) $room['capacity'] ?> pax</span>
                <?php if ($room['floor_number']): ?><span class="chip">Floor <?= (int) $room['floor_number'] ?></span><?php endif; ?></div>
            <h1 style="font-size:2rem"><?= e($room['title']) ?></h1>
            <p class="muted"><?= nl2br(e($room['description'])) ?></p>
            <h3 style="margin-top:26px">Amenities</h3>
            <?php if (!$ams): ?><p class="muted">No amenities listed.</p><?php endif; ?>
            <?php foreach ($ams as $a): $note = trim((string) ($a['note'] ?? '')); ?><div class="amenity-line"><span><?= e($a['name']) ?> <span class="muted tiny">· <?= e($a['category']) ?></span></span>
                <span><?= $a['price'] > 0 ? '+ ' . money($a['price']) . ' / month' . ($note !== '' ? ' <span class="muted tiny">' . e($note) . '</span>' : '') : '<span style="color:var(--success)">' . ($note !== '' ? e($note) : 'Included') . '</span>' ?></span></div><?php endforeach; ?>
            <?php if ($occupants): $used = array_sum(array_map(fn($o) => (int) $o['number_of_occupants'], $occupants)); ?><h3 style="margin-top:26px">Who booked this room</h3>
                <div class="occ-cards">
                    <?php foreach ($occupants as $o): $monthly = (float) $o['monthly_rate_at_booking'] + (float) $o['amenities_total']; ?>
                    <div class="occ-card">
                        <div class="occ-head">
                            <span class="occ-avatar"><?= e(mb_strtoupper(mb_substr(trim($o['first_name']), 0, 1) . mb_substr(trim($o['last_name']), 0, 1))) ?></span>
                            <div><strong><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></strong>
                                <span class="muted tiny"><?= (int) $o['number_of_occupants'] ?> bed space<?= (int) $o['number_of_occupants'] > 1 ? 's' : '' ?> · <?= nice_date($o['start_date']) ?> – <?= nice_date($o['end_date']) ?></span></div>
                        </div>
                        <div class="kv"><span>Rents</span><span><?= $o['rented'] ? e(implode(', ', $o['rented'])) : 'Bed space only — no extras' ?></span></div>
                        <div class="kv"><span>Pays per month</span><span class="occ-pay"><?= money($monthly) ?></span></div>
                        <?php if (($o['status'] ?? 'confirmed') === 'pending'): ?><div class="muted tiny" style="margin-top:6px">⏳ Booking request sent — waiting for landlord confirmation.</div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($room['occupancy_type'] === 'shared'): ?><p class="muted tiny" style="margin-top:8px"><?= $used === 1 ? '1 bed space' : "$used bed spaces" ?> of <?= (int) $room['capacity'] ?> taken<?= (int) $room['capacity'] > $used ? ' — ' . ((int) $room['capacity'] - $used) . ' still free' : '' ?>.</p><?php endif; ?><?php endif; ?>            <?php if ($busy): ?><h3 style="margin-top:26px">Already booked</h3><p class="muted tiny">Dates below are taken.</p>
                <?php foreach ($busy as $b): ?><div class="chip" style="display:inline-block;margin:0 6px 6px 0"><?= nice_date($b['start_date']) ?> – <?= nice_date($b['end_date']) ?></div><?php endforeach; ?><?php endif; ?>
        </div>
    </div>
    <aside class="price-box">
        <?php if ($owner): $fromAppr = $me['role'] === 'administrator' && ($_GET['from'] ?? '') === 'approvals'; ?>
        <a class="btn btn-outline btn-block" href="<?= $fromAppr ? 'panel/approvals.php' : 'panel/rooms.php' ?>" style="margin-bottom:16px"><?= $fromAppr ? '← Approvals' : ($me['role'] === 'administrator' ? '← All Rooms' : '← My Rooms') ?></a><?php endif; ?>
        <div class="big"><?= money($room['monthly_rate']) ?> <span class="muted" style="font-size:.9rem">/ month</span></div>
        <div style="margin:8px 0 16px"><?= badge($room['room_status']) ?></div>
        <div class="kv"><span>Base monthly rate</span><span><?= money($room['monthly_rate']) ?></span></div>
        <div class="kv"><span>Amenity extras / month</span><span><?= money($extra) ?></span></div>
        <div class="kv"><span>Monthly total</span><span><?= money($room['monthly_rate'] + $extra) ?></span></div>
        <div class="kv total"><span>Security deposit</span><span><?= money($room['security_deposit']) ?></span></div>
        <?php if ($landlord): ?><div class="listed-by">
            <strong>Listed by <?= e(trim($landlord['first_name'] . ' ' . $landlord['last_name'])) ?></strong>
            <?php if (($landlord['address'] ?? '')): ?><p class="muted tiny" style="margin:4px 0 0"><?= e($landlord['address']) ?></p><?php endif; ?>
        </div><?php endif; ?>
        <div style="margin-top:18px">
        <?php if (!$public): ?>
            <a class="btn btn-ghost btn-block" href="panel/room_form.php?id=<?= $id ?>">Edit this listing</a>
        <?php elseif ($room['room_status'] !== 'available'): ?>
            <button class="btn btn-ghost btn-block" disabled>Not available right now</button>
        <?php elseif (!$me): ?>
            <a class="btn btn-primary btn-block" href="login.php?next=<?= urlencode('/panel/book.php?room_id=' . $id) ?>">Log in to request booking</a>
        <?php elseif ($me['role'] === 'tenant'): ?>
            <a class="btn btn-primary btn-block" href="panel/book.php?room_id=<?= $id ?>">Request booking</a>
        <?php else: ?>
            <button class="btn btn-ghost btn-block" disabled>Only tenants can book</button>
        <?php endif; ?>
        </div>
        <p class="muted tiny" style="margin-top:14px">Your final total is shown before you submit. Payments are recorded manually by the landlord; there is no online payment.</p>
    </aside>
</div>
</div>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
