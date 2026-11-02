
<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

$user = currentUser();

if ($user === null) {
    sendJson(401, [
        'success' => false,
        'message' => 'Vui lòng đăng nhập.'
    ]);
}

if (!canAccessPage('material_requests')) {
    sendJson(403, [
        'success' => false,
        'message' => 'Bạn không có quyền lập phiếu yêu cầu.'
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendJson(405, [
        'success' => false,
        'message' => 'Phương thức không hợp lệ.'
    ]);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    sendJson(403, [
        'success' => false,
        'message' => 'CSRF không hợp lệ. Hãy tải lại trang.'
    ]);
}

$planId = filter_var(
    $_POST['production_plan_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$neededDate = $_POST['needed_date'] ?? '';
$priority = $_POST['priority'] ?? 'NORMAL';
$note = trim((string) ($_POST['note'] ?? ''));

$date = is_string($neededDate)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $neededDate)
    : false;

if (
    !$planId ||
    !$date ||
    $date->format('Y-m-d') !== $neededDate ||
    $neededDate < date('Y-m-d') ||
    !in_array($priority, ['NORMAL', 'HIGH', 'URGENT'], true) ||
    mb_strlen($note, 'UTF-8') > 1000
) {
    sendJson(422, [
        'success' => false,
        'message' => 'Thông tin phiếu yêu cầu không hợp lệ.'
    ]);
}

$pdo = null;

try {
    $pdo = Database::getConnection();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Không kết nối được PDO.');
    }

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->beginTransaction();

    // Khóa KHSX để tránh gửi trùng đồng thời.
    $stmt = $pdo->prepare("
        SELECT
            id,
            plan_code,
            product_id,
            quantity,
            status
        FROM production_plans
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new DomainException(
            'Không tìm thấy kế hoạch sản xuất.'
        );
    }

    if ($plan['status'] !== 'PLANNED') {
        throw new DomainException(
            'KHSX không ở trạng thái PLANNED.'
        );
    }

    // Kiểm tra phiếu đã gửi.
    $stmt = $pdo->prepare("
        SELECT request_code
        FROM material_requests
        WHERE production_plan_id = ?
          AND status IN ('PENDING', 'APPROVED', 'FULFILLED')
        LIMIT 1
    ");
    $stmt->execute([$planId]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new DomainException(
            'KHSX đã có phiếu yêu cầu: '
            . $existing['request_code']
        );
    }

    // Lấy nguyên liệu từ BOM, không nhận danh sách
    // nguyên liệu do trình duyệt gửi lên.
    $stmt = $pdo->prepare("
        SELECT
            b.material_id,
            b.quantity_per_unit,
            m.unit,
            (
                b.quantity_per_unit * ?
            ) AS requested_quantity
        FROM production_bom b
        JOIN products m
            ON m.id = b.material_id
           AND m.type = 'RAW_MATERIAL'
        WHERE b.product_id = ?
        ORDER BY b.material_id
    ");
    $stmt->execute([
        $plan['quantity'],
        $plan['product_id']
    ]);

    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$materials) {
        throw new DomainException(
            'KHSX chưa có nguyên liệu trong BOM.'
        );
    }

    $seen = [];

    foreach ($materials as $material) {
        $materialId = (int) $material['material_id'];
        $quantity = (float) $material['requested_quantity'];

        if (isset($seen[$materialId])) {
            throw new DomainException(
                'BOM có nguyên liệu bị trùng.'
            );
        }

        $seen[$materialId] = true;

        if (
            !is_finite($quantity) ||
            $quantity <= 0 ||
            $quantity > 999999999999999
        ) {
            throw new DomainException(
                'Số lượng nguyên liệu trong BOM không hợp lệ.'
            );
        }

        // Cột requested_quantity lưu 3 chữ số thập phân.
        if (round($quantity, 3) <= 0) {
            throw new DomainException(
                'Số lượng nguyên liệu quá nhỏ để lưu.'
            );
        }
    }

    $requestCode = 'YCNL-'
        . date('Ymd')
        . '-'
        . strtoupper(bin2hex(random_bytes(6)));

    $createdBy = isset($user['id'])
        ? (string) $user['id']
        : null;

    $stmt = $pdo->prepare("
        INSERT INTO material_requests (
            request_code,
            production_plan_id,
            needed_date,
            priority,
            status,
            note,
            created_by
        )
        VALUES (?, ?, ?, ?, 'PENDING', ?, ?)
    ");

    $stmt->execute([
        $requestCode,
        $planId,
        $neededDate,
        $priority,
        $note !== '' ? $note : null,
        $createdBy
    ]);

    $requestId = (int) $pdo->lastInsertId();

    if ($requestId <= 0) {
        throw new RuntimeException(
            'Không lấy được ID phiếu vừa lưu.'
        );
    }

$stmt = $pdo->prepare("
    INSERT INTO material_request_items (
        request_id,
        material_id,
        quantity_per_unit,
        requested_quantity,
        unit
    )
    VALUES (?, ?, ?, ?, ?)
");s
    foreach ($materials as $material) {
        $stmt->execute([
            $requestId,
            $material['material_id'],
            $material['quantity_per_unit'],
            $material['requested_quantity'],
            $material['unit']
        ]);
    }

    $pdo->commit();

    sendJson(201, [
        'success' => true,
        'message' => 'Gửi yêu cầu nguyên liệu thành công.',
        'request_id' => $requestId,
        'request_code' => $requestCode,
        'status' => 'PENDING',
        'material_count' => count($materials)
    ]);

} catch (DomainException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendJson(422, [
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[MATERIAL_REQUEST_ERROR] '
        . $e->getMessage()
        . ' at '
        . $e->getFile()
        . ':'
        . $e->getLine()
    );

    sendJson(500, [
        'success' => false,
        'message' => 'Không thể lưu phiếu. Xem log PHP với từ khóa MATERIAL_REQUEST_ERROR.'
    ]);
}
