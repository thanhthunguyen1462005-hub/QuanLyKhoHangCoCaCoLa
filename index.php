<?php
require_once __DIR__ . '/config/constants.php';

if (currentUser() !== null) {
    header('Location: ' . BASE_URL . '/pages/dashboard/');
    exit;
}

$loggedOut = isset($_GET['logged_out']);
$roles = [
    ['icon' => 'fa-shield-halved', 'name' => 'Quản trị hệ thống', 'description' => 'Quản lý truy cập và theo dõi hệ thống.'],
    ['icon' => 'fa-user-tie', 'name' => 'Ban Giám đốc', 'description' => 'Theo dõi tổng quan và duyệt kiểm kê.'],
    ['icon' => 'fa-user-gear', 'name' => 'Quản lý kho', 'description' => 'Điều phối các nghiệp vụ kho.'],
    ['icon' => 'fa-boxes-packing', 'name' => 'Nhân viên kho', 'description' => 'Thực hiện nhập, xuất và quét mã.'],
    ['icon' => 'fa-microscope', 'name' => 'QA/QC Chất lượng', 'description' => 'Tra cứu lô hàng phục vụ kiểm định.'],
    ['icon' => 'fa-cart-shopping', 'name' => 'Bộ phận Mua hàng', 'description' => 'Theo dõi tồn và nhập nguyên liệu.'],
    ['icon' => 'fa-industry', 'name' => 'Xưởng sản xuất', 'description' => 'Theo dõi cấp nguyên liệu, nhập thành phẩm.'],
    ['icon' => 'fa-calendar-days', 'name' => 'Lập kế hoạch', 'description' => 'Theo dõi tồn kho và nguyên liệu sản xuất.'],
    ['icon' => 'fa-truck-fast', 'name' => 'Bộ phận Bán hàng', 'description' => 'Tra cứu tồn thành phẩm và xuất hàng.'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="public-home">
    <nav class="landing-nav">
        <a class="landing-brand" href="<?= BASE_URL ?>/">
            <span class="landing-brand-icon"><i class="fa-solid fa-warehouse"></i></span>
            <span><strong>COCA-COLA WMS</strong><small>WAREHOUSE MANAGEMENT</small></span>
        </a>
        <a class="btn btn-primary px-4" href="<?= BASE_URL ?>/auth/login.php">
            Đăng nhập <i class="fa-solid fa-arrow-right ms-2"></i>
        </a>
    </nav>

    <main>
        <?php if ($loggedOut): ?>
            <div class="container pt-3">
                <div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-2"></i>Bạn đã đăng xuất an toàn.</div>
            </div>
        <?php endif; ?>
        <section class="landing-hero">
            <div class="landing-hero-copy">
                <span class="landing-eyebrow"><i class="fa-solid fa-bottle-water me-2"></i>HỆ THỐNG QUẢN LÝ KHO NHÀ MÁY</span>
                <h1>Quản lý kho <span>thông minh</span>, vận hành rõ ràng.</h1>
                <p>Theo dõi nguyên liệu, bao bì và thành phẩm Coca-Cola trên một giao diện tập trung. Mỗi tài khoản có màn hình và quyền truy cập theo đúng vai trò.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-primary btn-lg px-4" href="<?= BASE_URL ?>/auth/login.php">Đăng nhập hệ thống <i class="fa-solid fa-arrow-right ms-2"></i></a>
                    <a class="btn btn-outline-secondary btn-lg px-4" href="#roles">Xem các vai trò</a>
                </div>
                <div class="landing-highlights">
                    <span><i class="fa-solid fa-check"></i> 11 kho nội bộ</span>
                    <span><i class="fa-solid fa-check"></i> Theo dõi lô & hạn dùng</span>
                    <span><i class="fa-solid fa-check"></i> Phân quyền theo vai trò</span>
                </div>
            </div>
            <div class="landing-hero-visual" aria-hidden="true">
                <div class="landing-visual-orbit orbit-one"></div>
                <div class="landing-visual-orbit orbit-two"></div>
                <div class="landing-visual-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">TỔNG QUAN NHÀ MÁY</span>
                        <span class="badge rounded-pill text-bg-success">Đang hoạt động</span>
                    </div>
                    <div class="landing-visual-bottle"><i class="fa-solid fa-warehouse"></i></div>
                    <div class="landing-mini-stats">
                        <div><strong>11</strong><small>Kho nội bộ</small></div>
                        <div><strong>5</strong><small>SKU mẫu</small></div>
                        <div><strong>9</strong><small>Vai trò</small></div>
                    </div>
                    <div class="landing-stock-line"><span style="width: 76%"></span></div>
                    <div class="small text-muted mt-2">Nguyên liệu · Bao bì · Thành phẩm</div>
                </div>
                <span class="landing-float-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                <span class="landing-float-icon float-second"><i class="fa-solid fa-barcode"></i></span>
            </div>
        </section>

        <section class="landing-roles container" id="roles">
            <div class="text-center mb-4">
                <span class="landing-eyebrow">MỘT HỆ THỐNG · NHIỀU VAI TRÒ</span>
                <h2>Không gian làm việc phù hợp với từng bộ phận</h2>
                <p class="text-muted mb-0">Đăng nhập để xem các chức năng được cấp cho tài khoản của bạn.</p>
            </div>
            <div class="row g-3">
                <?php foreach ($roles as $role): ?>
                    <div class="col-sm-6 col-lg-4">
                        <article class="landing-role-card">
                            <span class="role-card-icon"><i class="fa-solid <?= htmlspecialchars($role['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                            <div>
                                <h3><?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($role['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <footer class="landing-footer">
        <span><strong><?= APP_NAME ?></strong> · Dữ liệu minh họa phục vụ học tập và trình diễn.</span>
        <span>© <?= date('Y') ?> · FEFO / FIFO</span>
    </footer>
</body>
</html>
