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

// Chỉ Quản trị viên (Admin) mới có quyền sửa cài đặt hệ thống
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang cấu hình hệ thống.";
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$user_id = (int)$_SESSION['user_id'];

// Xử lý lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settings = [
        'agency_name'           => trim($_POST['agency_name'] ?? ''),
        'parent_agency'         => trim($_POST['parent_agency'] ?? ''),
        'site_title'            => trim($_POST['site_title'] ?? ''),
        'watermark_text'        => trim($_POST['watermark_text'] ?? ''),
        'max_upload_size_mb'    => max(5, intval($_POST['max_upload_size_mb'] ?? 100)),
        'allowed_file_exts'     => strtolower(trim($_POST['allowed_file_exts'] ?? '')),
        'register_header_title' => trim($_POST['register_header_title'] ?? ''),
        'footer_copyright'      => trim($_POST['footer_copyright'] ?? '')
    ];

    try {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $k => $v) {
            $stmt->bind_param("ss", $k, $v);
            $stmt->execute();
        }
        $stmt->close();

        // Ghi nhật ký thao tác
        $log_act = "Cập nhật cấu hình và nhận diện thương hiệu hệ thống";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        $_SESSION['flash_msg'] = "Đã lưu toàn bộ cấu hình hệ thống thành công!";
    } catch (Exception $e) {
        $_SESSION['flash_msg_error'] = "Lỗi khi lưu cấu hình: " . $e->getMessage();
    }

    header('Location: system_settings.php');
    exit;
}

// Lấy danh sách cấu hình hiện tại
$current_settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $current_settings[$r['setting_key']] = $r['setting_value'];
    }
}

$title = "Cấu hình tham số hệ thống";
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
                            <i class="fas fa-sliders-h text-danger me-2"></i>Cấu hình Tham số Hệ thống
                        </h4>
                        <div class="text-muted small">Tùy chỉnh thông tin cơ quan, nhận diện thương hiệu, đóng dấu watermark và quy chuẩn tải tệp</div>
                    </div>
                </div>

                <form action="system_settings.php" method="POST">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="row g-4">
                        <!-- KHỐI 1: THÔNG TIN CƠ QUAN & THƯƠNG HIỆU -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                    <h6 class="fw-bold mb-0 text-danger">
                                        <i class="fas fa-building me-2"></i>1. Thông tin Cơ quan & Nhận diện
                                    </h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Cơ quan / Đơn vị chủ quản cấp trên</label>
                                        <input type="text" class="form-control" name="parent_agency" value="<?= h($current_settings['parent_agency'] ?? '') ?>" placeholder="VD: BỘ Y TẾ">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Tên Đơn vị sử dụng hệ thống <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="agency_name" value="<?= h($current_settings['agency_name'] ?? '') ?>" required placeholder="VD: BỆNH VIỆN ĐA KHOA TỈNH">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Tiêu đề trang (Site Title) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="site_title" value="<?= h($current_settings['site_title'] ?? '') ?>" required placeholder="VD: HỆ THỐNG QUẢN LÝ VĂN BẢN">
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label small fw-semibold text-dark">Nội dung bản quyền chân trang (Footer)</label>
                                        <input type="text" class="form-control" name="footer_copyright" value="<?= h($current_settings['footer_copyright'] ?? '') ?>" placeholder="VD: © 2026 Bản quyền thuộc Đơn vị">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KHỐI 2: VĂN BẢN, WATERMARK & IN ẤN -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                    <h6 class="fw-bold mb-0 text-primary">
                                        <i class="fas fa-stamp me-2"></i>2. Quy chuẩn In ấn & Dấu bản quyền
                                    </h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Tiêu đề in Sổ theo dõi văn bản (Khổ A4)</label>
                                        <input type="text" class="form-control" name="register_header_title" value="<?= h($current_settings['register_header_title'] ?? '') ?>" placeholder="VD: SỔ ĐĂNG KÝ THEO DÕI VĂN BẢN PHÁP QUY">
                                        <div class="form-text small">Hiển thị ở đầu trang khi xuất file in `print_register.php`.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Dòng chữ đóng dấu chìm (Watermark)</label>
                                        <input type="text" class="form-control" name="watermark_text" value="<?= h($current_settings['watermark_text'] ?? '') ?>" placeholder="VD: BẢN LƯU NỘI BỘ - NGHIÊM CẤM SAO CHÉP">
                                        <div class="form-text small">Được in chìm nghiêng 45 độ trên trình xem trước văn bản mật/bảo mật.</div>
                                    </div>

                                    <div class="row g-2 mb-0">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Dung lượng tải lên tối đa (MB)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="max_upload_size_mb" value="<?= (int)($current_settings['max_upload_size_mb'] ?? 120) ?>" min="5" max="500">
                                                <span class="input-group-text">MB</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Định dạng tệp cho phép</label>
                                            <input type="text" class="form-control" name="allowed_file_exts" value="<?= h($current_settings['allowed_file_exts'] ?? 'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip,rar') ?>" placeholder="pdf,doc,docx...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-danger fw-bold px-4 py-2 shadow-sm">
                            <i class="fas fa-save me-1"></i>Lưu thay đổi cài đặt
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 3000 });
        }
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_msg_error'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: <?= json_encode($_SESSION['flash_msg_error'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 4000 });
        }
        <?php unset($_SESSION['flash_msg_error']); ?>
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>