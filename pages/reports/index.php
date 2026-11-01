<?php
/**
 * FR-17: Báo cáo quản trị từ số liệu kho và chứng từ đã lưu.
 */
$pageTitle = 'Báo Cáo Quản Trị';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('reports');

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');
$dateFrom = is_string($_GET['from'] ?? null) ? $_GET['from'] : $defaultFrom;
$dateTo = is_string($_GET['to'] ?? null) ? $_GET['to'] : $defaultTo;
$pageError = '';
$report = [
    'summary' => null,
    'warehouse_rows' => [],
    'period' => null,
    'daily' => [],
    'period_movements' => [],
    'low_stock' => [],
    'expiring' => [],
    'overdue' => [],
    'top_products' => [],
];

$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
            ? $date
            : null;
};

$fromDate = $parseDate($dateFrom);
$toDate = $parseDate($dateTo);
if ($fromDate === null || $toDate === null || $fromDate > $toDate) {
    $pageError = 'Khoảng thời gian không hợp lệ. Hãy chọn ngày bắt đầu và ngày kết thúc hợp lệ.';
    $dateFrom = $defaultFrom;
    $dateTo = $defaultTo;
    $fromDate = $parseDate($dateFrom);
    $toDate = $parseDate($dateTo);
} elseif ($fromDate->diff($toDate)->days > 365) {
    $pageError = 'Khoảng thời gian báo cáo không được vượt quá 366 ngày.';
    $dateFrom = $defaultFrom;
    $dateTo = $defaultTo;
    $fromDate = $parseDate($dateFrom);
    $toDate = $parseDate($dateTo);
}

