<?php
/**
 * Sample data. Runs once, the first time the app opens.
 * Logins:  admin@stayhaus.test / admin123
 *          landlord@stayhaus.test / landlord123   (also landlord2@stayhaus.test)
 *          bea@example.com / tenant123            (also jero@, nica@, carlo@)
 */
function seed_database(PDO $pdo): void
{
    $t = date('Y-m-d H:i:s');
    $day = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? "+$n" : "$n") . ' days'));
    $ins = function (string $table, array $data) use ($pdo): int {
        $cols = array_keys($data);
        $pdo->prepare("INSERT INTO $table (" . implode(',', array_map(fn($c) => $c === 'key' ? '"key"' : $c, $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($data));
        return (int) $pdo->lastInsertId();
    };
    $ts = ['created_at' => $t, 'updated_at' => $t];

    // Roles
    foreach ([['Administrator', 'administrator', 'Full access: users, approvals, reports, settings'],
              ['Landlord', 'landlord', 'Creates room listings and manages booking requests'],
              ['Tenant', 'tenant', 'Browses rooms, books and pays']] as $r) {
        $ins('roles', ['name' => $r[0], 'slug' => $r[1], 'description' => $r[2]] + $ts);
    }

    // Users: [role_id, first, last, email, phone, password, address]
    $users = [
        [1, 'Admin', 'Cruz', 'admin@stayhaus.test', '0917-000-0001', 'admin123', '2F StayHaus Hub, 245 España Boulevard, Sampaloc, Manila, 1008'],
        [2, 'Marco', 'Reyes', 'landlord@stayhaus.test', '0917-000-0002', 'landlord123', '19 Katipunan Avenue, Loyola Heights, Quezon City, 1108'],
        [2, 'Lorna', 'Dizon', 'landlord2@stayhaus.test', '0917-000-0003', 'landlord123', '78 Mabini Street, Ermita, Manila, 1000'],
        [3, 'Bea', 'Santos', 'bea@example.com', '0917-111-2222', 'tenant123', '12 F. Torres Street, Project 4, Quezon City, 1109'],
        [3, 'Jero', 'Villanueva', 'jero@example.com', '0917-333-4444', 'tenant123', '335 M. Recto Avenue, San Miguel, Manila, 1005'],
        [3, 'Nica', 'Domingo', 'nica@example.com', '0917-555-6666', 'tenant123', '8 Honradez Street, Sampaloc, Manila, 1008'],
        [3, 'Carlo', 'Mendoza', 'carlo@example.com', '0917-777-8888', 'tenant123', '155 Kamias Road, Quezon City, 1102'],
        [3, 'Juan', 'Perez', 'juan@example.com', '0917-999-0000', 'tenant123', '52 Cristo Rey Street, Tandang Sora, Quezon City, 1116'],
    ];
    foreach ($users as $u) {
        $ins('users', ['role_id' => $u[0], 'first_name' => $u[1], 'last_name' => $u[2], 'email' => $u[3], 'phone' => $u[4], 'address' => $u[6],
                       'password' => password_hash($u[5], PASSWORD_DEFAULT), 'status' => 'active', 'email_verified_at' => $t] + $ts);
    }
    // ids: 1 admin, 2 Marco, 3 Lorna, 4 Bea, 5 Jero, 6 Nica, 7 Carlo, 8 Juan
    // ids: 1 admin, 2 Marco, 3 Lorna, 4 Bea, 5 Jero, 6 Nica, 7 Carlo

    // Room types
    $types = [['Single', 'One-person room for students or workers', 1, 3500], ['Shared', 'Room shared by several boarders', 3, 1800], ['Studio', 'Private studio-type room with more space', 2, 6000]];
    foreach ($types as $x) { $ins('room_types', ['name' => $x[0], 'description' => $x[1], 'default_capacity' => $x[2], 'default_monthly_rate' => $x[3]] + $ts); }

    // Amenities
    $am = [['Wi-Fi', 'utility'], ['Aircon', 'comfort'], ['Study Desk', 'study'], ['Laundry Area', 'utility'], ['Parking', 'utility']];
    foreach ($am as $a) { $ins('amenities', ['name' => $a[0], 'category' => $a[1], 'description' => $a[0] . ' available for boarders'] + $ts); }
    // amenity ids: 1 Wi-Fi, 2 Aircon, 3 Study Desk, 4 Laundry, 5 Parking

    // Rooms: [type, landlord, number, floor, title, desc, occupancy, cap, rate, deposit, approval, images, amenities(id=>price)]
    // amenity ids: 1 Wi-Fi, 2 Aircon, 3 Study Desk, 4 Laundry Area, 5 Parking
    $rooms = [
        [1, 2, 'B-101', 1, 'Cozy Single Near Campus', 'Quiet single room with a study desk, ten minutes from the university gate.', 'private', 1, 3500, 3500, 'approved', [1, 3], [1 => 0, 3 => 0, 2 => 500]],
        [2, 2, 'B-102', 1, 'Budget Shared Room (3 pax)', 'Triple-sharing room with bunk beds and personal cabinets. Good for students on a budget.', 'shared', 3, 1800, 1800, 'approved', [2, 10], [1 => 0, 4 => 150]],
        [3, 2, 'B-103', 2, 'Deluxe Studio with Balcony', 'Spacious studio with its own bathroom, small kitchenette and balcony view.', 'private', 2, 6000, 6000, 'approved', [3, 11, 7], [1 => 0, 2 => 0, 5 => 300]],
        [2, 2, 'B-104', 2, 'Shared Room Near the Kitchen', 'Shared room beside the common kitchen. Waiting for admin review.', 'shared', 3, 1900, 1900, 'pending', [4], [1 => 0]],
        [1, 2, 'B-105', 2, 'Compact Single (draft)', 'Still being prepared by the landlord.', 'private', 1, 3200, 3200, 'draft', [], []],
        [1, 3, 'L-201', 2, 'Bright Single with Aircon', 'Corner single room with lots of natural light and a private study corner.', 'private', 1, 3800, 3800, 'approved', [6, 8], [1 => 0, 3 => 0, 2 => 400]],
        [3, 3, 'L-202', 3, 'Premium Studio Loft', 'Top-floor studio with a queen bed, kitchenette and fast Wi-Fi.', 'private', 2, 6500, 6500, 'approved', [12, 5], [1 => 0, 2 => 0, 3 => 0]],
        [2, 3, 'L-203', 3, 'Shared Room (rejected sample)', 'Shared room listing that needs better photos.', 'shared', 3, 2000, 2000, 'rejected', [9], [1 => 0]],
    ];
    foreach ($rooms as $i => $r) {
        $approved = $r[10] === 'approved';
        $id = $ins('rooms', ['room_type_id' => $r[0], 'created_by' => $r[1], 'room_number' => $r[2], 'floor_number' => $r[3], 'title' => $r[4],
            'description' => $r[5], 'occupancy_type' => $r[6], 'capacity' => $r[7], 'monthly_rate' => $r[8], 'security_deposit' => $r[9],
            'approval_status' => $r[10], 'room_status' => $approved ? 'available' : 'unavailable',
            'rejection_reason' => $r[10] === 'rejected' ? 'Photos do not show the real room. Please upload clearer pictures and resubmit.' : null,
            'approved_by' => $approved ? 1 : null, 'approved_at' => $approved ? $t : null, 'is_published' => $approved ? 1 : 0] + $ts);
        foreach ($r[11] as $n => $imgNo) {
            $ins('room_images', ['room_id' => $id, 'image_path' => "rooms/room$imgNo.svg", 'alt_text' => $r[4], 'is_primary' => $n === 0 ? 1 : 0, 'sort_order' => $n] + $ts);
        }
        foreach ($r[12] as $amId => $price) { $ins('amenity_room', ['room_id' => $id, 'amenity_id' => $amId, 'price' => $price] + $ts); }
        // approval history
        if ($r[10] !== 'draft') {
            $ins('room_approval_logs', ['room_id' => $id, 'submitted_by' => $r[1], 'previous_status' => 'draft', 'new_status' => 'pending', 'remarks' => 'Submitted for approval'] + $ts);
        }
        if ($approved) {
            $ins('room_approval_logs', ['room_id' => $id, 'submitted_by' => $r[1], 'reviewed_by' => 1, 'previous_status' => 'pending', 'new_status' => 'approved', 'remarks' => 'Looks good', 'reviewed_at' => $t] + $ts);
        } elseif ($r[10] === 'rejected') {
            $ins('room_approval_logs', ['room_id' => $id, 'submitted_by' => $r[1], 'reviewed_by' => 1, 'previous_status' => 'pending', 'new_status' => 'rejected', 'remarks' => 'Photos do not show the real room.', 'reviewed_at' => $t] + $ts);
        }
    }
    // room ids: 1=B-101 2=B-102 3=B-103 4=B-104 5=B-105 6=L-201 7=L-202 8=L-203

    // Bookings: [ref, tenant, room, start, end, occupants, status, notes, processor, rented amenity ids (null = whole room as it was)]
    $bookings = [
        ['BK-SEED-0001', 4, 1, $day(-60), $day(120), 1, 'confirmed', 'Please prepare the room before I move in.', 2, [1, 2, 3]],
        ['BK-SEED-0002', 5, 2, $day(-30), $day(150), 1, 'confirmed', null, 2, [1, 4]],
        ['BK-SEED-0003', 6, 7, $day(14), $day(194), 2, 'pending', 'We are a couple, both working.', null, [1, 2, 3]],
        ['BK-SEED-0004', 7, 6, $day(-200), $day(-20), 1, 'completed', null, 3, [1, 2, 3]],
        ['BK-SEED-0005', 7, 3, $day(30), $day(210), 1, 'cancelled', 'Changed plans', null, null],
        ['BK-SEED-0006', 6, 3, $day(10), $day(100), 2, 'rejected', null, 2, null],
        ['BK-SEED-0007', 8, 2, $day(-30), $day(150), 1, 'confirmed', null, 2, [1]],
    ];
    $bk = [];
    foreach ($bookings as $b) {
        $room = $pdo->query("SELECT * FROM rooms WHERE id = {$b[2]}")->fetch();
        if ($b[9] === null) { $extra = $amTotal($b[2]); }
        else {
            $extra = 0.0;
            foreach ($b[9] as $aid) {
                $p = $pdo->prepare('SELECT price FROM amenity_room WHERE room_id = ? AND amenity_id = ? AND price > 0');
                $p->execute([$b[2], $aid]); $extra += (float) ($p->fetchColumn() ?: 0);
            }
        }
        $months = max(1, (int) ceil((strtotime($b[4]) - strtotime($b[3])) / 86400 / 30));
        $total = ($room['monthly_rate'] + $extra) * $months + $room['security_deposit'];
        $bk[$b[0]] = $ins('bookings', ['booking_reference' => $b[0], 'tenant_id' => $b[1], 'room_id' => $b[2], 'start_date' => $b[3], 'end_date' => $b[4],
            'number_of_occupants' => $b[5], 'monthly_rate_at_booking' => $room['monthly_rate'], 'daily_rate_at_booking' => round($room['monthly_rate'] / 30, 2),
            'security_deposit_at_booking' => $room['security_deposit'], 'amenities_total' => $extra, 'amenity_ids' => $b[9] === null ? null : json_encode(array_values($b[9])), 'total_amount' => $total, 'status' => $b[6],
            'special_requests' => $b[7], 'processed_by' => $b[8],
            'decision_notes' => $b[6] === 'rejected' ? 'Room already reserved for other dates.' : null] + $ts);
    }

    // Payments: [booking ref, tenant, by, amount, method, type, status, daysAgo, notes]
    $pays = [
        ['BK-SEED-0001', 4, 2, 3500, 'cash', 'deposit', 'paid', 60, 'Deposit received'],
        ['BK-SEED-0001', 4, 2, 4000, 'gcash', 'rent', 'paid', 55, 'Rent month 1 (incl. aircon)'],
        ['BK-SEED-0001', 4, 2, 4000, 'gcash', 'rent', 'paid', 25, 'Rent month 2'],
        ['BK-SEED-0001', 4, 2, 4000, 'gcash', 'rent', 'pending', 0, 'Rent month 3 - waiting for proof'],
        ['BK-SEED-0002', 5, 2, 1800, 'bank_transfer', 'deposit', 'paid', 30, null],
        ['BK-SEED-0002', 5, 2, 1950, 'cash', 'rent', 'paid', 28, 'Rent month 1 (incl. laundry)'],
        ['BK-SEED-0004', 7, 3, 3800, 'cash', 'deposit', 'paid', 200, null],
        ['BK-SEED-0004', 7, 3, 4200, 'cash', 'rent', 'paid', 170, null],
        ['BK-SEED-0004', 7, 3, 3800, 'cash', 'deposit', 'refunded', 20, 'Deposit returned after move-out'],
    ];
    foreach ($pays as $i => $p) {
        $paidAt = $p[6] === 'pending' ? null : date('Y-m-d H:i:s', strtotime("-{$p[7]} days"));
        $ins('payments', ['booking_id' => $bk[$p[0]], 'tenant_id' => $p[1], 'processed_by' => $p[2], 'transaction_reference' => 'PAY-SEED-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            'amount' => $p[3], 'payment_method' => $p[4], 'payment_type' => $p[5], 'status' => $p[6], 'paid_at' => $paidAt, 'notes' => $p[8]] + $ts);
    }

    // Notifications
    $note = function (int $uid, string $type, string $title, string $msg, ?string $url, bool $read) use ($ins, $t) {
        $ins('notifications', ['id' => sprintf('%08x-0000-4000-8000-%012x', random_int(0, PHP_INT_MAX >> 40), random_int(0, PHP_INT_MAX >> 20)), 'type' => $type, 'notifiable_type' => 'User', 'notifiable_id' => $uid,
            'data' => json_encode(['title' => $title, 'message' => $msg, 'url' => $url]), 'read_at' => $read ? $t : null, 'created_at' => $t, 'updated_at' => $t]);
    };
    $note(1, 'room.submitted', 'Room waiting for approval', 'Room B-104 was submitted by Marco Reyes.', 'approvals.php', false);
    $note(3, 'booking.requested', 'New booking request', 'A tenant requested room L-202.', 'bookings.php', false);
    $note(6, 'booking.pending', 'Booking request sent', 'Your request for L-202 is waiting for the landlord.', 'bookings.php', false);
    $note(2, 'room.rejected', 'Room listing rejected', 'L-203 was rejected: photos do not show the real room.', 'rooms.php', true);
    $note(4, 'booking.confirmed', 'Booking confirmed', 'Your booking BK-SEED-0001 for B-101 is confirmed.', 'bookings.php', true);

    // Activity logs
    foreach ([[1, 'room.approved', 'Room', 1, 'Approved room B-101'], [2, 'room.submitted', 'Room', 4, 'Submitted room B-104 for approval'],
              [2, 'booking.confirmed', 'Booking', $bk['BK-SEED-0001'], 'Confirmed booking BK-SEED-0001'], [1, 'room.rejected', 'Room', 8, 'Rejected room L-203'],
              [2, 'payment.recorded', 'Payment', 1, 'Recorded deposit payment for BK-SEED-0001']] as $l) {
        $ins('activity_logs', ['user_id' => $l[0], 'action' => $l[1], 'entity_type' => $l[2], 'entity_id' => $l[3], 'description' => $l[4], 'ip_address' => '127.0.0.1', 'created_at' => $t]);
    }

    // Settings
    foreach ([['site_name', 'StayHaus', 'Name shown in the header and page titles'], ['currency_symbol', '₱', 'Symbol shown before money values'],
              ['contact_email', 'hello@stayhaus.test', 'Public contact email'], ['site_address', '2F StayHaus Hub, 245 España Boulevard, Sampaloc, Manila, 1008', 'System address shown in the public footer'],
              ['min_booking_days', '30', 'Shortest rental period allowed (days)'],
              ['penalty_percent', '5', 'Late-rent penalty: percent of the monthly amount charged when rent is paid after the due date'],
              ['penalty_grace_days', '0', 'Days past the due date before the late penalty applies (0 = any day late)'],
              ['rooms_per_page', '9', 'Rooms shown per page in public search']] as $s) {
        $ins('settings', ['key' => $s[0], 'value' => $s[1], 'description' => $s[2]] + $ts);
    }
}
