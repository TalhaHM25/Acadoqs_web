-- Migration: 024_separate_queue_areas.sql
-- Separates registrar and cashier queue windows/displays.

USE document_request_system;

ALTER TABLE cashier_windows
  ADD COLUMN window_type ENUM('registrar','cashier') NOT NULL DEFAULT 'cashier' AFTER id,
  ADD INDEX idx_cashier_windows_type (window_type);

ALTER TABLE queue_numbers
  ADD COLUMN service_area ENUM('registrar','cashier') NOT NULL DEFAULT 'registrar' AFTER type,
  ADD INDEX idx_qn_service_area (service_area);

UPDATE cashier_windows
SET window_type = 'cashier'
WHERE window_type IS NULL OR window_type = '';

UPDATE queue_numbers
SET service_area = 'registrar'
WHERE service_area IS NULL OR service_area = '';

INSERT INTO cashier_windows (window_type, name, level, sort_order)
SELECT 'registrar', 'Registrar Window 1', 'college', 1
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE window_type = 'registrar' AND name = 'Registrar Window 1');

INSERT INTO cashier_windows (window_type, name, level, sort_order)
SELECT 'registrar', 'Registrar Window 2', 'college', 2
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE window_type = 'registrar' AND name = 'Registrar Window 2');

INSERT INTO cashier_windows (window_type, name, level, sort_order)
SELECT 'registrar', 'Registrar Window 3', 'senior_high', 3
WHERE NOT EXISTS (SELECT 1 FROM cashier_windows WHERE window_type = 'registrar' AND name = 'Registrar Window 3');
