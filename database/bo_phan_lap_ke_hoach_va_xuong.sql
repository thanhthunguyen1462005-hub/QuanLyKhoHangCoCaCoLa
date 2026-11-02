-- Module: Lap ke hoach san xuat + Xuong san xuat
-- Source: quanlykhohang_db(1).sql (02/11/2026)
-- IMPORTANT: Backup database before importing. Import into existing database.
-- No DROP/TRUNCATE; no existing user accounts, passwords, or operational records imported.
-- Requires existing products, users, roles tables and MySQL/MariaDB.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `workshops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workshop_code` varchar(20) NOT NULL,
  `workshop_name` varchar(100) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_workshop_code` (`workshop_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO workshops (workshop_code,workshop_name,status) VALUES
('PX01','Phân xưởng 1','ACTIVE'),('PX02','Phân xưởng 2','ACTIVE'),
('PX03','Phân xưởng 3','ACTIVE'),('PX04','Phân xưởng 4','ACTIVE'),
('PX05','Phân xưởng 5','ACTIVE')
ON DUPLICATE KEY UPDATE workshop_name=VALUES(workshop_name);

CREATE TABLE IF NOT EXISTS `production_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_code` varchar(50) DEFAULT NULL,
  `workshop_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_code` (`plan_code`),
  KEY `idx_plan_product` (`product_id`),
  KEY `idx_plan_workshop` (`workshop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `production_bom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_per_unit` decimal(18,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_material` (`product_id`,`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `material_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_code` varchar(50) NOT NULL,
  `production_plan_id` int(11) NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','FULFILLED') NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `needed_date` date DEFAULT NULL,
  `priority` enum('NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_code` (`request_code`),
  KEY `idx_request_plan` (`production_plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `material_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `requested_quantity` decimal(18,6) NOT NULL,
  `quantity_per_unit` decimal(18,6) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request_material` (`request_id`,`material_id`),
  KEY `idx_request_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add only missing columns, without replacing existing users or plans.
DELIMITER $$
DROP PROCEDURE IF EXISTS `pw_add_column_if_missing`$$
CREATE PROCEDURE `pw_add_column_if_missing`(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @pw_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE pw_stmt FROM @pw_sql;
    EXECUTE pw_stmt;
    DEALLOCATE PREPARE pw_stmt;
  END IF;
END$$
DELIMITER ;

CALL pw_add_column_if_missing('users','workshop_id','INT(11) NULL');
CALL pw_add_column_if_missing('production_plans','order_code','VARCHAR(50) NULL');
CALL pw_add_column_if_missing('production_plans','workshop_id','INT(11) NULL');
CALL pw_add_column_if_missing('material_requests','needed_date','DATE NULL');
CALL pw_add_column_if_missing('material_requests','priority',"ENUM('NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL'");
CALL pw_add_column_if_missing('material_requests','created_by','VARCHAR(100) NULL');
CALL pw_add_column_if_missing('material_request_items','quantity_per_unit','DECIMAL(18,6) NULL');
CALL pw_add_column_if_missing('material_request_items','unit','VARCHAR(50) NULL');
DROP PROCEDURE IF EXISTS `pw_add_column_if_missing`;

-- Optional demo BOM from source, resolved by SKU (not hard-coded IDs).
-- Adds missing lines only; does not overwrite the leader's existing BOM.
INSERT INTO production_bom (product_id,material_id,quantity_per_unit)
SELECT p.id, m.id, x.qty
FROM (
  SELECT 'NVL-SIRO-01' AS sku, CAST(0.500000 AS DECIMAL(18,6)) AS qty
  UNION ALL SELECT 'NVL-COCA-01', 0.050000
  UNION ALL SELECT 'NVL-CO2-01', 0.010000
  UNION ALL SELECT 'BB-PET-01', 24.000000
) AS x
JOIN products p ON p.sku='TP-COCA-500' AND p.type='FINISHED_GOODS'
JOIN products m ON m.sku=x.sku AND m.type='RAW_MATERIAL'
WHERE NOT EXISTS (
  SELECT 1 FROM production_bom b WHERE b.product_id=p.id AND b.material_id=m.id
);

-- Existing users are NOT inserted/updated. Assign each workshop account separately
-- after checking actual usernames and role mappings in leader's database.
-- Existing production plans and material requests are NOT imported.
-- Foreign keys are not forced onto pre-existing tables: check existing constraints
-- and data consistency with the leader before adding them.
