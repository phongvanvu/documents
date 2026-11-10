<?php
session_start();

// Chỉ Admin có quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$username = $full_name = $email = $role = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $avatar_path = ''; 

    // 1. Kiểm tra rỗng và logic cơ bản
    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "Vui lòng nhập đầy đủ các trường bắt buộc (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Định dạng Email không hợp lệ.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Mật khẩu và xác nhận mật khẩu không trùng khớp.";
    } else {
        
        // 2. Kiểm tra trùng lặp Tên đăng nhập / Email
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Tên đăng nhập hoặc Email này đã tồn tại trên hệ thống.";
        }
        $check_stmt->close();

        // 3. Xử lý Upload Avatar
        if (empty($error_message) && !empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/avatars/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
            
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if ($file["size"] > 2097152) { // 2MB
                $error_message = "Kích thước ảnh đại diện không được vượt quá 2MB.";
            } elseif (!in_array($ext, $allowed_exts, true) || getimagesize($file["tmp_name"]) === false) {
                $error_message = "Chỉ chấp nhận ảnh định dạng JPG, JPEG, PNG, WEBP hoặc GIF hợp lệ.";
            } else {
                $target_file = $target_dir . "avatar_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
                if (move_uploaded_file($file["tmp_name"], $target_file)) {
                    $avatar_path = $target_file;
                } else {
                    $error_message = "Không thể lưu tệp ảnh lên máy chủ.";
                }
            }
        }

        // 4. Lưu người dùng vào CSDL
        if (empty($error_message)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $full_name, $email, $hashed_password, $role, $avatar_path);

            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                $stmt->close();

                // Ghi Log hệ thống
                $admin_id = (int)$_SESSION['user_id'];
                $action = "Tạo tài khoản người dùng mới: '{$username}' (ID: {$new_user_id})";
                $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("is", $admin_id, $action);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                $_SESSION['flash_msg'] = "Thêm người dùng '{$username}' thành công!";
                header("Location: manage_users.php");
                exit();
            } else {
                $error_message = "Lỗi hệ thống khi lưu vào cơ sở dữ liệu.";
                $stmt->close();
            }
        }
    }
}

$title = "Thêm người dùng mới";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4" style="max-width: 900px;">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2"></i>THÊM NGƯỜI DÙNG MỚI</h5>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <form action="add_user.php" method="POST" enctype="multipart/form-data">

                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3"><i class="fas fa-id-card me-2"></i>Thông tin cá nhân</h6>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" value="<?= e($full_name) ?>" required placeholder="VD: Nguyễn Văn A">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?= e($email) ?>" required placeholder="VD: nguyenvan@example.com">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Ảnh đại diện (Avatar)</label>
                                <input type="file" class="form-control" name="avatar" accept="image/png, image/jpeg, image/webp, image/gif">
                                <div class="form-text">Định dạng JPG, PNG, WEBP. Dung lượng tối đa 2MB.</div>
                            </div>

                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3 mt-4"><i class="fas fa-user-shield me-2"></i>Tài khoản & Phân quyền</h6>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Tên đăng nhập <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" class="form-control" name="username" value="<?= e($username) ?>" required placeholder="Viết liền không dấu">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò phân quyền <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role" required>
                                        <option value="">-- Chọn vai trò --</option>
                                        <option value="admin" <?= ($role === "admin") ? "selected" : "" ?>>Quản trị viên (Admin)</option>
                                        <option value="editor" <?= ($role === "editor") ? "selected" : "" ?>>Biên tập viên (Editor)</option>
                                        <option value="viewer" <?= ($role === "viewer") ? "selected" : "" ?>>Người xem (Viewer)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-key text-muted"></i></span>
                                        <input type="password" class="form-control" name="password" required placeholder="Nhập mật khẩu">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-check-circle text-muted"></i></span>
                                        <input type="password" class="form-control" name="confirm_password" required placeholder="Nhập lại mật khẩu">
                                    </div>
                                </div>
                            </div>

                            <div class="text-end pt-3 border-top">
                                <a href="manage_users.php" class="btn btn-outline-secondary me-2"><i class="fas fa-times me-1"></i>Hủy bỏ</a>
                                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-save me-1"></i>Khởi tạo người dùng</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($error_message)): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi nhập liệu',
                text: <?= json_encode($error_message, JSON_UNESCAPED_UNICODE) ?>,
                confirmButtonColor: '#dc3545'
            });
        } else {
            alert(<?= json_encode($error_message, JSON_UNESCAPED_UNICODE) ?>);
        }
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>