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

// Xử lý xóa người dùng nếu có yêu cầu
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success_message = "Xóa người dùng thành công.";
    } else {
        $error_message = "Có lỗi xảy ra khi xóa người dùng.";
    }
    $stmt->close();
}

// Lấy danh sách người dùng từ cơ sở dữ liệu
$query = "SELECT id, username, role FROM users";
$result = $conn->query($query);
include 'header.php';
?>
<!-- Sidebar -->
<div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
    <?php include 'sidebar.php'; ?>
</div>
<!-- Nội dung chính -->
<div id="content" class="content" class="col py-3">
<div class="col-md-12">
    <h2 class="text-center">Quản lý Người dùng</h2>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php elseif (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="user-management">
        <a href="add_user.php" class="btn btn-success mb-3">Thêm Người dùng mới</a>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên người dùng</th>
                    <th>Vai trò</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                            <a href="manage_users.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('<?php echo htmlspecialchars($row['username']); ?>');">Xóa</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function confirmDelete(userTitle) {
    return confirm('Bạn có chắc chắn muốn xóa người dùng: ' + userTitle + '?');
}
</script>
<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
