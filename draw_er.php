<?php
// One-off: render the StayHaus EER diagram to PNG with GD, then delete this file.
$im = imagecreatetruecolor(2400, 920);
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $white);

$font = 'C:\\Windows\\Fonts\\arial.ttf';
$fontB = 'C:\\Windows\\Fonts\\arialbd.ttf';
$colors = [
    'people'   => [77, 101, 255],
    'rooms'    => [160, 77, 255],
    'amenity'  => [235, 50, 171],
    'book'     => [15, 138, 95],
    'support'  => [100, 116, 139],
];
$dark  = imagecolorallocate($im, 30, 34, 44);
$gray  = imagecolorallocate($im, 110, 118, 132);
$line  = imagecolorallocate($im, 90, 98, 115);
$rowln = imagecolorallocate($im, 226, 230, 238);

$groups = [
    'people' => ['x' => 30,   'tables' => ['ROLES', 'USERS', 'ACTIVITY_LOGS']],
    'rooms'  => ['x' => 490,  'tables' => ['ROOM_TYPES', 'ROOMS', 'ROOM_IMAGES']],
    'amenity'=> ['x' => 950,  'tables' => ['AMENITIES', 'AMENITY_ROOM']],
    'book'   => ['x' => 1410, 'tables' => ['BOOKINGS', 'PAYMENTS']],
    'support'=> ['x' => 1870, 'tables' => ['ROOM_APPROVAL_LOGS', 'NOTIFICATIONS', 'SETTINGS', 'PASSWORD_RESET_TOKENS']],
];

