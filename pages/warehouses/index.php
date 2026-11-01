<?php
/**
 * FR-02: Danh mục và tình trạng vận hành kho.
 */
$pageTitle = 'Hệ Thống Kho';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('warehouses');

$pdo = Database::getConnection();
$canManageWarehouses = hasRole(ROLE_MANAGER);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flashType = 'warning';
    $flashText = 'Yêu cầu không hợp lệ.';

    if (!$canManageWarehouses) {
        $flashText = 'Chỉ Quản lý kho mới có quyền thêm hoặc cập nhật danh mục kho.';
    } elseif (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashText = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($pdo === null) {
        $flashType = 'danger';
        $flashText = 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL.';
    } else {
        $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $code = strtoupper(trim(is_string($_POST['code'] ?? null) ? $_POST['code'] : ''));
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        $capacityInput = $_POST['capacity_m3'] ?? '';
        $capacity = filter_var($capacityInput, FILTER_VALIDATE_FLOAT);
        $location = trim(is_string($_POST['location_description'] ?? null) ? $_POST['location_description'] : '');
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : 'ACTIVE';
        $managerInput = $_POST['manager_id'] ?? '';
        $managerId = $managerInput === '' ? null : filter_var($managerInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!in_array($_POST['action'] ?? '', ['create', 'update'], true)
            || ($_POST['action'] === 'update' && $warehouseId === false)
            || !preg_match('/^[A-Z0-9][A-Z0-9-]{1,49}$/', $code)
            || $name === ''
            || mb_strlen($name, 'UTF-8') > 150
            || !in_array($type, ['RAW_MATERIAL', 'FINISHED_GOODS', 'DEFECTIVE_RETURN'], true)
            || $capacity === false
            || $capacity <= 0
            || ($managerInput !== '' && $managerId === false)
            || !in_array($status, ['ACTIVE', 'MAINTENANCE', 'INACTIVE'], true)
            || mb_strlen($location, 'UTF-8') > 255) {
            $flashText = 'Dữ liệu kho không hợp lệ. Vui lòng kiểm tra mã, tên, loại kho, sức chứa và trạng thái.';
        } else {
            try {
                if ($managerId !== null) {
                    $managerQuery = $pdo->prepare(
                        "SELECT u.id
                         FROM users u
                         INNER JOIN roles r ON r.id = u.role_id
                         WHERE u.id = :id AND r.code = 'manager' AND u.is_active = 1"
                    );
                    $managerQuery->execute(['id' => $managerId]);
                    if ($managerQuery->fetchColumn() === false) {
                        throw new DomainException('Người phụ trách phải là tài khoản Quản lý kho đang hoạt động.');
                    }
                }

                $values = [
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'capacity_m3' => $capacity,
                    'manager_id' => $managerId,
                    'location_description' => $location !== '' ? $location : null,
                    'status' => $status,
                ];

                $pdo->beginTransaction();
                $previousValues = null;
                if ($_POST['action'] === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO warehouses (code, name, type, capacity_m3, manager_id, location_description, status)
                         VALUES (:code, :name, :type, :capacity_m3, :manager_id, :location_description, :status)'
                    );
                    $statement->execute($values);
                    $warehouseId = (int) $pdo->lastInsertId();
                    $logAction = 'INSERT';
                } else {
                    $previousQuery = $pdo->prepare(
                        'SELECT code, name, type, capacity_m3, manager_id, location_description, status
                         FROM warehouses WHERE id = :id FOR UPDATE'
                    );
                    $previousQuery->execute(['id' => $warehouseId]);
                    $previousValues = $previousQuery->fetch();
                    if ($previousValues === false) {
                        throw new DomainException('Không tìm thấy kho cần cập nhật.');
                    }

                    $values['id'] = $warehouseId;
                    $statement = $pdo->prepare(
                        'UPDATE warehouses
                         SET code = :code, name = :name, type = :type, capacity_m3 = :capacity_m3,
                             manager_id = :manager_id, location_description = :location_description, status = :status
                         WHERE id = :id'
                    );
                    $statement->execute($values);
                    $logAction = 'UPDATE';
                }

                $logDetails = json_encode([
                    'warehouse_code' => $code,
                    'before' => $previousValues,
                    'after' => $values,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($logDetails === false) {
                    throw new RuntimeException('Không thể mã hóa chi tiết thay đổi kho.');
                }
                $log = $pdo->prepare(
                    'INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                     VALUES (:user_id, :action, :module, :record_id, :ip_address, :user_agent, :details)'
                );
                $log->execute([
                    'user_id' => currentUser()['id'],
                    'action' => $logAction,
                    'module' => 'WAREHOUSE',
                    'record_id' => (string) $warehouseId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => $logDetails,
                ]);
                $pdo->commit();
                $flashType = 'success';
                $flashText = $_POST['action'] === 'create' ? 'Đã thêm kho vào danh mục.' : 'Đã cập nhật thông tin kho.';
            } catch (DomainException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $flashText = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Warehouse write failed: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    $flashText = 'Mã kho đã được sử dụng hoặc dữ liệu đang được chứng từ khác tham chiếu.';
                } else {
                    $flashType = 'danger';
                    $flashText = 'Không thể lưu kho do lỗi database. Vui lòng kiểm tra cấu hình và thử lại.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Warehouse write failed: ' . $exception->getMessage());
                $flashType = 'danger';
                $flashText = 'Không thể lưu thông tin kho do lỗi hệ thống.';
            }
        }
    }

    setFlashMessage($flashType, $flashText);
    header('Location: ' . BASE_URL . '/pages/warehouses/');
    exit;
}

