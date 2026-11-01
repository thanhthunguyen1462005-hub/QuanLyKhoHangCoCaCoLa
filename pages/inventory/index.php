<?php
/**
 * FR-12, FR-13, FR-14: QUẢN LÝ TỒN KHO & CẢNH BÁO MIN/MAX, CẬN HẠN
 * Tra cứu đa tiêu chí: SKU, Barcode, Số Lô, Vị trí Kệ, Nhà Cung Cấp
 */
$pageTitle = 'Tra Cứu Tồn Kho';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('inventory');

$inventoryRows = [];
$warehouses = [];
$pageError = '';
$filterError = '';
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$selectedWarehouse = trim(is_string($_GET['warehouse'] ?? null) ? $_GET['warehouse'] : '');
$selectedProductType = is_string($_GET['product_type'] ?? null) ? $_GET['product_type'] : '';
$selectedAlert = is_string($_GET['alert'] ?? null) ? $_GET['alert'] : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$pageSize = 25;
$totalRows = 0;
$totalPages = 1;
$exportCsv = ($_GET['export'] ?? '') === 'csv';

if (mb_strlen($search, 'UTF-8') > 100) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
    $filterError = 'Từ khóa tìm kiếm đã được giới hạn tối đa 100 ký tự.';
}
if (!in_array($selectedProductType, ['', 'RAW', 'FINISHED'], true)) {
    $filterError = 'Loại hàng hóa không hợp lệ.';
    $selectedProductType = '';
}
if (!in_array($selectedAlert, ['', 'LOW', 'EXPIRING', 'EXPIRED', 'HIGH'], true)) {
    $filterError = 'Trạng thái cảnh báo không hợp lệ.';
    $selectedAlert = '';
}

