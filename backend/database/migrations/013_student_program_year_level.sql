-- Add program (text) and year_level to student_profiles, replacing program_id FK dependency
ALTER TABLE student_profiles
  ADD COLUMN program    VARCHAR(120) NULL AFTER program_id,
  ADD COLUMN year_level VARCHAR(50)  NULL AFTER program;
