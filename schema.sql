-- =========================================================
-- VitalCheck BMI Calculator — Database Schema
-- =========================================================

CREATE DATABASE IF NOT EXISTS health_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE health_tracker;

CREATE TABLE IF NOT EXISTS bmi_records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,                 -- optional, for future login/auth support
    height      DECIMAL(5,2) NOT NULL,              -- height in cm
    weight      DECIMAL(5,2) NOT NULL,              -- weight in kg
    age         TINYINT UNSIGNED NOT NULL,
    gender      ENUM('male', 'female', 'other') NOT NULL,
    bmi_value   DECIMAL(5,2) NOT NULL,
    category    ENUM('Underweight', 'Normal', 'Overweight', 'Obese') NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- Optional: sample seed data for testing the History section / chart
-- INSERT INTO bmi_records (height, weight, age, gender, bmi_value, category, created_at) VALUES
-- (170.0, 65.0, 28, 'male',   22.49, 'Normal',      NOW() - INTERVAL 6 DAY),
-- (170.0, 70.0, 28, 'male',   24.22, 'Normal',      NOW() - INTERVAL 4 DAY),
-- (170.0, 78.0, 28, 'male',   26.99, 'Overweight',  NOW() - INTERVAL 2 DAY),
-- (170.0, 75.0, 28, 'male',   25.95, 'Overweight',  NOW());
