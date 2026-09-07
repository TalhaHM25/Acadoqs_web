-- ============================================================
-- Migration: 001_create_tables.sql
-- Academic Document Request System
-- Run: mysql -u root -p document_request_system < 001_create_tables.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS document_request_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE document_request_system;

-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email             VARCHAR(255) NOT NULL UNIQUE,
  password          VARCHAR(255) NOT NULL,
  role              ENUM('student','admin') NOT NULL DEFAULT 'student',
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  email_verified_at DATETIME     NULL,
  last_login_at     DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROGRAMS
-- ============================================================

CREATE TABLE IF NOT EXISTS programs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  code       VARCHAR(50)  NULL,
  department VARCHAR(255) NULL,
  level      ENUM('college','senior_high','vocational','graduate') NOT NULL DEFAULT 'college',
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_programs_level  (level),
  INDEX idx_programs_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STUDENT PROFILES
-- ============================================================

CREATE TABLE IF NOT EXISTS student_profiles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL UNIQUE,
  student_id   VARCHAR(50)  NULL UNIQUE,
  first_name   VARCHAR(100) NOT NULL,
  middle_name  VARCHAR(100) NULL,
  last_name    VARCHAR(100) NOT NULL,
  suffix       VARCHAR(20)  NULL,
  gender       ENUM('male','female','other') NULL,
  birthday     DATE         NULL,
  birthplace   VARCHAR(255) NULL,
  phone_number VARCHAR(20)  NULL,
  address      TEXT         NULL,
  program_id   INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_sp_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_sp_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
  INDEX idx_sp_student_id (student_id),
  INDEX idx_sp_last_name  (last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENT CATEGORIES
-- ============================================================

CREATE TABLE IF NOT EXISTS document_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) NOT NULL UNIQUE,
  description TEXT         NULL,
  sort_order  SMALLINT     NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENT TYPES
-- ============================================================

CREATE TABLE IF NOT EXISTS document_types (
  id                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  category_id        INT UNSIGNED   NOT NULL,
  name               VARCHAR(255)   NOT NULL,
  slug               VARCHAR(255)   NOT NULL UNIQUE,
  description        TEXT           NULL,
  base_fee           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  certified_copy_fee DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  required_fields    JSON           NULL,
  processing_days    TINYINT        NOT NULL DEFAULT 3,
  is_active          TINYINT(1)     NOT NULL DEFAULT 1,
  sort_order         SMALLINT       NOT NULL DEFAULT 0,
  created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dt_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE RESTRICT,
  INDEX idx_dt_category (category_id),
  INDEX idx_dt_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENT REQUESTS
-- ============================================================

CREATE TABLE IF NOT EXISTS document_requests (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_number    VARCHAR(30)  NOT NULL UNIQUE,
  user_id           INT UNSIGNED NOT NULL,
  document_type_id  INT UNSIGNED NOT NULL,
  status            ENUM('draft','submitted','pending_payment','paid','payment_verified','processing','ready_for_release','completed','rejected','cancelled') NOT NULL DEFAULT 'draft',

  purpose           VARCHAR(500) NOT NULL,
  copies            TINYINT      NOT NULL DEFAULT 1,
  is_certified_copy TINYINT(1)   NOT NULL DEFAULT 0,
  total_fee         DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  req_student_id    VARCHAR(50)  NULL,
  req_last_name     VARCHAR(100) NOT NULL,
  req_first_name    VARCHAR(100) NOT NULL,
  req_middle_name   VARCHAR(100) NULL,
  req_program       VARCHAR(255) NULL,
  req_email         VARCHAR(255) NOT NULL,
  req_phone         VARCHAR(20)  NULL,

  is_representative TINYINT(1)   NOT NULL DEFAULT 0,
  rep_name          VARCHAR(255) NULL,
  rep_relationship  VARCHAR(100) NULL,

  gender            ENUM('male','female','other') NULL,
  birthday          DATE         NULL,
  birthplace        VARCHAR(255) NULL,
  has_name_change   TINYINT(1)   NULL,
  original_name     VARCHAR(255) NULL,
  is_graduate       TINYINT(1)   NULL,
  graduation_date   DATE         NULL,
  last_semester     ENUM('1st','2nd','summer') NULL,
  last_school_year  VARCHAR(20)  NULL,

  rejection_reason  TEXT         NULL,
  admin_notes       TEXT         NULL,
  processed_by      INT UNSIGNED NULL,
  processed_at      DATETIME     NULL,

  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_dr_user         FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE RESTRICT,
  CONSTRAINT fk_dr_doc_type     FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT,
  CONSTRAINT fk_dr_processed_by FOREIGN KEY (processed_by)     REFERENCES users(id)          ON DELETE SET NULL,
  INDEX idx_dr_user           (user_id),
  INDEX idx_dr_status         (status),
  INDEX idx_dr_doc_type       (document_type_id),
  INDEX idx_dr_request_number (request_number),
  INDEX idx_dr_created_at     (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REQUEST STATUS LOGS
-- ============================================================

CREATE TABLE IF NOT EXISTS request_status_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  from_status VARCHAR(30)  NULL,
  to_status   VARCHAR(30)  NOT NULL,
  notes       TEXT         NULL,
  changed_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_rsl_request FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsl_user    FOREIGN KEY (changed_by)  REFERENCES users(id)             ON DELETE RESTRICT,
  INDEX idx_rsl_request (request_id),
  INDEX idx_rsl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENT RECORDS
-- ============================================================

CREATE TABLE IF NOT EXISTS payment_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id       INT UNSIGNED NOT NULL UNIQUE,
  amount           DECIMAL(10,2) NOT NULL,
  payment_method   ENUM('gcash') NOT NULL DEFAULT 'gcash',
  reference_number VARCHAR(100) NULL,
  proof_path       VARCHAR(500) NULL,
  proof_filename   VARCHAR(255) NULL,
  status           ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  rejection_reason TEXT         NULL,
  verified_by      INT UNSIGNED NULL,
  verified_at      DATETIME     NULL,
  submitted_at     DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_pr_request     FOREIGN KEY (request_id)  REFERENCES document_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_verified_by FOREIGN KEY (verified_by) REFERENCES users(id)             ON DELETE SET NULL,
  INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REQUEST ATTACHMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS request_attachments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  label       VARCHAR(255) NOT NULL,
  file_path   VARCHAR(500) NOT NULL,
  file_name   VARCHAR(255) NOT NULL,
  file_size   INT UNSIGNED NOT NULL,
  mime_type   VARCHAR(100) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_ra_request FOREIGN KEY (request_id)  REFERENCES document_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ra_user    FOREIGN KEY (uploaded_by) REFERENCES users(id)             ON DELETE RESTRICT,
  INDEX idx_ra_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(100) NOT NULL,
  title      VARCHAR(255) NOT NULL,
  message    TEXT         NOT NULL,
  data       JSON         NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  read_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user    (user_id),
  INDEX idx_notif_is_read (is_read),
  INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`       VARCHAR(100) NOT NULL UNIQUE,
  value       TEXT         NULL,
  type        ENUM('text','number','boolean','json','file') NOT NULL DEFAULT 'text',
  description VARCHAR(500) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
