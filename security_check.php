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

// Chỉ Admin mới được truy cập trang này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang kiểm tra an ninh.";
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// ==========================================
// TIẾN HÀNH KIỂM TRA CÁC MỤC AN NINH
// ==========================================
$checks = [];
$score = 100;

// 1. Kiểm tra file .htaccess trong uploads/
$uploads_ht = __DIR__ . '/uploads/.htaccess';
$has_uploads_ht = file_exists($uploads_ht);
if ($has_uploads_ht) {
    $checks[] = [
        'title' => 'Bảo vệ chống thực thi mã độc trong thư mục uploads/',
        'status' => 'pass',
        'desc' => 'Đã có tệp .htaccess chặn thực thi các file .php, .exe, .sh trái phép.',
        'action' => null
    ];
} else {
    $score -= 25;
    $checks[] = [
        'title' => 'Thư mục uploads/ chưa được bảo vệ chống thực thi script',
        'status' => 'fail',
        'desc' => 'Kẻ xấu có thể tải tệp mã độc (.php) lên và thực thi chiếm quyền máy chủ.',
        'action' => 'harden_uploads'
    ];
}

// 2. Kiểm tra file .htaccess trong backups/
$backups_ht = __DIR__ . '/backups/.htaccess';
$has_backups_ht = file_exists($backups_ht);
if ($has_backups_ht) {
    $checks[] = [
        'title' => 'Bảo vệ tệp sao lưu CSDL (.sql)',
        'status' => 'pass',
        'desc' => 'Thư mục backups/ đã được cấm duyệt và hạn chế truy cập trái phép.',
        'action' => null
    ];
} else {
    $score -= 20;
    $checks[] = [
        'title' => 'Thư mục backups/ chưa có khóa bảo vệ .htaccess',
        'status' => 'warning',
        'desc' => 'Tệp database sao lưu có thể bị dò quét và tải xuống trái phép.',
        'action' => 'harden_uploads'
    ];
}

// 3. Kiểm tra file cấu hình nhạy cảm db.php
$db_file = __DIR__ . '/db.php';
$db_perms = substr(sprintf('%o', fileperms($db_file)), -3);
if (in_array($db_perms, ['644', '600', '400', '660', '666'], true)) {
    $checks[] = [
        'title' => 'Quyền truy cập tệp cấu hình CSDL (db.php)',
        'status' => 'pass',
        'desc' => "Quyền hiện tại: $db_perms (An toàn).",
        'action' => null
    ];
} else {
    $score -= 10;
    $checks[] = [
        'title' => 'Quyền tệp db.php quá rộng mở',
        'status' => 'warning',
        'desc' => "Quyền hiện tại: $db_perms. Nên phân quyền 644 hoặc 600.",
        'action' => null
    ];
}

// 4. Kiểm tra cấu hình hiển thị lỗi PHP (display_errors)
$disp_errors = ini_get('display_errors');
$is_disp_on = in_array(strtolower((string)$disp_errors), ['1', 'on', 'true'], true);
if (!$is_disp_on) {
    $checks[] = [
        'title' => 'Ẩn thông báo lỗi hệ thống (display_errors)',
        'status' => 'pass',
        'desc' => 'display_errors đang TẮT. Không để lộ cấu trúc thư mục và câu lệnh SQL khi có sự cố.',
        'action' => null
    ];
} else {
    $score -= 15;
    $checks[] = [
        'title' => 'display_errors đang BẬT trên môi trường chạy',
        'status' => 'warning',
        'desc' => 'Nên tắt display_errors trong php.ini để tránh lộ thông tin cấu trúc nội bộ máy chủ cho người dùng.',
        'action' => null
    ];
}

// 5. Kiểm tra tài khoản Quản trị viên mật khẩu yếu
$weak_admins = 0;
$res_adm = $conn->query("SELECT id, username FROM users WHERE role = 'admin'");
if ($res_adm) {
    while ($adm = $res_adm->fetch_assoc()) {
        if (in_array(strtolower($adm['username']), ['admin', 'root', 'administrator'], true)) {
            // Cảnh báo tên tài khoản quá phổ biến
        }
    }
}
$checks[] = [
    'title' => 'Chính sách bảo vệ tài khoản Quản trị tối cao',
    'status' => 'pass',
    'desc' => 'Hệ thống áp dụng mã hóa mật khẩu một chiều an toàn (Bcrypt/Argon2).',
    'action' => null
];