$pdo = Database::getConnection();
if ($pdo === null) {
    $pageError = 'Không thể kết nối cơ sở dữ liệu. Báo cáo cần MySQL đang hoạt động.';
} elseif ($pageError === '') {
    try {
        $report['summary'] = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE status = 'ACTIVE') AS active_skus,
                (SELECT COUNT(*) FROM inventory WHERE quantity > 0) AS stock_positions,
                (SELECT COALESCE(SUM(i.quantity * p.cost_price), 0)
                 FROM inventory i INNER JOIN products p ON p.id = i.product_id) AS inventory_value,
                (SELECT COUNT(*) FROM (
                    SELECT p.id
                    FROM products p
                    LEFT JOIN inventory i ON i.product_id = p.id
                    WHERE p.status = 'ACTIVE'
                    GROUP BY p.id, p.min_stock
                    HAVING COALESCE(SUM(i.quantity - i.reserved_quantity), 0) < p.min_stock
                ) low_stock_products) AS low_stock_skus,
                (SELECT COUNT(*) FROM (
                    SELECT l.id
                    FROM product_lots l
                    INNER JOIN inventory i ON i.lot_id = l.id AND i.quantity > 0
                    WHERE l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                    GROUP BY l.id
                ) expiring_lots) AS expiring_lots,
                (SELECT COUNT(DISTINCT l.id)
                 FROM product_lots l
                 INNER JOIN inventory i ON i.lot_id = l.id AND i.quantity > 0
                 WHERE l.expiry_date < CURDATE()) AS overdue_lots,
                (SELECT COUNT(*) FROM inventory_audits WHERE status = 'WAITING_DIRECTOR') AS pending_audits"
        )->fetch();

        $report['warehouse_rows'] = $pdo->query(
            'SELECT w.code, w.name,
                    COUNT(DISTINCT CASE WHEN i.quantity > 0 THEN i.id END) AS stock_positions,
                    COUNT(DISTINCT CASE WHEN i.quantity > 0 THEN i.product_id END) AS sku_count,
                    COALESCE(SUM(i.quantity * p.cost_price), 0) AS stock_value
             FROM warehouses w
             LEFT JOIN inventory i ON i.warehouse_id = w.id
             LEFT JOIN products p ON p.id = i.product_id
             GROUP BY w.id, w.code, w.name
             ORDER BY w.code'
        )->fetchAll();

        $periodQuery = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM stock_receipts
                 WHERE status = 'RECEIVED' AND COALESCE(receipt_date, created_at) >= :receipt_from
                   AND COALESCE(receipt_date, created_at) < DATE_ADD(:receipt_to, INTERVAL 1 DAY)) AS received_documents,
                (SELECT COUNT(*) FROM stock_issues
                 WHERE status = 'ISSUED' AND COALESCE(issue_date, created_at) >= :issue_from
                   AND COALESCE(issue_date, created_at) < DATE_ADD(:issue_to, INTERVAL 1 DAY)) AS issued_documents,
                (SELECT COUNT(*) FROM stock_transfers
                 WHERE status = 'COMPLETED' AND COALESCE(transfer_date, created_at) >= :transfer_from
                   AND COALESCE(transfer_date, created_at) < DATE_ADD(:transfer_to, INTERVAL 1 DAY)) AS completed_transfers,
                (SELECT COUNT(*) FROM inventory_audits
                 WHERE status IN ('APPROVED', 'BALANCED') AND approved_at >= :approved_from
                   AND approved_at < DATE_ADD(:approved_to, INTERVAL 1 DAY)) AS approved_audits,
                (SELECT COUNT(*) FROM inventory_audits
                 WHERE status = 'REJECTED' AND rejected_at >= :rejected_from
                   AND rejected_at < DATE_ADD(:rejected_to, INTERVAL 1 DAY)) AS rejected_audits,
                (SELECT COALESCE(SUM(ri.quantity * ri.unit_price), 0)
                 FROM stock_receipts r
                 INNER JOIN stock_receipt_items ri ON ri.stock_receipt_id = r.id
                 WHERE r.status = 'RECEIVED'
                   AND COALESCE(r.receipt_date, r.created_at) >= :received_value_from
                   AND COALESCE(r.receipt_date, r.created_at) < DATE_ADD(:received_value_to, INTERVAL 1 DAY)) AS received_value,
                (SELECT COALESCE(SUM(COALESCE(ii.actual_quantity, 0) * p.cost_price), 0)
                 FROM stock_issues si
                 INNER JOIN stock_issue_items ii ON ii.stock_issue_id = si.id
                 INNER JOIN products p ON p.id = ii.product_id
                 WHERE si.status = 'ISSUED'
                   AND COALESCE(si.issue_date, si.created_at) >= :issued_value_from
                   AND COALESCE(si.issue_date, si.created_at) < DATE_ADD(:issued_value_to, INTERVAL 1 DAY)) AS issued_cost_value"
        );
        $periodQuery->execute([
            'receipt_from' => $dateFrom,
            'receipt_to' => $dateTo,
            'issue_from' => $dateFrom,
            'issue_to' => $dateTo,
            'transfer_from' => $dateFrom,
            'transfer_to' => $dateTo,
            'approved_from' => $dateFrom,
            'approved_to' => $dateTo,
            'rejected_from' => $dateFrom,
            'rejected_to' => $dateTo,
            'received_value_from' => $dateFrom,
            'received_value_to' => $dateTo,
            'issued_value_from' => $dateFrom,
            'issued_value_to' => $dateTo,
        ]);
        $report['period'] = $periodQuery->fetch();

        $movementQuery = $pdo->prepare(
            "SELECT p.sku, p.name, p.unit,
                    SUM(m.received_quantity) AS received_quantity,
                    SUM(m.issued_quantity) AS issued_quantity,
                    SUM(m.received_value) AS received_value,
                    SUM(m.issued_cost_value) AS issued_cost_value
             FROM products p
             INNER JOIN (
                 SELECT ri.product_id,
                        SUM(ri.quantity) AS received_quantity,
                        0 AS issued_quantity,
                        SUM(ri.quantity * ri.unit_price) AS received_value,
                        0 AS issued_cost_value
                 FROM stock_receipts r
                 INNER JOIN stock_receipt_items ri ON ri.stock_receipt_id = r.id
                 WHERE r.status = 'RECEIVED'
                   AND COALESCE(r.receipt_date, r.created_at) >= :movement_receipt_from
                   AND COALESCE(r.receipt_date, r.created_at) < DATE_ADD(:movement_receipt_to, INTERVAL 1 DAY)
                 GROUP BY ri.product_id
                 UNION ALL
                 SELECT ii.product_id,
                        0 AS received_quantity,
                        SUM(COALESCE(ii.actual_quantity, 0)) AS issued_quantity,
                        0 AS received_value,
                        SUM(COALESCE(ii.actual_quantity, 0) * p_issue.cost_price) AS issued_cost_value
                 FROM stock_issues si
                 INNER JOIN stock_issue_items ii ON ii.stock_issue_id = si.id
                 INNER JOIN products p_issue ON p_issue.id = ii.product_id
                 WHERE si.status = 'ISSUED'
                   AND COALESCE(si.issue_date, si.created_at) >= :movement_issue_from
                   AND COALESCE(si.issue_date, si.created_at) < DATE_ADD(:movement_issue_to, INTERVAL 1 DAY)
                 GROUP BY ii.product_id
             ) m ON m.product_id = p.id
             GROUP BY p.id, p.sku, p.name, p.unit
             ORDER BY SUM(m.received_value + m.issued_cost_value) DESC, p.sku
             LIMIT 15"
        );
        $movementQuery->execute([
            'movement_receipt_from' => $dateFrom,
            'movement_receipt_to' => $dateTo,
            'movement_issue_from' => $dateFrom,
            'movement_issue_to' => $dateTo,
        ]);
        $report['period_movements'] = $movementQuery->fetchAll();

        $dailyQuery = $pdo->prepare(
            "SELECT activity_date, SUM(receipt_count) AS receipt_count, SUM(issue_count) AS issue_count
             FROM (
                 SELECT DATE(COALESCE(receipt_date, created_at)) AS activity_date,
                        COUNT(*) AS receipt_count, 0 AS issue_count
                 FROM stock_receipts
                 WHERE status = 'RECEIVED' AND COALESCE(receipt_date, created_at) >= :daily_receipt_from
                   AND COALESCE(receipt_date, created_at) < DATE_ADD(:daily_receipt_to, INTERVAL 1 DAY)
                 GROUP BY DATE(COALESCE(receipt_date, created_at))
                 UNION ALL
                 SELECT DATE(COALESCE(issue_date, created_at)) AS activity_date,
                        0 AS receipt_count, COUNT(*) AS issue_count
                 FROM stock_issues
                 WHERE status = 'ISSUED' AND COALESCE(issue_date, created_at) >= :daily_issue_from
                   AND COALESCE(issue_date, created_at) < DATE_ADD(:daily_issue_to, INTERVAL 1 DAY)
                 GROUP BY DATE(COALESCE(issue_date, created_at))
             ) movements
             GROUP BY activity_date
             ORDER BY activity_date"
        );
        $dailyQuery->execute([
            'daily_receipt_from' => $dateFrom,
            'daily_receipt_to' => $dateTo,
            'daily_issue_from' => $dateFrom,
            'daily_issue_to' => $dateTo,
        ]);
        $report['daily'] = $dailyQuery->fetchAll();

        $report['low_stock'] = $pdo->query(
            "SELECT p.sku, p.name, p.unit, p.min_stock,
                    COALESCE(SUM(i.quantity - i.reserved_quantity), 0) AS available_quantity
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.status = 'ACTIVE'
             GROUP BY p.id, p.sku, p.name, p.unit, p.min_stock
             HAVING available_quantity < p.min_stock
             ORDER BY available_quantity ASC, p.sku
             LIMIT 8"
        )->fetchAll();

        $report['expiring'] = $pdo->query(
            'SELECT p.sku, p.name, p.unit, l.lot_number, l.expiry_date,
                    SUM(i.quantity) AS quantity,
                    DATEDIFF(l.expiry_date, CURDATE()) AS days_remaining
             FROM product_lots l
             INNER JOIN products p ON p.id = l.product_id
             INNER JOIN inventory i ON i.lot_id = l.id AND i.quantity > 0
             WHERE l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
             GROUP BY l.id, p.sku, p.name, p.unit, l.lot_number, l.expiry_date
             ORDER BY l.expiry_date, p.sku
             LIMIT 8'
        )->fetchAll();

        $report['overdue'] = $pdo->query(
            'SELECT p.sku, p.name, p.unit, l.lot_number, l.expiry_date,
                    SUM(i.quantity) AS quantity,
                    DATEDIFF(CURDATE(), l.expiry_date) AS days_overdue
             FROM product_lots l
             INNER JOIN products p ON p.id = l.product_id
             INNER JOIN inventory i ON i.lot_id = l.id AND i.quantity > 0
             WHERE l.expiry_date < CURDATE()
             GROUP BY l.id, p.sku, p.name, p.unit, l.lot_number, l.expiry_date
             ORDER BY l.expiry_date, p.sku
             LIMIT 8'
        )->fetchAll();

        $report['top_products'] = $pdo->query(
            'SELECT p.sku, p.name, p.unit, SUM(i.quantity) AS quantity,
                    SUM(i.quantity * p.cost_price) AS stock_value
             FROM inventory i
             INNER JOIN products p ON p.id = i.product_id
             WHERE i.quantity > 0
             GROUP BY p.id, p.sku, p.name, p.unit
             ORDER BY stock_value DESC
             LIMIT 8'
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Executive report query failed: ' . $exception->getMessage());
        $pageError = 'Không thể tổng hợp báo cáo từ cơ sở dữ liệu. Vui lòng kiểm tra cấu trúc database.';
        $report = [
            'summary' => null,
            'warehouse_rows' => [],
            'period' => null,
            'daily' => [],
            'period_movements' => [],
            'low_stock' => [],
            'expiring' => [],
            'overdue' => [],
            'top_products' => [],
        ];
    }
}

