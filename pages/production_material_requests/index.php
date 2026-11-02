<?php
$pageTitle = 'Lập phiếu yêu cầu nhận nguyên liệu';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('production_material_requests');

$pdo = Database::getConnection();

$pageError = '';
$flashMessage = null;
$plans = [];
$products = [];
$requests = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flashType = 'warning';
    $flashText = 'Thông tin xử lý không hợp lệ.';
    if (isset($_POST['review_action'])) {
        try {
            if (!hasRole(ROLE_STAFF)) {
                throw new DomainException('Bạn không có quyền duyệt phiếu.');
            }

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                throw new DomainException(
                    'Phiên làm việc hết hạn. Vui lòng tải lại trang.'
                );
            }

            if ($pdo === null) {
                throw new DomainException('Không thể kết nối database.');
            }

            $requestId = filter_var(
                $_POST['request_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $action = $_POST['review_action'];
            $rejectionReason = trim($_POST['rejection_reason'] ?? '');

            if ($requestId === false) {
                throw new DomainException('Mã phiếu không hợp lệ.');
            }

            if (!in_array($action, ['APPROVE', 'REJECT'], true)) {
                throw new DomainException('Thao tác không hợp lệ.');
            }

            if ($action === 'REJECT' && $rejectionReason === '') {
                throw new DomainException('Vui lòng nhập lý do từ chối.');
            }

            if (mb_strlen($rejectionReason, 'UTF-8') > 2000) {
                throw new DomainException(
                    'Lý do từ chối không được quá 2000 ký tự.'
                );
            }

            $currentUserId = (int) currentUser()['id'];

            $newStatus = $action === 'APPROVE'
                ? 'APPROVED'
                : 'REJECTED';

            // Chỉ xử lý phiếu đang chờ duyệt và không phải phiếu do mình lập.
            $reviewQuery = $pdo->prepare(
                "UPDATE production_material_requests
                 SET status = :status,
                     approved_by = :reviewer,
                     approved_at = NOW(),
                     rejection_reason = :reason
                 WHERE id = :id
                   AND status = 'PENDING'
                   AND created_by <> :reviewer_check"
            );

            $reviewQuery->execute([
                'status' => $newStatus,
                'reviewer' => $currentUserId,
                'reason' => $action === 'REJECT'
                    ? $rejectionReason
                    : null,
                'id' => $requestId,
                'reviewer_check' => $currentUserId
            ]);

            if ($reviewQuery->rowCount() !== 1) {
                throw new DomainException(
                    'Không thể xử lý phiếu. Phiếu có thể đã được xử lý hoặc do chính bạn lập.'
                );
            }

            $flashType = 'success';

            $flashText = $action === 'APPROVE'
                ? 'Đã duyệt phiếu thành công.'
                : 'Đã từ chối phiếu thành công.';
        } catch (DomainException $e) {
            $flashText = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Review material request failed: ' . $e->getMessage());

            $flashType = 'danger';
            $flashText = 'Không thể xử lý phiếu. Vui lòng thử lại.';
        }
    } else {
        try {
            if (!hasRole(ROLE_PLANNING) && !hasRole(ROLE_WORKSHOP)) {
                throw new DomainException(
                    'Bạn không có quyền lập phiếu nhận nguyên liệu.'
                );
            }

            if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
                throw new DomainException(
                    'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'
                );
            }

            if ($pdo === null) {
                throw new DomainException('Không thể kết nối database.');
            }

            $planCode = trim($_POST['plan_code'] ?? '');
            $reason = trim($_POST['reason'] ?? '');

            $productIds = $_POST['product_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $notes = $_POST['item_note'] ?? [];

            if ($planCode === '' || mb_strlen($reason, 'UTF-8') > 2000) {
                throw new DomainException(
                    'Vui lòng chọn KHSX và nhập lý do hợp lệ.'
                );
            }

            if (
                !is_array($productIds)
                || !is_array($quantities)
                || !is_array($notes)
                || count($productIds) < 1
                || count($productIds) > 100
                || count($productIds) !== count($quantities)
                || count($productIds) !== count($notes)
            ) {
                throw new DomainException(
                    'Danh sách nguyên liệu không hợp lệ.'
                );
            }

            $items = [];

            foreach ($productIds as $index => $rawId) {
                $productId = filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                $quantity = filter_var(
                    $quantities[$index] ?? null,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1,
                            'max_range' => 1000000
                        ]
                    ]
                );

                if ($productId === false || $quantity === false) {
                    throw new DomainException(
                        'Nguyên liệu hoặc số lượng không hợp lệ.'
                    );
                }

                if (isset($items[$productId])) {
                    throw new DomainException(
                        'Không được chọn trùng nguyên liệu.'
                    );
                }

                $itemNote = trim(
                    is_string($notes[$index] ?? null)
                        ? $notes[$index]
                        : ''
                );

                if (mb_strlen($itemNote, 'UTF-8') > 255) {
                    throw new DomainException(
                        'Ghi chú nguyên liệu không được quá 255 ký tự.'
                    );
                }

                $items[$productId] = [
                    'quantity' => $quantity,
                    'note' => $itemNote
                ];
            }

            $pdo->beginTransaction();
            $planQuery = $pdo->prepare(
                "SELECT plan_code
                 FROM production_plans
                 WHERE plan_code = :code
                   AND status IN ('PLANNED', 'IN_PROGRESS')
                 FOR UPDATE"
            );

            $planQuery->execute([
                'code' => $planCode
            ]);

            if (!$planQuery->fetch()) {
                throw new DomainException(
                    'KHSX không tồn tại hoặc đã kết thúc.'
                );
            }
            $checkProduct = $pdo->prepare(
                "SELECT id
                 FROM products
                 WHERE id = :id
                   AND type = 'RAW_MATERIAL'
                   AND status = 'ACTIVE'"
            );

            foreach ($items as $productId => $item) {
                $checkProduct->execute([
                    'id' => $productId
                ]);

                if (!$checkProduct->fetch()) {
                    throw new DomainException(
                        'Có nguyên liệu không tồn tại hoặc đã ngừng hoạt động.'
                    );
                }
            }

            $requestCode = 'YCNVL-'
                . date('Ymd')
                . '-'
                . strtoupper(bin2hex(random_bytes(4)));
            $insertRequest = $pdo->prepare(
                "INSERT INTO production_material_requests
                    (request_code, plan_code, created_by, reason, status)
                 VALUES
                    (:code, :plan, :user, :reason, 'PENDING')"
            );

            $insertRequest->execute([
                'code' => $requestCode,
                'plan' => $planCode,
                'user' => currentUser()['id'],
                'reason' => $reason !== '' ? $reason : null
            ]);

            $requestId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                "INSERT INTO production_material_request_items
                    (request_id, product_id, requested_quantity, note)
                 VALUES
                    (:request, :product, :quantity, :note)"
            );

            foreach ($items as $productId => $item) {
                $insertItem->execute([
                    'request' => $requestId,
                    'product' => $productId,
                    'quantity' => $item['quantity'],
                    'note' => $item['note'] !== ''
                        ? $item['note']
                        : null
                ]);
            }
            $log = $pdo->prepare(
                "INSERT INTO audit_logs
                    (user_id, action, module, record_id,
                     ip_address, user_agent, details)
                 VALUES
                    (:user, 'INSERT', 'PRODUCTION_MATERIAL_REQUEST',
                     :record, :ip, :agent, :details)"
            );

            $log->execute([
                'user' => currentUser()['id'],
                'record' => (string) $requestId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode([
                    'request_code' => $requestCode,
                    'plan_code' => $planCode,
                    'item_count' => count($items)
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            $pdo->commit();

            $flashType = 'success';
            $flashText = 'Đã tạo phiếu yêu cầu '
                . $requestCode
                . ' thành công.';
        } catch (DomainException $e) {
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $flashText = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo !== null && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Production material request failed: ' . $e->getMessage()
            );

            $flashType = 'danger';
            $flashText = 'Không thể lưu phiếu. Vui lòng kiểm tra database và thử lại.';
        }
    }

    setFlashMessage($flashType, $flashText);

    header(
        'Location: ' . BASE_URL . '/pages/production_material_requests/'
    );

    exit;
}
$flashMessage = consumeFlashMessage();

