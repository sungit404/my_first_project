<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= BASE ?>index.php" class="brand">
                    <span class="brand-mark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 11L12 3L21 11" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 10V20H19V10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <?= e(setting('site_name', 'StayHaus')) ?>
                </a>
                <p>The modern way to find a boarding room — verified listings, transparent monthly cost, and booking requests that reach the landlord instantly.</p>
                <p class="footer-address">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= e(setting('site_address', '2F StayHaus Hub, 245 España Boulevard, Sampaloc, Manila, 1008')) ?>
                </p>
            </div>
            <div class="footer-col">
                <h4>Explore</h4>
                <a href="<?= BASE ?>index.php">Home</a>
                <a href="<?= BASE ?>rooms.php">Find a Room</a>
                <?php if ($me ?? null): ?><a href="<?= BASE ?>panel/dashboard.php">My Dashboard</a><?php endif; ?>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <a href="<?= BASE ?>login.php">Log in</a>
                <a href="<?= BASE ?>register.php">Create account</a>
                <a href="<?= BASE ?>forgot.php">Forgot password</a>
            </div>
            <div class="footer-col">
                <h4>Trust</h4>
                <a href="<?= BASE ?>index.php#features">Why StayHaus</a>
                <a href="<?= BASE ?>index.php#how">How it works</a>
                <a href="<?= BASE ?>index.php#faq">FAQ</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= e(setting('site_name', 'StayHaus')) ?> Boarding Rental System</span>
            <span class="privacy-note">// current occupants of each room are shown on its page — see who you'll live with</span>
        </div>
    </div>
</footer>
<script src="<?= BASE ?>assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
