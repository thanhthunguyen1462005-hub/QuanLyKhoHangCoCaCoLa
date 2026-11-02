
<?php
$pageTitle = 'Lập phiếu yêu cầu nhận nguyên liệu';

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

requirePageAccess('material_requests');

$plans = [];
$materialsByPlan = [];
$error = null;

try {
    $pdo = Database::getConnection();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Không kết nối được MySQL.');
    }

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Chỉ hiển thị KHSX chưa có phiếu đang hiệu lực.
    $stmt = $pdo->query("
        SELECT
            pp.id,
            pp.plan_code,
            pp.product_id,
            p.sku AS product_sku,
            p.name AS product_name,
            p.unit AS product_unit,
            pp.quantity,
            pp.start_date,
            pp.end_date,
            pp.status,
            pp.note
        FROM production_plans pp
        JOIN products p ON p.id = pp.product_id
        WHERE pp.status = 'PLANNED'
          AND NOT EXISTS (
              SELECT 1
              FROM material_requests mr
              WHERE mr.production_plan_id = pp.id
                AND mr.status IN (
                    'PENDING',
                    'APPROVED',
                    'FULFILLED'
                )
          )
        ORDER BY pp.id DESC
    ");

    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy BOM và tồn khả dụng.
    $stmt = $pdo->query("
        SELECT
            pp.id AS plan_id,
            b.material_id,
            m.sku AS material_sku,
            m.name AS material_name,
            m.unit,
            b.quantity_per_unit,
            (
                pp.quantity * b.quantity_per_unit
            ) AS quantity_required,
            COALESCE(stock.available, 0) AS available
        FROM production_plans pp
        JOIN production_bom b
            ON b.product_id = pp.product_id
        JOIN products m
            ON m.id = b.material_id
           AND m.type = 'RAW_MATERIAL'
        LEFT JOIN (
            SELECT
                product_id,
                SUM(quantity - reserved_quantity) AS available
            FROM inventory
            GROUP BY product_id
        ) stock
            ON stock.product_id = b.material_id
        WHERE pp.status = 'PLANNED'
        ORDER BY pp.id DESC, b.material_id
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $materialsByPlan[(string) $item['plan_id']][] = $item;
    }

} catch (Throwable $e) {
    error_log(
        '[MATERIAL_REQUEST_PAGE] ' . $e->getMessage()
    );
    $error = 'Không thể tải dữ liệu KHSX. Kiểm tra database.';
}

$jsonFlags = JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;

