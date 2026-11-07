<?php
/**
 * Database bootstrap (SQLite = zero setup on Herd).
 * First run: creates data/stayhaus.sqlite, builds the tables and loads sample data.
 */
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }
$dbFile = $dataDir . '/stayhaus.sqlite';
$fresh = !file_exists($dbFile) || filesize($dbFile) === 0;

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA busy_timeout = 3000');

// Migrations for older databases: only touch tables that already exist
$usersCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
if ($usersCols && !in_array('address', $usersCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN address VARCHAR(255)');
}
$bookingsCols = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_COLUMN, 1);
if ($bookingsCols && !in_array('amenity_ids', $bookingsCols, true)) {
    $pdo->exec('ALTER TABLE bookings ADD COLUMN amenity_ids TEXT');
    // historic bookings rented every amenity the room offered
    $pdo->exec("UPDATE bookings SET amenity_ids = (
        SELECT json_group_array(ar.amenity_id) FROM amenity_room ar WHERE ar.room_id = bookings.room_id
    ) WHERE status IN ('confirmed','completed') AND amenity_ids IS NULL");
}

// vNext tables for older databases: stay extensions and maintenance requests
$pdo->exec("CREATE TABLE IF NOT EXISTS extension_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL REFERENCES bookings(id),
    requested_by INTEGER NOT NULL REFERENCES users(id),
    current_end TEXT NOT NULL,
    new_end TEXT NOT NULL,
    extra_days INTEGER NOT NULL,
    amount REAL NOT NULL,
    note TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    decided_by INTEGER REFERENCES users(id),
    decision_notes TEXT,
    created_at TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL REFERENCES users(id),
    room_id INTEGER NOT NULL REFERENCES rooms(id),
    booking_id INTEGER REFERENCES bookings(id),
    category TEXT NOT NULL,
    description TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open',
    resolution_notes TEXT,
    resolved_by INTEGER REFERENCES users(id),
    resolved_at TEXT,
    created_at TEXT, updated_at TEXT)");

// payments.payment_type gains 'extension' (stay extensions) — SQLite cannot alter a CHECK,
// so rebuild the table once, copying every row (the tenant's payment history is preserved)
$paySql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'payments'")->fetchColumn();
if ($paySql && !str_contains($paySql, "'extension'")) {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE payments_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
            tenant_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            processed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            transaction_reference VARCHAR(50) NOT NULL UNIQUE,
            amount DECIMAL(10,2) NOT NULL,
            payment_method TEXT NOT NULL CHECK (payment_method IN ('cash','gcash','bank_transfer','other')),
            payment_type TEXT NOT NULL CHECK (payment_type IN ('deposit','rent','extension','amenity','penalty','other')),
            status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','paid','failed','refunded','cancelled')),
            paid_at TEXT,
            notes TEXT,
            created_at TEXT, updated_at TEXT
        )");
        $pdo->exec("INSERT INTO payments_new (id, booking_id, tenant_id, processed_by, transaction_reference, amount, payment_method, payment_type, status, paid_at, notes, created_at, updated_at)
                    SELECT id, booking_id, tenant_id, processed_by, transaction_reference, amount, payment_method, payment_type, status, paid_at, notes, created_at, updated_at FROM payments");
        $pdo->exec('DROP TABLE payments');
        $pdo->exec('ALTER TABLE payments_new RENAME TO payments');
        $pdo->exec('CREATE INDEX idx_payments_booking ON payments(booking_id)');
        $pdo->exec('CREATE INDEX idx_payments_tenant ON payments(tenant_id)');
        $pdo->exec('CREATE INDEX idx_payments_status ON payments(status)');
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $ex;
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
}

// users.status gains 'pending' (landlord self-registrations wait for admin approval) — rebuild once, copying every row
$usrSql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
if ($usrSql && !str_contains($usrSql, "'pending'")) {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(20),
            address VARCHAR(255),
            password VARCHAR(255) NOT NULL,
            profile_photo VARCHAR(255),
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','pending','inactive','suspended')),
            email_verified_at TEXT,
            last_login_at TEXT,
            remember_token VARCHAR(100),
            created_at TEXT, updated_at TEXT
        )");
        $pdo->exec("INSERT INTO users_new (id, role_id, first_name, last_name, email, phone, address, password, profile_photo, status, email_verified_at, last_login_at, remember_token, created_at, updated_at)
                    SELECT id, role_id, first_name, last_name, email, phone, address, password, profile_photo, status, email_verified_at, last_login_at, remember_token, created_at, updated_at FROM users");
        $pdo->exec('DROP TABLE users');
        $pdo->exec('ALTER TABLE users_new RENAME TO users');
        $pdo->exec('CREATE INDEX idx_users_role ON users(role_id)');
        $pdo->exec('CREATE INDEX idx_users_status ON users(status)');
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $pdo->exec('PRAGMA foreign_keys = ON');
        throw $ex;
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
}

if ($fresh) {
    try {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
        require __DIR__ . '/../database/seed.php';
        $pdo->beginTransaction();
        seed_database($pdo);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $pdo = null;
        @unlink($dbFile);
        die('Setup failed: ' . htmlspecialchars($ex->getMessage()));
    }
}

// Settings seeded later: fill in any settings key this version expects
$pdo->exec("INSERT OR IGNORE INTO settings (\"key\", value, description, created_at, updated_at) VALUES
    ('site_address', '2F StayHaus Hub, 245 España Boulevard, Sampaloc, Manila, 1008', 'System address shown in the public footer', date('now'), date('now')),
    ('penalty_percent', '5', 'Late-rent penalty: percent of the monthly amount charged when rent is paid after the due date', date('now'), date('now')),
    ('penalty_grace_days', '0', 'Days past the due date before the late penalty applies (0 = any day late)', date('now'), date('now'))");
