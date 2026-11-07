<?php
// expects $title, $nav (active key). User must already be authenticated.
$me = auth();
$role = $me['role'];
$links = [['dashboard.php', '📊', 'Dashboard', 'dashboard']];
if ($role === 'administrator') {
    $pend = (int) val("SELECT COUNT(*) FROM rooms WHERE approval_status = 'pending' AND deleted_at IS NULL");
    $links = array_merge($links, [
        ['rooms.php', '🛏️', 'All Rooms', 'rooms'],
        ['approvals.php', '✅', 'Approvals' . ($pend ? " ($pend)" : ''), 'approvals'],
        ['bookings.php', '📅', 'Bookings', 'bookings'],
        ['maintenance.php', '🛠️', 'Maintenance', 'maintenance'],
        ['reports.php', '📈', 'Reports', 'reports'],
        ['users.php', '👥', 'Users', 'users'],
        ['room_types.php', '🏷️', 'Room Types', 'room_types'],
        ['amenities.php', '✨', 'Amenities', 'amenities'],
        ['logs.php', '🧾', 'Activity Logs', 'logs'],
        ['settings.php', '⚙️', 'Settings', 'settings'],
    ]);
} elseif ($role === 'landlord') {
    $links = array_merge($links, [
        ['rooms.php', '🛏️', 'My Rooms', 'rooms'],
        ['bookings.php', '📅', 'Booking Requests', 'bookings'],
        ['payments.php', '💳', 'Billing', 'payments'],
        ['maintenance.php', '🛠️', 'Maintenance', 'maintenance'],
        ['reports.php', '📈', 'Reports', 'reports'],
    ]);
} else {
    $links = array_merge($links, [
        ['bookings.php', '📅', 'My Bookings', 'bookings'],
        ['maintenance.php', '🛠️', 'Report a Problem', 'maintenance'],
    ]);
}
$links[] = ['notifications.php', '🔔', 'Notifications' . (($n = unread_count()) ? " ($n)" : ''), 'notifications'];
$links[] = ['profile.php', '🙂', 'My Profile', 'profile'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · <?= e(setting('site_name', 'StayHaus')) ?></title>
<link rel="stylesheet" href="<?= BASE ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="<?= BASE ?>index.php" class="brand">
            <span class="brand-mark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 11L12 3L21 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 10V20H19V10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <?= e(setting('site_name', 'StayHaus')) ?>
        </a>
        <nav class="side-links">
            <?php foreach ($links as $l): ?>
                <a href="<?= $l[0] ?>" class="<?= ($nav ?? '') === $l[3] ? 'active' : '' ?>"><?= $l[1] ?> <?= e($l[2]) ?></a>
            <?php endforeach; ?>
            <a href="<?= BASE ?>rooms.php">🔎 Browse Public Site</a>
        </nav>
        <div class="side-foot">
            <span class="tiny">Logged in as</span>
            <strong class="side-name"><?= e(full_name($me)) ?></strong>
            <span class="pill pill-<?= e($role) ?> side-role"><?= e($me['role_name']) ?></span>
            <a href="<?= BASE ?>logout.php" class="side-logout">Log out</a>
        </div>
    </aside>
    <main class="admin-main">
        <div class="mobile-nav"><?php foreach ($links as $l): ?><a href="<?= $l[0] ?>" class="<?= ($nav ?? '') === $l[3] ? 'on' : '' ?>"><?= $l[1] ?> <?= e($l[2]) ?></a><?php endforeach; ?><a href="<?= BASE ?>logout.php">🚪 Log out</a></div>
        <?= flash_html() ?>
