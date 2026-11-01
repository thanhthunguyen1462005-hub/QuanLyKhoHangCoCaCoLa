USE `quanlykhohang_db`;

CREATE TABLE IF NOT EXISTS `production_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_code` VARCHAR(40) NOT NULL UNIQUE,
    `product_id` INT NOT NULL,
    `planned_quantity` INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLANNED',
    `created_by` INT NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_production_plans_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_production_plans_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_production_plans_dates` (`start_date`, `end_date`),
    INDEX `idx_production_plans_status` (`status`),
    INDEX `idx_production_plans_created` (`created_at`)
) ENGINE=InnoDB;
