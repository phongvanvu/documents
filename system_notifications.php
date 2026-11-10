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
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang cấu hình thông báo.";
    header('Location: documents.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Xử lý lưu thiết lập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notifications'])) {
    $settings = [
        'smtp_host'            => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'            => trim($_POST['smtp_port'] ?? '587'),
        'smtp_secure'          => trim($_POST['smtp_secure'] ?? 'tls'),
        'smtp_user'            => trim($_POST['smtp_user'] ?? ''),
        'smtp_pass'            => trim($_POST['smtp_pass'] ?? ''),
        'smtp_from_name'       => trim($_POST['smtp_from_name'] ?? ''),
        'alert_expiry_days'    => trim($_POST['alert_expiry_days'] ?? '30,60'),
        'alert_admin_email'    => trim($_POST['alert_admin_email'] ?? ''),
        'telegram_webhook_url' => trim($_POST['telegram_webhook_url'] ?? '')
    ];

    try {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $k => $v) {
            $stmt->bind_param("ss", $k, $v);
            $stmt->execute();
        }
        $stmt->close();

        // Ghi log
        $log_act = "Cập nhật cấu hình thông báo và kết nối cảnh báo tự động";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        $_SESSION['flash_msg'] = "Đã lưu toàn bộ cấu hình thông báo thành công!";
    } catch (Exception $e) {
        $_SESSION['flash_msg_error'] = "Lỗi khi lưu: " . $e->getMessage();
    }

    header('Location: system_notifications.php');
    exit;
}

