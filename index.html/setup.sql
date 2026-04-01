-- ============================================================
-- AttendanceTrack — Database Setup / Migration Script
-- Run this in phpMyAdmin > controlled_student_attendance_system
-- ============================================================

USE controlled_student_attendance_system;

-- Add missing columns to students table (ignore errors if they exist)
ALTER TABLE students ADD COLUMN IF NOT EXISTS full_name VARCHAR(120) DEFAULT NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_number VARCHAR(50) DEFAULT NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(120) DEFAULT NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL;

-- Ensure attendance_sessions has date_opened column
ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS date_opened DATE DEFAULT NULL;

-- If date_opened doesn't have a value, fill with CURDATE()
UPDATE attendance_sessions SET date_opened = CURDATE() WHERE date_opened IS NULL;

-- Make sure attendance_records has marks_deducted column
ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS marks_deducted INT DEFAULT 0;

-- Optional: Add a unique index on username in students table
-- (ignore if already exists)
ALTER TABLE students ADD UNIQUE IF NOT EXISTS (username);

-- Verify tables
SHOW TABLES;
DESCRIBE students;
DESCRIBE attendance_sessions;
DESCRIBE attendance_records;
