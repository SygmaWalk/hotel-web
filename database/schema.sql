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
    status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending',
    submission_token CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id),
    INDEX availability (room_id, status, check_in, check_out),
    CHECK (check_out > check_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    identity_hash CHAR(64) PRIMARY KEY,
    failures INT NOT NULL DEFAULT 0,
    last_failure DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
