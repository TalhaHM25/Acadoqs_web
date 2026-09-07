-- Migration: 016_add_reminder_sent_at.sql
-- Tracks one-time automated release reminders per request.

USE document_request_system;

ALTER TABLE document_requests
  ADD COLUMN reminder_sent_at DATETIME NULL AFTER processed_at,
  ADD INDEX idx_dr_reminder_sent_at (reminder_sent_at);

