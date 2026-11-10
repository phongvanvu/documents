<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Khôi phục session từ cookie nếu cần
if (!isset($_SESSION['user_id']) && isset($_COOKIE['username'], $_COOKIE['user_id'])) {
    $_SESSION['user_name'] = $_COOKIE['username'];
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
}

// Kiểm tra quyền: Admin hoặc người có quyền documents.create
if (!isset($_SESSION['user_id']) || (!has_permission('documents.create') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập chức năng này.";
    header('Location: documents.php');
    exit;
}

// Xử lý tải xuống file CSV mẫu
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=mau_nhap_van_ban.csv');

    $out = fopen('php://output', 'w');
    // Ghi BOM UTF-8 để mở đúng tiếng Việt trên Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    // Tiêu đề cột
    fputcsv($out, [
        'So_ky_hieu', 
        'Loai_van_ban', 
        'Co_quan_ban_hanh', 
        'Ngay_ban_hanh (dd-mm-yyyy)', 
        'Ngay_hieu_luc (dd-mm-yyyy)', 
        'Ngay_het_han (dd-mm-yyyy)', 
        'Tinh_trang_hieu_luc', 
        'Trich_yeu_noi_dung'
    ]);

    // Dữ liệu mẫu minh họa
    fputcsv($out, [
        '01/2026/QĐ-UBND', 
        'Quyết định', 
        'UBND Tỉnh', 
        '15/01/2026', 
        '01/02/2026', 
        '', 
        'Còn hiệu lực', 
        'Về việc ban hành quy chế bảo vệ bí mật nhà nước trên địa bàn tỉnh'
    ]);
    fputcsv($out, [
        '45/2025/TT-BYT', 
        'Thông tư', 
        'Bộ Y tế', 
        '10/11/2025', 
        '01/01/2026', 
        '01/01/2030', 
        'Còn hiệu lực', 
        'Hướng dẫn quy chuẩn kỹ thuật quốc gia về trang thiết bị y tế'
    ]);

    fclose($out);
    exit;
}

