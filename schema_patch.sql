-- Consolidated, data-preserving migration for an existing barangay_docs database.
-- Import this file once; it is safe to run again.
USE barangay_docs;

-- The quick request flow may create resident accounts before profile details exist.
ALTER TABLE residents
    MODIFY birthdate DATE NULL,
    MODIFY gender ENUM('male','female','other') NULL,
    MODIFY civil_status ENUM('single','married','widowed','separated','divorced') NULL;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'residents' AND column_name = 'is_deleted'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE residents ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'residents' AND column_name = 'deleted_at'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE residents ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_deleted',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Allow requests to be made on behalf of another person.
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'requests' AND column_name = 'on_behalf_of'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE requests ADD COLUMN on_behalf_of TINYINT(1) NOT NULL DEFAULT 0 AFTER purpose',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'requests' AND column_name = 'beneficiary_name'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE requests ADD COLUMN beneficiary_name VARCHAR(120) NULL AFTER on_behalf_of',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'requests' AND column_name = 'beneficiary_address'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE requests ADD COLUMN beneficiary_address VARCHAR(255) NULL AFTER beneficiary_name',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'requests' AND column_name = 'beneficiary_civil_status'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE requests ADD COLUMN beneficiary_civil_status ENUM(''single'',''married'',''widowed'',''separated'',''divorced'') NULL AFTER beneficiary_address',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'requests' AND column_name = 'relationship'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE requests ADD COLUMN relationship VARCHAR(60) NULL AFTER beneficiary_civil_status',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Preserve existing request/history data while allowing cancellations.
ALTER TABLE requests
    MODIFY status ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled')
    NOT NULL DEFAULT 'pending';
ALTER TABLE request_status_history
    MODIFY status ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled')
    NOT NULL;

-- The certificate title can be customized per document type.
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'document_types'
      AND column_name = 'certificate_title'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE document_types ADD COLUMN certificate_title VARCHAR(120) NULL AFTER requirements',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Store editable certificate wording with each document type.
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'document_types'
      AND column_name = 'certificate_template'
);
SET @migration_sql = IF(@column_exists = 0,
    'ALTER TABLE document_types ADD COLUMN certificate_template LONGTEXT NULL AFTER requirements',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Shared certificate details are edited once for all document types.
CREATE TABLE IF NOT EXISTS certificate_settings (
    settings_id TINYINT UNSIGNED PRIMARY KEY,
    republic_text VARCHAR(120) NOT NULL,
    barangay_name VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    office_address VARCHAR(255) NOT NULL,
    office_subtitle VARCHAR(160) NOT NULL,
    disclaimer VARCHAR(255) NOT NULL,
    signatory_name VARCHAR(120) NOT NULL,
    signatory_title VARCHAR(120) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO certificate_settings (
    settings_id, republic_text, barangay_name, municipality, province,
    office_address, office_subtitle, disclaimer, signatory_name, signatory_title
) VALUES (
    1, 'Republic of the Philippines', 'Lawacamulag', 'San Jose', 'Tarlac',
    'Barangay Hall, Lawacamulag, San Jose, Tarlac, Philippines',
    'Office of the Punong Barangay',
    'Not valid without the official dry seal of this office.',
    'Hon. Punong Barangay', 'Barangay Chairperson'
);

-- Add the first-time jobseeker document type without overwriting existing catalog edits.
INSERT INTO document_types (code, name, description, requirements, processing_days, fee)
VALUES (
    'FTJ',
    'First-Time Jobseeker Certificate',
    'Exempts first-time jobseekers from fees on government transactions and documents, per RA 11261.',
    'Valid ID',
    1,
    0.00
)
ON DUPLICATE KEY UPDATE code = VALUES(code);

-- Daily service queue.
CREATE TABLE IF NOT EXISTS service_queue_daily (
    queue_date DATE PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    current_number INT UNSIGNED NULL,
    last_served INT UNSIGNED NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_queue_staff
        FOREIGN KEY (updated_by) REFERENCES staff_accounts(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_queue_entries (
    queue_entry_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    queue_date DATE NOT NULL,
    queue_number INT UNSIGNED NOT NULL,
    status ENUM('waiting','serving','served','skipped') NOT NULL DEFAULT 'waiting',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_queue_request
        FOREIGN KEY (request_id) REFERENCES requests(request_id) ON DELETE CASCADE,
    UNIQUE KEY uq_service_queue_daily_number (queue_date, queue_number),
    INDEX idx_service_queue_waiting (queue_date, status, queue_number)
) ENGINE=InnoDB;
