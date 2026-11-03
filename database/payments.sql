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
