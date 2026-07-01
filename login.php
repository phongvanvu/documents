<?php
session_start();

// Kết nối đến cơ sở dữ liệu
include 'db.php';

$error = ''; // Biến lưu thông báo lỗi

// Kiểm tra nếu người dùng gửi biểu mẫu đăng nhập
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
	$remember = isset($_POST['remember']);

    // Kiểm tra xem username có tồn tại trong CSDL không
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Kiểm tra mật khẩu
        if (password_verify($password, $user['password'])) {
            // Đăng nhập thành công
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['username'];
			$_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];
			
			if ($remember) {
				// Ghi nhớ đăng nhập bằng cookie
				setcookie('username', $_SESSION['user_name'], time() + (86400 * 30), "/"); // Lưu cookie trong 30 ngày
                setcookie('user_id', $_SESSION['user_id'], time() + (86400 * 30), "/");
                setcookie('full_name', $_SESSION['full_name'], time() + (86400 * 30), "/");
                setcookie('role', $_SESSION['role'], time() + (86400 * 30), "/");
                setcookie('avatar', $_SESSION['avatar'], time() + (86400 * 30), "/");
			} else {
				// Xóa cookie nếu không chọn ghi nhớ
                if (isset($_COOKIE['username'])) {
				    setcookie('username', '', time() - 3600, "/");
                    setcookie('user_id', '', time() - 3600, "/");
                    setcookie('full_name', '', time() - 3600, "/");
                    setcookie('role', '', time() - 3600, "/");
                    setcookie('avatar', '', time() - 3600, "/");
                }
			}

            // Chuyển hướng đến trang dashboard sau khi đăng nhập thành công
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
$title = "Đăng nhập";
include 'header.php';
?>
<main>
    <div class="container">
        <h3 class="text-center mb-4">Quản lý văn bản</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="mb-3">
                <input type="text" name="username" class="form-control" id="username" placeholder="Tài khoản" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" id="password" placeholder="Mật khẩu" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                <label class="form-check-label" for="rememberMe">Ghi nhớ tôi</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</main>
<?php
include 'footer.php';
?>