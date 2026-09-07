-- Simplify document request lifecycle to:
-- pending -> payment_verified -> completed, with rejected as the only terminal exception.

UPDATE document_requests
SET status = 'pending'
WHERE status IN ('draft', 'submitted', 'pending_payment', 'cancelled');

UPDATE document_requests
SET status = 'payment_verified'
WHERE status IN ('paid', 'processing', 'ready_for_release');

ALTER TABLE document_requests
  MODIFY COLUMN status ENUM('pending','payment_verified','completed','rejected') NOT NULL DEFAULT 'pending';

ALTER TABLE request_status_logs
  DROP FOREIGN KEY fk_rsl_user;

ALTER TABLE request_status_logs
  MODIFY COLUMN changed_by INT UNSIGNED NULL;

ALTER TABLE request_status_logs
  ADD CONSTRAINT fk_rsl_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL;
