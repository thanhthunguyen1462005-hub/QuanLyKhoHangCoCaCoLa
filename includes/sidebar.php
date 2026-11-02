<?php
$currentPage = $_SERVER['PHP_SELF'];
$sidebarGroups = [
    [
        'title' => 'Tổng quan',
        'links' => [
            ['page' => 'dashboard', 'label' => 'Bảng điều khiển', 'icon' => 'fa-chart-pie', 'url' => '/pages/dashboard/'],
        ],
    ],
    [
        'title' => 'Kho & tồn kho',
        'links' => [
            ['page' => 'warehouses', 'label' => 'Hệ thống kho', 'icon' => 'fa-warehouse', 'url' => '/pages/warehouses/'],
            ['page' => 'inventory', 'label' => 'Tra cứu tồn kho', 'icon' => 'fa-cubes', 'url' => '/pages/inventory/'],
            ['page' => 'scanner', 'label' => 'Quét Barcode / QR', 'icon' => 'fa-qrcode', 'url' => '/pages/scanner/'],
        ],
    ],
    [
        'title' => 'Nghiệp vụ nhập kho',
        'links' => [
            ['page' => 'receipts_raw', 'label' => 'Nhập nguyên liệu (PO)', 'icon' => 'fa-cart-flatbed', 'url' => '/pages/receipts/?type=raw'],
            ['page' => 'receipts_finished', 'label' => 'Nhập thành phẩm', 'icon' => 'fa-box-open', 'url' => '/pages/receipts/?type=finished'],
        ],
    ],
    [
        'title' => 'Nghiệp vụ xuất kho',
        'links' => [
            ['page' => 'issues_raw', 'label' => 'Xuất nguyên liệu (FEFO)', 'icon' => 'fa-share-from-square', 'url' => '/pages/issues/?type=raw'],
            ['page' => 'issues_finished', 'label' => 'Xuất thành phẩm', 'icon' => 'fa-truck-fast', 'url' => '/pages/issues/?type=finished'],
        ],
    ],
    [
        'title' => 'Kế hoạch sản xuất',
        'links' => [
            [
                'page' => 'production_planning',
                'label' => 'Lập kế hoạch sản xuất',
                'icon' => 'fa-calendar-days',
                'url' => '/pages/production_planning/',
            ],
        ],
    ],
    [
        'title' => 'Nghiệp vụ Xưởng',
        'links' => [
            [
                'page' => 'production_material_requests',
                'label' => 'Lập phiếu nhận nguyên liệu',
                'icon' => 'fa-file-circle-plus',
                'url' => '/pages/production_material_requests/',
            ],
        ],
    ],
    [
        'title' => 'Kiểm kê',
        'links' => [
            ['page' => 'audits', 'label' => 'Phê duyệt biên bản kiểm kê', 'icon' => 'fa-clipboard-check', 'url' => '/pages/audits/'],
        ],
    ],
    [
        'title' => 'Kiểm định chất lượng',
        'links' => [
            [
                'page' => 'qaqc',
                'label' => 'Lập biên bản kiểm định chất lượng',
                'icon' => 'fa-clipboard-check',
                'url' => '/pages/qaqc/'
            ],
        ],
    ],
    [
        'title' => 'Quản trị',
        'links' => [
            ['page' => 'reports', 'label' => 'Báo cáo quản trị', 'icon' => 'fa-chart-line', 'url' => '/pages/reports/'],
            ['page' => 'audit_logs', 'label' => 'Nhật ký hệ thống', 'icon' => 'fa-clock-rotate-left', 'url' => '/pages/audit_logs/'],
        ],
    ],
];
?>
<aside class="app-sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/pages/dashboard/" class="sidebar-brand">
            <div class="brand-icon"><i class="fa-solid fa-warehouse"></i></div>
            <div>
                <span style="font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px;">COCA-COLA WMS</span>
                <span class="d-block" style="font-size: 0.65rem; color: #94a3b8; font-weight: 500;">QUẢN LÝ KHO NƯỚC GIẢI KHÁT</span>
            </div>
        </a>
    </div>
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php foreach ($sidebarGroups as $group): ?>
                <?php
                $visibleLinks = array_filter($group['links'], static function ($link) {
                    return canAccessPage($link['page']);
                });
                ?>
                <?php if ($visibleLinks !== []): ?>
                    <li class="sidebar-menu-category"><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php foreach ($visibleLinks as $link): ?>
                        <?php
                        $isCurrent = $link['page'] === 'receipts_raw'
                            ? (strpos($currentPage, '/receipts/') !== false && ($_GET['type'] ?? 'raw') === 'raw')
                            : ($link['page'] === 'receipts_finished'
                                ? (strpos($currentPage, '/receipts/') !== false && ($_GET['type'] ?? '') === 'finished')
                                : ($link['page'] === 'issues_raw'
                                    ? (strpos($currentPage, '/issues/') !== false && ($_GET['type'] ?? 'raw') === 'raw')
                                    : ($link['page'] === 'issues_finished'
                                        ? (strpos($currentPage, '/issues/') !== false && ($_GET['type'] ?? '') === 'finished')
                                        : strpos($currentPage, str_replace('/pages', '', $link['url'])) !== false)));
                        ?>
                        <li class="sidebar-nav-item">
                            <a href="<?= BASE_URL . $link['url'] ?>" class="sidebar-nav-link <?= $isCurrent ? 'active' : '' ?>">
                                <span class="nav-icon"><i class="fa-solid <?= htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <span><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="sidebar-user">
        <span class="sidebar-user-icon"><i class="fa-solid <?= htmlspecialchars(ROLES_LIST[$currentUser['role']]['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
        <div class="overflow-hidden flex-grow-1">
            <div class="text-white fw-bold text-truncate" style="font-size: 0.85rem;"><?= htmlspecialchars($currentUser['fullname'], ENT_QUOTES, 'UTF-8') ?></div>
            <span class="badge bg-<?= htmlspecialchars(ROLES_LIST[$currentUser['role']]['badge'] ?? 'secondary', ENT_QUOTES, 'UTF-8') ?>" style="font-size: 0.65rem;">
                <?= htmlspecialchars($currentUser['role_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>
</aside>