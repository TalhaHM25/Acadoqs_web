-- Migration: 003_add_grade_level_request_semester.sql
-- Adds grade_level and request_semester to document_requests

USE document_request_system;

ALTER TABLE document_requests
  ADD COLUMN grade_level       VARCHAR(50)         NULL AFTER req_program,
  ADD COLUMN request_semester  ENUM('1st','2nd')   NULL AFTER grade_level;
