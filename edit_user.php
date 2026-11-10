<?php
session_start();

// BẢO MẬT: Chỉ có Admin mới được phép chỉnh sửa tài khoản nhân sự
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error_message = "";
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// 1. KIỂM TRA ID HỢP LỆ VÀ LẤY DỮ LIỆU CŨ
if (!$id) {
    include 'header.php';
    echo "<div class='container-fluid main'><div class='row flex-nowrap'><div id='sidebar' class='col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar'>";
    include 'sidebar.php';
    echo "</div><div id='content' class='content col py-5 text-center'><div class='container mt-5'><div class='alert alert-danger fw-bold'>ID người dùng không hợp lệ hoặc thiếu.</div></div></div></div></div>";
    include 'footer.php';
    exit;
}

$stmt = $conn->prepare("SELECT username, full_name, email, role, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    include 'header.php';
    echo "<div class='container-fluid main'><div class='row flex-nowrap'><div id='sidebar' class='col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar'>";
    include 'sidebar.php';
    echo "</div><div id='content' class='content col py-5 text-center'><div class='container mt-5'><div class='alert alert-warning fw-bold'>Tài khoản nhân sự không tồn tại hoặc đã bị xóa.</div></div></div></div></div>";
    include 'footer.php';
    exit;
}

// Đổ dữ liệu cũ ra các biến form
$username = $user['username'];
$full_name = $user['full_name'];
$email = $user['email'];
$role = $user['role'];
$current_avatar = $user['avatar'];

// ==========================================
// 2. XỬ LÝ KHI FORM ĐƯỢC SUBMIT (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $avatar_path = $current_avatar; 

    // Kiểm tra dữ liệu bắt buộc
    if (empty($username) || empty($full_name) || empty($email) || empty($role)) {
        $error_message = "Vui lòng nhập đầy đủ các thông tin bắt buộc.";
    } 
    // Chặn lỗi logic tự hạ quyền Admin của chính mình
    elseif ($id === intval($_SESSION['user_id']) && $role !== 'admin') {
        $error_message = "Bảo mật hệ thống: Bạn không thể tự hạ vai trò Quản trị viên của chính mình.";
        $role = 'admin';
    } 
    // Kiểm tra khớp mật khẩu mới (nếu có đổi)
    elseif (!empty($password) && $password !== $confirm_password) {
        $error_message = "Mật khẩu mới và xác nhận mật khẩu không trùng khớp.";
    } 
    else {
        // Kiểm tra trùng lặp tài khoản/email với người dùng khác
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $username, $email, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Tên đăng nhập hoặc địa chỉ Email đã được sử dụng bởi một tài khoản khác.";
        }
        $check_stmt->close();

        // Xử lý thay đổi Ảnh đại diện nếu có tệp mới tải lên
        if (empty($error_message) && !empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/avatars/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
            
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if ($file["size"] > 2097152) {
                $error_message = "Dung lượng ảnh đại diện vượt quá giới hạn (Tối đa 2MB).";
            } elseif (!in_array($ext, $allowed_exts, true) || getimagesize($file["tmp_name"]) === false) {
                $error_message = "Chỉ chấp nhận tệp ảnh JPG, JPEG, PNG, WEBP hoặc GIF hợp lệ.";
            } else {
                $target_file = $target_dir . "avatar_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
                if (move_uploaded_file($file["tmp_name"], $target_file)) {
                    $avatar_path = $target_file;
                    // Xóa ảnh cũ trên máy chủ (nếu không phải ảnh default)
                    if (!empty($current_avatar) && file_exists($current_avatar) && strpos($current_avatar, 'default.jpg') === false) {
                        @unlink($current_avatar);
                    }
                } else {
                    $error_message = "Không thể lưu tệp ảnh đại diện lên máy chủ.";
                }
            }
        }

        // Thực thi Cập nhật dữ liệu
        if (empty($error_message)) {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, avatar = ?, password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $username, $full_name, $email, $role, $avatar_path, $hashed_password, $id);
            } else {
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, avatar = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $username, $full_name, $email, $role, $avatar_path, $id);
            }

            if ($stmt->execute()) {
                $stmt->close();

                // Cập nhật lại Session nếu Admin tự sửa chính tài khoản của mình
                if ($id === intval($_SESSION['user_id'])) {
                    $_SESSION['user_name'] = $username;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['avatar'] = $avatar_path;
                }

                // Ghi Log hệ thống
                $admin_id = (int)$_SESSION['user_id'];
                $action = "Cập nhật tài khoản người dùng: '{$username}' (ID: {$id})";
                $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("is", $admin_id, $action);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                $_SESSION['flash_msg'] = "Cập nhật thông tin '{$username}' thành công!";
                header("Location: manage_users.php");
                exit();
            } else {
                $error_message = "Lỗi hệ thống khi cập nhật cơ sở dữ liệu.";
                $stmt->close();
            }
        }
    }
}

