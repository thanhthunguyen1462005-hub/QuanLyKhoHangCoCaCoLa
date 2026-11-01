<?php
/**
 * Hệ Thống Quản Lý Kho Hàng Nhà Máy (Industrial WMS)
 * Cấu hình kết nối Cơ sở dữ liệu MySQL qua PDO
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanlykhohang_db');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): ?PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Ghi nhận lỗi nhưng không crash nếu nhóm chưa kịp import DB
                error_log("Database connection failed: " . $e->getMessage());
                return null;
            }
        }
        return self::$instance;
    }
}

