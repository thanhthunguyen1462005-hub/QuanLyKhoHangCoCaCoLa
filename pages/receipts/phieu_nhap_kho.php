<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function reply($code, $success, $message)
{
    http_response_code($code);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, false, 'Chỉ chấp nhận POST.');
}

$user = currentUser();

if (!$user || !hasRole(ROLE_STAFF)) {
    reply(403, false, 'Không có quyền lập phiếu.');
}

$userId = (int) ($user['id'] ?? 0);

$requestId = filter_input(
    INPUT_POST,
    'request_id',
    FILTER_VALIDATE_INT
);

$selectedLots = $_POST['selected_lots'] ?? [];
$locations = $_POST['locations'] ?? [];
$notes = trim((string) ($_POST['notes'] ?? ''));

if (
    !$userId ||
    !$requestId ||
    !is_array($selectedLots) ||
    !is_array($locations) ||
    count($selectedLots) === 0
) {
    reply(422, false, 'Dữ liệu nhập kho không hợp lệ.');
}

// Chuẩn hóa và chống gửi lặp số lô
$selectedIds = [];

foreach ($selectedLots as $value) {
    if (
        !is_scalar($value) ||
        !ctype_digit((string) $value) ||
        (int) $value <= 0
    ) {
        reply(422, false, 'Số lô không hợp lệ.');
    }

    $selectedIds[] = (int) $value;
}

