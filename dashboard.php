<?php
// Khởi động session
session_start();

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['full_name'] = $_COOKIE['full_name'];
        $_SESSION['role'] = $_COOKIE['role'];
        $_SESSION['avatar'] = $_COOKIE['avatar'];
    } else {
        header('Location: login.php');
        exit;
    }
}

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Khách';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'viewer';

// Kết nối tới cơ sở dữ liệu và lấy thông tin avatar
$sql = "SELECT avatar FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$_SESSION['avatar'] = ($result->num_rows > 0) ? $result->fetch_assoc()['avatar'] : "uploads/avatars/default.jpg";

// Kiểm tra và lấy dữ liệu từ form
$search_query = htmlspecialchars($_GET['search_query'] ?? '', ENT_QUOTES, 'UTF-8');
$search_column = htmlspecialchars($_GET['search_column'] ?? '', ENT_QUOTES, 'UTF-8');
$document_type_filter = $_GET['document_type_id'] ?? '';

// Số lượng mục trên mỗi trang
$items_per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

// Cấu trúc cơ bản của câu truy vấn
$sql_base = "SELECT d.id, d.document_number,
            CONCAT(dt.type_name, ' - ', d.document_number, ' - ', d.content) AS content,
            d.issued_date, d.file_path
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.id";

// Xây dựng điều kiện tìm kiếm
$where_clause = "";
$params = [];
$param_types = "";
$search_param = '%' . $search_query . '%';

if (empty($search_column)) {
	// Tìm kiếm tất cả các cột
    $where_clause = " WHERE (document_number LIKE ? OR content LIKE ? OR issued_date LIKE ? OR file_path LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params = array_fill(0, 4, $search_param); // 4 lần cho 4 cột
	$param_types = "ssss";
} elseif (in_array($search_column, ['document_number', 'content', 'issued_date', 'file_path'])) {
    // Tìm kiếm theo cột cụ thể
    $where_clause = " WHERE $search_column LIKE ?";
    $params = ["%$search_query%"];
    $param_types = "s";
} else {
    die("Cột không hợp lệ!");
}

// Lọc theo loại văn bản
if (!empty($document_type_filter)) {
    $where_clause .= " AND document_type_id = ?";
    $params[] = $document_type_filter;
	$param_types .= "s";
}

// Tính tổng số mục
$sql_count = "SELECT COUNT(*) AS total FROM documents" . $where_clause;
$stmt = $conn->prepare($sql_count);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];

// Truy vấn để lấy kết quả cho trang hiện tại
$sql = $sql_base . $where_clause . " ORDER BY issued_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;  // Thêm limit vào mảng tham số
$params[] = $start_from; // Thêm offset vào mảng tham số
$param_types .= "ii"; // Thêm kiểu dữ liệu cho limit và offset

$stmt = $conn->prepare("$sql");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$documents = $result->fetch_all(MYSQLI_ASSOC);

