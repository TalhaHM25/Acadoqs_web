-- Normalize legacy request statuses left by older payment/status flows.

ALTER TABLE document_requests
  MODIFY COLUMN status ENUM(
    'draft','submitted','pending_payment','pending','paid','processing','ready_for_release',
    'payment_verified','completed','rejected','cancelled'
  ) NOT NULL DEFAULT 'payment_verified';

UPDATE document_requests
SET status = 'payment_verified',
    updated_at = NOW()
WHERE status IN ('paid', 'processing', 'ready_for_release');

UPDATE document_requests
SET status = 'rejected',
    rejection_reason = COALESCE(rejection_reason, 'Payment was not completed before request submission.'),
    updated_at = NOW()
WHERE status IN ('draft', 'submitted', 'pending_payment', 'pending', 'cancelled');

ALTER TABLE document_requests
  MODIFY COLUMN status ENUM('payment_verified','completed','rejected') NOT NULL DEFAULT 'payment_verified';
