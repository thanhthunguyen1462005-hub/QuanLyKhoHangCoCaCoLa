<?php
/**
 * FR-18: Tra cứu nhật ký thao tác hệ thống.
 */
$pageTitle = 'Nhật Ký Hệ Thống';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('audit_logs');

$pageSize = 25;
$pageNumber = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$pageNumber = $pageNumber === false ? 1 : $pageNumber;
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$actionFilter = trim(is_string($_GET['action'] ?? null) ? $_GET['action'] : '');
$moduleFilter = trim(is_string($_GET['module'] ?? null) ? $_GET['module'] : '');
$userFilter = filter_var($_GET['user_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$userFilter = $userFilter === false ? null : $userFilter;
$dateFrom = trim(is_string($_GET['from'] ?? null) ? $_GET['from'] : '');
$dateTo = trim(is_string($_GET['to'] ?? null) ? $_GET['to'] : '');
$pageError = '';
$invalidFilter = false;
$rows = [];
$actions = [];
$modules = [];
$users = [];
$totalRows = 0;

$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
            ? $date
            : null;
};

$userFilterInput = is_string($_GET['user_id'] ?? null) ? $_GET['user_id'] : '';
if ($userFilterInput !== '' && $userFilter === null) {
    $pageError = 'Người thực hiện được chọn không hợp lệ.';
    $invalidFilter = true;
}

if (($dateFrom !== '' && $parseDate($dateFrom) === null)
    || ($dateTo !== '' && $parseDate($dateTo) === null)
    || ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo)) {
    $pageError = 'Khoảng thời gian lọc không hợp lệ.';
    $invalidFilter = true;
}

$pdo = Database::getConnection();
if ($pdo === null) {
    $pageError = 'Không thể kết nối cơ sở dữ liệu. Nhật ký hệ thống cần MySQL đang hoạt động.';
} else {
    try {
        $actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
        $modules = $pdo->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
        $users = $pdo->query(
            'SELECT DISTINCT u.id, u.fullname
             FROM audit_logs l
             INNER JOIN users u ON u.id = l.user_id
             ORDER BY u.fullname'
        )->fetchAll();

        $conditions = [];
        $parameters = [];
        if ($invalidFilter) {
            $conditions[] = '1 = 0';
        }
        if ($search !== '') {
            $conditions[] = "(l.record_id LIKE :search_record ESCAPE '!' OR l.details LIKE :search_details ESCAPE '!'
                OR l.ip_address LIKE :search_ip ESCAPE '!' OR u.fullname LIKE :search_name ESCAPE '!'
                OR u.username LIKE :search_username ESCAPE '!')";
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $likeSearch = '%' . $escapedSearch . '%';
            $parameters['search_record'] = $likeSearch;
            $parameters['search_details'] = $likeSearch;
            $parameters['search_ip'] = $likeSearch;
            $parameters['search_name'] = $likeSearch;
            $parameters['search_username'] = $likeSearch;
        }
        if ($actionFilter !== '' && in_array($actionFilter, $actions, true)) {
            $conditions[] = 'l.action = :action';
            $parameters['action'] = $actionFilter;
        } elseif ($actionFilter !== '') {
            $pageError = 'Loại thao tác được chọn không tồn tại trong nhật ký.';
            $conditions[] = '1 = 0';
        }
        if ($moduleFilter !== '' && in_array($moduleFilter, $modules, true)) {
            $conditions[] = 'l.module = :module';
            $parameters['module'] = $moduleFilter;
        } elseif ($moduleFilter !== '') {
            $pageError = 'Phân hệ được chọn không tồn tại trong nhật ký.';
            $conditions[] = '1 = 0';
        }
        if ($userFilter !== null) {
            $conditions[] = 'l.user_id = :user_id';
            $parameters['user_id'] = $userFilter;
        }
        if ($dateFrom !== '') {
            $conditions[] = 'l.created_at >= :date_from';
            $parameters['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $conditions[] = 'l.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $parameters['date_to'] = $dateTo;
        }

        $whereClause = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countQuery = $pdo->prepare(
            'SELECT COUNT(*) FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id' . $whereClause
        );
        $countQuery->execute($parameters);
        $totalRows = (int) $countQuery->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $pageNumber = min($pageNumber, $totalPages);
        $offset = ($pageNumber - 1) * $pageSize;

        $logsQuery = $pdo->prepare(
            'SELECT l.id, l.action, l.module, l.record_id, l.ip_address, l.details, l.created_at,
                    u.username, u.fullname
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.user_id' . $whereClause . '
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :page_size OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $logsQuery->bindValue(':' . $name, $value);
        }
        $logsQuery->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
        $logsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
        $logsQuery->execute();
        $rows = $logsQuery->fetchAll();
    } catch (Throwable $exception) {
        error_log('Audit log search failed: ' . $exception->getMessage());
        $pageError = 'Không thể tra cứu nhật ký hệ thống. Vui lòng kiểm tra database.';
        $rows = [];
    }
}

