<?php
/**
 * Định nghĩa Hằng số và Thiết lập Hệ thống WMS
 * Bao gồm 9 Vai trò (Actors), 11 Kho chuẩn FR-02, Trạng thái chứng từ FR-19
 */

// Đường dẫn Base URL (chạy trực tiếp trên XAMPP localhost/QuanLyKhoHang)
define('BASE_URL', '/QuanLyKhoHang');
define('APP_NAME', 'Coca-Cola WMS');
define('APP_VERSION', '1.0.0');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 9 Vai trò người dùng (Actors theo đặc tả)
define('ROLE_ADMIN', 'admin');             // Quản trị hệ thống (FR-01, FR-20)
define('ROLE_DIRECTOR', 'director');       // Ban Giám đốc (FR-02, FR-15, FR-17)
define('ROLE_MANAGER', 'manager');         // Quản lý kho (FR-02 -> FR-19)
define('ROLE_STAFF', 'staff');             // Nhân viên kho / Thủ kho
define('ROLE_QC', 'qc');                   // QA / QC kiểm định chất lượng (FR-06, FR-08, FR-11)
define('ROLE_PROCUREMENT', 'procurement'); // Mua hàng (FR-05, FR-06)
define('ROLE_WORKSHOP', 'workshop');       // Xưởng sản xuất (FR-07, FR-08)
define('ROLE_PLANNING', 'planning');       // Lập kế hoạch (FR-07, FR-12)
define('ROLE_SALES', 'sales');             // Bán hàng (FR-09)

// Tên hiển thị của các vai trò
const ROLES_LIST = [
    ROLE_ADMIN       => ['name' => 'Quản trị hệ thống', 'badge' => 'danger', 'icon' => 'fa-shield-halved'],
    ROLE_DIRECTOR    => ['name' => 'Ban Giám đốc', 'badge' => 'primary', 'icon' => 'fa-user-tie'],
    ROLE_MANAGER     => ['name' => 'Quản lý kho', 'badge' => 'warning', 'icon' => 'fa-user-gear'],
    ROLE_STAFF       => ['name' => 'Nhân viên kho', 'badge' => 'info', 'icon' => 'fa-boxes-packing'],
    ROLE_QC          => ['name' => 'QA/QC Chất lượng', 'badge' => 'success', 'icon' => 'fa-microscope'],
    ROLE_PROCUREMENT => ['name' => 'Bộ phận Mua hàng', 'badge' => 'secondary', 'icon' => 'fa-cart-shopping'],
    ROLE_WORKSHOP    => ['name' => 'Xưởng sản xuất', 'badge' => 'dark', 'icon' => 'fa-industry'],
    ROLE_PLANNING    => ['name' => 'Lập kế hoạch', 'badge' => 'teal', 'icon' => 'fa-calendar-days'],
    ROLE_SALES       => ['name' => 'Bộ phận Bán hàng', 'badge' => 'purple', 'icon' => 'fa-truck-fast'],
];

// Danh sách 11 Kho chuẩn (FR-02)
const WAREHOUSES_DEF = [
    // 5 kho nguyên liệu và bao bì
    ['code' => 'KNL-01', 'name' => 'Kho Đường tinh luyện & siro nền', 'type' => 'RAW_MATERIAL', 'capacity_m3' => 2500, 'location' => 'Khu A - nguyên liệu đường, siro'],
    ['code' => 'KNL-02', 'name' => 'Kho Nước cốt & hương liệu đồ uống', 'type' => 'RAW_MATERIAL', 'capacity_m3' => 1800, 'location' => 'Khu A - kho mát, kiểm soát nhiệt độ'],
    ['code' => 'KNL-03', 'name' => 'Kho CO₂ thực phẩm & phụ gia', 'type' => 'RAW_MATERIAL', 'capacity_m3' => 800, 'location' => 'Khu an toàn bình khí và phụ gia'],
    ['code' => 'KNL-04', 'name' => 'Kho Chai PET, nắp chai & lon nhôm', 'type' => 'RAW_MATERIAL', 'capacity_m3' => 3000, 'location' => 'Khu B - bao bì sơ cấp'],
    ['code' => 'KNL-05', 'name' => 'Kho Nhãn chai, màng co & thùng carton', 'type' => 'RAW_MATERIAL', 'capacity_m3' => 2000, 'location' => 'Khu B - vật liệu đóng gói'],
    // 5 kho thành phẩm theo quy cách và luồng phân phối
    ['code' => 'KTP-01', 'name' => 'Kho Nước ngọt chai PET', 'type' => 'FINISHED_GOODS', 'capacity_m3' => 3500, 'location' => 'Khu xuất hàng chai PET'],
    ['code' => 'KTP-02', 'name' => 'Kho Nước ngọt lon nhôm', 'type' => 'FINISHED_GOODS', 'capacity_m3' => 4000, 'location' => 'Khu xuất hàng lon'],
    ['code' => 'KTP-03', 'name' => 'Kho Thành phẩm chờ xuất phân phối', 'type' => 'FINISHED_GOODS', 'capacity_m3' => 3000, 'location' => 'Khu logistics nội địa'],
    ['code' => 'KTP-04', 'name' => 'Kho Thành phẩm chờ kiểm định', 'type' => 'FINISHED_GOODS', 'capacity_m3' => 900, 'location' => 'Khu cách ly chờ QA/QC'],
    ['code' => 'KTP-05', 'name' => 'Kho Thành phẩm xuất khẩu', 'type' => 'FINISHED_GOODS', 'capacity_m3' => 2800, 'location' => 'Khu logistics xuất khẩu'],
    // 1 kho cách ly hàng lỗi và hàng trả về
    ['code' => 'KHL-01', 'name' => 'Kho Cách ly hàng lỗi & hàng trả về', 'type' => 'DEFECTIVE_RETURN', 'capacity_m3' => 1500, 'location' => 'Khu cách ly chờ QA/QC xử lý'],
];