// name => [group, y, attributes: [key, name, type]]
$T = [
    'ROLES' => ['people', 60, [
        ['PK', 'id', 'int'], ['', 'name', 'Administrator / Landlord / Tenant'], ['', 'slug', 'varchar'],
    ]],
    'USERS' => ['people', 240, [
        ['PK', 'id', 'int'], ['FK', 'role_id', 'roles'], ['', 'first_name', 'varchar'], ['', 'last_name', 'varchar'],
        ['UK', 'email', 'varchar'], ['', 'phone', 'varchar'], ['', 'password', 'hashed'], ['', 'status', 'active/inactive/suspended'],
    ]],
    'ACTIVITY_LOGS' => ['people', 620, [
        ['PK', 'id', 'int'], ['FK', 'user_id', 'users'], ['', 'action', 'varchar'], ['', 'entity_type / entity_id', ''],
        ['', 'description', 'text'], ['', 'ip_address', 'varchar'],
    ]],
    'ROOM_TYPES' => ['rooms', 60, [
        ['PK', 'id', 'int'], ['', 'name', 'Single / Shared / Studio'], ['', 'default_capacity', 'int'], ['', 'default_monthly_rate', 'decimal'],
    ]],
    'ROOMS' => ['rooms', 240, [
        ['PK', 'id', 'int'], ['FK', 'room_type_id', 'room_types'], ['FK', 'created_by', 'users (owner landlord)'],
        ['FK', 'approved_by', 'users (admin)'], ['UK', 'room_number', 'e.g. B-101, A-306'], ['', 'floor_number', 'smallint'],
        ['', 'title / description', ''], ['', 'occupancy_type', 'private / shared'], ['', 'capacity', 'max pax'],
        ['', 'monthly_rate', 'decimal'], ['', 'security_deposit', 'decimal'], ['', 'approval_status', 'draft/pending/approved/rejected'],
        ['', 'room_status', 'available/occupied/unavailable'], ['', 'is_published', 'boolean'], ['', 'deleted_at', 'soft delete'],
    ]],
    'ROOM_IMAGES' => ['rooms', 720, [
        ['PK', 'id', 'int'], ['FK', 'room_id', 'rooms'], ['', 'image_path', 'varchar'], ['', 'is_primary / sort_order', ''],
    ]],
    'AMENITIES' => ['amenity', 60, [
        ['PK', 'id', 'int'], ['UK', 'name', 'Wi-Fi, Aircon, ...'], ['', 'category', 'comfort/security/study/utility'],
    ]],
    'AMENITY_ROOM' => ['amenity', 260, [
        ['PK', 'id', 'int'], ['FK', 'room_id', 'rooms'], ['FK', 'amenity_id', 'amenities'],
        ['', 'price', '0 = included, >0 extra/mo'], ['', 'note', 'free-text label'],
    ]],
    'BOOKINGS' => ['book', 60, [
        ['PK', 'id', 'int'], ['UK', 'booking_reference', 'BK-...'], ['FK', 'tenant_id', 'users'], ['FK', 'room_id', 'rooms'],
        ['FK', 'processed_by', 'users (staff)'], ['', 'start_date / end_date', 'date'], ['', 'number_of_occupants', 'int'],
        ['', 'monthly_rate_at_booking', 'decimal'], ['', 'security_deposit_at_booking', 'decimal'], ['', 'amenities_total', 'decimal'],
        ['', 'total_amount', 'decimal'], ['', 'status', 'pending/confirmed/rejected/cancelled/completed'],
    ]],
    'PAYMENTS' => ['book', 500, [
        ['PK', 'id', 'int'], ['FK', 'booking_id', 'bookings'], ['FK', 'tenant_id', 'users'], ['FK', 'processed_by', 'users'],
        ['UK', 'transaction_reference', 'varchar'], ['', 'amount', 'decimal'], ['', 'payment_method', 'cash/gcash/bank/other'],
        ['', 'payment_type', 'deposit/rent/amenity/penalty'], ['', 'status', 'pending/paid/failed/refunded'],
    ]],
    'ROOM_APPROVAL_LOGS' => ['support', 60, [
        ['PK', 'id', 'int'], ['FK', 'room_id', 'rooms'], ['FK', 'submitted_by', 'users'], ['FK', 'reviewed_by', 'users'],
        ['', 'previous_status / new_status', ''], ['', 'remarks', 'text'],
    ]],
    'NOTIFICATIONS' => ['support', 340, [
        ['PK', 'id', 'char36'], ['', 'type', 'varchar'], ['', 'notifiable_type / notifiable_id', ''], ['', 'data / read_at', ''],
    ]],
    'SETTINGS' => ['support', 560, [
        ['PK', 'id', 'int'], ['', 'key / value', ''], ['', 'description', 'varchar'],
    ]],
    'PASSWORD_RESET_TOKENS' => ['support', 740, [
        ['PK', 'email', 'varchar'], ['', 'token', 'varchar'],
    ]],
];

const BW = 470; const HH = 40; const RH = 26;

// compute geometry
$G = [];
foreach ($T as $name => $g) { $G[$name] = ['x' => $groups[$g[0]]['x'], 'y' => $g[1], 'w' => BW, 'h' => HH + count($g[2]) * RH + 10, 'grp' => $g[0]]; }

