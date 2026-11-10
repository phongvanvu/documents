<?php
session_start();

// 1. SỬA LỖI LOGIC: Nếu đã đăng nhập rồi thì không cho vào trang login nữa
// Lưu ý: Đã đổi dashboard.php thành documents.php nếu hệ thống của bạn coi danh sách là trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

$error = ''; 
$success_msg = '';

// Hiển thị thông báo khi đổi mật khẩu thành công từ trang change_password.php
if (isset($_GET['message']) && $_GET['message'] === 'PasswordChanged') {
    $success_msg = "Đổi mật khẩu thành công! Vui lòng đăng nhập lại.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ tài khoản và mật khẩu!";
    } else {
        // Kiểm tra tài khoản
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Kiểm tra mật khẩu mã hóa
            if (password_verify($password, $user['password'])) {
                
                // Đăng nhập thành công -> Thiết lập Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'];
                
                // Xử lý Ghi nhớ đăng nhập
                $cookie_time = time() + (86400 * 30); // 30 ngày
                
                if ($remember) {
                    // TỐI ƯU BẢO MẬT: Bổ sung cấu hình [httponly => true] để chống mã độc XSS đánh cắp cookie
                    setcookie('username', $_SESSION['user_name'], [
                        'expires' => $cookie_time, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
                    ]);
                    setcookie('user_id', $_SESSION['user_id'], [
                        'expires' => $cookie_time, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
                    ]);
                    setcookie('full_name', $_SESSION['full_name'], [
                        'expires' => $cookie_time, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
                    ]);
                    setcookie('role', $_SESSION['role'], [
                        'expires' => $cookie_time, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
                    ]);
                    setcookie('avatar', $_SESSION['avatar'], [
                        'expires' => $cookie_time, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'
                    ]);
                } else {
                    // Hủy bỏ cookie nếu không chọn ghi nhớ
                    $past_time = time() - 3600;
                    setcookie('username', '', ['expires' => $past_time, 'path' => '/']);
                    setcookie('user_id', '', ['expires' => $past_time, 'path' => '/']);
                    setcookie('full_name', '', ['expires' => $past_time, 'path' => '/']);
                    setcookie('role', '', ['expires' => $past_time, 'path' => '/']);
                    setcookie('avatar', '', ['expires' => $past_time, 'path' => '/']);
                }

                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Mật khẩu không đúng!";
            }
        } else {
            $error = "Tài khoản không tồn tại!";
        }
        $stmt->close();
    }
}

$title = "Đăng nhập hệ thống";

// Khởi tạo HTML trực tiếp mà KHÔNG gọi file header.php 
// (Trang login không cần sidebar hay navbar)
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- CSS (Local) -->
    <link href="assets/fonts/montserrat.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/plugins/fontawesome/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f6f9;
            background-image: radial-gradient(#dc3545 0.5px, transparent 0.5px), radial-gradient(#dc3545 0.5px, #f4f6f9 0.5px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            background-attachment: fixed;
            background-opacity: 0.1;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            background: white;
        }
        .login-header {
            background-color: #dc3545;
            color: white;
            padding: 2rem 1rem;
            text-align: center;
        }
        .login-body {
            padding: 2.5rem 2rem;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
            border-radius: 0.375rem;
        }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #dc3545;
        }
        .btn-toggle-password {
            background-color: transparent;
            border: 1px solid #ced4da;
            border-left: none;
            color: #6c757d;
        }
        .input-group:focus-within .btn-toggle-password {
            border-color: #dc3545;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="container" style="max-width: 450px;">
        <div class="login-card">
            
            <div class="login-header">
                <div class="mb-2">
                    <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle text-danger shadow-sm" style="width: 70px; height: 70px;">
                        <i class="fas fa-file-signature fa-2x"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-0">QUẢN LÝ VĂN BẢN</h4>
                <div class="small opacity-75 mt-1">Hệ thống lưu trữ & tra cứu nội bộ</div>
            </div>

            <div class="login-body">
                
                <?php if ($success_msg): ?>
                    <div class="alert alert-success d-flex align-items-center fw-semibold small" role="alert">
                        <i class="fas fa-check-circle flex-shrink-0 me-2 fa-lg"></i>
                        <div><?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center fw-semibold small" role="alert">
                        <i class="fas fa-exclamation-triangle flex-shrink-0 me-2 fa-lg"></i>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Tài khoản đăng nhập</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control form-control-lg" placeholder="Nhập username..." value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-muted small text-uppercase">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control form-control-lg" placeholder="Nhập mật khẩu..." required autocomplete="current-password">
                            <button class="btn btn-toggle-password" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input border-danger" id="remember" checked>
                            <label class="form-check-label fw-semibold text-secondary small" style="cursor:pointer;" for="remember">Ghi nhớ đăng nhập</label>
                        </div>
                        <a href="manage_contacts.php" class="text-danger small fw-semibold text-decoration-none" title="Vui lòng liên hệ Admin để cấp lại mật khẩu">Quên mật khẩu?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold shadow-sm py-2">
                        <i class="fas fa-sign-in-alt me-2"></i>ĐĂNG NHẬP
                    </button>
                </form>

            </div>
        </div>
        
        <div class="text-center mt-4 text-muted small fw-semibold">
            &copy; <?= date('Y'); ?> Hệ thống Quản lý Văn bản.
        </div>
    </div>

    <!-- JS (Local) -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Nút ẩn/hiện mật khẩu
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    </script>
</body>
</html>