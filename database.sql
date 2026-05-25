-- IT Asset Management System - One-time import database
-- Fixed for older MySQL/MariaDB/WAMP index limit (#1071)
-- Default login: admin / admin123

CREATE DATABASE IF NOT EXISTS it_asset_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE it_asset_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS no_serial_items;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 username VARCHAR(50) NOT NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_description VARCHAR(150) NOT NULL,
 serial_number VARCHAR(80) NULL COMMENT 'Serialized asset serial or general item code for non-serialized stock items.',
 location VARCHAR(80) DEFAULT NULL,
 uom VARCHAR(20) DEFAULT 'Pc',
 boh INT NOT NULL DEFAULT 0,
 total_received INT NOT NULL DEFAULT 0,
 total_issued INT NOT NULL DEFAULT 0,
 total_returned INT NOT NULL DEFAULT 0,
 actual_stock INT NOT NULL DEFAULT 0,
 status ENUM('Available','Issued','Low Stock','Out of Stock') NOT NULL DEFAULT 'Available',
 current_co VARCHAR(100) DEFAULT NULL,
 reorder_level INT NOT NULL DEFAULT 5,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_items_serial_number (serial_number),
 KEY idx_items_description (item_description),
 KEY idx_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE no_serial_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_description VARCHAR(150) NOT NULL,
 item_code VARCHAR(80) NOT NULL COMMENT 'Auto-generated general item code for non-serialized stock transactions',
 default_location VARCHAR(80) DEFAULT NULL,
 uom VARCHAR(20) DEFAULT 'Pc',
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_no_serial_description (item_description),
 UNIQUE KEY uq_no_serial_code (item_code),
 CONSTRAINT fk_no_serial_created_by
   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_id INT NULL,
 serial_number VARCHAR(80) NULL,
 item_description VARCHAR(150) NOT NULL,
 action_type ENUM('Received','Issued','Returned','Adjusted') NOT NULL,
 quantity INT NOT NULL DEFAULT 1,
 pic VARCHAR(100) DEFAULT NULL,
 location VARCHAR(80) DEFAULT NULL,
 week_no VARCHAR(20) GENERATED ALWAYS AS (
   CONCAT('Week ', WEEK(created_at, 1) - WEEK(DATE_SUB(created_at, INTERVAL DAYOFMONTH(created_at)-1 DAY), 1) + 1)
 ) STORED,
 remarks TEXT NULL,
 created_by INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_transactions_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
 CONSTRAINT fk_transactions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_action_date (action_type, created_at),
 INDEX idx_serial (serial_number),
 INDEX idx_pic (pic),
 INDEX idx_item_description (item_description),
 INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, username, password, role) VALUES
('System Administrator','admin','$2y$12$eTualBAnaF3vIU5JxGgYPu7SJ6szaLMxCEmQb5g38E05S3CpEGn0y','admin'),
('Staff User','staff','$2y$12$eTualBAnaF3vIU5JxGgYPu7SJ6szaLMxCEmQb5g38E05S3CpEGn0y','staff'),
('Viewer User','viewer','$2y$12$eTualBAnaF3vIU5JxGgYPu7SJ6szaLMxCEmQb5g38E05S3CpEGn0y','viewer');

INSERT INTO items (item_description, serial_number, location, uom, boh, total_received, total_issued, total_returned, actual_stock, reorder_level, status) VALUES
('Dell Pro 14 Laptop','PBC123','Rack 1','Unit',0,5,0,0,5,2,'Available'),
('USB Mouse','5718185','Cabinet 1','Pc',0,50,0,0,50,10,'Available');

INSERT INTO no_serial_items (item_description, item_code, default_location, uom) VALUES
('AA Battery','NS-AA-BATTERY','Storage Room','Pc'),
('Sign Here (Post it)','NS-SIGN-HERE-POST-IT','Storage Room','Pack'),
('Post it','NS-POST-IT','Storage Room','Pack'),
('A4 Bond paper','NS-A4-BOND-PAPER','Storage Room','Pack'),
('Alcohol 473ml','NS-ALCOHOL-473ML','Storage Room','Pc'),
('Dell Bag','NS-DELL-BAG','Cabinet 1','Pc'),
('Dell Wireless Mouse','NS-DELL-WIRELESS-MOUSE','Cabinet 1','Pc'),
('Jabra Evolve 20 Headphone','NS-JABRA-EVOLVE-20-HEADPHONE','Cabinet 1','Pc'),
('Permanent Marker EK-700A','NS-PERMANENT-MARKER-EK-700A','Storage Room','Pc'),
('Microfiber Towel','NS-MICROFIBER-TOWEL','Storage Room','Pc'),
('Stapler','NS-STAPLER','Storage Room','Pc'),
('Double sided tape 1"','NS-DOUBLE-SIDED-TAPE-1','Storage Room','Pc'),
('Packaging tape 2"','NS-PACKAGING-TAPE-2','Storage Room','Pc'),
('Masking tape 1/2"','NS-MASKING-TAPE-1-2','Storage Room','Pc'),
('Card case A4','NS-CARD-CASE-A4','Storage Room','Pc'),
('Clear tape 1"','NS-CLEAR-TAPE-1','Storage Room','Pc'),
('L Type plastic folder A4','NS-L-TYPE-PLASTIC-FOLDER-A4','Storage Room','Pc'),
('Clip board','NS-CLIP-BOARD','Storage Room','Pc'),
('Scissors','NS-SCISSORS','Storage Room','Pc'),
('Masking tape 1','NS-MASKING-TAPE-1','Storage Room','Pc'),
('Sticker paper','NS-STICKER-PAPER','Storage Room','Pack');

INSERT INTO items (item_description, serial_number, location, uom, boh, total_received, total_issued, total_returned, actual_stock, reorder_level, status)
SELECT item_description, item_code, default_location, uom, 0, 0, 0, 0, 0, 5, 'Available'
FROM no_serial_items
ON DUPLICATE KEY UPDATE
 item_description=VALUES(item_description),
 location=VALUES(location),
 uom=VALUES(uom);
