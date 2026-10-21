USE barangay_docs;

-- Adds a 'cancelled' status so residents can cancel their own pending
-- requests. This is a status change, not a real deletion — the request
-- stays on record (and in request_status_history) for audit purposes,
-- consistent with how every other status change already works.
ALTER TABLE requests
    MODIFY status ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled')
    NOT NULL DEFAULT 'pending';

ALTER TABLE request_status_history
    MODIFY status ENUM('pending','processing','approved','rejected','ready_for_pickup','released','cancelled')
    NOT NULL;
