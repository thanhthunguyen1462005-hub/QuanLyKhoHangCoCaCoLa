<?php
require_once __DIR__ . '/../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Phương thức không được hỗ trợ.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Yêu cầu đăng xuất không hợp lệ. Vui lòng tải lại trang và thử lại.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => 'Lax',
]);
session_destroy();

header('Location: ' . BASE_URL . '/?logged_out=1');
exit;
