-- Migration: 006_document_levels.sql
-- Adds level to document categories and types for college vs senior-high filtering

USE document_request_system;

ALTER TABLE document_categories
  ADD COLUMN level ENUM('college','senior_high','all') NOT NULL DEFAULT 'all' AFTER slug;

ALTER TABLE document_types
  ADD COLUMN level ENUM('college','senior_high','all') NOT NULL DEFAULT 'all' AFTER category_id;

-- Seed: mark existing categories/types as 'college' by default
-- (admin can adjust via UI)
UPDATE document_categories SET level = 'college';
UPDATE document_types       SET level = 'college';