$title = "Nhập dữ liệu văn bản từ Excel/CSV";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-file-import text-danger me-2"></i>Nhập Văn bản Hàng loạt từ Excel / CSV
                        </h4>
                        <div class="text-muted small">Số hóa nhanh hàng trăm văn bản, tự động phát hiện và đồng bộ Loại văn bản & Cơ quan ban hành mới</div>
                    </div>
                    <a href="import_documents.php?download_template=1" class="btn btn-outline-success btn-sm shadow-sm fw-semibold">
                        <i class="fas fa-file-download me-1"></i>Tải tệp CSV mẫu chuẩn UTF-8
                    </a>
                </div>

                <div class="row g-4">
                    <!-- KHỐI 1: FORM TẢI FILE IMPORT -->
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-upload text-danger me-2"></i>Tải lên tệp dữ liệu CSV
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <form id="formImportDoc" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold small text-dark">
                                            Chọn tệp CSV từ máy tính <span class="text-danger">*</span>
                                        </label>
                                        <input type="file" class="form-control form-control-lg" name="csv_file" id="inputCsvFile" accept=".csv, .txt" required>
                                        <div class="form-text mt-1 text-muted small">
                                            <i class="fas fa-info-circle me-1"></i>Chỉ chấp nhận tệp có định dạng <strong>.CSV</strong> bảng mã <strong>UTF-8</strong> để không bị lỗi dấu tiếng Việt.
                                        </div>
                                    </div>

                                    <div class="mb-4 p-3 bg-light rounded-3 border">
                                        <label class="form-label fw-bold small text-dark mb-2">
                                            <i class="fas fa-clone text-warning me-1"></i>Phương án xử lý khi gặp Số ký hiệu đã tồn tại:
                                        </label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input cursor-pointer" type="radio" name="duplicate_action" id="dupSkip" value="skip" checked>
                                            <label class="form-check-label cursor-pointer small" for="dupSkip">
                                                <strong>Bỏ qua (Khuyên dùng):</strong> Giữ nguyên văn bản hiện tại trong hệ thống và không ghi đè.
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="radio" name="duplicate_action" id="dupUpdate" value="update">
                                            <label class="form-check-label cursor-pointer small text-danger" for="dupUpdate">
                                                <strong>Cập nhật đè:</strong> Cập nhật lại trích yếu, ngày tháng và tình trạng hiệu lực từ file mới.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-danger fw-bold py-2 shadow-sm" id="btnSubmitImport">
                                            <i class="fas fa-play me-1"></i>Tiến hành Nhập dữ liệu ngay
                                        </button>
                                    </div>
                                </form>

                                <!-- KẾT QUẢ IMPORT -->
                                <div id="importResultBox" class="mt-4 d-none">
                                    <div class="alert alert-success border-start border-4 border-success shadow-sm rounded-3 p-3 mb-0">
                                        <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i>Hoàn tất quá trình nhập liệu!</h6>
                                        <ul class="mb-0 small">
                                            <li>Số văn bản thêm mới thành công: <strong id="resImported" class="text-success">0</strong></li>
                                            <li>Số văn bản cập nhật đè: <strong id="resUpdated" class="text-primary">0</strong></li>
                                            <li>Số văn bản trùng đã bỏ qua: <strong id="resSkipped" class="text-secondary">0</strong></li>
                                        </ul>
                                        <div class="mt-3">
                                            <a href="documents.php" class="btn btn-sm btn-success px-3"><i class="fas fa-list me-1"></i>Xem danh sách văn bản</a>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- KHỐI 2: HƯỚNG DẪN QUY CHUẨN CỘT -->
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-question-circle text-primary me-2"></i>Quy chuẩn Cột trong file Excel/CSV
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle small mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;" class="text-center">Cột</th>
                                                <th>Tên trường</th>
                                                <th>Mô tả & Ví dụ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-center fw-bold">A</td>
                                                <td>Số ký hiệu <span class="text-danger">*</span></td>
                                                <td><code>01/2026/QĐ-UBND</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">B</td>
                                                <td>Loại văn bản</td>
                                                <td>Quyết định, Thông tư... (Tự tạo nếu chưa có)</td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">C</td>
                                                <td>Cơ quan BH</td>
                                                <td>UBND Tỉnh, Bộ Y tế... (Tự tạo nếu chưa có)</td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">D</td>
                                                <td>Ngày ban hành</td>
                                                <td>Định dạng <code>dd/mm/yyyy</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">E</td>
                                                <td>Ngày hiệu lực</td>
                                                <td>Định dạng <code>dd/mm/yyyy</code></td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">F</td>
                                                <td>Ngày hết hạn</td>
                                                <td>Để trống nếu không có</td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">G</td>
                                                <td>Tình trạng</td>
                                                <td>Còn hiệu lực / Hết hiệu lực</td>
                                            </tr>
                                            <tr>
                                                <td class="text-center fw-bold">H</td>
                                                <td>Trích yếu <span class="text-danger">*</span></td>
                                                <td>Nội dung tóm tắt văn bản</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="alert alert-warning border-start border-3 border-warning shadow-sm rounded-3 mt-3 mb-0 p-2 small">
                                    <i class="fas fa-lightbulb text-warning me-1"></i>
                                    <strong>Mẹo nhỏ:</strong> Nếu soạn thảo trên Microsoft Excel, khi lưu hãy chọn <strong>"Save as type: CSV UTF-8 (Comma delimited) (*.csv)"</strong> để đảm bảo hiển thị đúng tiếng Việt.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formImportDoc');
    const btnSubmit = document.getElementById('btnSubmitImport');
    const resultBox = document.getElementById('importResultBox');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý dữ liệu...';
            resultBox.classList.add('d-none');

            const formData = new FormData(form);

            fetch('ajax_import_documents.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('resImported').innerText = data.data.imported;
                    document.getElementById('resUpdated').innerText = data.data.updated;
                    document.getElementById('resSkipped').innerText = data.data.skipped;
                    resultBox.classList.remove('d-none');
                    form.reset();

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Hoàn tất nhập dữ liệu!',
                            text: `Đã thêm mới ${data.data.imported}, cập nhật ${data.data.updated}, bỏ qua ${data.data.skipped} văn bản.`
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
                    } else {
                        alert(data.message);
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ trong quá trình nhập liệu!');
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-play me-1"></i>Tiến hành Nhập dữ liệu ngay';
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>