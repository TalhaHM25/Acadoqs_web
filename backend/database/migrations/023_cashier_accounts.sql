-- Migration: 023_cashier_accounts.sql
-- Adds cashier-only staff accounts for cashier queue management.

USE document_request_system;

ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'admin',
    'college_registrar',
    'senior_high_registrar',
    'college_student',
    'senior_high_student',
    'cashier'
  ) NOT NULL DEFAULT 'college_student';
