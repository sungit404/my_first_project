<?php
/** Who may see / manage a booking? Returns [booking+room info, isManager, isTenantOwner] or null. */
function load_booking(int $id): ?array {
    $me = auth();
    $b = row("SELECT b.*, r.room_number, r.title, r.created_by AS landlord_id, r.capacity, tu.first_name AS tf, tu.last_name AS tl, tu.email AS temail, tu.phone AS tphone,
                     lu.first_name AS lf, lu.last_name AS ll
              FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users tu ON tu.id = b.tenant_id JOIN users lu ON lu.id = r.created_by WHERE b.id = ?", [$id]);
    if (!$b) { return null; }
    $b['is_manager'] = $me['role'] === 'administrator' || ($me['role'] === 'landlord' && (int) $b['landlord_id'] === (int) $me['id']);
    $b['is_tenant'] = $me['role'] === 'tenant' && (int) $b['tenant_id'] === (int) $me['id'];
    return ($b['is_manager'] || $b['is_tenant']) ? $b : null;
}

/** confirm | reject | cancel. Returns [ok, message]. */
function booking_action(array $b, string $action, string $notes): array {
    $me = auth(); $today = date('Y-m-d'); $id = (int) $b['id']; $ref = $b['booking_reference'];
    $notes = trim($notes);
    if ($action === 'confirm') {
        if (!$b['is_manager']) { return [false, 'Only the landlord or an administrator can confirm bookings.']; }
        if ($b['status'] !== 'pending') { return [false, 'Only pending requests can be confirmed.']; }
        if (!val("SELECT 1 FROM rooms WHERE id = ? AND approval_status = 'approved' AND deleted_at IS NULL", [$b['room_id']])) { return [false, 'This room is not approved, so it cannot be booked.']; }
        if ($b['end_date'] < $today) { return [false, 'These dates are already in the past.']; }
        if ($err = booking_slot_error((int) $b['room_id'], $b['start_date'], $b['end_date'], (int) $b['number_of_occupants'], $id)) { return [false, $err]; }
        q("UPDATE bookings SET status = 'confirmed', processed_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?", [$me['id'], $notes ?: null, now(), $id]);
        // competing private-room requests for the same dates can no longer be honoured;
        // shared rooms keep their free beds, so those pendings stay with the landlord
        $isShared = val('SELECT occupancy_type FROM rooms WHERE id = ?', [$b['room_id']]) === 'shared';
        if (!$isShared) {
            foreach (rows("SELECT * FROM bookings WHERE room_id = ? AND status = 'pending' AND id != ? AND start_date <= ? AND end_date >= ?", [$b['room_id'], $id, $b['end_date'], $b['start_date']]) as $o) {
                q("UPDATE bookings SET status = 'rejected', processed_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?", [$me['id'], 'Dates were given to another booking.', now(), $o['id']]);
                notify((int) $o['tenant_id'], 'booking.rejected', 'Booking not available', "Your request {$o['booking_reference']} for room {$b['room_number']} was declined because the dates were taken.", 'bookings.php');
            }
        }
        notify((int) $b['tenant_id'], 'booking.confirmed', 'Booking confirmed', "Your booking $ref for room {$b['room_number']} is confirmed.", 'booking_view.php?id=' . $id);
        log_activity('booking.confirmed', 'Booking', $id, "Confirmed booking $ref");
        return [true, "Booking $ref confirmed."];
    }
    if ($action === 'reject') {
        if (!$b['is_manager']) { return [false, 'Only the landlord or an administrator can reject bookings.']; }
        if ($b['status'] !== 'pending') { return [false, 'Only pending requests can be rejected.']; }
        if ($notes === '') { return [false, 'Please write a short reason for the tenant.']; }
        q("UPDATE bookings SET status = 'rejected', processed_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?", [$me['id'], $notes, now(), $id]);
        notify((int) $b['tenant_id'], 'booking.rejected', 'Booking declined', "Your request $ref for room {$b['room_number']} was declined: $notes", 'booking_view.php?id=' . $id);
        log_activity('booking.rejected', 'Booking', $id, "Rejected booking $ref: $notes");
        return [true, "Booking $ref rejected."];
    }
    if ($action === 'cancel') {
        $okTenant = $b['is_tenant'] && ($b['status'] === 'pending' || ($b['status'] === 'confirmed' && $b['start_date'] > $today));
        $okManager = $b['is_manager'] && in_array($b['status'], ['pending', 'confirmed'], true);
        if (!$okTenant && !$okManager) { return [false, 'This booking can no longer be cancelled.']; }
        if ($b['is_manager'] && $notes === '') { return [false, 'Please write a reason for cancelling.']; }
        q("UPDATE bookings SET status = 'cancelled', processed_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?", [$b['is_manager'] ? $me['id'] : null, $notes ?: ($b['is_tenant'] ? 'Cancelled by tenant' : null), now(), $id]);
        if ($b['is_tenant']) { notify((int) $b['landlord_id'], 'booking.cancelled', 'Booking cancelled', full_name($me) . " cancelled $ref for room {$b['room_number']}.", 'booking_view.php?id=' . $id); }
        else { notify((int) $b['tenant_id'], 'booking.cancelled', 'Booking cancelled', "Your booking $ref for room {$b['room_number']} was cancelled: $notes", 'booking_view.php?id=' . $id); }
        log_activity('booking.cancelled', 'Booking', $id, "Cancelled booking $ref");
        return [true, "Booking $ref cancelled."];
    }
    if ($action === 'end_stay') {
        // early move-out: the stay has begun and the tenant wants out (e.g. to move to another room)
        if (!$b['is_tenant']) { return [false, 'Only the tenant on this booking can end their stay.']; }
        if ($b['status'] !== 'confirmed') { return [false, 'Only confirmed bookings can be ended.']; }
        if ($b['start_date'] > $today) { return [false, 'Your stay has not started yet — cancel the request instead.']; }
        if ($b['end_date'] < $today) { return [false, 'This stay has already ended.']; }
        if ($notes === '') { return [false, 'Please tell your landlord why you are ending your stay.']; }
        $newEnd = $b['end_date'] > $today ? $today : $b['end_date'];   // move out today (unless the booked end has already passed)
        q("UPDATE bookings SET status = 'cancelled', end_date = ?, decision_notes = ?, updated_at = ? WHERE id = ?", [$newEnd, 'Ended early by tenant: ' . $notes, now(), $id]);
        notify((int) $b['landlord_id'], 'booking.ended', 'Tenant ended their stay', full_name($me) . " ended $ref for room {$b['room_number']} today (move-out $today): $notes", 'booking_view.php?id=' . $id);
        log_activity('booking.ended', 'Booking', $id, "Tenant ended booking $ref early — move-out $today: $notes");
        return [true, "Your stay in room {$b['room_number']} ended today. The room is now free and your landlord was notified — you can book a different room from Find a Room anytime."];
    }
    return [false, 'Unknown action.'];
}

/** Manual payment record. Landlord (own rooms) or administrator only. */
function record_payment(array $b, array $in): array {
    $me = auth();
    if (!$b['is_manager']) { return [false, 'You cannot record payments for this booking.']; }
    if (!in_array($b['status'], ['confirmed', 'completed'], true)) { return [false, 'Payments can only be recorded for confirmed or completed bookings.']; }
    $amount = (float) ($in['amount'] ?? 0);
    $method = $in['payment_method'] ?? ''; $type = $in['payment_type'] ?? ''; $status = $in['status'] ?? 'paid';
    if ($amount <= 0) { return [false, 'Amount must be greater than zero.']; }
    if (!in_array($method, ['cash', 'gcash', 'bank_transfer', 'other'], true) || !in_array($type, ['deposit', 'rent', 'extension', 'amenity', 'penalty', 'other'], true)) { return [false, 'Choose a valid method and payment type.']; }
    if (!in_array($status, ['pending', 'paid', 'failed', 'refunded', 'cancelled'], true)) { $status = 'paid'; }
    // the landlord can set the actual date the tenant paid; defaults to today
    $paidAt = null;
    if ($status === 'paid') {
        $d = trim((string) ($in['paid_at'] ?? ''));
        $paidAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d . ' ' . date('H:i:s') : now();
    }
    do { $ref = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); } while (val('SELECT 1 FROM payments WHERE transaction_reference = ?', [$ref]));
    q('INSERT INTO payments (booking_id, tenant_id, processed_by, transaction_reference, amount, payment_method, payment_type, status, paid_at, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
      [$b['id'], $b['tenant_id'], $me['id'], $ref, $amount, $method, $type, $status, $paidAt, trim($in['notes'] ?? '') ?: null, now(), now()]);
    notify((int) $b['tenant_id'], 'payment.recorded', 'Payment recorded', money($amount) . " $type payment ($status) was recorded for {$b['booking_reference']}.", 'payments.php');
    log_activity('payment.recorded', 'Payment', insert_id(), "Recorded $type payment of " . money($amount) . " for {$b['booking_reference']}");
    // paying rent after the due date automatically charges a late penalty (Settings: penalty_percent of the monthly amount)
    $penalty = $type === 'rent' && $status === 'paid' ? rent_penalty_amount($b, $paidAt) : 0.0;
    if ($penalty > 0) {
        $lateDays = rent_late_days((string) $b['start_date'], $paidAt);
        do { $pref = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); } while (val('SELECT 1 FROM payments WHERE transaction_reference = ?', [$pref]));
        q('INSERT INTO payments (booking_id, tenant_id, processed_by, transaction_reference, amount, payment_method, payment_type, status, paid_at, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
          [$b['id'], $b['tenant_id'], $me['id'], $pref, $penalty, $method, 'penalty', 'pending', null, "Late rent penalty — paid $lateDays day(s) after the due date (" . setting('penalty_percent', 5) . "% of " . money((float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total']) . ')', now(), now()]);
        notify((int) $b['tenant_id'], 'payment.penalty', 'Late rent penalty', "Rent for {$b['booking_reference']} was paid $lateDays day(s) late — a " . money($penalty) . " penalty was charged to your account.", 'payments.php');
        log_activity('payment.penalty', 'Payment', insert_id(), "Auto-charged " . money($penalty) . " late penalty on {$b['booking_reference']} (rent $lateDays day(s) late)");
        return [true, "Payment $ref recorded. Rent was $lateDays day(s) late, so a " . money($penalty) . " penalty was automatically charged to the tenant."];
    }
    return [true, "Payment $ref recorded."];
}

/** Tenant asks to extend a confirmed stay. Extra days are billed at the exact daily rate (monthly ÷ 30). Returns [ok, message]. */
function request_extension(array $b, array $in): array {
    $me = auth();
    if (!$b['is_tenant']) { return [false, 'Only the tenant on this booking can request an extension.']; }
    if ($b['status'] !== 'confirmed') { return [false, 'Extensions can only be requested for confirmed bookings.']; }
    if (val("SELECT 1 FROM extension_requests WHERE booking_id = ? AND status = 'pending'", [$b['id']])) { return [false, 'An extension is already waiting for a decision on this booking.']; }
    $newEnd = trim((string) ($in['new_end'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newEnd) || $newEnd <= $b['end_date']) { return [false, 'Pick a new end date after the current one.']; }
    $days = (new DateTime($b['end_date']))->diff(new DateTime($newEnd))->days;
    if ($days > 365) { return [false, 'An extension can cover at most 365 extra days.']; }
    $monthly = (float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total'];
    $amount = round($days * $monthly / 30, 2);
    q('INSERT INTO extension_requests (booking_id, requested_by, current_end, new_end, extra_days, amount, note, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$b['id'], $me['id'], $b['end_date'], $newEnd, $days, $amount, trim((string) ($in['note'] ?? '')) ?: null, 'pending', now(), now()]);
    notify((int) $b['landlord_id'], 'extension.requested', 'Stay extension requested', full_name($me) . " asked to extend {$b['booking_reference']} in room {$b['room_number']} by $days day(s) — " . money($amount) . ' extra.', 'booking_view.php?id=' . (int) $b['id']);
    log_activity('extension.requested', 'Booking', (int) $b['id'], "Requested a $days day(s) extension on {$b['booking_reference']} (" . money($amount) . ")");
    return [true, "Extension request sent: $days extra day(s) at the exact daily rate — " . money($amount) . ". The landlord will approve or decline it."];
}

/** Landlord/admin decides an extension request: approve extends the booking and bills the extra days; reject needs a reason. Returns [ok, message]. */
function decide_extension(array $b, int $reqId, string $decision, string $notes): array {
    $me = auth();
    if (!$b['is_manager']) { return [false, 'Only the landlord or an administrator can decide extension requests.']; }
    $r = row('SELECT * FROM extension_requests WHERE id = ? AND booking_id = ?', [$reqId, $b['id']]);
    if (!$r || $r['status'] !== 'pending') { return [false, 'This extension request was already decided.']; }
    $notes = trim($notes);
    if ($decision === 'reject') {
        if ($notes === '') { return [false, 'Please write a short reason for declining.']; }
        q('UPDATE extension_requests SET status = ?, decided_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?', ['rejected', $me['id'], $notes, now(), $reqId]);
        notify((int) $b['tenant_id'], 'extension.rejected', 'Extension declined', "Your extension for {$b['booking_reference']} was declined: $notes", 'booking_view.php?id=' . (int) $b['id']);
        log_activity('extension.rejected', 'Booking', (int) $b['id'], "Declined extension on {$b['booking_reference']}: $notes");
        return [true, 'Extension request declined.'];
    }
    if ($decision !== 'approve') { return [false, 'Unknown decision.']; }
    if ($b['status'] !== 'confirmed') { return [false, 'The booking is no longer active, so it cannot be extended.']; }
    if ((string) $b['end_date'] !== (string) $r['current_end']) { return [false, 'The booking end date changed since this request — decline it and ask the tenant for a new one.']; }
    if ($err = booking_slot_error((int) $b['room_id'], $b['start_date'], $r['new_end'], (int) $b['number_of_occupants'], (int) $b['id'])) { return [false, 'Cannot extend: ' . $err]; }
    q('UPDATE extension_requests SET status = ?, decided_by = ?, decision_notes = ?, updated_at = ? WHERE id = ?', ['approved', $me['id'], $notes ?: null, now(), $reqId]);
    q('UPDATE bookings SET end_date = ?, updated_at = ? WHERE id = ?', [$r['new_end'], now(), $b['id']]);
    do { $ref = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); } while (val('SELECT 1 FROM payments WHERE transaction_reference = ?', [$ref]));
    q('INSERT INTO payments (booking_id, tenant_id, processed_by, transaction_reference, amount, payment_method, payment_type, status, paid_at, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
      [$b['id'], $b['tenant_id'], $me['id'], $ref, $r['amount'], 'cash', 'extension', 'pending', null,
       "Stay extension: +{$r['extra_days']} day(s) (" . nice_date($r['current_end']) . ' → ' . nice_date($r['new_end']) . ') at ' . money(daily_rate((float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total'])) . '/day', now(), now()]);
    notify((int) $b['tenant_id'], 'extension.approved', 'Extension approved', "Your stay in room {$b['room_number']} now ends " . nice_date($r['new_end']) . ' — ' . money($r['amount']) . " was added to your billing ({$r['extra_days']} extra day(s)).", 'booking_view.php?id=' . (int) $b['id']);
    log_activity('extension.approved', 'Booking', (int) $b['id'], "Approved extension on {$b['booking_reference']}: +{$r['extra_days']} day(s), " . money($r['amount']));
    return [true, "Extension approved — the booking now ends " . nice_date($r['new_end']) . ' and ' . money($r['amount']) . ' was added to the tenant\'s billing.'];
}
