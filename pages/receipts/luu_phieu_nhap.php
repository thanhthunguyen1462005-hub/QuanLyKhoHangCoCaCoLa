<?php
/**
 * SIX STAR - COCA-COLA WMS
 * FR-06: LUU PHIEU NHAP KHO NGUYEN LIEU
 *
 * Khong sua index.php.
 * Khong cap nhat inventory khi lap phieu.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

requirePageAccess('receipts_raw');

$pdo = Database::getConnection();

function receiptError($message, $status = 400)
{
    http_response_code($status);

    echo '<meta charset="UTF-8">';
    echo '<div style="font-family:Arial;padding:30px">';
    echo '<h3>Không thể lưu phiếu nhập kho</h3>';
    echo '<p>' . htmlspecialchars(
        $message,
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
    echo '<a href="phieu_nhap_kho.php">Quay lại</a>';
    echo '</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    receiptError('Yêu cầu không hợp lệ.', 405);
}

$requestId = filter_input(
    INPUT_POST,
    'request_id',
    FILTER_VALIDATE_INT
);

$locationIds = $_POST['location_id'] ?? [];
$differences = $_POST['difference'] ?? [];
$note = trim((string) ($_POST['note'] ?? ''));

// Lay user dang dang nhap
$userId = (int) (
    $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? $_SESSION['user']['id']
    ?? 0
);

if ($userId <= 0) {
    receiptError(
        'Không xác định được tài khoản đăng nhập. '
        . 'Kiểm tra tên biến session của dự án.',
        401
    );
}

if (!$requestId) {
    receiptError('Thiếu mã yêu cầu nhập kho.');
}

if (!is_array($locationIds) || !is_array($differences)) {
    receiptError('Dữ liệu chi tiết không hợp lệ.');
}

try {

    $pdo->beginTransaction();

    // 1. Kiem tra nguoi lap
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    if (!$stmt->fetchColumn()) {
        throw new Exception('Người lập phiếu không hợp lệ.');
    }

    // 2. Khoa yeu cau de tranh lap trung
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.request_code,
            r.po_id,
            r.qc_inspection_id,
            r.warehouse_id,
            po.po_code,
            q.passed_quantity

        FROM raw_material_inbound_requests r

        JOIN purchase_orders po
            ON po.id = r.po_id

        JOIN qc_inspections q
            ON q.id = r.qc_inspection_id

        JOIN warehouses w
            ON w.id = r.warehouse_id

        WHERE r.id = ?
          AND r.status = 'WAITING_RECEIPT'
          AND r.assigned_by IS NOT NULL
          AND po.status = 'APPROVED_FOR_RECEIPT'
          AND q.inspection_type = 'INCOMING_MATERIAL'
          AND q.result IN ('PASSED', 'PARTIAL')
          AND q.approval_status = 'APPROVED'
          AND q.reference_code = po.po_code
          AND w.type = 'RAW_MATERIAL'
          AND w.status = 'ACTIVE'

        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception(
            'Yêu cầu không tồn tại hoặc chưa đủ điều kiện.'
        );
    }

    // 3. Chong lap phieu trung QC
    $stmt = $pdo->prepare("
        SELECT id
        FROM stock_receipts
        WHERE qc_inspection_id = ?
        LIMIT 1
    ");

    $stmt->execute([$request['qc_inspection_id']]);

    if ($stmt->fetchColumn()) {
        throw new Exception(
            'Biên bản QC này đã có phiếu nhập kho.'
        );
    }

    // 4. Lay chi tiet lo QC
    $stmt = $pdo->prepare("
        SELECT
            d.lot_id,
            d.passed_qty,
            l.product_id,
            pi.unit_price

        FROM raw_material_qc_lot_details d

        JOIN product_lots l
            ON l.id = d.lot_id

        JOIN products p
            ON p.id = l.product_id

        JOIN po_items pi
            ON pi.po_id = ?
           AND pi.product_id = l.product_id

        WHERE d.qc_inspection_id = ?
          AND d.qc_result IN ('PASSED', 'PARTIAL')
          AND l.qc_status = 'PASSED'
          AND p.type = 'RAW_MATERIAL'
          AND d.passed_qty > 0

        ORDER BY d.lot_id
    ");

    $stmt->execute([
        $request['po_id'],
        $request['qc_inspection_id']
    ]);

    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$lots) {
        throw new Exception('Không có lô nguyên liệu đạt QC.');
    }

    // 5. Kiem tra tong so luong QC
    $totalPassed = 0;

    foreach ($lots as $lot) {
        $totalPassed += (int) $lot['passed_qty'];
    }

    if ($totalPassed > (int) $request['passed_quantity']) {
        throw new Exception(
            'Tổng số lượng lô vượt số lượng đạt QC.'
        );
    }

    // 6. Kiem tra vi tri thuoc kho chi dinh
    $checkLocation = $pdo->prepare("
        SELECT id
        FROM warehouse_locations
        WHERE id = ?
          AND warehouse_id = ?
        LIMIT 1
    ");

    $receiptItems = [];
    $hasDifference = false;

    foreach ($lots as $lot) {

        $lotId = (int) $lot['lot_id'];
        $passedQty = (int) $lot['passed_qty'];

        $locationId = filter_var(
            $locationIds[$lotId] ?? null,
            FILTER_VALIDATE_INT
        );

        $rawDifference = $differences[$lotId] ?? null;

        if (
            !is_scalar($rawDifference)
            || !preg_match('/^\d+$/', (string) $rawDifference)
        ) {
            throw new Exception(
                'Chênh lệch không hợp lệ tại lô ' . $lotId
            );
        }

        $difference = (int) $rawDifference;

        if ($difference < 0 || $difference > $passedQty) {
            throw new Exception(
                'Chênh lệch vượt số lượng đạt QC tại lô '
                . $lotId
            );
        }

        if (!$locationId) {
            throw new Exception(
                'Chưa chọn vị trí lưu trữ cho lô ' . $lotId
            );
        }

        $checkLocation->execute([
            $locationId,
            $request['warehouse_id']
        ]);

        if (!$checkLocation->fetchColumn()) {
            throw new Exception(
                'Vị trí không thuộc kho được chỉ định.'
            );
        }

        if ($difference > 0) {
            $hasDifference = true;
        }

        $quantity = $passedQty - $difference;

        // Khong tao dong nhap voi so luong bang 0
        if ($quantity > 0) {
            $receiptItems[] = [
                'product_id' => (int) $lot['product_id'],
                'lot_id' => $lotId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'unit_price' => $lot['unit_price']
            ];
        }
    }

    if ($hasDifference && $note === '') {
        throw new Exception(
            'Có chênh lệch thì phải nhập lý do.'
        );
    }

    if (!$receiptItems) {
        throw new Exception(
            'Không có số lượng nguyên liệu để nhập kho.'
        );
    }

    // 7. Tao ma phieu nhap duy nhat
    $receiptCode = 'PN-NL-'
        . date('YmdHis')
        . '-'
        . strtoupper(bin2hex(random_bytes(4)));

    // 8. Luu phieu nhap - CHUA DUYET
    $stmt = $pdo->prepare("
        INSERT INTO stock_receipts (
            receipt_code,
            receipt_type,
            reference_doc,
            qc_inspection_id,
            warehouse_id,
            created_by,
            approved_by,
            status,
            receipt_date,
            notes
        )
        VALUES (
            ?,
            'RAW_MATERIAL',
            ?,
            ?,
            ?,
            ?,
            NULL,
            'PENDING',
            NOW(),
            ?
        )
    ");

    $stmt->execute([
        $receiptCode,
        $request['po_code'],
        $request['qc_inspection_id'],
        $request['warehouse_id'],
        $userId,
        $note
    ]);

    $receiptId = (int) $pdo->lastInsertId();

    // 9. Luu chi tiet phieu nhap
    $stmt = $pdo->prepare("
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

    foreach ($receiptItems as $item) {
        $stmt->execute([
            $receiptId,
            $item['product_id'],
            $item['lot_id'],
            $item['location_id'],
            $item['quantity'],
            $item['unit_price']
        ]);
    }

    // 10. Cap nhat trang thai yeu cau
    $stmt = $pdo->prepare("
        UPDATE raw_material_inbound_requests
        SET status = 'RECEIPT_CREATED'
        WHERE id = ?
          AND status = 'WAITING_RECEIPT'
    ");

    $stmt->execute([$requestId]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception(
            'Không cập nhật được trạng thái yêu cầu.'
        );
    }

    /*
     * KHONG UPDATE inventory.
     * KHONG UPDATE po_items.received_qty.
     *
     * Hai phan nay chi duoc xu ly trong
     * quy trinh phe duyet/hoan tat nhap kho.
     */

    $pdo->commit();

    header(
        'Location: phieu_nhap_kho.php?success=1'
    );
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'FR-06 SAVE ERROR: ' . $e->getMessage()
    );

    receiptError($e->getMessage());
}
?>