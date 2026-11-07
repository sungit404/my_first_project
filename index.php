<?php
require __DIR__ . '/includes/bootstrap.php';
$title = 'Home'; $activePage = 'home';

$vis = visible_room_sql();
$totalRooms = (int) val("SELECT COUNT(*) FROM rooms r WHERE $vis");
$availCount = (int) val("SELECT COUNT(*) FROM rooms r WHERE $vis AND r.room_status = 'available'");
$landlords = (int) val("SELECT COUNT(DISTINCT created_by) FROM rooms r WHERE $vis");
$types = rows('SELECT name FROM room_types ORDER BY name');

/* ---- Featured rooms: latest approved rooms for the simple home grid ---- */
$featured = rows('SELECT ' . ROOM_SELECT . " WHERE $vis ORDER BY (r.room_status = 'available') DESC, r.id DESC LIMIT 6");

require __DIR__ . '/includes/public_head.php';
?>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="container">
        <div class="eyebrow">Verified listings only</div>
        <h1>Rooms that feel like home, <em>priced like sense.</em></h1>
        <p>Search boarding rooms by price, type and amenities. Before you send a booking request, see the true monthly cost — extras included, nothing hidden.</p>
        <div class="hero-actions">
            <a href="rooms.php" class="btn btn-primary">Browse rooms →</a>
            <a href="#how" class="btn btn-ghost">How it works</a>
        </div>
        <form action="rooms.php" method="get" class="hero-search">
            <div class="field"><label>Room number or keyword</label><input type="text" name="q" placeholder="e.g. studio, B-101"></div>
            <div class="field"><label>Room type</label>
                <select name="type"><option value="">Any type</option><?php foreach ($types as $t): ?><option><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Max budget</label><input type="number" name="max" min="0" placeholder="PHP / month"></div>
            <button class="btn btn-primary">Search</button>
        </form>
        <div class="stats-row">
            <div class="stat-item"><div class="num"><?= $totalRooms ?></div><div class="label">Approved Rooms</div></div>
            <div class="stat-item"><div class="num"><?= $availCount ?></div><div class="label">Available Now</div></div>
            <div class="stat-item"><div class="num"><?= $landlords ?></div><div class="label">Landlords</div></div>
        </div>
    </div>
</section>

<!-- ============ FEATURED ROOMS ============ -->
<section class="section" id="features" style="padding-bottom:40px">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">Rooms</div>
                <h2>Featured rooms. <span class="grad-text">Approved and priced.</span></h2>
                <p class="muted" style="max-width:520px">Browse approved rooms with photos, type and monthly rate extras included, nothing hidden.</p>
            </div>
        </div>
        <div class="room-grid">
            <?php foreach ($featured as $r) { room_card($r); } ?>
        </div>
        <div class="see-all">
            <a href="rooms.php" class="btn btn-ghost">See all rooms &rarr;</a>
        </div>
    </div>
</section>
<!-- ============ HOW IT WORKS ============ -->
<section class="section" id="how" style="padding-top:0">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="section-eyebrow">How it works</div>
                <h2>Three steps. Zero guesswork.</h2>
                <p class="muted" style="max-width:520px">From first search to move-in day, every step is tracked in your dashboard.</p>
            </div>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <span class="step-num">STEP 01</span>
                <h3>Search & shortlist</h3>
                <p>Filter approved rooms by budget, type and amenities. Compare your top three side by side, total cost included.</p>
            </div>
            <div class="step-card">
                <span class="step-num">STEP 02</span>
                <h3>Send a request</h3>
                <p>Pick a move-in date and send a booking request straight to the landlord. No phone tag, no WhatsApp threads.</p>
            </div>
            <div class="step-card">
                <span class="step-num">STEP 03</span>
                <h3>Get confirmed & move in</h3>
                <p>Track every status change through notifications. Once confirmed, you're set — just bring your bags.</p>
            </div>
        </div>
        <div class="steps-note">Average flow from search to <b>confirmed booking</b> — all inside one dashboard.</div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<section class="section" id="faq" style="padding-top:0">
    <div class="container">
        <div class="section-head" style="justify-content:center;text-align:center">
            <div style="width:100%">
                <div class="section-eyebrow" style="justify-content:center">FAQ</div>
                <h2>
                    Common questions,<br>straight answers.
                </h2>
            </div>
        </div>
        <div class="faq-list">
            <?php foreach ([
                ['How do I know a listing is legitimate?',
                 'Every room goes through administrator approval before it appears publicly. Listings that are unpublished, rejected or deleted never show up in search — you only browse approved inventory.'],
                ['What exactly does "monthly rate" include?',
                 'The base rate shown on each card is the room itself. Any amenity extras (Wi-Fi, water, parking, etc.) are added up front on the room page, so the total you see is the total you plan for.'],
                ['Can I compare rooms before deciding?',
                 'Yes. Tick "Compare" on up to three rooms and open the side-by-side view — rates, amenities and calculated monthly totals laid out next to each other.'],
                ['How does a booking request work?',
                 'Choose your move-in date on the room page and send the request. The landlord confirms or declines, and you get an in-app notification either way. All your requests live in one dashboard.'],
                ['I own rooms — can I list them here?',
                 'Landlords get their own panel: add rooms with photos, monthly rates and amenity pricing, then track bookings and tenant inquiries from the same dashboard.'],
                ['Do I have to pay StayHaus to search?',
                 'Searching, comparing and sending booking requests is free for tenants. Rooms are priced by their landlords — StayHaus never pads the rate.'],
            ] as $q) { ?>
            <details class="faq-item">
                <summary><?= e($q[0]) ?></summary>
                <p class="faq-a"><?= e($q[1]) ?></p>
            </details>
            <?php } ?>
        </div>
    </div>
</section>

<!-- ============ FINAL CTA ============ -->
<section class="cta-final">
    <div class="container">
        <h2>Your next room is <span class="grad-text">already listed.</span></h2>
        <p>Browse approved rooms, lock in your date, and get confirmed without a single phone call.</p>
        <div class="cta-actions">
            <a href="rooms.php" class="btn btn-primary">Browse rooms →</a>
            <a href="register.php" class="btn btn-ghost">Create an account</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/public_foot.php'; ?>
