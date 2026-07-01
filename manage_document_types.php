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

// Xử lý xóa loại văn bản nếu có yêu cầu
if (isset($_GET['delete'])) {
    $document_type_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
    $stmt->bind_param("i", $document_type_id);
    if ($stmt->execute()) {
        $success_message = "Xóa loại văn bản thành công.";
    } else {
        $error_message = "Có lỗi xảy ra khi xóa loại văn bản.";
    }
    $stmt->close();
}

// Truy vấn danh sách loại văn bản với tìm kiếm và phân trang
$query = "SELECT id, type_name FROM document_types WHERE type_name LIKE ? LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $search_query, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Đếm tổng số bản ghi để tạo phân trang với tìm kiếm
$total_query = "SELECT COUNT(*) AS total FROM document_types WHERE type_name LIKE ?";
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

<h2 class="mt-3 text-center">Quản lý Loại Văn bản</h2>

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
<form action="manage_document_types.php" method="GET" class="mb-4">
    <div class="row">
        <div class="col-md-10">
            <div class="form-group">
                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm loại văn bản" value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-block">Tìm kiếm</button>
        </div>
    </div>
</form>

<div class="document-type-management">
    <a href="add_document_type.php" class="btn btn-success mb-3">Thêm Loại Văn bản mới</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên Loại Văn bản</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                        <td>
                            <a href="edit_document_type.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Sửa</a>
                            <a href="manage_document_types.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('<?php echo $row['type_name']; ?>');">Xóa</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">Không có loại văn bản nào.</td>
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
                <?php if ($i == $page): ?>
                    <?php echo $i; ?>
                <?php else: ?>
                    <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
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
function confirmDelete(documentTitle) {
    return confirm('Bạn có chắc chắn muốn xóa loại văn bản: ' + documentTitle + '?');
}
</script>
</div>
<?php
include 'footer.php';

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