$actionLabels = [
    'INITIALIZE' => 'Khởi tạo',
    'LOGIN' => 'Đăng nhập',
    'LOGIN_FAILED' => 'Đăng nhập thất bại',
    'LOGOUT' => 'Đăng xuất',
    'APPROVE' => 'Phê duyệt',
    'REJECT' => 'Từ chối',
    'INSERT' => 'Thêm mới',
    'UPDATE' => 'Cập nhật',
    'DELETE' => 'Xóa',
];
$moduleLabels = [
    'SYSTEM' => 'Hệ thống',
    'AUTHENTICATION' => 'Xác thực',
    'WAREHOUSE' => 'Danh mục kho',
    'STOCK_RECEIPT' => 'Phiếu nhập',
    'STOCK_ISSUE' => 'Phiếu xuất',
    'STOCK_TRANSFER' => 'Điều chuyển',
    'INVENTORY_AUDIT' => 'Biên bản kiểm kê',
];
$queryParameters = $_GET;
unset($queryParameters['page']);
$paginationUrl = static function (int $page) use ($queryParameters): string {
    $query = http_build_query(array_merge($queryParameters, ['page' => $page]));

    return BASE_URL . '/pages/audit_logs/?' . $query;
};

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white p-3 rounded-3 border mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Nhật Ký Hệ Thống (FR-18)</h4>
            <div class="text-muted small">Tra cứu thao tác đã được hệ thống ghi nhận; hiển thị mới nhất trước.</div>
        </div>
        <a href="<?= BASE_URL ?>/pages/reports/" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-chart-line me-1"></i>Báo cáo quản trị
        </a>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="logSearch" class="form-label small fw-semibold">Từ khóa</label>
                    <input type="search" id="logSearch" name="q" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Người dùng, mã bản ghi, IP, nội dung...">
                </div>
                <div class="col-md-2">
                    <label for="logAction" class="form-label small fw-semibold">Thao tác</label>
                    <select id="logAction" name="action" class="form-select form-select-sm">
                        <option value="">Tất cả thao tác</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $actionFilter === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($actionLabels[$action] ?? $action, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="logModule" class="form-label small fw-semibold">Phân hệ</label>
                    <select id="logModule" name="module" class="form-select form-select-sm">
                        <option value="">Tất cả phân hệ</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>" <?= $moduleFilter === $module ? 'selected' : '' ?>>
                                <?= htmlspecialchars($moduleLabels[$module] ?? $module, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="logUser" class="form-label small fw-semibold">Người thực hiện</label>
                    <select id="logUser" name="user_id" class="form-select form-select-sm">
                        <option value="">Tất cả người dùng</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" <?= $userFilter === (int) $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="logFrom" class="form-label small fw-semibold">Từ ngày</label>
                    <input type="date" id="logFrom" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label for="logTo" class="form-label small fw-semibold">Đến ngày</label>
                    <input type="date" id="logTo" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i>Lọc nhật ký</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= BASE_URL ?>/pages/audit_logs/" class="btn btn-outline-secondary btn-sm w-100">Xóa bộ lọc</a>
                </div>
            </div>
        </div>
    </form>

    <div class="custom-table shadow-sm">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <h6 class="fw-bold mb-0">Nhật ký thao tác</h6>
            <span class="badge bg-primary"><?= number_format($totalRows) ?> bản ghi</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Phân hệ</th><th>Mã bản ghi</th><th>IP</th><th>Chi tiết</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($row['fullname'] !== null): ?>
                                    <strong><?= htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Hệ thống</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-secondary"><?= htmlspecialchars($actionLabels[$row['action']] ?? $row['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($moduleLabels[$row['module']] ?? $row['module'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($row['record_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars($row['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small" style="min-width: 260px; max-width: 520px; white-space: pre-wrap;"><?= htmlspecialchars($row['details'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Không có nhật ký phù hợp với bộ lọc.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php $totalPages = max(1, (int) ceil($totalRows / $pageSize)); ?>
        <?php if ($totalPages > 1): ?>
            <nav class="p-3" aria-label="Phân trang nhật ký">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <li class="page-item <?= $pageNumber <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($paginationUrl(max(1, $pageNumber - 1)), ENT_QUOTES, 'UTF-8') ?>">Trước</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Trang <?= $pageNumber ?> / <?= $totalPages ?></span></li>
                    <li class="page-item <?= $pageNumber >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($paginationUrl(min($totalPages, $pageNumber + 1)), ENT_QUOTES, 'UTF-8') ?>">Sau</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
