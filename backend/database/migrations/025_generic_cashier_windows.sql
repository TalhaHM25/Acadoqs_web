-- Migration: 025_generic_cashier_windows.sql
-- Makes cashier windows generic while keeping registrar windows level-specific.

USE document_request_system;

ALTER TABLE cashier_windows
  MODIFY COLUMN level ENUM('college','senior_high','all') NOT NULL DEFAULT 'all';

UPDATE cashier_windows
SET level = 'all'
WHERE window_type = 'cashier';

UPDATE cashier_windows
SET name = CONCAT('Cashier Window ', sort_order)
WHERE window_type = 'cashier'
  AND name REGEXP '^(College|Senior High) Window [0-9]+$';

INSERT INTO cashier_windows (window_type, name, level, sort_order)
SELECT 'cashier', 'Cashier Window 1', 'all', 1
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE window_type = 'cashier');
