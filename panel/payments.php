<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/booking_actions.php';
$me = require_role('landlord', 'tenant'); $role = $me['role']; $uid = (int) $me['id']; $manager = $role === 'landlord';   // billing lives with landlords — they record the payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $manager) {
    $action = $_POST['action'] ?? '';
    if ($action === 'record_payment') {
        $b = load_booking((int) ($_POST['booking_id'] ?? 0));
        [$ok, $msg] = $b ? record_payment($b, $_POST) : [false, 'Choose a booking.'];
        flash($ok ? 'success' : 'error', $msg);
    } elseif ($action === 'delete_payment') {
        $pay = row('SELECT p.*, b.booking_reference, r.created_by AS landlord_id FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN rooms r ON r.id = b.room_id WHERE p.id = ?', [(int) ($_POST['payment_id'] ?? 0)]);
        if (!$pay || ($role === 'landlord' && (int) $pay['landlord_id'] !== $uid)) { flash('error', 'Payment not found.'); }
        else {
            q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
            log_activity('payment.deleted', 'Payment', (int) $pay['id'], "Deleted payment {$pay['transaction_reference']} for booking {$pay['booking_reference']}");
            flash('success', "Payment {$pay['transaction_reference']} was deleted.");
        }
    } elseif ($action === 'record_month') {
        // one-click: file this month's rent for a tenant, pre-filled with their monthly amount
        $b = load_booking((int) ($_POST['booking_id'] ?? 0));
        if ($b && in_array($b['status'], ['confirmed', 'completed'], true)) {
            $amt = (float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total'];
            [$ok, $msg] = record_payment($b, ['amount' => $amt, 'payment_type' => 'rent', 'payment_method' => 'cash', 'status' => 'paid', 'paid_at' => date('Y-m-d'), 'notes' => 'Monthly rent — ' . date('F Y')]);
            flash($ok ? 'success' : 'error', $msg);
        } else { flash('error', 'That booking is not active.'); }
    }
    redirect('payments.php');
}
$f = ['status' => $_GET['status'] ?? '', 'type' => $_GET['type'] ?? '', 'q' => trim($_GET['q'] ?? '')];
$where = ['1=1']; $p = [];
if ($role === 'tenant') { $where[] = 'p.tenant_id = ?'; $p[] = $uid; } elseif ($role === 'landlord') { $where[] = 'r.created_by = ?'; $p[] = $uid; }
if (in_array($f['status'], ['pending', 'paid', 'failed', 'refunded', 'cancelled'], true)) { $where[] = 'p.status = ?'; $p[] = $f['status']; }
if (in_array($f['type'], ['deposit', 'rent', 'amenity', 'penalty', 'other'], true)) { $where[] = 'p.payment_type = ?'; $p[] = $f['type']; }
if ($f['q'] !== '') { $where[] = '(p.transaction_reference LIKE ? OR b.booking_reference LIKE ? OR r.room_number LIKE ?)'; array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%"); }
$W = implode(' AND ', $where);
$base = "FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = p.tenant_id WHERE $W";
$total = (int) val("SELECT COUNT(*) $base", $p);
$sum = (float) val("SELECT COALESCE(SUM(p.amount),0) $base AND p.status = 'paid'", $p);
$pg = paginate($total, 12, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT p.*, b.booking_reference, r.room_number, u.first_name, u.last_name $base ORDER BY p.id DESC LIMIT 12 OFFSET {$pg['offset']}", $p);
$open = [];
if ($manager) {
    $ow = $role === 'landlord' ? 'AND r.created_by = ' . $uid : '';
    $open = rows("SELECT b.id, b.booking_reference, b.monthly_rate_at_booking, b.amenities_total, b.security_deposit_at_booking, b.start_date, r.room_number, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM payments p2 WHERE p2.booking_id = b.id AND p2.payment_type = 'deposit' AND p2.status IN ('paid','pending')) AS deposit_paid,
        (SELECT COUNT(*) FROM payments p3 WHERE p3.booking_id = b.id AND p3.payment_type = 'rent' AND p3.status IN ('paid','pending') AND strftime('%Y-%m', COALESCE(p3.paid_at, p3.created_at)) = ?) AS rent_this_month
        FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id WHERE b.status IN ('confirmed','completed') $ow ORDER BY b.id DESC", [date('Y-m')]);
}
// tenants staying in a room right now + the monthly amount they pay (bed rate + their rented extras)
$income = [];
if ($manager) {
    $today = date('Y-m-d');
    $iw = $role === 'landlord' ? 'AND r.created_by = ' . $uid : '';
    $income = rows("SELECT b.id, b.booking_reference, b.monthly_rate_at_booking, b.amenities_total, b.start_date, b.end_date,
        r.room_number, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM payments p2 WHERE p2.booking_id = b.id AND p2.payment_type = 'rent'
            AND p2.status IN ('paid','pending') AND strftime('%Y-%m', COALESCE(p2.paid_at, p2.created_at)) = ?) AS paid_this_month
        FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id
        WHERE b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ? $iw
        ORDER BY r.room_number, u.first_name", [date('Y-m'), $today, $today]);
}
$title = $role === 'tenant' ? 'My Billing' : 'Billing'; $nav = 'payments';
require __DIR__ . '/../includes/panel_head.php';
?>
<div class="admin-topbar"><h1><?= e($title) ?></h1><span class="muted">Paid in this view: <strong style="color:var(--lavender)"><?= money($sum) ?></strong></span></div>
<?php if ($manager): ?><details class="box" open><summary>+ Record a payment (tenant · room · status · date paid)</summary>
    <form method="post" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="record_payment">
        <div class="form-grid">
            <div class="field full"><label>Booking (tenant · room)</label><select name="booking_id" id="pay_booking" required><option value="">Choose…</option><?php foreach ($open as $o): ?><option value="<?= (int) $o['id'] ?>" data-monthly="<?= (float) $o['monthly_rate_at_booking'] + (float) $o['amenities_total'] ?>" data-deposit="<?= (float) $o['security_deposit_at_booking'] ?>" data-dep-paid="<?= (int) $o['deposit_paid'] ?>" data-start="<?= e($o['start_date']) ?>"><?= e($o['booking_reference'] . ' · ' . $o['room_number'] . ' · ' . $o['first_name'] . ' ' . $o['last_name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Amount <span class="muted tiny">— auto-filled with what this tenant owes next</span></label><input type="number" name="amount" id="pay_amount" min="1" step="0.01" required></div>
            <div class="field"><label>Type <span class="muted tiny">— auto: deposit first, then rent</span></label><select name="payment_type" id="pay_type"><?php foreach (['deposit', 'rent', 'penalty', 'other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Method</label><select name="payment_method"><?php foreach (['cash', 'gcash', 'bank_transfer', 'other'] as $t): ?><option value="<?= $t ?>"><?= label($t) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Status</label><select name="status"><option value="paid">Paid</option><option value="pending">Pending</option><option value="failed">Failed</option><option value="refunded">Refunded</option><option value="cancelled">Cancelled</option></select></div>
            <div class="field"><label>Date paid (used when status is Paid)</label><input type="date" name="paid_at" id="pay_date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
            <div class="field full"><label>Notes</label><input name="notes"></div></div>
        <p class="tiny" id="pen_hint" style="display:none;margin:10px 0 0;color:#f87171"></p>
        <button class="btn btn-primary btn-sm">Save payment</button></form></details>
<script>
// picking a booking auto-fills what this tenant owes next —
// the security deposit first (if the room has one and it isn't recorded yet),
// afterwards their monthly rent (rate they signed up at + the extras they rent).
// if the rent is paid after its due date (move-in day, then every 30 days), a late
// penalty (penalty_percent of the monthly amount) is previewed here and auto-charged on save
(function(){
  var sel=document.getElementById('pay_booking'), amt=document.getElementById('pay_amount'),
      typ=document.getElementById('pay_type'), dat=document.getElementById('pay_date'), hint=document.getElementById('pen_hint'),
      PCT=<?= (float) setting('penalty_percent', 5) ?>, GRACE=<?= (int) setting('penalty_grace_days', 0) ?>,
      CUR=<?= json_encode(setting('currency_symbol', 'PHP ')) ?>;
  if(!sel||!amt)return;
  function money(n){return CUR+(Math.round(n*100)/100).toLocaleString('en-US',{maximumFractionDigits:2});}
  function penalty(){
    if(!hint){return null;}
    var o=sel.options[sel.selectedIndex];
    if(!o||!o.value){hint.style.display='none';return null;}
    var dep=parseFloat(o.dataset.deposit)||0;
    if(dep>0 && o.dataset.depPaid==='0'){hint.style.display='none';return null;} // deposit branch — never penalised
    var st=Date.parse(o.dataset.start), d=Date.parse(dat.value);
    if(isNaN(st)||isNaN(d)||d<=st){hint.style.display='none';return null;}
    var due=st+Math.floor((d-st)/2592000000)*2592000000, days=Math.floor((d-due)/86400000);
    if(days<=GRACE){hint.style.display='none';return null;}
    var pen=Math.round((parseFloat(o.dataset.monthly)||0)*PCT)/100;
    hint.textContent='⚠️ Rent is '+days+' day(s) late — a '+money(pen)+' penalty ('+PCT+'% of the monthly amount) will be added to this tenant\'s bill automatically when you save.';
    hint.style.display='block';
    return pen;
  }
  sel.addEventListener('change',function(){
    var o=sel.options[sel.selectedIndex]; if(!o||!o.value){return;}
    var mo=parseFloat(o.dataset.monthly)||0, dep=parseFloat(o.dataset.deposit)||0;
    if(dep>0 && o.dataset.depPaid==='0'){ typ.value='deposit'; amt.value=dep; }
    else { typ.value='rent'; amt.value=mo; }
    penalty();
  });
  if(dat){dat.addEventListener('change',penalty);}
})();
</script><?php endif; ?>
<?php if ($income): $moTotal = array_sum(array_map(fn($t) => (float) $t['monthly_rate_at_booking'] + (float) $t['amenities_total'], $income)); ?>
<div class="panel" style="margin:18px 0">
    <h3 style="margin-bottom:4px">Monthly income per tenant</h3>
    <p class="muted tiny">What each tenant staying right now pays you every month (bed rate + the extras they rent). Record a month with one click — it files a paid rent payment dated today, cash method.</p>
    <div class="table-wrap"><table><thead><tr><th>Tenant</th><th>Room</th><th>Booking</th><th>Period</th><th>Monthly amount</th><th>This month</th><th></th></tr></thead><tbody>
    <?php foreach ($income as $t): $moAmt = (float) $t['monthly_rate_at_booking'] + (float) $t['amenities_total']; ?>
        <tr><td><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td><td><?= e($t['room_number']) ?></td>
            <td><a href="booking_view.php?id=<?= (int) $t['id'] ?>" style="color:var(--purple-300)"><?= e($t['booking_reference']) ?></a></td>
            <td class="muted tiny"><?= nice_date($t['start_date']) ?> – <?= nice_date($t['end_date']) ?></td>
            <td><strong style="color:var(--violet)"><?= money($moAmt) ?></strong></td>
            <td><?php if ($t['paid_this_month']): ?><span style="color:#4ade80">✓ Rent recorded</span><?php else: ?><span class="muted tiny">Not yet</span><?php endif; ?></td>
            <td><?php if (!$t['paid_this_month']): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="record_month"><input type="hidden" name="booking_id" value="<?= (int) $t['id'] ?>"><button class="btn btn-outline btn-sm">Record <?= money($moAmt) ?> for <?= date('M Y') ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <tr><td colspan="4"><strong>Total per month (<?= count($income) ?> tenant<?= count($income) === 1 ? '' : 's' ?>)</strong></td><td><strong style="color:var(--violet)"><?= money($moTotal) ?></strong></td><td colspan="2"></td></tr>
    </tbody></table></div>
</div>
<?php endif; ?>
<form method="get" class="toolbar">
    <div class="field"><label>Search</label><input name="q" value="<?= e($f['q']) ?>" placeholder="Reference or room"></div>
    <div class="field"><label>Status</label><select name="status"><option value="">All</option><?php foreach (['pending', 'paid', 'failed', 'refunded', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Type</label><select name="type"><option value="">All</option><?php foreach (['deposit', 'rent', 'extension', 'amenity', 'penalty', 'other'] as $s): ?><option value="<?= $s ?>" <?= $f['type'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-outline btn-sm">Filter</button><a href="payments.php" class="btn btn-ghost btn-sm">Reset</a>
</form>
<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Booking</th><?php if ($role !== 'tenant'): ?><th>Tenant</th><?php endif; ?><th>Room</th><th>Type / Method</th><th>Amount</th><th>Status</th><th>Date</th><?php if ($manager): ?><th>Action</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($list as $x): ?><tr><td class="tiny"><?= e($x['transaction_reference']) ?></td><td><a href="booking_view.php?id=<?= (int) $x['booking_id'] ?>" style="color:var(--purple-300)"><?= e($x['booking_reference']) ?></a></td>
    <?php if ($role !== 'tenant'): ?><td><?= e($x['first_name'] . ' ' . $x['last_name']) ?></td><?php endif; ?><td><?= e($x['room_number']) ?></td>
    <td><?= e(label($x['payment_type'])) ?><br><span class="muted tiny"><?= e(label($x['payment_method'])) ?></span></td><td><?= money($x['amount']) ?></td>
    <td><?= badge($x['status']) ?></td>
    <td class="muted tiny"><?= e(substr((string) ($x['paid_at'] ?: $x['created_at']), 0, 10)) ?></td>
    <?php if ($manager): ?><td><form method="post" class="inline" onsubmit="return confirm('Delete payment <?= e($x['transaction_reference']) ?>? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $x['id'] ?>"><button class="btn btn-ghost btn-sm" style="color:#f87171">Delete</button></form></td><?php endif; ?></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="<?= $manager ? 9 : 8 ?>" class="muted">No payments found.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg) ?>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