// Thiết lập tiêu đề trang
$title = "Trang chính";
include 'header.php';
?>
<div class="container-fluid main">
    <div class="row flex-nowrap">
        <?php include 'check_backup.php'; ?>
        <!-- Sidebar -->
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        <!-- Nội dung chính -->
        <div id="content" class="content" class="col py-3">
            <!-- Form tìm kiếm và bảng văn bản trong cùng một cột -->
            <div class="col-md-12 mt-3">
                <!-- Form tìm kiếm -->
                <form action="" method="GET" class="mb-4">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" name="search_query" class="form-control" placeholder="Tìm kiếm tài liệu..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="search_column" class="form-control">
                                <option value="">Tất cả các cột</option>
                                <?php foreach (['document_number' => 'Số hiệu', 'content' => 'Trích yếu', 'issued_date' => 'Ngày ban hành', 'file_path' => 'Đường dẫn file'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($search_column === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="document_type_id" name="document_type_id">
                                <option value="">Chọn loại văn bản</option>
                                <?php 
                                    // Lấy tất cả loại văn bản
                                    $sql_types = "SELECT id, type_name FROM document_types ORDER BY type_name";
                                    $types_result = $conn->query($sql_types);
                                    while ($type = $types_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($type['id']); ?>" <?php echo (isset($_GET['document_type_id']) && $_GET['document_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?>
                                        </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
                        </div>
                    </div>
                </form>

                <!-- Danh sách tài liệu -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">Nội dung</th>
                                <th class="text-center">Ngày ban hành</th>
                                <th class="text-center">Tập tin</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($documents) > 0): ?>
                                <?php foreach ($documents as $document):
                                    $formatted_date = date('d/m/Y', strtotime(htmlspecialchars($document['issued_date'])));
                                    $file_type = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                    $icon = ($file_type == 'pdf') ? '<i class="fas fa-file-pdf file-icon text-danger"></i>' :
                                            (($file_type == 'doc' || $file_type == 'docx') ? '<i class="fas fa-file-word file-icon text-primary"></i>' : '<i class="fas fa-file file-icon"></i>');
                                    ?>
                                    <tr>
                                        <td class="document-list justify-content">
                                            <a href="<?php echo $document['file_path']; ?>" target="_blank" class="document-link">
                                                <?php echo htmlspecialchars($document['content']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center"><?php echo $formatted_date; ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($document['file_path'])): ?>
                                                <a href="<?php echo $document['file_path']; ?>" target="_blank"><?php echo $icon; ?></a>
                                            <?php else: ?>
                                                Không có file
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="actions">
                                                <a href="view_document.php?id=<?php echo $document['id']; ?>" class="btn btn-primary btn-sm">Xem</a>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <a href="edit_document.php?id=<?php echo $document['id']; ?>" class="btn btn-primary btn-sm">Sửa</a>
                                                    <a href="delete_document.php?id=<?php echo $document['id']; ?>" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $document['id']; ?>">Xóa</a>
                                                    <script>
                                                        // Chọn tất cả các nút xóa
                                                        document.querySelectorAll('.delete-btn').forEach(button => {
                                                            button.addEventListener('click', function(event) {
                                                                event.preventDefault(); // Ngăn hành động mặc định xảy ra
                                                                let documentId = this.getAttribute('data-id');

                                                                // Hiển thị hộp thoại xác nhận với SweetAlert
                                                                Swal.fire({
                                                                    title: 'Xác nhận xóa',
                                                                    text: "Bạn có chắc chắn muốn xóa tài liệu này không?",
                                                                    icon: 'warning',
                                                                    showCancelButton: true,
                                                                    confirmButtonColor: '#3085d6',
                                                                    cancelButtonColor: '#d33',
                                                                    confirmButtonText: 'Có, xóa!',
                                                                    cancelButtonText: 'Không'
                                                                }).then((result) => {
                                                                    if (result.isConfirmed) {
                                                                        // Nếu người dùng chọn "Có", chuyển hướng tới trang xử lý PHP
                                                                        window.location.href = 'delete_document.php?id=' + documentId;
                                                                    }
                                                                });
                                                            });
                                                        });
                                                    </script>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">Không tìm thấy kết quả nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php
                $total_pages = ceil($total_rows / $items_per_page);
                if ($total_pages > 1):
                    $range = 2; // Số trang hiển thị trước và sau trang hiện tại
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);
                ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <!-- Trang đầu & Trang trước -->
                        <?php 
                        if ($page > 1): ?>
                            <li>
                                <a class="page-link" href="?page=1&search_query=<?php echo urlencode($search_query) ?>&search_column=<?php echo urlencode($search_column) ?>&document_type_id=<?php echo urlencode($document_type_filter) ?>">&laquo;</a>
                            </li>
                            <li>
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search_query=<?php echo htmlspecialchars($search_query); ?>&search_column=<?php echo htmlspecialchars($search_column); ?>&document_type_id=<?php echo htmlspecialchars($document_type_filter);?>">&lsaquo;</a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Các trang -->
                        <?php 
                        // Tính toán lại phạm vi hiển thị trang
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $start + 4); 

                        // Điều chỉnh bắt đầu nếu cần
                        if ($end - $start < 4) {
                            $start = max(1, $end - 4); // Đảm bảo có 5 trang
                        }

                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?>
                                    <?php echo $i; ?>
                                <?php else: ?>
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search_query=<?php echo htmlspecialchars($search_query); ?>&search_column=<?php echo htmlspecialchars($search_column); ?>&document_type_id=<?php echo htmlspecialchars($document_type_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Trang sau & Trang cuối -->
                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search_query=<?php echo htmlspecialchars($search_query); ?>&search_column=<?php echo htmlspecialchars($search_column); ?>&document_type_id=<?php echo htmlspecialchars($document_type_filter); ?>">&rsaquo;</a>
                            </li>
                            <li>
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&search_query=<?php echo urlencode($search_query); ?>&search_column=<?php echo urlencode($search_column); ?>&document_type_id=<?php echo urlencode($document_type_filter); ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>