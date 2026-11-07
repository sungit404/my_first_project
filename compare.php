<?php
require __DIR__ . '/includes/bootstrap.php';
$title = 'Compare Rooms'; $activePage = 'rooms';
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['ids'] ?? []))))), 0, 3);
$rooms = [];
foreach ($ids as $id) {
    $r = row('SELECT ' . ROOM_SELECT . ' WHERE r.id = ? AND ' . visible_room_sql(), [$id]);
    if ($r) { $r['ams'] = room_amenities($id); $rooms[] = $r; }
}
require __DIR__ . '/includes/public_head.php';
?>
<section class="section" style="padding-top:40px"><div class="container">
<div class="section-head"><div><h2>Compare Rooms</h2><p class="muted">Amenity extras are added to the base rate so you see the real monthly cost.</p></div><a href="rooms.php" class="btn btn-outline btn-sm">Back to search</a></div>
<?php if (count($rooms) < 2): ?><div class="panel empty-state">Pick at least 2 rooms on the search page to compare them.</div>
<?php else: $allAm = []; foreach ($rooms as $r) foreach ($r['ams'] as $a) $allAm[$a['name']] = true; ksort($allAm); ?>
<div class="table-wrap"><table class="compare-table">
    <tr><th></th><?php foreach ($rooms as $r): ?><td><a href="room.php?id=<?= (int) $r['id'] ?>"><img src="<?= img_url($r['image']) ?>" class="thumb" style="width:100%;height:auto;aspect-ratio:16/10"><strong><?= e($r['title']) ?></strong></a></td><?php endforeach; ?></tr>
    <tr><th>Room</th><?php foreach ($rooms as $r): ?><td><?= e($r['room_number']) ?> · <?= e($r['type_name']) ?></td><?php endforeach; ?></tr>
    <tr><th>Occupancy</th><?php foreach ($rooms as $r): ?><td><?= e(ucfirst($r['occupancy_type'])) ?>, up to <?= (int) $r['capacity'] ?> pax</td><?php endforeach; ?></tr>
    <tr><th>Status</th><?php foreach ($rooms as $r): ?><td><?= badge($r['room_status']) ?></td><?php endforeach; ?></tr>
    <tr><th>Base rate / month</th><?php foreach ($rooms as $r): ?><td><?= money($r['monthly_rate']) ?></td><?php endforeach; ?></tr>
    <tr><th>Amenity extras / month</th><?php foreach ($rooms as $r): ?><td><?= money($r['amenity_extra']) ?></td><?php endforeach; ?></tr>
    <tr><th>Monthly total</th><?php foreach ($rooms as $r): ?><td><strong><?= money($r['monthly_rate'] + $r['amenity_extra']) ?></strong></td><?php endforeach; ?></tr>
    <tr><th>Security deposit</th><?php foreach ($rooms as $r): ?><td><?= money($r['security_deposit']) ?></td><?php endforeach; ?></tr>
    <?php foreach (array_keys($allAm) as $name): ?><tr><th><?= e($name) ?></th><?php foreach ($rooms as $r):
        $hit = null; foreach ($r['ams'] as $a) if ($a['name'] === $name) $hit = $a; ?>
        <td><?= $hit ? ($hit['price'] > 0 ? '+ ' . money($hit['price']) : '<span style="color:var(--success)">' . (trim((string) ($hit['note'] ?? '')) !== '' ? e(trim((string) $hit['note'])) : 'Included') . '</span>') : '<span class="muted">-</span>' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</table></div>
<?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
