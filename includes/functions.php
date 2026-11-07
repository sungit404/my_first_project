<?php
/* ---------- Database helpers ---------- */
function q(string $sql, array $p = []): PDOStatement { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function insert_id(): int { global $pdo; return (int) $pdo->lastInsertId(); }
function now(): string { return date('Y-m-d H:i:s'); }

/* ---------- Output helpers ---------- */
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function money($n): string {
    $n = (float) $n;
    return setting('currency_symbol', 'PHP ') . number_format($n, fmod($n, 1) == 0 ? 0 : 2);
}
function nice_date(?string $d): string { return $d ? date('M j, Y', strtotime($d)) : '-'; }
function label(string $s): string { return ucwords(str_replace('_', ' ', $s)); }
function badge(string $status): string { return '<span class="pill status-' . e($status) . '">' . e(label($status)) . '</span>'; }
function redirect(string $to): void { header('Location: ' . $to); exit; }
function full_name(array $u): string { return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); }
/** Trim + capitalize a person's name ("glenard gallega" -> "Glenard Gallega", "anne-marie" -> "Anne-Marie"). */
function clean_name(string $s): string { return trim(ucwords(mb_strtolower(trim($s)), " \t\r\n\f\v-'")); }
/** Strip spaces, dots and dashes from a phone ("0917 123 - 4567" -> "09171234567"). */
function clean_phone(string $s): string { return preg_replace('/[\s.\-]/', '', trim($s)); }
/** True for a valid PH mobile: exactly 11 digits starting with 09. */
function valid_phone(string $s): bool { return preg_match('/^09\d{9}$/', $s) === 1; }

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (rows('SELECT "key", value FROM settings') as $r) { $cache[$r['key']] = $r['value']; } }
    return $cache[$key] ?? $default;
}

function flash(string $key, ?string $msg = null) {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $m = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $m;
}
function flash_html(): string {
    $out = '';
    foreach (['success' => 'alert-success', 'error' => 'alert-error', 'info' => 'alert-info'] as $k => $cls) {
        if ($m = flash($k)) { $out .= '<div class="alert ' . $cls . '">' . e($m) . '</div>'; }
    }
    return $out;
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(24)); }
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }

