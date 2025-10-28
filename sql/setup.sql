-- Village Health Connect Database Schema - Complete Version
-- Run this in phpMyAdmin to create the database

CREATE DATABASE IF NOT EXISTS village_health_connect;
USE village_health_connect;

-- Users table with all roles
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','villager','avms','doctor') NOT NULL,
    phone VARCHAR(20),
    village VARCHAR(100),
    status ENUM('active','inactive','pending') DEFAULT 'pending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Problems table with enhanced workflow
CREATE TABLE problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    villager_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    photo VARCHAR(255),
    location VARCHAR(100),
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','assigned','in_progress','resolved','escalated','completed','closed') DEFAULT 'pending',
    assigned_to INT,
    escalated_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (villager_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (escalated_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Problem updates/history for tracking all changes
CREATE TABLE problem_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id INT NOT NULL,
    updated_by INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    notes TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
);


-- Notifications system
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
);

-- Medical advice/responses from doctors (aligned with application fields)
CREATE TABLE medical_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id INT NOT NULL,
    doctor_id INT NOT NULL,
    response TEXT NOT NULL,
    recommendations TEXT,
    follow_up_required TINYINT(1) DEFAULT 0,
    urgency_level ENUM('low','medium','high','critical') DEFAULT 'medium',
    status ENUM('draft','submitted','revised') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (name, email, password, role, status, village) 
VALUES ('System Admin', 'admin@villagehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 'All Villages');

-- Insert sample AVMS member for testing
INSERT INTO users (name, email, password, role, status, village, phone) 
VALUES ('AVMS Officer', 'avms@villagehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'avms', 'active', 'Main Village', '9876543210');

-- Insert sample doctor for testing
INSERT INTO users (name, email, password, role, status, village, phone) 
VALUES ('Dr. Kumar', 'doctor@villagehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 'Health Center', '9876543211');

-- Insert sample villager for testing
INSERT INTO users (name, email, password, role, status, village, phone) 
VALUES ('Village User', 'villager@villagehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'villager', 'active', 'Main Village', '9876543212');

-- Sample problem data
INSERT INTO problems (villager_id, title, description, category, priority, status) 
VALUES (4, 'High Fever and Headache', 'My child has been having high fever (102°F) and severe headache for the past 2 days. Need immediate medical attention.', 'health', 'high', 'pending');

-- Default password for all accounts is: password
