-- =============================================================
-- Taxi Financial Tracker – Database Schema
-- =============================================================
-- Run this file against your MySQL server to initialise the DB:
--   mysql -u root -p < schema.sql
-- Or use setup.php from your browser for an automatic install.
-- =============================================================

CREATE DATABASE IF NOT EXISTS taxi_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE taxi_tracker;

-- ------------------------------------------------------------------
-- Users
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT            AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100)   NOT NULL UNIQUE,
    password   VARCHAR(255)   NOT NULL,
    role       ENUM('admin','partner') NOT NULL DEFAULT 'partner',
    full_name  VARCHAR(200)   DEFAULT NULL,
    created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- Incomes  (daily revenue)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incomes (
    id          INT            AUTO_INCREMENT PRIMARY KEY,
    amount      DECIMAL(10,2)  NOT NULL,
    description VARCHAR(500)   DEFAULT NULL,
    date        DATE           NOT NULL,
    created_by  INT            DEFAULT NULL,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_incomes_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- Outcomes  (daily expenses)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS outcomes (
    id          INT            AUTO_INCREMENT PRIMARY KEY,
    amount      DECIMAL(10,2)  NOT NULL,
    description VARCHAR(500)   NOT NULL,
    date        DATE           NOT NULL,
    created_by  INT            DEFAULT NULL,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_outcomes_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- Transfers  (Admin → Partner payments)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transfers (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    amount       DECIMAL(10,2)  NOT NULL,
    partner_id   INT            DEFAULT NULL,
    partner_name VARCHAR(200)   NOT NULL,
    notes        VARCHAR(500)   DEFAULT NULL,
    created_by   INT            DEFAULT NULL,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transfers_partner FOREIGN KEY (partner_id)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transfers_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- Audit Logs  (records every admin write/update/delete action)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id                   INT  AUTO_INCREMENT PRIMARY KEY,
    action_type          ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    table_name           VARCHAR(100)  NOT NULL,
    record_id            INT           DEFAULT NULL,
    original_data        TEXT          DEFAULT NULL,
    modified_data        TEXT          DEFAULT NULL,
    performed_by         INT           DEFAULT NULL,
    performed_by_username VARCHAR(100) DEFAULT NULL,
    created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------
-- Default admin account
-- IMPORTANT: Do NOT run this INSERT manually – the placeholder hash
-- is not a valid bcrypt hash.
-- Use setup.php instead: it creates the admin account with a proper
-- password_hash()-generated hash automatically.
-- Default credentials set by setup.php:  admin / admin123
-- Change the password immediately after first login!
-- ------------------------------------------------------------------
-- INSERT is intentionally omitted here; run setup.php.
