
<?php
$pageTitle = 'Lập kế hoạch sản xuất';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

requirePageAccess('production_planning');

$materials = [];
$finishedProducts = [];
$dbError = null;

try {
    $pdo = Database::getConnection();

    if (!$pdo) {
        throw new RuntimeException('Không thể kết nối database.');
    }

    $sql = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.unit,
            COALESCE(SUM(i.quantity), 0) AS stock,
            COALESCE(SUM(i.reserved_quantity), 0) AS reserved,
            COALESCE(
                SUM(i.quantity - i.reserved_quantity),
                0
            ) AS available
        FROM products p
        LEFT JOIN inventory i
            ON i.product_id = p.id
        WHERE p.type = 'RAW_MATERIAL'
        GROUP BY p.id, p.sku, p.name, p.unit
        ORDER BY p.id
    ";

    $materials = $pdo->query($sql)->fetchAll();
    $finishedProducts = $pdo->query("
    SELECT id, sku, name, unit
    FROM products
    WHERE type = 'FINISHED_GOODS'
      AND status = 'ACTIVE'
    ORDER BY name
")->fetchAll();

} catch (Throwable $e) {
    error_log($e->getMessage());
    $dbError = 'Không thể tải dữ liệu nguyên liệu từ MySQL.';
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white border rounded-3 p-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="fa-solid fa-calendar-days text-primary me-2"></i>Lập kế hoạch sản xuất</h4>
      <div class="text-muted small">Tạo kế hoạch, theo dõi tiến độ và kiểm tra nguyên liệu (dữ liệu minh họa).</div>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal"><i class="fa-solid fa-plus me-1"></i>Tạo kế hoạch</button>
  </div>
  <div class="alert alert-info small">
    Kế hoạch được lưu trong MySQL. Tồn kho nguyên liệu được đọc từ database.
    Chức năng tính định mức nguyên liệu đang được hoàn thiện.
</div>
  
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        Tồn kho nguyên liệu từ MySQL
    </div>

    <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mã nguyên liệu</th>
                    <th>Tên nguyên liệu</th>
                    <th>Tồn kho</th>
                    <th>Đã giữ</th>
                    <th>Khả dụng</th>
                    <th>Đơn vị</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($dbError): ?>
                    <tr>
                        <td colspan="6" class="text-danger text-center">
                            <?= htmlspecialchars($dbError) ?>
                        </td>
                    </tr>
                <?php elseif (empty($materials)): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            Chưa có dữ liệu nguyên liệu.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $material): ?>
                        <tr>
                            <td><?= htmlspecialchars($material['sku']) ?></td>
                            <td><?= htmlspecialchars($material['name']) ?></td>
                            <td><?= number_format((float)$material['stock'], 0, ',', '.') ?></td>
                            <td><?= number_format((float)$material['reserved'], 0, ',', '.') ?></td>
                            <td class="fw-bold">
                                <?= number_format((float)$material['available'], 0, ',', '.') ?>
                            </td>
                            <td><?= htmlspecialchars($material['unit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Danh sách kế hoạch sản xuất</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Mã kế hoạch</th><th>Sản phẩm</th><th>Số lượng (thùng)</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody id="plansBody"></tbody>
      </table>
    </div>
  </div>
</div>
<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tạo kế hoạch sản xuất</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
    <form id="planForm">
    <?= csrfField() ?>
      <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="product">Sản phẩm *</label>
<select class="form-select" id="product" required>
    <option value="">-- Chọn thành phẩm --</option>
    <?php foreach ($finishedProducts as $product): ?>
        <option value="<?= (int) $product['id'] ?>">
            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
</select>
</div>
        <div class="col-md-6"><label class="form-label" for="quantity">Số lượng (thùng) *</label><input class="form-control" type="number" id="quantity" min="1" max="1000000" step="1" required></div>
        <div class="col-md-6"><label class="form-label" for="startDate">Ngày bắt đầu *</label><input class="form-control" type="date" id="startDate" required></div>
        <div class="col-md-6"><label class="form-label" for="endDate">Ngày kết thúc *</label><input class="form-control" type="date" id="endDate" required></div>
        <div class="col-12"><label class="form-label" for="note">Ghi chú</label><textarea class="form-control" id="note" rows="2" maxlength="500"></textarea></div>
      </div><div id="formError" class="text-danger small mt-3" role="alert"></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary">Lưu kế hoạch</button></div>
    </form>
  </div></div>
</div>
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết kế hoạch</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
    <div class="modal-body" id="detailContent"></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
  </div></div>
</div>

<script>
(() => {
    'use strict';

    const $ = id => document.getElementById(id);

    const escapeHtml = value =>
        String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));

    const formatNumber = n =>
        Number(n).toLocaleString('vi-VN');

    const formatDate = date => {
        const parts = String(date ?? '').split('-');
        return parts.length === 3
            ? `${parts[2]}/${parts[1]}/${parts[0]}`
            : escapeHtml(date);
    };

    let plans = [];

    function render() {
        $('plansBody').innerHTML = plans.length
            ? plans.map(p => `
                <tr>
                    <td>${escapeHtml(p.plan_code)}</td>
                    <td>${escapeHtml(p.product_name)}</td>
                    <td>${formatNumber(p.quantity)}</td>
                    <td>${formatDate(p.start_date)}</td>
                    <td>${formatDate(p.end_date)}</td>
                    <td>
                        <span class="badge bg-secondary">
                            ${escapeHtml(p.status)}
                        </span>
                    </td>
                    <td>
                        <button type="button"
                            class="btn btn-sm btn-outline-primary view-plan"
                            data-id="${Number(p.id)}">
                            Xem
                        </button>
                    </td>
                </tr>
            `).join('')
            : `<tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Chưa có kế hoạch trong MySQL.
                </td>
               </tr>`;
    }

    async function loadPlans() {
        const response = await fetch('api.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Không thể tải kế hoạch.');
        }

        plans = result.plans;
        render();
    }

    $('planForm').addEventListener('submit', async event => {
        event.preventDefault();

        $('formError').textContent = '';

        const productId = Number($('product').value);
        const quantity = Number($('quantity').value);
        const start = $('startDate').value;
        const end = $('endDate').value;

        if (
            !Number.isSafeInteger(productId) ||
            productId < 1 ||
            !Number.isSafeInteger(quantity) ||
            quantity < 1 ||
            quantity > 1000000 ||
            !start ||
            !end ||
            end < start
        ) {
            $('formError').textContent =
                'Vui lòng nhập sản phẩm, số lượng và ngày hợp lệ.';
            return;
        }

        const formData = new FormData();

        formData.append('product_id', String(productId));
        formData.append('quantity', String(quantity));
        formData.append('start_date', start);
        formData.append('end_date', end);
        formData.append('note', $('note').value.trim());

        formData.append(
            'csrf_token',
            $('planForm').querySelector('[name="csrf_token"]').value
        );

        const submitButton =
            $('planForm').querySelector('[type="submit"]');

        submitButton.disabled = true;

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Không thể lưu kế hoạch.');
            }

            await loadPlans();

            $('planForm').reset();

            bootstrap.Modal
                .getOrCreateInstance($('planModal'))
                .hide();

        } catch (error) {
            $('formError').textContent = error.message;
        } finally {
            submitButton.disabled = false;
        }
    });

    $('plansBody').addEventListener('click', event => {
        const button = event.target.closest('.view-plan');

        if (!button) return;

        const plan = plans.find(
            p => Number(p.id) === Number(button.dataset.id)
        );

        if (!plan) return;

        $('detailContent').innerHTML = `
            <p><strong>Mã kế hoạch:</strong>
                ${escapeHtml(plan.plan_code)}</p>
            <p><strong>Sản phẩm:</strong>
                ${escapeHtml(plan.product_name)}</p>
            <p><strong>Số lượng:</strong>
                ${formatNumber(plan.quantity)} thùng</p>
            <p><strong>Ngày bắt đầu:</strong>
                ${formatDate(plan.start_date)}</p>
            <p><strong>Ngày kết thúc:</strong>
                ${formatDate(plan.end_date)}</p>
            <p><strong>Trạng thái:</strong>
                ${escapeHtml(plan.status)}</p>
            <p><strong>Ghi chú:</strong>
                ${escapeHtml(plan.note || 'Không có')}</p>
            <p class="text-muted small">
                Chưa có định mức nguyên liệu để tính đủ hoặc thiếu.
            </p>
        `;

        bootstrap.Modal
            .getOrCreateInstance($('detailModal'))
            .show();
    });

    loadPlans().catch(error => {
        $('plansBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-danger text-center py-4">
                    ${escapeHtml(error.message)}
                </td>
            </tr>
        `;
    });
})();
</script>


<?php include __DIR__ . '/../../includes/footer.php'; ?>