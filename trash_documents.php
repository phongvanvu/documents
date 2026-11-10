<?php
session_start();
include 'db.php';

// Chỉ Admin mới được vào Thùng rác để khôi phục hoặc xóa vĩnh viễn
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div class='alert alert-danger text-center mt-5'>Chỉ quản trị viên mới có quyền truy cập Thùng rác.</div>");
}

function h($val) { return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8'); }

// ===============================================
// XỬ LÝ KHÔI PHỤC VĂN BẢN (RESTORE)
// ===============================================
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $restore_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($restore_id) {
        $stmt = $conn->prepare("UPDATE documents SET deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $restore_id);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "Đã khôi phục văn bản thành công!";
        }
        $stmt->close();
    }
    header('Location: trash_documents.php');
    exit;
}

// ===============================================
// XỬ LÝ XÓA VĨNH VIỄN (FORCE DELETE)
// ===============================================
if (isset($_GET['action']) && $_GET['action'] === 'force_delete' && isset($_GET['id'])) {
    $del_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($del_id) {
        // 1. Lấy thông tin tệp
        $stmt = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 2. Lấy danh sách tệp đính kèm phụ
        $stmt_att = $conn->prepare("SELECT attachment_path FROM document_attachments WHERE document_id = ?");
        $stmt_att->bind_param('i', $del_id);
        $stmt_att->execute();
        $attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_att->close();

        // 3. Xóa các bản ghi liên quan (Relations & Attachments)
        $conn->prepare("DELETE FROM document_relations WHERE document_id = ? OR related_document_id = ?")->bind_param('ii', $del_id, $del_id);
        $conn->prepare("DELETE FROM document_attachments WHERE document_id = ?")->bind_param('i', $del_id);

        // 4. Xóa vĩnh viễn văn bản chính
        $stmt_del = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt_del->bind_param("i", $del_id);
        
        if ($stmt_del->execute()) {
            // Dọn dẹp tệp vật lý
            if (!empty($doc['file_path']) && file_exists($doc['file_path'])) { @unlink($doc['file_path']); }
            foreach ($attachments as $att) {
                if (!empty($att['attachment_path']) && file_exists($att['attachment_path'])) { @unlink($att['attachment_path']); }
            }
            $_SESSION['flash_msg'] = "Đã xóa vĩnh viễn văn bản và giải phóng ổ cứng!";
        }
        $stmt_del->close();
    }
    header('Location: trash_documents.php');
    exit;
}

// Lấy danh sách văn bản trong thùng rác
$sql = "SELECT d.id, d.document_number, d.content, d.deleted_at, dt.type_name 
        FROM documents d 
        LEFT JOIN document_types dt ON d.document_type_id = dt.id 
        WHERE d.deleted_at IS NOT NULL 
        ORDER BY d.deleted_at DESC";
$trash_docs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$title = "Thùng rác";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0 text-dark fw-bold"><i class="fas fa-trash-restore text-danger me-2"></i>Thùng rác</h4>
                        <div class="text-muted small mt-1">Các văn bản đã bị xóa. Chỉ Admin mới có quyền xóa vĩnh viễn.</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Số ký hiệu</th>
                                        <th>Trích yếu</th>
                                        <th class="text-center">Thời gian xóa</th>
                                        <th class="text-center" style="width: 250px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($trash_docs) > 0): ?>
                                        <?php foreach ($trash_docs as $doc): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-secondary"><?= h($doc['document_number']) ?></td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 400px;"><?= h($doc['content']) ?></div>
                                                    <div class="small text-muted"><?= h($doc['type_name']) ?></div>
                                                </td>
                                                <td class="text-center text-danger small fw-semibold">
                                                    <?= date('H:i - d/m/Y', strtotime($doc['deleted_at'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="trash_documents.php?action=restore&id=<?= $doc['id'] ?>" class="btn btn-sm btn-success px-3 me-1">
                                                        <i class="fas fa-undo me-1"></i>Khôi phục
                                                    </a>
                                                    <button class="btn btn-sm btn-danger px-3 btn-force-delete" data-id="<?= $doc['id'] ?>">
                                                        <i class="fas fa-times me-1"></i>Xóa sạch
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-5">
                                                <i class="fas fa-box-open fa-3x mb-3 text-light"></i><br>Thùng rác đang trống.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['flash_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['flash_msg'] ?>', showConfirmButton: false, timer: 3000 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    document.querySelectorAll('.btn-force-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            let id = this.getAttribute('data-id');
            Swal.fire({
                title: 'Xóa VĨNH VIỄN?',
                text: "Dữ liệu và file vật lý sẽ bị xóa sạch khỏi ổ cứng. Không thể hoàn tác!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xóa vĩnh viễn!',
                cancelButtonText: 'Hủy'
            }).then((res) => { 
                if (res.isConfirmed) window.location.href = 'trash_documents.php?action=force_delete&id=' + id; 
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>