<?php
/**
 * FR-02: QUẢN LÝ DANH MỤC 11 KHO NHÀ MÁY
 * (5 Kho Nguyên liệu & Bao bì, 5 Kho Nước ngọt thành phẩm, 1 Kho Cách ly)
 */
$pageTitle = 'Quản Lý Hệ Thống 11 Kho';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('warehouses');

$warehouses = WAREHOUSES_DEF;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Quản Lý Hệ Thống 11 Kho Nhà Máy (FR-02)
            </h4>
            <div class="text-muted small">
                Quản lý nguyên liệu pha chế, bao bì, nước ngọt thành phẩm và hàng cách ly.
            </div>
        </div>
        <div>
            <button class="btn btn-primary btn-sm shadow-sm" onclick="showToast('Thêm kho mới', 'Chức năng mở rộng theo NFR-09', 'info')">
                <i class="fa-solid fa-plus me-1"></i> Thêm Kho Mới (NFR-09)
            </button>
        </div>
    </div>

    <!-- Lọc danh sách theo nhóm kho -->
    <ul class="nav nav-pills mb-3" id="warehouseTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" type="button" data-warehouse-filter="all" aria-pressed="true">Tất cả (11 Kho)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-warehouse-filter="RAW_MATERIAL" aria-pressed="false">5 Kho Nguyên liệu & Bao bì (KNL)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-warehouse-filter="FINISHED_GOODS" aria-pressed="false">5 Kho Nước ngọt thành phẩm (KTP)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-warehouse-filter="DEFECTIVE_RETURN" aria-pressed="false">Kho Cách ly (KHL)</button>
        </li>
    </ul>

    <div class="row g-3" id="warehouseGrid">
        <?php foreach ($warehouses as $wh): 
            $typeBadge = $wh['type'] === 'RAW_MATERIAL' ? 'bg-primary' : ($wh['type'] === 'FINISHED_GOODS' ? 'bg-success' : 'bg-danger');
            $typeText = $wh['type'] === 'RAW_MATERIAL' ? 'Nguyên liệu / Bao bì' : ($wh['type'] === 'FINISHED_GOODS' ? 'Nước ngọt thành phẩm' : 'Hàng cách ly / Trả về');
        ?>
        <div class="col-md-6 col-xl-4 warehouse-item" data-warehouse-type="<?= $wh['type'] ?>">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-dark fw-bold" style="font-size: 0.8rem;"><?= $wh['code'] ?></span>
                        <span class="badge <?= $typeBadge ?>"><?= $typeText ?></span>
                    </div>
                    <h5 class="card-title fw-bold text-dark mt-2 mb-1" style="font-size: 1rem;"><?= $wh['name'] ?></h5>
                    <p class="text-muted small mb-3">Vị trí: <?= htmlspecialchars($wh['location']) ?></p>
                    
                    <div class="bg-light p-2 rounded-2 mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Sức chứa thiết kế:</span>
                            <span class="fw-bold text-dark"><?= number_format($wh['capacity_m3']) ?> m³</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Thủ kho phụ trách:</span>
                            <span class="fw-semibold text-primary">Nhân viên kho</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Trạng thái:</span>
                            <span class="badge bg-success-subtle text-success">Đang vận hành (mẫu)</span>
                        </div>
                    </div>

                    <div class="d-flex">
                        <a href="<?= BASE_URL ?>/pages/inventory/?warehouse=<?= rawurlencode($wh['code']) ?>" class="btn btn-light btn-sm border w-100">
                            <i class="fa-solid fa-boxes-stacked"></i> Tồn kho
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.querySelectorAll('[data-warehouse-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
        const selectedType = button.dataset.warehouseFilter;
        document.querySelectorAll('[data-warehouse-filter]').forEach(function (tab) {
            const isActive = tab === button;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-pressed', String(isActive));
        });
        document.querySelectorAll('.warehouse-item').forEach(function (item) {
            item.classList.toggle('d-none', selectedType !== 'all' && item.dataset.warehouseType !== selectedType);
        });
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