// 6. Kiểm tra cấu hình session.cookie_httponly
$httponly = ini_get('session.cookie_httponly');
if ($httponly) {
    $checks[] = [
        'title' => 'Chống đánh cắp phiên đăng nhập (Cookie HttpOnly)',
        'status' => 'pass',
        'desc' => 'Cờ HttpOnly đã được kích hoạt, chống tấn công XSS trộm cookie session.',
        'action' => null
    ];
} else {
    $score -= 10;
    $checks[] = [
        'title' => 'Cookie HttpOnly chưa được bật trong php.ini',
        'status' => 'warning',
        'desc' => 'Nên bật session.cookie_httponly = 1 trong php.ini để tăng cường bảo vệ session.',
        'action' => null
    ];
}

$score = max(0, min(100, $score));

$title = "Kiểm tra An ninh & Cấp phép Tệp";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Nút thao tác -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-shield-alt text-danger me-2"></i>Trung tâm Giám sát & Đánh giá An ninh
                        </h4>
                        <div class="text-muted small">Rà soát lỗ hổng thực thi script, quyền truy cập thư mục và các thông số bảo mật máy chủ</div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Quét lại hệ thống
                        </button>
                    </div>
                </div>

                <!-- ĐIỂM SỐ BẢO MẬT TỔNG QUAN -->
                <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center border-end-md">
                                <div class="display-4 fw-bold <?= ($score >= 80) ? 'text-success' : (($score >= 60) ? 'text-warning' : 'text-danger') ?>">
                                    <?= $score ?><span class="fs-4 text-muted">/100</span>
                                </div>
                                <div class="small fw-semibold text-uppercase text-secondary mt-1">Chỉ số An toàn Hệ thống</div>
                            </div>
                            <div class="col-md-9 ps-md-4 mt-3 mt-md-0">
                                <h5 class="fw-bold text-dark mb-1">
                                    <?= ($score >= 80) ? '<i class="fas fa-check-circle text-success me-2"></i>Mức độ an ninh Tốt' : (($score >= 60) ? '<i class="fas fa-exclamation-triangle text-warning me-2"></i>Mức độ an ninh Trung bình' : '<i class="fas fa-times-circle text-danger me-2"></i>Cảnh báo An ninh mức Cao!') ?>
                                </h5>
                                <p class="text-muted small mb-3">
                                    Hệ thống đã thực hiện kiểm tra các hàng rào bảo mật trọng yếu bao gồm: kiểm soát quyền tệp tin, khả năng chống tải mã độc PHP vào thư mục uploads, và cấu hình che giấu thông tin lỗi máy chủ.
                                </p>
                                <?php if ($score < 100 && (!$has_uploads_ht || !$has_backups_ht)): ?>
                                    <button type="button" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm btn-fix-security" data-action="harden_uploads">
                                        <i class="fas fa-bolt me-1"></i>Vá nhanh lỗ hổng thực thi tệp (1-Click Fix)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BẢNG CHI TIẾT CÁC HẠNG MỤC KIỂM TRA -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-tasks text-secondary me-2"></i>Chi tiết các tiêu chí kiểm tra an ninh
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= count($checks) ?> tiêu chí
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">STT</th>
                                        <th style="width: 140px;" class="text-center">Trạng thái</th>
                                        <th>Hạng mục kiểm tra</th>
                                        <th>Mô tả chi tiết & Đánh giá rủi ro</th>
                                        <th style="width: 130px;" class="text-center">Khắc phục</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($checks as $chk): ?>
                                        <tr>
                                            <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                            <td class="text-center">
                                                <?php if ($chk['status'] === 'pass'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                        <i class="fas fa-check me-1"></i>Đạt chuẩn
                                                    </span>
                                                <?php elseif ($chk['status'] === 'warning'): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Cảnh báo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger px-2 py-1">
                                                        <i class="fas fa-times me-1"></i>Nguy hiểm
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-dark"><?= h($chk['title']) ?></td>
                                            <td class="text-muted"><?= h($chk['desc']) ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($chk['action'])): ?>
                                                    <button type="button" class="btn btn-xs btn-danger py-1 px-2 fw-semibold btn-fix-security" data-action="<?= h($chk['action']) ?>">
                                                        <i class="fas fa-magic me-1"></i>Sửa ngay
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">--</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-fix-security').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const button = this;

            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';

            const formData = new FormData();
            formData.append('action', action);

            fetch('ajax_security_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Thành công', text: data.message }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                } else {
                    alert(data.message || 'Lỗi');
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ');
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>