<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_role('administrator', 'landlord');
$isAdmin = $me['role'] === 'administrator'; $uid = (int) $me['id'];
$all = ['inventory' => 'Room inventory', 'occupancy' => 'Availability & occupancy', 'bookings' => 'Bookings', 'payments' => 'Billing', 'income' => 'Monthly recorded income'];
if ($isAdmin) { $all['approvals'] = 'Room approvals'; }
$type = array_key_exists($_GET['report'] ?? '', $all) ? $_GET['report'] : 'inventory';
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? $d : null;
$from = $valid($_GET['from'] ?? null) ?: date('Y-m-d', strtotime('-365 days'));
$to = $valid($_GET['to'] ?? null) ?: date('Y-m-d');
if ($to < $from) { [$from, $to] = [$to, $from]; }
$fromT = "$from 00:00:00"; $toT = "$to 23:59:59"; $today = date('Y-m-d');
$own = $isAdmin ? '' : ' AND r.created_by = ' . $uid;
$cols = []; $data = []; $note = '';

switch ($type) {
    case 'inventory':
        $cols = ['Room', 'Title', 'Type', 'Occupancy', 'Capacity', 'Monthly rate', 'Deposit', 'Approval', 'Room status', 'Landlord'];
        foreach (rows("SELECT r.*, rt.name AS tn, u.first_name, u.last_name FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id JOIN users u ON u.id = r.created_by WHERE r.deleted_at IS NULL $own ORDER BY r.room_number") as $r) {
            $data[] = [$r['room_number'], $r['title'], $r['tn'], ucfirst($r['occupancy_type']), $r['capacity'], $r['monthly_rate'], $r['security_deposit'], ucfirst($r['approval_status']), ucfirst($r['room_status']), $r['first_name'] . ' ' . $r['last_name']];
        }
        $note = count($data) . ' rooms listed.'; break;
    case 'occupancy':
        $cols = ['Room', 'Room status', 'Current tenant', 'Current period', 'Next confirmed booking'];
        $occ = 0; $av = 0;
        foreach (rows("SELECT r.id, r.room_number, r.room_status FROM rooms r WHERE r.approval_status = 'approved' AND r.deleted_at IS NULL $own ORDER BY r.room_number") as $r) {
            $cur = row("SELECT b.start_date, b.end_date, u.first_name, u.last_name FROM bookings b JOIN users u ON u.id = b.tenant_id WHERE b.room_id = ? AND b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ?", [$r['id'], $today, $today]);
            $nx = row("SELECT start_date, end_date FROM bookings WHERE room_id = ? AND status = 'confirmed' AND start_date > ? ORDER BY start_date LIMIT 1", [$r['id'], $today]);
            $r['room_status'] === 'occupied' ? $occ++ : $av++;
            $data[] = [$r['room_number'], ucfirst($r['room_status']), $cur ? $cur['first_name'] . ' ' . $cur['last_name'] : '-', $cur ? nice_date($cur['start_date']) . ' - ' . nice_date($cur['end_date']) : '-', $nx ? nice_date($nx['start_date']) . ' - ' . nice_date($nx['end_date']) : '-'];
        }
        $tot = $occ + $av; $note = "Occupied: $occ · Available: $av" . ($tot ? ' · Occupancy rate ' . round($occ / $tot * 100) . '%' : ''); break;
    case 'approvals':
        $cols = ['Date', 'Room', 'Landlord', 'Change', 'Reviewed by', 'Remarks'];
        foreach (rows("SELECT l.*, r.room_number, s.first_name AS sf, s.last_name AS sl, v.first_name AS vf, v.last_name AS vl FROM room_approval_logs l JOIN rooms r ON r.id = l.room_id JOIN users s ON s.id = l.submitted_by LEFT JOIN users v ON v.id = l.reviewed_by WHERE l.created_at BETWEEN ? AND ? ORDER BY l.id DESC", [$fromT, $toT]) as $l) {
            $data[] = [substr($l['created_at'], 0, 10), $l['room_number'], $l['sf'] . ' ' . $l['sl'], $l['previous_status'] . ' -> ' . $l['new_status'], trim(($l['vf'] ?? '') . ' ' . ($l['vl'] ?? '')) ?: '-', $l['remarks']];
        }
        $note = count($data) . ' approval events.'; break;
    case 'bookings':
        $cols = ['Reference', 'Room', 'Tenant', 'Start', 'End', 'Occupants', 'Total', 'Status', 'Requested'];
        $sum = 0;
        foreach (rows("SELECT b.*, r.room_number, u.first_name, u.last_name FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.tenant_id WHERE b.created_at BETWEEN ? AND ? $own ORDER BY b.id DESC", [$fromT, $toT]) as $b) {
            $data[] = [$b['booking_reference'], $b['room_number'], $b['first_name'] . ' ' . $b['last_name'], $b['start_date'], $b['end_date'], $b['number_of_occupants'], $b['total_amount'], ucfirst($b['status']), substr($b['created_at'], 0, 10)];
            if ($b['status'] === 'confirmed' || $b['status'] === 'completed') { $sum += $b['total_amount']; }
        }
        $note = count($data) . ' bookings · value of confirmed/completed: ' . money($sum); break;
    case 'payments':
        $cols = ['Reference', 'Booking', 'Room', 'Tenant', 'Type', 'Method', 'Amount', 'Status', 'Date'];
        $sum = 0;
        foreach (rows("SELECT p.*, b.booking_reference, r.room_number, u.first_name, u.last_name FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = p.tenant_id WHERE COALESCE(p.paid_at, p.created_at) BETWEEN ? AND ? $own ORDER BY p.id DESC", [$fromT, $toT]) as $p) {
            $data[] = [$p['transaction_reference'], $p['booking_reference'], $p['room_number'], $p['first_name'] . ' ' . $p['last_name'], ucfirst($p['payment_type']), label($p['payment_method']), $p['amount'], ucfirst($p['status']), substr((string) ($p['paid_at'] ?: $p['created_at']), 0, 10)];
            if ($p['status'] === 'paid') { $sum += $p['amount']; }
        }
        $note = count($data) . ' payments · paid total: ' . money($sum); break;
    case 'income':
        $cols = ['Month', 'Paid payments', 'Recorded income'];
        $sum = 0;
        foreach (rows("SELECT substr(p.paid_at, 1, 7) AS m, COUNT(*) AS n, SUM(p.amount) AS t FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN rooms r ON r.id = b.room_id WHERE p.status = 'paid' AND p.paid_at BETWEEN ? AND ? $own GROUP BY m ORDER BY m", [$fromT, $toT]) as $m) {
            $data[] = [date('F Y', strtotime($m['m'] . '-01')), $m['n'], $m['t']]; $sum += $m['t'];
        }
        $note = 'Total recorded income: ' . money($sum) . ' (manual records only; there is no online payment gateway).'; break;
}