$title = "Sửa thông tin người dùng";
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
                        <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>CẬP NHẬT THÔNG TIN NHÂN SỰ</h5>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <form action="edit_user.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data">

                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3"><i class="fas fa-id-card me-2"></i>Thông tin cá nhân</h6>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" value="<?= e($full_name) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" value="<?= e($email) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-4 align-items-center">
                                <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                                    <?php $view_avatar = (!empty($current_avatar) && file_exists($current_avatar)) ? $current_avatar : 'uploads/avatars/default.jpg'; ?>
                                    <img src="<?= e($view_avatar) ?>" alt="Avatar" class="rounded-circle border border-2 shadow-sm" style="width: 70px; height: 70px; object-fit: cover;">
                                </div>
                                <div class="col-md-10">
                                    <label class="form-label fw-semibold">Thay đổi ảnh đại diện mới</label>
                                    <input type="file" class="form-control" name="avatar" accept="image/png, image/jpeg, image/webp, image/gif">
                                    <div class="form-text">Bỏ trống nếu giữ nguyên ảnh hiện tại. Dung lượng tối đa 2MB.</div>
                                </div>
                            </div>

                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3 mt-4"><i class="fas fa-user-shield me-2"></i>Tài khoản & Phân quyền</h6>
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Tên đăng nhập <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                                        <input type="text" class="form-control" name="username" value="<?= e($username) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò hệ thống <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role" required <?= ($id === intval($_SESSION['user_id'])) ? 'disabled' : ''; ?>>
                                        <option value="admin" <?= ($role === "admin") ? "selected" : "" ?>>Quản trị viên (Admin)</option>
                                        <option value="editor" <?= ($role === "editor") ? "selected" : "" ?>>Biên tập viên (Editor)</option>
                                        <option value="viewer" <?= ($role === "viewer") ? "selected" : "" ?>>Người xem (Viewer)</option>
                                    </select>
                                    <?php if ($id === intval($_SESSION['user_id'])): ?>
                                        <input type="hidden" name="role" value="admin">
                                        <div class="form-text text-danger"><i class="fas fa-info-circle me-1"></i>Hệ thống tự động khóa tính năng thay đổi quyền khi bạn tự sửa hồ sơ của mình.</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-4 bg-light p-3 rounded border border-dashed">
                                <div class="col-12"><small class="text-muted d-block mb-2 fw-bold"><i class="fas fa-shield-alt me-1"></i> THAY ĐỔI MẬT KHẨU (TÙY CHỌN)</small></div>
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label small fw-semibold">Mật khẩu mới</label>
                                    <input type="password" class="form-control" name="password" placeholder="Bỏ trống nếu giữ nguyên">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Xác nhận mật khẩu mới</label>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Nhập lại mật khẩu mới">
                                </div>
                            </div>

                            <div class="text-end pt-3 border-top">
                                <a href="manage_users.php" class="btn btn-outline-secondary me-2"><i class="fas fa-times me-1"></i>Hủy bỏ</a>
                                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-save me-1"></i>Lưu thay đổi</button>
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