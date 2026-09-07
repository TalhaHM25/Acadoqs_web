-- Migration: 011_registrar_permissions.sql
-- Module-level access control for registrar accounts

USE document_request_system;

CREATE TABLE IF NOT EXISTS registrar_permissions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  module     VARCHAR(100) NOT NULL,   -- e.g. 'requests', 'reports', 'queue', 'document_types', 'settings'
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_module (user_id, module),
  CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rp_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
