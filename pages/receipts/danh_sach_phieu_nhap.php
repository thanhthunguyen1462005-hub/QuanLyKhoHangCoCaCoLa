<?php
$pageTitle = 'Lập Phiếu Nhập Kho Nguyên Liệu';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

requirePageAccess('receipts_raw');

$pdo = Database::getConnection();
if (!$pdo) {
    die('Không kết nối được database.');
}

$requests = $pdo->query("
    SELECT
        r.id,
        r.request_code,
        r.status,
        r.created_at,
        po.po_code,
        w.warehouse_code,
        q.qc_code,
        q.result AS qc_result
    FROM raw_material_inbound_requests r
    JOIN purchase_orders po ON po.id = r.po_id
    JOIN warehouses w ON w.id = r.warehouse_id
    JOIN qc_inspections q ON q.id = r.qc_inspection_id
    WHERE r.status = 'WAITING_RECEIPT'
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$receipts = $pdo->query("
    SELECT
        sr.id,
        sr.receipt_code,
        sr.reference_doc,
        sr.status,
        sr.created_at,
        w.warehouse_code
    FROM stock_receipts sr
    JOIN warehouses w ON w.id = sr.warehouse_id
    WHERE sr.receipt_type = 'RAW_MATERIAL'
    ORDER BY sr.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
    <h4 class="fw-bold mb-4">
        <i class="fa-solid fa-file-circle-plus me-2"></i>
        Lập Phiếu Nhập Kho Nguyên Liệu
    </h4>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Đã lập phiếu nhập kho thành công.
            Phiếu đang chờ phê duyệt.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Yêu cầu chờ lập phiếu nhập kho</h5>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Mã yêu cầu</th>
                        <th>Đơn mua hàng</th>
                        <th>Biên bản QC</th>
                        <th>Kết quả QC</th>
                        <th>Kho được chỉ định</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($r['request_code']) ?></td>
                            <td><?= h($r['po_code']) ?></td>
                            <td><?= h($r['qc_code']) ?></td>
                            <td><?= h($r['qc_result']) ?></td>
                            <td><?= h($r['warehouse_code']) ?></td>
                            <td>
                                <a class="btn btn-primary btn-sm" href="create.php?request_id=<?= (int) $r['id'] ?>">
                                    Lập phiếu
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$requests): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Không có yêu cầu chờ lập phiếu.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Danh sách phiếu nhập kho</h5>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Mã phiếu</th>
                        <th>Chứng từ</th>
                        <th>Kho nhập</th>
                        <th>Trạng thái</th>
                        <th>Ngày lập</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($r['receipt_code']) ?></td>
                            <td><?= h($r['reference_doc']) ?></td>
                            <td><?= h($r['warehouse_code']) ?></td>
                            <td>
                                <?php if ($r['status'] === 'PENDING'): ?>
                                    <span class="badge bg-warning text-dark">
                                        Chờ phê duyệt
                                    </span>
                                <?php elseif ($r['status'] === 'APPROVED'): ?>
                                    <span class="badge bg-success">Đã duyệt</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <?= h($r['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$receipts): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                Chưa có phiếu nhập kho.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>