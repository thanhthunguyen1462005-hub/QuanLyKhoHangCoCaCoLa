<?php
$pageTitle = 'Lập Phiếu Nhập Kho Nguyên Liệu';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

requirePageAccess('receipts_raw');

$pdo = Database::getConnection();

function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$requestId = filter_input(
    INPUT_GET,
    'request_id',
    FILTER_VALIDATE_INT
);

if (!$requestId) {
    header('Location: ./?type=raw');
    exit;
}

// Lấy yêu cầu nhập và kho đã được chỉ định
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.request_code,
        r.warehouse_id,
        r.po_id,
        r.qc_inspection_id,
        r.status,
        po.po_code,
        po.status AS po_status,
        s.name AS supplier_name,
        q.qc_code,
        q.result AS qc_result,
        q.approval_status,
        w.code AS warehouse_code,
        w.name AS warehouse_name,
        w.type AS warehouse_type,
        w.status AS warehouse_status
    FROM raw_material_inbound_requests r
    JOIN purchase_orders po ON po.id = r.po_id
    JOIN qc_inspections q ON q.id = r.qc_inspection_id
    JOIN warehouses w ON w.id = r.warehouse_id
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$request ||
    $request['status'] !== 'WAITING_RECEIPT' ||
    $request['po_status'] !== 'APPROVED_FOR_RECEIPT' ||
    $request['approval_status'] !== 'APPROVED' ||
    !in_array($request['qc_result'], ['PASSED', 'PARTIAL'], true) ||
    $request['warehouse_type'] !== 'RAW_MATERIAL' ||
    $request['warehouse_status'] !== 'ACTIVE'
) {
    http_response_code(404);
    exit('Yêu cầu nhập kho không hợp lệ hoặc chưa được duyệt.');
}

