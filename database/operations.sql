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