$flashMessage = consumeFlashMessage();
$warehouseType = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$warehouseStatus = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$allowedTypes = ['RAW_MATERIAL', 'FINISHED_GOODS', 'DEFECTIVE_RETURN'];
$allowedStatuses = ['ACTIVE', 'MAINTENANCE', 'INACTIVE'];
$filterError = '';

if ($warehouseType !== '' && !in_array($warehouseType, $allowedTypes, true)) {
    $filterError = 'Loại kho được chọn không hợp lệ.';
    $warehouseType = '';
}
if ($warehouseStatus !== '' && !in_array($warehouseStatus, $allowedStatuses, true)) {
    $filterError = 'Trạng thái kho được chọn không hợp lệ.';
    $warehouseStatus = '';
}

$warehouses = [];
$managers = [];
$pageError = '';
if ($pdo === null) {
    $pageError = 'Không thể kết nối cơ sở dữ liệu. Danh mục kho cần MySQL đang hoạt động.';
} else {
    try {
        if ($canManageWarehouses) {
            $managers = $pdo->query(
                "SELECT u.id, u.fullname
                 FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.code = 'manager' AND u.is_active = 1
                 ORDER BY u.fullname"
            )->fetchAll();
        }

        $conditions = [];
        $parameters = [];
        if ($warehouseType !== '') {
            $conditions[] = 'w.type = :type';
            $parameters['type'] = $warehouseType;
        }
        if ($warehouseStatus !== '') {
            $conditions[] = 'w.status = :status';
            $parameters['status'] = $warehouseStatus;
        }
        if ($search !== '') {
            $conditions[] = "(w.code LIKE :search_code ESCAPE '!' OR w.name LIKE :search_name ESCAPE '!'
                OR w.location_description LIKE :search_location ESCAPE '!')";
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $likeSearch = '%' . $escapedSearch . '%';
            $parameters['search_code'] = $likeSearch;
            $parameters['search_name'] = $likeSearch;
            $parameters['search_location'] = $likeSearch;
        }

        $whereClause = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $query = $pdo->prepare(
            'SELECT w.id, w.code, w.name, w.type, w.capacity_m3, w.location_description, w.status,
                    w.manager_id, manager.fullname AS manager_name,
                    COUNT(DISTINCT wl.id) AS location_count,
                    COUNT(DISTINCT CASE WHEN i.quantity > 0 THEN i.location_id END) AS occupied_locations,
                    COUNT(DISTINCT CASE WHEN i.quantity > 0 THEN i.id END) AS stock_positions,
                    COUNT(DISTINCT CASE WHEN i.quantity > 0 THEN i.product_id END) AS sku_count,
                    COALESCE(SUM(i.quantity * p.cost_price), 0) AS stock_value
             FROM warehouses w
             LEFT JOIN users manager ON manager.id = w.manager_id
             LEFT JOIN warehouse_locations wl ON wl.warehouse_id = w.id
             LEFT JOIN inventory i ON i.warehouse_id = w.id AND i.location_id = wl.id
             LEFT JOIN products p ON p.id = i.product_id'
            . $whereClause .
            ' GROUP BY w.id, w.code, w.name, w.type, w.capacity_m3, w.location_description, w.status,
                      w.manager_id, manager.fullname
              ORDER BY w.code'
        );
        $query->execute($parameters);
        $warehouses = $query->fetchAll();
    } catch (Throwable $exception) {
        error_log('Warehouse directory query failed: ' . $exception->getMessage());
        $pageError = 'Không thể tải danh mục kho từ database.';
        $warehouses = [];
    }
}