$chartLabels = array_map(static function (array $row): string {
    return date('d/m', strtotime($row['activity_date']));
}, $report['daily']);
$chartReceipts = array_map('intval', array_column($report['daily'], 'receipt_count'));
$chartIssues = array_map('intval', array_column($report['daily'], 'issue_count'));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white p-3 rounded-3 border mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i>Báo Cáo Quản Trị (FR-17)</h4>
            <div class="text-muted small">Tồn kho hiện tại, chứng từ đã hoàn tất và biến động nhập/xuất theo từng SKU trong kỳ.</div>
        </div>
        <a href="<?= BASE_URL ?>/pages/audit_logs/" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-clock-rotate-left me-1"></i>Tra cứu nhật ký
        </a>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label for="reportFrom" class="form-label small fw-semibold">Từ ngày</label>
                <input type="date" id="reportFrom" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div>
                <label for="reportTo" class="form-label small fw-semibold">Đến ngày</label>
                <input type="date" id="reportTo" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i>Xem báo cáo</button>
            <span class="small text-muted">Tối đa 366 ngày. Tồn kho là ảnh chụp hiện tại; giá xuất dùng giá vốn hiện tại để ước tính.</span>
        </div>
    </form>

    <?php if ($report['summary'] !== null && $report['period'] !== null): ?>
        <?php $summary = $report['summary']; $period = $report['period']; ?>
        <h6 class="text-uppercase text-muted fw-bold small mb-3">Tồn kho hiện tại</h6>
        <div class="row g-3 mb-4">
            <?php
            $cards = [
                ['label' => 'SKU đang hoạt động', 'value' => number_format((int) $summary['active_skus']), 'icon' => 'fa-boxes-stacked', 'color' => 'primary'],
                ['label' => 'Vị trí đang có hàng', 'value' => number_format((int) $summary['stock_positions']), 'icon' => 'fa-location-dot', 'color' => 'success'],
                ['label' => 'Giá trị tồn theo giá vốn', 'value' => formatCurrency((float) $summary['inventory_value']), 'icon' => 'fa-coins', 'color' => 'info'],
                ['label' => 'SKU dưới mức tồn tối thiểu', 'value' => number_format((int) $summary['low_stock_skus']), 'icon' => 'fa-triangle-exclamation', 'color' => 'warning'],
                ['label' => 'Lô cận hạn trong 60 ngày', 'value' => number_format((int) $summary['expiring_lots']), 'icon' => 'fa-hourglass-half', 'color' => 'danger'],
                ['label' => 'Lô quá hạn còn tồn', 'value' => number_format((int) $summary['overdue_lots']), 'icon' => 'fa-calendar-xmark', 'color' => 'danger'],
                ['label' => 'Biên bản chờ Ban Giám đốc', 'value' => number_format((int) $summary['pending_audits']), 'icon' => 'fa-clipboard-check', 'color' => 'secondary'],
            ];
            ?>
            <?php foreach ($cards as $card): ?>
                <div class="col-sm-6 col-xl-4">
                    <div class="stat-card <?= $card['color'] ?> h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="stat-value mt-2"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="icon-box"><i class="fa-solid <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h6 class="text-uppercase text-muted fw-bold small mb-3">
            Chứng từ hoàn tất từ <?= htmlspecialchars(date('d/m/Y', strtotime($dateFrom)), ENT_QUOTES, 'UTF-8') ?>
            đến <?= htmlspecialchars(date('d/m/Y', strtotime($dateTo)), ENT_QUOTES, 'UTF-8') ?>
        </h6>
        <div class="row g-3 mb-4">
            <?php
            $periodCards = [
                ['label' => 'Phiếu nhập đã hoàn tất', 'value' => $period['received_documents'], 'icon' => 'fa-arrow-down', 'color' => 'primary'],
                ['label' => 'Phiếu xuất đã hoàn tất', 'value' => $period['issued_documents'], 'icon' => 'fa-arrow-up', 'color' => 'warning'],
                ['label' => 'Điều chuyển hoàn tất', 'value' => $period['completed_transfers'], 'icon' => 'fa-right-left', 'color' => 'info'],
                ['label' => 'Biên bản đã duyệt', 'value' => $period['approved_audits'], 'icon' => 'fa-check', 'color' => 'success'],
                ['label' => 'Biên bản bị từ chối', 'value' => $period['rejected_audits'], 'icon' => 'fa-xmark', 'color' => 'danger'],
            ];
            ?>
            <?php foreach ($periodCards as $card): ?>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body">
                        <div class="small text-muted"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="fs-3 fw-bold text-<?= $card['color'] ?> mt-2">
                            <i class="fa-solid <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= number_format((int) $card['value']) ?>
                        </div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ((int) $period['received_documents'] === 0
            && (int) $period['issued_documents'] === 0
            && (int) $period['completed_transfers'] === 0): ?>
            <div class="alert alert-info" role="status">
                Kỳ này chưa có phiếu nhập, xuất hoặc điều chuyển đã hoàn tất được lưu trong database.
                Các biểu mẫu nhập/xuất hiện chưa ghi giao dịch thật; vì vậy những chỉ số này là 0, không phải dữ liệu ước đoán.
            </div>
        <?php endif; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-muted">Giá trị hàng nhập theo đơn giá trên phiếu</div>
                    <div class="fs-4 fw-bold text-primary mt-2"><?= formatCurrency((float) $period['received_value']) ?></div>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-muted">Giá trị hàng xuất ước tính theo giá vốn hiện tại</div>
                    <div class="fs-4 fw-bold text-warning mt-2"><?= formatCurrency((float) $period['issued_cost_value']) ?></div>
                </div></div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Số chứng từ nhập/xuất theo ngày</div>
                    <div class="card-body">
                        <?php if ($report['daily'] === []): ?>
                            <div class="text-muted small">Chưa có phiếu nhập hoặc xuất hoàn tất trong khoảng thời gian này.</div>
                        <?php else: ?>
                            <canvas id="movementChart" height="120" aria-label="Biểu đồ chứng từ nhập và xuất"></canvas>
                        <?php endif; ?>
                        <div class="small text-muted mt-2">Biểu đồ đếm chứng từ hoàn tất. Số lượng chi tiết được nhóm theo SKU và đơn vị; điều chuyển hiện chỉ có đầu phiếu, chưa có chi tiết hàng.</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Giá trị tồn theo kho (giá vốn)</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Kho</th><th class="text-end">Vị trí</th><th class="text-end">SKU</th><th class="text-end">Giá trị tồn</th></tr></thead>
                            <tbody>
                                <?php foreach ($report['warehouse_rows'] as $warehouse): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($warehouse['code'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                        <td class="text-end"><?= number_format((int) $warehouse['stock_positions']) ?></td>
                                        <td class="text-end"><?= number_format((int) $warehouse['sku_count']) ?></td>
                                        <td class="text-end"><?= formatCurrency((float) $warehouse['stock_value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($report['warehouse_rows'] === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted">Chưa có dữ liệu kho.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Nhập / xuất theo mặt hàng trong kỳ</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>SKU</th><th>Mặt hàng</th><th class="text-end">Đã nhập</th><th class="text-end">Đã xuất</th><th class="text-end">Giá trị nhập</th><th class="text-end">Chi phí xuất ước tính</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['period_movements'] as $movement): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($movement['sku'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($movement['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end"><?= number_format((int) $movement['received_quantity']) ?> <?= htmlspecialchars($movement['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end"><?= number_format((int) $movement['issued_quantity']) ?> <?= htmlspecialchars($movement['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end"><?= formatCurrency((float) $movement['received_value']) ?></td>
                                <td class="text-end"><?= formatCurrency((float) $movement['issued_cost_value']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($report['period_movements'] === []): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Chưa có phiếu nhập/xuất đã hoàn tất có dòng hàng trong kỳ.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">SKU dưới mức tồn tối thiểu</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>SKU</th><th>Mặt hàng</th><th class="text-end">Khả dụng</th><th class="text-end">Tối thiểu</th></tr></thead>
                            <tbody>
                                <?php foreach ($report['low_stock'] as $item): ?>
                                    <tr><td><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end text-danger fw-semibold"><?= number_format((int) $item['available_quantity']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= number_format((int) $item['min_stock']) ?></td></tr>
                                <?php endforeach; ?>
                                <?php if ($report['low_stock'] === []): ?><tr><td colspan="4" class="text-center text-success">Không có SKU dưới mức tồn tối thiểu.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Lô hàng cận hạn (60 ngày)</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>SKU / Số lô</th><th class="text-end">Tồn</th><th>Hạn dùng</th><th class="text-end">Còn ngày</th></tr></thead>
                            <tbody>
                                <?php foreach ($report['expiring'] as $lot): ?>
                                    <tr><td><strong><?= htmlspecialchars($lot['sku'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars($lot['lot_number'], ENT_QUOTES, 'UTF-8') ?></div></td><td class="text-end"><?= number_format((int) $lot['quantity']) ?> <?= htmlspecialchars($lot['unit'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(date('d/m/Y', strtotime($lot['expiry_date'])), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end text-warning fw-semibold"><?= number_format((int) $lot['days_remaining']) ?></td></tr>
                                <?php endforeach; ?>
                                <?php if ($report['expiring'] === []): ?><tr><td colspan="4" class="text-center text-success">Không có lô sắp hết hạn trong 60 ngày.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold text-danger">Lô đã quá hạn nhưng vẫn còn tồn</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>SKU / Số lô</th><th class="text-end">Tồn</th><th>Hạn dùng</th><th class="text-end">Quá hạn</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['overdue'] as $lot): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($lot['sku'], ENT_QUOTES, 'UTF-8') ?></strong><div class="small text-muted"><?= htmlspecialchars($lot['lot_number'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                <td class="text-end"><?= number_format((int) $lot['quantity']) ?> <?= htmlspecialchars($lot['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($lot['expiry_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end text-danger fw-semibold"><?= number_format((int) $lot['days_overdue']) ?> ngày</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($report['overdue'] === []): ?>
                            <tr><td colspan="4" class="text-center text-success py-3">Không có lô quá hạn còn tồn.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Top mặt hàng theo giá trị tồn</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>SKU</th><th>Mặt hàng</th><th class="text-end">Tồn hiện tại</th><th class="text-end">Giá trị tồn</th></tr></thead>
                    <tbody>
                        <?php foreach ($report['top_products'] as $item): ?>
                            <tr><td><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= number_format((int) $item['quantity']) ?> <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end fw-semibold"><?= formatCurrency((float) $item['stock_value']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($report['top_products'] === []): ?><tr><td colspan="4" class="text-center text-muted">Chưa có tồn kho để thống kê.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($report['daily'] !== []): ?>
    <script>
        new Chart(document.getElementById('movementChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                datasets: [
                    { label: 'Phiếu nhập hoàn tất', data: <?= json_encode($chartReceipts) ?>, backgroundColor: '#2563eb' },
                    { label: 'Phiếu xuất hoàn tất', data: <?= json_encode($chartIssues) ?>, backgroundColor: '#f59e0b' }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
