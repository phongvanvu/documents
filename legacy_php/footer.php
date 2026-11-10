</div> <!-- Đóng thẻ div.wrapper từ header.php -->

<footer id="systemFooter" class="w-100 bg-white border-top py-3 mt-auto shadow-sm" style="display: none; z-index: 1000;">
    <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center px-4">
        <small class="text-muted mb-2 mb-md-0">
            &copy; <?= date('Y'); ?> <span class="fw-bold text-danger">phongvanvu</span>. Tất cả quyền được bảo lưu.
        </small>
        <small class="text-muted fw-semibold">
            Phiên bản hệ thống <span class="badge bg-danger rounded-pill ms-1 shadow-sm">1.0.0</span>
        </small>
    </div>
</footer>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Dọn dẹp bố cục Footer, chèn vào #content để chống đè lên Sidebar
    (function() {
        const contentDiv = document.getElementById("content");
        const footerEl = document.getElementById("systemFooter");
        
        if (contentDiv && footerEl) {
            contentDiv.classList.add("d-flex", "flex-column");
            contentDiv.appendChild(footerEl);
            footerEl.style.display = "block";
        }
    })();

    // 2. Khởi tạo tính năng UI toàn cục khi DOM sẵn sàng
    document.addEventListener("DOMContentLoaded", function() {
        // Init Bootstrap Tooltips
        if (typeof bootstrap !== 'undefined') {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));
            [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]')).map(el => new bootstrap.Popover(el));
        }

        // Tự động đóng các thẻ Alert sau 5 giây (trừ thẻ có class .alert-permanent)
        document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
            setTimeout(() => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                    const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                    if (bsAlert) bsAlert.close();
                } else {
                    alert.remove();
                }
            }, 5000);
        });
    });
</script>
</body>
</html>