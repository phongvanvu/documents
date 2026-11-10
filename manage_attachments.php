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

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || (!has_permission('documents.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang này.";
    header('Location: documents.php');
    exit;
}

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$can_edit = has_permission('documents.edit') || (($_SESSION['role'] ?? '') === 'admin');

// =========================================================================
// TỰ ĐỘNG KIỂM TRA VÀ BỔ SUNG CÁC CỘT THIẾU TRONG BẢNG CŨ (AUTO-MIGRATION)
// =========================================================================
$existing_cols = [];
$res_cols = $conn->query("SHOW COLUMNS FROM document_attachments");
if ($res_cols) {
    while ($col = $res_cols->fetch_assoc()) {
        $existing_cols[] = $col['Field'];
    }
}

if (!in_array('attachment_name', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_attachments ADD COLUMN attachment_name VARCHAR(255) NULL AFTER document_id");
}
if (!in_array('file_size', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_attachments ADD COLUMN file_size INT NULL AFTER attachment_path");
}
if (!in_array('created_at', $existing_cols, true)) {
    $conn->query("ALTER TABLE document_attachments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Bộ lọc
$search_query = trim($_GET['search'] ?? '');
$ext_filter = trim($_GET['ext'] ?? '');

$conditions = ["1=1"];
$params = [];
$param_types = "";

if ($search_query !== '') {
    $search_param = '%' . $search_query . '%';
    $conditions[] = "(COALESCE(a.attachment_name, '') LIKE ? OR a.attachment_path LIKE ? OR d.document_number LIKE ? OR d.content LIKE ?)";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $param_types .= "ssss";
}

if ($ext_filter !== '') {
    $conditions[] = "LOWER(a.attachment_path) LIKE ?";
    $params[] = "%." . strtolower($ext_filter);
    $param_types .= "s";
}

$where_clause = " WHERE " . implode(" AND ", $conditions);

// Phân trang
$items_per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total 
              FROM document_attachments a 
              LEFT JOIN documents d ON a.document_id = d.id 
              $where_clause";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_rows = (int)($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();

// Lấy danh sách phụ lục
$sql = "SELECT a.id, a.document_id, a.attachment_name, a.attachment_path, a.file_size, a.created_at, 
               d.document_number, d.content AS doc_content 
        FROM document_attachments a 
        LEFT JOIN documents d ON a.document_id = d.id 
        $where_clause 
        ORDER BY a.id DESC 
        LIMIT ? OFFSET ?";

$list_params = array_merge($params, [$items_per_page, $start_from]);
$param_types_full = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types_full, ...$list_params);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "Quản lý Phụ lục & Tệp đính kèm";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-paperclip text-danger me-2"></i>Quản Lý Phụ Lục & Tệp Đính Kèm
                        </h4>
                        <div class="text-muted small">Tra cứu nhanh, giám sát dung lượng và kiểm tra tính toàn vẹn của các tệp văn bản kèm theo</div>
                    </div>
                </div>

                <!-- BỘ LỌC TÌM KIẾM -->
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body p-3">
                        <form method="GET" action="manage_attachments.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Tìm theo tên file, số ký hiệu hoặc trích yếu văn bản cha..." value="<?= h($search_query) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="ext" class="form-select form-select-sm">
                                    <option value="">-- Tất cả định dạng tệp --</option>
                                    <option value="pdf" <?= ($ext_filter === 'pdf') ? 'selected' : '' ?>>Tệp PDF (.pdf)</option>
                                    <option value="doc" <?= ($ext_filter === 'doc') ? 'selected' : '' ?>>Tệp Word (.doc, .docx)</option>
                                    <option value="xls" <?= ($ext_filter === 'xls') ? 'selected' : '' ?>>Tệp Excel (.xls, .xlsx)</option>
                                    <option value="zip" <?= ($ext_filter === 'zip') ? 'selected' : '' ?>>Tệp nén (.zip, .rar)</option>
                                    <option value="png" <?= ($ext_filter === 'png') ? 'selected' : '' ?>>Tệp hình ảnh (.png, .jpg)</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-danger px-3"><i class="fas fa-filter me-1"></i>Lọc</button>
                                <a href="manage_attachments.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Đặt lại</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BẢNG DANH SÁCH PHỤ LỤC -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-folder-open text-secondary me-2"></i>Danh sách tệp phụ lục đính kèm
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= number_format($total_rows) ?> tệp
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">STT</th>
                                        <th style="width: 320px;">Tên tệp phụ lục</th>
                                        <th style="width: 100px;" class="text-center">Dung lượng</th>
                                        <th>Văn bản gốc liên kết</th>
                                        <th style="width: 150px;" class="text-center">Thời gian tải lên</th>
                                        <th style="width: 140px;" class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($attachments) > 0): ?>
                                        <?php 
                                        $i = $start_from + 1;
                                        foreach ($attachments as $row): 
                                            $fpath = $row['attachment_path'];
                                            $fname = !empty($row['attachment_name']) ? $row['attachment_name'] : basename($fpath);
                                            $fexists = file_exists($fpath) && is_file($fpath);
                                            $fsize = $fexists ? filesize($fpath) : (int)($row['file_size'] ?? 0);
                                            $ext = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));

                                            $icon = '<i class="fas fa-file-alt text-secondary fa-lg me-2"></i>';
                                            if ($ext === 'pdf') $icon = '<i class="fas fa-file-pdf text-danger fa-lg me-2"></i>';
                                            elseif (in_array($ext, ['doc', 'docx'])) $icon = '<i class="fas fa-file-word text-primary fa-lg me-2"></i>';
                                            elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '<i class="fas fa-file-excel text-success fa-lg me-2"></i>';
                                            elseif (in_array($ext, ['zip', 'rar'])) $icon = '<i class="fas fa-file-archive text-warning fa-lg me-2"></i>';
                                            elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) $icon = '<i class="fas fa-file-image text-info fa-lg me-2"></i>';
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?= $icon ?>
                                                        <div class="text-truncate" style="max-width: 280px;">
                                                            <div class="fw-bold text-dark mb-0 text-truncate" title="<?= h($fname) ?>">
                                                                <?= h($fname) ?>
                                                            </div>
                                                            <div class="text-muted small" style="font-size: 0.72rem;">
                                                                <?php if ($fexists): ?>
                                                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Tệp toàn vẹn</span>
                                                                <?php else: ?>
                                                                    <span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Mất file trên máy chủ</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border font-monospace"><?= formatBytes($fsize) ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['document_id']) && !empty($row['document_number'])): ?>
                                                        <a href="view_document.php?id=<?= (int)$row['document_id'] ?>" target="_blank" class="fw-bold text-danger text-decoration-none">
                                                            <?= h($row['document_number']) ?>
                                                        </a>
                                                        <div class="text-muted text-truncate" style="max-width: 320px; font-size: 0.75rem;" title="<?= h($row['doc_content']) ?>">
                                                            <?= h($row['doc_content']) ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Văn bản đã bị xóa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center text-muted">
                                                    <i class="far fa-clock me-1 text-secondary"></i><?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '--' ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <?php if ($fexists): ?>
                                                        <a href="<?= h($fpath) ?>" target="_blank" class="btn btn-xs btn-outline-primary px-2 py-1 me-1" title="Mở xem tệp">
                                                            <i class="fas fa-eye me-1"></i>Xem
                                                        </a>
                                                        <a href="<?= h($fpath) ?>" download class="btn btn-xs btn-outline-success px-2 py-1 me-1" title="Tải về">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($can_edit): ?>
                                                        <button type="button" class="btn btn-xs btn-outline-danger px-2 py-1 btn-delete-att" data-id="<?= (int)$row['id'] ?>" data-title="<?= h($fname) ?>" title="Xóa tệp đính kèm này">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-paperclip fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Không tìm thấy tệp phụ lục nào.</div>
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
                    $base_url = 'manage_attachments.php?' . http_build_query(['search' => $search_query, 'ext' => $ext_filter]) . '&page=';
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
    document.querySelectorAll('.btn-delete-att').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');

            const doDelete = () => {
                const formData = new FormData();
                formData.append('action', 'delete_attachment');
                formData.append('id', id);

                fetch('ajax_attachment_action.php', { method: 'POST', body: formData })
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
                })
                .catch(err => alert('Lỗi kết nối máy chủ'));
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa tệp đính kèm?',
                    html: `Bạn có chắc chắn muốn xóa tệp: <strong>${title}</strong>?<br><span class="text-danger small">Tệp vật lý trên máy chủ sẽ bị xóa vĩnh viễn!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xóa',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) doDelete(); });
            } else {
                if (confirm(`Xác nhận xóa tệp ${title}?`)) doDelete();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>