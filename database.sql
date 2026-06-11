CREATE DATABASE IF NOT EXISTS online_exam_db;
USE online_exam_db;

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Subjects Table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE
);

-- 3. Exams Table
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    duration INT NOT NULL, -- in minutes
    pass_percentage DECIMAL(5,2) DEFAULT 50.00,
    negative_marking DECIMAL(3,2) DEFAULT 0.00, -- Marks deducted per wrong answer
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Questions Table
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- 5. Options Table
CREATE TABLE options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_letter CHAR(1) NOT NULL, -- A, B, C, or D
    option_text TEXT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- 6. Answer Keys Table
CREATE TABLE answer_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL UNIQUE,
    correct_option CHAR(1) NOT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- 7. Exam Attempts Table
CREATE TABLE exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    status ENUM('ongoing', 'completed') DEFAULT 'ongoing',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- 8. Student Answers Table
CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) DEFAULT NULL,
    is_flagged TINYINT(1) DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ans (attempt_id, question_id)
);

-- 9. Exam Results Table
CREATE TABLE exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL UNIQUE,
    score_obtained DECIMAL(5,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    status ENUM('Pass', 'Fail') NOT NULL,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE
);

-- 10. Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed Default Accounts (Passwords match cleartext requirements: admin123, teacher123, student123)
INSERT INTO users (fullname, username, password, role, email) VALUES
('System Administrator', 'admin', 'admin123', 'admin', 'admin@portal.com'),
('Lead Educator', 'teacher1', '$2y$10$UoV6I62RjX9q9.6m9fF8gOXmH7V1Yv3PszNOnQ84yR1nL9NUp6fEq', 'teacher', 'teacher@portal.com'),
('Academic Student', 'student1', '$2y$10$Z3U6gE6mRQLM9vQ6V60FauxLp5w19KjA6p99.k2QzGZfA2FwD8uG.', 'student', 'student@portal.com');

-- Seed Mock Subject and Exam Data
INSERT INTO subjects (id, subject_name) VALUES (1, 'Information Technology');
INSERT INTO exams (id, subject_id, title, duration, pass_percentage, negative_marking, created_by) 
VALUES (1, 1, 'Web Architectures Final', 10, 50.00, 0.25, 2);

INSERT INTO questions (id, exam_id, question_text) VALUES 
(1, 1, 'Which protocol is primary used to securely transmit data across web browsers?'),
(2, 1, 'What does the abbreviation SQL represent in context of relational platforms?');

INSERT INTO options (question_id, option_letter, option_text) VALUES 
(1, 'A', 'HTTP'), (1, 'B', 'HTTPS'), (1, 'C', 'FTP'), (1, 'D', 'SMTP'),
(2, 'A', 'Structured Query Language'), (2, 'B', 'Simple Queue Layout'), (2, 'C', 'System Query Logic'), (2, 'D', 'Standard Quick Link');

INSERT INTO answer_keys (question_id, correct_option) VALUES (1, 'B'), (2, 'A');