<?php
// Khởi động session
session_start();

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username']) && isset($_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['full_name'] = $_COOKIE['full_name'] ?? '';
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
        $_SESSION['avatar'] = $_COOKIE['avatar'] ?? '';
    } else {
        header('Location: login.php');
        exit;
    }
}

// Khai báo biến để lưu thông tin và lỗi
$current_password = $new_password = $confirm_password = "";
$error_message = $success_message = "";
$user_name = "";

// Lấy thông tin người dùng từ cơ sở dữ liệu
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Xử lý khi form được submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Kiểm tra xem các trường có được nhập đầy đủ không
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Vui lòng nhập đầy đủ thông tin.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Mật khẩu mới và xác nhận mật khẩu không khớp.";
    } else {
        // Lấy mật khẩu hiện tại từ cơ sở dữ liệu
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        // Kiểm tra mật khẩu hiện tại
        if (!password_verify($current_password, $hashed_password)) {
            $error_message = "Mật khẩu hiện tại không chính xác.";
        } else {
            // Mã hóa mật khẩu mới
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Cập nhật mật khẩu mới vào cơ sở dữ liệu
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed_password, $user_id);

            if ($stmt->execute()) {
                // Xóa thông tin cookie nếu có để bắt buộc đăng nhập lại
                setcookie("user_id", "", time() - 3600, "/");
                setcookie("username", "", time() - 3600, "/");
                
                // Xóa thông tin người dùng khỏi session và yêu cầu đăng nhập lại
                session_unset();
                session_destroy();
                
                // Chuyển hướng đến trang đăng nhập với thông báo thành công
                header('Location: login.php?message=PasswordChanged');
                exit;
            } else {
                $error_message = "Có lỗi xảy ra khi cập nhật mật khẩu.";
            }
            $stmt->close();
        }
    }
}

$title = "Đổi mật khẩu";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container mt-4 mb-5" style="max-width: 600px;">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0 text-dark fw-bold">
                        <i class="fas fa-key text-danger me-2"></i>Đổi mật khẩu
                    </h4>
                    <!-- Đã sửa thành documents.php -->
                    <a href="documents.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Trở về
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-shield fa-2x text-secondary"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></h5>
                            <p class="text-muted small">Hãy cập nhật mật khẩu thường xuyên để bảo vệ tài khoản</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                                <i class="fas fa-exclamation-circle flex-shrink-0 me-2"></i>
                                <div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>

                        <form action="change_password.php" method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-unlock-alt text-muted"></i></span>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Nhập mật khẩu đang sử dụng">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Tối thiểu 6 ký tự">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-check-circle text-muted"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="form-text mt-2" id="password_match_message"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger py-2 fw-bold" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>Cập nhật mật khẩu
                                </button>
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
    // Xử lý nút ẩn/hiện mật khẩu
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Kiểm tra trực tiếp xem 2 mật khẩu mới có khớp nhau không
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('password_match_message');
    const submitBtn = document.getElementById('submitBtn');

    function checkPasswordMatch() {
        if (confirmPassword.value === '') {
            matchMessage.innerHTML = '';
            submitBtn.disabled = false;
            return;
        }

        if (newPassword.value === confirmPassword.value) {
            matchMessage.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Mật khẩu khớp nhau</span>';
            submitBtn.disabled = false;
        } else {
            matchMessage.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Mật khẩu không khớp</span>';
            submitBtn.disabled = true;
        }
    }

    newPassword.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);
});
</script>

<?php
include 'footer.php';
$conn->close();
?>