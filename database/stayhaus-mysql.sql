-- StayHaus schema - MySQL dialect (for MySQL Workbench import + Reverse Engineer EER)
-- Converted from database/schema.sql (SQLite flavour). Import this into MySQL, then
-- in Workbench: Database > Reverse Engineer (Ctrl+R) to draw the EER diagram.

CREATE DATABASE IF NOT EXISTS stayhaus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stayhaus;

CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT, updated_at TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    role_id INTEGER NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255),
    status TEXT NOT NULL DEFAULT 'active',
    email_verified_at TEXT,
    last_login_at TEXT,
    remember_token VARCHAR(100),
    created_at TEXT, updated_at TEXT,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    INDEX idx_users_role (role_id),
    INDEX idx_users_status (status(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE room_types (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    default_capacity INTEGER NOT NULL DEFAULT 1,
    default_monthly_rate DECIMAL(10,2) NOT NULL,
    created_at TEXT, updated_at TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rooms (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    room_type_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    floor_number SMALLINT,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    occupancy_type TEXT NOT NULL DEFAULT 'private',
    capacity INTEGER NOT NULL,
    monthly_rate DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    approval_status TEXT NOT NULL DEFAULT 'draft',
    room_status TEXT NOT NULL DEFAULT 'unavailable',
    rejection_reason TEXT,
    approved_by INTEGER NULL,
    approved_at TEXT,
    is_published BOOLEAN NOT NULL DEFAULT 0,
    created_at TEXT, updated_at TEXT,
    deleted_at TEXT NULL,
    CONSTRAINT fk_rooms_type FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_rooms_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_rooms_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_rooms_type (room_type_id),
    INDEX idx_rooms_creator (created_by),
    INDEX idx_rooms_occupancy (occupancy_type(20)),
    INDEX idx_rooms_rate (monthly_rate),
    INDEX idx_rooms_approval (approval_status(20)),
    INDEX idx_rooms_status (room_status(20)),
    INDEX idx_rooms_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE room_images (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    room_id INTEGER NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(150),
    is_primary BOOLEAN NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TEXT, updated_at TEXT,
    CONSTRAINT fk_images_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room_images_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE amenities (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50),
    created_at TEXT, updated_at TEXT,
    INDEX idx_amenities_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE amenity_room (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    room_id INTEGER NOT NULL,
    amenity_id INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    note TEXT NULL,
    created_at TEXT, updated_at TEXT,
    UNIQUE (room_id, amenity_id),
    CONSTRAINT fk_amroom_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_amroom_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE room_approval_logs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    room_id INTEGER NOT NULL,
    submitted_by INTEGER NOT NULL,
    reviewed_by INTEGER NULL,
    previous_status VARCHAR(20) NOT NULL,
    new_status VARCHAR(20) NOT NULL,
    remarks TEXT,
    reviewed_at TEXT,
    created_at TEXT, updated_at TEXT,
    CONSTRAINT fk_approval_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approval_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_approval_logs_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TEXT,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(30) NOT NULL UNIQUE,
    tenant_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    number_of_occupants INTEGER NOT NULL DEFAULT 1,
    monthly_rate_at_booking DECIMAL(10,2) NOT NULL,
    daily_rate_at_booking DECIMAL(10,2),
    security_deposit_at_booking DECIMAL(10,2) NOT NULL,
    amenities_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amenity_ids TEXT,
    total_amount DECIMAL(10,2) NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    special_requests TEXT,
    processed_by INTEGER NULL,
    decision_notes TEXT,
    created_at TEXT, updated_at TEXT,
    CONSTRAINT fk_bookings_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bookings_tenant (tenant_id),
    INDEX idx_bookings_room (room_id),
    INDEX idx_bookings_start (start_date),
    INDEX idx_bookings_end (end_date),
    INDEX idx_bookings_status (status(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    booking_id INTEGER NOT NULL,
    tenant_id INTEGER NOT NULL,
    processed_by INTEGER NULL,
    transaction_reference VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method TEXT NOT NULL,
    payment_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    paid_at TEXT,
    notes TEXT,
    created_at TEXT, updated_at TEXT,
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payments_booking (booking_id),
    INDEX idx_payments_tenant (tenant_id),
    INDEX idx_payments_status (status(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id INTEGER NOT NULL,
    data TEXT NOT NULL,
    read_at TEXT,
    created_at TEXT, updated_at TEXT,
    INDEX idx_notifications_user (notifiable_type(50), notifiable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    description VARCHAR(255),
    created_at TEXT, updated_at TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
