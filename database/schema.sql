-- ==============================================================================
-- CƠ SỞ DỮ LIỆU: QUẢN LÝ KHO NHÀ MÁY SẢN XUẤT NƯỚC GIẢI KHÁT
-- Thỏa mãn yêu cầu chức năng FR-01 -> FR-20 và phi chức năng NFR-01 -> NFR-12
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `quanlykhohang_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `quanlykhohang_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `in_app_notifications`;
DROP TABLE IF EXISTS `inventory_audit_items`;
DROP TABLE IF EXISTS `inventory_audits`;
DROP TABLE IF EXISTS `stock_transfer_items`;
DROP TABLE IF EXISTS `stock_transfers`;
DROP TABLE IF EXISTS `stock_issue_items`;
DROP TABLE IF EXISTS `stock_issues`;
DROP TABLE IF EXISTS `stock_receipt_items`;
DROP TABLE IF EXISTS `stock_receipts`;
DROP TABLE IF EXISTS `qc_inspections`;
DROP TABLE IF EXISTS `sales_order_items`;
DROP TABLE IF EXISTS `sales_orders`;
DROP TABLE IF EXISTS `production_request_items`;
DROP TABLE IF EXISTS `production_requests`;
DROP TABLE IF EXISTS `po_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `product_lots`;
DROP TABLE IF EXISTS `warehouse_locations`;
DROP TABLE IF EXISTS `warehouse_zones`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. BẢNG PHÂN QUYỀN & TÀI KHOẢN (FR-01, FR-20, NFR-06)
-- ------------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `roles` (`code`, `name`, `description`) VALUES
('admin', 'Quản trị hệ thống', 'Toàn quyền cấu hình, quản lý tài khoản và phân quyền'),
('director', 'Ban Giám đốc', 'Xem Dashboard, báo cáo KPI, phê duyệt biên bản kiểm kê cân bằng kho'),
('manager', 'Quản lý kho', 'Phê duyệt phiếu nhập/xuất/chuyển kho, điều phối, xác minh kiểm kê'),
('staff', 'Nhân viên kho', 'Thực thi nhập/xuất kho, xếp kệ, quét mã vạch, kiểm đếm thực tế'),
('qc', 'QA/QC Chất lượng', 'Kiểm tra chất lượng nguyên liệu nhập, thành phẩm xưởng, hàng lỗi'),
('procurement', 'Bộ phận Mua hàng', 'Quản lý Nhà cung cấp, tạo Đơn mua hàng (PO)'),
('workshop', 'Xưởng sản xuất', 'Gửi yêu cầu xuất nguyên liệu và yêu cầu nhập thành phẩm'),
('planning', 'Lập kế hoạch', 'Theo dõi tồn kho, lập kế hoạch sản xuất'),
('sales', 'Bộ phận Bán hàng', 'Tạo đơn đặt hàng bán thành phẩm làm căn cứ xuất hàng');

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Mật khẩu mặc định là: 123456 (hash bcrypt)
INSERT INTO `users` (`role_id`, `username`, `password`, `fullname`, `email`, `phone`) VALUES
(1, 'admin', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Vũ Quản Trị Hệ Thống', 'admin@factory.com', '0901000001'),
(2, 'giamdoc', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Trần Tổng Giám Đốc', 'director@factory.com', '0901000002'),
(3, 'quanlykho', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Lê Quản Lý Kho', 'warehouse.manager@factory.com', '0901000003'),
(4, 'thukho', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Nguyễn Nhân Viên Thủ Kho', 'thukho@factory.com', '0901000004'),
(5, 'qaqc', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Phạm Kiểm Định QA/QC', 'qc@factory.com', '0901000005'),
(6, 'muahang', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Hoàng Nhân Viên Mua Hàng', 'procurement@factory.com', '0901000006'),
(7, 'xuong1', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Đặng Quản Đốc Xưởng 1', 'workshop@factory.com', '0901000007'),
(8, 'kehoach', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Bùi Kỹ Sư Kế Hoạch', 'planning@factory.com', '0901000008'),
(9, 'banhang', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Đỗ Chuyên Viên Bán Hàng', 'sales@factory.com', '0901000009');

-- ------------------------------------------------------------------------------
-- 2. BẢNG HỆ THỐNG KHO & VỊ TRÍ LƯU TRỮ (FR-02, FR-04)
-- ------------------------------------------------------------------------------
CREATE TABLE `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('RAW_MATERIAL', 'FINISHED_GOODS', 'DEFECTIVE_RETURN') NOT NULL,
    `capacity_m3` DECIMAL(12,2) DEFAULT 1000.00,
    `manager_id` INT NULL,
    `location_description` VARCHAR(255) NULL,
    `status` ENUM('ACTIVE', 'MAINTENANCE', 'INACTIVE') DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Khởi tạo đúng 11 Kho theo quy định FR-02 (5 Kho NVL, 5 Kho TP, 1 Kho Lỗi)
