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

// Kiểm tra quyền: Admin hoặc người có quyền documents.delete
if (!isset($_SESSION['user_id']) || (!has_permission('documents.delete') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập Thùng rác.";
    header('Location: documents.php');
    exit;
}

function formatDateVN($d) {
    if (empty($d) || in_array($d, ['0000-00-00', '1970-01-01'], true)) return '';
    $t = strtotime($d); return $t ? date('d/m/Y', $t) : '';
}

// Phân trang
$items_per_page = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

// Đếm tổng số văn bản trong thùng rác
$res_count = $conn->query("SELECT COUNT(*) AS total FROM documents WHERE deleted_at IS NOT NULL");
$total_trash = (int)($res_count->fetch_assoc()['total'] ?? 0);

// Truy vấn danh sách văn bản trong thùng rác
$sql = "SELECT d.id, d.document_number, d.content, d.issued_date, d.deleted_at, d.file_path, dt.type_name, ia.authority_name 
        FROM documents d 
        LEFT JOIN document_types dt ON d.document_type_id = dt.id 
        LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
        WHERE d.deleted_at IS NOT NULL 
        ORDER BY d.deleted_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $items_per_page, $start_from);
$stmt->execute();
$trash_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = "Quản lý Thùng rác";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Nút thao tác hàng loạt -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-trash-alt text-danger me-2"></i>Thùng rác Văn bản
                        </h4>
                        <div class="text-muted small">Danh sách các văn bản đã xóa tạm thời. Bạn có thể khôi phục lại hoặc xóa vĩnh viễn khỏi máy chủ.</div>
                    </div>
                    
                    <?php if ($total_trash > 0): ?>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm shadow-sm fw-semibold btn-trash-batch" data-action="restore_all">
                                <i class="fas fa-undo-alt me-1"></i>Khôi phục tất cả
                            </button>
                            <button type="button" class="btn btn-danger btn-sm shadow-sm fw-semibold btn-trash-batch" data-action="empty_trash">
                                <i class="fas fa-dumpster-fire me-1"></i>Dọn sạch thùng rác
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- BẢNG DANH SÁCH VĂN BẢN TRONG THÙNG RÁC -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-list me-2 text-secondary"></i>Các văn bản đang chờ xử lý
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= number_format($total_trash) ?> văn bản
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-center text-secondary">
                                    <tr>
                                        <th style="width: 50px;">STT</th>
                                        <th style="width: 160px;" class="text-start ps-3">Số ký hiệu</th>
                                        <th class="text-start">Trích yếu nội dung</th>
                                        <th style="width: 140px;">Cơ quan ban hành</th>
                                        <th style="width: 100px;">Ngày BH</th>
                                        <th style="width: 140px;">Thời gian xóa</th>
                                        <th style="width: 160px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($trash_docs) > 0): ?>
                                        <?php $i = $start_from + 1; foreach ($trash_docs as $doc): ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td class="ps-3 fw-bold text-danger">
                                                    <?= h($doc['document_number']) ?>
                                                </td>
                                                <td class="text-start">
                                                    <div class="text-dark fw-medium mb-1"><?= h($doc['content']) ?></div>
                                                    <span class="badge bg-light text-secondary border"><?= h($doc['type_name'] ?? 'Chưa phân loại') ?></span>
                                                </td>
                                                <td class="text-center text-muted"><?= h($doc['authority_name'] ?? 'Chưa rõ') ?></td>
                                                <td class="text-center text-muted"><?= formatDateVN($doc['issued_date']) ?></td>
                                                <td class="text-center text-muted small">
                                                    <i class="far fa-clock me-1 text-secondary"></i><?= date('d/m/Y H:i', strtotime($doc['deleted_at'])) ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <div class="d-inline-flex gap-1">
                                                        <!-- Khôi phục -->
                                                        <button type="button" class="btn btn-sm btn-outline-success px-2 py-1 btn-restore-single" data-id="<?= (int)$doc['id'] ?>" title="Khôi phục văn bản">
                                                            <i class="fas fa-undo-alt me-1"></i>Khôi phục
                                                        </button>
                                                        <!-- Xóa vĩnh viễn -->
                                                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 btn-force-delete" data-id="<?= (int)$doc['id'] ?>" data-docnum="<?= h($doc['document_number']) ?>" title="Xóa vĩnh viễn khỏi hệ thống">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-trash-restore fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Thùng rác trống.</div>
                                                <div class="small">Không có văn bản nào đang chờ khôi phục hoặc xóa bỏ.</div>
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
                $total_pages = (int)ceil($total_trash / $items_per_page);
                if ($total_pages > 1): 
                ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=1">&laquo;</a></li>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">&lsaquo;</a></li>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2); $end = min($total_pages, $start + 4);
                            if ($end - $start < 4) $start = max(1, $end - 4);
                            for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?= ($p === $page) ? '#' : '?page=' . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">&rsaquo;</a></li>
                                <li class="page-item"><a class="page-link" href="?page=<?= $total_pages ?>">&raquo;</a></li>
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
    // 1. Khôi phục 1 văn bản
    document.querySelectorAll('.btn-restore-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const button = this;

            button.disabled = true;
            const formData = new FormData();
            formData.append('action', 'restore_single');
            formData.append('id', id);

            fetch('ajax_trash_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.message || 'Lỗi');
                    button.disabled = false;
                }
            })
            .catch(err => {
                alert('Lỗi kết nối máy chủ');
                button.disabled = false;
            });
        });
    });

    // 2. Xóa vĩnh viễn 1 văn bản
    document.querySelectorAll('.btn-force-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const docNum = this.getAttribute('data-docnum');

            const executeDelete = () => {
                const formData = new FormData();
                formData.append('action', 'force_delete_single');
                formData.append('id', id);

                fetch('ajax_trash_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                        }
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        alert(data.message || 'Lỗi');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa vĩnh viễn văn bản?',
                    html: `Văn bản <strong>${docNum}</strong> cùng toàn bộ tệp và quan hệ liên kết sẽ bị xóa vĩnh viễn khỏi ổ cứng máy chủ!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xóa vĩnh viễn',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeDelete(); });
            } else {
                if (confirm(`Xóa vĩnh viễn văn bản ${docNum}? Hành động không thể hoàn tác.`)) executeDelete();
            }
        });
    });

    // 3. Thao tác hàng loạt (Khôi phục tất cả / Dọn sạch thùng rác)
    document.querySelectorAll('.btn-trash-batch').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const isRestore = (action === 'restore_all');

            const title = isRestore ? 'Khôi phục toàn bộ văn bản?' : 'Dọn sạch toàn bộ thùng rác?';
            const text = isRestore 
                ? 'Tất cả các văn bản trong thùng rác sẽ được đưa trở lại danh sách hoạt động.' 
                : 'TẤT CẢ văn bản trong thùng rác và các tệp đính kèm sẽ bị XÓA VĨNH VIỄN khỏi máy chủ. Không thể hoàn tác!';

            const executeBatch = () => {
                const formData = new FormData();
                formData.append('action', action);

                fetch('ajax_trash_action.php', { method: 'POST', body: formData })
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
                        alert(data.message || 'Lỗi');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: isRestore ? 'question' : 'error',
                    showCancelButton: true,
                    confirmButtonColor: isRestore ? '#198754' : '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: isRestore ? 'Đồng ý khôi phục' : 'Xóa sạch vĩnh viễn',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeBatch(); });
            } else {
                if (confirm(title)) executeBatch();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>