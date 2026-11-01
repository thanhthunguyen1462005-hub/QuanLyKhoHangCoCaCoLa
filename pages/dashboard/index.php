<?php
/**
 * TRANG CHỦ / BẢNG ĐIỀU KHIỂN CHÍNH (MAIN DASHBOARD)
 * Trung tâm điều phối hệ thống WMS Nhà máy - Đáp ứng FR-01 -> FR-20
 */

$pageTitle = 'Tổng Quan Coca-Cola WMS';
require_once __DIR__ . '/../../config/constants.php';
requirePageAccess('dashboard');
$currentUser = currentUser();

$roleDashboard = [
    ROLE_ADMIN => ['title' => 'Không gian quản trị hệ thống', 'description' => 'Theo dõi tổng quan vận hành và các phân hệ được cấp quyền quản trị.'],
    ROLE_DIRECTOR => ['title' => 'Tổng quan dành cho Ban Giám đốc', 'description' => 'Theo dõi tình hình kho, cảnh báo và các biên bản kiểm kê cần phê duyệt.'],
    ROLE_MANAGER => ['title' => 'Trung tâm điều phối kho', 'description' => 'Theo dõi nhập, xuất, kiểm kê và tình trạng các kho trong nhà máy.'],
    ROLE_STAFF => ['title' => 'Không gian làm việc của Nhân viên kho', 'description' => 'Tra cứu tồn, quét mã và theo dõi các nghiệp vụ nhập xuất được phân công.'],
    ROLE_QC => ['title' => 'Không gian kiểm soát chất lượng', 'description' => 'Tra cứu lô hàng và theo dõi thông tin nhập kho phục vụ kiểm định QA/QC.'],
    ROLE_PROCUREMENT => ['title' => 'Không gian nghiệp vụ Mua hàng', 'description' => 'Theo dõi tồn kho và thông tin nhập nguyên liệu phục vụ kế hoạch mua hàng.'],
    ROLE_WORKSHOP => ['title' => 'Không gian Xưởng sản xuất', 'description' => 'Theo dõi nguyên liệu cấp cho sản xuất và thông tin nhập thành phẩm.'],
    ROLE_PLANNING => ['title' => 'Không gian Lập kế hoạch', 'description' => 'Theo dõi tồn kho và các lô nguyên liệu phục vụ kế hoạch sản xuất.'],
    ROLE_SALES => ['title' => 'Không gian nghiệp vụ Bán hàng', 'description' => 'Tra cứu tồn thành phẩm và theo dõi thông tin xuất hàng phân phối.'],
];
$dashboardActions = [
    ['page' => 'receipts_raw', 'label' => 'Nhập nguyên liệu (PO)', 'detail' => 'FR-06', 'icon' => 'fa-cart-flatbed', 'color' => 'primary', 'url' => '/pages/receipts/?type=raw'],
    ['page' => 'issues_raw', 'label' => 'Xuất nguyên liệu (FEFO)', 'detail' => 'FR-07', 'icon' => 'fa-truck-ramp-box', 'color' => 'warning', 'url' => '/pages/issues/?type=raw'],
    ['page' => 'receipts_finished', 'label' => 'Nhập thành phẩm', 'detail' => 'FR-08', 'icon' => 'fa-boxes-packing', 'color' => 'info', 'url' => '/pages/receipts/?type=finished'],
    ['page' => 'issues_finished', 'label' => 'Xuất thành phẩm', 'detail' => 'FR-09', 'icon' => 'fa-truck-fast', 'color' => 'success', 'url' => '/pages/issues/?type=finished'],
    ['page' => 'audits', 'label' => 'Kiểm kê kho', 'detail' => 'FR-15', 'icon' => 'fa-clipboard-check', 'color' => 'danger', 'url' => '/pages/audits/'],
    ['page' => 'warehouses', 'label' => 'Danh mục kho', 'detail' => 'FR-02', 'icon' => 'fa-warehouse', 'color' => 'secondary', 'url' => '/pages/warehouses/'],
    ['page' => 'inventory', 'label' => 'Tra cứu tồn kho', 'detail' => 'FR-12, FR-14', 'icon' => 'fa-cubes', 'color' => 'primary', 'url' => '/pages/inventory/'],
    ['page' => 'scanner', 'label' => 'Quét Barcode/QR', 'detail' => 'NFR-10', 'icon' => 'fa-qrcode', 'color' => 'info', 'url' => '/pages/scanner/'],
];
$flashMessage = consumeFlashMessage();
$roleIntro = $roleDashboard[$currentUser['role']] ?? $roleDashboard[ROLE_STAFF];

