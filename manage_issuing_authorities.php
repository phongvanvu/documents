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

// Thiết lập số lượng bản ghi trên mỗi trang
$limit = 10;

// Lấy số trang hiện tại, mặc định là 1 nếu không có tham số page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;  // Tính toán vị trí bắt đầu của bản ghi

// Xử lý tìm kiếm
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = '%' . $search . '%';

// Xử lý xóa cơ quan ban hành nếu có yêu cầu
if (isset($_GET['delete'])) {
    $authority_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM issuing_authorities WHERE id = ?");
    $stmt->bind_param("i", $authority_id);
    if ($stmt->execute()) {
        $success_message = "Xóa cơ quan ban hành thành công.";
    } else {
        $error_message = "Có lỗi xảy ra khi xóa cơ quan ban hành.";
    }
    $stmt->close();
}

// Truy vấn danh sách cơ quan ban hành với tìm kiếm và phân trang
$query = "SELECT id, authority_name FROM issuing_authorities WHERE authority_name LIKE ? LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $search_query, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Đếm tổng số bản ghi để tạo phân trang với tìm kiếm
$total_query = "SELECT COUNT(*) AS total FROM issuing_authorities WHERE authority_name LIKE ?";
$total_stmt = $conn->prepare($total_query);
$total_stmt->bind_param("s", $search_query);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit); // Tổng số trang

include 'header.php';
?>
<!-- Sidebar -->
<div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
    <?php include 'sidebar.php'; ?>
</div>
<!-- Nội dung chính -->
<div id="content" class="content" class="col py-3">
<h2 class="mt-3 text-center">Quản lý Cơ quan Ban hành</h2>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php elseif (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Form tìm kiếm -->
<form action="manage_issuing_authorities.php" method="GET" class="mb-4">
    <div class="row">
        <div class="col-md-10">
            <div class="form-group">
                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm cơ quan ban hành" value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-block">Tìm kiếm</button>
        </div>
    </div>
</form>

<div class="issuing-authority-management">
    <a href="add_issuing_authority.php" class="btn btn-success mb-3">Thêm Cơ quan Ban hành mới</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên Cơ quan Ban hành</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['authority_name']); ?></td>
                        <td>
                            <a href="edit_issuing_authority.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                            <a href="manage_issuing_authorities.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('<?php echo $row['authority_name']; ?>');">Xóa</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">Không có cơ quan ban hành nào.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Phân trang -->
<?php if ($total_pages > 1): ?>  <!-- Kiểm tra nếu tổng số trang > 1 thì mới hiển thị phân trang -->
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>  <!-- Kết thúc kiểm tra tổng số trang -->

<script>
function confirmDelete(authorityName) {
    return confirm('Bạn có chắc chắn muốn xóa cơ quan ban hành: ' + authorityName + '?');
}
</script>
</div>
<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
