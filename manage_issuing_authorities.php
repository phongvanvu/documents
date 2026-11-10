<?php
session_start();

// Chỉ admin được quản lý cơ quan ban hành
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$limit = 10;
$search = trim($_GET['search'] ?? '');
$search_query = '%' . $search . '%';
$page = max(1, intval($_GET['page'] ?? 1));

// ===============================
// XỬ LÝ XÓA CƠ QUAN BAN HÀNH
// ===============================
if (isset($_GET['delete'])) {
    $authority_id = intval($_GET['delete']);

    if ($authority_id <= 0) {
        $_SESSION['flash_error'] = "ID cơ quan ban hành không hợp lệ.";
    } else {
        // Kiểm tra cơ quan ban hành này có đang được văn bản sử dụng không
        $check_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM documents WHERE authority_id = ?");
        $check_stmt->bind_param("i", $authority_id);
        $check_stmt->execute();
        $used_count = intval($check_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $check_stmt->close();

        if ($used_count > 0) {
            $_SESSION['flash_error'] = "Không thể xóa! Cơ quan ban hành này đang được sử dụng bởi {$used_count} văn bản. Vui lòng sử dụng tính năng 'Gộp danh mục' để thay thế.";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM issuing_authorities WHERE id = ?");
            $stmt_delete->bind_param("i", $authority_id);

            if ($stmt_delete->execute()) {
                $_SESSION['flash_msg'] = "Xóa cơ quan ban hành thành công!";
            } else {
                $_SESSION['flash_error'] = "Có lỗi xảy ra khi xóa cơ quan ban hành.";
            }
            $stmt_delete->close();
        }
    }

    // Redirect để tránh F5 bị xóa lại
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if ($page > 1) $params['page'] = $page;
    $redirect_url = 'manage_issuing_authorities.php' . (!empty($params) ? '?' . http_build_query($params) : '');

    header("Location: {$redirect_url}");
    exit;
}

// ===============================
// ĐẾM TỔNG SỐ BẢN GHI & PHÂN TRANG
// ===============================
$total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM issuing_authorities WHERE authority_name LIKE ?");
$total_stmt->bind_param("s", $search_query);
$total_stmt->execute();
$total_records = intval($total_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_stmt->close();

$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// ===============================
// LẤY DANH SÁCH CƠ QUAN BAN HÀNH
// ===============================
$query = "
    SELECT ia.id, ia.authority_name, COUNT(d.id) AS used_count
    FROM issuing_authorities ia
    LEFT JOIN documents d ON d.authority_id = ia.id
    WHERE ia.authority_name LIKE ?
    GROUP BY ia.id, ia.authority_name
    ORDER BY ia.authority_name ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $search_query, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$title = "Quản lý cơ quan ban hành";
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
                        <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-building me-2 text-danger"></i>QUẢN LÝ CƠ QUAN BAN HÀNH</h4>
                        <small class="text-muted">Danh mục cơ quan, đơn vị ban hành văn bản pháp luật</small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="merge_categories.php" class="btn btn-outline-danger fw-bold"><i class="fas fa-object-group me-1"></i>Gộp danh mục</a>
                        <a href="add_issuing_authority.php" class="btn btn-danger fw-bold"><i class="fas fa-plus-circle me-1"></i>Thêm mới</a>
                    </div>
                </div>

                <!-- Ô tìm kiếm -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-3">
                        <form action="manage_issuing_authorities.php" method="GET" class="mb-0">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Nhập tên cơ quan ban hành cần tìm..." value="<?= e($search) ?>">
                                <button type="submit" class="btn btn-danger px-4 fw-bold">Tìm kiếm</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_issuing_authorities.php" class="btn btn-outline-secondary px-3">Xóa lọc</a>
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
                                        <th class="text-start">Tên Cơ Quan Ban Hành</th>
                                        <th style="width: 180px;">Đang sử dụng</th>
                                        <th style="width: 160px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $auth_id = (int)$row['id'];
                                            $auth_name = $row['authority_name'] ?? '';
                                            $used_count = (int)$row['used_count'];
                                        ?>
                                            <tr>
                                                <td class="text-center fw-bold text-secondary"><?= $auth_id ?></td>
                                                <td class="text-start fw-semibold text-dark">
                                                    <i class="fas fa-building text-danger me-2"></i><?= e($auth_name) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($used_count > 0): ?>
                                                        <span class="badge bg-success rounded-pill px-3 py-2"><?= $used_count ?> văn bản</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary rounded-pill px-3 py-2 text-white-50">Chưa sử dụng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="edit_issuing_authority.php?id=<?= $auth_id ?>" class="btn btn-outline-secondary btn-sm px-2 me-1" title="Chỉnh sửa"><i class="fas fa-edit me-1"></i>Sửa</a>

                                                    <?php if ($used_count > 0): ?>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="Swal.fire('Không thể xóa', 'Cơ quan ban hành này đang được sử dụng bởi <?= $used_count ?> văn bản. Hãy dùng tính năng Gộp danh mục!', 'info');"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm px-2 delete-auth-btn" data-id="<?= $auth_id ?>" data-name="<?= e($auth_name) ?>"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fa-2x mb-2 text-light"></i><br>
                                                <?= empty($search) ? "Chưa có dữ liệu cơ quan ban hành nào." : "Không tìm thấy kết quả phù hợp."; ?>
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
    // Flash Messages
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 3000 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: <?= json_encode($_SESSION['flash_error'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 4000 });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    // Nút Xóa với SweetAlert2
    document.querySelectorAll('.delete-auth-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let id = this.getAttribute('data-id');
            let name = this.getAttribute('data-name');
            let url = 'manage_issuing_authorities.php?delete=' + id + '<?= !empty($search) ? "&search=" . urlencode($search) : "" ?>' + '<?= $page > 1 ? "&page=" . $page : "" ?>';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa cơ quan ban hành?',
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