// Lấy tất cả nguyên liệu và lô thuộc QC
$stmt = $pdo->prepare("
    SELECT
        d.lot_id,
        d.passed_qty,
        d.rejected_qty,
        d.qc_result,
        l.lot_number,
        l.qc_status AS lot_qc_status,
        l.mfg_date,
        l.expiry_date,
        p.id AS product_id,
        p.sku,
        p.name AS product_name,
        p.unit
    FROM raw_material_qc_lot_details d
    JOIN product_lots l ON l.id = d.lot_id
    JOIN products p ON p.id = l.product_id
    WHERE d.qc_inspection_id = ?
      AND p.type = 'RAW_MATERIAL'
    ORDER BY p.name, l.lot_number
");
$stmt->execute([$request['qc_inspection_id']]);
$lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Chỉ lấy vị trí thuộc kho Quản lý kho đã chỉ định
$stmt = $pdo->prepare("
    SELECT id, location_code
    FROM warehouse_locations
    WHERE warehouse_id = ?
    ORDER BY location_code
");
$stmt->execute([$request['warehouse_id']]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">

    <div class="d-flex justify-content-between
                align-items-center mb-4">
        <h4 class="fw-bold">
            <i class="fa-solid fa-boxes-stacked me-2"></i>
            Lập Phiếu Nhập Kho Nguyên Liệu
        </h4>

        <a href="./?type=raw" class="btn btn-secondary">
            Quay lại
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Thông tin yêu cầu nhập kho</h5>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Mã yêu cầu</label>
                    <input class="form-control" value="<?= e($request['request_code']) ?>" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Đơn mua hàng</label>
                    <input class="form-control" value="<?= e($request['po_code']) ?>" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Biên bản QC</label>
                    <input class="form-control" value="<?= e($request['qc_code']) ?>" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nhà cung cấp</label>
                    <input class="form-control" value="<?= e($request['supplier_name']) ?>" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Kho do Quản lý kho chỉ định
                    </label>
                    <input class="form-control" value="<?= e($request['warehouse_name']) ?>" readonly>
                    <small class="text-muted">
                        Nhân viên kho không được thay đổi kho.
                    </small>
                </div>

            </div>
        </div>
    </div>

    <form id="receiptForm" action="nhapkho.php" method="POST">

        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

        <div class="card shadow-sm">

            <div class="card-header bg-white">
                <h5 class="mb-1">
                    Danh sách nguyên liệu nhập theo số lô
                </h5>
                <small class="text-muted">
                    Chọn nhiều lô, chọn vị trí lưu trữ.
                    Số lượng được tự động lấy từ QC.
                </small>
            </div>

            <div class="card-body">

                <?php if (empty($lots)): ?>
                    <div class="alert alert-warning">
                        Chưa có nguyên liệu hoặc số lô đạt QC.
                    </div>
                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-bordered
                                  table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Chọn</th>
                                    <th>Mã nguyên liệu</th>
                                    <th>Tên nguyên liệu</th>
                                    <th>Số lô</th>
                                    <th>Số lượng đạt QC</th>
                                    <th>Vị trí nhập kho</th>
                                </tr>
                            </thead>

                            <tbody>
                                        <?php foreach ($lots as $lot): ?>
                                            <?php
                                            $lotId = (int) $lot['lot_id'];
                                            $valid =
                                                $lot['qc_result'] === 'PASSED' &&
                                                $lot['lot_qc_status'] === 'PASSED' &&
                                                (float) $lot['passed_qty'] > 0;
                                            ?>

                                    <tr>
                                        <td class="text-center">
                                                    <?php if ($valid): ?>
                                                <input type="checkbox" class="form-check-input lot-check" name="selected_lots[]"
                                                    value="<?= $lotId ?>">
                                                    <?php else: ?>
                                                <span class="text-danger">
                                                    Không đạt
                                                </span>
                                                    <?php endif; ?>
                                        </td>

                                        <td><?= e($lot['sku']) ?></td>

                                        <td>
                                                    <?= e($lot['product_name']) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                        <?= e($lot['lot_number']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                                    <?= number_format(
                                                        (float) $lot['passed_qty'],
                                                        2,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                                    <?= e($lot['unit']) ?>

                                            <div class="small text-muted">
                                                Tự động lấy từ QC
                                            </div>
                                        </td>

                                        <td>
                                                    <?php if ($valid): ?>
                                                <select class="form-select location-select" name="locations[<?= $lotId ?>]">
                                                    <option value="">
                                                        -- Chọn vị trí --
                                                    </option>

                                                            <?php foreach ($locations as $location): ?>
                                                        <option value="<?= (int) $location['id'] ?>">
                                                                    <?= e($location['location_code']) ?>
                                                        </option>
                                                            <?php endforeach; ?>
                                                </select>
                                                    <?php else: ?>
                                                <span class="text-muted">
                                                    Không được nhập
                                                </span>
                                                    <?php endif; ?>
                                        </td>
                                    </tr>

                                        <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

                <?php if (empty($locations)): ?>
                    <div class="alert alert-warning">
                        Kho được chỉ định chưa có vị trí lưu trữ.
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Nhập ghi chú nếu có..."></textarea>
                </div>

                <div id="messageBox" class="mt-3"></div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="./?type=raw" class="btn btn-secondary">
                        Hủy
                    </a>

                    <button type="submit" id="submitBtn" class="btn btn-primary" <?= empty($lots) || empty($locations)
                        ? 'disabled' : '' ?>>
                        Xác nhận nhập kho
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('receiptForm');
        const button = document.getElementById('submitBtn');
        const messageBox = document.getElementById('messageBox');

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            messageBox.textContent = '';

            const selected = form.querySelectorAll(
                '.lot-check:checked'
            );

            if (selected.length === 0) {
                alert('Vui lòng chọn ít nhất một số lô.');
                return;
            }

            for (const checkbox of selected) {
                const row = checkbox.closest('tr');
                const location = row.querySelector('.location-select');

                if (!location || !location.value) {
                    alert('Vui lòng chọn vị trí cho tất cả lô đã chọn.');
                    return;
                }
            }

            if (!confirm(
                'Xác nhận nhập các lô đã chọn và cập nhật tồn kho?'
            )) {
                return;
            }

            button.disabled = true;
            button.textContent = 'Đang lưu phiếu...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || 'Lưu phiếu thất bại.'
                    );
                }

                messageBox.innerHTML =
                    '<div class="alert alert-success"></div>';

                messageBox.firstElementChild.textContent =
                    result.message;

                window.setTimeout(function () {
                    window.location.href = './?type=raw';
                }, 1200);

            } catch (error) {
                messageBox.innerHTML =
                    '<div class="alert alert-danger"></div>';

                messageBox.firstElementChild.textContent =
                    error.message;

                button.disabled = false;
                button.textContent = 'Xác nhận nhập kho';
            }
        });
    });
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>