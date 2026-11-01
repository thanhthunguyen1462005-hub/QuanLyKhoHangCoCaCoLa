/**
 * SMART-WMS Warehouse Management System Scripts
 * Hỗ trợ giao diện, thao tác nhanh, quét Barcode/QR và thống kê
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggle cho Mobile/Tablet
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('d-none');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.add('d-none');
        });
    }

    // 2. Kích hoạt Tooltips của Bootstrap 5
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 3. Giả lập quét mã vạch bằng máy quét Barcode Scanner (Bắt sự kiện Keydown Enter nhanh)
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();

    window.addEventListener('keydown', function (e) {
        // Tránh can thiệp khi đang gõ vào input thông thường trừ khi có class .barcode-input
        if (e.target.tagName === 'INPUT' && !e.target.classList.contains('barcode-input') && e.target.type !== 'search') {
            return;
        }

        const currentTime = Date.now();
        if (currentTime - lastKeyTime > 100) {
            barcodeBuffer = ''; // Reset buffer nếu gõ quá chậm (người gõ)
        }
        lastKeyTime = currentTime;

        if (e.key === 'Enter') {
            if (barcodeBuffer.length >= 6) {
                e.preventDefault();
                handleBarcodeScanned(barcodeBuffer);
                barcodeBuffer = '';
            }
        } else if (e.key.length === 1) {
            barcodeBuffer += e.key;
        }
    });

    function handleBarcodeScanned(barcode) {
        console.log("Đã nhận tín hiệu từ máy quét mã vạch:", barcode);
        showToast("Quét mã thành công", `Mã Barcode: ${barcode}`, "success");
        const searchInput = document.getElementById('globalQuickSearch');
        if (searchInput) {
            searchInput.value = barcode;
            searchInput.dispatchEvent(new Event('input'));
        }
    }
});

// Toast thông báo chuyên nghiệp
function showToast(title, message, type = 'info') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' :
        type === 'danger' ? 'bg-danger text-white' :
            type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElem = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastElem, { delay: 4000 });
    bsToast.show();

    toastElem.addEventListener('hidden.bs.toast', function () {
        toastElem.remove();
    });
}