const PAGE_ROLE_ACCESS = [
    'dashboard' => [ROLE_ADMIN, ROLE_DIRECTOR, ROLE_MANAGER, ROLE_STAFF, ROLE_QC, ROLE_PROCUREMENT, ROLE_WORKSHOP, ROLE_PLANNING, ROLE_SALES],
    'warehouses' => [ROLE_DIRECTOR, ROLE_MANAGER, ROLE_STAFF, ROLE_QC, ROLE_PROCUREMENT, ROLE_PLANNING],
    'inventory' => [ROLE_DIRECTOR, ROLE_MANAGER, ROLE_STAFF, ROLE_QC, ROLE_PROCUREMENT, ROLE_WORKSHOP, ROLE_PLANNING, ROLE_SALES],
    'receipts_raw' => [ROLE_MANAGER, ROLE_STAFF, ROLE_QC, ROLE_PROCUREMENT],
    'receipts_finished' => [ROLE_MANAGER, ROLE_STAFF, ROLE_QC, ROLE_WORKSHOP],
    'issues_raw' => [ROLE_MANAGER, ROLE_STAFF, ROLE_WORKSHOP, ROLE_PLANNING],
    'production_planning' => [ROLE_PLANNING],
    'issues_finished' => [ROLE_MANAGER, ROLE_STAFF, ROLE_SALES],
    'audits' => [ROLE_DIRECTOR, ROLE_MANAGER, ROLE_STAFF],
    'reports' => [ROLE_DIRECTOR],
    'audit_logs' => [ROLE_DIRECTOR],
    'scanner' => [ROLE_MANAGER, ROLE_STAFF, ROLE_QC],
];

function currentUser(): ?array
{
    $user = $_SESSION['current_user'] ?? null;

    return !empty($_SESSION['authenticated']) && is_array($user) ? $user : null;
}

function hasRole(...$roles): bool
{
    $user = currentUser();
    if ($user === null) {
        return false;
    }

    return $user['role'] === ROLE_ADMIN || in_array($user['role'], $roles, true);
}

function canAccessPage(string $page): bool
{
    $user = currentUser();
    if ($user === null) {
        return false;
    }

    if ($user['role'] === ROLE_ADMIN) {
        return true;
    }

    return in_array($user['role'], PAGE_ROLE_ACCESS[$page] ?? [], true);
}

function requirePageAccess(string $page): void
{
    if (currentUser() === null) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    if (!canAccessPage($page)) {
        setFlashMessage('warning', 'Tài khoản của bạn không được cấp quyền truy cập chức năng này.');
        header('Location: ' . BASE_URL . '/pages/dashboard/');
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrfToken($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function consumeFlashMessage(): ?array
{
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    return $message;
}

// Helper format tiền tệ & ngày tháng
function formatCurrency($amount): string {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function formatDate($dateStr): string {
    if (!$dateStr) return '---';
    return date('d/m/Y', strtotime($dateStr));
}

function formatDateTime($dateStr): string {
    if (!$dateStr) return '---';
    return date('d/m/Y H:i', strtotime($dateStr));
}