// ---- relationships first (drawn under the boxes) ----
imagesetthickness($im, 2);
function anchor(array $b, string $side, float $frac): array {
    return match ($side) {
        'r' => [$b['x'] + $b['w'], $b['y'] + 20 + (int) (($b['h'] - 40) * $frac)],
        'l' => [$b['x'], $b['y'] + 20 + (int) (($b['h'] - 40) * $frac)],
        't' => [$b['x'] + (int) ($b['w'] * $frac), $b['y']],
        'b' => [$b['x'] + (int) ($b['w'] * $frac), $b['y'] + $b['h']],
    };
}
$rels = [
    ['ROLES', 'r', .5, 'USERS', 'l', .3, '1:N'],
    ['USERS', 'r', .5, 'ROOMS', 'l', .25, '1:N creates'],
    ['USERS', 'r', .8, 'ROOMS', 'l', .55, '1:N approves'],
    ['ROOM_TYPES', 'b', .5, 'ROOMS', 't', .25, '1:N'],
    ['ROOMS', 'r', .3, 'ROOM_IMAGES', 'l', .5, '1:N'],
    ['ROOMS', 'r', .8, 'AMENITY_ROOM', 'l', .4, '1:N'],
    ['AMENITIES', 'b', .5, 'AMENITY_ROOM', 't', .5, '1:N'],
    ['USERS', 'b', .5, 'ACTIVITY_LOGS', 't', .5, '1:N'],
    ['ROOMS', 'b', .5, 'ROOM_IMAGES', 't', .3, ''],
    ['AMENITY_ROOM', 'r', .5, 'ROOM_APPROVAL_LOGS', 'l', .5, ''],
    ['USERS', 'r', .2, 'ROOM_APPROVAL_LOGS', 'l', .2, ''],
];
// note: bookings/payment rels drawn on right side columns
$rels2 = [
    ['USERS', 'r', .1, 'BOOKINGS', 'l', .25, '1:N tenant'],
    ['ROOMS', 'r', .5, 'BOOKINGS', 'l', .6, '1:N'],
    ['USERS', 'r', .35, 'BOOKINGS', 'l', .85, '1:N processes'],
    ['BOOKINGS', 'b', .5, 'PAYMENTS', 't', .5, '1:N'],
    ['USERS', 't', .9, 'PAYMENTS', 'l', .2, '1:N pays'],
];
foreach (array_merge($rels, $rels2) as $r) {
    [$a, $b] = [$G[$r[0]], $G[$r[3]]];
    [$x1, $y1] = anchor($a, $r[1], $r[2]);
    [$x2, $y2] = anchor($b, $r[4], $r[5]);
    imageline($im, $x1, $y1, $x2, $y2, $line);
    if ($r[6] !== '') {
        $mx = (int) (($x1 + $x2) / 2); $my = (int) (($y1 + $y2) / 2);
        imagettftext($im, 11, 0, $mx + 4, $my - 4, $gray, $font, $r[6]);
    }
}

// ---- boxes on top ----
foreach ($T as $name => $g) {
    $geo = $G[$name];
    $c = $colors[$g[0]];
    $hdr = imagecolorallocate($im, ...$c);
    imagefilledrectangle($im, $geo['x'], $geo['y'], $geo['x'] + $geo['w'], $geo['y'] + $geo['h'], $white);
    imagerectangle($im, $geo['x'], $geo['y'], $geo['x'] + $geo['w'], $geo['y'] + $geo['h'], $dark);
    imagefilledrectangle($im, $geo['x'], $geo['y'], $geo['x'] + $geo['w'], $geo['y'] + HH, $hdr);
    imagerectangle($im, $geo['x'], $geo['y'], $geo['x'] + $geo['w'], $geo['y'] + HH, $dark);
    imagettftext($im, 15, 0, $geo['x'] + 12, $geo['y'] + 27, $white, $fontB, $name);
    $ry = $geo['y'] + HH;
    foreach ($g[2] as $i => $attr) {
        $ry += RH;
        if ($i > 0) imageline($im, $geo['x'], $ry - RH + RH, $geo['x'] + $geo['w'], $ry - RH + RH, $rowln);
        $tag = $attr[0]; $lineC = $tag === 'PK' ? $hdr : ($tag === 'FK' ? imagecolorallocate($im, 200, 90, 30) : $dark);
        $prefix = $tag !== '' ? "$tag " : '';
        imagettftext($im, 12, 0, $geo['x'] + 10, $ry - 7, $lineC, $tag === 'PK' ? $fontB : $font, $prefix . $attr[1]);
        if ($attr[2] !== '') imagettftext($im, 10, 0, $geo['x'] + $geo['w'] - 10 - (int) (strlen($attr[2]) * 5.4), $ry - 7, $gray, $font, $attr[2]);
    }
}

imagettftext($im, 18, 0, 30, 905, $dark, $fontB, 'StayHaus - Enhanced Entity Relationship Diagram (14 tables)');
imagepng($im, 'database/stayhaus-er.png');
echo "written: " . (GetSize('database/stayhaus-er.png')) . "\n";
function GetSize(string $f): int { return filesize($f); }
