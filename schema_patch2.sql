USE barangay_docs;

-- Allows a logged-in resident to submit a request on behalf of someone else
-- (a family member, elderly parent, minor, etc.) without that person needing
-- their own account. The document itself gets issued in the beneficiary's
-- name; the logged-in account is kept on record as who actually submitted it.
ALTER TABLE requests
    ADD COLUMN on_behalf_of         TINYINT(1) NOT NULL DEFAULT 0 AFTER purpose,
    ADD COLUMN beneficiary_name     VARCHAR(120) NULL AFTER on_behalf_of,
    ADD COLUMN beneficiary_address  VARCHAR(255) NULL AFTER beneficiary_name,
    ADD COLUMN beneficiary_civil_status ENUM('single','married','widowed','separated','divorced') NULL AFTER beneficiary_address,
    ADD COLUMN relationship         VARCHAR(60) NULL AFTER beneficiary_civil_status;
