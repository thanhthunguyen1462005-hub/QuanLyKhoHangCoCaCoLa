<?php
/**
 * FR-12, FR-13, FR-14: QUẢN LÝ TỒN KHO & CẢNH BÁO MIN/MAX, CẬN HẠN
 * Tra cứu đa tiêu chí: SKU, Barcode, Số Lô, Vị trí Kệ, Nhà Cung Cấp
 */
$pageTitle = 'Quản Lý Tồn Kho & Cảnh Báo (FR-12, FR-13)';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('inventory');

$mockInventory = [
    [
        'type' => 'RAW',
        'sku' => 'NVL-SIRO-01',
        'barcode' => '893600001101',
        'name' => 'Đường tinh luyện sản xuất siro',
        'category' => 'Đường và siro nền',
        'warehouse' => 'KNL-01 (Kho Đường & siro)',
        'location' => 'KNL01-A-R01-S01-B01',
        'lot' => 'LOT-SIRO-2026-110',
        'supplier' => 'Nhà cung cấp Đường tinh luyện',
        'mfg_date' => '20/10/2026',
        'expiry_date' => '20/11/2026',
        'quantity' => 420,
        'min_stock' => 500,
        'max_stock' => 10000,
        'unit' => 'Kg',
        'status' => 'LOW_AND_EXPIRING'
    ],
    [
        'type' => 'RAW',
        'sku' => 'NVL-COCA-01',
        'barcode' => '893600001102',
        'name' => 'Nước cốt cô đặc hương Coca-Cola',
        'category' => 'Nước cốt và hương liệu',
        'warehouse' => 'KNL-02 (Kho Nước cốt & hương liệu)',
        'location' => 'KNL02-A-R01-S01-B01',
        'lot' => 'LOT-CONC-2026-044',
        'supplier' => 'Nhà cung cấp nguyên liệu đồ uống',
        'mfg_date' => '01/10/2026',
        'expiry_date' => '12/11/2026',
        'quantity' => 240,
        'min_stock' => 100,
        'max_stock' => 2000,
        'unit' => 'Kg',
        'status' => 'LOW_AND_EXPIRING' // Chạm ngưỡng min và cận date
    ],
    [
        'type' => 'RAW',
        'sku' => 'NVL-CO2-01',
        'barcode' => '893600001103',
        'name' => 'Khí CO₂ tinh khiết cấp thực phẩm',
        'category' => 'CO₂ thực phẩm và phụ gia',
        'warehouse' => 'KNL-03 (Kho CO₂ thực phẩm & phụ gia)',
        'location' => 'KNL03-A-R01-B03',
        'lot' => 'LOT-CO2-2026-044',
        'supplier' => 'Nhà cung cấp nguyên liệu đồ uống',
        'mfg_date' => '10/10/2026',
        'expiry_date' => '10/10/2027',
        'quantity' => 36,
        'min_stock' => 20,
        'max_stock' => 200,
        'unit' => 'Bình',
        'status' => 'SAFE'
    ],
    [
        'type' => 'RAW',
        'sku' => 'BB-PET-01',
        'barcode' => '893600001104',
        'name' => 'Phôi chai PET dùng cho nước giải khát',
        'category' => 'Bao bì chai PET và lon',
        'warehouse' => 'KNL-04 (Kho Chai PET & nắp chai)',
        'location' => 'KNL04-B-R01-S01-B01',
        'lot' => 'LOT-PET-2026-090',
        'supplier' => 'Nhà cung cấp bao bì thực phẩm',
        'mfg_date' => '10/09/2026',
        'expiry_date' => '10/09/2028',
        'quantity' => 18000,
        'min_stock' => 5000,
        'max_stock' => 100000,
        'unit' => 'Cái',
        'status' => 'SAFE'
    ],
    [
        'type' => 'FINISHED',
        'sku' => 'TP-COCA-500',
        'barcode' => '893600001201',
        'name' => 'Coca-Cola Original 500 ml (thùng 24 chai PET)',
        'category' => 'Nước ngọt thành phẩm',
        'warehouse' => 'KTP-01 (Kho Nước ngọt chai PET)',
        'location' => 'KTP01-B-R05-S01-B01',
        'lot' => 'LOT-CC-2026-108',
        'supplier' => 'Sản xuất nội bộ',
        'mfg_date' => '15/10/2026',
        'expiry_date' => '01/05/2027',
        'quantity' => 680,
        'min_stock' => 100,
        'max_stock' => 3000,
        'unit' => 'Thùng',
        'status' => 'SAFE'
    ]
];

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
                Tra cứu tức thời theo Số Lô, Vị trí Kệ, Hạn Dùng & Cảnh báo Chạm Min / Vượt Max / Cận Date.
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="showToast('Xuất báo cáo', 'Đang kết xuất file Excel danh sách tồn kho...', 'info')">
                <i class="fa-solid fa-file-excel text-success me-1"></i> Xuất Excel
            </button>
            <?php if (canAccessPage('audits')): ?>
                <a href="<?= BASE_URL ?>/pages/audits/" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-clipboard-check me-1"></i> Lập Kỳ Kiểm Kê (FR-15)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- BỘ LỌC ĐA TIÊU CHÍ (FR-14) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Từ khóa tìm kiếm</label>
                    <input type="search" class="form-control form-control-sm" id="inventorySearch" placeholder="SKU, tên, barcode, lô, vị trí, NCC...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Kho lưu trữ (FR-02)</label>
                    <select class="form-select form-select-sm" id="warehouseFilter">
                        <option value="">-- Tất cả 11 Kho --</option>
                        <?php foreach (WAREHOUSES_DEF as $w): ?>
                            <option value="<?= htmlspecialchars($w['code']) ?>" <?= ($_GET['warehouse'] ?? '') === $w['code'] ? 'selected' : '' ?>><?= htmlspecialchars($w['code'] . ' - ' . $w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Phân loại hàng hóa</label>
                    <select class="form-select form-select-sm" id="productTypeFilter">
                        <option value="">-- Tất cả chủng loại --</option>
                        <option value="RAW">Nguyên vật liệu (Raw Materials)</option>
                        <option value="FINISHED">Thành phẩm (Finished Goods)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Bộ lọc Cảnh báo (FR-13)</label>
                    <select class="form-select form-select-sm" id="inventoryAlertFilter">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="LOW">Cảnh báo: Dưới mức tồn Min</option>
                        <option value="EXPIRING">Cảnh báo: Cận hạn sử dụng (&lt; 60 ngày)</option>
                        <option value="HIGH">Cảnh báo: Vượt mức tồn Max</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- BẢNG DỮ LIỆU TỒN KHO CHI TIẾT -->
    <div class="custom-table shadow-sm">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <h6 class="fw-bold mb-0">Danh Sách Tồn Kho Phân Cấp Theo Vị Trí & Số Lô</h6>
            <span class="badge bg-primary" id="inventoryResultCount"><?= count($mockInventory) ?> bản ghi</span>
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
                    <?php foreach ($mockInventory as $item):
                        $isLow = $item['quantity'] < $item['min_stock'];
                        $isHigh = $item['quantity'] > $item['max_stock'];
                        $expiryDate = DateTime::createFromFormat('d/m/Y', $item['expiry_date']);
                        $daysToExpiry = $expiryDate ? (int)date_diff(new DateTime('today'), $expiryDate)->format('%r%a') : null;
                        $isExpiring = $daysToExpiry !== null && $daysToExpiry >= 0 && $daysToExpiry <= 60;
                        $alertCodes = [];
                        if ($isLow) $alertCodes[] = 'LOW';
                        if ($isHigh) $alertCodes[] = 'HIGH';
                        if ($isExpiring) $alertCodes[] = 'EXPIRING';
                    ?>
                    <tr class="inventory-row"
                        data-search="<?= htmlspecialchars(strtolower(implode(' ', [$item['sku'], $item['barcode'], $item['name'], $item['category'], $item['warehouse'], $item['location'], $item['lot'], $item['supplier']])), ENT_QUOTES) ?>"
                        data-warehouse="<?= htmlspecialchars(substr($item['warehouse'], 0, 6), ENT_QUOTES) ?>"
                        data-product-type="<?= htmlspecialchars($item['type'], ENT_QUOTES) ?>"
                        data-alerts="<?= htmlspecialchars(implode(',', $alertCodes), ENT_QUOTES) ?>">
                        <td>
                            <strong class="text-dark"><?= $item['sku'] ?></strong>
                            <div class="text-muted small"><i class="fa-solid fa-barcode me-1"></i><?= $item['barcode'] ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= $item['name'] ?></div>
                            <small class="text-muted"><?= $item['category'] ?></small>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= $item['warehouse'] ?></div>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i><code><?= $item['location'] ?></code>
                            </span>
                        </td>
                        <td>
                            <div class="badge bg-dark"><?= $item['lot'] ?></div>
                            <div class="small text-muted mt-1">HSD: <strong><?= $item['expiry_date'] ?></strong></div>
                        </td>
                        <td>
                            <span class="small"><?= $item['supplier'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="fs-6 fw-bold <?= $isLow ? 'text-danger' : 'text-primary' ?>">
                                <?= number_format($item['quantity']) ?> <?= $item['unit'] ?>
                            </span>
                            <div class="text-muted" style="font-size: 0.72rem;">
                                Min: <?= $item['min_stock'] ?> | Max: <?= $item['max_stock'] ?>
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
                            <?php if (!$alertCodes): ?>
                                <span class="badge bg-success-subtle text-success">An toàn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php $issuePage = $item['type'] === 'FINISHED' ? 'issues_finished' : 'issues_raw'; ?>
                                <?php if (canAccessPage($issuePage)): ?>
                                    <a href="<?= BASE_URL ?>/pages/issues/?type=<?= $item['type'] === 'FINISHED' ? 'finished' : 'raw' ?>&auto_lot=<?= rawurlencode($item['lot']) ?>" class="btn btn-light border" title="Xuất kho">
                                        <i class="fa-solid fa-share-from-square text-warning"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="inventoryNoResults" class="d-none">
                        <td colspan="8" class="text-center text-muted py-4">Không tìm thấy mặt hàng phù hợp.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('inventorySearch');
    const warehouse = document.getElementById('warehouseFilter');
    const productType = document.getElementById('productTypeFilter');
    const alertFilter = document.getElementById('inventoryAlertFilter');
    const rows = Array.from(document.querySelectorAll('.inventory-row'));
    const resultCount = document.getElementById('inventoryResultCount');
    const noResults = document.getElementById('inventoryNoResults');

    function filterInventory() {
        const query = search.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matches = row.dataset.search.includes(query)
                && (!warehouse.value || row.dataset.warehouse === warehouse.value)
                && (!productType.value || row.dataset.productType === productType.value)
                && (!alertFilter.value || row.dataset.alerts.split(',').includes(alertFilter.value));
            row.classList.toggle('d-none', !matches);
            if (matches) visibleCount += 1;
        });

        resultCount.textContent = visibleCount + ' bản ghi';
        noResults.classList.toggle('d-none', visibleCount > 0);
    }

    search.addEventListener('input', filterInventory);
    [warehouse, productType, alertFilter].forEach(function (filter) {
        filter.addEventListener('change', filterInventory);
    });
    filterInventory();
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
