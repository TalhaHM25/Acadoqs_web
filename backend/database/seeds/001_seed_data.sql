-- ============================================================
-- Seed: 001_seed_data.sql
-- Run AFTER migrations
-- ============================================================

USE document_request_system;

-- ============================================================
-- PROGRAMS
-- ============================================================

INSERT INTO programs (name, code, department, level, sort_order) VALUES
  ('Bachelor of Science in Information Technology', 'BSIT', 'College of Computing', 'college', 1),
  ('Bachelor of Science in Computer Science',       'BSCS', 'College of Computing', 'college', 2),
  ('Bachelor of Science in Business Administration','BSBA', 'College of Business',  'college', 3),
  ('Bachelor of Science in Education',             'BSEd', 'College of Education', 'college', 4),
  ('Bachelor of Science in Nursing',               'BSN',  'College of Nursing',   'college', 5),
  ('Bachelor of Science in Accountancy',           'BSA',  'College of Business',  'college', 6),
  ('Bachelor of Arts in Communication',            'BAComm','College of Arts',     'college', 7),
  ('STEM',  'STEM',  NULL, 'senior_high', 10),
  ('ABM',   'ABM',   NULL, 'senior_high', 11),
  ('HUMSS', 'HUMSS', NULL, 'senior_high', 12),
  ('GAS',   'GAS',   NULL, 'senior_high', 13),
  ('TVL',   'TVL',   NULL, 'senior_high', 14);

-- ============================================================
-- DOCUMENT CATEGORIES
-- ============================================================

INSERT INTO document_categories (name, slug, description, sort_order) VALUES
  ('Certification',     'certification',     'Official certifications issued by the registrar', 1),
  ('Registrar Records', 'registrar-records', 'Official academic records and transcripts',       2);

-- ============================================================
-- DOCUMENT TYPES — Certification (category_id = 1)
-- ============================================================

INSERT INTO document_types (category_id, name, slug, base_fee, certified_copy_fee, required_fields, processing_days, sort_order) VALUES
  (1, 'Academic Completion',                  'academic-completion',           50.00, 30.00, '[]', 3, 1),
  (1, 'Course Description',                   'course-description',            50.00, 30.00, '[]', 3, 2),
  (1, 'Cumulative GWA',                       'cumulative-gwa',                50.00, 30.00, '[]', 3, 3),
  (1, 'English as Medium of Instruction',     'english-medium-instruction',    50.00, 30.00, '[]', 3, 4),
  (1, 'Enrollment',                           'enrollment',                    50.00, 30.00, '[]', 2, 5),
  (1, 'Good Moral Character',                 'good-moral-character',          50.00, 30.00, '[]', 3, 6),
  (1, 'Graduation / With Honors',             'graduation-with-honors',        50.00, 30.00, '["is_graduate","graduation_date"]', 3, 7),
  (1, 'Grades / Form 138',                    'grades-form138',                50.00, 30.00, '[]', 3, 8),
  (1, 'Units Earned',                         'units-earned',                  50.00, 30.00, '[]', 3, 9),
  (1, 'Diploma',                              'diploma',                      100.00, 50.00, '["is_graduate","graduation_date"]', 5, 10),
  (1, 'Special Order Number',                 'special-order-number',         100.00, 50.00, '["is_graduate","graduation_date"]', 5, 11),
  (1, 'List of Graduates for PRC',            'list-graduates-prc',           100.00, 50.00, '["is_graduate","graduation_date"]', 5, 12);

-- ============================================================
-- DOCUMENT TYPES — Registrar Records (category_id = 2)
-- ============================================================

INSERT INTO document_types (category_id, name, slug, base_fee, certified_copy_fee, required_fields, processing_days, sort_order) VALUES
  (2, 'Transcript of Records',
      'transcript-of-records',
      150.00, 50.00,
      '["gender","birthday","birthplace","has_name_change","is_graduate","graduation_date","last_semester","last_school_year"]',
      7, 1),
  (2, 'Form 137',
      'form-137',
      100.00, 30.00,
      '["gender","birthday","birthplace","is_graduate","graduation_date"]',
      5, 2),
  (2, 'Form 138',
      'form-138',
      100.00, 30.00,
      '["gender","birthday","birthplace"]',
      5, 3),
  (2, 'Transfer Credentials / Honorable Dismissal',
      'transfer-credentials',
      150.00, 50.00,
      '["gender","birthday","birthplace","has_name_change","is_graduate","last_semester","last_school_year"]',
      7, 4);

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================

INSERT INTO system_settings (`key`, value, type, description) VALUES
  ('gcash_name',   'School Registrar Office', 'text', 'GCash account name displayed to students'),
  ('gcash_number', '09XX-XXX-XXXX',           'text', 'GCash mobile number'),
  ('gcash_qr',     NULL,                      'file', 'Path to GCash QR code image'),
  ('school_name',  'Sample College',           'text', 'School name shown in the system'),
  ('school_logo',  NULL,                      'file', 'School logo path'),
  ('daily_request_limit', '0',                'number', 'Maximum document requests accepted per day. Use 0 for no limit.');

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Password: Admin@1234  (bcrypt hash — CHANGE THIS IMMEDIATELY)
-- ============================================================

INSERT INTO users (email, password, role) VALUES
  ('admin@school.edu',
   '$2y$12$eImiTXuWVxfM37uY4JANjq.yNWCzRfqnQIzGU8CL0GIW1eCrkFm3S',
   'admin');
