USE it_asset_db;

CREATE TABLE IF NOT EXISTS no_serial_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_description VARCHAR(150) NOT NULL,
 item_code VARCHAR(80) NOT NULL COMMENT 'Auto-generated general item code for non-serialized stock transactions',
 default_location VARCHAR(80) DEFAULT NULL,
 uom VARCHAR(20) DEFAULT 'Pc',
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_no_serial_description (item_description),
 UNIQUE KEY uq_no_serial_code (item_code),
 CONSTRAINT fk_no_serial_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO items (item_description, serial_number, location, uom, boh, total_received, actual_stock, reorder_level, status)
SELECT item_description, item_code, default_location, uom, 0, 0, 0, 5, 'Available' FROM no_serial_items
ON DUPLICATE KEY UPDATE item_description=VALUES(item_description), location=VALUES(location), uom=VALUES(uom);