$title = "Cấu hình Thông báo & Cảnh báo Tự động";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-bell text-danger me-2"></i>Cấu Hình Thông Báo & Cảnh Báo Tự Động
                        </h4>
                        <div class="text-muted small">Thiết lập quy tắc cảnh báo hạn hiệu lực văn bản, máy chủ gửi thư SMTP và kênh Webhook</div>
                    </div>
                </div>

                <form action="system_notifications.php" method="POST">
                    <input type="hidden" name="save_notifications" value="1">

                    <div class="row g-4">
                        <!-- KHỐI 1: QUY TẮC CẢNH BÁO HIỆU LỰC -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                    <h6 class="fw-bold mb-0 text-danger">
                                        <i class="fas fa-clock me-2"></i>1. Quy tắc Cảnh báo Văn bản Sắp hết hạn
                                    </h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">
                                            Các mốc cảnh báo trước (Số ngày, cách nhau bởi dấu phẩy) <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" name="alert_expiry_days" value="<?= h(get_setting('alert_expiry_days', '30,60')) ?>" placeholder="VD: 15,30,60">
                                        <div class="form-text small">Hệ thống sẽ gắn cờ cảnh báo đối với các văn bản sắp hết hạn trong khoảng ngày này.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Email Quản trị viên nhận thông báo</label>
                                        <input type="email" class="form-control" name="alert_admin_email" value="<?= h(get_setting('alert_admin_email', '')) ?>" placeholder="admin@domain.com">
                                        <div class="form-text small">Địa chỉ hộp thư nhận danh sách văn bản sắp hết hiệu lực định kỳ.</div>
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label small fw-semibold text-dark">Webhook URL (Telegram / Discord)</label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" name="telegram_webhook_url" id="inputWebhook" value="<?= h(get_setting('telegram_webhook_url', '')) ?>" placeholder="https://api.telegram.org/... hoặc Webhook Discord">
                                            <button type="button" class="btn btn-outline-primary" id="btnTestWebhook" title="Gửi thử nghiệm">
                                                <i class="fas fa-paper-plane me-1"></i>Thử
                                            </button>
                                        </div>
                                        <div class="form-text small">Gửi tin nhắn tức thời đến nhóm làm việc khi có văn bản mới hoặc cảnh báo hết hạn.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KHỐI 2: CẤU HÌNH MÁY CHỦ EMAIL SMTP -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-3 h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold mb-0 text-primary">
                                        <i class="fas fa-envelope-open-text me-2"></i>2. Cấu hình Máy chủ Gửi thư (SMTP)
                                    </h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-0" id="btnTestSmtp">
                                        <i class="fas fa-plug me-1"></i>Kiểm tra kết nối
                                    </button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold text-dark">Máy chủ SMTP (Host)</label>
                                            <input type="text" class="form-control" name="smtp_host" id="inputSmtpHost" value="<?= h(get_setting('smtp_host', '')) ?>" placeholder="smtp.gmail.com">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-dark">Cổng (Port)</label>
                                            <input type="number" class="form-control" name="smtp_port" id="inputSmtpPort" value="<?= h(get_setting('smtp_port', '587')) ?>">
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Giao thức mã hóa</label>
                                            <select class="form-select" name="smtp_secure">
                                                <?php $sec = get_setting('smtp_secure', 'tls'); ?>
                                                <option value="tls" <?= ($sec === 'tls') ? 'selected' : '' ?>>TLS (Khuyên dùng)</option>
                                                <option value="ssl" <?= ($sec === 'ssl') ? 'selected' : '' ?>>SSL</option>
                                                <option value="none" <?= ($sec === 'none') ? 'selected' : '' ?>>Không mã hóa</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Tên người gửi hiển thị</label>
                                            <input type="text" class="form-control" name="smtp_from_name" value="<?= h(get_setting('smtp_from_name', 'Hệ thống Quản lý Văn bản')) ?>">
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-0">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Tài khoản SMTP</label>
                                            <input type="text" class="form-control" name="smtp_user" value="<?= h(get_setting('smtp_user', '')) ?>" placeholder="email@gmail.com">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-dark">Mật khẩu ứng dụng</label>
                                            <input type="password" class="form-control" name="smtp_pass" value="<?= h(get_setting('smtp_pass', '')) ?>" placeholder="••••••••••••">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-danger fw-bold px-4 py-2 shadow-sm">
                            <i class="fas fa-save me-1"></i>Lưu cấu hình thông báo
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
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 3000 });
        }
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_msg_error'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: <?= json_encode($_SESSION['flash_msg_error'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 4000 });
        }
        <?php unset($_SESSION['flash_msg_error']); ?>
    <?php endif; ?>

    // Kiểm tra kết nối SMTP
    const btnTestSmtp = document.getElementById('btnTestSmtp');
    if (btnTestSmtp) {
        btnTestSmtp.addEventListener('click', function() {
            btnTestSmtp.disabled = true;
            btnTestSmtp.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang kiểm tra...';

            const formData = new FormData();
            formData.append('action', 'test_smtp');
            formData.append('smtp_host', document.getElementById('inputSmtpHost').value);
            formData.append('smtp_port', document.getElementById('inputSmtpPort').value);

            fetch('ajax_notification_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: data.status === 'success' ? 'success' : 'error',
                        title: data.status === 'success' ? 'Thành công' : 'Thất bại',
                        text: data.message
                    });
                } else {
                    alert(data.message);
                }
            })
            .catch(() => alert('Lỗi kết nối kiểm tra máy chủ!'))
            .finally(() => {
                btnTestSmtp.disabled = false;
                btnTestSmtp.innerHTML = '<i class="fas fa-plug me-1"></i>Kiểm tra kết nối';
            });
        });
    }

    // Kiểm tra gửi Webhook
    const btnTestWebhook = document.getElementById('btnTestWebhook');
    if (btnTestWebhook) {
        btnTestWebhook.addEventListener('click', function() {
            btnTestWebhook.disabled = true;
            btnTestWebhook.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('action', 'test_webhook');
            formData.append('webhook_url', document.getElementById('inputWebhook').value);

            fetch('ajax_notification_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: data.status === 'success' ? 'success' : 'error',
                        title: data.status === 'success' ? 'Thành công' : 'Thất bại',
                        text: data.message
                    });
                } else {
                    alert(data.message);
                }
            })
            .catch(() => alert('Lỗi kiểm tra Webhook!'))
            .finally(() => {
                btnTestWebhook.disabled = false;
                btnTestWebhook.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Thử';
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>