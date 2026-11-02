-- Chạy MỘT LẦN sau database/schema.sql; không xóa dữ liệu hiện có.
USE `quanlykhohang_db`;
CREATE TABLE IF NOT EXISTS `qc_requests` (
 `id` INT AUTO_INCREMENT PRIMARY KEY,
 `request_code` VARCHAR(50) NOT NULL UNIQUE,
 `lot_id` INT NOT NULL,
 `status` ENUM('PENDING','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
 `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (`lot_id`) REFERENCES `product_lots`(`id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `qc_criteria` (
 `id` INT AUTO_INCREMENT PRIMARY KEY,
 `product_id` INT NOT NULL,
 `criterion_name` VARCHAR(150) NOT NULL,
 `is_required` TINYINT(1) NOT NULL DEFAULT 1,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
 UNIQUE KEY `uq_qc_criterion` (`product_id`,`criterion_name`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `qc_criterion_results` (
 `inspection_id` INT NOT NULL,
 `criterion_id` INT NOT NULL,
 `result` ENUM('PASSED','REJECTED') NOT NULL,
 `notes` VARCHAR(500) NULL,
 PRIMARY KEY (`inspection_id`,`criterion_id`),
 FOREIGN KEY (`inspection_id`) REFERENCES `qc_inspections`(`id`) ON DELETE CASCADE,
 FOREIGN KEY (`criterion_id`) REFERENCES `qc_criteria`(`id`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `qc_request_inspections` (
 `request_id` INT NOT NULL PRIMARY KEY,
 `inspection_id` INT NOT NULL UNIQUE,
 FOREIGN KEY (`request_id`) REFERENCES `qc_requests`(`id`),
 FOREIGN KEY (`inspection_id`) REFERENCES `qc_inspections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Phiếu mẫu cho các lô nguyên liệu; chạy lại không tạo trùng.
INSERT INTO `qc_requests` (`request_code`,`lot_id`)
 SELECT CONCAT('YC-', l.id),l.id FROM `product_lots` l JOIN `products` p ON p.id=l.product_id
 WHERE p.type='RAW_MATERIAL' AND NOT EXISTS (SELECT 1 FROM `qc_requests` r WHERE r.request_code=CONCAT('YC-',l.id));
INSERT IGNORE INTO `qc_criteria` (`product_id`,`criterion_name`)
 SELECT id, 'Ngoại quan' FROM `products` WHERE type='RAW_MATERIAL';
INSERT IGNORE INTO `qc_criteria` (`product_id`,`criterion_name`)
 SELECT id, 'Chất lượng theo quy cách' FROM `products` WHERE type='RAW_MATERIAL';
INSERT IGNORE INTO `qc_criteria` (`product_id`,`criterion_name`)
 SELECT id, 'Bao bì / tình trạng bảo quản' FROM `products` WHERE type='RAW_MATERIAL';
-- Lưu ý: các tên tiêu chí trên chỉ là MẪU; thay bằng quy chuẩn thực tế của nguyên liệu.
