-- =========================================================
-- Barangay Document Request System — MySQL Schema
-- =========================================================
CREATE DATABASE IF NOT EXISTS barangay_docs
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE barangay_docs;

-- ---------------------------------------------------------
-- Residents (the people who submit requests)
-- ---------------------------------------------------------
CREATE TABLE residents (
    resident_id      INT AUTO_INCREMENT PRIMARY KEY,
    first_name       VARCHAR(60)  NOT NULL,
    middle_name      VARCHAR(60),
    last_name        VARCHAR(60)  NOT NULL,
    suffix           VARCHAR(10),
    birthdate        DATE         NULL,
    gender           ENUM('male','female','other') NULL,
    civil_status     ENUM('single','married','widowed','separated','divorced') NULL,
    address          VARCHAR(255) NOT NULL,
    contact_number   VARCHAR(20)  NOT NULL,
    email            VARCHAR(120) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    valid_id_type    VARCHAR(60),
    valid_id_number  VARCHAR(60),
    valid_id_image   VARCHAR(255),
    is_verified      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Staff / admin accounts (barangay office side)
-- ---------------------------------------------------------
CREATE TABLE staff_accounts (
    staff_id      INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Types of documents the barangay issues (catalog/reference table)
-- ---------------------------------------------------------
CREATE TABLE document_types (
    document_type_id  INT AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(10)  NOT NULL UNIQUE,   -- e.g. 'BC', 'COR', 'COI'
    name               VARCHAR(120) NOT NULL,
    description        VARCHAR(255),
    requirements        TEXT,                           -- plain text list, or JSON if you prefer
    certificate_title VARCHAR(120) NULL,
    certificate_template LONGTEXT NULL,
    processing_days    INT          NOT NULL DEFAULT 1,
    fee                DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    is_active          TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE certificate_settings (
    settings_id       TINYINT UNSIGNED PRIMARY KEY,
    republic_text     VARCHAR(120) NOT NULL,
    barangay_name     VARCHAR(120) NOT NULL,
    municipality      VARCHAR(120) NOT NULL,
    province          VARCHAR(120) NOT NULL,
    office_address    VARCHAR(255) NOT NULL,
    office_subtitle   VARCHAR(160) NOT NULL,
    disclaimer        VARCHAR(255) NOT NULL,
    signatory_name    VARCHAR(120) NOT NULL,
    signatory_title   VARCHAR(120) NOT NULL,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO certificate_settings (
    settings_id, republic_text, barangay_name, municipality, province,
    office_address, office_subtitle, disclaimer, signatory_name, signatory_title
) VALUES (
    1, 'Republic of the Philippines', 'Lawacamulag', 'San Jose', 'Tarlac',
    'Barangay Hall, Lawacamulag, San Jose, Tarlac, Philippines',
    'Office of the Punong Barangay',
    'Not valid without the official dry seal of this office.',
    'Hon. Punong Barangay', 'Barangay Chairperson'
);

-- ---------------------------------------------------------
-- Document requests (the core transaction table)
-- ---------------------------------------------------------
CREATE TABLE requests (
    request_id        INT AUTO_INCREMENT PRIMARY KEY,
    reference_number   VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. 'BC-2026-0182'
    resident_id        INT          NOT NULL,
    document_type_id   INT          NOT NULL,
    purpose            VARCHAR(255) NOT NULL,
    on_behalf_of       TINYINT(1) NOT NULL DEFAULT 0,
    beneficiary_name   VARCHAR(120) NULL,
    beneficiary_address VARCHAR(255) NULL,
    beneficiary_civil_status ENUM('single','married','widowed','separated','divorced') NULL,
    relationship       VARCHAR(60) NULL,
    status             ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled')
                        NOT NULL DEFAULT 'pending',
    remarks            VARCHAR(255),
    handled_by          INT,                             -- staff_id who last actioned it
    requested_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    released_at         TIMESTAMP    NULL,

    FOREIGN KEY (resident_id) REFERENCES residents(resident_id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(document_type_id),
    FOREIGN KEY (handled_by) REFERENCES staff_accounts(staff_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_resident (resident_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Uploaded attachments per request (valid ID, supporting docs, etc.)
-- ---------------------------------------------------------
CREATE TABLE request_attachments (
    attachment_id  INT AUTO_INCREMENT PRIMARY KEY,
    request_id     INT NOT NULL,
    file_path      VARCHAR(255) NOT NULL,
    file_label     VARCHAR(100),                        -- e.g. 'Valid ID', 'Proof of billing'
    uploaded_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Status history (audit trail — powers the "track request" timeline)
-- ---------------------------------------------------------
CREATE TABLE request_status_history (
    history_id   INT AUTO_INCREMENT PRIMARY KEY,
    request_id   INT NOT NULL,
    status       ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled') NOT NULL,
    changed_by   INT,                                    -- staff_id, NULL if system-generated
    remarks      VARCHAR(255),
    changed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES staff_accounts(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Notifications (SMS/email log tied to a request)
-- ---------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id     INT NOT NULL,
    request_id      INT,
    channel         ENUM('email','sms','system') NOT NULL DEFAULT 'system',
    message         VARCHAR(255) NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (resident_id) REFERENCES residents(resident_id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Daily in-office service queue
-- ---------------------------------------------------------
CREATE TABLE service_queue_daily (
    queue_date      DATE PRIMARY KEY,
    last_number     INT UNSIGNED NOT NULL DEFAULT 0,
    current_number  INT UNSIGNED NULL,
    last_served     INT UNSIGNED NOT NULL DEFAULT 0,
    updated_by      INT NULL,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES staff_accounts(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE service_queue_entries (
    queue_entry_id  INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT NOT NULL UNIQUE,
    queue_date      DATE NOT NULL,
    queue_number    INT UNSIGNED NOT NULL,
    status          ENUM('waiting','serving','served','skipped') NOT NULL DEFAULT 'waiting',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    UNIQUE KEY uq_service_queue_daily_number (queue_date, queue_number),
    INDEX idx_service_queue_waiting (queue_date, status, queue_number)
) ENGINE=InnoDB;

-- =========================================================
-- Seed data: document types (matches the landing page cards)
-- =========================================================
INSERT INTO document_types (code, name, description, requirements, processing_days, fee) VALUES
('BC',  'Barangay Clearance',           'Required for job applications, business permits, and most government transactions.', 'Valid ID', 1, 50.00),
('COR', 'Certificate of Residency',     'Confirms residency within the barangay. Needed for school enrollment and IDs.',       'Valid ID, Proof of billing', 1, 30.00),
('COI', 'Certificate of Indigency',     'For residents applying for financial aid, medical assistance, or scholarships.',      'Valid ID', 1, 0.00),
('BPE', 'Business Permit Endorsement',  'Barangay-level clearance required before applying for a mayor''s permit.',            'Valid ID, DTI/SEC Registration', 2, 100.00),
('BID', 'Barangay ID',                  'Official resident identification issued by the barangay office.',                    'Valid ID, 1x1 photo', 3, 50.00),
('GMC', 'Good Moral Character', 'Attests to standing in the community — commonly required by schools and employers.', 'Valid ID', 1, 30.00),
('FTJ', 'First-Time Jobseeker Certificate', 'Exempts first-time jobseekers from fees on government transactions and documents, per RA 11261.', 'Valid ID', 1, 0.00);

-- =========================================================
-- Helper: generate a reference number like BC-2026-0182
-- Call this from PHP after inserting the resident + document_type,
-- or use a trigger — shown here as a stored procedure for clarity.
-- =========================================================
DELIMITER //
CREATE TRIGGER trg_requests_history_insert
AFTER INSERT ON requests
FOR EACH ROW
BEGIN
    INSERT INTO request_status_history (request_id, status, remarks)
    VALUES (NEW.request_id, NEW.status, 'Request submitted');
END//

CREATE TRIGGER trg_requests_history_update
AFTER UPDATE ON requests
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO request_status_history (request_id, status, changed_by, remarks)
        VALUES (NEW.request_id, NEW.status, NEW.handled_by, NEW.remarks);
    END IF;
END//
DELIMITER ;
