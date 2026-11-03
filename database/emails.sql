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
