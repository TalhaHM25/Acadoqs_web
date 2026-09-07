-- Migration: 005_update_roles_levels.sql
-- Extends user roles and adds level tracking

USE document_request_system;

-- Step 1: Add new role values to ENUM (keep 'student' temporarily for data migration)
ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'student',
    'admin',
    'college_registrar',
    'senior_high_registrar',
    'college_student',
    'senior_high_student'
  ) NOT NULL DEFAULT 'college_student';

-- Step 2: Migrate existing 'student' → 'college_student'
UPDATE users SET role = 'college_student' WHERE role = 'student';

-- Step 3: Remove 'student' from ENUM now that no rows use it
ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'admin',
    'college_registrar',
    'senior_high_registrar',
    'college_student',
    'senior_high_student'
  ) NOT NULL DEFAULT 'college_student';

-- Step 4: Add level + address to student_profiles
ALTER TABLE student_profiles
  ADD COLUMN level ENUM('college','senior_high') NOT NULL DEFAULT 'college' AFTER user_id,
  ADD COLUMN address TEXT NULL AFTER phone_number;

-- Step 5: Seed level from users.role for existing students
UPDATE student_profiles sp
  JOIN users u ON u.id = sp.user_id
  SET sp.level = 'college'
  WHERE u.role = 'college_student';