if (count($selectedIds) !== count(array_unique($selectedIds))) {
    reply(422, false, 'Có số lô được chọn trùng.');
}

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // 1. Khóa yêu cầu để tránh nhập trùng
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            po.po_code,
            po.status AS po_status,
            po.supplier_id,
            q.inspection_type,
            q.reference_code,
            q.result,
            q.approval_status,
            q.passed_quantity
        FROM raw_material_inbound_requests r
        JOIN purchase_orders po ON po.id = r.po_id
        JOIN qc_inspections q ON q.id = r.qc_inspection_id
        WHERE r.id = ?
        FOR UPDATE
    ");

    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException(
            'Không tìm thấy yêu cầu nhập kho.'
        );
    }

    if ($request['status'] !== 'WAITING_RECEIPT') {
        throw new RuntimeException(
            'Yêu cầu đã được xử lý hoặc không còn chờ nhập.'
        );
    }

    if ($request['po_status'] !== 'APPROVED_FOR_RECEIPT') {
        throw new RuntimeException(
            'Đơn mua hàng chưa đủ điều kiện nhập.'
        );
    }

    if (
        $request['approval_status'] !== 'APPROVED' ||
        $request['inspection_type'] !== 'INCOMING_MATERIAL' ||
        $request['reference_code'] !== $request['po_code'] ||
        !in_array(
            $request['result'],
            ['PASSED', 'PARTIAL'],
            true
        )
    ) {
        throw new RuntimeException(
            'Biên bản QC chưa được phê duyệt hợp lệ.'
        );
    }

    if (empty($request['assigned_by'])) {
        throw new RuntimeException(
            'Quản lý kho chưa chỉ định kho nhập.'
        );
    }

    // 2. Kiểm tra kho đã chỉ định
    $stmt = $pdo->prepare("
        SELECT id
        FROM warehouses
        WHERE id = ?
          AND type = 'RAW_MATERIAL'
          AND status = 'ACTIVE'
    ");

    $stmt->execute([$request['warehouse_id']]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Kho nguyên liệu không hợp lệ.'
        );
    }

    // 3. Chặn nhập trùng biên bản QC
    $stmt = $pdo->prepare("
        SELECT id
        FROM stock_receipts
        WHERE qc_inspection_id = ?
        LIMIT 1
    ");

    $stmt->execute([$request['qc_inspection_id']]);

    if ($stmt->fetchColumn()) {
        throw new RuntimeException(
            'Biên bản QC này đã có phiếu nhập kho.'
        );
    }

    // 4. Lấy toàn bộ lô từ QC và đơn mua
    $stmt = $pdo->prepare("
        SELECT
            d.lot_id,
            d.passed_qty,
            d.qc_result,
            l.lot_number,
            l.product_id,
            l.supplier_id,
            l.qc_status AS lot_qc_status,
            p.type AS product_type,
            COALESCE(pi.unit_price, 0) AS unit_price
        FROM raw_material_qc_lot_details d
        JOIN product_lots l ON l.id = d.lot_id
        JOIN products p ON p.id = l.product_id
        JOIN po_items pi
          ON pi.po_id = ?
         AND pi.product_id = l.product_id
        WHERE d.qc_inspection_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $request['po_id'],
        $request['qc_inspection_id']
    ]);

    $qcLots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$qcLots) {
        throw new RuntimeException(
            'Không có số lô trong biên bản QC.'
        );
    }

    $allowed = [];
    $qcTotal = 0;

    foreach ($qcLots as $lot) {
        $lotId = (int) $lot['lot_id'];

        if ($lot['product_type'] !== 'RAW_MATERIAL') {
            throw new RuntimeException(
                'Có sản phẩm không phải nguyên liệu.'
            );
        }

        if (
            $lot['supplier_id'] !== null &&
            (int) $lot['supplier_id'] !==
            (int) $request['supplier_id']
        ) {
            throw new RuntimeException(
                'Nhà cung cấp của lô không khớp đơn mua.'
            );
        }

        if (isset($allowed[$lotId])) {
            throw new RuntimeException(
                'Chi tiết QC có số lô trùng.'
            );
        }

        $allowed[$lotId] = $lot;

        if ($lot['qc_result'] === 'PASSED') {
            $qcTotal += (float) $lot['passed_qty'];
        }
    }

    if ($qcTotal > (float) $request['passed_quantity']) {
        throw new RuntimeException(
            'Tổng số lượng theo lô vượt kết quả QC.'
        );
    }

    // 5. Kiểm tra các lô nhân viên chọn
    $items = [];

    $checkLocation = $pdo->prepare("
        SELECT id
        FROM warehouse_locations
        WHERE id = ?
          AND warehouse_id = ?
    ");

    foreach ($selectedIds as $lotId) {
        if (!isset($allowed[$lotId])) {
            throw new RuntimeException(
                'Số lô không thuộc biên bản QC.'
            );
        }

        $lot = $allowed[$lotId];

        if (
            $lot['qc_result'] !== 'PASSED' ||
            $lot['lot_qc_status'] !== 'PASSED'
        ) {
            throw new RuntimeException(
                'Lô ' . $lot['lot_number'] .
                ' chưa đạt QC.'
            );
        }

        // SỐ LƯỢNG TỰ LẤY TỪ QC
        // KHÔNG LẤY SỐ LƯỢNG TỪ FORM
        $quantity = (float) $lot['passed_qty'];

        if ($quantity <= 0) {
            throw new RuntimeException(
                'Số lượng QC của lô không hợp lệ.'
            );
        }

        $locationId = filter_var(
            $locations[$lotId] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$locationId) {
            throw new RuntimeException(
                'Chưa chọn vị trí cho lô ' .
                $lot['lot_number']
            );
        }

        $checkLocation->execute([
            $locationId,
            $request['warehouse_id']
        ]);

        if (!$checkLocation->fetchColumn()) {
            throw new RuntimeException(
                'Vị trí của lô ' . $lot['lot_number'] .
                ' không thuộc kho được chỉ định.'
            );
        }

        $items[] = [
            'product_id' => (int) $lot['product_id'],
            'lot_id' => $lotId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit_price' => $lot['unit_price']
        ];
    }

    if (!$items) {
        throw new RuntimeException(
            'Chưa chọn lô nguyên liệu để nhập.'
        );
    }

    // 6. Tạo phiếu nhập
    $receiptCode = 'PN-NVL-' .
        date('YmdHis') . '-' .
        strtoupper(bin2hex(random_bytes(3)));

    $stmt = $pdo->prepare("
        INSERT INTO stock_receipts (
            receipt_code,
            receipt_type,
            reference_doc,
            qc_inspection_id,
            warehouse_id,
            created_by,
            status,
            notes
        )
        VALUES (
            ?,
            'RAW_MATERIAL',
            ?,
            ?,
            ?,
            ?,
            'RECEIVED',
            ?
        )
    ");

    $stmt->execute([
        $receiptCode,
        $request['po_code'],
        $request['qc_inspection_id'],
        $request['warehouse_id'],
        $userId,
        $notes
    ]);

    $receiptId = (int) $pdo->lastInsertId();

    // 7. Lưu nhiều nguyên liệu, nhiều số lô
    $insertItem = $pdo->prepare("
        INSERT INTO stock_receipt_items (
            stock_receipt_id,
            product_id,
            lot_id,
            location_id,
            quantity,
            unit_price
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    // 8. Cập nhật tồn kho theo từng lô, từng vị trí
    $updateInventory = $pdo->prepare("
        INSERT INTO inventory (
            warehouse_id,
            location_id,
            product_id,
            lot_id,
            quantity,
            reserved_quantity
        )
        VALUES (?, ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE
            quantity = quantity + VALUES(quantity)
    ");

    foreach ($items as $item) {
        $insertItem->execute([
            $receiptId,
            $item['product_id'],
            $item['lot_id'],
            $item['location_id'],
            $item['quantity'],
            $item['unit_price']
        ]);

        $updateInventory->execute([
            $request['warehouse_id'],
            $item['location_id'],
            $item['product_id'],
            $item['lot_id'],
            $item['quantity']
        ]);
    }

    // 9. Ghi nhận số lượng đã nhận theo sản phẩm
    $receivedByProduct = [];

    foreach ($items as $item) {
        $pid = $item['product_id'];

        $receivedByProduct[$pid] =
            ($receivedByProduct[$pid] ?? 0)
            + $item['quantity'];
    }

    $updatePoItem = $pdo->prepare("
        UPDATE po_items
        SET received_qty = received_qty + ?
        WHERE po_id = ?
          AND product_id = ?
    ");

    foreach ($receivedByProduct as $pid => $qty) {
        $updatePoItem->execute([
            $qty,
            $request['po_id'],
            $pid
        ]);
    }

    // 10. Đánh dấu yêu cầu đã lập phiếu
    $stmt = $pdo->prepare("
        UPDATE raw_material_inbound_requests
        SET status = 'RECEIPT_CREATED'
        WHERE id = ?
          AND status = 'WAITING_RECEIPT'
    ");

    $stmt->execute([$requestId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Không cập nhật được trạng thái yêu cầu.'
        );
    }

    // Chỉ hoàn tất PO khi đã nhận đủ
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM po_items
        WHERE po_id = ?
          AND received_qty < ordered_qty
    ");

    $stmt->execute([$request['po_id']]);
    $remaining = (int) $stmt->fetchColumn();

    if ($remaining === 0) {
        $stmt = $pdo->prepare("
            UPDATE purchase_orders
            SET status = 'COMPLETED'
            WHERE id = ?
        ");

        $stmt->execute([$request['po_id']]);
    }

    $pdo->commit();

    reply(
        201,
        true,
        'Đã lập phiếu ' . $receiptCode .
        ' và cập nhật tồn kho theo các số lô.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Lỗi nhập kho: ' . $e->getMessage());

    reply(422, false, $e->getMessage());
}
