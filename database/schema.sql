CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    nightly_rate DECIMAL(10,2) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT 1,
    CHECK (capacity > 0),
    CHECK (nightly_rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    nightly_rate DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    submission_token CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id),
    INDEX availability (room_id, status, check_in, check_out),
    CHECK (check_out > check_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservation_cancellations (
    reservation_id INT UNSIGNED PRIMARY KEY,
    reason VARCHAR(500) NOT NULL,
    cancelled_at DATETIME NOT NULL,
    cancelled_by INT UNSIGNED NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (cancelled_by) REFERENCES users(id),
    CHECK (CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    identity_hash CHAR(64) PRIMARY KEY,
    failures INT NOT NULL DEFAULT 0,
    last_failure DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stays (
    reservation_id INT UNSIGNED PRIMARY KEY,
    checked_in_at DATETIME NOT NULL,
    checked_in_by INT UNSIGNED NOT NULL,
    checked_out_at DATETIME NULL,
    checked_out_by INT UNSIGNED NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (checked_in_by) REFERENCES users(id),
    FOREIGN KEY (checked_out_by) REFERENCES users(id),
    CHECK (checked_out_at IS NULL OR checked_out_at >= checked_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    filename VARCHAR(80) NOT NULL UNIQUE,
    mime_type VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_cleanings (
    reservation_id INT UNSIGNED PRIMARY KEY,
    pending_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    completed_by INT UNSIGNED NULL,
    FOREIGN KEY (reservation_id) REFERENCES stays(reservation_id),
    FOREIGN KEY (completed_by) REFERENCES users(id),
    INDEX pending_cleanings (completed_at, reservation_id),
    CHECK ((completed_at IS NULL AND completed_by IS NULL) OR
           (completed_at IS NOT NULL AND completed_by IS NOT NULL AND completed_at >= pending_at))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservation_changes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    old_room_id INT UNSIGNED NOT NULL,
    new_room_id INT UNSIGNED NOT NULL,
    old_room_code VARCHAR(20) NOT NULL,
    new_room_code VARCHAR(20) NOT NULL,
    old_check_in DATE NOT NULL,
    old_check_out DATE NOT NULL,
    new_check_in DATE NOT NULL,
    new_check_out DATE NOT NULL,
    old_nightly_rate DECIMAL(10,2) NOT NULL,
    new_nightly_rate DECIMAL(10,2) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    changed_at DATETIME NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (old_room_id) REFERENCES rooms(id),
    FOREIGN KEY (new_room_id) REFERENCES rooms(id),
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX reservation_history (reservation_id, id),
    CHECK (old_check_out > old_check_in AND new_check_out > new_check_in),
    CHECK (old_nightly_rate >= 0 AND new_nightly_rate >= 0),
    CHECK (CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_maintenance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    submission_token CHAR(64) NOT NULL UNIQUE,
    released_at DATETIME NULL,
    released_by INT UNSIGNED NULL,
    release_reason VARCHAR(500) NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (released_by) REFERENCES users(id),
    INDEX maintenance_dates (room_id, start_date, end_date),
    CHECK (end_date > start_date),
    CHECK (CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 500),
    CHECK ((released_at IS NULL AND released_by IS NULL AND release_reason IS NULL) OR
        (released_at IS NOT NULL AND released_by IS NOT NULL AND release_reason IS NOT NULL
         AND released_at >= created_at AND CHAR_LENGTH(TRIM(release_reason)) BETWEEN 1 AND 500))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reservation_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    kind ENUM('deposit','payment','refund') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('cash','transfer','card','other') NOT NULL,
    paid_on DATE NOT NULL,
    note VARCHAR(500) NOT NULL,
    recorded_at DATETIME NOT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    submission_token CHAR(64) NOT NULL UNIQUE,
    voided_at DATETIME NULL,
    voided_by INT UNSIGNED NULL,
    void_reason VARCHAR(500) NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (voided_by) REFERENCES users(id),
    INDEX payment_reservation (reservation_id, id),
    INDEX payment_day (paid_on, id),
    CHECK (amount > 0),
    CHECK (CHAR_LENGTH(TRIM(note)) BETWEEN 1 AND 500),
    CHECK ((voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) OR
        (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL
         AND voided_at >= recorded_at AND CHAR_LENGTH(TRIM(void_reason)) BETWEEN 1 AND 500))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reservation_emails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    kind ENUM('received','confirmed') NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    snapshot TEXT NOT NULL,
    message_key CHAR(32) NOT NULL UNIQUE,
    status ENUM('pending','sending','sent','failed','uncertain','obsolete') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    attempted_at DATETIME NULL,
    sent_at DATETIME NULL,
    error_code VARCHAR(30) NULL,
    UNIQUE KEY email_event (reservation_id, kind),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX email_pending (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
