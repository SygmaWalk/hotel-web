-- Conserva los estados y registros existentes. Se puede volver a ejecutar.
ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS reservation_cancellations (
    reservation_id INT UNSIGNED PRIMARY KEY,
    reason VARCHAR(500) NOT NULL,
    cancelled_at DATETIME NOT NULL,
    cancelled_by INT UNSIGNED NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (cancelled_by) REFERENCES users(id),
    CHECK (CHAR_LENGTH(TRIM(reason)) BETWEEN 1 AND 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
