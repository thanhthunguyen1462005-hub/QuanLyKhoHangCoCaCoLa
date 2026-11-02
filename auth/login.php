<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

if (currentUser() !== null) {
    header('Location: ' . BASE_URL . '/pages/dashboard/');
    exit;
}

$errorMessage = '';
$username = '';
$demoAccounts = [
    ['username' => 'admin', 'role' => 'Quản trị hệ thống'],
    ['username' => 'giamdoc', 'role' => 'Ban Giám đốc'],
    ['username' => 'quanlykho', 'role' => 'Quản lý kho'],
    ['username' => 'thukho', 'role' => 'Nhân viên kho'],
    ['username' => 'qaqc', 'role' => 'QA/QC Chất lượng'],
    ['username' => 'muahang', 'role' => 'Bộ phận Mua hàng'],
    ['username' => 'xuong1', 'role' => 'Xưởng sản xuất'],
    ['username' => 'kehoach', 'role' => 'Lập kế hoạch'],
    ['username' => 'banhang', 'role' => 'Bộ phận Bán hàng'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.';
    } elseif ($username === '' || $password === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $pdo = Database::getConnection();

        if ($pdo === null) {
            $errorMessage = 'Không thể kết nối cơ sở dữ liệu. Hãy bật MySQL và import database/schema.sql trước khi đăng nhập.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'SELECT u.id, u.username, u.password, u.fullname, u.email, r.code AS role, r.name AS role_name
                     FROM users u
                     INNER JOIN roles r ON r.id = u.role_id
                     WHERE u.username = :username AND u.is_active = 1
                     LIMIT 1'
                );
                $statement->execute(['username' => $username]);
                $account = $statement->fetch();

                if ($account === false || !password_verify($password, $account['password'])) {
                    $errorMessage = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
                    try {
                        $failedLoginLog = $pdo->prepare(
                            "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                             VALUES (:user_id, 'LOGIN_FAILED', 'AUTHENTICATION', :record_id, :ip_address, :user_agent, :details)"
                        );
                        $failedLoginLog->execute([
                            'user_id' => $account === false ? null : $account['id'],
                            'record_id' => $account === false ? null : (string) $account['id'],
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                            'details' => json_encode([
                                'attempted_username' => $username,
                                'reason' => $account === false ? 'ACCOUNT_NOT_FOUND_OR_INACTIVE' : 'INVALID_PASSWORD',
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    } catch (Throwable $logException) {
                        error_log('Failed login attempt could not be recorded: ' . $logException->getMessage());
                    }
                } else {
                    $pdo->beginTransaction();
                    $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
                    $update->execute(['id' => $account['id']]);
                    $loginLog = $pdo->prepare(
                        "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                         VALUES (:user_id, 'LOGIN', 'AUTHENTICATION', :record_id, :ip_address, :user_agent, :details)"
                    );
                    $loginLog->execute([
                        'user_id' => $account['id'],
                        'record_id' => (string) $account['id'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'details' => 'Đăng nhập thành công.',
                    ]);
                    $pdo->commit();

                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);
                    $_SESSION['authenticated'] = true;
                    $_SESSION['current_user'] = [
                        'id' => (int) $account['id'],
                        'username' => $account['username'],
                        'fullname' => $account['fullname'],
                        'email' => $account['email'],
                        'role' => $account['role'],
                        'role_name' => $account['role_name'],
                    ];

                    header('Location: ' . BASE_URL . '/pages/dashboard/');
                    exit;
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Login database operation failed: ' . $exception->getMessage());
                $errorMessage = 'Không thể xác thực tài khoản lúc này. Vui lòng kiểm tra cấu hình cơ sở dữ liệu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-panel">
            <a class="landing-brand mb-5" href="<?= BASE_URL ?>/">
                <span class="landing-brand-icon"><i class="fa-solid fa-warehouse"></i></span>
                <span><strong>COCA-COLA WMS</strong><small>WAREHOUSE MANAGEMENT</small></span>
            </a>
            <div class="auth-heading">
                <span class="landing-eyebrow">CHÀO MỪNG TRỞ LẠI</span>
                <h1>Đăng nhập hệ thống</h1>
                <p>Nhập tài khoản được quản trị viên cấp để tiếp tục.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>/auth/login.php" class="auth-form">
                <?= csrfField() ?>
                <label for="username" class="form-label fw-semibold">Tên đăng nhập</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required autofocus>
                </div>
                <label for="password" class="form-label fw-semibold">Mật khẩu</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Hiện hoặc ẩn mật khẩu"><i class="fa-regular fa-eye"></i></button>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
                    Đăng nhập <i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>
                </button>
            </form>

            <div class="auth-demo-box">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div><strong><i class="fa-solid fa-flask me-2 text-primary"></i>Tài khoản dùng thử</strong>
                        <div class="small text-muted mt-1">Chọn vai trò để điền nhanh thông tin.</div>
                    </div>
                    <span class="badge text-bg-warning">Demo</span>
                </div>
                <div class="demo-account-list">
                    <?php foreach ($demoAccounts as $account): ?>
                        <button type="button" class="demo-account" data-username="<?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mb-0 mt-2">Mật khẩu mẫu: <code>123456</code>. Chỉ dùng cho bản demo; hãy đổi mật khẩu trước khi triển khai thật.</p>
            </div>
            <a href="<?= BASE_URL ?>/" class="auth-back-link"><i class="fa-solid fa-arrow-left me-2"></i>Về trang chủ</a>
        </section>
        <aside class="auth-side">
            <div class="auth-side-content">
                <span class="auth-side-mark"><i class="fa-solid fa-boxes-stacked"></i></span>
                <span class="landing-eyebrow text-white-50">WAREHOUSE · QUALITY · DELIVERY</span>
                <h2>Mọi luồng kho hàng, trong tầm kiểm soát.</h2>
                <p>Phân quyền đúng vai trò giúp mỗi bộ phận tập trung vào đúng nghiệp vụ của mình.</p>
                <div class="auth-side-points">
                    <span><i class="fa-solid fa-circle-check"></i> Quản lý nguyên liệu & thành phẩm</span>
                    <span><i class="fa-solid fa-circle-check"></i> Theo dõi lô và hạn sử dụng</span>
                    <span><i class="fa-solid fa-circle-check"></i> Giao diện theo từng bộ phận</span>
                </div>
            </div>
            <div class="auth-side-footer">Dữ liệu minh họa phục vụ học tập và trình diễn.</div>
        </aside>
    </main>
    <script>
        document.querySelectorAll('.demo-account').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('username').value = button.dataset.username;
                document.getElementById('password').value = '123456';
                document.getElementById('password').focus();
            });
        });

        document.getElementById('togglePassword').addEventListener('click', (event) => {
            const password = document.getElementById('password');
            const visible = password.type === 'password';
            password.type = visible ? 'text' : 'password';
            event.currentTarget.innerHTML = visible ?
                '<i class="fa-regular fa-eye-slash"></i>' :
                '<i class="fa-regular fa-eye"></i>';
        });
    </script>
</body>

</html>