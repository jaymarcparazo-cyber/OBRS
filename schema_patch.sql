USE barangay_docs;

-- The quick request form only collects name/contact/address/email — not a full
-- profile. Let these be filled in later by a proper registration flow.
ALTER TABLE residents
    MODIFY birthdate    DATE NULL,
    MODIFY gender       ENUM('male','female','other') NULL,
    MODIFY civil_status ENUM('single','married','widowed','separated','divorced') NULL;

-- Add the 6th document type used on the landing page ("First-Time Jobseeker
-- Certificate" — a real barangay-issued document under RA 11261, exempting
-- first-time jobseekers from fees on their first government transaction).
INSERT INTO document_types (code, name, description, requirements, processing_days, fee)
VALUES ('FTJ', 'First-Time Jobseeker Certificate',
        'Exempts first-time jobseekers from fees on government transactions and documents, per RA 11261.',
        'Valid ID', 1, 0.00)
ON DUPLICATE KEY UPDATE name = VALUES(name);
