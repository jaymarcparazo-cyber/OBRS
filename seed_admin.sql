USE barangay_docs;

-- Default admin login: username "admin", password "admin123"
-- CHANGE THIS PASSWORD before deploying anywhere real.
INSERT INTO staff_accounts (username, password_hash, full_name, role)
VALUES ('admin', '$2b$12$knOQm.A1ugywQyvTAaxVBO457xY.Bo9gr.ON/H1DRC3eDqm6ZANeK', 'Barangay Administrator', 'admin')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);
