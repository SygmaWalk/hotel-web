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
