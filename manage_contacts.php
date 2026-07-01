<?php
// Khởi động session
session_start();

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Kiểm tra nếu người dùng chưa đăng nhập và không phải là admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Truy vấn lấy danh sách yêu cầu liên hệ
$sql = "SELECT * FROM contacts ORDER BY created_at DESC";
$result = $conn->query($sql);

// Đưa vào tiêu đề và header
include 'header.php';
?>

<div class="container mt-5">
    <h1>Quản lý yêu cầu liên hệ</h1>

    <!-- Nút tìm kiếm (nếu cần) -->
    <form action="" method="GET" class="mb-4">
        <input type="text" name="search_query" placeholder="Tìm kiếm theo tên hoặc email" class="form-control" style="width: 300px; display: inline-block;">
        <button type="submit" class="btn btn-primary">Tìm kiếm</button>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Tên</th>
                <th>Email</th>
                <th>Thông điệp</th>
                <th>Thời gian gửi</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <a href="delete_contact.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu này không?');">Xóa</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">Không có yêu cầu nào!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Đưa vào footer
include 'footer.php';
?>