// Các chỉ số và giao dịch trên Dashboard là dữ liệu mẫu phục vụ trình diễn.
$stats = [
    'total_skus'       => 5,
    'inventory_lines'  => 5,
    'pending_actions'  => 2,
    'fefo_alerts'      => 2,
];

// 11 Kho chuẩn FR-02
$warehouses = WAREHOUSES_DEF;
$warehouseStats = [
    'KNL-01' => ['used' => 1850, 'cap' => 2500, 'items' => 12],
    'KNL-02' => ['used' => 920,  'cap' => 1800, 'items' => 18],
    'KNL-03' => ['used' => 410,  'cap' => 800,  'items' => 9],
    'KNL-04' => ['used' => 2250, 'cap' => 3000, 'items' => 24],
    'KNL-05' => ['used' => 1340, 'cap' => 2000, 'items' => 16],
    'KTP-01' => ['used' => 2860, 'cap' => 3500, 'items' => 22],
    'KTP-02' => ['used' => 2450, 'cap' => 4000, 'items' => 19],
    'KTP-03' => ['used' => 2180, 'cap' => 3000, 'items' => 27],
    'KTP-04' => ['used' => 510,  'cap' => 900,  'items' => 8],
    'KTP-05' => ['used' => 1720, 'cap' => 2800, 'items' => 14],
    'KHL-01' => ['used' => 180,  'cap' => 1500, 'items' => 5],
];

// Danh sách cảnh báo FEFO (Lô hàng sắp hết hạn) - FR-07, FR-13
$fefoAlerts = [
    [
        'sku' => 'NVL-COCA-01',
        'name' => 'Nước cốt cô đặc hương Coca-Cola',
        'lot' => 'LOT-CONC-2026-044',
        'warehouse' => 'KNL-02',
        'location' => 'KNL02-A-R01-S01-B01',
        'qty' => 240,
        'unit' => 'Kg',
        'expiry' => '2026-11-12',
        'days_left' => 11,
        'priority' => 'CRITICAL'
    ],
    [
        'sku' => 'NVL-SIRO-01',
        'name' => 'Siro nền Coca-Cola',
        'lot' => 'LOT-SIRO-2026-110',
        'warehouse' => 'KNL-01',
        'location' => 'KNL01-A-R01-S01-B01',
        'qty' => 420,
        'unit' => 'Kg',
        'expiry' => '2026-11-20',
        'days_left' => 19,
        'priority' => 'HIGH'
    ],
];

// Danh sách tiến độ phiếu gần đây (FR-19)
$recentTransactions = [
    [
        'code' => 'PN-NVL-2026-1101',
        'type' => 'Nhập đường tinh luyện (PO)',
        'type_badge' => 'primary',
        'ref' => 'PO-2026-11-001',
        'warehouse' => 'Kho Đường & siro (KNL-01)',
        'creator' => 'Nguyễn Nhân Viên (Thủ kho)',
        'time' => '10 phút trước',
        'status' => 'Đã kiểm định QC Đạt',
        'status_badge' => 'success'
    ],
    [
        'code' => 'PX-NVL-2026-0189',
        'type' => 'Cấp siro cho dây chuyền',
        'type_badge' => 'warning text-dark',
        'ref' => 'YC-PHACHE-042',
        'warehouse' => 'Kho Nước cốt (KNL-02)',
        'creator' => 'Lê Quản Lý Kho',
        'time' => '45 phút trước',
        'status' => 'Chờ duyệt xuất',
        'status_badge' => 'warning text-dark'
    ],
    [
        'code' => 'PN-TP-2026-0078',
        'type' => 'Nhập Coca-Cola thành phẩm',
        'type_badge' => 'info',
        'ref' => 'LSX-2026-109',
        'warehouse' => 'Kho Chai PET (KTP-01)',
        'creator' => 'Đặng Quản Đốc (Xưởng 1)',
        'time' => '2 giờ trước',
        'status' => 'Chờ QC kiểm định',
        'status_badge' => 'secondary'
    ],
    [
        'code' => 'PX-TP-2026-0231',
        'type' => 'Xuất nước ngọt phân phối',
        'type_badge' => 'success',
        'ref' => 'DH-NPP-2026-01',
        'warehouse' => 'Kho Chờ phân phối (KTP-03)',
        'creator' => 'Đỗ Chuyên Viên (Sales)',
        'time' => '4 giờ trước',
        'status' => 'Đã xuất hàng',
        'status_badge' => 'primary'
    ],
    [
        'code' => 'DC-2026-0045',
        'type' => 'Điều chuyển nội bộ',
        'type_badge' => 'dark',
        'ref' => 'DC-KNL01->04',
        'warehouse' => 'KNL-01 &rarr; KNL-04',
        'creator' => 'Lê Quản Lý Kho',
        'time' => 'Hôm qua',
        'status' => 'Đang vận chuyển',
        'status_badge' => 'info'
    ],
];
$transactionsByRole = [
    ROLE_QC => [0, 2],
    ROLE_PROCUREMENT => [0],
    ROLE_WORKSHOP => [1, 2],
    ROLE_PLANNING => [1],
    ROLE_SALES => [3],
];
$visibleTransactions = in_array($currentUser['role'], [ROLE_ADMIN, ROLE_DIRECTOR, ROLE_MANAGER, ROLE_STAFF], true)
    ? $recentTransactions
    : array_intersect_key($recentTransactions, array_flip($transactionsByRole[$currentUser['role']] ?? []));
