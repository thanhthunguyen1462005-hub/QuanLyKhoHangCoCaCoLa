<?php
/**
 * NFR-10 & FR-14: QUÉT MÃ BARCODE & QR CODE
 * Tương thích đầu đọc súng bắn mã vạch USB và Camera di động
 */
$pageTitle = 'Quét Mã Barcode / QR Code (NFR-10)';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('scanner');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-qrcode text-info me-2"></i>Trình Quét Mã Barcode / QR Code (NFR-10)
            </h4>
            <div class="text-muted small">
                Hỗ trợ súng bắn mã vạch công nghiệp (USB HID) và Camera điện thoại/máy tính bảng để kiểm tra nhanh vị trí, số lô, hạn dùng.
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Cột trái: Đầu đọc Barcode Gun -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-barcode text-primary me-2"></i>Cổng Nhận Tín Hiệu Súng Bắn Mã Vạch (USB)
                    </h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small">
                        Đặt con trỏ vào ô bên dưới hoặc bấm cò súng quét mã vạch bất kỳ lúc nào trên màn hình:
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Dữ liệu mã vạch nhận được:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" id="scannerInput" class="form-control barcode-input form-control-lg" placeholder="Chờ tín hiệu từ máy quét..." autofocus>
                            <button class="btn btn-primary" type="button" onclick="processScannedCode()">Tra Cứu</button>
                        </div>
                    </div>

                    <!-- Nút test nhanh cho thành viên nhóm -->
                    <div class="mt-4 pt-3 border-top">
                        <span class="small fw-bold text-muted text-uppercase d-block mb-2">Thử nghiệm nhanh mẫu mã (Click để test):</span>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="simulateScan('893600001101')">
                                <i class="fa-solid fa-barcode me-1"></i> Đường tinh luyện (893600001101)
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="simulateScan('LOT-CONC-2026-044')">
                                <i class="fa-solid fa-qrcode me-1"></i> Lô Nước cốt (LOT-CONC-2026-044)
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="simulateScan('KNL01-A-R01-S01-B01')">
                                <i class="fa-solid fa-location-dot me-1"></i> Kệ nguyên liệu KNL-01
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột phải: Kết quả tra cứu tức thời (Real-time Result) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-circle-check text-success me-2"></i>Thông Tin Hàng Hóa & Vị Trí Lưu Trữ
                    </h6>
                    <span id="scanStatusBadge" class="badge bg-secondary">Chờ quét mã</span>
                </div>
                <div class="card-body p-4" id="scanResultArea">
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-barcode fa-4x mb-3 text-secondary opacity-50"></i>
                        <p>Vui lòng quét Barcode hoặc chọn mã mẫu bên trái để hiển thị thông tin chi tiết.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function simulateScan(code) {
    document.getElementById('scannerInput').value = code;
    processScannedCode();
}

