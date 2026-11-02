-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Nov 01, 2026 at 05:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quanlykhohang_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `record_id`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
(1, 1, 'INITIALIZE', 'SYSTEM', '0', NULL, NULL, 'Hệ thống Quản lý Kho Thông minh được khởi tạo thành công.', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('RAW_MATERIAL','FINISHED_GOODS') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `code`, `type`) VALUES
(1, 'Đường và siro nền', 'CAT-SUGAR-SYRUP', 'RAW_MATERIAL'),
(2, 'Nước cốt và hương liệu', 'CAT-CONCENTRATE', 'RAW_MATERIAL'),
(3, 'CO₂ thực phẩm và phụ gia', 'CAT-FOOD-GAS', 'RAW_MATERIAL'),
(4, 'Bao bì chai PET và lon', 'CAT-BEVERAGE-PACK', 'RAW_MATERIAL'),
(5, 'Nước ngọt thành phẩm', 'CAT-SOFT-DRINK', 'FINISHED_GOODS');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `code`, `name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'KH-DISTRIBUTOR-01', 'Nhà phân phối nước giải khát miền Nam', '02837771001', 'distribution@example.com', 'TP. Hồ Chí Minh', '2026-11-01 15:19:42'),
(2, 'KH-RETAIL-01', 'Đối tác bán lẻ nước giải khát', '02837771002', 'retail@example.com', 'TP. Hồ Chí Minh', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reserved_quantity` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `warehouse_id`, `location_id`, `product_id`, `lot_id`, `quantity`, `reserved_quantity`, `last_updated`) VALUES
(1, 1, 1, 1, 1, 420, 0, '2026-11-01 15:19:42'),
(2, 1, 2, 1, 2, 2800, 0, '2026-11-01 15:19:42'),
(3, 2, 6, 2, 3, 240, 0, '2026-11-01 15:19:42'),
(4, 3, 8, 3, 4, 36, 0, '2026-11-01 15:19:42'),
(5, 4, 7, 4, 5, 18000, 0, '2026-11-01 15:19:42'),
(6, 6, 4, 5, 6, 680, 0, '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audits`
--

CREATE TABLE `inventory_audits` (
  `id` int(11) NOT NULL,
  `audit_code` varchar(50) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `audit_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `approved_by_director` int(11) DEFAULT NULL,
  `status` enum('IN_PROGRESS','WAITING_MANAGER','WAITING_DIRECTOR','BALANCED','REJECTED') DEFAULT 'IN_PROGRESS',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audit_items`
--

CREATE TABLE `inventory_audit_items` (
  `id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `system_quantity` int(11) NOT NULL,
  `actual_quantity` int(11) NOT NULL,
  `difference` int(11) GENERATED ALWAYS AS (`actual_quantity` - `system_quantity`) STORED,
  `discrepancy_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `ordered_qty` int(11) NOT NULL,
  `received_qty` int(11) DEFAULT 0,
  `unit_price` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `product_id`, `ordered_qty`, `received_qty`, `unit_price`) VALUES
(1, 1, 1, 2000, 0, 22000.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `type` enum('RAW_MATERIAL','FINISHED_GOODS') NOT NULL,
  `min_stock` int(11) DEFAULT 10,
  `max_stock` int(11) DEFAULT 1000,
  `cost_price` decimal(15,2) DEFAULT 0.00,
  `sale_price` decimal(15,2) DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','DISCONTINUED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `barcode`, `name`, `category_id`, `unit`, `type`, `min_stock`, `max_stock`, `cost_price`, `sale_price`, `image_url`, `status`, `created_at`) VALUES
(1, 'NVL-SIRO-01', '893600001101', 'Đường tinh luyện sản xuất siro', 1, 'Kg', 'RAW_MATERIAL', 500, 10000, 22000.00, 0.00, NULL, 'ACTIVE', '2026-11-01 15:19:42'),
(2, 'NVL-COCA-01', '893600001102', 'Nước cốt cô đặc hương Coca-Cola', 2, 'Kg', 'RAW_MATERIAL', 100, 2000, 180000.00, 0.00, NULL, 'ACTIVE', '2026-11-01 15:19:42'),
(3, 'NVL-CO2-01', '893600001103', 'Khí CO₂ tinh khiết cấp thực phẩm', 3, 'Bình', 'RAW_MATERIAL', 20, 200, 950000.00, 0.00, NULL, 'ACTIVE', '2026-11-01 15:19:42'),
(4, 'BB-PET-01', '893600001104', 'Phôi chai PET dùng cho nước giải khát', 4, 'Cái', 'RAW_MATERIAL', 5000, 100000, 1200.00, 0.00, NULL, 'ACTIVE', '2026-11-01 15:19:42'),
(5, 'TP-COCA-500', '893600001201', 'Coca-Cola Original 500 ml (thùng 24 chai PET)', 5, 'Thùng', 'FINISHED_GOODS', 100, 3000, 85000.00, 120000.00, NULL, 'ACTIVE', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `product_lots`
--

CREATE TABLE `product_lots` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_number` varchar(100) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `mfg_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `qc_status` enum('PENDING','PASSED','REJECTED') DEFAULT 'PASSED',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_lots`
--

INSERT INTO `product_lots` (`id`, `product_id`, `lot_number`, `supplier_id`, `mfg_date`, `expiry_date`, `qc_status`, `notes`, `created_at`) VALUES
(1, 1, 'LOT-SIRO-2026-110', 1, '2026-10-20', '2026-11-20', 'PASSED', NULL, '2026-11-01 15:19:42'),
(2, 1, 'LOT-SIRO-2026-111', 1, '2026-10-28', '2027-01-28', 'PASSED', NULL, '2026-11-01 15:19:42'),
(3, 2, 'LOT-CONC-2026-044', 2, '2026-10-01', '2026-11-12', 'PASSED', NULL, '2026-11-01 15:19:42'),
(4, 3, 'LOT-CO2-2026-044', 2, '2026-10-10', '2027-10-10', 'PASSED', NULL, '2026-11-01 15:19:42'),
(5, 4, 'LOT-PET-2026-090', 3, '2026-09-10', '2028-09-10', 'PASSED', NULL, '2026-11-01 15:19:42'),
(6, 5, 'LOT-CC-2026-108', NULL, '2026-10-15', '2027-05-01', 'PASSED', NULL, '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_code` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status` enum('PENDING','QC_INSPECTING','APPROVED_FOR_RECEIPT','COMPLETED','CANCELLED') DEFAULT 'PENDING',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_code`, `supplier_id`, `created_by`, `order_date`, `expected_delivery`, `status`, `total_amount`, `notes`, `created_at`) VALUES
(1, 'PO-2026-11-001', 1, 6, '2026-11-01', NULL, 'APPROVED_FOR_RECEIPT', 44000000.00, NULL, '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `qc_inspections`
--

CREATE TABLE `qc_inspections` (
  `id` int(11) NOT NULL,
  `qc_code` varchar(50) NOT NULL,
  `inspection_type` enum('INCOMING_MATERIAL','PRODUCTION_OUTPUT','CUSTOMER_RETURN') NOT NULL,
  `reference_code` varchar(50) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `inspection_date` datetime NOT NULL,
  `result` enum('PASSED','REJECTED','PARTIAL') NOT NULL,
  `passed_quantity` int(11) NOT NULL DEFAULT 0,
  `rejected_quantity` int(11) NOT NULL DEFAULT 0,
  `defect_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `qc_inspections`
--

INSERT INTO `qc_inspections` (`id`, `qc_code`, `inspection_type`, `reference_code`, `inspector_id`, `inspection_date`, `result`, `passed_quantity`, `rejected_quantity`, `defect_reason`, `notes`) VALUES
(1, 'QC-2026-11-01', 'INCOMING_MATERIAL', 'PO-2026-11-001', 5, '2026-11-01 09:30:00', 'PASSED', 2000, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'Quản trị hệ thống', 'Toàn quyền cấu hình, quản lý tài khoản và phân quyền', '2026-11-01 15:19:42'),
(2, 'director', 'Ban Giám đốc', 'Xem Dashboard, báo cáo KPI, phê duyệt biên bản kiểm kê cân bằng kho', '2026-11-01 15:19:42'),
(3, 'manager', 'Quản lý kho', 'Phê duyệt phiếu nhập/xuất/chuyển kho, điều phối, xác minh kiểm kê', '2026-11-01 15:19:42'),
(4, 'staff', 'Nhân viên kho', 'Thực thi nhập/xuất kho, xếp kệ, quét mã vạch, kiểm đếm thực tế', '2026-11-01 15:19:42'),
(5, 'qc', 'QA/QC Chất lượng', 'Kiểm tra chất lượng nguyên liệu nhập, thành phẩm xưởng, hàng lỗi', '2026-11-01 15:19:42'),
(6, 'procurement', 'Bộ phận Mua hàng', 'Quản lý Nhà cung cấp, tạo Đơn mua hàng (PO)', '2026-11-01 15:19:42'),
(7, 'workshop', 'Xưởng sản xuất', 'Gửi yêu cầu xuất nguyên liệu và yêu cầu nhập thành phẩm', '2026-11-01 15:19:42'),
(8, 'planning', 'Lập kế hoạch', 'Theo dõi tồn kho, lập kế hoạch sản xuất', '2026-11-01 15:19:42'),
(9, 'sales', 'Bộ phận Bán hàng', 'Tạo đơn đặt hàng bán thành phẩm làm căn cứ xuất hàng', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `stock_issues`
--

CREATE TABLE `stock_issues` (
  `id` int(11) NOT NULL,
  `issue_code` varchar(50) NOT NULL,
  `issue_type` enum('TO_WORKSHOP','TO_CUSTOMER','TRANSFER','DISPOSAL') NOT NULL,
  `reference_doc` varchar(50) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('DRAFT','WAITING_APPROVAL','APPROVED','ISSUED','CANCELLED') DEFAULT 'WAITING_APPROVAL',
  `issue_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_issue_items`
--

CREATE TABLE `stock_issue_items` (
  `id` int(11) NOT NULL,
  `stock_issue_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `requested_quantity` int(11) NOT NULL,
  `actual_quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_receipts`
--

CREATE TABLE `stock_receipts` (
  `id` int(11) NOT NULL,
  `receipt_code` varchar(50) NOT NULL,
  `receipt_type` enum('RAW_MATERIAL','FINISHED_GOODS','DEFECTIVE_RETURN') NOT NULL,
  `reference_doc` varchar(50) NOT NULL,
  `qc_inspection_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','RECEIVED','REJECTED') DEFAULT 'PENDING',
  `receipt_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_receipt_items`
--

CREATE TABLE `stock_receipt_items` (
  `id` int(11) NOT NULL,
  `stock_receipt_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `transfer_code` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('PENDING','IN_TRANSIT','COMPLETED','REJECTED') DEFAULT 'PENDING',
  `transfer_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `code`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'NCC-SUGAR', 'Nhà cung cấp Đường tinh luyện', 'Nguyễn Minh An', '02837770001', 'sugar@example.com', 'Khu vực miền Nam', '2026-11-01 15:19:42'),
(2, 'NCC-INGREDIENT', 'Nhà cung cấp nguyên liệu đồ uống', 'Trần Thu Hà', '02837770002', 'ingredient@example.com', 'Khu vực miền Nam', '2026-11-01 15:19:42'),
(3, 'NCC-PACK', 'Nhà cung cấp bao bì thực phẩm', 'Lê Quốc Bảo', '02837770003', 'packaging@example.com', 'Khu vực miền Nam', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `username`, `password`, `fullname`, `email`, `phone`, `is_active`, `last_login`, `created_at`) VALUES
(1, 1, 'admin', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Vũ Quản Trị Hệ Thống', 'admin@factory.com', '0901000001', 1, NULL, '2026-11-01 15:19:42'),
(2, 2, 'giamdoc', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Trần Tổng Giám Đốc', 'director@factory.com', '0901000002', 1, NULL, '2026-11-01 15:19:42'),
(3, 3, 'quanlykho', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Lê Quản Lý Kho', 'warehouse.manager@factory.com', '0901000003', 1, NULL, '2026-11-01 15:19:42'),
(4, 4, 'thukho', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Nguyễn Nhân Viên Thủ Kho', 'thukho@factory.com', '0901000004', 1, NULL, '2026-11-01 15:19:42'),
(5, 5, 'qaqc', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Phạm Kiểm Định QA/QC', 'qc@factory.com', '0901000005', 1, NULL, '2026-11-01 15:19:42'),
(6, 6, 'muahang', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Hoàng Nhân Viên Mua Hàng', 'procurement@factory.com', '0901000006', 1, NULL, '2026-11-01 15:19:42'),
(7, 7, 'xuong1', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Đặng Quản Đốc Xưởng 1', 'workshop@factory.com', '0901000007', 1, '2026-11-01 22:54:37', '2026-11-01 15:19:42'),
(8, 8, 'kehoach', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Bùi Kỹ Sư Kế Hoạch', 'planning@factory.com', '0901000008', 1, '2026-11-01 22:57:48', '2026-11-01 15:19:42'),
(9, 9, 'banhang', '$2y$10$lsHP/zsTkiYvxVFMHI.fF.zQ4.DLQpSisOsSChidNG9SavRVhsmUi', 'Đỗ Chuyên Viên Bán Hàng', 'sales@factory.com', '0901000009', 1, NULL, '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('RAW_MATERIAL','FINISHED_GOODS','DEFECTIVE_RETURN') NOT NULL,
  `capacity_m3` decimal(12,2) DEFAULT 1000.00,
  `manager_id` int(11) DEFAULT NULL,
  `location_description` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','MAINTENANCE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `code`, `name`, `type`, `capacity_m3`, `manager_id`, `location_description`, `status`, `created_at`) VALUES
(1, 'KNL-01', 'Kho Đường tinh luyện & siro nền', 'RAW_MATERIAL', 2500.00, NULL, 'Khu A - nguyên liệu đường, siro', 'ACTIVE', '2026-11-01 15:19:42'),
(2, 'KNL-02', 'Kho Nước cốt & hương liệu đồ uống', 'RAW_MATERIAL', 1800.00, NULL, 'Khu A - kho mát, kiểm soát nhiệt độ', 'ACTIVE', '2026-11-01 15:19:42'),
(3, 'KNL-03', 'Kho CO₂ thực phẩm & phụ gia', 'RAW_MATERIAL', 800.00, NULL, 'Khu an toàn bình khí và phụ gia', 'ACTIVE', '2026-11-01 15:19:42'),
(4, 'KNL-04', 'Kho Chai PET, nắp chai & lon nhôm', 'RAW_MATERIAL', 3000.00, NULL, 'Khu B - bao bì sơ cấp', 'ACTIVE', '2026-11-01 15:19:42'),
(5, 'KNL-05', 'Kho Nhãn chai, màng co & thùng carton', 'RAW_MATERIAL', 2000.00, NULL, 'Khu B - vật liệu đóng gói', 'ACTIVE', '2026-11-01 15:19:42'),
(6, 'KTP-01', 'Kho Nước ngọt chai PET', 'FINISHED_GOODS', 3500.00, NULL, 'Khu xuất hàng chai PET', 'ACTIVE', '2026-11-01 15:19:42'),
(7, 'KTP-02', 'Kho Nước ngọt lon nhôm', 'FINISHED_GOODS', 4000.00, NULL, 'Khu xuất hàng lon', 'ACTIVE', '2026-11-01 15:19:42'),
(8, 'KTP-03', 'Kho Thành phẩm chờ xuất phân phối', 'FINISHED_GOODS', 3000.00, NULL, 'Khu logistics nội địa', 'ACTIVE', '2026-11-01 15:19:42'),
(9, 'KTP-04', 'Kho Thành phẩm chờ kiểm định', 'FINISHED_GOODS', 900.00, NULL, 'Khu cách ly chờ QA/QC', 'ACTIVE', '2026-11-01 15:19:42'),
(10, 'KTP-05', 'Kho Thành phẩm xuất khẩu', 'FINISHED_GOODS', 2800.00, NULL, 'Khu logistics xuất khẩu', 'ACTIVE', '2026-11-01 15:19:42'),
(11, 'KHL-01', 'Kho Cách ly hàng lỗi & hàng trả về', 'DEFECTIVE_RETURN', 1500.00, NULL, 'Khu cách ly chờ QA/QC xử lý', 'ACTIVE', '2026-11-01 15:19:42');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_locations`
--

CREATE TABLE `warehouse_locations` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `location_code` varchar(50) NOT NULL,
  `aisle` varchar(20) NOT NULL,
  `rack` varchar(20) NOT NULL,
  `shelf` varchar(20) NOT NULL,
  `bin` varchar(20) NOT NULL,
  `max_weight_kg` decimal(10,2) DEFAULT 1000.00,
  `is_occupied` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_locations`
--

INSERT INTO `warehouse_locations` (`id`, `warehouse_id`, `zone_id`, `location_code`, `aisle`, `rack`, `shelf`, `bin`, `max_weight_kg`, `is_occupied`) VALUES
(1, 1, 1, 'KNL01-A-R01-S01-B01', 'A', 'R01', 'S01', 'B01', 1000.00, 0),
(2, 1, 1, 'KNL01-A-R01-S01-B02', 'A', 'R01', 'S01', 'B02', 1000.00, 0),
(3, 1, 1, 'KNL01-A-R01-S02-B01', 'A', 'R01', 'S02', 'B01', 1000.00, 0),
(4, 6, 3, 'KTP01-B-R05-S01-B01', 'B', 'R05', 'S01', 'B01', 1000.00, 0),
(5, 11, 4, 'KHL01-ERR-R01-B01', 'ERR', 'R01', 'S01', 'B01', 1000.00, 0),
(6, 2, 5, 'KNL02-A-R01-S01-B01', 'A', 'R01', 'S01', 'B01', 1000.00, 0),
(7, 4, 6, 'KNL04-B-R01-S01-B01', 'B', 'R01', 'S01', 'B01', 1000.00, 0),
(8, 3, 7, 'KNL03-A-R01-B03', 'A', 'R01', 'S01', 'B03', 1000.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_zones`
--

CREATE TABLE `warehouse_zones` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `zone_code` varchar(50) NOT NULL,
  `zone_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_zones`
--

INSERT INTO `warehouse_zones` (`id`, `warehouse_id`, `zone_code`, `zone_name`) VALUES
(1, 1, 'ZONE-A1', 'Đường tinh luyện và nguyên liệu khô'),
(2, 1, 'ZONE-A2', 'Siro nền và nguyên liệu chờ kiểm tra'),
(3, 6, 'ZONE-TP1', 'Nước ngọt chai PET đóng thùng'),
(4, 11, 'ZONE-ERR', 'Khu chờ QA/QC thẩm định'),
(5, 2, 'ZONE-A3', 'Nước cốt và hương liệu'),
(6, 4, 'ZONE-B1', 'Chai PET và nắp chai'),
(7, 3, 'ZONE-A4', 'CO₂ thực phẩm và phụ gia');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stock_pos` (`warehouse_id`,`location_id`,`product_id`,`lot_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Indexes for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `audit_code` (`audit_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `approved_by_director` (`approved_by_director`);

--
-- Indexes for table `inventory_audit_items`
--
ALTER TABLE `inventory_audit_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_id` (`audit_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_lots`
--
ALTER TABLE `product_lots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_lot` (`product_id`,`lot_number`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_code` (`po_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qc_code` (`qc_code`),
  ADD KEY `inspector_id` (`inspector_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `stock_issues`
--
ALTER TABLE `stock_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `issue_code` (`issue_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `stock_issue_items`
--
ALTER TABLE `stock_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_issue_id` (`stock_issue_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_code` (`receipt_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `qc_inspection_id` (`qc_inspection_id`);

--
-- Indexes for table `stock_receipt_items`
--
ALTER TABLE `stock_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_receipt_id` (`stock_receipt_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `lot_id` (`lot_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_code` (`transfer_code`),
  ADD KEY `from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_code` (`location_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Indexes for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_audit_items`
--
ALTER TABLE `inventory_audit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_lots`
--
ALTER TABLE `product_lots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `stock_issues`
--
ALTER TABLE `stock_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_issue_items`
--
ALTER TABLE `stock_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_receipt_items`
--
ALTER TABLE `stock_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`),
  ADD CONSTRAINT `inventory_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_ibfk_4` FOREIGN KEY (`lot_id`) REFERENCES `product_lots` (`id`);

--
-- Constraints for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  ADD CONSTRAINT `inventory_audits_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `inventory_audits_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_audits_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_audits_ibfk_4` FOREIGN KEY (`approved_by_director`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_audit_items`
--
ALTER TABLE `inventory_audit_items`
  ADD CONSTRAINT `inventory_audit_items_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_audit_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inventory_audit_items_ibfk_3` FOREIGN KEY (`lot_id`) REFERENCES `product_lots` (`id`),
  ADD CONSTRAINT `inventory_audit_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`);

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_lots`
--
ALTER TABLE `product_lots`
  ADD CONSTRAINT `product_lots_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_lots_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `qc_inspections`
--
ALTER TABLE `qc_inspections`
  ADD CONSTRAINT `qc_inspections_ibfk_1` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_issues`
--
ALTER TABLE `stock_issues`
  ADD CONSTRAINT `stock_issues_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_issues_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_issues_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_issue_items`
--
ALTER TABLE `stock_issue_items`
  ADD CONSTRAINT `stock_issue_items_ibfk_1` FOREIGN KEY (`stock_issue_id`) REFERENCES `stock_issues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_issue_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_issue_items_ibfk_3` FOREIGN KEY (`lot_id`) REFERENCES `product_lots` (`id`),
  ADD CONSTRAINT `stock_issue_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`);

--
-- Constraints for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  ADD CONSTRAINT `stock_receipts_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_receipts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_receipts_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_receipts_ibfk_4` FOREIGN KEY (`qc_inspection_id`) REFERENCES `qc_inspections` (`id`);

--
-- Constraints for table `stock_receipt_items`
--
ALTER TABLE `stock_receipt_items`
  ADD CONSTRAINT `stock_receipt_items_ibfk_1` FOREIGN KEY (`stock_receipt_id`) REFERENCES `stock_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_receipt_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_receipt_items_ibfk_3` FOREIGN KEY (`lot_id`) REFERENCES `product_lots` (`id`),
  ADD CONSTRAINT `stock_receipt_items_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `warehouse_locations` (`id`);

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_locations`
--
ALTER TABLE `warehouse_locations`
  ADD CONSTRAINT `warehouse_locations_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_locations_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `warehouse_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_zones`
--
ALTER TABLE `warehouse_zones`
  ADD CONSTRAINT `warehouse_zones_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
