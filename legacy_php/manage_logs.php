<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Khôi phục session từ cookie nếu cần
if (!isset($_SESSION['user_id']) && isset($_COOKIE['username'], $_COOKIE['user_id'])) {
    $_SESSION['user_name'] = $_COOKIE['username'];
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
}

// Kiểm tra quyền: Admin hoặc người có quyền logs.view
if (!isset($_SESSION['user_id']) || (!has_permission('logs.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang này.";
    header('Location: documents.php');
    exit;
}

// Tự động tạo bảng nếu chưa có
$conn->query("CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

function h($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

$user_id_current = (int)$_SESSION['user_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// =========================================================================
// 1. XỬ LÝ DỌN DẸP NHẬT KÝ (LOG ROTATION) - DÀNH CHO ADMIN
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    // Dọn dẹp log theo số ngày (>30, >90, >180)
    if (isset($_POST['clear_days'])) {
        $days = intval($_POST['clear_days']);
        if ($days > 0) {
            $stmt_del = $conn->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt_del->bind_param("i", $days);
            if ($stmt_del->execute()) {
                $deleted = $stmt_del->affected_rows;
                $stmt_del->close();

                // Ghi log chính hành động dọn dẹp này
                $log_act = "Dọn dẹp $deleted bản ghi nhật ký hệ thống cũ hơn $days ngày";
                $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($l) {
                    $l->bind_param("is", $user_id_current, $log_act);
                    $l->execute();
                    $l->close();
                }

                $_SESSION['flash_msg'] = "Đã dọn dẹp thành công $deleted bản ghi nhật ký cũ hơn $days ngày!";
            }
        }
        header('Location: manage_logs.php');
        exit;
    }

    // Xóa trắng toàn bộ log
    if (isset($_POST['clear_all_logs'])) {
        if ($conn->query("TRUNCATE TABLE logs")) {
            $log_act = "Làm sạch toàn bộ dữ liệu nhật ký hệ thống";
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id_current, $log_act);
                $l->execute();
                $l->close();
            }
            $_SESSION['flash_msg'] = "Đã làm sạch toàn bộ nhật ký hệ thống!";
        }
        header('Location: manage_logs.php');
        exit;
    }
}

// =========================================================================
// 2. XÂY DỰNG BỘ LỌC TÌM KIẾM
// =========================================================================
$search_query = trim($_GET['search'] ?? '');
$user_filter = intval($_GET['user_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$conditions = ["1=1"];
$params = [];
$param_types = "";

if ($search_query !== '') {
    $search_param = '%' . $search_query . '%';
    $conditions[] = "(l.action LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    array_push($params, $search_param, $search_param, $search_param);
    $param_types .= "sss";
}

if ($user_filter > 0) {
    $conditions[] = "l.user_id = ?";
    $params[] = $user_filter;
    $param_types .= "i";
}

if ($date_from !== '') {
    $conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to !== '') {
    $conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = " WHERE " . implode(" AND ", $conditions);

// =========================================================================
// 3. XUẤT DỮ LIỆU LOG SANG CSV NẾU CÓ YÊU CẦU
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Thêm BOM UTF-8 để mở tiếng Việt không bị lỗi font trên Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['STT', 'Tai khoan', 'Ho va ten', 'Vai tro', 'Noi dung thao tac', 'Thoi gian']);

    $sql_export = "SELECT l.id, l.action, l.created_at, u.username, u.full_name, u.role 
                   FROM logs l 
                   LEFT JOIN users u ON l.user_id = u.id 
                   $where_clause 
                   ORDER BY l.id DESC";
    $stmt_exp = $conn->prepare($sql_export);
    if (!empty($params)) {
        $stmt_exp->bind_param($param_types, ...$params);
    }
    $stmt_exp->execute();
    $res_exp = $stmt_exp->get_result();
    $stt = 1;
    while ($r = $res_exp->fetch_assoc()) {
        fputcsv($output, [
            $stt++,
            $r['username'] ?? 'system',
            $r['full_name'] ?? 'Hệ thống',
            $r['role'] ?? 'system',
            $r['action'],
            date('d/m/Y H:i:s', strtotime($r['created_at']))
        ]);
    }
    $stmt_exp->close();
    fclose($output);
    exit;
}

// =========================================================================
// 4. PHÂN TRANG & TRUY VẤN DỮ LIỆU
// =========================================================================
$items_per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

// Đếm tổng số dòng
$sql_count = "SELECT COUNT(*) as total FROM logs l LEFT JOIN users u ON l.user_id = u.id" . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_rows = intval($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();

// Truy vấn danh sách logs
$sql = "SELECT l.id, l.action, l.created_at, u.username, u.full_name, u.role, u.avatar 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $where_clause 
        ORDER BY l.id DESC 
        LIMIT ? OFFSET ?";

$list_params = array_merge($params, [$items_per_page, $start_from]);
$param_types_list = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types_list, ...$list_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy danh sách users phục vụ select box
$users_list = $conn->query("SELECT id, username, full_name FROM users ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);

// Link xuất CSV
$export_csv_url = 'manage_logs.php?' . http_build_query([
    'search' => $search_query,
    'user_id' => $user_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'export' => 'csv'
]);

$title = "Nhật ký hệ thống";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Nút tác vụ -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="mb-0 text-dark fw-bold">
                            <i class="fas fa-clipboard-list text-danger me-2"></i>Nhật ký Hoạt động Hệ thống
                        </h4>
                        <div class="text-muted small">Giám sát & truy vết lịch sử Thao tác văn bản, Phân quyền và Bảo trì hệ thống</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <!-- Nút Xuất CSV -->
                        <a href="<?= h($export_csv_url) ?>" class="btn btn-outline-success btn-sm shadow-sm fw-semibold">
                            <i class="fas fa-file-csv me-1"></i>Xuất file CSV
                        </a>

                        <!-- Dropdown Dọn dẹp -->
                        <?php if ($is_admin): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-danger btn-sm dropdown-toggle shadow-sm fw-semibold" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-broom me-1"></i>Dọn dẹp nhật ký
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <form action="manage_logs.php" method="POST" class="form-clean-log" data-title="Dọn dẹp log cũ hơn 30 ngày?">
                                            <input type="hidden" name="clear_days" value="30">
                                            <button type="submit" class="dropdown-item"><i class="fas fa-clock me-2 text-warning"></i>Xóa log > 30 ngày</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="manage_logs.php" method="POST" class="form-clean-log" data-title="Dọn dẹp log cũ hơn 90 ngày?">
                                            <input type="hidden" name="clear_days" value="90">
                                            <button type="submit" class="dropdown-item"><i class="fas fa-history me-2 text-warning"></i>Xóa log > 90 ngày</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="manage_logs.php" method="POST" class="form-clean-log" data-title="Dọn dẹp log cũ hơn 180 ngày?">
                                            <input type="hidden" name="clear_days" value="180">
                                            <button type="submit" class="dropdown-item"><i class="fas fa-calendar-alt me-2 text-warning"></i>Xóa log > 180 ngày</button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="manage_logs.php" method="POST" id="formClearAllLogs">
                                            <input type="hidden" name="clear_all_logs" value="1">
                                            <button type="submit" class="dropdown-item text-danger fw-bold"><i class="fas fa-trash-alt me-2"></i>Xóa sạch toàn bộ log</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BỘ LỌC TÌM KIẾM -->
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body p-3">
                        <form action="manage_logs.php" method="GET" class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Tìm theo thao tác, user..." value="<?= h($search_query) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="user_id" class="form-select form-select-sm">
                                    <option value="0">-- Tất cả tài khoản --</option>
                                    <?php foreach ($users_list as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= ($user_filter === (int)$u['id']) ? 'selected' : '' ?>>
                                            <?= h($u['username']) ?><?= !empty($u['full_name']) ? ' (' . h($u['full_name']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control form-control-sm" title="Từ ngày" value="<?= h($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control form-control-sm" title="Đến ngày" value="<?= h($date_to) ?>">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-danger px-3"><i class="fas fa-filter me-1"></i>Lọc</button>
                                <a href="manage_logs.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Đặt lại</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BẢNG DANH SÁCH NHẬT KÝ -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-list-ol text-secondary me-2"></i>Lịch sử thao tác
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= number_format($total_rows) ?> bản ghi
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-center text-secondary">
                                    <tr>
                                        <th style="width: 50px;">STT</th>
                                        <th class="text-start ps-3" style="width: 240px;">Người thực hiện</th>
                                        <th class="text-start">Nội dung thao tác</th>
                                        <th style="width: 180px;">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($logs) > 0): ?>
                                        <?php $i = $start_from + 1; foreach ($logs as $log): 
                                            $action_text = h($log['action']);
                                            $u_role = $log['role'] ?? 'viewer';
                                            $role_badge = ($u_role === 'admin') ? 'bg-danger' : (($u_role === 'editor') ? 'bg-primary' : 'bg-secondary');
                                            $avatar = !empty($log['avatar']) ? $log['avatar'] : 'uploads/avatars/default.jpg';

                                            // Gán icon trực quan theo loại thao tác
                                            $icon = '<i class="fas fa-info-circle text-primary"></i>';
                                            if (stripos($action_text, 'thêm') !== false || stripos($action_text, 'tạo') !== false) {
                                                $icon = '<i class="fas fa-plus-circle text-success"></i>';
                                            } elseif (stripos($action_text, 'sửa') !== false || stripos($action_text, 'cập nhật') !== false || stripos($action_text, 'gộp') !== false) {
                                                $icon = '<i class="fas fa-edit text-warning"></i>';
                                            } elseif (stripos($action_text, 'xóa') !== false || stripos($action_text, 'hủy') !== false || stripos($action_text, 'thùng rác') !== false) {
                                                $icon = '<i class="fas fa-trash-alt text-danger"></i>';
                                            } elseif (stripos($action_text, 'sao lưu') !== false || stripos($action_text, 'phục hồi') !== false || stripos($action_text, 'tối ưu') !== false) {
                                                $icon = '<i class="fas fa-database text-info"></i>';
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= h($avatar) ?>" alt="Avatar" class="rounded-circle me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-bold text-dark mb-0"><?= h($log['full_name'] ?? ($log['username'] ?? 'Hệ thống')) ?></div>
                                                            <div class="d-flex align-items-center gap-1">
                                                                <span class="text-muted" style="font-size: 0.75rem;">@<?= h($log['username'] ?? 'system') ?></span>
                                                                <span class="badge <?= $role_badge ?>" style="font-size: 0.6rem;"><?= h($u_role) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-start">
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2 fs-6"><?= $icon ?></span>
                                                        <span class="fw-medium text-dark"><?= $action_text ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center text-muted">
                                                    <i class="far fa-clock me-1 text-secondary"></i><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="fas fa-search fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Không tìm thấy bản ghi nhật ký nào.</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Phân trang -->
                <?php 
                $total_pages = (int)ceil($total_rows / $items_per_page);
                if ($total_pages > 1): 
                    $base_url = 'manage_logs.php?' . http_build_query(['search' => $search_query, 'user_id' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to]) . '&page=';
                ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . '1'); ?>">&laquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . ($page - 1)); ?>">&lsaquo;</a></li>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2); $end = min($total_pages, $start + 4);
                            if ($end - $start < 4) $start = max(1, $end - 4);
                            for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?= ($p === $page) ? '#' : h($base_url . $p); ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . ($page + 1)); ?>">&rsaquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . $total_pages); ?>">&raquo;</a></li>
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
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 3500 });
        }
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    // Xử lý xác nhận dọn dẹp log cũ
    document.querySelectorAll('.form-clean-log').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const titleText = this.getAttribute('data-title');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: titleText,
                    text: 'Thao tác này sẽ xóa các bản ghi lịch sử cũ và không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xóa',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) form.submit(); });
            } else {
                if (confirm(titleText)) form.submit();
            }
        });
    });

    // Xử lý xóa sạch toàn bộ log
    const formAll = document.getElementById('formClearAllLogs');
    if (formAll) {
        formAll.addEventListener('submit', function(e) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa TOÀN BỘ nhật ký?',
                    text: 'Toàn bộ dữ liệu lịch sử hoạt động hệ thống sẽ bị xóa sạch hoàn toàn!',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xác nhận xóa sạch',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) formAll.submit(); });
            } else {
                if (confirm('Bạn có chắc chắn muốn xóa toàn bộ lịch sử nhật ký không?')) formAll.submit();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>