<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('tenant');
$roomId = (int) ($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
$room = row('SELECT ' . ROOM_SELECT . ' WHERE r.id = ? AND ' . visible_room_sql(), [$roomId]);
if (!$room) { flash('error', 'That room is not available for booking.'); redirect('../rooms.php'); }
$minDays = max(1, (int) setting('min_booking_days', 30));
$ams = room_amenities($roomId);
$busy = rows("SELECT start_date, end_date FROM bookings WHERE room_id = ? AND status = 'confirmed' AND end_date >= ? ORDER BY start_date", [$roomId, date('Y-m-d')]);
$bedsFree = max(0, (int) $room['capacity'] - beds_taken_today($roomId));
$errors = []; $in = ['start_date' => $_POST['start_date'] ?? '', 'end_date' => $_POST['end_date'] ?? '', 'number_of_occupants' => (int) ($_POST['number_of_occupants'] ?? 1), 'special_requests' => trim($_POST['special_requests'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $in['start_date']; $e = $in['end_date']; $today = date('Y-m-d');
    $validDates = preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e) && strtotime($s) && strtotime($e);
    // occupied private rooms are closed; occupied shared rooms still accept new roommates while beds remain
    if ($room['room_status'] !== 'available' && !($room['occupancy_type'] === 'shared' && $bedsFree > 0)) { $errors[] = 'This room is not available right now.'; }
    if (!$validDates) { $errors[] = 'Please choose a valid start and end date.'; }
    else {
        if ($s < $today) { $errors[] = 'The start date cannot be in the past.'; }
        if ($e <= $s) { $errors[] = 'The end date must be after the start date.'; }
        elseif ((new DateTime($s))->diff(new DateTime($e))->days < $minDays) { $errors[] = "The minimum rental period is $minDays days."; }
        if (!$errors && ($err = booking_slot_error($roomId, $s, $e, $in['number_of_occupants']))) { $errors[] = $err; }
    }
    if ($in['number_of_occupants'] < 1 || $in['number_of_occupants'] > (int) $room['capacity']) { $errors[] = 'Number of occupants must be between 1 and ' . (int) $room['capacity'] . '.'; }
    if (!$errors && val("SELECT 1 FROM bookings WHERE tenant_id = ? AND room_id = ? AND status = 'pending'", [$me['id'], $roomId])) { $errors[] = 'You already have a pending request for this room.'; }
    if (!$errors) {
        // only amenities this room actually offers, as picked by the tenant
        $amIds = [];
        foreach ((array) ($_POST['amx'] ?? []) as $aid) { if (in_array((int) $aid, array_column($ams, 'id'), true)) { $amIds[] = (int) $aid; } }
        $qt = quote($room, $s, $e, $amIds);
        do { $ref = 'BK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))); } while (val('SELECT 1 FROM bookings WHERE booking_reference = ?', [$ref]));
        q('INSERT INTO bookings (booking_reference, tenant_id, room_id, start_date, end_date, number_of_occupants, monthly_rate_at_booking, daily_rate_at_booking, security_deposit_at_booking, amenities_total, amenity_ids, total_amount, status, special_requests, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$ref, $me['id'], $roomId, $s, $e, $in['number_of_occupants'], $room['monthly_rate'], round($room['monthly_rate'] / 30, 2), $room['security_deposit'], $qt['amenities'], json_encode($amIds), $qt['total'], 'pending', $in['special_requests'] ?: null, now(), now()]);
        $bid = insert_id();
        notify((int) $room['created_by'], 'booking.requested', 'New booking request', full_name($me) . " requested room {$room['room_number']} ($ref).", 'booking_view.php?id=' . $bid);
        log_activity('booking.requested', 'Booking', $bid, "Requested room {$room['room_number']} ($ref)");
        flash('success', "Request $ref sent. The landlord will confirm or decline it soon.");
        redirect('booking_view.php?id=' . $bid);
    }
}
$title = 'Request Booking'; $nav = 'bookings';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1>Request Booking</h1><a class="btn btn-ghost btn-sm" href="../room.php?id=<?= $roomId ?>">← Back to room</a></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<div class="detail-layout" style="padding-top:0">
<form method="post" class="panel"><?= csrf_field() ?><input type="hidden" name="room_id" value="<?= $roomId ?>">
    <h3><?= e($room['room_number']) ?> · <?= e($room['title']) ?></h3>
    <?php if ($room['occupancy_type'] === 'shared'): ?>
        <p class="muted tiny" style="margin:-4px 0 10px">Shared room — <?= (int) $room['capacity'] ?> bed spaces. <?= beds_taken_today($roomId) ?> are taken now<?= $bedsFree ? ", $bedsFree still free" : '' ?>. Every tenant pays their own bed space and extras; you become roommates with the tenants who share your dates.</p>
    <?php endif; ?>
    <div class="form-grid" style="margin-top:14px">
        <div class="field"><label>Start date</label><input type="date" id="s" name="start_date" min="<?= date('Y-m-d') ?>" value="<?= e($in['start_date']) ?>" required></div>
        <div class="field"><label>End date (auto: start + <?= $minDays ?> days)</label><input type="date" id="e" name="end_date" value="<?= e($in['end_date']) ?>" readonly required></div>
        <div class="field"><label>Number of occupants (max <?= (int) $room['capacity'] ?>)</label><input type="number" name="number_of_occupants" min="1" max="<?= $bedsFree ?: (int) $room['capacity'] ?>" value="<?= max(1, $in['number_of_occupants']) ?>" required></div>
        <div class="field"></div>
        <div class="field full"><label>Special requests (optional)</label><textarea name="special_requests"><?= e($in['special_requests']) ?></textarea></div>
    </div>
    <button class="btn btn-primary">Send booking request</button>
</form>
<aside class="price-box" id="q" data-rate="<?= e($room['monthly_rate']) ?>" data-dep="<?= e($room['security_deposit']) ?>" data-cur="<?= e(setting('currency_symbol', 'PHP ')) ?>">
    <h3>Price summary</h3>
    <div class="kv"><span>Base monthly rate (bed space)</span><span><?= money($room['monthly_rate']) ?></span></div>
    <h4 style="margin:14px 0 4px;font-size:.85rem">Extras this tenant rents <span class="muted tiny">— optional, each is a monthly extra</span></h4>
    <?php foreach ($ams as $a): ?><label class="kv" style="cursor:pointer"><span><input type="checkbox" class="amx" data-price="<?= e($a['price']) ?>" name="amx[]" value="<?= (int) $a['id'] ?>" checked style="accent-color:var(--violet);margin-right:7px"> <?= e($a['name']) ?>
        <?php if ($a['price'] > 0): ?><span class="muted tiny">+ <?= money($a['price']) ?>/mo</span><?php else: ?><span class="muted tiny">included</span><?php endif; ?></span><span></span></label><?php endforeach; ?>
    <div class="kv"><span>Extras per month</span><span id="qex">-</span></div>
    <div class="kv"><span>Stay (exact: months + days)</span><span id="qm">-</span></div>
    <div class="kv"><span>Rent for the period</span><span id="qr">-</span></div>
    <div class="kv"><span>Security deposit</span><span><?= money($room['security_deposit']) ?></span></div>
    <div class="kv total"><span>Estimated total</span><span id="qt">-</span></div>
    <?php if ($busy): ?><p class="muted tiny" style="margin-top:12px">Taken dates: <?php foreach ($busy as $b): ?><?= nice_date($b['start_date']) ?>–<?= nice_date($b['end_date']) ?>; <?php endforeach; ?></p><?php endif; ?>
</aside>
</div>
<script>
(function(){
  var box=document.getElementById('q'), s=document.getElementById('s'), e=document.getElementById('e'), MIN=<?= $minDays ?>;
  function addDays(str,n){var p=str.split('-');var d=new Date(+p[0],+p[1]-1,+p[2]+n);var m=('0'+(d.getMonth()+1)).slice(-2),da=('0'+d.getDate()).slice(-2);return d.getFullYear()+'-'+m+'-'+da;}
  function extras(){var t=0;box.querySelectorAll('.amx:checked').forEach(function(c){t+=parseFloat(c.dataset.price)||0;});return t;}
  function fmt(n){return box.dataset.cur+Number(n).toLocaleString('en-US',{maximumFractionDigits:2});}
  function calc(){
    box.querySelector('#qex').textContent=fmt(extras());
    if(!s.value||!e.value){return;}
    var d=(new Date(e.value)-new Date(s.value))/86400000; if(!(d>0)){return;}
    var unit=parseFloat(box.dataset.rate)+extras(), mo=Math.floor(d/30), ex=d-mo*30;
    var rent=Math.round((mo*unit+ex*unit/30)*100)/100;
    var txt=(mo?mo+' month'+(mo>1?'s':''):'')+(mo&&ex?' + ':'')+(ex?ex+' day'+(ex>1?'s':''):'');
    document.getElementById('qm').textContent=txt||d+' days';
    document.getElementById('qr').textContent=fmt(rent);
    document.getElementById('qt').textContent=fmt(rent+parseFloat(box.dataset.dep));
  }
  box.querySelectorAll('.amx').forEach(function(c){c.addEventListener('change',calc);});
  // picking a start date auto-fills the end date with the minimum stay and updates the price
  s.addEventListener('change',function(){if(s.value){e.value=addDays(s.value,MIN);}calc();});
  e.addEventListener('change',calc); calc();
})();
</script>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
