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

// Khởi tạo biến để chứa thông báo lỗi hoặc thành công
$success_message = '';
$error_message = '';

// Kiểm tra nếu có yêu cầu sửa cơ quan ban hành
if (isset($_GET['id'])) {
    $authority_id = intval($_GET['id']);

    // Truy vấn thông tin cơ quan ban hành
    $stmt = $conn->prepare("SELECT id, authority_name FROM issuing_authorities WHERE id = ?");
    $stmt->bind_param("i", $authority_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $authority = $result->fetch_assoc();
    } else {
        $error_message = 'Cơ quan ban hành không tồn tại.';
    }

    $stmt->close();
} else {
    $error_message = 'ID cơ quan ban hành không được cung cấp.';
}

// Xử lý lưu thông tin khi form được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authority_name = trim($_POST['authority_name']);
    
    if (empty($authority_name)) {
        $error_message = 'Tên cơ quan ban hành không được để trống.';
    } else {
        // Cập nhật thông tin cơ quan ban hành
        $stmt = $conn->prepare("UPDATE issuing_authorities SET authority_name = ? WHERE id = ?");
        $stmt->bind_param("si", $authority_name, $authority_id);
        
        if ($stmt->execute()) {
            // Chuyển hướng về trang quản lý sau khi cập nhật thành công
            header('Location: manage_issuing_authorities.php?success=1');
            exit;
        } else {
            $error_message = 'Có lỗi xảy ra khi cập nhật cơ quan ban hành.';
        }
        
        $stmt->close();
    }
}

include 'header.php';
?>

<a href="dashboard.php" class="btn btn-secondary mb-3" style="margin-top: 20px;">Quay lại Bảng điều khiển</a>

<h2 class="mt-3 text-center">Chỉnh sửa Cơ quan Ban hành</h2>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($authority)): ?>
    <form action="edit_issuing_authority.php?id=<?php echo $authority_id; ?>" method="POST">
        <div class="form-group">
            <label for="authority_name">Tên Cơ quan Ban hành:</label>
            <input type="text" id="authority_name" name="authority_name" class="form-control" value="<?php echo htmlspecialchars($authority['authority_name']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Lưu Thay Đổi</button>
    </form>
<?php endif; ?>

<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
