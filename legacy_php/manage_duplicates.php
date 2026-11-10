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
if (!isset($_SESSION['user_id']) || (!has_permission('documents.edit') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang này.";
    header('Location: documents.php');
    exit;
}

$scan_mode = trim($_GET['mode'] ?? 'number'); // 'number' (theo số ký hiệu) hoặc 'content' (theo trích yếu)

$duplicate_groups = [];

if ($scan_mode === 'content') {
    // Quét các văn bản có cùng trích yếu nội dung
    $sql = "SELECT content, COUNT(*) as cnt 
            FROM documents 
            WHERE deleted_at IS NULL AND content IS NOT NULL AND TRIM(content) != '' 
            GROUP BY TRIM(content) 
            HAVING cnt > 1 
            ORDER BY cnt DESC LIMIT 50";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $content_val = $row['content'];
            $stmt = $conn->prepare("SELECT d.id, d.document_number, d.content, d.issued_date, d.status, d.file_path, dt.type_name, ia.authority_name 
                                    FROM documents d 
                                    LEFT JOIN document_types dt ON d.document_type_id = dt.id 
                                    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
                                    WHERE d.deleted_at IS NULL AND TRIM(d.content) = TRIM(?) 
                                    ORDER BY d.id ASC");
            $stmt->bind_param("s", $content_val);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($items) > 1) {
                $duplicate_groups[] = [
                    'key_name' => 'Trích yếu trùng lặp',
                    'key_val' => $content_val,
                    'items' => $items
                ];
            }
        }
    }
} else {
    // Quét các văn bản có cùng số ký hiệu (bỏ qua khoảng trắng và không phân biệt hoa thường)
    $sql = "SELECT REPLACE(LOWER(TRIM(document_number)), ' ', '') as norm_num, COUNT(*) as cnt 
            FROM documents 
            WHERE deleted_at IS NULL AND document_number IS NOT NULL AND TRIM(document_number) != '' 
            GROUP BY norm_num 
            HAVING cnt > 1 
            ORDER BY cnt DESC LIMIT 50";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $norm = $row['norm_num'];
            $stmt = $conn->prepare("SELECT d.id, d.document_number, d.content, d.issued_date, d.status, d.file_path, dt.type_name, ia.authority_name 
                                    FROM documents d 
                                    LEFT JOIN document_types dt ON d.document_type_id = dt.id 
                                    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
                                    WHERE d.deleted_at IS NULL AND REPLACE(LOWER(TRIM(d.document_number)), ' ', '') = ? 
                                    ORDER BY d.id ASC");
            $stmt->bind_param("s", $norm);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (count($items) > 1) {
                $duplicate_groups[] = [
                    'key_name' => 'Số ký hiệu',
                    'key_val' => $items[0]['document_number'],
                    'items' => $items
                ];
            }
        }
    }
}

