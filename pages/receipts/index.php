<?php
/**
 * FR-06 & FR-08: QUẢN LÝ NHẬP KHO NGUYÊN LIỆU & THÀNH PHẨM
 * Tự động điền dữ liệu từ PO / Yêu cầu xưởng & Kiểm định QA/QC (NFR-01)
 */
$type = ($_GET['type'] ?? 'raw') === 'finished' ? 'finished' : 'raw';
$isRaw = $type === 'raw';
$pageTitle = $isRaw ? 'Quản Lý Nhập Nguyên Liệu (FR-06)' : 'Quản Lý Nhập Thành Phẩm (FR-08)';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess($isRaw ? 'receipts_raw' : 'receipts_finished');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>
                <?= $pageTitle ?>
            </h4>
            <div class="text-muted small">
                <?= $isRaw
                    ? 'Căn cứ Đơn mua hàng (PO) và Biên bản QC kiểm định nguyên liệu đạt chuẩn.'
                    : 'Căn cứ Phiếu yêu cầu từ Xưởng sản xuất sau khi QA/QC xác nhận đạt chuẩn.' ?>
            </div>
        </div>
        <div>
            <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal"
                data-bs-target="#createReceiptModal">
                <i class="fa-solid fa-plus me-1"></i> Lập Phiếu Nhập Kho Mới
            </button>
        </div>
    </div>

    <!-- Bảng danh sách phiếu nhập -->
    <div class="custom-table shadow-sm mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
            <h6 class="fw-bold mb-0">Danh Sách Phiếu Nhập Gần Đây</h6>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" placeholder="Tìm theo mã phiếu, PO...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Mã Phiếu</th>
                        <th>Chứng Từ Căn Cứ</th>
                        <th>Biên Bản QA/QC</th>
                        <th>Kho Đích Đến</th>
                        <th>Người Lập</th>
                        <th>Ngày Nhập</th>
                        <th>Trạng Thái</th>
                        <th class="text-end">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong class="text-primary">
                                <?= $isRaw ? 'PN-NVL-2026-1101' : 'PN-TP-2026-1101' ?>
                            </strong></td>
                        <td><span class="badge bg-secondary">
                                <?= $isRaw ? 'PO-2026-11-001' : 'LSX-2026-109' ?>
                            </span> (
                            <?= $isRaw ? 'Đường tinh luyện' : 'Nước ngọt Coca-Cola' ?>)
                        </td>
                        <td><span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>QC-2026-11-01
                                Đạt</span></td>
                        <td>
                            <?= $isRaw ? 'KNL-01 (Kho Đường & siro)' : 'KTP-01 (Kho Nước ngọt chai PET)' ?>
                        </td>
                        <td>Nguyễn Nhân Viên (Thủ kho)</td>
                        <td>01/11/2026 10:15</td>
                        <td><span class="badge bg-success">Đã nhập & ghi nhận tồn</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light border"
                                onclick="showToast('In phiếu', 'Đang kết xuất phiếu nhập in PDF...', 'info')"><i
                                    class="fa-solid fa-print"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong class="text-primary">
                                <?= $isRaw ? 'PN-NVL-2026-1102' : 'PN-TP-2026-1102' ?>
                            </strong></td>
                        <td><span class="badge bg-secondary">
                                <?= $isRaw ? 'PO-2026-11-002' : 'LSX-2026-110' ?>
                            </span> (
                            <?= $isRaw ? 'Nước cốt cô đặc' : 'Nước ngọt Coca-Cola' ?>)
                        </td>
                        <td><span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Chờ QA/QC
                                kiểm định</span></td>
                        <td>
                            <?= $isRaw ? 'KNL-02 (Kho Nước cốt & hương liệu)' : 'KTP-04 (Kho Thành phẩm chờ kiểm định)' ?>
                        </td>
                        <td>Nguyễn Nhân Viên (Thủ kho)</td>
                        <td>01/11/2026 11:30</td>
                        <td><span class="badge bg-warning text-dark">Chờ QC / Quản lý duyệt</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" disabled>Chờ QC đạt</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Tạo Phiếu Nhập Tự Động Điền (NFR-01) -->
