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
