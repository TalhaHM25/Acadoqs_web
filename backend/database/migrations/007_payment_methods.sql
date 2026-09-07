-- Migration: 007_payment_methods.sql
-- Admin-managed payment methods (GCash, BPI, Cash, etc.)

USE document_request_system;

CREATE TABLE IF NOT EXISTS payment_methods (
  id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100)   NOT NULL,
  type           ENUM('cash','gcash','bank','other') NOT NULL DEFAULT 'other',
  account_name   VARCHAR(255)   NULL,
  account_number VARCHAR(100)   NULL,
  instructions   TEXT           NULL,
  qr_path        VARCHAR(500)   NULL,
  is_active      TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order     SMALLINT       NOT NULL DEFAULT 0,
  created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_pm_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: migrate existing GCash setting into the payment_methods table
INSERT INTO payment_methods (name, type, account_name, account_number, is_active, sort_order)
SELECT
  'GCash' AS name,
  'gcash' AS type,
  (SELECT value FROM system_settings WHERE `key` = 'gcash_name'   LIMIT 1) AS account_name,
  (SELECT value FROM system_settings WHERE `key` = 'gcash_number' LIMIT 1) AS account_number,
  1,
  0
;

-- Add payment_method_id to document_requests and payment_records
ALTER TABLE document_requests
  ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER total_fee,
  ADD CONSTRAINT fk_dr_pm FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL;

ALTER TABLE payment_records
  ADD COLUMN payment_method_id INT UNSIGNED NULL AFTER payment_method,
  ADD CONSTRAINT fk_pr_pm FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL;

-- Back-fill: link existing GCash payment_records to new payment method row
UPDATE payment_records SET payment_method_id = (SELECT id FROM payment_methods WHERE type = 'gcash' LIMIT 1)
WHERE payment_method = 'gcash';
