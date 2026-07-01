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

// Lấy danh sách các bản ghi từ cơ sở dữ liệu
$query = "SELECT logs.id, users.username, logs.action, logs.created_at
          FROM logs 
          JOIN users ON logs.user_id = users.id 
          ORDER BY logs.created_at DESC";
$result = $conn->query($query);
include 'header.php';
?>

    <h2 class="mt-5 text-center">Nhật ký Hoạt động Hệ thống</h2>

    <div class="logs-table mt-4">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên người dùng</th>
                    <th>Hành động</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['action']); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="btn btn-secondary">Quay lại Bảng điều khiển</a>
<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
