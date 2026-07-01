<?php
session_start();

// Kiểm tra nếu người dùng chưa đăng nhập hoặc không phải là admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'editor') {
    header('Location: index.php');
    exit;
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Khai báo biến để lưu thông tin và lỗi
$username = $full_name = $email = $password = $confirm_password = $role = "";
$error_message = $success_message = "";

// Xử lý khi form được submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = trim($_POST['role']);
    $avatar_path = ''; // Đường dẫn avatar mặc định (nếu không có ảnh)

    // Kiểm tra dữ liệu hợp lệ
    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "Vui lòng nhập đầy đủ thông tin.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Email không hợp lệ.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Mật khẩu và xác nhận mật khẩu không khớp.";
    } else {
        // Xử lý upload avatar nếu có
        if (!empty($_FILES['avatar']['name'])) {
            $avatar_path = uploadAvatar($_FILES['avatar']);
            if ($avatar_path === false) {
                $error_message = "Có lỗi khi tải lên ảnh đại diện.";
            }
        }
        // Gọi hàm thêm người dùng mới
        if (empty($error_message)) {
            $error_message = addNewUser($username, $full_name, $email, $password, $role, $avatar_path);
            
            if (!$error_message) {
                $success_message = "Người dùng mới đã được thêm thành công.";
                // Điều hướng về trang quản lý người dùng
                header("Location: manage_users.php");
                exit();
            }
        }
    }
}

// Hàm thêm người dùng mới vào cơ sở dữ liệu
function addNewUser($username, $full_name, $email, $password, $role, $avatar_path) {
    global $conn;
    
    // Mã hóa mật khẩu
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Chuẩn bị câu lệnh truy vấn
    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $full_name, $email, $hashed_password, $role, $avatar_path);

    if ($stmt->execute()) {
        return null; // Không có lỗi
    } else {
        return "Có lỗi xảy ra khi thêm người dùng.";
    }
}

// Hàm xử lý upload avatar
function uploadAvatar($file) {
    $target_dir = "uploads/avatars/";
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Kiểm tra có phải là file ảnh không
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return false; // Không phải ảnh
    }

    // Kiểm tra kích thước file (ví dụ: không lớn hơn 2MB)
    if ($file["size"] > 2000000) {
        return false; // File quá lớn
    }

    // Chỉ cho phép các định dạng ảnh JPG, JPEG, PNG, GIF
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        return false; // Định dạng file không hợp lệ
    }

    // Di chuyển file vào thư mục uploads
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $target_file; // Trả về đường dẫn ảnh
    } else {
        return false; // Lỗi khi tải file lên
    }
}
$title = "Thêm người dùng";
include 'header.php';
?>
<main>
    <div class="container">
        <h3 class="text-center mb-4">Thêm Người dùng mới</h3>
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
        <form action="add_user.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="username" class="col-sm-4 col-form-label">Tên đăng nhập:</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="full_name" class="col-sm-4 col-form-label">Họ và tên:</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="email" class="col-sm-4 col-form-label">Email:</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="password" class="col-sm-4 col-form-label">Mật khẩu:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="confirm_password" class="col-sm-4 col-form-label">Xác nhận mật khẩu:</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="role" class="col-sm-4 col-form-label">Vai trò:</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="">Chọn vai trò</option>
                    <option value="admin" <?php if ($role == "admin") echo "selected"; ?>>Admin</option>
                    <option value="editor" <?php if ($role == "editor") echo "selected"; ?>>Editor</option>
                    <option value="viewer" <?php if ($role == "viewer") echo "selected"; ?>>Viewer</option>
                </select>
            </div>
            <div class="mb-3 d-flex align-items-center flex-nowrap">
                <label for="avatar" class="col-sm-4 col-form-label">Chọn ảnh đại diện:</label>
                <input type="file" name="avatar" id="avatar" accept="image/*" class="d-none" onchange="updateFileName()">
                <label for="avatar" class="btn btn-danger">Chọn tệp</label>
                <span id="file-name" class="ml-2">Chưa chọn tệp</span>
                <script>
                    function updateFileName() {
                        const fileInput = document.getElementById('avatar');
                        const fileName = fileInput.files.length > 0 ? fileInput.files[0].name : 'Chưa chọn tệp';
                        document.getElementById('file-name').textContent = fileName;
                    }
                </script>
            </div>
            <button type="submit" class="btn btn-primary w-100">Thêm người dùng</button>
            <a href="manage_users.php" class="btn btn-secondary w-100 mt-2">Quay lại</a>
        </form>
    </div>
</main>

<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
