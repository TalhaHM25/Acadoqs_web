-- Migration: 012_add_request_source.sql
-- Tracks whether a request came from the web app, kiosk, or mobile app.

USE document_request_system;

ALTER TABLE document_requests
  ADD COLUMN request_source ENUM('online','kiosk','mobile') NOT NULL DEFAULT 'online'
  AFTER is_walkin;

-- Back-fill: existing walk-in rows → kiosk, everything else → online
UPDATE document_requests SET request_source = 'kiosk'  WHERE is_walkin = 1;
UPDATE document_requests SET request_source = 'online' WHERE is_walkin = 0;
