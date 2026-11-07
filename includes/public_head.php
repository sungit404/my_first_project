<?php
// expects $title (string). Optional $activePage.
$activePage = $activePage ?? '';
$me = auth();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Home') ?> · <?= e(setting('site_name', 'StayHaus')) ?></title>
<link rel="stylesheet" href="<?= BASE ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<nav class="navbar">
    <div class="container navbar-inner">
        <a href="<?= BASE ?>index.php" class="brand">
            <span class="brand-mark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 11L12 3L21 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 10V20H19V10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <?= e(setting('site_name', 'StayHaus')) ?>
        </a>
        <div class="nav-links">
            <a href="<?= BASE ?>index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Home</a>
            <a href="<?= BASE ?>rooms.php" class="<?= $activePage === 'rooms' ? 'active' : '' ?>">Find a Room</a>
            <?php if ($activePage === 'home'): ?>
                <a href="<?= BASE ?>index.php#features">Why StayHaus</a>
                <a href="<?= BASE ?>index.php#how">How it works</a>
                <a href="<?= BASE ?>index.php#faq">FAQ</a>
            <?php endif; ?>
            <?php if ($me): ?><a href="<?= BASE ?>panel/dashboard.php">My Dashboard</a><?php endif; ?>
        </div>
        <div class="nav-cta">
            <?php if ($me): ?>
                <a href="<?= BASE ?>panel/notifications.php" class="btn btn-ghost btn-sm">🔔<?= ($n = unread_count()) ? ' <span class="dot">' . $n . '</span>' : '' ?></a>
                <a href="<?= BASE ?>logout.php" class="btn btn-ghost btn-sm">Log out</a>
            <?php else: ?>
                <a href="<?= BASE ?>login.php" class="btn btn-ghost btn-sm">Log in</a>
                <a href="<?= BASE ?>register.php" class="btn btn-primary btn-sm">Get Started</a>
            <?php endif; ?>
            <button type="button" class="nav-burger" id="navBurger" aria-label="Menu" aria-expanded="false">☰</button>
        </div>
    </div>
</nav>