<div class="modal fade" id="createReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-file-circle-plus text-primary me-2"></i>Lập Phiếu Nhập Kho Tự Động Điền
                    (NFR-01)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <strong>Cơ chế tự động:</strong> Chọn
                    <?= $isRaw ? 'PO đã được kiểm định QA/QC đạt' : 'lệnh sản xuất đã được QA/QC xác nhận đạt' ?> để
                    điền mặt hàng, số lượng và
                    <?= $isRaw ? 'nhà cung cấp' : 'xưởng sản xuất' ?>.
                </div>
                <form id="receiptForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">1. Chọn
                                <?= $isRaw ? 'PO nguyên liệu' : 'lệnh sản xuất đã hoàn tất' ?> *
                            </label>
                            <select class="form-select form-select-sm" id="poSelect" onchange="autoFillPOData()">
                                <option value="">-- Chọn chứng từ có sẵn --</option>
                                <?php if ($isRaw): ?>
                                    <option value="PO-001" data-supplier="Nhà cung cấp Đường tinh luyện"
                                        data-sku="NVL-SIRO-01" data-name="Đường tinh luyện" data-qty="2000">PO-2026-11-001
                                        (2.000 Kg đường tinh luyện - Đã QC đạt)</option>
                                    <option value="PO-002" data-supplier="Nhà cung cấp nguyên liệu đồ uống"
                                        data-sku="NVL-COCA-01" data-name="Nước cốt Coca-Cola" data-qty="500">PO-2026-11-002
                                        (500 Kg nước cốt - Đã QC đạt)</option>
                                <?php else: ?>
                                    <option value="LSX-109" data-supplier="Xưởng sản xuất Coca-Cola" data-sku="TP-COCA-500"
                                        data-name="Coca-Cola Original 500 ml" data-qty="680">LSX-2026-109 (680 thùng
                                        Coca-Cola 500 ml - QC đạt)</option>
                                    <option value="LSX-110" data-supplier="Xưởng sản xuất Coca-Cola" data-sku="TP-COCA-500"
                                        data-name="Coca-Cola Original 500 ml" data-qty="420">LSX-2026-110 (420 thùng
                                        Coca-Cola 500 ml - QC đạt)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">2. Biên bản kiểm định QA/QC liên kết *</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="qcRef"
                                value="QC-PASSED-AUTO" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">3.
                                <?= $isRaw ? 'Nhà cung cấp' : 'Xưởng sản xuất' ?>
                            </label>
                            <input type="text" class="form-control form-control-sm bg-light" id="supplierName"
                                placeholder="Tự động điền..." readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">4. Chỉ định Kho đích đến (FR-02) *</label>
                            <select class="form-select form-select-sm" required>
                                <?php if ($isRaw): ?>
                                    <option value="1">KNL-01 - Kho Đường tinh luyện & siro nền</option>
                                    <option value="2">KNL-02 - Kho Nước cốt & hương liệu đồ uống</option>
                                    <option value="3">KNL-03 - Kho CO₂ thực phẩm & phụ gia</option>
                                    <option value="4">KNL-04 - Kho Chai PET, nắp chai & lon nhôm</option>
                                    <option value="5">KNL-05 - Kho Nhãn chai, màng co & thùng carton</option>
                                <?php else: ?>
                                    <option value="6">KTP-01 - Kho Nước ngọt chai PET</option>
                                    <option value="7">KTP-02 - Kho Nước ngọt lon nhôm</option>
                                    <option value="8">KTP-03 - Kho Thành phẩm chờ xuất phân phối</option>
                                    <option value="9">KTP-04 - Kho Thành phẩm chờ kiểm định</option>
                                    <option value="10">KTP-05 - Kho Thành phẩm xuất khẩu</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <!-- Chi tiết Lô & Hạn sử dụng (FR-03) -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Số Lô sản xuất (Lot Number) *</label>
                            <input type="text" class="form-control form-control-sm" placeholder="VD: LOT-2026-TH01"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Ngày sản xuất (MFG)</label>
                            <input type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Hạn sử dụng (Expiry Date - FEFO) *</label>
                            <input type="date" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Vị trí Ô/Kệ chỉ định (FR-04) *</label>
                            <select class="form-select form-select-sm">
                                <option>KNL01-A-R01-S01-B01 (Dãy A, Kệ 1, Tầng 1, Ô 1)</option>
                                <option>KNL01-A-R01-S01-B02 (Dãy A, Kệ 1, Tầng 1, Ô 2)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Số lượng nhập thực tế *</label>
                            <input type="number" class="form-control form-control-sm" id="importQty" value="100" min="1"
                                required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveReceipt()">
                    <i class="fa-solid fa-check me-1"></i> Mô phỏng gửi Quản lý duyệt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function autoFillPOData() {
        const sel = document.getElementById('poSelect');
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.supplier) {
            document.getElementById('supplierName').value = opt.dataset.supplier;
            document.getElementById('importQty').value = opt.dataset.qty;
            showToast("Tự động điền dữ liệu", `Đã trích xuất thông tin từ ${sel.value}`, "info");
        }
    }

    function saveReceipt() {
        showToast("Bản minh họa", "Chức năng hiện chưa lưu phiếu hoặc cập nhật tồn kho vào cơ sở dữ liệu.", "info");
        const modalEl = document.getElementById('createReceiptModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>