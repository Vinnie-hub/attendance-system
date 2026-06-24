-- Employee Attendance Management System
-- Database Schema

CREATE DATABASE IF NOT EXISTS attendance_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE attendance_db;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(120)  NOT NULL,
  email       VARCHAR(180)  NOT NULL UNIQUE,
  password    VARCHAR(255)  NOT NULL,
  role        ENUM('admin','employee') NOT NULL DEFAULT 'employee',
  department  VARCHAR(100)  DEFAULT NULL,
  position    VARCHAR(100)  DEFAULT NULL,
  phone       VARCHAR(30)   DEFAULT NULL,
  avatar      VARCHAR(255)  DEFAULT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: work_schedule
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS work_schedule (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_name VARCHAR(80)  NOT NULL,
  start_time    TIME         NOT NULL DEFAULT '08:00:00',
  end_time      TIME         NOT NULL DEFAULT '17:00:00',
  grace_minutes INT          NOT NULL DEFAULT 15,
  is_default    TINYINT(1)  NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO work_schedule (schedule_name, start_time, end_time, grace_minutes, is_default)
VALUES ('Standard (8 AM – 5 PM)', '08:00:00', '17:00:00', 15, 1);

-- ------------------------------------------------------------
-- Table: attendance
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  attendance_date DATE         NOT NULL,
  check_in_time   DATETIME     DEFAULT NULL,
  check_out_time  DATETIME     DEFAULT NULL,
  check_in_lat    DECIMAL(10,7) DEFAULT NULL,
  check_in_lng    DECIMAL(10,7) DEFAULT NULL,
  check_out_lat   DECIMAL(10,7) DEFAULT NULL,
  check_out_lng   DECIMAL(10,7) DEFAULT NULL,
  status          ENUM('on_time','late','absent','half_day','full_day') NOT NULL DEFAULT 'absent',
  work_hours      DECIMAL(5,2) DEFAULT NULL,
  notes           TEXT          DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_date (user_id, attendance_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: qr_tokens  (daily QR code sessions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS qr_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  action     ENUM('check_in','check_out') NOT NULL,
  valid_date DATE         NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: office_location  (GPS fence)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS office_location (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  latitude    DECIMAL(10,7) NOT NULL,
  longitude   DECIMAL(10,7) NOT NULL,
  radius_m    INT          NOT NULL DEFAULT 700,
  is_active   TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO office_location (name, latitude, longitude, radius_m)
VALUES ('ICT Department Service Desk (Siriba Branch)', -0.003496, 34.610503, 700);   -- Siriba Campus, Maseno

-- ------------------------------------------------------------
-- Seed: default admin account
-- Password: Admin@1234  (bcrypt)
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, password, role, department, position)
VALUES (
  'System Administrator',
  'admin@company.com',
  '$2y$10$7hjhs8ekF5Rdldh3OBxl.eyX8qOLJ1TsyBKwAJM/nis2AJgH25J8S', -- Admin@1234
  'admin',
  'Management',
  'HR Administrator'
);

-- ------------------------------------------------------------
-- Seed: sample employees
-- Password for all: Pass@1234
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, password, role, department, position) VALUES
('Alice Mwangi',   'alice@company.com',   '$2y$10$Sl/Mwx15eur.75Zn6dhbc.jVEQ5HExM/0ZGupFFUCuOhELQr4Bqr6', 'employee', 'Engineering', 'Software Developer'),
('Brian Otieno',   'brian@company.com',   '$2y$10$Sl/Mwx15eur.75Zn6dhbc.jVEQ5HExM/0ZGupFFUCuOhELQr4Bqr6', 'employee', 'Design',      'UI/UX Designer'),
('Carol Njeri',    'carol@company.com',   '$2y$10$Sl/Mwx15eur.75Zn6dhbc.jVEQ5HExM/0ZGupFFUCuOhELQr4Bqr6', 'employee', 'Finance',     'Accountant'),
('David Kamau',    'david@company.com',   '$2y$10$Sl/Mwx15eur.75Zn6dhbc.jVEQ5HExM/0ZGupFFUCuOhELQr4Bqr6', 'employee', 'Sales',       'Sales Executive');
