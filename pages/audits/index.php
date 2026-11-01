<?php
/**
 * FR-15: QUẢN LÝ KIỂM KÊ & CÂN BẰNG TỒN KHO
 * Ban kiểm kê & Quản lý kho kiểm tra -> Ban Giám đốc phê duyệt biên bản mới cân bằng
 */
$pageTitle = 'Quản Lý Kiểm Kê & Cân Bằng Kho (FR-15)';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('audits');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-clipboard-check text-warning me-2"></i>Kiểm Kê & Cân Bằng Tồn Kho (FR-15)
            </h4>
            <div class="text-muted small">
                Quy trình nghiêm ngặt: Kiểm đếm thực tế &rarr; Quản lý kho xác minh chênh lệch &rarr; <strong>Ban Giám đốc phê duyệt</strong> &rarr; Hệ thống mới cân bằng tồn.
            </div>
        </div>
        <div>
            <button class="btn btn-primary btn-sm shadow-sm" onclick="showToast('Tạo đợt kiểm kê', 'Mở phiếu kiểm kê mới cho phân kho được chọn...', 'info')">
                <i class="fa-solid fa-plus me-1"></i> Mở Đợt Kiểm Kê Mới
            </button>
        </div>
    </div>

    <!-- BIÊN BẢN KIỂM KÊ ĐANG CHỜ PHÊ DUYỆT CÂN BẰNG -->
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge bg-warning text-dark fw-bold mb-1">ĐỢT KIỂM KÊ: KK-2026-11-01</span>
                    <h5 class="fw-bold text-dark mb-0">Biên Bản Kiểm Kê Kho Đường & Siro (KNL-01) - Tháng 11/2026</h5>
                    <div class="small text-muted">Trưởng ban kiểm kê: <strong>Nguyễn Nhân Viên (Thủ kho)</strong> &bull; Xác minh: <strong>Lê Quản Lý Kho</strong></div>
                </div>
                <div>
                    <?php if (hasRole(ROLE_DIRECTOR)): ?>
                        <button class="btn btn-success btn-sm fw-bold shadow-sm" onclick="approveAudit()">
                            <i class="fa-solid fa-signature me-1"></i> Ban Giám Đốc Phê Duyệt Cân Bằng (FR-15)
                        </button>
                    <?php else: ?>
                        <div class="badge bg-light text-secondary border p-2">
                            <i class="fa-solid fa-lock me-1"></i> Cần quyền <strong>Ban Giám Đốc</strong> để duyệt cân bằng tồn
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bảng so lệch sổ sách vs thực tế -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã Hàng (SKU)</th>
                            <th>Tên Hàng Hóa</th>
                            <th>Vị Trí Ô/Kệ</th>
                            <th>Số Lô</th>
                            <th class="text-center">Sổ Sách Hệ Thống</th>
                            <th class="text-center">Thực Tế Kiểm Đếm</th>
                            <th class="text-center">Chênh Lệch</th>
                            <th>Giải Trình Nguyên Nhân</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>NVL-SIRO-01</strong></td>
                            <td>Đường tinh luyện sản xuất siro</td>
                            <td><code>KNL01-A-R01-S01-B01</code></td>
                            <td><span class="badge bg-secondary">LOT-SIRO-2026-110</span></td>
                            <td class="text-center fw-bold">422 Kg</td>
                            <td class="text-center fw-bold text-primary">420 Kg</td>
                            <td class="text-center fw-bold text-danger">-2 Kg</td>
                            <td class="small text-muted">Chênh lệch cân đối chiếu khi nhập nguyên liệu pha chế</td>
                        </tr>
                        <tr>
                            <td><strong>NVL-CO2-01</strong></td>
                            <td>CO₂ tinh khiết cấp thực phẩm</td>
                            <td><code>KNL03-A-R01-B03</code></td>
                            <td><span class="badge bg-secondary">LOT-CO2-2026-044</span></td>
                            <td class="text-center fw-bold">36 Bình</td>
                            <td class="text-center fw-bold text-primary">36 Bình</td>
                            <td class="text-center fw-bold text-success">0</td>
                            <td class="small text-muted">Khớp số lượng; cần ưu tiên xuất theo FEFO</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function approveAudit() {
    showToast("Bản minh họa", "Chức năng hiện chưa phê duyệt biên bản, điều chỉnh tồn hoặc ghi Audit Log vào cơ sở dữ liệu.", "info");
}
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
