-- Create Database
CREATE DATABASE IF NOT EXISTS mentor_book_system;
USE mentor_book_system;

-- Users Table (Common for all roles)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'mentor', 'student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student Details Table
CREATE TABLE student_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    roll_number VARCHAR(20) UNIQUE,
    class VARCHAR(20),
    section VARCHAR(10),
    parent_phone VARCHAR(15),
    address TEXT,
    mentor_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES users(id)
);

-- Semester Marks Table
CREATE TABLE semester_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    semester INT,
    subject VARCHAR(50),
    marks_obtained INT,
    total_marks INT,
    grade VARCHAR(2),
    FOREIGN KEY (student_id) REFERENCES student_details(id)
);

-- Achievements Table
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    achievement_title VARCHAR(200),
    description TEXT,
    certificate_path VARCHAR(255),
    verified_by_mentor BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_details(id)
);

-- Mentor Feedback Table
CREATE TABLE mentor_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    mentor_id INT,
    feedback TEXT,
    semester INT,
    given_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_details(id),
    FOREIGN KEY (mentor_id) REFERENCES users(id)
);

-- Insert Sample Admin
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('admin', MD5('admin123'), 'System Admin', 'admin@mentorbook.com', 'admin');

-- Insert Sample Mentor
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('mentor1', MD5('mentor123'), 'John Smith', 'john@mentorbook.com', 'mentor');

-- Insert Sample Student
INSERT INTO users (username, password, full_name, email, role) 
VALUES ('student1', MD5('student123'), 'Alice Johnson', 'alice@mentorbook.com', 'student');

-- Link student details
INSERT INTO student_details (user_id, roll_number, class, section, parent_phone, mentor_id) 
VALUES (3, '2024001', '10th', 'A', '9876543210', 2);