INSERT INTO `warehouses` (`code`, `name`, `type`, `capacity_m3`, `location_description`) VALUES
('KNL-01', 'Kho Đường tinh luyện & siro nền', 'RAW_MATERIAL', 2500, 'Khu A - nguyên liệu đường, siro'),
('KNL-02', 'Kho Nước cốt & hương liệu đồ uống', 'RAW_MATERIAL', 1800, 'Khu A - kho mát, kiểm soát nhiệt độ'),
('KNL-03', 'Kho CO₂ thực phẩm & phụ gia', 'RAW_MATERIAL', 800, 'Khu an toàn bình khí và phụ gia'),
('KNL-04', 'Kho Chai PET, nắp chai & lon nhôm', 'RAW_MATERIAL', 3000, 'Khu B - bao bì sơ cấp'),
('KNL-05', 'Kho Nhãn chai, màng co & thùng carton', 'RAW_MATERIAL', 2000, 'Khu B - vật liệu đóng gói'),
('KTP-01', 'Kho Nước ngọt chai PET', 'FINISHED_GOODS', 3500, 'Khu xuất hàng chai PET'),
('KTP-02', 'Kho Nước ngọt lon nhôm', 'FINISHED_GOODS', 4000, 'Khu xuất hàng lon'),
('KTP-03', 'Kho Thành phẩm chờ xuất phân phối', 'FINISHED_GOODS', 3000, 'Khu logistics nội địa'),
('KTP-04', 'Kho Thành phẩm chờ kiểm định', 'FINISHED_GOODS', 900, 'Khu cách ly chờ QA/QC'),
('KTP-05', 'Kho Thành phẩm xuất khẩu', 'FINISHED_GOODS', 2800, 'Khu logistics xuất khẩu'),
('KHL-01', 'Kho Cách ly hàng lỗi & hàng trả về', 'DEFECTIVE_RETURN', 1500, 'Khu cách ly chờ QA/QC xử lý');

-- Vị trí ô kệ phân cấp (Kho -> Khu vực -> Dãy -> Kệ -> Ô/Bin - FR-04)
CREATE TABLE `warehouse_zones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `zone_code` VARCHAR(50) NOT NULL,
    `zone_name` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `warehouse_zones` (`warehouse_id`, `zone_code`, `zone_name`) VALUES
(1, 'ZONE-A1', 'Đường tinh luyện và nguyên liệu khô'),
(1, 'ZONE-A2', 'Siro nền và nguyên liệu chờ kiểm tra'),
(6, 'ZONE-TP1', 'Nước ngọt chai PET đóng thùng'),
(11, 'ZONE-ERR', 'Khu chờ QA/QC thẩm định'),
(2, 'ZONE-A3', 'Nước cốt và hương liệu'),
(4, 'ZONE-B1', 'Chai PET và nắp chai'),
(3, 'ZONE-A4', 'CO₂ thực phẩm và phụ gia');

CREATE TABLE `warehouse_locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `zone_id` INT NULL,
    `location_code` VARCHAR(50) NOT NULL UNIQUE, -- VD: KNL01-A-R01-S02 (Kho - Dãy - Kệ - Tầng/Ô)
    `aisle` VARCHAR(20) NOT NULL,  -- Dãy
    `rack` VARCHAR(20) NOT NULL,   -- Kệ
    `shelf` VARCHAR(20) NOT NULL,  -- Tầng
    `bin` VARCHAR(20) NOT NULL,    -- Ô chứa
    `max_weight_kg` DECIMAL(10,2) DEFAULT 1000.00,
    `is_occupied` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`zone_id`) REFERENCES `warehouse_zones`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `warehouse_locations` (`warehouse_id`, `zone_id`, `location_code`, `aisle`, `rack`, `shelf`, `bin`) VALUES
