<?php
require __DIR__ . '/includes/bootstrap.php';
$title = 'Find a Room'; $activePage = 'rooms';

// ---- read + validate filters (server side) ----
$f = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
    'occ' => in_array($_GET['occ'] ?? '', ['private', 'shared'], true) ? $_GET['occ'] : '',
    'min' => ($_GET['min'] ?? '') !== '' && is_numeric($_GET['min']) ? max(0, (float) $_GET['min']) : '',
    'max' => ($_GET['max'] ?? '') !== '' && is_numeric($_GET['max']) ? max(0, (float) $_GET['max']) : '',
    'cap' => ($_GET['cap'] ?? '') !== '' && ctype_digit((string) $_GET['cap']) ? (int) $_GET['cap'] : '',
    'sort' => in_array($_GET['sort'] ?? '', ['price_asc', 'price_desc', 'newest'], true) ? $_GET['sort'] : 'available',
    'full' => !empty($_GET['full']),   // include fully booked rooms (hidden by default)
];
// locations offered in the filter: the addresses already saved on landlord accounts that have visible rooms
$addrs = rows("SELECT DISTINCT u.address FROM users u JOIN rooms rm ON rm.created_by = u.id
               WHERE u.address != '' AND u.status = 'active' AND rm.deleted_at IS NULL AND rm.approval_status = 'approved' AND rm.is_published = 1
               ORDER BY u.address");
$f['addr'] = in_array((string) ($_GET['addr'] ?? ''), array_column($addrs, 'address'), true) ? (string) ($_GET['addr'] ?? '') : '';
$am = array_values(array_filter(array_map('intval', (array) ($_GET['am'] ?? []))));
$start = $_GET['start'] ?? ''; $end = $_GET['end'] ?? '';
$dateErr = null;
if ($start !== '' || $end !== '') {
    $ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end >= $start;
    if (!$ok) { $dateErr = 'Choose both dates, and make the end date after the start date.'; $start = $end = ''; }
}

$where = [visible_room_sql()]; $p = [];
if ($f['q'] !== '') { $where[] = '(r.room_number LIKE ? OR r.title LIKE ? OR r.description LIKE ?)'; array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }
if ($f['type'] !== '') { $where[] = 'rt.name = ?'; $p[] = $f['type']; }
if ($f['occ']) { $where[] = 'r.occupancy_type = ?'; $p[] = $f['occ']; }
if ($f['min'] !== '') { $where[] = 'r.monthly_rate >= ?'; $p[] = $f['min']; }
if ($f['max'] !== '') { $where[] = 'r.monthly_rate <= ?'; $p[] = $f['max']; }
if ($f['cap'] !== '') { $where[] = 'r.capacity >= ?'; $p[] = $f['cap']; }
if ($f['addr'] !== '') { $where[] = 'EXISTS (SELECT 1 FROM users u2 WHERE u2.id = r.created_by AND u2.address = ?)'; $p[] = $f['addr']; }
// a booked room should not keep showing as rentable: private rooms disappear once occupied,
// shared rooms disappear only when every bed is taken. Tick "Include fully booked" to see them.
if (!$f['full']) {
    $where[] = "(CASE WHEN r.occupancy_type = 'private' THEN r.room_status = 'available'"
             . " ELSE (SELECT COALESCE(SUM(b2.number_of_occupants),0) FROM bookings b2 WHERE b2.room_id = r.id AND b2.status = 'confirmed' AND b2.start_date <= ? AND b2.end_date >= ?) < r.capacity END)";
    array_push($p, date('Y-m-d'), date('Y-m-d'));
}
foreach ($am as $aid) { $where[] = 'EXISTS (SELECT 1 FROM amenity_room x WHERE x.room_id = r.id AND x.amenity_id = ?)'; $p[] = $aid; }
if ($start !== '') { $where[] = "NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = r.id AND b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ?)"; array_push($p, $end, $start); }
$W = implode(' AND ', $where);

$order = ['price_asc' => 'r.monthly_rate ASC', 'price_desc' => 'r.monthly_rate DESC', 'newest' => 'r.id DESC',
          'available' => "(r.room_status = 'available') DESC, r.monthly_rate ASC"][$f['sort']];
$per = max(3, (int) setting('rooms_per_page', 9));
$total = (int) val("SELECT COUNT(*) FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id WHERE $W", $p);
$pg = paginate($total, $per, (int) ($_GET['page'] ?? 1));
$list = rows('SELECT ' . ROOM_SELECT . " WHERE $W ORDER BY $order LIMIT $per OFFSET {$pg['offset']}", $p);

$types = rows('SELECT name FROM room_types ORDER BY name');
$allAm = rows('SELECT * FROM amenities ORDER BY name');
require __DIR__ . '/includes/public_head.php';
?>
<section class="section" style="padding-top:40px">
<div class="container">
    <div class="section-head"><div><div class="section-eyebrow">Full listing board</div><h2>Find a Room</h2><p class="muted"><?= $f['full'] ? 'Including fully booked rooms' : 'Fully booked rooms are hidden' ?> — <?= $total ?> room<?= $total === 1 ? '' : 's' ?> <?= $f['full'] ? 'match' : 'you can rent right now match' ?> your search.</p></div></div>
    <div class="search-layout">
        <div class="filter-col" id="filterCol">
        <button type="button" class="filter-close" id="filterClose">✕ Close</button>
        <form class="filter-box" method="get">
            <h3>Filters</h3>
            <div class="field"><label>Keyword / room number</label><input type="text" name="q" value="<?= e($f['q']) ?>"></div>
            <div class="field"><label>Room type</label><select name="type"><option value="">Any</option>
                <?php foreach ($types as $t): ?><option <?= $f['type'] === $t['name'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Occupancy</label><select name="occ"><option value="">Private or shared</option>
                <option value="private" <?= $f['occ'] === 'private' ? 'selected' : '' ?>>Private</option><option value="shared" <?= $f['occ'] === 'shared' ? 'selected' : '' ?>>Shared</option></select></div>
            <div class="field"><label>Location (landlord address)</label><select name="addr"><option value="">Any location</option>
                <?php foreach ($addrs as $a): ?><option <?= $f['addr'] === $a['address'] ? 'selected' : '' ?>><?= e($a['address']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Monthly budget (min / max)</label><div class="two"><input type="number" name="min" min="0" placeholder="Min" value="<?= e($f['min']) ?>"><input type="number" name="max" min="0" placeholder="Max" value="<?= e($f['max']) ?>"></div></div>
            <div class="field"><label>Minimum capacity</label><input type="number" name="cap" min="1" value="<?= e($f['cap']) ?>"></div>
            <div class="field"><label>Free on these dates</label><div class="two"><input type="date" name="start" min="<?= date('Y-m-d') ?>" value="<?= e($start) ?>"><input type="date" name="end" min="<?= date('Y-m-d') ?>" value="<?= e($end) ?>"></div>
                <?php if ($dateErr): ?><div class="tiny" style="color:var(--danger);margin-top:6px"><?= e($dateErr) ?></div><?php endif; ?></div>
            <div class="field"><label>Amenities</label><div class="check-list">
                <?php foreach ($allAm as $a): ?><label><input type="checkbox" name="am[]" value="<?= (int) $a['id'] ?>" <?= in_array((int) $a['id'], $am, true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label><?php endforeach; ?></div></div>
            <div class="field"><div class="check-list"><label><input type="checkbox" name="full" value="1" <?= $f['full'] ? 'checked' : '' ?>> Include fully booked rooms</label><span class="muted tiny">Booked rooms are hidden by default — you only see rooms you can actually rent.</span></div></div>
            <div class="field"><label>Sort by</label><select name="sort">
                <option value="available" <?= $f['sort'] === 'available' ? 'selected' : '' ?>>Available first</option>
                <option value="price_asc" <?= $f['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $f['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                <option value="newest" <?= $f['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option></select></div>
            <button class="btn btn-primary btn-block">Apply filters</button>
            <a href="rooms.php" class="btn btn-ghost btn-block" style="margin-top:8px">Reset</a>
        </form>
        <form id="compareForm" action="compare.php" method="get" class="filter-box cmp-box">
            <h3>Compare</h3>
            <p class="muted tiny" style="margin:6px 0 12px">Tick "Compare" on up to 3 rooms in the list, then stack their prices and amenity extras side by side.</p>
            <button class="btn btn-outline btn-block">Compare selected</button>
        </form>
        </div>
        <div class="drawer-backdrop" id="drawerBackdrop"></div>
        <div>
            <button type="button" class="btn btn-outline btn-block filter-toggle" id="filterToggle">☰ Filters &amp; Compare</button>
            <?php if (!$list): ?><div class="panel empty-state">No rooms match those filters. Try widening your budget or removing an amenity.</div>
            <?php else: ?><div class="room-grid" style="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))"><?php foreach ($list as $r) { room_card($r, true); } ?></div><?php endif; ?>
            <?= pager($pg) ?>
        </div>
    </div>
</div>
</section>
<?php require __DIR__ . '/includes/public_foot.php'; ?>
