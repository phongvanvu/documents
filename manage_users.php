<?php
session_start();

// Kiểm tra quyền (Chỉ Admin mới được vào)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

include 'db.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Cấu hình phân trang & tìm kiếm
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$search_query = '%' . $search . '%';

// ==========================================
// XỬ LÝ LỆNH XÓA (CÓ CHỐNG TỰ XÓA)
// ==========================================
if (isset($_GET['delete'])) {
    $user_id_to_delete = intval($_GET['delete']);
    $current_user_id = intval($_SESSION['user_id']);
    
    if ($user_id_to_delete === $current_user_id) {
        $_SESSION['flash_error'] = "Lỗi bảo mật: Bạn không thể tự xóa tài khoản của chính mình!";
    } else {
        // Lấy thông tin user trước khi xóa để ghi log
        $stmt_get = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_get->bind_param("i", $user_id_to_delete);
        $stmt_get->execute();
        $target_user = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if ($target_user) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                // Ghi Log hệ thống
                $action = "Xóa tài khoản người dùng: '{$target_user['username']}' (ID: {$user_id_to_delete})";
                $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($log_stmt) {
                    $log_stmt->bind_param("is", $current_user_id, $action);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                $_SESSION['flash_msg'] = "Đã xóa tài khoản '{$target_user['username']}' thành công!";
            } else {
                $_SESSION['flash_error'] = "Lỗi hệ thống: Không thể xóa người dùng này.";
            }
            $stmt->close();
        } else {
            $_SESSION['flash_error'] = "Người dùng không tồn tại hoặc đã bị xóa trước đó.";
        }
    }
    
    // Redirect về lại trang
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if ($page > 1) $params['page'] = $page;
    $redirect_url = "manage_users.php" . (!empty($params) ? '?' . http_build_query($params) : '');

    header('Location: ' . $redirect_url);
    exit;
}

// ==========================================
// TRUY VẤN DANH SÁCH NGƯỜI DÙNG
// ==========================================
$query = "SELECT id, username, full_name, role 
          FROM users 
          WHERE username LIKE ? OR full_name LIKE ? 
          ORDER BY id DESC 
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $search_query, $search_query, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Đếm tổng số bản ghi
$total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE username LIKE ? OR full_name LIKE ?");
$total_stmt->bind_param("ss", $search_query, $search_query);
$total_stmt->execute();
$total_records = intval($total_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_stmt->close();

$total_pages = max(1, (int)ceil($total_records / $limit));

$title = "Quản lý Người dùng";
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
                        <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-users-cog me-2 text-danger"></i>QUẢN LÝ NGƯỜI DÙNG</h4>
                        <small class="text-muted">Danh sách tài khoản và phân quyền nhân sự hệ thống</small>
                    </div>
                    <a href="add_user.php" class="btn btn-danger fw-bold"><i class="fas fa-user-plus me-1"></i>Thêm nhân sự mới</a>
                </div>

                <!-- Tìm kiếm -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-3">
                        <form action="manage_users.php" method="GET" class="mb-0">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Nhập tên tài khoản hoặc họ tên cần tìm..." value="<?= e($search) ?>">
                                <button type="submit" class="btn btn-danger px-4 fw-bold">Tìm kiếm</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_users.php" class="btn btn-outline-secondary px-3">Xóa lọc</a>
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
                                        <th style="width: 70px;">ID</th>
                                        <th class="text-start" style="width: 220px;">Tài khoản</th>
                                        <th class="text-start">Họ và tên</th>
                                        <th style="width: 170px;">Vai trò</th>
                                        <th style="width: 160px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $uid = (int)$row['id'];
                                            $uname = $row['username'] ?? '';
                                            $is_me = ($uid === (int)$_SESSION['user_id']);
                                        ?>
                                            <tr>
                                                <td class="text-center fw-bold text-secondary"><?= $uid ?></td>
                                                <td class="text-start fw-semibold text-dark">
                                                    <i class="fas fa-user-circle text-danger me-2"></i><?= e($uname) ?>
                                                </td>
                                                <td class="text-start fw-semibold"><?= e($row['full_name'] ?: 'Chưa cập nhật') ?></td>
                                                <td class="text-center">
                                                    <?php if ($row['role'] === 'admin'): ?>
                                                        <span class="badge bg-danger px-3 py-2"><i class="fas fa-user-shield me-1"></i>Quản trị viên</span>
                                                    <?php elseif ($row['role'] === 'editor'): ?>
                                                        <span class="badge bg-primary px-3 py-2"><i class="fas fa-edit me-1"></i>Biên tập viên</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary px-3 py-2"><i class="fas fa-eye me-1"></i>Người xem</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="edit_user.php?id=<?= $uid ?>" class="btn btn-outline-secondary btn-sm px-2 me-1" title="Sửa"><i class="fas fa-edit me-1"></i>Sửa</a>
                                                    
                                                    <?php if (!$is_me): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm px-2 delete-user-btn" data-id="<?= $uid ?>" data-name="<?= e($uname) ?>"><i class="fas fa-trash-alt me-1"></i>Xóa</button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary btn-sm px-2" disabled title="Bạn không thể xóa chính mình"><i class="fas fa-ban me-1"></i>Xóa</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-users fa-2x mb-2 text-light"></i><br>
                                                <?= empty($search) ? "Không có người dùng nào trong hệ thống." : "Không tìm thấy người dùng phù hợp."; ?>
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

    // Xác nhận xóa SweetAlert2
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let id = this.getAttribute('data-id');
            let name = this.getAttribute('data-name');
            let url = 'manage_users.php?delete=' + id + '<?= !empty($search) ? "&search=" . urlencode($search) : "" ?>' + '<?= $page > 1 ? "&page=" . $page : "" ?>';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa tài khoản này?',
                    text: "Bạn có chắc chắn muốn xóa người dùng '" + name + "' không?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xóa!',
                    cancelButtonText: 'Hủy'
                }).then((res) => { if (res.isConfirmed) window.location.href = url; });
            } else {
                if (confirm("Bạn có chắc chắn muốn xóa người dùng '" + name + "' không?")) window.location.href = url;
            }
        });
    });
});
</script>

<?php 
$stmt->close();
include 'footer.php'; 
?>