$warehouseTypeLabels = [
    'RAW_MATERIAL' => 'Nguyên liệu / Bao bì',
    'FINISHED_GOODS' => 'Thành phẩm',
    'DEFECTIVE_RETURN' => 'Cách ly / Hàng trả về',
];
$warehouseStatusLabels = [
    'ACTIVE' => 'Đang hoạt động',
    'MAINTENANCE' => 'Đang bảo trì',
    'INACTIVE' => 'Ngừng hoạt động',
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white p-3 rounded-3 border mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-warehouse text-primary me-2"></i>Hệ Thống Kho (FR-02)</h4>
            <div class="small text-muted">Danh mục, trạng thái, người phụ trách và số liệu tồn kho theo từng kho.</div>
        </div>
        <?php if ($canManageWarehouses): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                <i class="fa-solid fa-plus me-1"></i>Thêm kho
            </button>
        <?php endif; ?>
    </div>

    <?php if ($flashMessage !== null): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>
    <?php if ($filterError !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="warehouseSearch" class="form-label small fw-semibold">Tìm kho</label>
                    <input type="search" id="warehouseSearch" name="q" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Mã kho, tên kho, vị trí...">
                </div>
                <div class="col-md-3">
                    <label for="warehouseType" class="form-label small fw-semibold">Loại kho</label>
                    <select id="warehouseType" name="type" class="form-select form-select-sm">
                        <option value="">Tất cả loại kho</option>
                        <?php foreach ($warehouseTypeLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $warehouseType === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="warehouseStatus" class="form-label small fw-semibold">Trạng thái</label>
                    <select id="warehouseStatus" name="status" class="form-select form-select-sm">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach ($warehouseStatusLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $warehouseStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Lọc</button>
                    <a href="<?= BASE_URL ?>/pages/warehouses/" class="btn btn-outline-secondary btn-sm">Xóa</a>
                </div>
            </div>
        </div>
    </form>

    <div class="custom-table shadow-sm">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <h6 class="fw-bold mb-0">Danh mục kho</h6>
            <span class="badge bg-primary"><?= number_format(count($warehouses)) ?> kho</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã kho</th><th>Tên kho / Vị trí</th><th>Loại</th><th class="text-end">Sức chứa</th>
                        <th>Phụ trách</th><th class="text-center">Vị trí có hàng</th>
                        <th class="text-center">Dòng tồn / SKU</th><th class="text-end">Giá trị tồn</th><th>Trạng thái</th>
                        <th class="text-end">Tồn kho</th><?php if ($canManageWarehouses): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($warehouse['location_description'] ?? 'Chưa cập nhật vị trí', ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td><?= htmlspecialchars($warehouseTypeLabels[$warehouse['type']] ?? $warehouse['type'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end text-nowrap"><?= number_format((float) $warehouse['capacity_m3']) ?> m³</td>
                            <td><?= htmlspecialchars($warehouse['manager_name'] ?? 'Chưa phân công', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= number_format((int) $warehouse['occupied_locations']) ?> / <?= number_format((int) $warehouse['location_count']) ?></td>
                            <td class="text-center"><?= number_format((int) $warehouse['stock_positions']) ?> / <?= number_format((int) $warehouse['sku_count']) ?></td>
                            <td class="text-end text-nowrap"><?= formatCurrency((float) $warehouse['stock_value']) ?></td>
                            <td><?= htmlspecialchars($warehouseStatusLabels[$warehouse['status']] ?? $warehouse['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= BASE_URL ?>/pages/inventory/?warehouse=<?= rawurlencode($warehouse['code']) ?>" class="btn btn-outline-primary btn-sm">Tra cứu</a>
                            </td>
                            <?php if ($canManageWarehouses): ?>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#warehouseModal"
                                            data-id="<?= (int) $warehouse['id'] ?>"
                                            data-code="<?= htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-type="<?= htmlspecialchars($warehouse['type'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-capacity="<?= htmlspecialchars((string) $warehouse['capacity_m3'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-location="<?= htmlspecialchars($warehouse['location_description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-status="<?= htmlspecialchars($warehouse['status'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-manager="<?= (int) ($warehouse['manager_id'] ?? 0) ?>">
                                        Sửa
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($warehouses === [] && $pageError === ''): ?>
                        <tr><td colspan="<?= $canManageWarehouses ? 11 : 10 ?>" class="text-center text-muted py-4">Không tìm thấy kho phù hợp.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!$canManageWarehouses): ?>
        <div class="small text-muted mt-3">Bạn có quyền tra cứu danh mục kho. Chỉ Quản lý kho mới có thể thêm hoặc cập nhật thông tin kho.</div>
    <?php endif; ?>
</div>

<?php if ($canManageWarehouses): ?>
    <div class="modal fade" id="warehouseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="warehouseModalTitle">Thêm kho</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="warehouse_id" id="warehouseId">
                        <input type="hidden" name="action" id="warehouseAction" value="create">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="warehouseCode" class="form-label">Mã kho <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="warehouseCode" name="code" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9-]{1,49}" required>
                            </div>
                            <div class="col-md-8">
                                <label for="warehouseName" class="form-label">Tên kho <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="warehouseName" name="name" maxlength="150" required>
                            </div>
                            <div class="col-md-6">
                                <label for="warehouseTypeInput" class="form-label">Loại kho <span class="text-danger">*</span></label>
                                <select class="form-select" id="warehouseTypeInput" name="type" required>
                                    <?php foreach ($warehouseTypeLabels as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="warehouseCapacity" class="form-label">Sức chứa (m³) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="warehouseCapacity" name="capacity_m3" min="0.01" step="0.01" required>
                            </div>
                            <div class="col-md-6">
                                <label for="warehouseManager" class="form-label">Quản lý phụ trách</label>
                                <select class="form-select" id="warehouseManager" name="manager_id">
                                    <option value="">Chưa phân công</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?= (int) $manager['id'] ?>"><?= htmlspecialchars($manager['fullname'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="warehouseStatusInput" class="form-label">Trạng thái</label>
                                <select class="form-select" id="warehouseStatusInput" name="status">
                                    <?php foreach ($warehouseStatusLabels as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="warehouseLocation" class="form-label">Vị trí / Mô tả</label>
                                <input type="text" class="form-control" id="warehouseLocation" name="location_description" maxlength="255">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Lưu kho</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('warehouseModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const editing = Boolean(button.dataset.id);
            document.getElementById('warehouseModalTitle').textContent = editing ? 'Cập nhật kho' : 'Thêm kho';
            document.getElementById('warehouseAction').value = editing ? 'update' : 'create';
            document.getElementById('warehouseId').value = editing ? button.dataset.id : '';
            document.getElementById('warehouseCode').value = editing ? button.dataset.code : '';
            document.getElementById('warehouseName').value = editing ? button.dataset.name : '';
            document.getElementById('warehouseTypeInput').value = editing ? button.dataset.type : 'RAW_MATERIAL';
            document.getElementById('warehouseCapacity').value = editing ? button.dataset.capacity : '';
            document.getElementById('warehouseLocation').value = editing ? button.dataset.location : '';
            document.getElementById('warehouseStatusInput').value = editing ? button.dataset.status : 'ACTIVE';
            document.getElementById('warehouseManager').value = editing && button.dataset.manager !== '0' ? button.dataset.manager : '';
            document.getElementById('warehouseCode').readOnly = editing;
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