(1, 1, 'KNL01-A-R01-S01-B01', 'A', 'R01', 'S01', 'B01'),
(1, 1, 'KNL01-A-R01-S01-B02', 'A', 'R01', 'S01', 'B02'),
(1, 1, 'KNL01-A-R01-S02-B01', 'A', 'R01', 'S02', 'B01'),
(6, 3, 'KTP01-B-R05-S01-B01', 'B', 'R05', 'S01', 'B01'),
(11, 4, 'KHL01-ERR-R01-B01', 'ERR', 'R01', 'S01', 'B01'),
(2, 5, 'KNL02-A-R01-S01-B01', 'A', 'R01', 'S01', 'B01'),
(4, 6, 'KNL04-B-R01-S01-B01', 'B', 'R01', 'S01', 'B01'),
(3, 7, 'KNL03-A-R01-B03', 'A', 'R01', 'S01', 'B03');

-- ------------------------------------------------------------------------------
-- 3. BẢNG DANH MỤC, HÀNG HÓA, ĐỐI TÁC (FR-03, FR-05)
-- ------------------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('RAW_MATERIAL', 'FINISHED_GOODS') NOT NULL
) ENGINE=InnoDB;

INSERT INTO `categories` (`name`, `code`, `type`) VALUES
('Đường và siro nền', 'CAT-SUGAR-SYRUP', 'RAW_MATERIAL'),
('Nước cốt và hương liệu', 'CAT-CONCENTRATE', 'RAW_MATERIAL'),
('CO₂ thực phẩm và phụ gia', 'CAT-FOOD-GAS', 'RAW_MATERIAL'),
('Bao bì chai PET và lon', 'CAT-BEVERAGE-PACK', 'RAW_MATERIAL'),
('Nước ngọt thành phẩm', 'CAT-SOFT-DRINK', 'FINISHED_GOODS');

