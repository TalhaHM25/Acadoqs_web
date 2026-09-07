-- Migration: 004_add_email_verification.sql
-- Adds email verification token columns to users table.

USE document_request_system;

ALTER TABLE users
  ADD COLUMN email_verification_token      VARCHAR(100) NULL AFTER email_verified_at,
  ADD COLUMN email_verification_expires_at DATETIME     NULL AFTER email_verification_token;
