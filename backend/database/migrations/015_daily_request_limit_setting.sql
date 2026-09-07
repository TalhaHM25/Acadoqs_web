-- Migration: 015_daily_request_limit_setting.sql
-- Adds configurable daily document request cap.

USE document_request_system;

INSERT INTO system_settings (`key`, value, type, description)
VALUES (
  'daily_request_limit',
  '0',
  'number',
  'Maximum document requests accepted per day. Use 0 for no limit.'
)
ON DUPLICATE KEY UPDATE
  type = VALUES(type),
  description = VALUES(description);