/* ---------- Auth ---------- */
function auth(): ?array {
    if (array_key_exists('_auth', $GLOBALS)) { return $GLOBALS['_auth']; }
    $id = $_SESSION['uid'] ?? null;
    $u = $id ? row('SELECT u.*, r.slug AS role, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]) : null;
    if ($id && (!$u || $u['status'] !== 'active')) { unset($_SESSION['uid']); $u = null; }
    return $GLOBALS['_auth'] = $u;
}
function login_user(int $id): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($GLOBALS['_auth']);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $id]);
}
function logout_user(): void { $_SESSION = []; session_destroy(); unset($GLOBALS['_auth']); }
function require_login(): array {
    $u = auth();
    if (!$u) {
        flash('info', 'Please log in to continue.');
        redirect(BASE . 'login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        $title = 'Access denied';
        require __DIR__ . '/public_head.php';
        echo '<div class="container section"><div class="panel empty-state"><h2>403 - Access denied</h2><p>Your account role (' . e($u['role_name']) . ') cannot open this page.</p><a class="btn btn-primary" href="' . BASE . 'panel/dashboard.php">Back to my dashboard</a></div></div>';
        require __DIR__ . '/public_foot.php';
        exit;
    }
    return $u;
}
function safe_next(?string $next): string {
    if ($next && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\')) { return $next; }
    return BASE . 'panel/dashboard.php';
}

/* ---------- Logs & notifications ---------- */
function log_activity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $desc = null): void {
    $u = auth();
    q('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at) VALUES (?,?,?,?,?,?,?)',
      [$u['id'] ?? null, $action, $entityType, $entityId, $desc, $_SERVER['REMOTE_ADDR'] ?? null, now()]);
}
function uuid4(): string {
    $d = random_bytes(16); $d[6] = chr(ord($d[6]) & 0x0f | 0x40); $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function notify(int $userId, string $type, string $title, string $message, ?string $url = null): void {
    q('INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
      [uuid4(), $type, 'User', $userId, json_encode(['title' => $title, 'message' => $message, 'url' => $url]), now(), now()]);
}
function notify_admins(string $type, string $title, string $message, ?string $url = null): void {
    foreach (rows("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'administrator' AND u.status = 'active'") as $a) {
        notify((int) $a['id'], $type, $title, $message, $url);
    }
}
function unread_count(): int {
    $u = auth(); if (!$u) { return 0; }
    return (int) val("SELECT COUNT(*) FROM notifications WHERE notifiable_type = 'User' AND notifiable_id = ? AND read_at IS NULL", [$u['id']]);
}

/* ---------- Room / booking rules ---------- */
// Approval status and room status are separate. Room status follows confirmed bookings.
// Private (1-pax) rooms are taken by the FIRST booking request — pending counts, so the room
// shows "occupied" and the tenant card the moment someone books. Shared rooms count only
// confirmed tenants staying today.
function sync_rooms(): void {
    $today = date('Y-m-d');
    q("UPDATE bookings SET status = 'completed', updated_at = ? WHERE status = 'confirmed' AND end_date < ?", [now(), $today]);
    q("UPDATE rooms SET room_status = CASE
         WHEN approval_status = 'approved' AND is_published = 1 AND deleted_at IS NULL THEN
              (CASE WHEN EXISTS (SELECT 1 FROM bookings b WHERE b.room_id = rooms.id AND b.end_date >= ?
                        AND (CASE WHEN rooms.occupancy_type = 'shared' THEN b.status = 'confirmed' AND b.start_date <= ?
                                  ELSE b.status IN ('pending','confirmed') END))
                    THEN 'occupied' ELSE 'available' END)
         ELSE 'unavailable' END", [$today, $today]);
}
function room_conflict(int $roomId, string $start, string $end, ?int $ignoreBookingId = null): bool {
    return (bool) val("SELECT 1 FROM bookings WHERE room_id = ? AND status = 'confirmed' AND start_date <= ? AND end_date >= ? AND id != ? LIMIT 1",
                      [$roomId, $end, $start, $ignoreBookingId ?? 0]);
}
// days past the due date for a rent payment dated $paidAt on a booking that started $start.
// rent is due on the move-in date and then every 30 days; 0 = on time (or paid early)
function rent_late_days(string $start, ?string $paidAt): int {
    $s = strtotime($start); $d = $paidAt ? strtotime(substr((string) $paidAt, 0, 10)) : false;
    if (!$s || !$d || $d <= $s) { return 0; }
    $due = $s + (int) floor(($d - $s) / 2592000) * 2592000; // due day of the 30-day block the payment date falls in
    return (int) floor(($d - $due) / 86400);
}
// the late penalty owed on a booking: penalty_percent of the tenant's monthly amount, 0 when not late
function rent_penalty_amount(array $b, ?string $paidAt): float {
    $late = rent_late_days((string) $b['start_date'], $paidAt);
    if ($late <= (int) setting('penalty_grace_days', 0)) { return 0.0; }
    return round(((float) $b['monthly_rate_at_booking'] + (float) $b['amenities_total']) * ((float) setting('penalty_percent', 5)) / 100, 2);
}
// exact stay split: full 30-day blocks plus the remaining days, e.g. 45 days = 1 month + 15 days
function stay_parts(string $start, string $end): array {
    $days = (new DateTime($start))->diff(new DateTime($end))->days;
    $months = (int) floor($days / 30); $extra = $days - $months * 30;
    return ['days' => $days, 'months' => $months, 'extra_days' => $extra];
}
// the stay described in months and days: "1 month + 15 days"
function stay_sentence(string $start, string $end): string {
    $p = stay_parts($start, $end);
    $m = $p['months'] ? $p['months'] . ' month' . ($p['months'] > 1 ? 's' : '') : '';
    $d = $p['extra_days'] ? $p['extra_days'] . ' day' . ($p['extra_days'] > 1 ? 's' : '') : '';
    return trim($m . ($m && $d ? ' + ' : '') . $d) ?: '0 days';
}
// exact daily rate of a monthly amount (used for partial months and stay extensions)
function daily_rate(float $monthly): float {
    return round($monthly / 30, 2);
}
// total = (base monthly rate + amenity extras) x full months, plus any leftover days at the
// exact daily rate (monthly ÷ 30), plus the security deposit.
// $amIds (optional): the amenity ids THIS tenant rents, else all room amenities.
function quote(array $room, string $start, string $end, ?array $amIds = null): array {
    if ($amIds === null) {
        $am = amenities_total((int) $room['id']);
    } else {
        $amIds = array_values(array_unique(array_map('intval', $amIds)));
        $am = $amIds ? (float) val('SELECT COALESCE(SUM(price),0) FROM amenity_room WHERE room_id = ? AND amenity_id IN (' . implode(',', $amIds) . ')', [(int) $room['id']]) : 0.0;
    }
    $p = stay_parts($start, $end);
    $monthly = (float) $room['monthly_rate'] + $am;
    $rent = round($p['months'] * $monthly + $p['extra_days'] * $monthly / 30, 2);
    return ['months' => $p['months'], 'extra_days' => $p['extra_days'], 'days' => $p['days'], 'amenities' => $am,
            'rent' => $rent, 'deposit' => (float) $room['security_deposit'], 'total' => $rent + (float) $room['security_deposit']];
}
function amenities_total(int $roomId): float {
    return (float) val('SELECT COALESCE(SUM(price), 0) FROM amenity_room WHERE room_id = ?', [$roomId]);
}
function room_amenities(int $roomId): array {
    return rows('SELECT a.*, ar.price, ar.note FROM amenity_room ar JOIN amenities a ON a.id = ar.amenity_id WHERE ar.room_id = ? ORDER BY a.category, a.name', [$roomId]);
}
/* ---- Beds per tenant ----
   Private rooms take the whole room: only one confirmed booking may overlap.
   Shared rooms house several tenants at once, one bed per occupant, so a new
   booking only has to leave a free bed in the busiest overlapping window. */
function booking_slot_error(int $roomId, string $start, string $end, int $occupants, ?int $ignoreBookingId = null): ?string {
    $room = row('SELECT occupancy_type, capacity FROM rooms WHERE id = ?', [$roomId]);
    if (!$room) { return null; }
    $taken = rows("SELECT start_date, end_date FROM bookings WHERE room_id = ? AND status = 'confirmed' AND start_date <= ? AND end_date >= ? AND id != ?",
                  [$roomId, $end, $start, $ignoreBookingId ?? 0]);
    if ($room['occupancy_type'] !== 'shared') {
        return $taken ? 'Another confirmed booking already covers those dates.' : null;
    }
    $windows = [[$start, $end]];
    foreach ($taken as $b) { $windows[] = [max($start, $b['start_date']), min($end, $b['end_date'])]; }
    foreach ($windows as $w) {
        if ($w[0] > $w[1]) { continue; }
        $mid = date('Y-m-d', (int) ((strtotime($w[0]) + strtotime($w[1])) / 2));
        $used = (int) val("SELECT COALESCE(SUM(number_of_occupants),0) FROM bookings WHERE room_id = ? AND status = 'confirmed' AND start_date <= ? AND end_date >= ? AND id != ?",
                          [$roomId, $mid, $mid, $ignoreBookingId ?? 0]);
        if ($used + $occupants > (int) $room['capacity']) {
            return 'This shared room only has ' . max(0, (int) $room['capacity'] - $used) . ' free bed(s) on those dates. Someone else already booked the rest.';
        }
    }
    return null;
}
// bed spaces currently claimed by occupants living in the room today
function beds_taken_today(int $roomId): int {
    $today = date('Y-m-d');
    return (int) val("SELECT COALESCE(SUM(number_of_occupants),0) FROM bookings WHERE room_id = ? AND status = 'confirmed' AND start_date <= ? AND end_date >= ?", [$roomId, $today, $today]);
}
// the people living in the room today — or, for private rooms, everyone whose booking
// hasn't ended yet, pending requests included (booked = taken, card appears at once)
function room_occupants_today(int $roomId): array {
    $today = date('Y-m-d');
    $shared = (($row = row('SELECT occupancy_type FROM rooms WHERE id = ?', [$roomId])) ? $row['occupancy_type'] : 'shared') === 'shared';
    $occ = rows("SELECT b.id, b.tenant_id, b.status, b.start_date, b.end_date, b.number_of_occupants, b.amenity_ids,
                        b.monthly_rate_at_booking, b.amenities_total, b.total_amount,
                        u.first_name, u.last_name, u.email, u.phone
                 FROM bookings b JOIN users u ON u.id = b.tenant_id
                 WHERE b.room_id = ? AND b.end_date >= ? AND b.status IN ('confirmed','pending')" . ($shared ? " AND b.status = 'confirmed' AND b.start_date <= ?" : "") . "
                 ORDER BY b.start_date, b.id", $shared ? [$roomId, $today, $today] : [$roomId, $today]);
    $names = [];
    foreach (rows('SELECT amenity_id, name FROM amenity_room ar JOIN amenities a ON a.id = ar.amenity_id WHERE ar.room_id = ?', [$roomId]) as $x) { $names[(int) $x['amenity_id']] = $x['name']; }
    foreach ($occ as &$o) {
        $ids = array_map('intval', json_decode((string) ($o['amenity_ids'] ?? '[]'), true) ?: []);
        $o['rented'] = array_values(array_filter(array_map(fn($i) => $names[$i] ?? null, $ids)));
    }
    return $occ;
}
// "occupied by Jero Villanueva · jero@example.com · 0917-333-4444 … roommate Juan …"
// Private rooms list their sequential bookings instead: "booked by X · Nov 6 – Dec 7 … next tenant Y"
function occupancy_sentence(array $occ, int $capacity, bool $withContact = false, string $occType = 'shared'): string {
    if (!$occ) { return 'This room has free beds right now — no confirmed occupants today.'; }
    $shared = $occType === 'shared';
    $usedSum = array_sum(array_map(fn($o) => (int) $o['number_of_occupants'], $occ));
    $bedTxt = $shared && $capacity > 1 ? ($usedSum === 1 ? '1 bed space' : "$usedSum bed spaces") . ' of ' . (int) $capacity . ' in total.' : '';
    $seq = [];
    foreach ($occ as $i => $o) {
        $who = e(trim($o['first_name'] . ' ' . $o['last_name']));
        if ($withContact) { $who .= ' · ' . e($o['email']) . ' · ' . e($o['phone'] ?: 'no phone'); }
        $beds = (int) $o['number_of_occupants'];
        $rents = $o['rented'] ? e(implode(', ', $o['rented'])) : '';
        $rentTxt = ($rents ? 'the ' . $rents : 'the bed space only — no extras');
        if (!$shared) {
            $span = nice_date($o['start_date']) . ' – ' . nice_date($o['end_date']);
            $pend = ($o['status'] ?? 'confirmed') === 'pending' ? ' <span class="muted tiny">(request pending — not confirmed yet)</span>' : '';
            $seq[] = '<p class="occ-line">' . ($i === 0 ? 'This room is booked by <strong>' . $who . '</strong>' : 'Their next tenant <strong>' . $who . '</strong>') . ' · <span class="muted tiny">' . $span . '</span>' . $pend . ' — rents ' . $rentTxt . '.</p>';
            continue;
        }
        if ($i === 0) {
            $line = 'This room is occupied by <strong>' . $who . '</strong>' . ($beds > 1 ? " ($beds bed spaces)" : '') . ' and they just rent ' . $rentTxt . '.';
        } else {
            $line = 'Their current roommate <strong>' . $who . '</strong>' . ($beds > 1 ? " ($beds bed spaces)" : '') . ' just rents ' . ($rents ? 'a bed with ' . $rents : 'a bed space only — no extras') . '.';
        }
        $seq[] = '<p class="occ-line">' . $line . '</p>';
    }
    if ($bedTxt) { $seq[] = '<p class="muted tiny">' . e($bedTxt) . '</p>'; }
    return implode('', $seq);
}
function visible_room_sql(): string { return "r.approval_status = 'approved' AND r.is_published = 1 AND r.deleted_at IS NULL"; }
const ROOM_SELECT = "r.*, rt.name AS type_name, (SELECT image_path FROM room_images i WHERE i.room_id = r.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image,
    (SELECT COALESCE(SUM(price),0) FROM amenity_room ar WHERE ar.room_id = r.id) AS amenity_extra,
    (SELECT COUNT(*) FROM amenity_room ar WHERE ar.room_id = r.id) AS amenity_count,
    (SELECT address FROM users u2 WHERE u2.id = r.created_by) AS owner_address
    FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id";
function img_url(?string $path): string { return BASE . 'assets/images/' . e($path ?: 'rooms/placeholder.svg'); }

function room_card(array $r, bool $compare = false): void {
    echo '<div class="room-card">';
    echo '<a class="room-photo" href="' . BASE . 'room.php?id=' . (int) $r['id'] . '" style="display:block"><img src="' . img_url($r['image']) . '" alt="' . e($r['title']) . '" loading="lazy">';
    echo '<div class="room-tag">' . e($r['room_number']) . '</div><div class="room-status status-' . e($r['room_status']) . '">' . e(label($r['room_status'])) . '</div></a>';
    echo '<div class="room-body"><h3>' . e($r['title']) . '</h3>';
    echo '<div class="chips"><span class="chip">' . e($r['type_name']) . '</span><span class="chip">' . e(ucfirst($r['occupancy_type'])) . '</span><span class="chip">' . (int) $r['capacity'] . ' pax</span>';
    if ((int) $r['amenity_count'] > 0) { echo '<span class="chip">' . (int) $r['amenity_count'] . ' amenities</span>'; }
    echo '</div>';
    if (!empty($r['owner_address'])) { echo '<div class="room-loc"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' . e($r['owner_address']) . '</div>'; }
    echo '<div class="room-foot"><div class="room-price">' . money($r['monthly_rate']) . ' <span>/ month</span></div>';
    echo '<a href="' . BASE . 'room.php?id=' . (int) $r['id'] . '" class="btn btn-primary btn-sm">View</a></div>';
    if ($compare) { echo '<label class="cmp"><input type="checkbox" name="ids[]" value="' . (int) $r['id'] . '" form="compareForm"> Compare</label>'; }
    echo '</div></div>';
}

/* ---------- Pagination ---------- */
function paginate(int $total, int $per, int $page): array {
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per, 'per' => $per, 'total' => $total];
}
function pager(array $pg): string {
    if ($pg['pages'] <= 1) { return ''; }
    $out = '<div class="pager">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $qs = http_build_query(array_merge($_GET, ['page' => $i]));
        $out .= '<a href="?' . e($qs) . '" class="' . ($i === $pg['page'] ? 'on' : '') . '">' . $i . '</a>';
    }
    return $out . '</div>';
}

/* ---------- Image uploads ---------- */
function save_upload(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) { return null; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($file['tmp_name']) === false) { return null; }
    $name = 'room_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dir = __DIR__ . '/../assets/images/uploads';
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    return move_uploaded_file($file['tmp_name'], $dir . '/' . $name) ? 'uploads/' . $name : null;
}
function sample_images(): array {
    $out = [];
    for ($i = 1; $i <= 12; $i++) { $out[] = "rooms/room$i.svg"; }
    return $out;
}

/* ---------- Room approval submit (shared by room list + room form) ---------- */
function submit_room_for_approval(array $room): array {
    $id = (int) $room['id']; $me = auth();
    if (!in_array($room['approval_status'], ['draft', 'rejected'], true)) { return [false, 'Only draft or rejected rooms can be submitted.']; }
    if (!val('SELECT 1 FROM room_images WHERE room_id = ?', [$id])) { return [false, 'Add at least one photo before submitting for approval.']; }
    q("UPDATE rooms SET approval_status = 'pending', rejection_reason = NULL, updated_at = ? WHERE id = ?", [now(), $id]);
    q('INSERT INTO room_approval_logs (room_id, submitted_by, previous_status, new_status, remarks, created_at, updated_at) VALUES (?,?,?,?,?,?,?)',
      [$id, $room['created_by'], $room['approval_status'], 'pending', 'Submitted for approval', now(), now()]);
    log_activity('room.submitted', 'Room', $id, "Submitted room {$room['room_number']} for approval");
    notify_admins('room.submitted', 'Room waiting for approval', "Room {$room['room_number']} was submitted by " . full_name($me) . '.', 'approvals.php');
    return [true, "Room {$room['room_number']} was sent to the administrator for approval."];
}

/* ---- Maintenance / complaints ----
   Tenants report problems (water, electrical, facilities); the landlord works them to resolution. */
function maintenance_categories(): array {
    return ['water' => 'Water supply', 'electrical' => 'Electrical', 'facility' => 'Broken facility', 'cleanliness' => 'Cleanliness', 'other' => 'Other'];
}
function maintenance_create(array $in): array {
    $me = auth();
    if ($me['role'] !== 'tenant') { return [false, 'Only tenants can report problems.']; }
    $roomId = (int) ($in['room_id'] ?? 0);
    $cat = (string) ($in['category'] ?? '');
    $desc = trim((string) ($in['description'] ?? ''));
    if (!array_key_exists($cat, maintenance_categories())) { return [false, 'Choose a problem category.']; }
    if (mb_strlen($desc) < 10) { return [false, 'Describe the problem in at least 10 characters.']; }
    $b = row("SELECT id FROM bookings WHERE tenant_id = ? AND room_id = ? AND status IN ('confirmed','completed') ORDER BY id DESC LIMIT 1", [$me['id'], $roomId]);
    if (!$b) { return [false, 'You can only report problems for a room you are renting or have rented.']; }
    q('INSERT INTO maintenance_requests (tenant_id, room_id, booking_id, category, description, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
      [$me['id'], $roomId, (int) $b['id'], $cat, $desc, 'open', now(), now()]);
    $rid = insert_id();
    $rn = (string) val('SELECT room_number FROM rooms WHERE id = ?', [$roomId]);
    notify((int) val('SELECT created_by FROM rooms WHERE id = ?', [$roomId]), 'maintenance.new', 'New problem report', full_name($me) . " reported a $cat problem in room $rn: " . mb_strcut($desc, 0, 120), 'maintenance.php');
    log_activity('maintenance.reported', 'Maintenance', $rid, "Reported a $cat problem in room $rn");
    return [true, 'Report sent to the landlord — you will be notified when it is handled.'];
}
function maintenance_update(int $id, string $action, string $notes): array {
    $me = auth();
    $m = row('SELECT m.*, r.room_number, r.created_by AS landlord_id FROM maintenance_requests m JOIN rooms r ON r.id = m.room_id WHERE m.id = ?', [$id]);
    if (!$m) { return [false, 'Report not found.']; }
    $isManager = $me['role'] === 'administrator' || ($me['role'] === 'landlord' && (int) $m['landlord_id'] === (int) $me['id']);
    if (!$isManager) { return [false, 'Only the landlord or an administrator can update a report.']; }
    if ($action === 'start') {
        if ($m['status'] !== 'open') { return [false, 'Only open reports can be marked as being worked on.']; }
        q('UPDATE maintenance_requests SET status = ?, updated_at = ? WHERE id = ?', ['in_progress', now(), $id]);
        notify((int) $m['tenant_id'], 'maintenance.update', 'Report being worked on', "Your report for room {$m['room_number']} is being worked on.", 'maintenance.php');
        log_activity('maintenance.started', 'Maintenance', $id, "Started working on report #$id (room {$m['room_number']})");
        return [true, 'Marked as being worked on.'];
    }
    if ($action === 'resolve') {
        if ($m['status'] === 'resolved') { return [false, 'This report is already resolved.']; }
        if (trim($notes) === '') { return [false, 'Write a short resolution note for the tenant.']; }
        q('UPDATE maintenance_requests SET status = ?, resolution_notes = ?, resolved_by = ?, resolved_at = ?, updated_at = ? WHERE id = ?', ['resolved', trim($notes), $me['id'], now(), now(), $id]);
        notify((int) $m['tenant_id'], 'maintenance.resolved', 'Report resolved', "Your report for room {$m['room_number']} was resolved: " . trim($notes), 'maintenance.php');
        log_activity('maintenance.resolved', 'Maintenance', $id, "Resolved report #$id (room {$m['room_number']})");
        return [true, 'Report resolved — the tenant has been notified.'];
    }
    return [false, 'Unknown action.'];
}
