-- FinTrack Expense Tracker — Database Setup
-- Run in phpMyAdmin > SQL tab, or: mysql -u root -p < db_setup.sql

CREATE DATABASE IF NOT EXISTS expense CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE expense;

CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) DEFAULT NULL,
    role        ENUM('admin','user') DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS records (
    id          VARCHAR(36) PRIMARY KEY,
    user_id     INT NOT NULL,
    type        ENUM('expense','earning') NOT NULL,
    item        VARCHAR(255) NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    category    VARCHAR(50) NOT NULL DEFAULT 'Other',
    note        TEXT DEFAULT NULL,
    date        DATE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: username=admin, password=admin123
-- Hash generated with: password_hash('admin123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, password, full_name, email, role)
VALUES (
    'admin',
    '$2y$10$TKh8H1.PfbuNIR1HBUPd9.7oTqxj8e.o/ADxTn3c0PfVz3aBvGzy2',
    'Administrator',
    'admin@fintrack.com',
    'admin'
);

-- NOTE: If the above hash doesn't work, run this PHP to generate a new one:
-- <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>
-- Then UPDATE users SET password='<new_hash>' WHERE username='admin';
