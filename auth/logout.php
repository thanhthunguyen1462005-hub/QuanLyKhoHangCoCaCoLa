<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Phương thức không được hỗ trợ.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Yêu cầu đăng xuất không hợp lệ. Vui lòng tải lại trang và thử lại.');
}

$user = currentUser();
$pdo = Database::getConnection();
if ($user !== null && $pdo !== null) {
    try {
        $logoutLog = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
             VALUES (:user_id, 'LOGOUT', 'AUTHENTICATION', :record_id, :ip_address, :user_agent, :details)"
        );
        $logoutLog->execute([
            'user_id' => $user['id'],
            'record_id' => (string) $user['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => 'Đăng xuất thành công.',
        ]);
    } catch (Throwable $exception) {
        error_log('Logout audit log could not be saved for user ' . $user['id'] . ': ' . $exception->getMessage());
    }
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
