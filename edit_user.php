<?php
// Khởi động session
session_start();

// Kiểm tra nếu người dùng chưa đăng nhập hoặc không phải là admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Khai báo biến để lưu thông tin và lỗi
$user_id = $username = $role = "";
$error_message = $success_message = "";

// Lấy ID của người dùng cần chỉnh sửa từ URL
if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Truy vấn thông tin người dùng từ cơ sở dữ liệu
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $role);
    $stmt->fetch();
    $stmt->close();
}

// Xử lý khi form được submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $role = trim($_POST['role']);

    // Kiểm tra xem tên người dùng và vai trò có được nhập đầy đủ
    if (empty($username) || empty($role)) {
        $error_message = "Vui lòng nhập đầy đủ thông tin.";
    } else {
        // Cập nhật thông tin người dùng
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $role, $user_id);

        if ($stmt->execute()) {
            $success_message = "Cập nhật thông tin người dùng thành công.";
        } else {
            $error_message = "Có lỗi xảy ra khi cập nhật người dùng.";
        }
        $stmt->close();
    }
}
include 'header.php';
?>

    <h2 class="mt-5 text-center">Chỉnh sửa Thông tin Người dùng</h2>

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

    <!-- Form chỉnh sửa thông tin người dùng -->
    <form action="edit_user.php?id=<?php echo $user_id; ?>" method="POST" class="mt-4">
        <div class="form-group">
            <label for="username">Tên người dùng:</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="form-group">
            <label for="role">Vai trò: </label>
            <select class="form-control" id="role" name="role" required>
                <option value="">Chọn vai trò</option>
                <option value="admin" <?php  if ($role == "admin") echo "selected"; ?>>Admin</option>
                <option value="editor" <?php if ($role == "editor") echo "selected"; ?>>Editor</option>
                <option value="viewer" <?php if ($role == "viewer") echo "selected"; ?>>Viewer</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="manage_users.php" class="btn btn-secondary">Quay lại</a>
    </form>
<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
