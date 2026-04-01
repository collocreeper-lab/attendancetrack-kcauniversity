-- ============================================================
--  AttendanceTrack — FRESH DATABASE SETUP
--  KCA University Controlled Student Attendance System
--
--  HOW TO USE:
--  1. Open phpMyAdmin → http://localhost/phpmyadmin
--  2. Click the "SQL" tab at the top
--  3. Paste ALL of this script and click "Go"
--  4. Done — your new database is ready!
-- ============================================================

-- Drop old database if it exists and create a clean one
DROP DATABASE IF EXISTS attendancetrack;
CREATE DATABASE attendancetrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendancetrack;

-- ============================================================
-- TABLE: users  (login credentials for ALL roles)
-- ============================================================
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(80)  NOT NULL UNIQUE,
    password    VARCHAR(80)  NOT NULL,
    role        ENUM('admin','lecturer','student') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: students  (extra profile info for students)
-- ============================================================
CREATE TABLE students (
    student_id       INT AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(80) NOT NULL UNIQUE,
    full_name        VARCHAR(120) DEFAULT NULL,
    admission_number VARCHAR(50)  DEFAULT NULL,
    email            VARCHAR(120) DEFAULT NULL,
    phone            VARCHAR(30)  DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: courses
-- ============================================================
CREATE TABLE courses (
    course_id    INT AUTO_INCREMENT PRIMARY KEY,
    course_name  VARCHAR(120) NOT NULL,
    course_code  VARCHAR(20)  NOT NULL UNIQUE,
    lecturer_id  INT          DEFAULT NULL,
    semester     VARCHAR(50)  DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ============================================================
-- TABLE: attendance_sessions  (opened by lecturers)
-- ============================================================
CREATE TABLE attendance_sessions (
    session_id   INT AUTO_INCREMENT PRIMARY KEY,
    course_id    INT  NOT NULL,
    date_opened  DATE NOT NULL DEFAULT (CURDATE()),
    late_time    TIME NOT NULL,
    end_time     TIME NOT NULL,
    status       ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: attendance_records  (one row per student per session)
-- ============================================================
CREATE TABLE attendance_records (
    record_id      INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT  NOT NULL,
    session_id     INT  NOT NULL,
    time_signed    DATETIME NOT NULL,
    status         ENUM('PRESENT','LATE','ABSENT') NOT NULL,
    marks_deducted INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_attendance (student_id, session_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(session_id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA — Admin
-- ============================================================
INSERT INTO users (username, password, role) VALUES
('admin1', 'admin123', 'admin');

-- ============================================================
-- SEED DATA — Lecturers
-- ============================================================
INSERT INTO users (username, password, role) VALUES
('lecturer1',     'lect123',   'lecturer'),
('lecturer2',     'lect456',   'lecturer'),
('GABUU',         'GABUU254',  'lecturer'),
('ALEX',          'ALEX231',   'lecturer'),
('KIMOTHS',       'kimoths123','lecturer'),
('DENNIS MUTHUI', 'DENOO',     'lecturer');

-- ============================================================
-- SEED DATA — Students (users table)
-- ============================================================
INSERT INTO users (username, password, role) VALUES
('collo creeper', 'respectively', 'student'),
('KUBIA',         'KUBIA21',      'student'),
('COLLINS',       'MINATHI',      'student'),
('KIAMBAKO',      '1236G6',       'student'),
('student1',      'pass123',      'student'),
('student2',      'pass456',      'student'),
('student3',      'pass789',      'student');

-- ============================================================
-- SEED DATA — Students profile table
-- ============================================================
INSERT INTO students (username, full_name, admission_number, email, phone) VALUES
('collo creeper', 'Collins Creeper',    'KCA/2023/001', 'collo@kcau.ac.ke',   '+254700000001'),
('KUBIA',         'Kubia Student',      'KCA/2023/002', 'kubia@kcau.ac.ke',   '+254700000002'),
('COLLINS',       'Collins Minathi',    'KCA/2023/003', 'collins@kcau.ac.ke', '+254700000003'),
('KIAMBAKO',      'Kiambako Student',   'KCA/2023/004', 'kiambako@kcau.ac.ke','+254700000004'),
('student1',      'Alice Wambui',       'KCA/2023/005', 'alice@kcau.ac.ke',   '+254711000001'),
('student2',      'Brian Otieno',       'KCA/2023/006', 'brian@kcau.ac.ke',   '+254711000002'),
('student3',      'Carol Njeri',        'KCA/2023/007', 'carol@kcau.ac.ke',   '+254711000003');

-- ============================================================
-- SEED DATA — Courses (linked to lecturer user_ids)
-- ============================================================
INSERT INTO courses (course_name, course_code, lecturer_id, semester)
SELECT 'Data Structures',   'CS201', user_id, 'Sem 1 2025' FROM users WHERE username='lecturer1';

INSERT INTO courses (course_name, course_code, lecturer_id, semester)
SELECT 'Database Systems',  'CS301', user_id, 'Sem 1 2025' FROM users WHERE username='lecturer1';

INSERT INTO courses (course_name, course_code, lecturer_id, semester)
SELECT 'Computer Networks', 'CS401', user_id, 'Sem 1 2025' FROM users WHERE username='lecturer2';

-- ============================================================
-- SEED DATA — Sample sessions (closed ones for history)
-- ============================================================
INSERT INTO attendance_sessions (course_id, date_opened, late_time, end_time, status)
SELECT course_id, '2025-03-28', '09:15:00', '09:45:00', 'CLOSED' FROM courses WHERE course_code='CS201';

INSERT INTO attendance_sessions (course_id, date_opened, late_time, end_time, status)
SELECT course_id, '2025-03-28', '11:15:00', '11:45:00', 'CLOSED' FROM courses WHERE course_code='CS301';

-- ============================================================
-- SEED DATA — Sample attendance records
-- ============================================================

-- student1 (Alice) → PRESENT in CS201 session 1
INSERT INTO attendance_records (student_id, session_id, time_signed, status, marks_deducted)
SELECT st.student_id, sess.session_id, '2025-03-28 09:10:00', 'PRESENT', 0
FROM students st, attendance_sessions sess
JOIN courses c ON sess.course_id = c.course_id
WHERE st.username = 'student1' AND c.course_code = 'CS201' AND sess.date_opened = '2025-03-28';

-- student2 (Brian) → PRESENT in CS301 session
INSERT INTO attendance_records (student_id, session_id, time_signed, status, marks_deducted)
SELECT st.student_id, sess.session_id, '2025-03-28 11:08:00', 'PRESENT', 0
FROM students st, attendance_sessions sess
JOIN courses c ON sess.course_id = c.course_id
WHERE st.username = 'student2' AND c.course_code = 'CS301' AND sess.date_opened = '2025-03-28';

-- ============================================================
-- VERIFY — Show all tables and sample data
-- ============================================================
SHOW TABLES;
SELECT '=== USERS ===' AS '';
SELECT user_id, username, password, role FROM users ORDER BY role, username;
SELECT '=== STUDENTS ===' AS '';
SELECT * FROM students;
SELECT '=== COURSES ===' AS '';
SELECT c.course_code, c.course_name, u.username AS lecturer, c.semester FROM courses c JOIN users u ON c.lecturer_id=u.user_id;
