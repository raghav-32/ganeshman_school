-- ============================================================
-- Ganeshman Singh Academy - School Management System
-- Database: ganeshman_school
-- ============================================================

CREATE DATABASE IF NOT EXISTS ganeshman_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ganeshman_school;

-- -----------------------------------------------
-- USERS TABLE (Admin, Teacher, Student logins)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL,
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- CLASSES TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    class_teacher_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_section (class_name, section)
) ENGINE=InnoDB;

-- -----------------------------------------------
-- STUDENTS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other'),
    address TEXT,
    phone VARCHAR(15),
    email VARCHAR(100),
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(15),
    guardian_relation VARCHAR(30),
    class_id INT,
    admission_date DATE,
    photo VARCHAR(255) DEFAULT 'default.png',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- PENDING REGISTRATIONS TABLE (student self sign-up)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other'),
    class_id INT,
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(15),
    guardian_relation VARCHAR(30),
    admission_date DATE,
    photo VARCHAR(255) DEFAULT 'default.png',
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- TEACHERS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    teacher_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other'),
    address TEXT,
    phone VARCHAR(15),
    email VARCHAR(100),
    qualification VARCHAR(100),
    joining_date DATE,
    photo VARCHAR(255) DEFAULT 'default.png',
    salary DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- TEACHER SUBJECTS/CLASSES ASSIGNMENT
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- SUBJECTS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) UNIQUE,
    class_id INT,
    full_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- ATTENDANCE TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent','Late','Leave') DEFAULT 'Present',
    marked_by INT,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, attendance_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- EXAMS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(100) NOT NULL,
    exam_type ENUM('Unit Test','Terminal','Final','Other') DEFAULT 'Unit Test',
    class_id INT NOT NULL,
    start_date DATE,
    end_date DATE,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- EXAM SCHEDULE TABLE (per-subject date/time/venue within an exam)
-- Used by admin/exams.php (schedule modal) and student/exams.php.
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY uq_exam_sub (exam_id, subject_id)
) ENGINE=InnoDB;

-- -----------------------------------------------
-- MARKS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    marks_obtained DECIMAL(5,2) DEFAULT 0,
    full_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    grade VARCHAR(5),
    remarks VARCHAR(255),
    entered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_marks (student_id, exam_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- FEE STRUCTURE TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS fee_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date INT DEFAULT 10,
    academic_year VARCHAR(10),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- FEE PAYMENTS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    receipt_number VARCHAR(50) UNIQUE,
    payment_method ENUM('Cash','Cheque','Online') DEFAULT 'Cash',
    collected_by INT,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- TIMETABLE TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_of_week ENUM('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(20),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------
-- NOTICES TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    target_role ENUM('all','admin','teacher','student') DEFAULT 'all',
    posted_by INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------
-- LIBRARY BOOKS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150),
    isbn VARCHAR(30) UNIQUE,
    category VARCHAR(100),
    publisher VARCHAR(150),
    publish_year INT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------
-- ISSUED BOOKS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS issued_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    fine_amount DECIMAL(8,2) DEFAULT 0.00,
    fine_paid TINYINT(1) DEFAULT 0,
    issued_by INT,
    status ENUM('Issued','Returned','Overdue') DEFAULT 'Issued',
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Default Admin User (password: admin123)
INSERT INTO users (username, password, role, email) VALUES
('admin', 'RESET_REQUIRED', 'admin', 'admin@ganeshman.edu.np');

-- Sample Classes
INSERT INTO classes (class_name, section) VALUES
('Class 1','A'), ('Class 1','B'),
('Class 2','A'), ('Class 2','B'),
('Class 3','A'), ('Class 4','A'),
('Class 5','A'), ('Class 6','A'),
('Class 7','A'), ('Class 8','A'),
('Class 9','A'), ('Class 10','A');

-- Sample Notices
INSERT INTO notices (title, content, target_role, posted_by) VALUES
('Welcome to New Academic Year', 'We warmly welcome all students and staff to the new academic year 2081 BS. Let us work together for excellence.', 'all', 1),
('Annual Sports Day', 'Annual Sports Day will be held on Baisakh 15, 2081. All students must participate.', 'all', 1),
('Fee Payment Reminder', 'Please ensure fee payment before Baisakh 10, 2081 to avoid late fine.', 'student', 1);

-- ============================================================
-- v2 SCHEMA ADDITIONS (run these if upgrading)
-- ============================================================

-- Add image column to notices
ALTER TABLE notices ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL AFTER content;

-- Change timetable day_of_week to allow multiple days (stored as comma-separated)
ALTER TABLE timetable MODIFY COLUMN day_of_week VARCHAR(100) NOT NULL DEFAULT 'Monday';

-- Add partial payment tracking to fee_payments
ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS is_partial TINYINT(1) DEFAULT 0 AFTER payment_method;

-- ============================================================
-- v3 SCHEMA ADDITIONS (run these if upgrading) — student email + email login
-- ============================================================

-- Add email column to students (students table didn't have one; teachers already did)
ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER phone;

-- Add email column to pending_registrations (create table first if it doesn't exist yet)
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other'),
    class_id INT,
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(15),
    guardian_relation VARCHAR(30),
    admission_date DATE,
    photo VARCHAR(255) DEFAULT 'default.png',
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
ALTER TABLE pending_registrations ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER phone;

-- One-time backfill: the admin "Edit Student"/"Edit Teacher" forms used to save
-- email onto students.email / teachers.email WITHOUT syncing it to users.email
-- (the login table). This fixes any records saved before that fix so email
-- login works for them too. Safe to re-run.
UPDATE users u INNER JOIN students s ON u.id = s.user_id
SET u.email = s.email
WHERE (u.email IS NULL OR u.email = '') AND s.email IS NOT NULL AND s.email <> '';

UPDATE users u INNER JOIN teachers t ON u.id = t.user_id
SET u.email = t.email
WHERE (u.email IS NULL OR u.email = '') AND t.email IS NOT NULL AND t.email <> '';

-- ============================================================
-- v4 SCHEMA ADDITIONS (run these if upgrading) — exam scheduling
-- Fixes "Could not load schedule" and exam Edit/Save failing silently:
-- exams.start_time/end_time and the whole exam_schedule table were used
-- by admin/exams.php and student/exams.php but were never in this file.
-- ============================================================
ALTER TABLE exams ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL AFTER end_date;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL AFTER start_time;

CREATE TABLE IF NOT EXISTS exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY uq_exam_sub (exam_id, subject_id)
) ENGINE=InnoDB;

-- ============================================================
-- v5 SCHEMA ADDITIONS (run these if upgrading) — mobile API (api/v1)
-- Token login for the Android app, plus two objects the web portal
-- previously only created lazily at page-load time.
-- ============================================================
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    device_name VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_api_tokens_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Publish results" flag used by admin/marks.php and student/results.php
ALTER TABLE exams ADD COLUMN IF NOT EXISTS results_published TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published;

CREATE TABLE IF NOT EXISTS book_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    book_id      INT NOT NULL,
    request_date DATE NOT NULL,
    status       ENUM('Pending','Issued','Cancelled') DEFAULT 'Pending',
    note         VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id)    REFERENCES books(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