$plansJson = json_encode($plans, $jsonFlags) ?: '[]';
$materialsJson = json_encode(
    $materialsByPlan,
    $jsonFlags
) ?: '{}';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-container">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-file-circle-plus text-danger me-2"></i>
                Lập phiếu yêu cầu nhận nguyên liệu
            </h4>
            <p class="text-muted mb-0">
                Tự động lấy nguyên liệu theo kế hoạch sản xuất.
            </p>
        </div>

        <span class="badge bg-danger px-3 py-2">
            Xưởng sản xuất
        </span>
    </div>

    <div id="resultMessage" role="status" aria-live="polite"></div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form id="requestForm">
        <?= csrfField() ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-calendar-check text-danger me-2"></i>
                    Chọn kế hoạch sản xuất
                </h6>
            </div>

            <div class="card-body">
                <label for="planSelect" class="form-label fw-semibold">
                    Kế hoạch sản xuất
                    <span class="text-danger">*</span>
                </label>

                <select
                    id="planSelect"
                    name="production_plan_id"
                    class="form-select"
                    required
                    <?= empty($plans) ? 'disabled' : '' ?>
                >
                    <option value="">-- Chọn kế hoạch sản xuất --</option>

                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>">
                            <?= htmlspecialchars(
                                $plan['plan_code'] . ' - ' . $plan['product_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (!$plans && !$error): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        Không có KHSX đang chờ lập phiếu.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="planDetails" class="d-none">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">
                        Thông tin kế hoạch sản xuất
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Mã KHSX</label>
                            <input id="planCode" class="form-control" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Mã thành phẩm</label>
                            <input id="productSku" class="form-control" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Trạng thái KHSX</label>
                            <input id="planStatus" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tên thành phẩm</label>
                            <input id="productName" class="form-control" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Số lượng sản xuất</label>
                            <input id="planQuantity" class="form-control" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Đơn vị</label>
                            <input id="productUnit" class="form-control" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input id="startDate" class="form-control" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Ngày kết thúc</label>
                            <input id="endDate" class="form-control" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Ngày lập phiếu</label>
                            <input
                                class="form-control"
                                value="<?= date('d/m/Y') ?>"
                                readonly
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label">Ghi chú KHSX</label>
                            <textarea
                                id="planNote"
                                class="form-control"
                                rows="2"
                                readonly
                            ></textarea>
                        </div>

                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-boxes-stacked text-danger me-2"></i>
                        Chi tiết nguyên liệu theo KHSX
                    </h6>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Mã NL</th>
                                    <th>Tên nguyên liệu</th>
                                    <th>Định mức / TP</th>
                                    <th>Số lượng cần</th>
                                    <th>ĐVT</th>
                                    <th>Tồn khả dụng</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody id="materialsBody"></tbody>
                        </table>
                    </div>

                    <div id="stockNotice"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">
                        Thông tin phiếu yêu cầu
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="neededDate" class="form-label">
                                Ngày cần nhận
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                id="neededDate"
                                name="needed_date"
                                class="form-control"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="priority" class="form-label">
                                Mức độ ưu tiên
                            </label>
                            <select
                                id="priority"
                                name="priority"
                                class="form-select"
                            >
                                <option value="NORMAL">Bình thường</option>
                                <option value="HIGH">Cao</option>
                                <option value="URGENT">Khẩn cấp</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="requestNote" class="form-label">
                                Ghi chú yêu cầu
                            </label>
                            <textarea
                                id="requestNote"
                                name="note"
                                class="form-control"
                                rows="3"
                                maxlength="1000"
                                placeholder="Ghi chú gửi bộ phận kho..."
                            ></textarea>
                        </div>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">

                <button
                    type="button"
                    id="clearBtn"
                    class="btn btn-outline-secondary"
                >
                    <i class="fa-solid fa-rotate-left me-1"></i>
                    Chọn lại
                </button>

                <button
                    type="button"
                    id="previewBtn"
                    class="btn btn-outline-primary"
                >
                    <i class="fa-solid fa-eye me-1"></i>
                    Xem trước
                </button>

                <button
                    type="submit"
                    id="submitBtn"
                    class="btn btn-success"
                >
                    <i class="fa-solid fa-paper-plane me-1"></i>
                    Gửi yêu cầu
                </button>

            </div>
        </div>
    </form>
</div>

<div
    class="modal fade"
    id="previewModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    Xem trước phiếu yêu cầu nhận nguyên liệu
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Đóng"
                ></button>
            </div>

            <div class="modal-body" id="previewContent"></div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Đóng
                </button>

                <button
                    type="button"
                    id="modalSubmitBtn"
                    class="btn btn-success"
                >
                    <i class="fa-solid fa-paper-plane me-1"></i>
                    Xác nhận gửi yêu cầu
                </button>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const plans = <?= $plansJson ?>;
    const materialsByPlan = <?= $materialsJson ?>;

    const planMap = new Map(
        plans.map(plan => [String(plan.id), plan])
    );

    const form = document.getElementById('requestForm');
    const planSelect = document.getElementById('planSelect');
    const planDetails = document.getElementById('planDetails');
    const materialsBody = document.getElementById('materialsBody');
    const submitBtn = document.getElementById('submitBtn');
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');
    const resultMessage = document.getElementById('resultMessage');

    let submitting = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 3
        }).format(Number(value) || 0);
    }

    function formatDate(value) {
        if (!value) return '---';

        const parts = String(value).slice(0, 10).split('-');

        return parts.length === 3
            ? `${parts[2]}/${parts[1]}/${parts[0]}`
            : value;
    }

    function setField(id, value) {
        document.getElementById(id).value = value ?? '';
    }

    function selectedPlan() {
        return planMap.get(String(planSelect.value)) || null;
    }

    function selectedItems() {
        return materialsByPlan[String(planSelect.value)] || [];
    }

    function renderPlan() {
        const plan = selectedPlan();

        if (!plan) {
            planDetails.classList.add('d-none');
            materialsBody.innerHTML = '';
            return;
        }

        planDetails.classList.remove('d-none');

        setField('planCode', plan.plan_code);
        setField('productSku', plan.product_sku);
        setField('productName', plan.product_name);
        setField('productUnit', plan.product_unit);
        setField('planQuantity', formatNumber(plan.quantity));
        setField('planStatus', plan.status);
        setField('startDate', formatDate(plan.start_date));
        setField('endDate', formatDate(plan.end_date));
        setField('planNote', plan.note);

        const items = selectedItems();
        let shortages = 0;

        if (items.length === 0) {
            materialsBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        KHSX này chưa có nguyên liệu trong BOM.
                    </td>
                </tr>
            `;
        } else {
            materialsBody.innerHTML = items.map((item, index) => {
                const required = Number(item.quantity_required);
                const available = Number(item.available);
                const enough = available >= required;

                if (!enough) shortages++;

                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(item.material_sku)}</td>
                        <td>${escapeHtml(item.material_name)}</td>
                        <td class="text-end">
                            ${formatNumber(item.quantity_per_unit)}
                        </td>
                        <td class="text-end fw-bold">
                            ${formatNumber(required)}
                        </td>
                        <td>${escapeHtml(item.unit)}</td>
                        <td class="text-end">
                            ${formatNumber(available)}
                        </td>
                        <td>
                            <span class="badge ${enough ? 'bg-success' : 'bg-warning text-dark'}">
                                ${enough ? 'Đủ tồn' : 'Thiếu tồn'}
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        document.getElementById('stockNotice').innerHTML =
            shortages > 0
                ? `<div class="alert alert-warning mt-3 mb-0">
                    Có ${shortages} nguyên liệu thiếu tồn khả dụng.
                    Phiếu vẫn có thể gửi để kho kiểm tra.
                   </div>`
                : '';
    }

    function validateRequest() {
        if (!selectedPlan()) {
            alert('Vui lòng chọn KHSX.');
            return false;
        }

        if (selectedItems().length === 0) {
            alert('KHSX chưa có nguyên liệu trong BOM.');
            return false;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }

        return true;
    }

    function setSubmitting(value) {
        submitting = value;

        submitBtn.disabled = value;
        modalSubmitBtn.disabled = value;

        submitBtn.innerHTML = value
            ? '<i class="fa-solid fa-spinner fa-spin me-1"></i> Đang gửi...'
            : '<i class="fa-solid fa-paper-plane me-1"></i> Gửi yêu cầu';

        modalSubmitBtn.textContent = value
            ? 'Đang gửi...'
            : 'Xác nhận gửi yêu cầu';
    }

    async function submitRequest() {
        if (submitting || !validateRequest()) {
            return;
        }

        setSubmitting(true);
        resultMessage.innerHTML = '';

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const raw = await response.text();

            let data;

            try {
                data = JSON.parse(raw);
            } catch (error) {
                throw new Error(
                    'API không trả về JSON hợp lệ. Kiểm tra lỗi PHP trong api.php.'
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Không thể gửi yêu cầu.'
                );
            }

            const modalElement = document.getElementById('previewModal');

            if (typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) modal.hide();
            }

            resultMessage.innerHTML = `
                <div class="alert alert-success">
                    <strong>Gửi yêu cầu thành công!</strong>
                    <div>
                        Mã phiếu:
                        <strong>${escapeHtml(data.request_code)}</strong>
                    </div>
                    <div>
                        Số nguyên liệu:
                        ${Number(data.material_count) || 0}
                    </div>
                    <div>Trạng thái: Chờ xử lý (PENDING).</div>
                </div>
            `;

            const option = planSelect.selectedOptions[0];

            if (option && option.value) {
                option.remove();
            }

            planSelect.value = '';
            renderPlan();

            resultMessage.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } catch (error) {
            resultMessage.innerHTML = `
                <div class="alert alert-danger">
                    ${escapeHtml(error.message)}
                </div>
            `;
        } finally {
            setSubmitting(false);
        }
    }

    planSelect.addEventListener('change', renderPlan);

    document.getElementById('clearBtn').addEventListener('click', function () {
        planSelect.value = '';
        renderPlan();
    });

    document.getElementById('previewBtn').addEventListener('click', function () {
        if (!validateRequest()) return;

        const plan = selectedPlan();
        const items = selectedItems();

        const rows = items.map((item, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(item.material_sku)}</td>
                <td>${escapeHtml(item.material_name)}</td>
                <td class="text-end">
                    ${formatNumber(item.quantity_required)}
                </td>
                <td>${escapeHtml(item.unit)}</td>
            </tr>
        `).join('');

        document.getElementById('previewContent').innerHTML = `
            <p>
                <strong>Mã KHSX:</strong>
                ${escapeHtml(plan.plan_code)}
            </p>
            <p>
                <strong>Thành phẩm:</strong>
                ${escapeHtml(plan.product_name)}
            </p>
            <p>
                <strong>Số lượng sản xuất:</strong>
                ${formatNumber(plan.quantity)}
                ${escapeHtml(plan.product_unit)}
            </p>
            <p>
                <strong>Ngày cần nhận:</strong>
                ${formatDate(document.getElementById('neededDate').value)}
            </p>
            <p>
                <strong>Ghi chú:</strong>
                ${escapeHtml(
                    document.getElementById('requestNote').value || 'Không có'
                )}
            </p>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>STT</th>
                            <th>Mã NL</th>
                            <th>Tên nguyên liệu</th>
                            <th>SL cần nhận</th>
                            <th>ĐVT</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;

        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap JavaScript chưa được tải.');
            return;
        }

        bootstrap.Modal
            .getOrCreateInstance(
                document.getElementById('previewModal')
            )
            .show();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitRequest();
    });

    modalSubmitBtn.addEventListener('click', submitRequest);

});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
