-- Require online student requests to be paid before creating the document request.

CREATE TABLE IF NOT EXISTS request_checkout_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  request_payload LONGTEXT NOT NULL,
  identity_proof_path VARCHAR(255) NOT NULL,
  identity_proof_filename VARCHAR(255) NOT NULL,
  identity_proof_mime VARCHAR(100) NOT NULL,
  identity_proof_size INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paymongo_checkout_session_id VARCHAR(100) NULL UNIQUE,
  paymongo_checkout_url TEXT NULL,
  paymongo_payment_intent_id VARCHAR(100) NULL,
  paymongo_payment_id VARCHAR(100) NULL,
  gateway_payload LONGTEXT NULL,
  status ENUM('checkout_pending','paid','converted','expired') NOT NULL DEFAULT 'checkout_pending',
  document_request_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rcs_user_status (user_id, status),
  INDEX idx_rcs_paymongo_session (paymongo_checkout_session_id),
  CONSTRAINT fk_rcs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rcs_request FOREIGN KEY (document_request_id) REFERENCES document_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE document_requests
SET status = 'rejected',
    rejection_reason = COALESCE(rejection_reason, 'Payment was not completed before request submission.'),
    updated_at = NOW()
WHERE status = 'pending';

ALTER TABLE document_requests
  MODIFY COLUMN status ENUM('payment_verified','completed','rejected') NOT NULL DEFAULT 'payment_verified';
