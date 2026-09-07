-- ============================================================
-- Migration: 002_add_password_reset.sql
-- Adds password-reset token columns to users table.
-- Run once: mysql -u root -p document_request_system < 002_add_password_reset.sql
-- ============================================================

USE document_request_system;

ALTER TABLE users
  ADD COLUMN reset_token            VARCHAR(100) NULL AFTER last_login_at,
  ADD COLUMN reset_token_expires_at DATETIME     NULL AFTER reset_token;