if ($pdo === null) {
    $pageError = 'Không thể kết nối database. Hãy kiểm tra MySQL.';
} else {
    try {
        $plans = $pdo->query(
            "SELECT pp.plan_code, pp.planned_quantity,
                    pp.start_date, pp.end_date, pp.status,
                    p.name AS product_name, p.sku, p.unit
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.status IN ('PLANNED', 'IN_PROGRESS')
             ORDER BY pp.created_at DESC"
        )->fetchAll();

        $products = $pdo->query(
            "SELECT id, sku, name, unit
             FROM products
             WHERE type = 'RAW_MATERIAL'
               AND status = 'ACTIVE'
             ORDER BY name"
        )->fetchAll();

        // Danh sách phiếu yêu cầu.
        $requests = $pdo->query(
            "SELECT r.id,
                    r.request_code,
                    r.plan_code,
                    r.created_by,
                    r.reason,
                    r.rejection_reason,
                    r.status,
                    r.created_at,
                    u.fullname AS creator_name,
                    COUNT(i.id) AS item_count
             FROM production_material_requests r
             INNER JOIN users u ON u.id = r.created_by
             LEFT JOIN production_material_request_items i
                    ON i.request_id = r.id
             GROUP BY
                    r.id,
                    r.request_code,
                    r.plan_code,
                    r.created_by,
                    r.reason,
                    r.rejection_reason,
                    r.status,
                    r.created_at,
                    u.fullname
             ORDER BY r.created_at DESC, r.id DESC"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log(
            'Material request listing failed: ' . $e->getMessage()
        );

        $pageError = 'Không thể tải dữ liệu. Hãy kiểm tra hai bảng yêu cầu nguyên liệu.';
    }
}


