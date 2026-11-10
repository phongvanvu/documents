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
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang quản lý liên kết văn bản.";
    header('Location: documents.php');
    exit;
}

$can_edit = has_permission('documents.edit') || (($_SESSION['role'] ?? '') === 'admin');

// Bộ lọc
$filter_type = trim($_GET['relation_type'] ?? '');
$filter_search = trim($_GET['search'] ?? '');

$conditions = ["1=1"];
$params = [];
$param_types = "";

if ($filter_type !== '') {
    $conditions[] = "r.relation_type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_search !== '') {
    $search_param = '%' . $filter_search . '%';
    $conditions[] = "(d1.document_number LIKE ? OR d1.content LIKE ? OR d2.document_number LIKE ? OR d2.content LIKE ?)";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $param_types .= "ssss";
}

$where_clause = " WHERE " . implode(" AND ", $conditions);

// Phân trang
$items_per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total 
              FROM document_relations r 
              JOIN documents d1 ON r.document_id = d1.id 
              JOIN documents d2 ON r.related_document_id = d2.id 
              $where_clause";

$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$total_rows = (int)($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();

// Lấy danh sách liên kết
$sql = "SELECT r.id, r.relation_type, r.note, r.created_at,
               d1.id AS doc1_id, d1.document_number AS doc1_number, d1.content AS doc1_content, d1.status AS doc1_status,
               d2.id AS doc2_id, d2.document_number AS doc2_number, d2.content AS doc2_content, d2.status AS doc2_status
        FROM document_relations r
        JOIN documents d1 ON r.document_id = d1.id
        JOIN documents d2 ON r.related_document_id = d2.id
        $where_clause
        ORDER BY r.id DESC
        LIMIT ? OFFSET ?";

$list_params = array_merge($params, [$items_per_page, $start_from]);
$param_types_full = $param_types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types_full, ...$list_params);
$stmt->execute();
$relations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy danh sách 100 văn bản mới nhất phục vụ ô chọn TomSelect
$recent_docs = $conn->query("SELECT id, document_number, content FROM documents WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$title = "Quản lý Liên kết & Hiệu lực Văn bản";
include 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-wrapper .ts-control { border-radius: 0.375rem; }
</style>

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
                            <i class="fas fa-project-diagram text-danger me-2"></i>Mối Quan Hệ & Hiệu Lực Văn Bản
                        </h4>
                        <div class="text-muted small">Kiểm soát phả hệ pháp lý: Văn bản thay thế, sửa đổi bổ sung, hướng dẫn và căn cứ pháp lý</div>
                    </div>
                    <?php if ($can_edit): ?>
                        <button type="button" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddRelation">
                            <i class="fas fa-link me-1"></i>Tạo liên kết văn bản mới
                        </button>
                    <?php endif; ?>
                </div>

                <!-- BỘ LỌC TÌM KIẾM -->
                <div class="card border-0 shadow-sm rounded-3 mb-3">
                    <div class="card-body p-3">
                        <form method="GET" action="manage_relations.php" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Tìm theo số hiệu, trích yếu văn bản nguồn hoặc đích..." value="<?= h($filter_search) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="relation_type" class="form-select form-select-sm">
                                    <option value="">-- Tất cả loại quan hệ --</option>
                                    <option value="Thay thế" <?= ($filter_type === 'Thay thế') ? 'selected' : '' ?>>Thay thế</option>
                                    <option value="Sửa đổi, bổ sung" <?= ($filter_type === 'Sửa đổi, bổ sung') ? 'selected' : '' ?>>Sửa đổi, bổ sung</option>
                                    <option value="Hướng dẫn" <?= ($filter_type === 'Hướng dẫn') ? 'selected' : '' ?>>Hướng dẫn thi hành</option>
                                    <option value="Căn cứ" <?= ($filter_type === 'Căn cứ') ? 'selected' : '' ?>>Căn cứ pháp lý</option>
                                    <option value="Bãi bỏ" <?= ($filter_type === 'Bãi bỏ') ? 'selected' : '' ?>>Bãi bỏ / Hủy bỏ</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-danger px-3"><i class="fas fa-filter me-1"></i>Lọc</button>
                                <a href="manage_relations.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Đặt lại</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- BẢNG DANH SÁCH LIÊN KẾT -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-sitemap text-secondary me-2"></i>Danh sách quan hệ giữa các văn bản
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= number_format($total_rows) ?> liên kết
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">STT</th>
                                        <th style="width: 35%;">Văn bản tác động (Chủ động)</th>
                                        <th style="width: 180px;" class="text-center">Mối quan hệ</th>
                                        <th style="width: 35%;">Văn bản chịu tác động (Bị động)</th>
                                        <?php if ($can_edit): ?>
                                            <th style="width: 80px;" class="text-center">Thao tác</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($relations) > 0): ?>
                                        <?php 
                                        $i = $start_from + 1;
                                        foreach ($relations as $rel): 
                                            // Màu sắc huy hiệu quan hệ
                                            $badge_class = 'bg-secondary';
                                            $rel_icon = 'fa-arrow-right';
                                            if ($rel['relation_type'] === 'Thay thế') {
                                                $badge_class = 'bg-danger';
                                                $rel_icon = 'fa-sync-alt';
                                            } elseif ($rel['relation_type'] === 'Sửa đổi, bổ sung') {
                                                $badge_class = 'bg-warning text-dark';
                                                $rel_icon = 'fa-edit';
                                            } elseif ($rel['relation_type'] === 'Hướng dẫn') {
                                                $badge_class = 'bg-primary';
                                                $rel_icon = 'fa-compass';
                                            } elseif ($rel['relation_type'] === 'Căn cứ') {
                                                $badge_class = 'bg-info text-dark';
                                                $rel_icon = 'fa-bookmark';
                                            } elseif ($rel['relation_type'] === 'Bãi bỏ') {
                                                $badge_class = 'bg-dark';
                                                $rel_icon = 'fa-ban';
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td>
                                                    <a href="view_document.php?id=<?= (int)$rel['doc1_id'] ?>" target="_blank" class="fw-bold text-danger text-decoration-none">
                                                        <?= h($rel['doc1_number']) ?>
                                                    </a>
                                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.65rem;"><?= h($rel['doc1_status']) ?></span>
                                                    <div class="text-muted text-truncate mt-1" style="max-width: 360px;" title="<?= h($rel['doc1_content']) ?>">
                                                        <?= h($rel['doc1_content']) ?>
                                                    </div>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <span class="badge <?= $badge_class ?> px-2 py-1">
                                                        <i class="fas <?= $rel_icon ?> me-1"></i><?= h($rel['relation_type']) ?>
                                                    </span>
                                                    <?php if (!empty($rel['note'])): ?>
                                                        <div class="text-muted small fst-italic mt-1" style="font-size: 0.75rem;" title="<?= h($rel['note']) ?>">
                                                            <?= h($rel['note']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_document.php?id=<?= (int)$rel['doc2_id'] ?>" target="_blank" class="fw-bold text-primary text-decoration-none">
                                                        <?= h($rel['doc2_number']) ?>
                                                    </a>
                                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.65rem;"><?= h($rel['doc2_status']) ?></span>
                                                    <div class="text-muted text-truncate mt-1" style="max-width: 360px;" title="<?= h($rel['doc2_content']) ?>">
                                                        <?= h($rel['doc2_content']) ?>
                                                    </div>
                                                </td>
                                                <?php if ($can_edit): ?>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-danger px-2 py-1 btn-delete-relation" data-id="<?= (int)$rel['id'] ?>" title="Gỡ bỏ liên kết này">
                                                            <i class="fas fa-unlink"></i>
                                                        </button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?= $can_edit ? 5 : 4 ?>" class="text-center py-5 text-muted">
                                                <i class="fas fa-project-diagram fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Chưa có mối quan hệ văn bản nào được thiết lập.</div>
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
                    $base_url = 'manage_relations.php?' . http_build_query(['relation_type' => $filter_type, 'search' => $filter_search]) . '&page=';
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

<!-- MODAL TẠO LIÊN KẾT MỚI -->
<?php if ($can_edit): ?>
<div class="modal fade" id="modalAddRelation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form id="formAddRelation">
                <input type="hidden" name="action" value="add_relation">

                <div class="modal-header py-3">
                    <h6 class="modal-title fw-bold text-dark">
                        <i class="fas fa-link text-danger me-2"></i>Thiết lập Mối quan hệ Văn bản
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-danger">
                                1. Văn bản tác động (Chủ động) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select select-doc" name="document_id" required>
                                <option value="">-- Chọn hoặc gõ số hiệu/trích yếu --</option>
                                <?php foreach ($recent_docs as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>">
                                        <?= h($d['document_number']) ?> - <?= h(mb_substr($d['content'], 0, 45, 'UTF-8')) ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-primary">
                                2. Văn bản chịu tác động (Bị động) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select select-doc" name="related_document_id" required>
                                <option value="">-- Chọn hoặc gõ số hiệu/trích yếu --</option>
                                <?php foreach ($recent_docs as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>">
                                        <?= h($d['document_number']) ?> - <?= h(mb_substr($d['content'], 0, 45, 'UTF-8')) ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">
                                Mối quan hệ pháp lý <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="relation_type" required>
                                <option value="Thay thế">Thay thế (Văn bản 1 thay thế văn bản 2)</option>
                                <option value="Sửa đổi, bổ sung">Sửa đổi, bổ sung điều/khoản</option>
                                <option value="Hướng dẫn">Hướng dẫn thi hành</option>
                                <option value="Căn cứ">Căn cứ pháp lý để ban hành</option>
                                <option value="Bãi bỏ">Bãi bỏ / Hủy bỏ hiệu lực</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Ghi chú chi tiết (Không bắt buộc)</label>
                            <input type="text" class="form-control" name="note" placeholder="VD: Thay thế Điều 5 và Khoản 2 Điều 9...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold" id="btnSubmitRelation">
                        <i class="fas fa-save me-1"></i>Lưu mối quan hệ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo tìm kiếm nhanh văn bản qua TomSelect
    document.querySelectorAll('.select-doc').forEach(el => {
        new TomSelect(el, { create: false, maxOptions: 50 });
    });

    // Thêm quan hệ
    const formAdd = document.getElementById('formAddRelation');
    if (formAdd) {
        formAdd.addEventListener('submit', function(e) {
            e.preventDefault();
            const doc1 = this.querySelector('[name="document_id"]').value;
            const doc2 = this.querySelector('[name="related_document_id"]').value;

            if (doc1 === doc2) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Không hợp lệ', text: 'Hai văn bản không được trùng nhau!' });
                } else {
                    alert('Hai văn bản không được trùng nhau!');
                }
                return;
            }

            const btn = document.getElementById('btnSubmitRelation');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';

            fetch('ajax_relation_action.php', {
                method: 'POST',
                body: new FormData(formAdd)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.message || 'Lỗi');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu mối quan hệ';
                }
            })
            .catch(err => {
                alert('Lỗi kết nối máy chủ');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu mối quan hệ';
            });
        });
    }

    // Xóa quan hệ
    document.querySelectorAll('.btn-delete-relation').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');

            const doDelete = () => {
                const formData = new FormData();
                formData.append('action', 'delete_relation');
                formData.append('id', id);

                fetch('ajax_relation_action.php', { method: 'POST', body: formData })
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
                    title: 'Xóa mối liên kết này?',
                    text: 'Thao tác này chỉ gỡ bỏ mối quan hệ hiệu lực, không làm mất bất kỳ văn bản nào.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý gỡ bỏ',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) doDelete(); });
            } else {
                if (confirm('Xác nhận gỡ bỏ mối quan hệ giữa 2 văn bản?')) doDelete();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>