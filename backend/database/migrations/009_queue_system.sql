-- Migration: 009_queue_system.sql
-- Queue number system for kiosk walk-ins

USE document_request_system;

CREATE TABLE IF NOT EXISTS queue_numbers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  queue_number VARCHAR(20)  NOT NULL,
  type         ENUM('document_request','general') NOT NULL DEFAULT 'general',
  level        ENUM('college','senior_high','all') NOT NULL DEFAULT 'all',
  status       ENUM('waiting','serving','completed','cancelled') NOT NULL DEFAULT 'waiting',
  request_id   INT UNSIGNED NULL,
  called_at    DATETIME NULL,
  completed_at DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_qn_request FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE SET NULL,
  INDEX idx_qn_status  (status),
  INDEX idx_qn_level   (level),
  INDEX idx_qn_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
