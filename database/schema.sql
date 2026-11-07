-- StayHaus schema (SQLite flavour of the MySQL design in the System Design document)
-- ENUM -> TEXT + CHECK, BIGINT UNSIGNED -> INTEGER, DECIMAL(10,2) kept as declared type.

CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT, updated_at TEXT
);

CREATE TABLE users (
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
);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TEXT
);

CREATE TABLE room_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    default_capacity INTEGER NOT NULL DEFAULT 1,
    default_monthly_rate DECIMAL(10,2) NOT NULL,
    created_at TEXT, updated_at TEXT
);

CREATE TABLE rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_type_id INTEGER NOT NULL REFERENCES room_types(id) ON DELETE RESTRICT,
    created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    floor_number SMALLINT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    occupancy_type TEXT NOT NULL DEFAULT 'private' CHECK (occupancy_type IN ('private','shared')),
    capacity INTEGER NOT NULL,
    monthly_rate DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    approval_status TEXT NOT NULL DEFAULT 'draft' CHECK (approval_status IN ('draft','pending','approved','rejected')),
    room_status TEXT NOT NULL DEFAULT 'unavailable' CHECK (room_status IN ('available','occupied','unavailable')),
    rejection_reason TEXT,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TEXT,
    is_published BOOLEAN NOT NULL DEFAULT 0,
    created_at TEXT, updated_at TEXT,
    deleted_at TEXT
);
CREATE INDEX idx_rooms_type ON rooms(room_type_id);
CREATE INDEX idx_rooms_creator ON rooms(created_by);
CREATE INDEX idx_rooms_occupancy ON rooms(occupancy_type);
CREATE INDEX idx_rooms_rate ON rooms(monthly_rate);
CREATE INDEX idx_rooms_approval ON rooms(approval_status);
CREATE INDEX idx_rooms_status ON rooms(room_status);
CREATE INDEX idx_rooms_published ON rooms(is_published);

CREATE TABLE room_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(150),
    is_primary BOOLEAN NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_room_images_room ON room_images(room_id);

CREATE TABLE amenities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50),
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_amenities_category ON amenities(category);

CREATE TABLE amenity_room (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    amenity_id INTEGER NOT NULL REFERENCES amenities(id) ON DELETE CASCADE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    note TEXT,
    created_at TEXT, updated_at TEXT,
    UNIQUE (room_id, amenity_id)
);

CREATE TABLE room_approval_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    submitted_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    previous_status VARCHAR(20) NOT NULL,
    new_status VARCHAR(20) NOT NULL,
    remarks TEXT,
    reviewed_at TEXT,
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_approval_logs_room ON room_approval_logs(room_id);

CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TEXT
);
CREATE INDEX idx_activity_user ON activity_logs(user_id);

CREATE TABLE bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_reference VARCHAR(30) NOT NULL UNIQUE,
    tenant_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE RESTRICT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    number_of_occupants INTEGER NOT NULL DEFAULT 1,
    monthly_rate_at_booking DECIMAL(10,2) NOT NULL,
    daily_rate_at_booking DECIMAL(10,2),
    security_deposit_at_booking DECIMAL(10,2) NOT NULL,
    amenities_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amenity_ids TEXT,
    total_amount DECIMAL(10,2) NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','rejected','cancelled','completed')),
    special_requests TEXT,
    processed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    decision_notes TEXT,
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_bookings_tenant ON bookings(tenant_id);
CREATE INDEX idx_bookings_room ON bookings(room_id);
CREATE INDEX idx_bookings_start ON bookings(start_date);
CREATE INDEX idx_bookings_end ON bookings(end_date);
CREATE INDEX idx_bookings_status ON bookings(status);

CREATE TABLE payments (
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
);
CREATE INDEX idx_payments_booking ON payments(booking_id);
CREATE INDEX idx_payments_tenant ON payments(tenant_id);
CREATE INDEX idx_payments_status ON payments(status);

CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id INTEGER NOT NULL,
    data TEXT NOT NULL,
    read_at TEXT,
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_notifications_user ON notifications(notifiable_type, notifiable_id);

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    "key" VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    description VARCHAR(255),
    created_at TEXT, updated_at TEXT
);

CREATE TABLE IF NOT EXISTS extension_requests (
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
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_extension_booking ON extension_requests(booking_id);

CREATE TABLE IF NOT EXISTS maintenance_requests (
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
    created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_maintenance_room ON maintenance_requests(room_id);
