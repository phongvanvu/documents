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

// Kiểm tra quyền
if (!isset($_SESSION['user_id']) || (!has_permission('documents.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang quản lý phiên bản.";
    header('Location: documents.php');
    exit;
}

$can_edit = has_permission('documents.edit') || (($_SESSION['role'] ?? '') === 'admin');

// =========================================================================
// TỰ ĐỘNG BỔ SUNG CỘT THIẾU TRONG BẢNG CŨ (AUTO-MIGRATION)
// =========================================================================
$conn->query("CREATE TABLE IF NOT EXISTS document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_number VARCHAR(20) NOT NULL DEFAULT '1.0',
    file_path VARCHAR(255) NULL,
    content_summary TEXT NULL,
    change_log TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$existing_cols = [];
$res_cols = $conn->query("SHOW COLUMNS FROM document_versions");
if ($res_cols) {
    while ($c = $res_cols->fetch_assoc()) {
        $existing_cols[] = $c['Field'];
    }
}

if (!in_array('content_summary', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_versions ADD COLUMN content_summary TEXT NULL AFTER file_path");
}
if (!in_array('change_log', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_versions ADD COLUMN change_log TEXT NULL AFTER content_summary");
}
if (!in_array('created_by', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_versions ADD COLUMN created_by INT NULL AFTER change_log");
}
if (!in_array('created_at', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_versions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// =========================================================================
// BỘ LỌC TÌM KIẾM
// =========================================================================
$search_query = trim($_GET['search'] ?? '');
$doc_filter = intval($_GET['doc_id'] ?? 0);

$conditions = ["1=1"];
$params = [];
$param_types = "";

if ($search_query !== '') {
    $search_param = '%' . $search_query . '%';
    $conditions[] = "(d.document_number LIKE ? OR COALESCE(v.change_log, '') LIKE ? OR COALESCE(v.content_summary, '') LIKE ? OR u.username LIKE ?)";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $param_types .= "ssss";
}

if ($doc_filter > 0) {
    $conditions[] = "v.document_id = ?";
    $params[] = $doc_filter;
    $param_types .= "i";
}

$where_clause = " WHERE " . implode(" AND ", $conditions);

// Phân trang
$items_per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total 
              FROM document_versions v 
              LEFT JOIN documents d ON v.document_id = d.id 
              LEFT JOIN users u ON v.created_by = u.id 
              $where_clause";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_rows = (int)($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();

// Lấy danh sách phiên bản
$sql = "SELECT v.id, v.document_id, v.version_number, v.file_path, v.content_summary, v.change_log, v.created_at, 
               d.document_number, d.content AS current_content, 
               u.username, u.full_name, u.avatar 
        FROM document_versions v 
        LEFT JOIN documents d ON v.document_id = d.id 
        LEFT JOIN users u ON v.created_by = u.id 
        $where_clause 
        ORDER BY v.id DESC 
        LIMIT ? OFFSET ?";

$list_params = array_merge($params, [$items_per_page, $start_from]);
$param_types_full = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types_full, ...$list_params);
$stmt->execute();
$versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "Lịch sử Phiên bản & Đảo ngược Dữ liệu";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-history text-danger me-2"></i>Lịch Sử Phiên Bản & Đảo Ngược Dữ Liệu
                        </h4>
                        <div class="text-muted small">Kiểm soát các lần thay đổi nội dung, tệp tin và khôi phục văn bản về trạng thái trước đó</div>
                    </div>
                </div>

                <!-- BỘ LỌC TÌM KIẾM -->
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body p-3">
                        <form method="GET" action="manage_versions.php" class="row g-2 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Tìm theo số hiệu văn bản, người sửa đổi, ghi chú thay đổi..." value="<?= h($search_query) ?>">
                                </div>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-danger px-3"><i class="fas fa-filter me-1"></i>Lọc</button>
                                <a href="manage_versions.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Đặt lại</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BẢNG DANH SÁCH PHIÊN BẢN -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-code-branch text-secondary me-2"></i>Lịch sử các phiên bản đã lưu
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= number_format($total_rows) ?> mốc phiên bản
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">STT</th>
                                        <th style="width: 170px;">Số hiệu văn bản</th>
                                        <th style="width: 110px;" class="text-center">Phiên bản</th>
                                        <th>Trích yếu & Ghi chú chỉnh sửa</th>
                                        <th style="width: 180px;">Người lưu / Thời gian</th>
                                        <th style="width: 140px;" class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($versions) > 0): ?>
                                        <?php 
                                        $i = $start_from + 1;
                                        foreach ($versions as $row): 
                                            $v_file = $row['file_path'];
                                            $has_file = !empty($v_file) && file_exists($v_file);
                                            $avatar = !empty($row['avatar']) ? $row['avatar'] : 'uploads/avatars/default.jpg';
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td>
                                                    <a href="view_document.php?id=<?= (int)$row['document_id'] ?>" target="_blank" class="fw-bold text-danger text-decoration-none">
                                                        <?= h($row['document_number'] ?: "Văn bản #{$row['document_id']}") ?>
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 font-monospace">
                                                        v<?= h($row['version_number']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-dark fw-medium mb-1 text-truncate" style="max-width: 420px;" title="<?= h($row['content_summary']) ?>">
                                                        <?= h($row['content_summary'] ?: $row['current_content']) ?>
                                                    </div>
                                                    <div class="text-muted small fst-italic">
                                                        <i class="fas fa-pen-nib text-secondary me-1"></i><?= h($row['change_log'] ?: 'Cập nhật định kỳ') ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= h($avatar) ?>" alt="Avatar" class="rounded-circle me-2 border" style="width: 28px; height: 28px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-semibold text-dark mb-0" style="font-size: 0.78rem;"><?= h($row['full_name'] ?: ($row['username'] ?: 'Hệ thống')) ?></div>
                                                            <div class="text-muted" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i><?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '--' ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <?php if ($has_file): ?>
                                                        <a href="<?= h($v_file) ?>" target="_blank" class="btn btn-xs btn-outline-primary px-2 py-1 me-1" title="Xem tệp của phiên bản này">
                                                            <i class="fas fa-file-download"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($can_edit): ?>
                                                        <button type="button" class="btn btn-xs btn-outline-warning text-dark px-2 py-1 btn-rollback" data-id="<?= (int)$row['id'] ?>" data-ver="<?= h($row['version_number']) ?>" data-doc="<?= h($row['document_number']) ?>" title="Đảo ngược văn bản về bản này">
                                                            <i class="fas fa-history me-1 text-warning"></i>Rollback
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline-danger px-2 py-1 ms-1 btn-delete-ver" data-id="<?= (int)$row['id'] ?>" title="Xóa mốc phiên bản này">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-code-branch fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Chưa có phiên bản thay đổi nào được lưu lại.</div>
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
                    $base_url = 'manage_versions.php?' . http_build_query(['search' => $search_query, 'doc_id' => $doc_filter]) . '&page=';
                ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . '1') ?>">&laquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . ($page - 1)) ?>">&lsaquo;</a></li>
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
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . ($page + 1)) ?>">&rsaquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?= h($base_url . $total_pages) ?>">&raquo;</a></li>
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
    // 1. Đảo ngược phiên bản (Rollback)
    document.querySelectorAll('.btn-rollback').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const ver = this.getAttribute('data-ver');
            const doc = this.getAttribute('data-doc');

            const executeRollback = () => {
                const formData = new FormData();
                formData.append('action', 'rollback_version');
                formData.append('id', id);

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Đang khôi phục...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                }

                fetch('ajax_version_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Thành công', text: data.message }).then(() => location.reload());
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(err => alert('Lỗi kết nối máy chủ'));
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận Rollback?',
                    html: `Bạn có chắc muốn đảo ngược văn bản <strong>${doc}</strong> về phiên bản <strong>v${ver}</strong>?<br><span class="text-danger small mt-2 d-block">Trạng thái hiện tại sẽ được tự động lưu thành một bản dự phòng.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý Rollback',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeRollback(); });
            } else {
                if (confirm(`Bạn có chắc muốn đảo ngược văn bản ${doc} về bản v${ver}?`)) executeRollback();
            }
        });
    });

    // 2. Xóa mốc phiên bản
    document.querySelectorAll('.btn-delete-ver').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');

            const executeDelete = () => {
                const formData = new FormData();
                formData.append('action', 'delete_version');
                formData.append('id', id);

                fetch('ajax_version_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert(data.message || 'Lỗi');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa mốc phiên bản này?',
                    text: 'Thao tác này chỉ xóa lịch sử lưu trữ bản ghi, không ảnh hưởng đến văn bản hiện tại.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa ngay',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeDelete(); });
            } else {
                if (confirm('Xác nhận xóa mốc phiên bản này?')) executeDelete();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>