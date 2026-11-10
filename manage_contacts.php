<?php
session_start();
include 'db.php';

// Kiểm tra quyền (Chỉ admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (isset($_COOKIE['username']) && isset($_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['full_name'] = $_COOKIE['full_name'] ?? '';
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
        $_SESSION['avatar'] = $_COOKIE['avatar'] ?? '';
        
        if ($_SESSION['role'] !== 'admin') {
            header('Location: dashboard.php');
            exit;
        }
    } else {
        header('Location: login.php');
        exit;
    }
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Xử lý tìm kiếm và phân trang
$search_query = trim($_GET['search_query'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$items_per_page = 15;
$start_from = ($page - 1) * $items_per_page;

$conditions = [];
$params = [];
$param_types = "";

if ($search_query !== '') {
    $search_param = '%' . $search_query . '%';
    $conditions[] = "(full_name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, $search_param));
    $param_types .= "sss";
}

$where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";

// Đếm tổng số dòng
$sql_count = "SELECT COUNT(*) AS total FROM contacts" . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_rows = intval($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();
$total_pages = ceil($total_rows / $items_per_page);

// Truy vấn danh sách
$sql = "SELECT id, full_name, email, message, created_at FROM contacts" . $where_clause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$list_params = array_merge($params, [$items_per_page, $start_from]);
$param_types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$list_params);
$stmt->execute();
$result = $stmt->get_result();
$contacts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = 'Quản lý yêu cầu liên hệ';
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0 text-dark fw-bold">
                            <i class="fas fa-envelope-open-text text-danger me-2"></i>Quản lý liên hệ
                        </h4>
                        <div class="text-muted small mt-1">Danh sách ý kiến, phản hồi hoặc yêu cầu từ người dùng.</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">
                        
                        <!-- Thanh công cụ tìm kiếm -->
                        <form action="" method="GET" class="mb-4">
                            <div class="row g-2">
                                <div class="col-md-6 col-lg-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" name="search_query" class="form-control" placeholder="Tìm theo tên, email, nội dung..." value="<?= h($search_query) ?>">
                                        <button type="submit" class="btn btn-danger px-4">Tìm kiếm</button>
                                    </div>
                                </div>
                                <?php if ($search_query !== ''): ?>
                                    <div class="col-auto d-flex align-items-center">
                                        <a href="manage_contacts.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i>Xóa bộ lọc</a>
                                        <span class="text-muted small ms-3">Tìm thấy <strong><?= $total_rows ?></strong> kết quả.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Bảng dữ liệu -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border table-sm">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th style="width: 60px;">STT</th>
                                        <th style="width: 200px;" class="text-start">Người gửi</th>
                                        <th class="text-start">Nội dung liên hệ</th>
                                        <th style="width: 160px;">Thời gian</th>
                                        <th style="width: 100px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($contacts) > 0): ?>
                                        <?php 
                                        $stt = $start_from + 1;
                                        foreach ($contacts as $row): 
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted"><?= $stt++ ?></td>
                                                <td class="text-start">
                                                    <div class="fw-bold text-dark"><?= h($row['full_name']) ?></div>
                                                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?= h($row['email']) ?></div>
                                                </td>
                                                <td class="text-start">
                                                    <div class="pe-3" style="max-height: 80px; overflow-y: auto;">
                                                        <?= nl2br(h($row['message'])) ?>
                                                    </div>
                                                </td>
                                                <td class="text-center small text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="#" class="btn btn-outline-danger btn-sm px-2 delete-contact-btn" data-id="<?= $row['id'] ?>" title="Xóa liên hệ">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 text-light"></i><br>
                                                Không tìm thấy dữ liệu liên hệ nào.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Phân trang -->
                        <?php if ($total_pages > 1): 
                            $base_url = '?search_query=' . urlencode($search_query) . '&page=';
                        ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $base_url . '1' ?>">&laquo;</a></li>
                                        <li class="page-item"><a class="page-link" href="<?= $base_url . ($page - 1) ?>">&lsaquo;</a></li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2); 
                                    $end = min($total_pages, $start + 4);
                                    if ($end - $start < 4) $start = max(1, $end - 4);
                                    
                                    for ($i = $start; $i <= $end; $i++): ?>
                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= ($i == $page) ? '#' : $base_url . $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $base_url . ($page + 1) ?>">&rsaquo;</a></li>
                                        <li class="page-item"><a class="page-link" href="<?= $base_url . $total_pages ?>">&raquo;</a></li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-contact-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            let id = this.getAttribute('data-id');
            let url = 'delete_contact.php?id=' + encodeURIComponent(id);
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa liên hệ?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa ngay!',
                    cancelButtonText: 'Hủy'
                }).then((res) => { 
                    if (res.isConfirmed) window.location.href = url; 
                });
            } else {
                if (confirm('Bạn có chắc chắn muốn xóa liên hệ này không?')) window.location.href = url;
            }
        });
    });

    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?= json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE) ?>, showConfirmButton: false, timer: 3000 });
        }
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>