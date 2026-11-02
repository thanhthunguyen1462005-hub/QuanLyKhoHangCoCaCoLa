
<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra đăng nhập và phân quyền.
if (currentUser() === null) {
    sendJson(401, [
        'success' => false,
        'message' => 'Vui lòng đăng nhập.'
    ]);
}

if (!canAccessPage('production_planning')) {
    sendJson(403, [
        'success' => false,
        'message' => 'Bạn không có quyền lập kế hoạch sản xuất.'
    ]);
}

try {
    $pdo = Database::getConnection();

    if (!$pdo) {
        sendJson(503, [
            'success' => false,
            'message' => 'Không thể kết nối MySQL.'
        ]);
    }

    // Đọc danh sách kế hoạch.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT
                pp.id,
                pp.plan_code,
                pp.product_id,
                p.name AS product_name,
                pp.quantity,
                pp.start_date,
                pp.end_date,
                pp.status,
                pp.note
            FROM production_plans pp
            JOIN products p ON p.id = pp.product_id
            ORDER BY pp.id DESC
        ");

        sendJson(200, [
            'success' => true,
            'plans' => $stmt->fetchAll()
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        sendJson(405, [
            'success' => false,
            'message' => 'Phương thức không được hỗ trợ.'
        ]);
    }

    // Kiểm tra CSRF.
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        sendJson(403, [
            'success' => false,
            'message' => 'Phiên bảo mật không hợp lệ. Hãy tải lại trang.'
        ]);
    }

    // Kiểm tra dữ liệu đầu vào.
    $productId = filter_var(
        $_POST['product_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $quantity = filter_var(
        $_POST['quantity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => [
            'min_range' => 1,
            'max_range' => 1000000
        ]]
    );

    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $note = trim((string) ($_POST['note'] ?? ''));

    $validDate = static function ($value): bool {
        if (!is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false
            && $date->format('Y-m-d') === $value;
    };

    if (
        !$productId ||
        !$quantity ||
        !$validDate($start) ||
        !$validDate($end) ||
        $end < $start ||
        mb_strlen($note, 'UTF-8') > 500
    ) {
        sendJson(422, [
            'success' => false,
            'message' => 'Thông tin kế hoạch không hợp lệ.'
        ]);
    }

    // Chỉ cho lập kế hoạch với thành phẩm đang hoạt động.
    $stmt = $pdo->prepare("
        SELECT id
        FROM products
        WHERE id = ?
          AND type = 'FINISHED_GOODS'
          AND status = 'ACTIVE'
        LIMIT 1
    ");

    $stmt->execute([$productId]);

    if (!$stmt->fetch()) {
        sendJson(422, [
            'success' => false,
            'message' => 'Sản phẩm không tồn tại hoặc không được phép lập kế hoạch.'
        ]);
    }

    // Mã ngẫu nhiên để hạn chế trùng kế hoạch.
    $planCode = 'KHSX-' . date('Y') . '-'
        . strtoupper(bin2hex(random_bytes(6)));

    $stmt = $pdo->prepare("
        INSERT INTO production_plans
            (plan_code, product_id, quantity,
             start_date, end_date, status, note)
        VALUES
            (?, ?, ?, ?, ?, 'PLANNED', ?)
    ");

    $stmt->execute([
        $planCode,
        $productId,
        $quantity,
        $start,
        $end,
        $note !== '' ? $note : null
    ]);

    sendJson(201, [
        'success' => true,
        'message' => 'Đã lưu kế hoạch vào MySQL.',
        'id' => (int) $pdo->lastInsertId(),
        'plan_code' => $planCode
    ]);

} catch (Throwable $e) {
    error_log('Production planning API: ' . $e->getMessage());

    sendJson(500, [
        'success' => false,
        'message' => 'Có lỗi khi xử lý kế hoạch.'
    ]);
}
