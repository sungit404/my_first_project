<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_actions.php';
$me = require_login(); $id = (int) ($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$b = load_booking($id);
if (!$b) { flash('error', 'Booking not found.'); redirect('bookings.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'record_payment') { [$ok, $msg] = record_payment($b, $_POST); }
    elseif ($action === 'request_extension') { [$ok, $msg] = request_extension($b, $_POST); }
    elseif (in_array($action, ['approve', 'reject'], true) && ($_POST['request_id'] ?? '')) { [$ok, $msg] = decide_extension($b, (int) $_POST['request_id'], $action, $_POST['notes'] ?? ''); }
    else { [$ok, $msg] = booking_action($b, $action, $_POST['notes'] ?? ''); }
    flash($ok ? 'success' : 'error', $msg); sync_rooms(); redirect("booking_view.php?id=$id");
}
$pays = rows('SELECT p.*, u.first_name, u.last_name FROM payments p LEFT JOIN users u ON u.id = p.processed_by WHERE p.booking_id = ? ORDER BY p.id', [$id]);
$paid = array_sum(array_map(fn($p) => $p['status'] === 'paid' ? $p['amount'] : 0, $pays));
$parts = stay_parts($b['start_date'], $b['end_date']);
$monthlyTot = (float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total'];
$exts = rows('SELECT e.*, du.first_name AS df, du.last_name AS dl FROM extension_requests e LEFT JOIN users du ON du.id = e.decided_by WHERE e.booking_id = ? ORDER BY e.id DESC', [$id]);
$pendExt = null; foreach ($exts as $x) { if ($x['status'] === 'pending') { $pendExt = $x; break; } }
$extBilled = array_sum(array_map(fn($x) => $x['status'] === 'approved' ? (float) $x['amount'] : 0, $exts));
$today = date('Y-m-d');
$title = 'Booking ' . $b['booking_reference']; $nav = 'bookings';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1><?= e($b['booking_reference']) ?> <?= badge($b['status']) ?></h1><a class="btn btn-ghost btn-sm" href="bookings.php">← All bookings</a></div>
<div class="detail-layout" style="padding-top:0">
<div>
    <div class="panel" style="margin-bottom:18px"><h3>Booking details</h3>
        <div class="kv"><span>Room</span><span><a href="../room.php?id=<?= (int) $b['room_id'] ?>" style="color:var(--purple-300)"><?= e($b['room_number']) ?> · <?= e($b['title']) ?></a></span></div>
        <div class="kv"><span>Rental period</span><span><?= nice_date($b['start_date']) ?> – <?= nice_date($b['end_date']) ?><br><span class="muted tiny">Stay: <?= stay_sentence($b['start_date'], $b['end_date']) ?> (<?= $parts['days'] ?> days)</span></span></div>
        <div class="kv"><span>Occupants</span><span><?= (int) $b['number_of_occupants'] ?> bed<?= (int) $b['number_of_occupants'] > 1 ? 's' : '' ?> claimed</span></div>
        <?php
        $rentIds = array_map('intval', json_decode((string) ($b['amenity_ids'] ?? '[]'), true) ?: []);
        $rentNames = $rentIds ? rows('SELECT name FROM amenities WHERE id IN (' . implode(',', $rentIds) . ') ORDER BY id') : [];
        ?><div class="kv"><span>This tenant rents</span><span><?= $rentNames ? e(implode(', ', array_column($rentNames, 'name'))) : ((int) $b['number_of_occupants'] > 1 ? 'Bed space(s) only — no extras' : 'Bed space only — no extras') ?></span></div>
        <div class="kv"><span>Tenant</span><span><?= e($b['tf'] . ' ' . $b['tl']) ?><?php if ($b['is_manager']): ?> · <?= e($b['temail']) ?> · <?= e($b['tphone']) ?><?php endif; ?></span></div>
        <div class="kv"><span>Landlord</span><span><?= e($b['lf'] . ' ' . $b['ll']) ?></span></div>
        <?php if ($b['special_requests']): ?><div class="kv"><span>Special requests</span><span><?= e($b['special_requests']) ?></span></div><?php endif; ?>
        <?php if ($b['decision_notes']): ?><div class="kv"><span>Decision notes</span><span><?= e($b['decision_notes']) ?></span></div><?php endif; ?>
        <div class="kv"><span>Requested on</span><span><?= e(substr($b['created_at'], 0, 16)) ?></span></div>
    </div>
    <div class="table-wrap"><div class="table-head"><strong>Billing</strong><span class="muted tiny">Paid so far: <?= money($paid) ?><?php if ($me['role'] === 'landlord'): ?> — <a href="../panel/payments.php" style="color:var(--purple-300)">record a payment in Billing</a><?php endif; ?></span></div>
        <table><thead><tr><th>Reference</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
        <?php foreach ($pays as $p): ?><tr><td class="tiny"><?= e($p['transaction_reference']) ?></td><td><?= e(label($p['payment_type'])) ?></td><td><?= e(label($p['payment_method'])) ?></td><td><?= money($p['amount']) ?></td><td><?= badge($p['status']) ?></td><td class="muted tiny"><?= e(substr((string) ($p['paid_at'] ?: $p['created_at']), 0, 10)) ?></td></tr><?php endforeach; ?>
        <?php if (!$pays): ?><tr><td colspan="6" class="muted">No payments recorded yet.</td></tr><?php endif; ?></tbody></table></div>
    <div class="table-wrap" style="margin-top:18px"><div class="table-head"><strong>Stay extensions</strong><span class="muted tiny">Extra days are billed exactly — daily rate = monthly ÷ 30</span></div>
        <table><thead><tr><th>Requested</th><th>Extension</th><th>Charge</th><th>Status</th><th>Decision</th></tr></thead><tbody>
        <?php foreach ($exts as $x): ?><tr><td class="muted tiny"><?= e(substr($x['created_at'], 0, 10)) ?></td>
            <td>+<?= (int) $x['extra_days'] ?> day<?= (int) $x['extra_days'] > 1 ? 's' : '' ?> — until <?= nice_date($x['new_end']) ?></td>
            <td><?= money($x['amount']) ?></td><td><?= badge($x['status']) ?></td>
            <td class="muted tiny"><?= $x['df'] ? e($x['df'] . ' ' . $x['dl']) : '—' ?><?= $x['decision_notes'] ? ' · ' . e($x['decision_notes']) : '' ?></td></tr><?php endforeach; ?>
        <?php if (!$exts): ?><tr><td colspan="5" class="muted">No extension requests yet.</td></tr><?php endif; ?></tbody></table></div>
    <?php if ($b['is_tenant'] && $b['status'] === 'confirmed' && !$pendExt): ?>
        <details class="box" style="margin-top:14px"><summary>Request an extension…</summary>
            <form method="post" id="extForm" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="request_extension"><input type="hidden" name="booking_id" value="<?= $id ?>">
                <div class="field"><label>Extend my stay until</label><input type="date" name="new_end" min="<?= date('Y-m-d', strtotime($b['end_date'] . ' +1 day')) ?>" required></div>
                <div class="field"><label>Note for the landlord (optional)</label><input name="note" maxlength="200" placeholder="e.g. My classes run two weeks longer"></div>
                <p class="tiny muted" id="extPrev" style="margin:4px 0 8px"></p>
                <button class="btn btn-primary btn-sm">Send extension request</button></form></details>
    <?php endif; ?>
    <?php if ($b['is_manager'] && $pendExt): ?>
        <details class="box" style="margin-top:14px" open><summary>Extension waiting for your decision (+<?= (int) $pendExt['extra_days'] ?> day(s), <?= money($pendExt['amount']) ?>)</summary>
            <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int) $pendExt['id'] ?>"><input type="hidden" name="booking_id" value="<?= $id ?>">
                <button class="btn btn-primary btn-sm">Approve — extend to <?= nice_date($pendExt['new_end']) ?></button></form>
            <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int) $pendExt['id'] ?>"><input type="hidden" name="booking_id" value="<?= $id ?>">
                <div class="field"><label>Reason (required)</label><input name="notes" required></div>
                <button class="btn btn-danger btn-sm">Decline extension</button></form></details>
    <?php endif; ?>
</div>
<aside class="price-box">
    <h3>Price breakdown</h3>
    <div class="kv"><span>Base monthly rate</span><span><?= money($b['monthly_rate_at_booking']) ?></span></div>
    <div class="kv"><span>Amenity extras / month</span><span><?= money($b['amenities_total']) ?></span></div>
    <div class="kv"><span>× <?= $parts['months'] ?> full month<?= $parts['months'] > 1 ? 's' : '' ?></span><span><?= money($monthlyTot * $parts['months']) ?></span></div>
    <?php if ($parts['extra_days']): ?><div class="kv"><span>+ <?= $parts['extra_days'] ?> extra day<?= $parts['extra_days'] > 1 ? 's' : '' ?> × <?= money(daily_rate($monthlyTot)) ?>/day</span><span><?= money(round($parts['extra_days'] * $monthlyTot / 30, 2)) ?></span></div><?php endif; ?>
    <div class="kv"><span>Security deposit</span><span><?= money($b['security_deposit_at_booking']) ?></span></div>
    <div class="kv total"><span>Total<?= $extBilled ? ' (incl. extensions)' : '' ?></span><span><?= money($b['total_amount'] + $extBilled) ?></span></div>
    <div class="kv"><span>Paid</span><span><?= money($paid) ?></span></div>
    <div class="kv"><span>Balance</span><span><?= money(max(0, $b['total_amount'] + $extBilled - $paid)) ?></span></div>
    <div style="margin-top:16px;display:grid;gap:10px">
    <?php if ($b['is_manager'] && $b['status'] === 'pending'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><input type="hidden" name="booking_id" value="<?= $id ?>"><button class="btn btn-primary btn-block">Confirm booking</button></form>
        <details class="box" style="margin:0"><summary>Reject…</summary><form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="booking_id" value="<?= $id ?>">
            <div class="field"><label>Reason (required)</label><textarea name="notes" required></textarea></div><button class="btn btn-danger btn-sm">Reject</button></form></details>
    <?php endif; ?>
    <?php if ($b['is_manager'] && in_array($b['status'], ['pending', 'confirmed'], true)): ?>
        <details class="box" style="margin:0"><summary>Cancel booking…</summary><form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= $id ?>">
            <div class="field"><label>Reason (required)</label><textarea name="notes" required></textarea></div><button class="btn btn-danger btn-sm">Cancel booking</button></form></details>
    <?php elseif ($b['is_tenant'] && ($b['status'] === 'pending' || ($b['status'] === 'confirmed' && $b['start_date'] > $today))): ?>
        <form method="post" onsubmit="return confirm('Cancel this booking?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= $id ?>"><button class="btn btn-danger btn-block">Cancel my booking</button></form>
    <?php elseif ($b['is_tenant'] && $b['status'] === 'confirmed' && $b['start_date'] <= $today && $b['end_date'] >= $today): ?>
        <details class="box" style="margin:0"><summary style="color:var(--danger,#f87171);font-weight:600">End my booking…</summary>
            <p class="tiny muted" style="margin:8px 0 4px">Moving out early (e.g. to change rooms)? This ends your stay <strong>today</strong>, frees the room and notifies your landlord. Any balance still owed stays on your billing.</p>
            <form method="post" onsubmit="return confirm('End your stay in this room today? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="end_stay"><input type="hidden" name="booking_id" value="<?= $id ?>">
                <div class="field"><label>Reason for your landlord (required)</label><textarea name="notes" required maxlength="200" placeholder="e.g. I want to transfer to a room closer to campus"></textarea></div>
                <button class="btn btn-danger btn-block">End my booking</button></form></details>
    <?php endif; ?>
    </div>
</aside>
</div>
<script>
// live cost preview for the tenant's extension request: extra days x exact daily rate
(function(){ var f=document.getElementById('extForm'); if(!f) return;
  var end='<?= $b['end_date'] ?>', mo=<?= json_encode($monthlyTot) ?>, cur=<?= json_encode(setting('currency_symbol', 'PHP ')) ?>;
  f.new_end.addEventListener('change',function(){
    var p=f.querySelector('#extPrev');
    if(!f.new_end.value){p.textContent='';return;}
    var d=Math.round((new Date(f.new_end.value)-new Date(end))/86400000);
    if(!(d>0)){p.textContent='Pick a date after the current end date.';return;}
    var a=Math.round(d*mo/30*100)/100;
    p.textContent=d+' extra day'+(d>1?'s':'')+' \u00d7 '+cur+(mo/30).toFixed(2)+'/day = '+cur+a.toLocaleString('en-US',{maximumFractionDigits:2});
  });
})();
</script>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
