<?php
$currentUser = currentUser();
?>
<div class="app-main">
<header class="app-header">
    <div class="d-flex align-items-center gap-3">
        <button id="sidebarToggle" class="btn btn-sm btn-light border d-lg-none" type="button" aria-label="Mở menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="position-relative d-none d-md-block" style="width: 320px;">
            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="search" id="globalQuickSearch" class="form-control form-control-sm ps-5 rounded-pill bg-light border-0"
                   placeholder="Tìm SKU, Barcode, Số Lô, Vị trí kệ..." autocomplete="off">
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if (canAccessPage('inventory')): ?>
            <a href="<?= BASE_URL ?>/pages/inventory/" class="btn btn-light rounded-circle position-relative p-2" aria-label="Xem cảnh báo tồn kho" style="width: 40px; height: 40px;">
                <i class="fa-regular fa-bell text-secondary"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">2</span>
            </a>
        <?php endif; ?>

        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 border-0 bg-transparent dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-menu-icon"><i class="fa-solid <?= htmlspecialchars(ROLES_LIST[$currentUser['role']]['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <span class="text-start d-none d-sm-block">
                    <span class="d-block fw-semibold text-dark" style="font-size: 0.875rem;"><?= htmlspecialchars($currentUser['fullname'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="d-block text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($currentUser['role_name'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                <li><h6 class="dropdown-header">Tài khoản: <?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= BASE_URL ?>/auth/logout.php" class="px-2">
                        <?= csrfField() ?>
                        <button type="submit" class="dropdown-item py-2 text-danger rounded">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Đăng xuất
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
<main class="app-content">