$statusLabels = [
    'PENDING' => ['Chờ duyệt', 'warning'],
    'APPROVED' => ['Đã duyệt', 'success'],
    'REJECTED' => ['Từ chối', 'danger'],
    'COMPLETED' => ['Hoàn thành', 'primary']
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">

    <div class="d-flex justify-content-between align-items-center
                flex-wrap gap-3 bg-white border rounded-3 p-3 mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-file-circle-plus text-primary me-2"></i>
                Lập phiếu yêu cầu nhận nguyên liệu
            </h4>

            <div class="text-muted small">
                Yêu cầu nguyên liệu căn cứ theo kế hoạch sản xuất.
            </div>
        </div>

        <?php if (
            (hasRole(ROLE_PLANNING) || hasRole(ROLE_WORKSHOP))
            && $pageError === ''
            && $plans !== []
            && $products !== []
        ): ?>
            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#requestModal">

                <i class="fa-solid fa-plus me-1"></i>
                Lập phiếu mới
            </button>
        <?php endif; ?>

    </div>
    <?php if ($flashMessage !== null): ?>
        <div class="alert alert-<?= htmlspecialchars(
                                    $flashMessage['type'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">
            <?= htmlspecialchars(
                $flashMessage['message'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars(
                $pageError,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info small">
        Phiếu yêu cầu không tự động trừ tồn kho.
        Kho sẽ xử lý xuất nguyên liệu sau khi phiếu được duyệt.
    </div>

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white fw-semibold">
            Danh sách phiếu yêu cầu nhận nguyên liệu
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Mã KHSX</th>
                        <th>Số loại nguyên liệu</th>
                        <th>Lý do</th>
                        <th>Người lập</th>
                        <th>Ngày lập</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($requests === []): ?>

                        <tr>
                            <td colspan="8"
                                class="text-center text-muted py-4">
                                Chưa có phiếu yêu cầu nguyên liệu.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($requests as $request): ?>

                            <?php
                            $status = $statusLabels[$request['status']]
                                ?? ['Không xác định', 'secondary'];
                            ?>

                            <tr>
                                <td>
                                    <strong class="text-primary">
                                        <?= htmlspecialchars(
                                            $request['request_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        $request['plan_code'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>
                                <td>
                                    <?= (int) $request['item_count'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        $request['reason'] ?? '—',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        $request['creator_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <!-- NGÀY LẬP -->
                                <td>
                                    <?= htmlspecialchars(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($request['created_at'])
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?= $status[1] ?>">
                                        <?= htmlspecialchars(
                                            $status[0],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <?php if (
                                        $request['status'] === 'REJECTED'
                                        && !empty($request['rejection_reason'])
                                    ): ?>
                                        <div class="small text-danger mt-1">
                                            Lý do:
                                            <?= htmlspecialchars(
                                                $request['rejection_reason'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 230px;">

                                    <?php if (
                                        hasRole(ROLE_STAFF)
                                        && $request['status'] === 'PENDING'
                                        && (int) $request['created_by']
                                        !== (int) currentUser()['id']
                                    ): ?>

                                        <form
                                            method="post"
                                            class="mb-2"
                                            onsubmit="return confirm('Bạn chắc chắn muốn duyệt phiếu này?');">

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int) $request['id'] ?>">

                                            <input
                                                type="hidden"
                                                name="review_action"
                                                value="APPROVE">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-success">

                                                <i class="fa-solid fa-check me-1"></i>
                                                Duyệt
                                            </button>

                                        </form>

                                        <form method="post">

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int) $request['id'] ?>">

                                            <input
                                                type="hidden"
                                                name="review_action"
                                                value="REJECT">

                                            <textarea
                                                name="rejection_reason"
                                                class="form-control form-control-sm mb-1"
                                                rows="2"
                                                maxlength="2000"
                                                placeholder="Nhập lý do từ chối..."
                                                required></textarea>

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bạn chắc chắn muốn từ chối phiếu này?');">

                                                <i class="fa-solid fa-xmark me-1"></i>
                                                Từ chối
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="text-muted small">
                                            Không có thao tác
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>
<?php if (
    (hasRole(ROLE_PLANNING) || hasRole(ROLE_WORKSHOP))
    && $pageError === ''
    && $plans !== []
    && $products !== []
): ?>

    <div
        class="modal fade"
        id="requestModal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-xl">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Lập phiếu yêu cầu nhận nguyên liệu
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <form method="post">

                    <div class="modal-body">

                        <?= csrfField() ?>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Kế hoạch sản xuất *
                            </label>

                            <select
                                class="form-select"
                                name="plan_code"
                                required>

                                <option value="">
                                    -- Chọn kế hoạch sản xuất --
                                </option>

                                <?php foreach ($plans as $plan): ?>

                                    <option value="<?= htmlspecialchars(
                                                        $plan['plan_code'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>">

                                        <?= htmlspecialchars(
                                            $plan['plan_code']
                                                . ' - '
                                                . $plan['product_name']
                                                . ' - '
                                                . number_format(
                                                    (int) $plan['planned_quantity']
                                                )
                                                . ' '
                                                . $plan['unit'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>
                        <div class="d-flex justify-content-between
                                    align-items-center mb-2">

                            <label class="form-label fw-semibold mb-0">
                                Danh sách nguyên liệu *
                            </label>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="addMaterial">

                                <i class="fa-solid fa-plus me-1"></i>
                                Thêm nguyên liệu
                            </button>

                        </div>

                        <div id="materialRows">

                            <div class="row g-2 mb-2 material-row">

                                <div class="col-md-6">

                                    <select
                                        class="form-select"
                                        name="product_id[]"
                                        required>

                                        <option value="">
                                            -- Chọn nguyên liệu --
                                        </option>

                                        <?php foreach ($products as $product): ?>

                                            <option value="<?= (int) $product['id'] ?>">

                                                <?= htmlspecialchars(
                                                    $product['sku']
                                                        . ' - '
                                                        . $product['name']
                                                        . ' ('
                                                        . $product['unit']
                                                        . ')',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-2">

                                    <input
                                        class="form-control"
                                        type="number"
                                        name="quantity[]"
                                        min="1"
                                        max="1000000"
                                        placeholder="Số lượng"
                                        required>

                                </div>

                                <div class="col-md-3">

                                    <input
                                        class="form-control"
                                        type="text"
                                        name="item_note[]"
                                        maxlength="255"
                                        placeholder="Ghi chú">

                                </div>

                                <div class="col-md-1">

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger removeMaterial"
                                        disabled>

                                        <i class="fa-solid fa-trash"></i>

                                    </button>

                                </div>

                            </div>

                        </div>
                        <div class="mt-3">

                            <label class="form-label fw-semibold">
                                Lý do yêu cầu
                            </label>

                            <textarea
                                class="form-control"
                                name="reason"
                                rows="3"
                                maxlength="2000"
                                placeholder="Nhập lý do cần nhận nguyên liệu...">
                            </textarea>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                            Hủy
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fa-solid fa-paper-plane me-1"></i>
                            Gửi phiếu yêu cầu

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const container = document.getElementById('materialRows');
            const addButton = document.getElementById('addMaterial');

            addButton.addEventListener('click', function() {

                const firstRow = container.querySelector('.material-row');
                const newRow = firstRow.cloneNode(true);

                newRow.querySelectorAll('select, input').forEach(function(field) {
                    field.value = '';
                });

                newRow.querySelector('.removeMaterial').disabled = false;

                container.appendChild(newRow);

                updateRemoveButtons();
            });

            container.addEventListener('click', function(event) {

                const button = event.target.closest('.removeMaterial');

                if (
                    button &&
                    container.querySelectorAll('.material-row').length > 1
                ) {
                    button.closest('.material-row').remove();

                    updateRemoveButtons();
                }

            });

            function updateRemoveButtons() {

                const rows = container.querySelectorAll('.material-row');

                rows.forEach(function(row) {

                    row.querySelector('.removeMaterial').disabled =
                        rows.length === 1;

                });

            }

        });
    </script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>