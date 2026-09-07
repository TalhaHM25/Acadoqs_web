-- Migration: 010_api_keys.sql
-- API keys for kiosk and mobile app access (no user login required)

USE document_request_system;

CREATE TABLE IF NOT EXISTS api_keys (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  key_value    VARCHAR(100) NOT NULL UNIQUE,
  type         ENUM('kiosk','mobile') NOT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  last_used_at DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_ak_key    (key_value),
  INDEX idx_ak_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