$title = "Phát hiện & Xử lý Trùng lặp Văn bản";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Chọn chế độ quét -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-clone text-danger me-2"></i>Rà Soát & Xử Lý Trùng Lặp Văn Bản
                        </h4>
                        <div class="text-muted small">Tự động phát hiện các văn bản bị nhập lặp số ký hiệu hoặc trùng nội dung, hỗ trợ gộp và chuẩn hóa dữ liệu</div>
                    </div>
                    <div class="btn-group shadow-sm">
                        <a href="manage_duplicates.php?mode=number" class="btn btn-sm <?= ($scan_mode === 'number') ? 'btn-danger fw-bold' : 'btn-outline-danger' ?>">
                            <i class="fas fa-hashtag me-1"></i>Trùng Số ký hiệu
                        </a>
                        <a href="manage_duplicates.php?mode=content" class="btn btn-sm <?= ($scan_mode === 'content') ? 'btn-danger fw-bold' : 'btn-outline-danger' ?>">
                            <i class="fas fa-align-left me-1"></i>Trùng Trích yếu
                        </a>
                    </div>
                </div>

                <!-- THỐNG KÊ KẾT QUẢ -->
                <div class="alert alert-light border shadow-sm rounded-3 d-flex justify-content-between align-items-center mb-4 p-3">
                    <div>
                        <i class="fas fa-search-dollar text-danger me-2"></i>
                        Đang quét theo: <strong><?= ($scan_mode === 'number') ? 'Số ký hiệu văn bản' : 'Trích yếu nội dung văn bản' ?></strong>.
                        Tìm thấy <strong><?= count($duplicate_groups) ?></strong> nhóm văn bản có dữ liệu trùng nhau.
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Quét lại
                    </button>
                </div>

                <!-- DANH SÁCH CÁC NHÓM TRÙNG -->
                <?php if (count($duplicate_groups) > 0): ?>
                    <?php foreach ($duplicate_groups as $gIndex => $grp): ?>
                        <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden border-start border-4 border-danger">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-danger me-2">Nhóm #<?= $gIndex + 1 ?></span>
                                    <strong class="text-dark"><?= h($grp['key_name']) ?>:</strong>
                                    <span class="text-danger fw-bold ms-1 font-monospace"><?= h($grp['key_val']) ?></span>
                                </div>
                                <span class="badge bg-light text-secondary border px-2 py-1">
                                    <?= count($grp['items']) ?> bản ghi trùng
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light text-secondary">
                                            <tr>
                                                <th style="width: 70px;" class="text-center">Mã ID</th>
                                                <th style="width: 170px;">Số ký hiệu</th>
                                                <th>Trích yếu nội dung</th>
                                                <th style="width: 140px;">Cơ quan BH</th>
                                                <th style="width: 100px;" class="text-center">Ngày BH</th>
                                                <th style="width: 100px;" class="text-center">Tệp gốc</th>
                                                <th style="width: 220px;" class="text-center">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $first_id = $grp['items'][0]['id'];
                                            foreach ($grp['items'] as $item): 
                                                $is_primary = ($item['id'] == $first_id);
                                            ?>
                                                <tr class="<?= $is_primary ? 'table-success bg-opacity-25' : '' ?>">
                                                    <td class="text-center font-monospace fw-bold text-muted">
                                                        #<?= $item['id'] ?>
                                                        <?php if ($is_primary): ?>
                                                            <div class="badge bg-success" style="font-size: 0.6rem;">Bản chính</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-dark">
                                                        <a href="view_document.php?id=<?= (int)$item['id'] ?>" target="_blank" class="text-danger text-decoration-none">
                                                            <?= h($item['document_number']) ?>
                                                        </a>
                                                        <div class="small text-muted"><?= h($item['type_name'] ?? 'Chưa phân loại') ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="text-dark fw-medium text-truncate" style="max-width: 380px;" title="<?= h($item['content']) ?>">
                                                            <?= h($item['content']) ?>
                                                        </div>
                                                        <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;"><?= h($item['status']) ?></span>
                                                    </td>
                                                    <td class="text-muted"><?= h($item['authority_name'] ?? 'Chưa rõ') ?></td>
                                                    <td class="text-center text-muted"><?= !empty($item['issued_date']) ? date('d/m/Y', strtotime($item['issued_date'])) : '--' ?></td>
                                                    <td class="text-center">
                                                        <?php if (!empty($item['file_path']) && file_exists($item['file_path'])): ?>
                                                            <a href="<?= h($item['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2" title="Xem tệp">
                                                                <i class="fas fa-file-pdf"></i> Có file
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted fst-italic">Không</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center text-nowrap">
                                                        <?php if (!$is_primary): ?>
                                                            <!-- Nút gộp vào bản chính -->
                                                            <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 me-1 btn-merge-doc" 
                                                                    data-primary="<?= (int)$first_id ?>" 
                                                                    data-dup="<?= (int)$item['id'] ?>" 
                                                                    data-pnum="<?= h($grp['items'][0]['document_number']) ?>" 
                                                                    data-dnum="<?= h($item['document_number']) ?>">
                                                                <i class="fas fa-compress-arrows-alt me-1"></i>Gộp vào #<?= $first_id ?>
                                                            </button>
                                                            <!-- Nút đưa vào thùng rác -->
                                                            <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 btn-trash-dup" data-id="<?= (int)$item['id'] ?>">
                                                                <i class="fas fa-trash-alt"></i> Xóa
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                                <i class="fas fa-check me-1"></i>Bản lưu giữ
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-3 p-5 text-center text-muted">
                        <i class="fas fa-check-double fa-3x mb-3 text-success"></i>
                        <h5 class="fw-bold text-dark">Dữ liệu sạch sẽ, không phát hiện văn bản trùng!</h5>
                        <p class="small text-muted mb-0">Hệ thống không tìm thấy số ký hiệu hoặc trích yếu nào bị lặp lại trong kho văn bản hiện hành.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gộp văn bản
    document.querySelectorAll('.btn-merge-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            const pId = this.getAttribute('data-primary');
            const dId = this.getAttribute('data-dup');
            const pNum = this.getAttribute('data-pnum');
            const dNum = this.getAttribute('data-dnum');

            const executeMerge = () => {
                const formData = new FormData();
                formData.append('action', 'merge_duplicates');
                formData.append('primary_id', pId);
                formData.append('duplicate_id', dId);

                fetch('ajax_duplicate_action.php', { method: 'POST', body: formData })
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
                    title: 'Xác nhận gộp văn bản?',
                    html: `Chuyển toàn bộ phụ lục, lịch sử phiên bản và liên kết từ văn bản <strong>#${dId} (${dNum})</strong> vào văn bản gốc <strong>#${pId} (${pNum})</strong>.<br><span class="text-danger small mt-2 d-block">Bản ghi trùng #${dId} sẽ được dọn sạch khỏi danh sách.</span>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý gộp',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeMerge(); });
            } else {
                if (confirm(`Gộp văn bản #${dId} vào bản #${pId}?`)) executeMerge();
            }
        });
    });

    // 2. Chuyển bản ghi trùng vào thùng rác
    document.querySelectorAll('.btn-trash-dup').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');

            const executeTrash = () => {
                const formData = new FormData();
                formData.append('action', 'soft_delete_duplicate');
                formData.append('id', id);

                fetch('ajax_duplicate_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1200 });
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert(data.message || 'Lỗi');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Chuyển vào thùng rác?',
                    text: `Bạn có chắc muốn chuyển văn bản ID #${id} vào thùng rác?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa vào thùng rác',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeTrash(); });
            } else {
                if (confirm(`Chuyển văn bản #${id} vào thùng rác?`)) executeTrash();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>