if ($isAdmin && ($_GET['export'] ?? '') === 'csv') {
    log_activity('report.exported', 'Report', null, "Exported $type report ($from to $to)");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stayhaus_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols);
    foreach ($data as $row) { fputcsv($out, array_map(fn($c) => is_string($c) && preg_match('/^[=+\-@]/', $c) ? "'" . $c : $c, $row)); }
    fclose($out); exit;
}
$title = 'Reports'; $nav = 'reports';
require __DIR__ . '/../includes/panel_head.php';
$moneyCols = ['Monthly rate', 'Deposit', 'Total', 'Amount', 'Recorded income'];
?>
<div class="admin-topbar"><h1>Reports</h1><?php if ($isAdmin): ?><a class="btn btn-outline btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $type, 'from' => $from, 'to' => $to, 'export' => 'csv']))) ?>">⬇ Export CSV</a><?php endif; ?></div>
<form method="get" class="toolbar">
    <div class="field"><label>Report</label><select name="report"><?php foreach ($all as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-primary btn-sm">Show report</button>
</form>
<p class="muted"><?= e($note) ?><?php if (in_array($type, ['inventory', 'occupancy'], true)): ?> <span class="tiny">(current snapshot; dates do not apply)</span><?php endif; ?></p>
<div class="table-wrap"><table><thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($data as $row): ?><tr><?php foreach ($row as $i => $cell): ?><td><?= in_array($cols[$i], $moneyCols, true) ? money($cell) : e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$data): ?><tr><td colspan="<?= count($cols) ?>" class="muted">No data for this report and date range.</td></tr><?php endif; ?></tbody></table></div>
<?php require __DIR__ . '/../includes/panel_foot.php'; ?>
