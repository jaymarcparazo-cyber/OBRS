USE barangay_docs;

CREATE TABLE IF NOT EXISTS service_queue_daily (
    queue_date      DATE PRIMARY KEY,
    last_number     INT UNSIGNED NOT NULL DEFAULT 0,
    current_number  INT UNSIGNED NULL,
    last_served     INT UNSIGNED NOT NULL DEFAULT 0,
    updated_by      INT NULL,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_queue_staff
        FOREIGN KEY (updated_by) REFERENCES staff_accounts(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_queue_entries (
    queue_entry_id  INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT NOT NULL UNIQUE,
    queue_date      DATE NOT NULL,
    queue_number    INT UNSIGNED NOT NULL,
    status          ENUM('waiting','serving','served','skipped') NOT NULL DEFAULT 'waiting',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_queue_request
        FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    UNIQUE KEY uq_service_queue_daily_number (queue_date, queue_number),
    INDEX idx_service_queue_waiting (queue_date, status, queue_number)
) ENGINE=InnoDB;
