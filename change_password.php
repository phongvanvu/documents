<?php
// Khởi động session
session_start();

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

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
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Kiểm tra xem các trường có được nhập đầy đủ không
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Vui lòng nhập đầy đủ thông tin.";
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
include 'header.php';
?>

<h2 class="mt-5 text-center">Thay Đổi Mật Khẩu</h2>

<!-- Hiển thị thông tin tên người dùng -->
<p class="text-center"><strong>Tên người dùng: <?php echo htmlspecialchars($user_name); ?></strong></p>

<!-- Hiển thị thông báo lỗi hoặc thành công -->
<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Form thay đổi mật khẩu -->
<form action="change_password.php" method="POST" class="mt-4">
    <div class="form-group">
        <label for="current_password">Mật khẩu hiện tại:</label>
        <input type="password" class="form-control" id="current_password" name="current_password" required>
    </div>
    <div class="form-group">
        <label for="new_password">Mật khẩu mới:</label>
        <input type="password" class="form-control" id="new_password" name="new_password" required>
    </div>
    <div class="form-group">
        <label for="confirm_password">Xác nhận mật khẩu mới:</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
</form>

<!-- Nút quay lại trang điều khiển -->
<div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-secondary">Quay lại Trang Điều Khiển</a>
</div>

<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