CREATE TABLE `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `suppliers` (`code`, `name`, `contact_person`, `phone`, `email`, `address`) VALUES
('NCC-SUGAR', 'Nhà cung cấp Đường tinh luyện', 'Nguyễn Minh An', '02837770001', 'sugar@example.com', 'Khu vực miền Nam'),
('NCC-INGREDIENT', 'Nhà cung cấp nguyên liệu đồ uống', 'Trần Thu Hà', '02837770002', 'ingredient@example.com', 'Khu vực miền Nam'),
('NCC-PACK', 'Nhà cung cấp bao bì thực phẩm', 'Lê Quốc Bảo', '02837770003', 'packaging@example.com', 'Khu vực miền Nam');

CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `customers` (`code`, `name`, `phone`, `email`, `address`) VALUES
('KH-DISTRIBUTOR-01', 'Nhà phân phối nước giải khát miền Nam', '02837771001', 'distribution@example.com', 'TP. Hồ Chí Minh'),
('KH-RETAIL-01', 'Đối tác bán lẻ nước giải khát', '02837771002', 'retail@example.com', 'TP. Hồ Chí Minh');

CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(60) NOT NULL UNIQUE,
    `barcode` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `category_id` INT NOT NULL,
    `unit` VARCHAR(30) NOT NULL, -- kg, m, tấm, cái, bộ
    `type` ENUM('RAW_MATERIAL', 'FINISHED_GOODS') NOT NULL,
    `min_stock` INT DEFAULT 10,
    `max_stock` INT DEFAULT 1000,
    `cost_price` DECIMAL(15,2) DEFAULT 0.00,
    `sale_price` DECIMAL(15,2) DEFAULT 0.00,
    `image_url` VARCHAR(255) NULL,
    `status` ENUM('ACTIVE', 'DISCONTINUED') DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB;

INSERT INTO `products` (`sku`, `barcode`, `name`, `category_id`, `unit`, `type`, `min_stock`, `max_stock`, `cost_price`, `sale_price`) VALUES
('NVL-SIRO-01', '893600001101', 'Đường tinh luyện sản xuất siro', 1, 'Kg', 'RAW_MATERIAL', 500, 10000, 22000, 0),
('NVL-COCA-01', '893600001102', 'Nước cốt cô đặc hương Coca-Cola', 2, 'Kg', 'RAW_MATERIAL', 100, 2000, 180000, 0),
('NVL-CO2-01', '893600001103', 'Khí CO₂ tinh khiết cấp thực phẩm', 3, 'Bình', 'RAW_MATERIAL', 20, 200, 950000, 0),
('BB-PET-01', '893600001104', 'Phôi chai PET dùng cho nước giải khát', 4, 'Cái', 'RAW_MATERIAL', 5000, 100000, 1200, 0),
('TP-COCA-500', '893600001201', 'Coca-Cola Original 500 ml (thùng 24 chai PET)', 5, 'Thùng', 'FINISHED_GOODS', 100, 3000, 85000, 120000);

-- ------------------------------------------------------------------------------
-- 4. BẢNG SỐ LÔ (LOT) & TỒN KHO THỰC TẾ (FR-03, FR-12, FR-13 - FEFO/FIFO)
-- ------------------------------------------------------------------------------
CREATE TABLE `product_lots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `lot_number` VARCHAR(100) NOT NULL, -- Mã số lô hàng
    `supplier_id` INT NULL,
    `mfg_date` DATE NOT NULL,          -- Ngày sản xuất
    `expiry_date` DATE NOT NULL,       -- Hạn sử dụng (FEFO căn cứ vào cột này)
    `qc_status` ENUM('PENDING', 'PASSED', 'REJECTED') DEFAULT 'PASSED',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_product_lot` (`product_id`, `lot_number`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `product_lots` (`product_id`, `lot_number`, `supplier_id`, `mfg_date`, `expiry_date`, `qc_status`) VALUES
(1, 'LOT-SIRO-2026-110', 1, '2026-10-20', '2026-11-20', 'PASSED'),
(1, 'LOT-SIRO-2026-111', 1, '2026-10-28', '2027-01-28', 'PASSED'),
(2, 'LOT-CONC-2026-044', 2, '2026-10-01', '2026-11-12', 'PASSED'),
(3, 'LOT-CO2-2026-044', 2, '2026-10-10', '2027-10-10', 'PASSED'),
(4, 'LOT-PET-2026-090', 3, '2026-09-10', '2028-09-10', 'PASSED'),
(5, 'LOT-CC-2026-108', NULL, '2026-10-15', '2027-05-01', 'PASSED');

-- Bảng lưu trữ số lượng tồn kho thực tế (Multi-Location & Lot Inventory)
CREATE TABLE `inventory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `lot_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `reserved_quantity` INT NOT NULL DEFAULT 0, -- Số lượng đã được giữ cho các phiếu đang chờ xuất
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_stock_pos` (`warehouse_id`, `location_id`, `product_id`, `lot_id`),
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations`(`id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`lot_id`) REFERENCES `product_lots`(`id`)
) ENGINE=InnoDB;

INSERT INTO `inventory` (`warehouse_id`, `location_id`, `product_id`, `lot_id`, `quantity`) VALUES
(1, 1, 1, 1, 420),
(1, 2, 1, 2, 2800),
(2, 6, 2, 3, 240),
(3, 8, 3, 4, 36),
(4, 7, 4, 5, 18000),
(6, 4, 5, 6, 680);

