-- Patient Hub Database Schema
-- Run: mysql -u root -p < db.sql

CREATE DATABASE IF NOT EXISTS patient_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE patient_hub;

-- Raw incoming requests log
CREATE TABLE IF NOT EXISTS raw_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    raw_data LONGTEXT NOT NULL,
    data_format ENUM('hl7','xml') NOT NULL,
    sender_ip VARCHAR(45) NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_received_at (received_at),
    INDEX idx_sender_ip (sender_ip)
) ENGINE=InnoDB;

-- Processed patient data (EAV model)
CREATE TABLE IF NOT EXISTS patient_data (
    id INT NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    field_value TEXT,
    PRIMARY KEY (id, field_name),
    INDEX idx_id (id),
    INDEX idx_field_name (field_name)
) ENGINE=InnoDB;

-- Pending patient queue
CREATE TABLE IF NOT EXISTS pending_patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    patient_name VARCHAR(255),
    status ENUM('pending','allocated','cancelled','discharged') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    FOREIGN KEY (request_id) REFERENCES raw_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Beds
CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_name VARCHAR(50) NOT NULL,
    is_occupied TINYINT(1) NOT NULL DEFAULT 0,
    occupied_by INT DEFAULT NULL,
    INDEX idx_is_occupied (is_occupied)
) ENGINE=InnoDB;

-- Outgoing messages log
CREATE TABLE IF NOT EXISTS sent_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pending_patient_id INT NOT NULL,
    hl7_message LONGTEXT NOT NULL,
    event_type ENUM('bed_allocation','cancellation') NOT NULL,
    allocated_bed VARCHAR(50) DEFAULT NULL,
    cancel_reason TEXT DEFAULT NULL,
    destination_response TEXT DEFAULT NULL,
    response_status ENUM('success','failure','pending') NOT NULL DEFAULT 'pending',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sent_at (sent_at),
    INDEX idx_event_type (event_type),
    FOREIGN KEY (pending_patient_id) REFERENCES pending_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Error log
CREATE TABLE IF NOT EXISTS error_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_message TEXT NOT NULL,
    error_context VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_manager TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default beds (1-10)
INSERT INTO beds (bed_name) VALUES
('Bed 1'), ('Bed 2'), ('Bed 3'), ('Bed 4'), ('Bed 5'),
('Bed 6'), ('Bed 7'), ('Bed 8'), ('Bed 9'), ('Bed 10');

-- Insert default manager user (password: hunedoara)
INSERT INTO users (username, password_hash, is_manager) VALUES
('manager', '$2y$12$MOF/rw4.yAso5cVh/iALMuEjgT2ynQVBlm.b7FuQNSNJFgw6kpg9S', 1);
