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

// Kiểm tra quyền xem
if (!isset($_SESSION['user_id']) || (!has_permission('documents.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập chức năng này.";
    header('Location: documents.php');
    exit;
}

// Lấy danh mục phục vụ bộ lọc
$authorities = $conn->query("SELECT id, authority_name FROM issuing_authorities ORDER BY authority_name ASC")->fetch_all(MYSQLI_ASSOC);
$doc_types = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name ASC")->fetch_all(MYSQLI_ASSOC);

$title = "Xuất dữ liệu & Đóng gói tệp văn bản";
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
                            <i class="fas fa-file-archive text-danger me-2"></i>Xuất Báo Cáo & Đóng Gói Tệp Hồ Sơ
                        </h4>
                        <div class="text-muted small">Xuất sổ mục lục văn bản ra file Excel/CSV hoặc nén hàng loạt tệp đính kèm (.PDF, .Word) thành tệp ZIP</div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- KHỐI FORM LỌC & THIẾT LẬP XUẤT -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-filter text-danger me-2"></i>1. Thiết lập phạm vi dữ liệu cần xuất
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <form id="formExport" method="POST" action="ajax_export_action.php">
                                    <input type="hidden" name="action" id="exportAction" value="export_csv">

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Cơ quan ban hành</label>
                                            <select class="form-select" name="authority_id">
                                                <option value="0">-- Tất cả cơ quan ban hành --</option>
                                                <?php foreach ($authorities as $a): ?>
                                                    <option value="<?= (int)$a['id'] ?>"><?= h($a['authority_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Loại văn bản</label>
                                            <select class="form-select" name="type_id">
                                                <option value="0">-- Tất cả loại văn bản --</option>
                                                <?php foreach ($doc_types as $t): ?>
                                                    <option value="<?= (int)$t['id'] ?>"><?= h($t['type_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-dark">Tình trạng hiệu lực</label>
                                            <select class="form-select" name="status">
                                                <option value="">-- Tất cả trạng thái --</option>
                                                <option value="Còn hiệu lực">Còn hiệu lực</option>
                                                <option value="Hết hiệu lực">Hết hiệu lực</option>
                                                <option value="Hết hiệu lực một phần">Hết hiệu lực một phần</option>
                                                <option value="Chưa có hiệu lực">Chưa có hiệu lực</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-dark">Từ ngày ban hành</label>
                                            <input type="date" class="form-control" name="date_from">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-dark">Đến ngày ban hành</label>
                                            <input type="date" class="form-control" name="date_to">
                                        </div>
                                    </div>

                                    <div class="p-3 bg-light rounded-3 border mb-4">
                                        <div class="small text-muted">
                                            <i class="fas fa-info-circle text-primary me-1"></i>
                                            Nếu bạn để trống tất cả các tiêu chí lọc, hệ thống sẽ tự động xuất toàn bộ kho văn bản hiện hành trong cơ sở dữ liệu.
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2">
                                        <!-- Nút xuất CSV -->
                                        <button type="button" class="btn btn-outline-success fw-bold px-3 py-2 shadow-sm btn-submit-export" data-type="export_csv">
                                            <i class="fas fa-file-excel me-2"></i>Xuất Sổ mục lục (.CSV / Excel)
                                        </button>
                                        <!-- Nút đóng gói ZIP -->
                                        <button type="button" class="btn btn-danger fw-bold px-3 py-2 shadow-sm btn-submit-export" data-type="export_zip">
                                            <i class="fas fa-file-archive me-2"></i>Đóng gói tệp đính kèm (.ZIP)
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- KHỐI HƯỚNG DẪN & LƯU Ý -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-shield-alt text-primary me-2"></i>Lưu ý khi đóng gói hồ sơ
                                </h6>
                            </div>
                            <div class="card-body p-4 small">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-check-circle text-success me-1"></i>Định dạng Sổ mục lục</h6>
                                    <p class="text-muted mb-0">File CSV được tạo với bảng mã UTF-8 BOM, đảm bảo mở trực tiếp trên Microsoft Excel mà không bị vỡ font tiếng Việt.</p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-box-open text-primary me-1"></i>Cơ chế nén tệp ZIP</h6>
                                    <p class="text-muted mb-0">Mỗi tệp đính kèm đưa vào file nén được tự động đổi tên theo cú pháp <code>[Số ký hiệu]_[Mã ID].[đuôi file]</code>, giúp việc sắp xếp và đối chiếu văn bản rất nhanh chóng.</p>
                                </div>

                                <div class="mb-0">
                                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-tachometer-alt text-warning me-1"></i>Giới hạn đóng gói</h6>
                                    <p class="text-muted mb-0">Nhằm bảo đảm tốc độ và bộ nhớ máy chủ, chức năng nén ZIP cho phép tối đa <strong>500 tệp văn bản</strong> trên mỗi lượt xuất.</p>
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
    const form = document.getElementById('formExport');
    const actionInput = document.getElementById('exportAction');

    document.querySelectorAll('.btn-submit-export').forEach(btn => {
        btn.addEventListener('click', function() {
            const exportType = this.getAttribute('data-type');
            actionInput.value = exportType;
            form.submit();
        });
    });
});
</script>

<?php include 'footer.php'; ?>