-- ------------------------------------------------------------------------------
-- 5. BẢNG CHỨNG TỪ NGUỒN CĂN CỨ: PO, YÊU CẦU XƯỞNG, ĐƠN HÀNG (FR-06 -> FR-09)
-- ------------------------------------------------------------------------------
CREATE TABLE `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_code` VARCHAR(50) NOT NULL UNIQUE,
    `supplier_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `order_date` DATE NOT NULL,
    `expected_delivery` DATE NULL,
    `status` ENUM('PENDING', 'QC_INSPECTING', 'APPROVED_FOR_RECEIPT', 'COMPLETED', 'CANCELLED') DEFAULT 'PENDING',
    `total_amount` DECIMAL(15,2) DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

INSERT INTO `purchase_orders` (`po_code`, `supplier_id`, `created_by`, `order_date`, `status`, `total_amount`) VALUES
('PO-2026-11-001', 1, 6, '2026-11-01', 'APPROVED_FOR_RECEIPT', 44000000.00);

CREATE TABLE `po_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `ordered_qty` INT NOT NULL,
    `received_qty` INT DEFAULT 0,
    `unit_price` DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

INSERT INTO `po_items` (`po_id`, `product_id`, `ordered_qty`, `received_qty`, `unit_price`) VALUES
(1, 1, 2000, 0, 22000.00);

-- Biên bản kiểm định chất lượng QA/QC (FR-06, FR-08, FR-11)
CREATE TABLE `qc_inspections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `qc_code` VARCHAR(50) NOT NULL UNIQUE,
    `inspection_type` ENUM('INCOMING_MATERIAL', 'PRODUCTION_OUTPUT', 'CUSTOMER_RETURN') NOT NULL,
    `reference_code` VARCHAR(50) NOT NULL, -- Mã PO hoặc Mã Yêu cầu Xưởng
    `inspector_id` INT NOT NULL,
    `inspection_date` DATETIME NOT NULL,
    `result` ENUM('PASSED', 'REJECTED', 'PARTIAL') NOT NULL,
    `passed_quantity` INT NOT NULL DEFAULT 0,
    `rejected_quantity` INT NOT NULL DEFAULT 0,
    `defect_reason` TEXT NULL,
    `notes` TEXT NULL,
    FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

INSERT INTO `qc_inspections` (`qc_code`, `inspection_type`, `reference_code`, `inspector_id`, `inspection_date`, `result`, `passed_quantity`, `rejected_quantity`) VALUES
('QC-2026-11-01', 'INCOMING_MATERIAL', 'PO-2026-11-001', 5, '2026-11-01 09:30:00', 'PASSED', 2000, 0);

-- ------------------------------------------------------------------------------
-- 6. BẢNG PHIẾU NHẬP - XUẤT - ĐIỀU CHUYỂN KHO (FR-06, 07, 08, 09, 10, 11)
-- ------------------------------------------------------------------------------
CREATE TABLE `stock_receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_code` VARCHAR(50) NOT NULL UNIQUE,
    `receipt_type` ENUM('RAW_MATERIAL', 'FINISHED_GOODS', 'DEFECTIVE_RETURN') NOT NULL,
    `reference_doc` VARCHAR(50) NOT NULL, -- Mã PO hoặc Mã Kế hoạch sản xuất
    `qc_inspection_id` INT NULL,
    `warehouse_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'RECEIVED', 'REJECTED') DEFAULT 'PENDING',
    `receipt_date` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `stock_receipt_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_receipt_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `lot_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (`stock_receipt_id`) REFERENCES `stock_receipts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`lot_id`) REFERENCES `product_lots`(`id`),
    FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations`(`id`)
) ENGINE=InnoDB;

-- Phiếu xuất kho (Tích hợp gợi ý FEFO/FIFO)
CREATE TABLE `stock_issues` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `issue_code` VARCHAR(50) NOT NULL UNIQUE,
    `issue_type` ENUM('TO_WORKSHOP', 'TO_CUSTOMER', 'TRANSFER', 'DISPOSAL') NOT NULL,
    `reference_doc` VARCHAR(50) NOT NULL, -- Mã Đơn hàng Sales hoặc Lệnh sản xuất
    `warehouse_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `status` ENUM('DRAFT', 'WAITING_APPROVAL', 'APPROVED', 'ISSUED', 'CANCELLED') DEFAULT 'WAITING_APPROVAL',
    `issue_date` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `stock_issue_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_issue_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `lot_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `requested_quantity` INT NOT NULL,
    `actual_quantity` INT DEFAULT 0,
    FOREIGN KEY (`stock_issue_id`) REFERENCES `stock_issues`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`lot_id`) REFERENCES `product_lots`(`id`),
    FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations`(`id`)
) ENGINE=InnoDB;