$pdo = Database::getConnection();
if ($pdo === null) {
    $pageError = 'Không thể kết nối cơ sở dữ liệu. Tra cứu tồn kho cần MySQL đang hoạt động.';
} else {
    try {
        $warehouses = $pdo->query('SELECT code, name FROM warehouses ORDER BY code')->fetchAll();
        $warehouseCodes = array_column($warehouses, 'code');
        if ($selectedWarehouse !== '' && !in_array($selectedWarehouse, $warehouseCodes, true)) {
            $filterError = 'Kho được chọn không tồn tại.';
        }

        $fromSql = '
            FROM inventory i
            INNER JOIN products p ON p.id = i.product_id
            INNER JOIN categories c ON c.id = p.category_id
            INNER JOIN warehouses w ON w.id = i.warehouse_id
            INNER JOIN warehouse_locations wl ON wl.id = i.location_id
            INNER JOIN product_lots l ON l.id = i.lot_id
            LEFT JOIN suppliers s ON s.id = l.supplier_id
            LEFT JOIN (
                SELECT product_id, SUM(quantity - reserved_quantity) AS available_quantity
                FROM inventory
                GROUP BY product_id
            ) product_totals ON product_totals.product_id = p.id';
        $conditions = [];
        $parameters = [];

        if ($selectedWarehouse !== '') {
            $conditions[] = 'w.code = :warehouse';
            $parameters['warehouse'] = $selectedWarehouse;
        }
        if ($selectedProductType === 'RAW') {
            $conditions[] = "p.type = 'RAW_MATERIAL'";
        } elseif ($selectedProductType === 'FINISHED') {
            $conditions[] = "p.type = 'FINISHED_GOODS'";
        }
        if ($search !== '') {
            $conditions[] = "(p.sku LIKE :search_sku ESCAPE '!'
                OR p.barcode LIKE :search_barcode ESCAPE '!'
                OR p.name LIKE :search_name ESCAPE '!'
                OR c.name LIKE :search_category ESCAPE '!'
                OR w.code LIKE :search_warehouse_code ESCAPE '!'
                OR w.name LIKE :search_warehouse_name ESCAPE '!'
                OR wl.location_code LIKE :search_location ESCAPE '!'
                OR l.lot_number LIKE :search_lot ESCAPE '!'
                OR s.name LIKE :search_supplier ESCAPE '!')";
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $likeSearch = '%' . $escapedSearch . '%';
            foreach (['sku', 'barcode', 'name', 'category', 'warehouse_code', 'warehouse_name', 'location', 'lot', 'supplier'] as $field) {
                $parameters['search_' . $field] = $likeSearch;
            }
        }
        switch ($selectedAlert) {
            case 'LOW':
                $conditions[] = 'COALESCE(product_totals.available_quantity, 0) < p.min_stock';
                break;
            case 'HIGH':
                $conditions[] = 'COALESCE(product_totals.available_quantity, 0) > p.max_stock';
                break;
            case 'EXPIRING':
                $conditions[] = 'i.quantity > 0 AND l.expiry_date >= CURDATE() AND l.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)';
                break;
            case 'EXPIRED':
                $conditions[] = 'i.quantity > 0 AND l.expiry_date < CURDATE()';
                break;
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $selectSql = '
            SELECT p.type, p.sku, p.barcode, p.name, c.name AS category,
                   w.code AS warehouse_code, w.name AS warehouse_name,
                   wl.location_code, l.lot_number, l.mfg_date, l.expiry_date,
                   s.name AS supplier_name, i.quantity, i.reserved_quantity,
                   p.min_stock, p.max_stock, p.unit,
                   COALESCE(product_totals.available_quantity, 0) AS product_available_quantity'
            . $fromSql . $whereSql;

        $countQuery = $pdo->prepare('SELECT COUNT(*) ' . $fromSql . $whereSql);
        $countQuery->execute($parameters);
        $totalRows = (int) $countQuery->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);

        $csvSafe = static function ($value): string {
            $value = (string) $value;
            return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
        };
        if ($exportCsv) {
            $csvQuery = $pdo->prepare($selectSql . ' ORDER BY p.sku, l.expiry_date, w.code, wl.location_code');
            $csvQuery->execute($parameters);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="tra-cuu-ton-kho-' . date('Ymd-His') . '.csv"');
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Không thể tạo nội dung CSV.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['SKU', 'Barcode', 'Tên hàng', 'Danh mục', 'Kho', 'Vị trí', 'Số lô', 'Ngày sản xuất', 'Hạn sử dụng', 'Nhà cung cấp', 'Tồn tại vị trí', 'Đã giữ', 'Khả dụng SKU', 'Min', 'Max', 'Đơn vị']);
            while ($row = $csvQuery->fetch()) {
                fputcsv($output, [
                    $csvSafe($row['sku']),
                    $csvSafe($row['barcode']),
                    $csvSafe($row['name']),
                    $csvSafe($row['category']),
                    $csvSafe($row['warehouse_code'] . ' - ' . $row['warehouse_name']),
                    $csvSafe($row['location_code']),
                    $csvSafe($row['lot_number']),
                    $row['mfg_date'],
                    $row['expiry_date'],
                    $csvSafe($row['supplier_name'] ?? 'Sản xuất nội bộ / chưa xác định NCC'),
                    $row['quantity'],
                    $row['reserved_quantity'],
                    $row['product_available_quantity'],
                    $row['min_stock'],
                    $row['max_stock'],
                    $csvSafe($row['unit']),
                ]);
            }
            fclose($output);
            exit;
        }

        $inventoryQuery = $pdo->prepare(
            $selectSql . ' ORDER BY p.sku, l.expiry_date, w.code, wl.location_code LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $inventoryQuery->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $inventoryQuery->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $inventoryQuery->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $inventoryQuery->execute();
        $inventoryRows = $inventoryQuery->fetchAll();
    } catch (Throwable $exception) {
        error_log('Inventory search query failed: ' . $exception->getMessage());
        $pageError = 'Không thể tải tồn kho từ database.';
        $warehouses = [];
        $inventoryRows = [];
    }
}

