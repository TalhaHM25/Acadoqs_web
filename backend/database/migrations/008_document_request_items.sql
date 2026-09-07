-- Migration: 008_document_request_items.sql
-- Multi-document support: one submission can contain multiple document types.
-- Also adds walk-in support (kiosk/mobile without a user account).

USE document_request_system;

-- 1. Create the items table
CREATE TABLE IF NOT EXISTS document_request_items (
  id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  request_id       INT UNSIGNED   NOT NULL,
  document_type_id INT UNSIGNED   NOT NULL,
  copies           TINYINT        NOT NULL DEFAULT 1,
  is_certified_copy TINYINT(1)   NOT NULL DEFAULT 0,
  item_fee         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  special_data     JSON           NULL,    -- e.g. {"tor_year": "2023"} for TOR requests
  created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dri_request  FOREIGN KEY (request_id)       REFERENCES document_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_dri_doc_type FOREIGN KEY (document_type_id) REFERENCES document_types(id)    ON DELETE RESTRICT,
  INDEX idx_dri_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Migrate existing single-doc requests → items
INSERT INTO document_request_items
  (request_id, document_type_id, copies, is_certified_copy, item_fee)
SELECT id, document_type_id, copies, is_certified_copy, total_fee
FROM document_requests
WHERE document_type_id IS NOT NULL;

-- 3. Add new columns to document_requests
ALTER TABLE document_requests
  ADD COLUMN level      ENUM('college','senior_high') NULL    AFTER user_id,
  ADD COLUMN is_walkin  TINYINT(1) NOT NULL DEFAULT 0         AFTER level,
  ADD COLUMN walkin_name  VARCHAR(255) NULL                   AFTER is_walkin,
  ADD COLUMN walkin_phone VARCHAR(20)  NULL                   AFTER walkin_name,
  ADD COLUMN walkin_email VARCHAR(255) NULL                   AFTER walkin_phone;

-- 4. Seed level from user role for existing requests
UPDATE document_requests dr
  JOIN users u ON u.id = dr.user_id
  SET dr.level = CASE
    WHEN u.role IN ('college_student','college_registrar','admin') THEN 'college'
    WHEN u.role = 'senior_high_student' THEN 'senior_high'
    ELSE 'college'
  END;

-- 5. Make document_type_id nullable (items table now holds the detail)
ALTER TABLE document_requests
  DROP FOREIGN KEY fk_dr_doc_type;

ALTER TABLE document_requests
  MODIFY COLUMN document_type_id INT UNSIGNED NULL;

ALTER TABLE document_requests
  ADD CONSTRAINT fk_dr_doc_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT;

-- 6. Update status ENUM: add pending_payment as default, keep all existing values
ALTER TABLE document_requests
  MODIFY COLUMN status ENUM(
    'draft','submitted','pending_payment','paid','payment_verified',
    'processing','ready_for_release','completed','rejected','cancelled'
  ) NOT NULL DEFAULT 'pending_payment';
