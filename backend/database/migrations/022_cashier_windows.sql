-- Migration: 022_cashier_windows.sql
-- Adds multiple cashier windows to the queue system.

USE document_request_system;

CREATE TABLE IF NOT EXISTS cashier_windows (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  level      ENUM('college','senior_high') NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_cashier_windows_level (level),
  INDEX idx_cashier_windows_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE queue_numbers
  ADD COLUMN window_id INT UNSIGNED NULL AFTER request_id,
  ADD COLUMN called_by INT UNSIGNED NULL AFTER window_id,
  ADD COLUMN call_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER called_by,
  ADD CONSTRAINT fk_qn_window FOREIGN KEY (window_id) REFERENCES cashier_windows(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_qn_called_by FOREIGN KEY (called_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_qn_window (window_id);

INSERT INTO cashier_windows (name, level, sort_order)
SELECT 'College Window 1', 'college', 1
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE name = 'College Window 1');

INSERT INTO cashier_windows (name, level, sort_order)
SELECT 'College Window 2', 'college', 2
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE name = 'College Window 2');

INSERT INTO cashier_windows (name, level, sort_order)
SELECT 'Senior High Window 1', 'senior_high', 3
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE name = 'Senior High Window 1');

INSERT INTO cashier_windows (name, level, sort_order)
SELECT 'Senior High Window 2', 'senior_high', 4
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE name = 'Senior High Window 2');

INSERT IGNORE INTO registrar_permissions (user_id, module)
SELECT user_id, 'cashier'
FROM registrar_permissions
WHERE module = 'queue';
