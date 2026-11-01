<?php
/**
 * FR-07 & FR-09: QUẢN LÝ XUẤT KHO NGUYÊN LIỆU & THÀNH PHẨM
 * Tích hợp thuật toán gợi ý FEFO/FIFO & Chặn xuất âm (NFR-05)
 */
$type = ($_GET['type'] ?? 'raw') === 'finished' ? 'finished' : 'raw';
$isRaw = $type === 'raw';
$pageTitle = $isRaw ? 'Xuất Nguyên Liệu (Gợi ý FEFO/FIFO - FR-07)' : 'Xuất Thành Phẩm Bán Hàng (FR-09)';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess($isRaw ? 'issues_raw' : 'issues_finished');

$issueLots = $isRaw ? [
    [
        'sku' => 'NVL-COCA-01',
        'name' => 'Nước cốt cô đặc Coca-Cola',
        'lot' => 'LOT-CONC-2026-044',
        'expiry' => '12/11/2026',
        'days_left' => 11,
        'warehouse' => 'KNL-02',
        'location' => 'KNL02-A-R01-S01-B01',
        'quantity' => 240,
        'unit' => 'Kg',
        'priority' => 1,
        'style' => 'table-danger'
    ],
    [
        'sku' => 'NVL-SIRO-01',
        'name' => 'Đường tinh luyện / siro nền',
        'lot' => 'LOT-SIRO-2026-110',
        'expiry' => '20/11/2026',
        'days_left' => 19,
        'warehouse' => 'KNL-01',
        'location' => 'KNL01-A-R01-S01-B01',
        'quantity' => 420,
        'unit' => 'Kg',
        'priority' => 2,
        'style' => 'table-warning'
    ],
    [
        'sku' => 'NVL-SIRO-01',
        'name' => 'Đường tinh luyện / siro nền',
        'lot' => 'LOT-SIRO-2026-111',
        'expiry' => '28/01/2027',
        'days_left' => 88,
        'warehouse' => 'KNL-01',
        'location' => 'KNL01-A-R01-S01-B02',
        'quantity' => 2800,
        'unit' => 'Kg',
        'priority' => 3,
        'style' => ''
    ],
] : [
    [
        'sku' => 'TP-COCA-500',
        'name' => 'Coca-Cola Original 500 ml (thùng 24 chai PET)',
        'lot' => 'LOT-CC-2026-108',
        'expiry' => '01/05/2027',
        'days_left' => 181,
        'warehouse' => 'KTP-01',
        'location' => 'KTP01-B-R05-S01-B01',
        'quantity' => 680,
        'unit' => 'Thùng',
        'priority' => 1,
        'style' => ''
    ],
];
$defaultIssueLot = $issueLots[0];
foreach ($issueLots as $lot) {
    if (isset($_GET['auto_lot']) && hash_equals($lot['lot'], (string)$_GET['auto_lot'])) {
        $defaultIssueLot = $lot;
        break;
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-share-from-square text-warning me-2"></i><?= $pageTitle ?>
            </h4>
            <div class="text-muted small">
                Căn cứ Yêu cầu sản xuất từ Xưởng hoặc Đơn bán hàng &bull; Tự động tính toán Lô xuất ưu tiên theo HSD (FEFO) và Ngày nhập (FIFO).
            </div>
        </div>
        <div>
            <button class="btn btn-warning text-dark btn-sm shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#createIssueModal">
                <i class="fa-solid fa-plus me-1"></i> Lập Lệnh Xuất Kho
            </button>
        </div>
    </div>

    <!-- KHỐI GỢI Ý THUẬT TOÁN FEFO / FIFO -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i>Thuật Toán FEFO: Thứ Tự Lô Đề Xuất Xuất Kho Ngay
            </h6>
            <span class="badge bg-warning text-dark">Cận Hạn Sử Dụng Trước</span>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Thứ Tự Ưu Tiên</th>
                            <th>Mã Hàng (SKU)</th>
                            <th>Số Lô (Lot Number)</th>
                            <th>Hạn Sử Dụng (Expiry)</th>
                            <th>Kho & Vị Trí Ô/Kệ</th>
                            <th>Tồn Khả Dụng</th>
                            <th class="text-center">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issueLots as $lot): ?>
                        <tr class="<?= $lot['style'] ?>">
                            <td class="text-center fw-bold">
                                <span class="badge <?= $lot['priority'] === 1 && $isRaw ? 'bg-danger' : 'bg-secondary' ?>">
                                    ƯU TIÊN <?= $lot['priority'] ?> (<?= $isRaw ? 'FEFO' : 'HSD' ?>)
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($lot['sku']) ?></strong> (<?= htmlspecialchars($lot['name']) ?>)</td>
                            <td><code><?= htmlspecialchars($lot['lot']) ?></code></td>
                            <td><?= htmlspecialchars($lot['expiry']) ?> (Còn <?= $lot['days_left'] ?> ngày)</td>
                            <td><?= htmlspecialchars($lot['warehouse']) ?> &bull; Ô: <?= htmlspecialchars($lot['location']) ?></td>
                            <td><?= number_format($lot['quantity']) ?> <?= htmlspecialchars($lot['unit']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm <?= $lot['priority'] === 1 && $isRaw ? 'btn-danger' : 'btn-outline-secondary' ?>"
                                        onclick="selectLotForIssue('<?= htmlspecialchars($lot['lot'], ENT_QUOTES) ?>', <?= (int)$lot['quantity'] ?>, '<?= htmlspecialchars($lot['warehouse'], ENT_QUOTES) ?>')">
                                    <?= $lot['priority'] === 1 ? 'Ưu tiên xuất' : 'Chọn lô' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BẢNG LỊCH SỬ PHIẾU XUẤT -->
    <div class="custom-table shadow-sm">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <h6 class="fw-bold mb-0">Nhật Ký Phiếu Xuất Kho Gần Đây</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã Phiếu Xuất</th>
                        <th>Căn Cứ (KHSX / Đơn Hàng)</th>
                        <th>Kho Xuất</th>
                        <th>Lô Đã Trừ</th>
                        <th>Số Lượng</th>
                        <th>Trạng Thái</th>
                        <th class="text-end">Chứng Từ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong class="text-warning text-dark">PX-2026-0189</strong></td>
                        <td><?= $isRaw ? 'Phiếu yêu cầu pha chế' : 'Đơn bán hàng' ?> <code><?= $isRaw ? 'YC-PHACHE-042' : 'DH-NPP-2026-01' ?></code></td>
                        <td><?= htmlspecialchars($defaultIssueLot['warehouse']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($defaultIssueLot['lot']) ?></span></td>
                        <td><strong><?= $isRaw ? '100 Kg' : '120 Thùng' ?></strong></td>
                        <td><span class="badge bg-warning text-dark">Chờ Quản lý duyệt xuất</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light border"><i class="fa-solid fa-eye"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Lập Phiếu Xuất Kho -->
<div class="modal fade" id="createIssueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-file-export text-warning me-2"></i>Lập Lệnh Xuất Kho & Kiểm Tra Tồn Khả Dụng (NFR-05)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    <strong>Ràng buộc NFR-05:</strong> Hệ thống chặn tuyệt đối việc lập phiếu xuất vượt quá tồn kho thực tế khả dụng.
                </div>
                <form id="issueForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Chứng từ yêu cầu căn cứ *</label>
                            <input type="text" class="form-control form-control-sm" placeholder="<?= $isRaw ? 'VD: YC-PHACHE-042 hoặc KHSX-2026-110' : 'VD: DH-NPP-2026-01' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kho xuất phát *</label>
                            <select class="form-select form-select-sm" id="sourceWarehouse">
                                <?php foreach (WAREHOUSES_DEF as $warehouse): ?>
                                    <?php if ($isRaw && $warehouse['type'] === 'RAW_MATERIAL' || !$isRaw && $warehouse['type'] === 'FINISHED_GOODS'): ?>
                                    <option value="<?= htmlspecialchars($warehouse['code']) ?>" <?= $defaultIssueLot['warehouse'] === $warehouse['code'] ? 'selected' : '' ?>><?= htmlspecialchars($warehouse['code'] . ' - ' . $warehouse['name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Lô hàng xuất chỉ định (Gợi ý FEFO) *</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="selectedLotNumber" value="<?= htmlspecialchars($defaultIssueLot['lot']) ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tồn khả dụng</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="availableStock" value="<?= (int)$defaultIssueLot['quantity'] ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Số lượng xuất *</label>
                            <input type="number" class="form-control form-control-sm" id="exportQuantity" value="<?= min(10, (int)$defaultIssueLot['quantity']) ?>" min="1" max="<?= (int)$defaultIssueLot['quantity'] ?>" required onchange="checkStockLimit(this)">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-warning text-dark btn-sm fw-bold" onclick="submitIssue()">
                    <i class="fa-solid fa-check me-1"></i> Mô phỏng gửi duyệt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function selectLotForIssue(lot, stock, warehouse) {
    document.getElementById('selectedLotNumber').value = lot;
    document.getElementById('availableStock').value = stock;
    document.getElementById('exportQuantity').max = stock;
    document.getElementById('sourceWarehouse').value = warehouse;
    const modal = new bootstrap.Modal(document.getElementById('createIssueModal'));
    modal.show();
}

function checkStockLimit(input) {
    const max = parseInt(input.max);
    const val = parseInt(input.value);
    if (val > max) {
        showToast("Vi phạm NFR-05", `Không thể xuất vượt quá tồn kho khả dụng (${max})!`, "danger");
        input.value = max;
    }
}

function submitIssue() {
    showToast("Bản minh họa", "Chức năng hiện chưa lưu phiếu hoặc trừ tồn kho trong cơ sở dữ liệu.", "info");
    const modalEl = document.getElementById('createIssueModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
