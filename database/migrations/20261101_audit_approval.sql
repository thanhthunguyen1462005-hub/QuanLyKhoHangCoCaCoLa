USE `quanlykhohang_db`;

ALTER TABLE `inventory_audits`
    MODIFY COLUMN `status` ENUM(
        'IN_PROGRESS', 'WAITING_MANAGER', 'WAITING_DIRECTOR', 'APPROVED', 'BALANCED', 'REJECTED'
    ) DEFAULT 'IN_PROGRESS',
    ADD COLUMN `approved_at` DATETIME NULL,
    ADD COLUMN `rejected_by_director` INT NULL,
    ADD COLUMN `rejected_at` DATETIME NULL,
    ADD COLUMN `rejection_reason` TEXT NULL,
    ADD CONSTRAINT `fk_inventory_audits_rejected_by`
        FOREIGN KEY (`rejected_by_director`) REFERENCES `users` (`id`);

ALTER TABLE `inventory_audit_items`
    ADD COLUMN `recommended_action` TEXT NULL;

CREATE TABLE `in_app_notifications` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `audit_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

INSERT INTO `inventory_audits`
    (`audit_code`, `warehouse_id`, `title`, `audit_date`, `created_by`, `verified_by`, `status`, `notes`)
SELECT
    'KK-2026-11-01', w.`id`, 'Biên Bản Kiểm Kê Kho Đường & Siro - Tháng 11/2026',
    '2026-11-01', staff.`id`, manager.`id`, 'WAITING_DIRECTOR',
    'Biên bản mẫu đã được Quản lý kho xác minh.'
FROM `warehouses` w
INNER JOIN `users` staff ON staff.`username` = 'thukho'
INNER JOIN `users` manager ON manager.`username` = 'quanlykho'
WHERE w.`code` = 'KNL-01'
  AND NOT EXISTS (
      SELECT 1 FROM `inventory_audits` WHERE `audit_code` = 'KK-2026-11-01'
  );

INSERT INTO `inventory_audit_items`
    (`audit_id`, `product_id`, `lot_id`, `location_id`, `system_quantity`, `actual_quantity`,
     `discrepancy_reason`, `recommended_action`)
SELECT
    a.`id`, p.`id`, l.`id`, wl.`id`, i.`quantity`, GREATEST(i.`quantity` - 2, 0),
    'Chênh lệch cân đối chiếu khi nhập nguyên liệu pha chế',
    'Điều chỉnh giảm tồn kho 2 Kg sau khi được Ban Giám đốc phê duyệt.'
FROM `inventory_audits` a
INNER JOIN `warehouses` w ON w.`id` = a.`warehouse_id`
INNER JOIN `inventory` i ON i.`warehouse_id` = w.`id`
INNER JOIN `products` p ON p.`id` = i.`product_id` AND p.`sku` = 'NVL-SIRO-01'
INNER JOIN `product_lots` l ON l.`id` = i.`lot_id` AND l.`lot_number` = 'LOT-SIRO-2026-110'
INNER JOIN `warehouse_locations` wl ON wl.`id` = i.`location_id`
WHERE a.`audit_code` = 'KK-2026-11-01'
  AND NOT EXISTS (
      SELECT 1 FROM `inventory_audit_items` WHERE `audit_id` = a.`id`
  );
