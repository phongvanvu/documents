<?php
session_start();

// Kiểm tra quyền (Chỉ Admin mới được vào)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Cấu hình phân trang
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Xử lý tìm kiếm
$search = trim($_GET['search'] ?? '');
$search_query = '%' . $search . '%';

// =========================================================================
// XỬ LÝ LỆNH XÓA (CÓ KIỂM TRA RÀNG BUỘC DỮ LIỆU)
// =========================================================================
if (isset($_GET['delete'])) {
    $type_id = intval($_GET['delete']);
    
    // 1. Kiểm tra xem Loại văn bản này có đang được sử dụng không
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE document_type_id = ?");
    $check_stmt->bind_param("i", $type_id);
    $check_stmt->execute();
    $check_stmt->bind_result($used_count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($used_count > 0) {
        $_SESSION['flash_error'] = "Không thể xóa! Loại văn bản này đang được gắn cho {$used_count} văn bản. Vui lòng sử dụng tính năng 'Gộp danh mục' để thay thế.";
    } else {
        // Xóa an toàn nếu không có bản ghi phụ thuộc
        $stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
        $stmt->bind_param("i", $type_id);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "Xóa loại văn bản thành công!";
        } else {
            $_SESSION['flash_error'] = "Có lỗi hệ thống xảy ra khi xóa.";
        }
        $stmt->close();
    }
    
    // Chuyển hướng lại trang
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if ($page > 1) $params['page'] = $page;
    $redirect_url = "manage_document_types.php" . (!empty($params) ? '?' . http_build_query($params) : '');
    
    header("Location: " . $redirect_url);
    exit;
}

// =========================================================================
// TRUY VẤN DỮ LIỆU (Đếm số lượng văn bản đang gắn loại này)
// =========================================================================
$query = "SELECT dt.id, dt.type_name, COUNT(d.id) AS doc_count 
          FROM document_types dt 
          LEFT JOIN documents d ON dt.id = d.document_type_id 
          WHERE dt.type_name LIKE ? 
          GROUP BY dt.id 
          ORDER BY dt.id DESC 
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $search_query, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Đếm tổng số bản ghi để phân trang
$total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM document_types WHERE type_name LIKE ?");
$total_stmt->bind_param("s", $search_query);
$total_stmt->execute();
$total_records = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_stmt->close();

$total_pages = (int)ceil($total_records / $limit);

$title = "Quản lý Loại Văn bản";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                
                <!-- Page Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-tags me-2 text-danger"></i>QUẢN LÝ LOẠI VĂN BẢN</h4>
                        <small class="text-muted">Danh mục các loại văn bản áp dụng trong hệ thống</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="merge_categories.php" class="btn btn-outline-danger fw-bold"><i class="fas fa-object-group me-1"></i>Gộp danh mục</a>
                        <a href="add_document_type.php" class="btn btn-danger fw-bold"><i class="fas fa-plus-circle me-1"></i>Thêm mới</a>
                    </div>
                </div>

                <!-- Ô tìm kiếm -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-3">
                        <form action="manage_document_types.php" method="GET" class="mb-0">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Nhập tên loại văn bản cần tìm..." value="<?= e($search) ?>">
                                <button type="submit" class="btn btn-danger px-4 fw-bold">Tìm kiếm</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_document_types.php" class="btn btn-outline-secondary px-3">Xóa lọc</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bảng danh sách -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th class="text-start">Tên Loại Văn Bản</th>
                                        <th style="width: 180px;">Đang sử dụng</th>
                                        <th style="width: 160px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-center fw-bold text-secondary"><?= (int)$row['id'] ?></td>
                                                <td class="text-start fw-semibold text-dark">
                                                    <i class="fas fa-tag text-danger me-2"></i><?= e($row['type_name']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['doc_count'] > 0): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-2"><?= (int)$row['doc_count'] ?> văn bản</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary rounded-pill px-3 py-2 text-white-50">Chưa sử dụng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="edit_document_type.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-secondary btn-sm px-2 me-1" title="Chỉnh sửa"><i class="fas fa-edit me-1"></i>Sửa</a>
                                                    
                                                    <?php if ($row['doc_count'] > 0): ?>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="Swal.fire('Không thể xóa', 'Loại văn bản này đang được dùng cho <?= $row['doc_count'] ?> văn bản. Hãy dùng tính năng Gộp danh mục!', 'info');"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm px-2 delete-type-btn" data-id="<?= (int)$row['id'] ?>" data-name="<?= e($row['type_name']) ?>"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fa-2x mb-2 text-light"></i><br>
                                                <?= empty($search) ? "Chưa có dữ liệu loại văn bản nào." : "Không tìm thấy kết quả phù hợp."; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=1">&laquo;</a></li>
                                <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">&lsaquo;</a></li>
                            <?php endif; ?>
                            
                            <?php 
                            $start = max(1, $page - 2); $end = min($total_pages, $start + 4);
                            if ($end - $start < 4) $start = max(1, $end - 4);
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= ($i == $page) ? '#' : '?search=' . urlencode($search) . '&page=' . $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">&rsaquo;</a></li>
                                <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $total_pages ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Thông báo Flash Messages
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 3000 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_error'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: <?= json_encode($_SESSION['flash_error'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 4000 });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    // Nút Xóa với SweetAlert2
    document.querySelectorAll('.delete-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let id = this.getAttribute('data-id');
            let name = this.getAttribute('data-name');
            let url = 'manage_document_types.php?delete=' + id + '<?= !empty($search) ? "&search=" . urlencode($search) : "" ?>' + '<?= $page > 1 ? "&page=" . $page : "" ?>';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa loại văn bản?',
                    text: "Bạn có chắc chắn muốn xóa '" + name + "' không?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xóa!',
                    cancelButtonText: 'Hủy'
                }).then((res) => { if (res.isConfirmed) window.location.href = url; });
            } else {
                if (confirm("Bạn có chắc chắn muốn xóa '" + name + "' không?")) window.location.href = url;
            }
        });
    });
});
</script>

<?php 
$stmt->close();
include 'footer.php'; 
?>