$canViewExecutiveReport = hasRole(ROLE_DIRECTOR, ROLE_MANAGER);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <?php if ($flashMessage !== null): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <!-- 1. BĂNG CHÀO MỪNG & TRẠNG THÁI HỆ THỐNG -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <?= htmlspecialchars($roleIntro['title']) ?>
            </h4>
            <div class="text-muted small">
                Xin chào <?= htmlspecialchars($currentUser['fullname']) ?>.
                <span class="badge bg-<?= ROLES_LIST[$currentUser['role']]['badge'] ?>"><?= htmlspecialchars($currentUser['role_name']) ?></span>
                &bull; <?= htmlspecialchars($roleIntro['description']) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (canAccessPage('scanner')): ?>
                <a href="<?= BASE_URL ?>/pages/scanner/" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-qrcode me-1"></i> Quét Barcode/QR
                </a>
            <?php endif; ?>
            <?php if (canAccessPage('receipts_raw')): ?>
                <a href="<?= BASE_URL ?>/pages/receipts/?type=raw" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Lập Phiếu Nhập
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. THẺ CHỈ SỐ KPI VẬN HÀNH (EXECUTIVE METRICS) -->
    <div class="row g-3 mb-4">
        <!-- Tổng SKU -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Mặt hàng & nguyên liệu (SKU)</div>
                        <div class="stat-value mt-2"><?= number_format($stats['total_skus']) ?></div>
                        <div class="small text-muted mt-1">
                            <span class="text-muted fw-semibold">Dữ liệu mẫu</span>
                        </div>
                    </div>
                    <div class="icon-box">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tồn kho tổng -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Dòng tồn kho đang quản lý</div>
                            <div class="stat-value mt-2"><?= number_format($stats['inventory_lines']) ?></div>
                        <div class="small text-muted mt-1">
                                Theo mặt hàng, lô và vị trí
                        </div>
                    </div>
                    <div class="icon-box">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phiếu chờ xử lý -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Phiếu Chờ Duyệt (FR-19)</div>
                        <div class="stat-value mt-2"><?= $stats['pending_actions'] ?></div>
                        <div class="small text-warning fw-semibold mt-1">
                            <i class="fa-solid fa-clock"></i> Cần điều phối ngay
                        </div>
                    </div>
                    <div class="icon-box">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cảnh báo Cận HSD (FEFO) -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Lô nguyên liệu cận hạn (FR-13)</div>
                        <div class="stat-value mt-2 text-danger"><?= $stats['fefo_alerts'] ?> Lô</div>
                        <div class="small text-danger fw-semibold mt-1">
                            <i class="fa-solid fa-triangle-exclamation"></i> Ưu tiên xuất FEFO
                        </div>
                    </div>
                    <div class="icon-box">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. THAO TÁC NGHIỆP VỤ NHANH (QUICK ACTIONS FOR TEAM TESTING) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="card-title fw-bold mb-0 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.05em; color: #64748b;">
                    <i class="fa-solid fa-bolt text-warning me-1"></i> Điều Hướng Thao Tác Nghiệp Vụ Nhanh (FR-06 &rarr; FR-15)
                </h6>
                <span class="text-muted small">Hiển thị theo quyền tài khoản</span>
            </div>
            <div class="row g-2">
                <?php foreach ($dashboardActions as $action): ?>
                    <?php if (canAccessPage($action['page'])): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="<?= BASE_URL . $action['url'] ?>" class="quick-action-btn">
                                <i class="fa-solid <?= htmlspecialchars($action['icon']) ?> text-<?= htmlspecialchars($action['color']) ?>"></i>
                                <span><?= htmlspecialchars($action['label']) ?></span>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($action['detail']) ?></small>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 4. KHỐI GIỮA: SƠ ĐỒ 11 KHO & CẢNH BÁO FEFO -->
    <div class="row g-3 mb-4">
        <!-- 4.1. TÌNH TRẠNG LẤP ĐẦY 11 KHO (FR-02) -->
        <?php if (canAccessPage('warehouses')): ?>
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-warehouse text-primary me-2"></i>Tình Trạng 11 Kho Nguyên liệu & Nước ngọt (FR-02)
                    </h6>
                    <a href="<?= BASE_URL ?>/pages/warehouses/" class="btn btn-sm btn-link text-decoration-none">Xem chi tiết &rarr;</a>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <!-- Nhóm 5 Kho Nguyên Liệu -->
                        <div class="col-12">
                            <div class="small fw-bold text-uppercase text-muted mb-2">
                                        <i class="fa-solid fa-cubes text-secondary me-1"></i> 5 Kho nguyên liệu & bao bì (KNL-01 &rarr; KNL-05)
                            </div>
                            <div class="row g-2">
                                <?php for ($i = 0; $i < 5; $i++): 
                                    $wh = $warehouses[$i];
                                    $st = $warehouseStats[$wh['code']];
                                    $pct = round(($st['used'] / $st['cap']) * 100);
                                    $barColor = $pct > 85 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-primary');
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="warehouse-meter-card">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-secondary badge-type"><?= $wh['code'] ?></span>
                                            <span class="small fw-bold <?= $pct > 85 ? 'text-danger' : 'text-dark' ?>"><?= $pct ?>%</span>
                                        </div>
                                        <div class="text-truncate fw-semibold small mb-2" title="<?= $wh['name'] ?>">
                                            <?= $wh['name'] ?>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar <?= $barColor ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem; margin-top: 4px;">
                                            <span><?= number_format($st['used']) ?> / <?= number_format($st['cap']) ?> m³</span>
                                            <span><?= $st['items'] ?> SKU</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Nhóm 5 Kho Thành Phẩm & 1 Kho Hàng Lỗi -->
                        <div class="col-12 mt-3">
                            <div class="small fw-bold text-uppercase text-muted mb-2">
                                <i class="fa-solid fa-boxes-packing text-success me-1"></i> 5 Kho nước ngọt thành phẩm & 1 Kho cách ly (KTP-01..05 & KHL-01)
                            </div>
                            <div class="row g-2">
                                <?php for ($i = 5; $i < 11; $i++): 
                                    $wh = $warehouses[$i];
                                    $st = $warehouseStats[$wh['code']];
                                    $pct = round(($st['used'] / $st['cap']) * 100);
                                    $isDefective = $wh['code'] === 'KHL-01';
                                    $barColor = $isDefective ? 'bg-danger' : ($pct > 85 ? 'bg-danger' : 'bg-success');
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="warehouse-meter-card <?= $isDefective ? 'border-danger-subtle bg-danger-subtle bg-opacity-10' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge <?= $isDefective ? 'bg-danger' : 'bg-success' ?> badge-type"><?= $wh['code'] ?></span>
                                            <span class="small fw-bold text-dark"><?= $pct ?>%</span>
                                        </div>
                                        <div class="text-truncate fw-semibold small mb-2" title="<?= $wh['name'] ?>">
                                            <?= $wh['name'] ?>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar <?= $barColor ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem; margin-top: 4px;">
                                            <span><?= number_format($st['used']) ?> / <?= number_format($st['cap']) ?> m³</span>
                                            <span><?= $st['items'] ?> SKU</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4.2. CẢNH BÁO HẠN DÙNG & GỢI Ý XUẤT FEFO (FR-07, FR-13) -->
        <div class="<?= canAccessPage('warehouses') ? 'col-xl-5' : 'col-xl-12' ?>">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-hourglass-half text-warning me-2"></i>Gợi Ý Xuất FEFO & Cận HSD (FR-07, 13)
                    </h6>
                    <span class="badge bg-warning text-dark">FEFO nguyên liệu</span>
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted mb-3">
                        Hệ thống tự động sắp xếp các lô hàng cận ngày hết hạn nhất để nhân viên kho ưu tiên lập phiếu xuất (First Expired, First Out).
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($fefoAlerts as $item): ?>
                            <div class="p-3 border rounded-3 bg-light position-relative">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <span class="badge bg-dark me-1"><?= $item['sku'] ?></span>
                                        <span class="fw-bold text-dark small"><?= $item['name'] ?></span>
                                    </div>
                                    <span class="badge <?= $item['priority'] === 'CRITICAL' ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                        Còn <?= $item['days_left'] ?> ngày
                                    </span>
                                </div>
                                <div class="small text-muted mt-2 d-flex flex-wrap justify-content-between">
                                    <span>Số Lô: <strong><?= $item['lot'] ?></strong></span>
                                    <span>Tồn: <strong class="text-primary"><?= $item['qty'] ?> <?= $item['unit'] ?></strong></span>
                                </div>
                                <div class="small text-muted mt-1 d-flex justify-content-between align-items-center">
                                    <span>Vị trí: <code><?= $item['location'] ?></code></span>
                                    <?php if (canAccessPage('issues_raw')): ?>
                                        <a href="<?= BASE_URL ?>/pages/issues/?type=raw&auto_lot=<?= rawurlencode($item['lot']) ?>" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 fw-semibold" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-dolly me-1"></i> Xuất Lô Này
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. BẢNG TIẾN ĐỘ CHỨNG TỪ & BIỂU ĐỒ BÁO CÁO BGĐ (FR-17, FR-19) -->
    <div class="row g-3 mb-4">
        <!-- 5.1. BẢNG THEO DÕI TIẾN ĐỘ CHỨNG TỪ GẦN ĐÂY -->
        <div class="<?= $canViewExecutiveReport ? 'col-xl-8' : 'col-xl-12' ?>">
            <div class="custom-table shadow-sm">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-list-check text-primary me-2"></i>Tiến Độ Xử Lý Phiếu Kho Gần Đây (FR-19)
                    </h6>
                    <a href="<?= BASE_URL ?>/pages/inventory/" class="btn btn-sm btn-link text-decoration-none">Tra cứu tồn kho &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Mã Phiếu</th>
                                <th>Nghiệp Vụ</th>
                                <th>Kho Thực Hiện</th>
                                <th>Người Lập</th>
                                <th>Thời Gian</th>
                                <th>Trạng Thái</th>
                                <th class="text-end">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visibleTransactions as $tx): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary"><?= $tx['code'] ?></span>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= $tx['ref'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $tx['type_badge'] ?>"><?= $tx['type'] ?></span>
                                </td>
                                <td><?= $tx['warehouse'] ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= $tx['creator'] ?></div>
                                </td>
                                <td class="text-muted small"><?= $tx['time'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $tx['status_badge'] ?>"><?= $tx['status'] ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light border py-1 px-2" title="Xem chi tiết" onclick="showToast('Xem chứng từ', 'Đang mở phiếu <?= $tx['code'] ?>', 'info')">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5.2. BIỂU ĐỒ BÁO CÁO BGĐ (FR-17) -->
        <?php if ($canViewExecutiveReport): ?>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-chart-simple text-primary me-2"></i>Báo Cáo Nhập - Xuất Tuần (FR-17)
                    </h6>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-center">
                    <canvas id="weeklyFlowChart" height="220"></canvas>
                    <div class="mt-3 text-center small text-muted">
                        Dữ liệu biến động xuất nhập tồn 7 ngày gần nhất
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('weeklyFlowChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
                datasets: [
                    {
                        label: 'Nhập kho (Tấn / ĐV)',
                        data: [42, 58, 35, 62, 70, 25, 12],
                        backgroundColor: '#2563eb',
                        borderRadius: 4
                    },
                    {
                        label: 'Xuất kho (Tấn / ĐV)',
                        data: [35, 48, 50, 45, 65, 30, 8],
                        backgroundColor: '#f59e0b',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>