-- Phiếu điều chuyển nội bộ giữa 11 Kho (FR-10)
CREATE TABLE `stock_transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transfer_code` VARCHAR(50) NOT NULL UNIQUE,
    `from_warehouse_id` INT NOT NULL,
    `to_warehouse_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `approved_by` INT NULL,
    `status` ENUM('PENDING', 'IN_TRANSIT', 'COMPLETED', 'REJECTED') DEFAULT 'PENDING',
    `transfer_date` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------------------
-- 7. BẢNG KIỂM KÊ & CÂN BẰNG TỒN KHO (FR-15 - Cần BGĐ Phê Duyệt)
-- ------------------------------------------------------------------------------
CREATE TABLE `inventory_audits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `audit_code` VARCHAR(50) NOT NULL UNIQUE,
    `warehouse_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `audit_date` DATE NOT NULL,
    `created_by` INT NOT NULL,
    `verified_by` INT NULL,           -- Quản lý kho xác minh kết quả
    `approved_by_director` INT NULL,  -- Ban Giám đốc phê duyệt
    `status` ENUM('IN_PROGRESS', 'WAITING_MANAGER', 'WAITING_DIRECTOR', 'APPROVED', 'BALANCED', 'REJECTED') DEFAULT 'IN_PROGRESS',
    `approved_at` DATETIME NULL,
    `rejected_by_director` INT NULL,
    `rejected_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`approved_by_director`) REFERENCES `users`(`id`),
    FOREIGN KEY (`rejected_by_director`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `inventory_audit_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `audit_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `lot_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `system_quantity` INT NOT NULL, -- Tồn trên sổ sách
    `actual_quantity` INT NOT NULL, -- Tồn đếm thực tế
    `difference` INT GENERATED ALWAYS AS (`actual_quantity` - `system_quantity`) STORED,
    `discrepancy_reason` VARCHAR(255) NULL,
    `recommended_action` TEXT NULL,
    FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`lot_id`) REFERENCES `product_lots`(`id`),
    FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations`(`id`)
) ENGINE=InnoDB;

INSERT INTO `inventory_audits`
    (`audit_code`, `warehouse_id`, `title`, `audit_date`, `created_by`, `verified_by`, `status`, `notes`)
VALUES
    ('KK-2026-11-01', 1, 'Biên Bản Kiểm Kê Kho Đường & Siro - Tháng 11/2026',
     '2026-11-01', 4, 3, 'WAITING_DIRECTOR', 'Biên bản mẫu đã được Quản lý kho xác minh.');

INSERT INTO `inventory_audit_items`
    (`audit_id`, `product_id`, `lot_id`, `location_id`, `system_quantity`, `actual_quantity`,
     `discrepancy_reason`, `recommended_action`)
VALUES
    (1, 1, 1, 1, 420, 418, 'Chênh lệch cân đối chiếu khi nhập nguyên liệu pha chế',
     'Điều chỉnh giảm tồn kho 2 Kg sau khi được Ban Giám đốc phê duyệt.');

CREATE TABLE `in_app_notifications` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `audit_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------------------
-- 8. BẢNG NHẬT KÝ HỆ THỐNG AUDIT LOG (FR-18, NFR-12)
-- ------------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(50) NOT NULL, -- INSERT, UPDATE, DELETE, APPROVE, BALANCE
    `module` VARCHAR(50) NOT NULL, -- INVENTORY, STOCK_ISSUE, STOCK_RECEIPT, USERS...
    `record_id` VARCHAR(50) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO `audit_logs` (`user_id`, `action`, `module`, `record_id`, `details`) VALUES
(1, 'INITIALIZE', 'SYSTEM', '0', 'Hệ thống Quản lý Kho Thông minh được khởi tạo thành công.');