$filterParams = [];
if ($search !== '') $filterParams['q'] = $search;
if ($selectedWarehouse !== '') $filterParams['warehouse'] = $selectedWarehouse;
if ($selectedProductType !== '') $filterParams['product_type'] = $selectedProductType;
if ($selectedAlert !== '') $filterParams['alert'] = $selectedAlert;
$exportUrl = BASE_URL . '/pages/inventory/?' . http_build_query($filterParams + ['export' => 'csv']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-cubes text-primary me-2"></i>Tra Cứu Tồn Kho & Cảnh Báo (FR-12, FR-13, FR-14)
            </h4>
            <div class="text-muted small">
                Tra cứu theo mã hàng, lô, vị trí và nhà cung cấp; tồn và cảnh báo được lấy trực tiếp từ database.
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (canAccessPage('audits')): ?>
                <a href="<?= BASE_URL ?>/pages/audits/" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-clipboard-check me-1"></i> Phê duyệt biên bản kiểm kê (FR-15)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="inventorySearch" class="form-label small fw-bold">Từ khóa tìm kiếm</label>
                    <input type="search" class="form-control form-control-sm" id="inventorySearch" name="q"
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" maxlength="100"
                           placeholder="SKU, tên, barcode, lô, vị trí, NCC...">
                </div>
                <div class="col-md-3">
                    <label for="warehouseFilter" class="form-label small fw-bold">Kho lưu trữ (FR-02)</label>
                    <select class="form-select form-select-sm" id="warehouseFilter" name="warehouse">
                        <option value="">-- Tất cả kho --</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= htmlspecialchars($w['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedWarehouse === $w['code'] ? 'selected' : '' ?>><?= htmlspecialchars($w['code'] . ' - ' . $w['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="productTypeFilter" class="form-label small fw-bold">Phân loại hàng hóa</label>
                    <select class="form-select form-select-sm" id="productTypeFilter" name="product_type">
                        <option value="">-- Tất cả chủng loại --</option>
                        <option value="RAW" <?= $selectedProductType === 'RAW' ? 'selected' : '' ?>>Nguyên vật liệu (Raw Materials)</option>
                        <option value="FINISHED" <?= $selectedProductType === 'FINISHED' ? 'selected' : '' ?>>Thành phẩm (Finished Goods)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="inventoryAlertFilter" class="form-label small fw-bold">Bộ lọc cảnh báo (FR-13)</label>
                    <select class="form-select form-select-sm" id="inventoryAlertFilter" name="alert">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="LOW" <?= $selectedAlert === 'LOW' ? 'selected' : '' ?>>Cảnh báo: Dưới mức tồn Min</option>
                        <option value="EXPIRING" <?= $selectedAlert === 'EXPIRING' ? 'selected' : '' ?>>Cảnh báo: Cận hạn sử dụng (≤ 60 ngày)</option>
                        <option value="EXPIRED" <?= $selectedAlert === 'EXPIRED' ? 'selected' : '' ?>>Cảnh báo: Đã quá hạn sử dụng</option>
                        <option value="HIGH" <?= $selectedAlert === 'HIGH' ? 'selected' : '' ?>>Cảnh báo: Vượt mức tồn Max</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-3">
                    <a href="<?= BASE_URL ?>/pages/inventory/" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Tra cứu</button>
                </div>
            </div>
        </div>
    </form>

    <!-- BẢNG DỮ LIỆU TỒN KHO CHI TIẾT -->
    <div class="custom-table shadow-sm">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <div>
                <h6 class="fw-bold mb-1">Danh sách tồn kho theo vị trí và số lô</h6>
                <span class="small text-muted">Hiển thị <?= number_format(count($inventoryRows)) ?> / <?= number_format($totalRows) ?> dòng</span>
            </div>
            <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i>Xuất CSV
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã Hàng / Barcode</th>
                        <th>Tên Hàng Hóa</th>
                        <th>Kho & Vị Trí Kệ</th>
                        <th>Số Lô & HSD (FEFO)</th>
                        <th>Nhà Cung Cấp</th>
                        <th class="text-center">Tồn / Định Mức</th>
                        <th>Cảnh Báo</th>
                        <th class="text-end">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryRows as $item):
                        $productType = $item['type'] === 'FINISHED_GOODS' ? 'FINISHED' : 'RAW';
                        $warehouseLabel = $item['warehouse_code'] . ' (' . $item['warehouse_name'] . ')';
                        $supplierLabel = $item['supplier_name'] ?? 'Sản xuất nội bộ / chưa xác định NCC';
                        $isLow = (int) $item['product_available_quantity'] < (int) $item['min_stock'];
                        $isHigh = (int) $item['product_available_quantity'] > (int) $item['max_stock'];
                        $expiryDate = new DateTimeImmutable($item['expiry_date']);
                        $daysToExpiry = (int) (new DateTimeImmutable('today'))->diff($expiryDate)->format('%r%a');
                        $isExpired = $daysToExpiry < 0 && (int) $item['quantity'] > 0;
                        $isExpiring = $daysToExpiry >= 0 && $daysToExpiry <= 60 && (int) $item['quantity'] > 0;
                        $alertCodes = [];
                        if ($isLow) $alertCodes[] = 'LOW';
                        if ($isHigh) $alertCodes[] = 'HIGH';
                        if ($isExpiring) $alertCodes[] = 'EXPIRING';
                        if ($isExpired) $alertCodes[] = 'EXPIRED';
                    ?>
                    <tr>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div class="text-muted small"><i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($item['barcode'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= htmlspecialchars($warehouseLabel, ENT_QUOTES, 'UTF-8') ?></div>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i><code><?= htmlspecialchars($item['location_code'], ENT_QUOTES, 'UTF-8') ?></code>
                            </span>
                        </td>
                        <td>
                            <div class="badge bg-dark"><?= htmlspecialchars($item['lot_number'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted mt-1">HSD: <strong><?= htmlspecialchars(date('d/m/Y', strtotime($item['expiry_date'])), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        </td>
                        <td>
                            <span class="small"><?= htmlspecialchars($supplierLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="text-center">
                            <span class="fs-6 fw-bold <?= $isLow ? 'text-danger' : 'text-primary' ?>">
                                <?= number_format((int) $item['quantity']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="text-muted" style="font-size: 0.72rem;">
                                Khả dụng SKU: <?= number_format((int) $item['product_available_quantity']) ?>
                                · Min: <?= number_format((int) $item['min_stock']) ?> | Max: <?= number_format((int) $item['max_stock']) ?>
                                <?php if ((int) $item['reserved_quantity'] > 0): ?>
                                    <div>Đã giữ tại vị trí: <?= number_format((int) $item['reserved_quantity']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($isLow): ?>
                                <span class="badge bg-danger mb-1 d-block">Dưới mức Min</span>
                            <?php endif; ?>
                            <?php if ($isHigh): ?>
                                <span class="badge bg-danger mb-1 d-block">Vượt mức Max</span>
                            <?php endif; ?>
                            <?php if ($isExpiring): ?>
                                <span class="badge bg-warning text-dark mb-1 d-block">Cận hạn (<?= $daysToExpiry ?> ngày)</span>
                            <?php endif; ?>
                            <?php if ($isExpired): ?>
                                <span class="badge bg-danger mb-1 d-block">Đã quá hạn (<?= abs($daysToExpiry) ?> ngày)</span>
                            <?php endif; ?>
                            <?php if (!$alertCodes): ?>
                                <span class="badge bg-success-subtle text-success">An toàn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php $issuePage = $productType === 'FINISHED' ? 'issues_finished' : 'issues_raw'; ?>
                                <?php if (canAccessPage($issuePage)): ?>
                                    <a href="<?= BASE_URL ?>/pages/issues/?type=<?= $productType === 'FINISHED' ? 'finished' : 'raw' ?>&auto_lot=<?= rawurlencode($item['lot_number']) ?>" class="btn btn-light border" title="Xuất kho">
                                        <i class="fa-solid fa-share-from-square text-warning"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($inventoryRows === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><?= $pageError === '' ? 'Không tìm thấy tồn kho phù hợp với bộ lọc.' : 'Không thể tải dữ liệu tồn kho.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Phân trang tồn kho">
            <ul class="pagination pagination-sm justify-content-end">
                <?php
                $pageLink = static function (int $targetPage) use ($filterParams): string {
                    return BASE_URL . '/pages/inventory/?' . http_build_query($filterParams + ['page' => $targetPage]);
                };
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($pageLink(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Trang trước">Trước</a>
                </li>
                <li class="page-item disabled"><span class="page-link">Trang <?= number_format($page) ?> / <?= number_format($totalPages) ?></span></li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= htmlspecialchars($pageLink(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Trang sau">Sau</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
