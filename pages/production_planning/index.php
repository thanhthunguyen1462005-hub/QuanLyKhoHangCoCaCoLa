<?php
$pageTitle = 'Lập kế hoạch sản xuất';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
requirePageAccess('production_planning');
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
  <div class="alert alert-info small">Bản demo: kế hoạch lưu trên trình duyệt hiện tại; số tồn nguyên liệu là dữ liệu mẫu, không đồng bộ với kho thực tế.</div>
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
      <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="product">Sản phẩm *</label><select class="form-select" id="product" required><option value="coca500">Coca-Cola Original 500 ml (24 chai/thùng)</option><option value="coca330">Coca-Cola Original 330 ml (24 lon/thùng)</option></select></div>
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
  const storageKey = 'cocacola_wms_production_plans_v1';
  const products = {
    coca500: { name: 'Coca-Cola Original 500 ml (24 chai/thùng)', material: 'Chai PET 500 ml', unitsPerCase: 24 },
    coca330: { name: 'Coca-Cola Original 330 ml (24 lon/thùng)', material: 'Lon nhôm 330 ml', unitsPerCase: 24 }
  };
  // Số lượng tồn kho minh họa, không lấy từ cơ sở dữ liệu.
  const stock = { coca500: 12000, coca330: 8400 };
  const $ = (id) => document.getElementById(id);
  const escapeHtml = (value) => String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const formatNumber = (n) => Number(n).toLocaleString('vi-VN');
  const formatDate = (date) => { const parts = String(date).split('-'); return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : escapeHtml(date); };
  function loadPlans() {
    try { const parsed = JSON.parse(localStorage.getItem(storageKey) || '[]'); return Array.isArray(parsed) ? parsed : []; }
    catch { return []; }
  }
  let plans = loadPlans();
  function render() {
    $('plansBody').innerHTML = plans.length ? plans.map(p => `<tr>
      <td><button type="button" class="btn btn-link p-0 fw-semibold view-plan" data-id="${escapeHtml(p.id)}">${escapeHtml(p.code)}</button></td>
      <td>${escapeHtml(products[p.product]?.name || p.product)}</td>
      <td>${formatNumber(p.quantity)}</td><td>${formatDate(p.start)}</td><td>${formatDate(p.end)}</td>
      <td><span class="badge bg-secondary">Dự kiến</span></td>
      <td><button type="button" class="btn btn-sm btn-outline-primary view-plan" data-id="${escapeHtml(p.id)}">Xem</button></td>
    </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">Chưa có kế hoạch. Nhấn “Tạo kế hoạch” để bắt đầu.</td></tr>';
  }
  $('planForm').addEventListener('submit', (event) => {
    event.preventDefault();
    $('formError').textContent = '';
    const product = $('product').value;
    const quantity = Number($('quantity').value);
    const start = $('startDate').value;
    const end = $('endDate').value;
    if (!products[product] || !Number.isSafeInteger(quantity) || quantity < 1 || quantity > 1000000 || !start || !end || end < start) {
      $('formError').textContent = 'Vui lòng nhập số lượng hợp lệ và ngày kết thúc không trước ngày bắt đầu.';
      return;
    }
    const id = (globalThis.crypto && typeof crypto.randomUUID === 'function') ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const code = `KHSX-${new Date().getFullYear()}-${String(Date.now()).slice(-8)}`;
    const updated = [{ id, code, product, quantity, start, end, note: $('note').value.trim() }, ...plans];
    try { localStorage.setItem(storageKey, JSON.stringify(updated)); }
    catch { $('formError').textContent = 'Không thể lưu dữ liệu trong trình duyệt. Hãy kiểm tra chế độ riêng tư hoặc dung lượng lưu trữ.'; return; }
    plans = updated;
    render();
        $('planForm').reset();
    bootstrap.Modal.getOrCreateInstance($('planModal')).hide();
  });

  $('plansBody').addEventListener('click', (event) => {
    const button = event.target.closest('.view-plan');
    if (!button) return;

    const plan = plans.find(p => p.id === button.dataset.id);
    if (!plan) return;

    const product = products[plan.product];
    const required = plan.quantity * product.unitsPerCase;
    const available = stock[plan.product] || 0;

    $('detailContent').innerHTML = `
      <p><strong>Mã kế hoạch:</strong> ${escapeHtml(plan.code)}</p>
      <p><strong>Sản phẩm:</strong> ${escapeHtml(product.name)}</p>
      <p><strong>Số lượng:</strong> ${formatNumber(plan.quantity)} thùng</p>
      <p><strong>Ngày bắt đầu:</strong> ${formatDate(plan.start)}</p>
      <p><strong>Ngày kết thúc:</strong> ${formatDate(plan.end)}</p>
      <p><strong>Nguyên liệu:</strong> ${escapeHtml(product.material)}</p>
      <p><strong>Cần:</strong> ${formatNumber(required)}</p>
      <p><strong>Tồn kho mẫu:</strong> ${formatNumber(available)}</p>
      <p><strong>Đánh giá:</strong> ${available >= required ? 'Đủ nguyên liệu' : 'Thiếu nguyên liệu'}</p>
      <p><strong>Ghi chú:</strong> ${escapeHtml(plan.note || 'Không có')}</p>
    `;

    bootstrap.Modal.getOrCreateInstance($('detailModal')).show();
  });

  render();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>