function processScannedCode() {
    const code = document.getElementById('scannerInput').value.trim();
    if (!code) {
        showToast("Lỗi", "Vui lòng nhập hoặc quét mã vạch!", "warning");
        return;
    }

    const resultArea = document.getElementById('scanResultArea');
    const statusBadge = document.getElementById('scanStatusBadge');
    const isSyrup = ['893600001101', 'NVL-SIRO-01', 'LOT-SIRO-2026-110'].includes(code);
    const isConcentrate = ['893600001102', 'NVL-COCA-01', 'LOT-CONC-2026-044'].includes(code);
    const isKnownLocation = code === 'KNL01-A-R01-S01-B01';

    if (!isSyrup && !isConcentrate && !isKnownLocation) {
        statusBadge.className = 'badge bg-danger';
        statusBadge.innerText = 'Không tìm thấy mã';
        resultArea.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>Mã chưa có trong dữ liệu minh họa. Hãy thử SKU, Barcode, số lô hoặc vị trí mẫu.</div>';
        showToast('Không tìm thấy mã', 'Mã này chưa có trong dữ liệu minh họa.', 'warning');
        return;
    }

    statusBadge.className = 'badge bg-success';
    statusBadge.innerText = 'Đã nhận diện: ' + code;

    if (isKnownLocation) {
        resultArea.innerHTML = `
            <div class="border rounded-3 p-3 bg-light mb-3">
                <span class="badge bg-info text-dark mb-1">VỊ TRÍ LƯU TRỮ</span>
                <h5 class="fw-bold text-dark mb-0">KNL-01 - Kho Đường tinh luyện & siro nền</h5>
                <div class="small text-muted">Khu A &bull; Dãy A &bull; Kệ R01 &bull; Tầng S01 &bull; Ô B01</div>
            </div>
            <div class="alert alert-info mb-0">Vị trí mẫu đang lưu lô đường tinh luyện <strong>LOT-SIRO-2026-110</strong>.</div>
        `;
        return;
    }

    if (isSyrup) {
        resultArea.innerHTML = `
            <div class="border rounded-3 p-3 bg-light mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-primary mb-1">NGUYÊN LIỆU</span>
                        <h5 class="fw-bold text-dark mb-0">Đường tinh luyện sản xuất siro</h5>
                        <div class="small text-muted">Mã SKU: <strong>NVL-SIRO-01</strong> &bull; Barcode: <code>893600001101</code></div>
                    </div>
                    <span class="badge bg-success fs-6">Tồn: 420 Kg</span>
                </div>
            </div>
            <div class="list-group list-group-flush border rounded-3">
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Kho lưu trữ:</span>
                    <strong>KNL-01 (Kho Đường tinh luyện & siro nền)</strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Vị trí Ô/Kệ:</span>
                    <span class="badge bg-dark"><i class="fa-solid fa-location-dot me-1 text-danger"></i>KNL01-A-R01-S01-B01</span>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Số Lô sản xuất:</span>
                    <strong>LOT-SIRO-2026-110</strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Hạn sử dụng (FEFO):</span>
                    <strong class="text-warning">20/11/2026 (Còn 19 ngày)</strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Nhà cung cấp:</span>
                    <strong>Nhà cung cấp Đường tinh luyện</strong>
                </div>
            </div>
            <?php if (canAccessPage('issues_raw')): ?>
                <div class="mt-3 d-flex gap-2">
                    <a href="<?= BASE_URL ?>/pages/issues/?type=raw&auto_lot=LOT-SIRO-2026-110" class="btn btn-warning text-dark btn-sm flex-grow-1 fw-bold">
                        <i class="fa-solid fa-share-from-square me-1"></i> Lập Phiếu Xuất Kho Lô Này
                    </a>
                </div>
            <?php endif; ?>
        `;
    } else {
        resultArea.innerHTML = `
            <div class="border rounded-3 p-3 bg-light mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-warning text-dark mb-1">CẬN DATE (FEFO)</span>
                        <h5 class="fw-bold text-dark mb-0">Nước cốt cô đặc hương Coca-Cola</h5>
                        <div class="small text-muted">SKU: <strong>NVL-COCA-01</strong> &bull; Lô: <strong>LOT-CONC-2026-044</strong></div>
                    </div>
                    <span class="badge bg-warning text-dark fs-6">Tồn: 240 Kg</span>
                </div>
            </div>
            <div class="list-group list-group-flush border rounded-3">
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Kho:</span>
                    <strong>KNL-02 (Kho Nước cốt & hương liệu)</strong>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Vị trí:</span>
                    <span class="badge bg-dark">KNL02-A-R01-S01-B01</span>
                </div>
                <div class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Hạn sử dụng:</span>
                    <strong class="text-danger">12/11/2026 (Còn 11 ngày)</strong>
                </div>
            </div>
            <?php if (canAccessPage('issues_raw')): ?>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/pages/issues/?type=raw&auto_lot=LOT-CONC-2026-044" class="btn btn-danger btn-sm w-100 fw-bold">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i> Ưu Tiên Xuất FEFO Ngay
                    </a>
                </div>
            <?php endif; ?>
        `;
    }
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
