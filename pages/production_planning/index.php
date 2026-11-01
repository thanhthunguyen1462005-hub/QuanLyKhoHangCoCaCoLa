<?php
$pageTitle = 'Lập kế hoạch sản xuất';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('production_planning');

$pdo = Database::getConnection();
$pageError = '';
$flashMessage = null;
$products = [];
$plans = [];
$productId = '';
$quantityInput = '';
$startDate = '';
$endDate = '';
$notes = '';

$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
            ? $date
            : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flashType = 'warning';
    $flashText = 'Yêu cầu không hợp lệ.';

    if (!hasRole(ROLE_PLANNING)) {
        $flashText = 'Chỉ Bộ phận Lập kế hoạch mới được tạo kế hoạch sản xuất.';
    } elseif (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashText = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($pdo === null) {
        $flashType = 'danger';
        $flashText = 'Không thể kết nối database. Kế hoạch chưa được lưu.';
    } else {
        $productIdInput = $_POST['product_id'] ?? null;
        $quantityInput = $_POST['planned_quantity'] ?? null;
        $startDate = is_string($_POST['start_date'] ?? null) ? $_POST['start_date'] : '';
        $endDate = is_string($_POST['end_date'] ?? null) ? $_POST['end_date'] : '';
        $notes = trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : '');
        $validatedProductId = filter_var($productIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $validatedQuantity = filter_var($quantityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        $parsedStartDate = $parseDate($startDate);
        $parsedEndDate = $parseDate($endDate);

        if ($validatedProductId === false
            || $validatedQuantity === false
            || $parsedStartDate === null
            || $parsedEndDate === null
            || $parsedEndDate < $parsedStartDate
            || mb_strlen($notes, 'UTF-8') > 2000) {
            $flashText = 'Thông tin kế hoạch không hợp lệ. Hãy kiểm tra sản phẩm, số lượng, ngày và ghi chú.';
        } else {
            try {
                $pdo->beginTransaction();

                $productQuery = $pdo->prepare(
                    "SELECT id, sku, name, unit
                     FROM products
                     WHERE id = :id AND type = 'FINISHED_GOODS' AND status = 'ACTIVE'
                     FOR UPDATE"
                );
                $productQuery->execute(['id' => $validatedProductId]);
                $product = $productQuery->fetch();
                if ($product === false) {
                    throw new DomainException('Sản phẩm không tồn tại hoặc không phải thành phẩm đang hoạt động.');
                }

                $planCode = 'KHSX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $insertPlan = $pdo->prepare(
                    "INSERT INTO production_plans
                        (plan_code, product_id, planned_quantity, start_date, end_date, status, created_by, notes)
                     VALUES
                        (:plan_code, :product_id, :planned_quantity, :start_date, :end_date, 'PLANNED', :created_by, :notes)"
                );
                $insertPlan->execute([
                    'plan_code' => $planCode,
                    'product_id' => $validatedProductId,
                    'planned_quantity' => $validatedQuantity,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'created_by' => currentUser()['id'],
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $planId = (int) $pdo->lastInsertId();

                $log = $pdo->prepare(
                    "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                     VALUES (:user_id, 'INSERT', 'PRODUCTION_PLANNING', :record_id, :ip_address, :user_agent, :details)"
                );
                $log->execute([
                    'user_id' => currentUser()['id'],
                    'record_id' => (string) $planId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => json_encode([
                        'plan_code' => $planCode,
                        'product_sku' => $product['sku'],
                        'planned_quantity' => $validatedQuantity,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $pdo->commit();
                $flashType = 'success';
                $flashText = 'Đã lưu kế hoạch sản xuất ' . $planCode . ' vào database.';
            } catch (DomainException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $flashText = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Production plan creation failed: ' . $exception->getMessage());
                $flashType = 'danger';
                $flashText = 'Không thể lưu kế hoạch do lỗi hệ thống. Vui lòng thử lại.';
            }
        }
    }

    setFlashMessage($flashType, $flashText);
    header('Location: ' . BASE_URL . '/pages/production_planning/');
    exit;
}

$flashMessage = consumeFlashMessage();
if ($pdo === null) {
    $pageError = 'Không thể kết nối database. Vui lòng kiểm tra MySQL trên XAMPP.';
} else {
    try {
        $products = $pdo->query(
            "SELECT id, sku, name, unit
             FROM products
             WHERE type = 'FINISHED_GOODS' AND status = 'ACTIVE'
             ORDER BY name"
        )->fetchAll();
        $plans = $pdo->query(
            'SELECT pp.id, pp.plan_code, pp.planned_quantity, pp.start_date, pp.end_date,
                    pp.status, pp.notes, pp.created_at, p.sku, p.name AS product_name, p.unit,
                    u.fullname AS creator_name
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             INNER JOIN users u ON u.id = pp.created_by
             ORDER BY pp.created_at DESC, pp.id DESC'
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Production plan listing failed: ' . $exception->getMessage());
        $pageError = 'Không thể tải kế hoạch sản xuất từ database. Hãy chạy migration database/migrations/20261101_production_planning.sql.';
        $products = [];
        $plans = [];
    }
}

$statusLabels = [
    'PLANNED' => ['Dự kiến', 'secondary'],
    'IN_PROGRESS' => ['Đang thực hiện', 'primary'],
    'COMPLETED' => ['Hoàn thành', 'success'],
    'CANCELLED' => ['Đã hủy', 'danger'],
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white border rounded-3 p-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Lập kế hoạch sản xuất</h4>
            <div class="text-muted small">Kế hoạch được lưu trên MySQL và dùng chung giữa các tài khoản được cấp quyền.</div>
        </div>
        <?php if (hasRole(ROLE_PLANNING) && $pageError === '' && $products !== []): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal">
                <i class="fa-solid fa-plus me-1"></i>Tạo kế hoạch
            </button>
        <?php endif; ?>
    </div>

    <?php if ($flashMessage !== null): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>
    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="alert alert-info small">
        Dữ liệu tồn kho mẫu đã được loại bỏ. Hệ thống hiện chưa có định mức nguyên vật liệu (BOM) cho thành phẩm nên chưa tự tính khả năng đáp ứng nguyên liệu.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Danh sách kế hoạch sản xuất</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã kế hoạch</th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Trạng thái</th>
                        <th>Người tạo</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($plans === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <?= $pageError === '' ? 'Chưa có kế hoạch sản xuất trong database.' : 'Không có dữ liệu để hiển thị.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($plans as $plan): ?>
                            <?php $status = $statusLabels[$plan['status']] ?? ['Không xác định', 'secondary']; ?>
                            <tr>
                                <td><strong class="text-primary"><?= htmlspecialchars($plan['plan_code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td>
                                    <div><?= htmlspecialchars($plan['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($plan['sku'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td><?= number_format((int) $plan['planned_quantity']) ?> <?= htmlspecialchars($plan['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($plan['start_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($plan['end_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge text-bg-<?= htmlspecialchars($status[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status[0], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($plan['creator_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($plan['notes'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (hasRole(ROLE_PLANNING) && $pageError === '' && $products !== []): ?>
    <div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tạo kế hoạch sản xuất</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <?= csrfField() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="productId">Sản phẩm thành phẩm *</label>
                                <select class="form-select" id="productId" name="product_id" required>
                                    <option value="">-- Chọn sản phẩm --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= (int) $product['id'] ?>">
                                            <?= htmlspecialchars($product['sku'] . ' — ' . $product['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="plannedQuantity">Số lượng kế hoạch *</label>
                                <input class="form-control" type="number" id="plannedQuantity" name="planned_quantity"
                                       min="1" max="1000000" step="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="startDate">Ngày bắt đầu *</label>
                                <input class="form-control" type="date" id="startDate" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="endDate">Ngày kết thúc *</label>
                                <input class="form-control" type="date" id="endDate" name="end_date" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="planNotes">Ghi chú</label>
                                <textarea class="form-control" id="planNotes" name="notes" rows="2" maxlength="2000"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Lưu kế hoạch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php elseif (hasRole(ROLE_PLANNING) && $pageError === '' && $products === []): ?>
    <div class="alert alert-warning">Chưa có sản phẩm thành phẩm đang hoạt động để lập kế hoạch.</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
