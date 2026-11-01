<?php
/**
 * FR-15: QUẢN LÝ KIỂM KÊ & CÂN BẰNG TỒN KHO
 */
$pageTitle = 'Phê Duyệt Biên Bản Kiểm Kê (FR-15)';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('audits');

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $auditId = filter_var($_POST['audit_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $submittedReason = $_POST['rejection_reason'] ?? null;
    $flashType = 'danger';
    $flashText = 'Yêu cầu không hợp lệ.';

    if (!hasRole(ROLE_DIRECTOR)) {
        $flashType = 'warning';
        $flashText = 'Chỉ Ban Giám đốc mới có quyền phê duyệt hoặc từ chối biên bản kiểm kê.';
    } elseif (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $flashType = 'warning';
        $flashText = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif ($auditId === false || !in_array($action, ['approve', 'reject'], true)) {
        $flashType = 'warning';
        $flashText = 'Thông tin biên bản hoặc thao tác không hợp lệ.';
    } elseif ($pdo === null) {
        $flashText = 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL.';
    } elseif ($action === 'reject' && (!is_string($submittedReason) || trim($submittedReason) === '')) {
        $flashType = 'warning';
        $flashText = 'Vui lòng nhập lý do từ chối.';
    } else {
        $mailRecipient = null;
        $mailSubject = null;
        $mailBody = null;

        try {
            $pdo->beginTransaction();

            $auditQuery = $pdo->prepare(
                'SELECT id, audit_code, title, warehouse_id, created_by, verified_by, status
                 FROM inventory_audits
                 WHERE id = :id
                 FOR UPDATE'
            );
            $auditQuery->execute(['id' => $auditId]);
            $audit = $auditQuery->fetch();

            if ($audit === false) {
                throw new DomainException('Không tìm thấy biên bản kiểm kê.');
            }
            if ($audit['status'] !== 'WAITING_DIRECTOR' || $audit['verified_by'] === null) {
                throw new DomainException('Biên bản không còn ở trạng thái chờ Ban Giám đốc duyệt hoặc chưa được Quản lý kho xác minh.');
            }

            if ($action === 'approve') {
                $itemsQuery = $pdo->prepare(
                    'SELECT ai.id, ai.product_id, ai.lot_id, ai.location_id,
                            ai.system_quantity, ai.actual_quantity, ai.discrepancy_reason,
                            ai.recommended_action, p.sku, p.name AS product_name, p.unit,
                            l.lot_number, wl.location_code, wl.warehouse_id AS location_warehouse_id
                     FROM inventory_audit_items ai
                     INNER JOIN products p ON p.id = ai.product_id
                     INNER JOIN product_lots l ON l.id = ai.lot_id
                     INNER JOIN warehouse_locations wl ON wl.id = ai.location_id
                     WHERE ai.audit_id = :audit_id
                     ORDER BY ai.id
                     FOR UPDATE'
                );
                $itemsQuery->execute(['audit_id' => $auditId]);
                $items = $itemsQuery->fetchAll();

                if ($items === []) {
                    throw new DomainException('Biên bản chưa có dòng hàng kiểm kê để phê duyệt.');
                }

                $stockQuery = $pdo->prepare(
                    'SELECT id, quantity, reserved_quantity
                     FROM inventory
                     WHERE warehouse_id = :warehouse_id
                       AND location_id = :location_id
                       AND product_id = :product_id
                       AND lot_id = :lot_id
                     FOR UPDATE'
                );
                $adjustments = [];
                $checkedPositions = [];

                foreach ($items as $item) {
                    if ((int) $item['location_warehouse_id'] !== (int) $audit['warehouse_id']) {
                        throw new DomainException('Vị trí lưu trữ trong biên bản không thuộc kho đang kiểm kê.');
                    }
                    if ((int) $item['system_quantity'] < 0 || (int) $item['actual_quantity'] < 0) {
                        throw new DomainException('Số lượng kiểm kê không hợp lệ.');
                    }

                    $positionKey = implode(':', [
                        $item['product_id'],
                        $item['lot_id'],
                        $item['location_id'],
                    ]);
                    if (isset($checkedPositions[$positionKey])) {
                        throw new DomainException('Biên bản có dòng hàng trùng vị trí tồn kho. Vui lòng chỉnh sửa biên bản trước khi duyệt.');
                    }
                    $checkedPositions[$positionKey] = true;

                    $stockQuery->execute([
                        'warehouse_id' => $audit['warehouse_id'],
                        'location_id' => $item['location_id'],
                        'product_id' => $item['product_id'],
                        'lot_id' => $item['lot_id'],
                    ]);
                    $stock = $stockQuery->fetch();
                    $currentQuantity = $stock === false ? 0 : (int) $stock['quantity'];

                    if ($currentQuantity !== (int) $item['system_quantity']) {
                        throw new DomainException('Dữ liệu tồn kho đã biến động, vui lòng làm mới biên bản');
                    }
                    if ($stock !== false && (int) $item['actual_quantity'] < (int) $stock['reserved_quantity']) {
                        throw new DomainException('Số lượng thực đếm thấp hơn lượng hàng đã được giữ cho phiếu xuất. Vui lòng xử lý các phiếu đang giữ hàng trước.');
                    }

                    $adjustments[] = [
                        'item' => $item,
                        'stock_id' => $stock === false ? null : (int) $stock['id'],
                        'current_quantity' => $currentQuantity,
                        'actual_quantity' => (int) $item['actual_quantity'],
                    ];
                }

                $updateStock = $pdo->prepare('UPDATE inventory SET quantity = :quantity WHERE id = :id');
                $insertStock = $pdo->prepare(
                    'INSERT INTO inventory (warehouse_id, location_id, product_id, lot_id, quantity)
                     VALUES (:warehouse_id, :location_id, :product_id, :lot_id, :quantity)'
                );
                $auditChanges = [];

                foreach ($adjustments as $adjustment) {
                    $item = $adjustment['item'];
                    $actualQuantity = $adjustment['actual_quantity'];

                    if ($adjustment['stock_id'] !== null) {
                        $updateStock->execute([
                            'quantity' => $actualQuantity,
                            'id' => $adjustment['stock_id'],
                        ]);
                    } elseif ($actualQuantity > 0) {
                        $insertStock->execute([
                            'warehouse_id' => $audit['warehouse_id'],
                            'location_id' => $item['location_id'],
                            'product_id' => $item['product_id'],
                            'lot_id' => $item['lot_id'],
                            'quantity' => $actualQuantity,
                        ]);
                    }

                    if ($adjustment['current_quantity'] !== $actualQuantity) {
                        $auditChanges[] = [
                            'sku' => $item['sku'],
                            'from' => $adjustment['current_quantity'],
                            'to' => $actualQuantity,
                        ];
                    }
                }

                $approve = $pdo->prepare(
                    "UPDATE inventory_audits
                     SET status = 'APPROVED', approved_by_director = :user_id, approved_at = NOW()
                     WHERE id = :id AND status = 'WAITING_DIRECTOR'"
                );
                $approve->execute(['user_id' => currentUser()['id'], 'id' => $auditId]);
                if ($approve->rowCount() !== 1) {
                    throw new DomainException('Biên bản vừa được xử lý bởi người dùng khác. Vui lòng tải lại danh sách.');
                }

                $logDetails = json_encode([
                    'audit_code' => $audit['audit_code'],
                    'inventory_adjustments' => $auditChanges,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $log = $pdo->prepare(
                    "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                     VALUES (:user_id, 'APPROVE', 'INVENTORY_AUDIT', :record_id, :ip_address, :user_agent, :details)"
                );
                $log->execute([
                    'user_id' => currentUser()['id'],
                    'record_id' => (string) $auditId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => $logDetails,
                ]);

                $pdo->commit();
                $flashType = 'success';
                $flashText = 'Phê duyệt biên bản kiểm kê thành công.';
            } else {
                $reason = trim($submittedReason);
                if (mb_strlen($reason, 'UTF-8') > 2000) {
                    throw new DomainException('Lý do từ chối không được vượt quá 2.000 ký tự.');
                }

                $recipientQuery = $pdo->prepare('SELECT fullname, email FROM users WHERE id = :id');
                $recipientQuery->execute(['id' => $audit['created_by']]);
                $recipient = $recipientQuery->fetch();

                $reject = $pdo->prepare(
                    "UPDATE inventory_audits
                     SET status = 'REJECTED', rejected_by_director = :user_id,
                         rejected_at = NOW(), rejection_reason = :reason
                     WHERE id = :id AND status = 'WAITING_DIRECTOR'"
                );
                $reject->execute([
                    'user_id' => currentUser()['id'],
                    'reason' => $reason,
                    'id' => $auditId,
                ]);
                if ($reject->rowCount() !== 1) {
                    throw new DomainException('Biên bản vừa được xử lý bởi người dùng khác. Vui lòng tải lại danh sách.');
                }

                $notification = $pdo->prepare(
                    'INSERT INTO in_app_notifications (user_id, audit_id, title, message)
                     VALUES (:user_id, :audit_id, :title, :message)'
                );
                $notification->execute([
                    'user_id' => $audit['created_by'],
                    'audit_id' => $auditId,
                    'title' => 'Biên bản kiểm kê bị từ chối',
                    'message' => 'Biên bản ' . $audit['audit_code'] . ' bị từ chối. Lý do: ' . $reason,
                ]);

                $log = $pdo->prepare(
                    "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, user_agent, details)
                     VALUES (:user_id, 'REJECT', 'INVENTORY_AUDIT', :record_id, :ip_address, :user_agent, :details)"
                );
                $log->execute([
                    'user_id' => currentUser()['id'],
                    'record_id' => (string) $auditId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'details' => json_encode([
                        'audit_code' => $audit['audit_code'],
                        'rejection_reason' => $reason,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                if ($recipient !== false && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                    $mailRecipient = $recipient['email'];
                    $mailSubject = '=?UTF-8?B?' . base64_encode('Biên bản kiểm kê bị từ chối - ' . $audit['audit_code']) . '?=';
                    $mailBody = "Xin chào " . $recipient['fullname'] . ",\r\n\r\n"
                        . "Biên bản kiểm kê " . $audit['audit_code'] . " (" . $audit['title'] . ") đã bị từ chối.\r\n"
                        . "Lý do: " . $reason . "\r\n\r\n"
                        . "Vui lòng đăng nhập hệ thống để xem và xử lý lại biên bản.";
                }

                $pdo->commit();

                $flashType = 'success';
                $flashText = 'Đã từ chối biên bản kiểm kê và tạo thông báo trong hệ thống.';
                if ($mailRecipient === null) {
                    $flashType = 'warning';
                    $flashText .= ' Không gửi được email vì người lập biên bản chưa có địa chỉ email hợp lệ.';
                } else {
                    try {
                        $mailSent = mail(
                            $mailRecipient,
                            $mailSubject,
                            $mailBody,
                            "From: Coca-Cola WMS <noreply@factory.com>\r\nContent-Type: text/plain; charset=UTF-8"
                        );
                    } catch (Throwable $mailException) {
                        $mailSent = false;
                        error_log('Inventory audit rejection email failed for audit ' . $auditId . ': ' . $mailException->getMessage());
                    }

                    if (!$mailSent) {
                        error_log('Inventory audit rejection email could not be sent for audit ' . $auditId . '.');
                        $flashType = 'warning';
                        $flashText .= ' Email chưa gửi được; vui lòng kiểm tra cấu hình SMTP của PHP.';
                    } else {
                        $flashText = 'Đã từ chối biên bản và gửi thông báo trong hệ thống cùng email nội bộ.';
                    }
                }
            }
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flashType = 'warning';
            $flashText = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Inventory audit decision failed for audit ' . $auditId . ': ' . $exception->getMessage());
            $flashText = 'Không thể xử lý biên bản do lỗi hệ thống. Giao dịch đã được hoàn tác; vui lòng thử lại.';
        }
    }

    setFlashMessage($flashType, $flashText);
    header('Location: ' . BASE_URL . '/pages/audits/');
    exit;
}

$flashMessage = consumeFlashMessage();
$pageError = '';
$audits = [];
$notifications = [];

if ($pdo === null) {
    $pageError = 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL.';
} else {
    try {
        $auditRows = $pdo->query(
            'SELECT a.id, a.audit_code, a.title, a.audit_date, a.status, a.notes,
                    a.rejection_reason, a.approved_at, a.rejected_at,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    creator.fullname AS creator_name, verifier.fullname AS verifier_name,
                    approver.fullname AS approver_name, rejector.fullname AS rejector_name,
                    ai.id AS item_id, ai.system_quantity, ai.actual_quantity,
                    ai.discrepancy_reason, ai.recommended_action,
                    p.sku, p.name AS product_name, p.unit,
                    l.lot_number, wl.location_code
             FROM inventory_audits a
             INNER JOIN warehouses w ON w.id = a.warehouse_id
             INNER JOIN users creator ON creator.id = a.created_by
             LEFT JOIN users verifier ON verifier.id = a.verified_by
             LEFT JOIN users approver ON approver.id = a.approved_by_director
             LEFT JOIN users rejector ON rejector.id = a.rejected_by_director
             LEFT JOIN inventory_audit_items ai ON ai.audit_id = a.id
             LEFT JOIN products p ON p.id = ai.product_id
             LEFT JOIN product_lots l ON l.id = ai.lot_id
             LEFT JOIN warehouse_locations wl ON wl.id = ai.location_id
             ORDER BY CASE WHEN a.status = "WAITING_DIRECTOR" THEN 0 ELSE 1 END,
                      a.audit_date DESC, a.id DESC'
        )->fetchAll();

        foreach ($auditRows as $row) {
            $id = (int) $row['id'];
            if (!isset($audits[$id])) {
                $audits[$id] = [
                    'id' => $id,
                    'audit_code' => $row['audit_code'],
                    'title' => $row['title'],
                    'audit_date' => $row['audit_date'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'rejection_reason' => $row['rejection_reason'],
                    'approved_at' => $row['approved_at'],
                    'rejected_at' => $row['rejected_at'],
                    'warehouse_code' => $row['warehouse_code'],
                    'warehouse_name' => $row['warehouse_name'],
                    'creator_name' => $row['creator_name'],
                    'verifier_name' => $row['verifier_name'],
                    'approver_name' => $row['approver_name'],
                    'rejector_name' => $row['rejector_name'],
                    'items' => [],
                ];
            }

            if ($row['item_id'] !== null) {
                $audits[$id]['items'][] = [
                    'sku' => $row['sku'],
                    'product_name' => $row['product_name'],
                    'unit' => $row['unit'],
                    'lot_number' => $row['lot_number'],
                    'location_code' => $row['location_code'],
                    'system_quantity' => (int) $row['system_quantity'],
                    'actual_quantity' => (int) $row['actual_quantity'],
                    'difference' => (int) $row['actual_quantity'] - (int) $row['system_quantity'],
                    'discrepancy_reason' => $row['discrepancy_reason'],
                    'recommended_action' => $row['recommended_action'],
                ];
            }
        }

        $notificationQuery = $pdo->prepare(
            'SELECT title, message, created_at
             FROM in_app_notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT 10'
        );
        $notificationQuery->execute(['user_id' => currentUser()['id']]);
        $notifications = $notificationQuery->fetchAll();
    } catch (Throwable $exception) {
        error_log('Inventory audit page could not load data: ' . $exception->getMessage());
        $pageError = 'Không thể tải dữ liệu kiểm kê. Vui lòng kiểm tra cơ sở dữ liệu và cấu trúc bảng.';
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';

$statusLabels = [
    'IN_PROGRESS' => ['Đang kiểm kê', 'secondary'],
    'WAITING_MANAGER' => ['Chờ Quản lý kho xác minh', 'info'],
    'WAITING_DIRECTOR' => ['Chờ Ban Giám đốc duyệt', 'warning'],
    'APPROVED' => ['Đã duyệt', 'success'],
    'BALANCED' => ['Đã duyệt', 'success'],
    'REJECTED' => ['Từ chối', 'danger'],
];
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded-3 border">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-clipboard-check text-warning me-2"></i>Phê Duyệt Biên Bản Kiểm Kê (FR-15)
            </h4>
            <div class="text-muted small">
                Quản lý kho xác minh kết quả trước khi Ban Giám đốc phê duyệt điều chỉnh tồn.
            </div>
        </div>
    </div>

    <?php if ($flashMessage !== null): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($notifications !== []): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="fa-regular fa-bell text-primary me-2"></i>Thông báo kiểm kê
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small class="text-muted"><?= htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <div class="small mt-1"><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($audits === [] && $pageError === ''): ?>
        <div class="alert alert-info">Hiện chưa có biên bản kiểm kê trong hệ thống.</div>
    <?php endif; ?>

    <?php foreach ($audits as $audit): ?>
        <?php
        $status = $statusLabels[$audit['status']] ?? ['Không xác định', 'secondary'];
        $canDecide = hasRole(ROLE_DIRECTOR) && $audit['status'] === 'WAITING_DIRECTOR';
        $previewLines = [];
        foreach ($audit['items'] as $item) {
            $previewLines[] = $item['sku'] . ': ' . number_format($item['system_quantity']) . ' → '
                . number_format($item['actual_quantity']) . ' ' . $item['unit'];
        }
        ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 <?= $audit['status'] === 'WAITING_DIRECTOR' ? 'border-warning' : 'border-secondary' ?>">
            <div class="card-body p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <span class="badge bg-light text-dark border mb-2"><?= htmlspecialchars($audit['audit_code'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge text-bg-<?= $status[1] ?> mb-2"><?= htmlspecialchars($status[0], ENT_QUOTES, 'UTF-8') ?></span>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($audit['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <div class="small text-muted">
                            <?= htmlspecialchars($audit['warehouse_code'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($audit['warehouse_name'], ENT_QUOTES, 'UTF-8') ?>
                            · Ngày kiểm kê <?= htmlspecialchars(date('d/m/Y', strtotime($audit['audit_date'])), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="small text-muted mt-1">
                            Người lập: <strong><?= htmlspecialchars($audit['creator_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            · Quản lý xác minh: <strong><?= htmlspecialchars($audit['verifier_name'] ?? 'Chưa xác minh', ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                    <?php if ($canDecide): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#approveAuditModal"
                                    data-audit-id="<?= $audit['id'] ?>"
                                    data-audit-code="<?= htmlspecialchars($audit['audit_code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-audit-preview="<?= htmlspecialchars(implode("\n", $previewLines), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-check me-1"></i>Phê duyệt
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectAuditModal"
                                    data-audit-id="<?= $audit['id'] ?>"
                                    data-audit-code="<?= htmlspecialchars($audit['audit_code'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-xmark me-1"></i>Từ chối
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã hàng</th>
                                <th>Tên hàng</th>
                                <th>Vị trí</th>
                                <th>Số lô</th>
                                <th class="text-center">Sổ sách</th>
                                <th class="text-center">Thực đếm</th>
                                <th class="text-center">Chênh lệch</th>
                                <th>Nguyên nhân / Đề xuất</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($audit['items'] === []): ?>
                                <tr><td colspan="8" class="text-center text-muted">Biên bản chưa có dòng hàng.</td></tr>
                            <?php else: ?>
                                <?php foreach ($audit['items'] as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><code><?= htmlspecialchars($item['location_code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td><?= htmlspecialchars($item['lot_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center fw-bold"><?= number_format($item['system_quantity']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center fw-bold text-primary"><?= number_format($item['actual_quantity']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center fw-bold <?= $item['difference'] === 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $item['difference'] > 0 ? '+' : '' ?><?= number_format($item['difference']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="small">
                                            <div><?= htmlspecialchars($item['discrepancy_reason'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if ($item['recommended_action'] !== null && $item['recommended_action'] !== ''): ?>
                                                <div class="text-muted mt-1"><strong>Đề xuất:</strong> <?= htmlspecialchars($item['recommended_action'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($audit['status'] === 'APPROVED' || $audit['status'] === 'BALANCED'): ?>
                    <div class="small text-success mt-3">
                        Đã duyệt bởi <?= htmlspecialchars($audit['approver_name'] ?? 'Ban Giám đốc', ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($audit['approved_at'] !== null): ?> · <?= htmlspecialchars($audit['approved_at'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    </div>
                <?php elseif ($audit['status'] === 'REJECTED'): ?>
                    <div class="small text-danger mt-3">
                        Từ chối bởi <?= htmlspecialchars($audit['rejector_name'] ?? 'Ban Giám đốc', ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($audit['rejected_at'] !== null): ?> · <?= htmlspecialchars($audit['rejected_at'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        <div><strong>Lý do:</strong> <?= htmlspecialchars($audit['rejection_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (hasRole(ROLE_DIRECTOR)): ?>
    <div class="modal fade" id="approveAuditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Xác nhận phê duyệt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Bạn xác nhận phê duyệt biên bản <strong id="approveAuditCode"></strong>?</p>
                        <div class="alert alert-warning mb-0">
                            <strong>Điều chỉnh tồn kho dự kiến</strong>
                            <pre id="approveAuditPreview" class="mb-0 mt-2 text-wrap"></pre>
                            <small>Nếu không có chênh lệch, tồn kho sẽ được giữ nguyên.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="audit_id" id="approveAuditId">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="submit" class="btn btn-success">Xác nhận</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectAuditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger">Từ chối biên bản kiểm kê</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Biên bản: <strong id="rejectAuditCode"></strong></p>
                        <label for="rejectionReason" class="form-label">Lý do từ chối <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="4" maxlength="2000" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="audit_id" id="rejectAuditId">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="submit" class="btn btn-danger">Xác nhận từ chối</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('approveAuditModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('approveAuditId').value = button.dataset.auditId;
            document.getElementById('approveAuditCode').textContent = button.dataset.auditCode;
            document.getElementById('approveAuditPreview').textContent = button.dataset.auditPreview || 'Không có chênh lệch tồn kho.';
        });

        document.getElementById('rejectAuditModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('rejectAuditId').value = button.dataset.auditId;
            document.getElementById('rejectAuditCode').textContent = button.dataset.auditCode;
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
