<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'], $_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    } else {
        header("Location: login.php");
        exit;
    }
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$document_id) {
    header("Location: documents.php");
    exit;
}

$stmt = $conn->prepare("SELECT d.*, dt.type_name, ia.authority_name FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id WHERE d.id = ? AND d.deleted_at IS NULL");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    die("Văn bản không tồn tại.");
}

// Lấy danh sách các phiên bản đã lưu (Đã sửa u.user_name thành u.username)
$stmt_v = $conn->prepare("SELECT v.*, u.full_name, u.username FROM document_versions v LEFT JOIN users u ON v.uploaded_by = u.id WHERE v.document_id = ? ORDER BY v.id DESC");
$stmt_v->bind_param("i", $document_id);
$stmt_v->execute();
$versions = $stmt_v->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_v->close();

$title = "Lịch sử phiên bản - " . $doc['document_number'];
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold mb-0"><i class="fas fa-history text-danger me-2"></i>Lịch sử phiên bản</h4>
                        <div class="text-muted small">Quản lý và tra cứu các lần đính chính, cập nhật của văn bản <strong><?= h($doc['document_number']) ?></strong></div>
                    </div>
                    <a href="view_document.php?id=<?= (int)$doc['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Quay lại</a>
                </div>

                <div class="row g-3">
                    <!-- Khối Form Upload Phiên bản mới -->
                    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)): ?>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-danger text-white py-2 px-3">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-upload me-2"></i>Tải lên phiên bản mới</h6>
                            </div>
                            <div class="card-body p-3">
                                <form id="formAddVersion" enctype="multipart/form-data">
                                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                    
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Tên phiên bản <span class="text-danger">*</span></label>
                                        <input type="text" name="version_number" class="form-control form-control-sm" placeholder="VD: v1.1, Bản đính chính..." required>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold">Tệp đính kèm <span class="text-danger">*</span></label>
                                        <input type="file" name="version_file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Lý do / Tóm tắt thay đổi</label>
                                        <textarea name="change_summary" class="form-control form-control-sm" rows="3" placeholder="Ghi chú nội dung chỉnh sửa so với bản gốc..."></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-danger btn-sm fw-bold" id="btnSubmitVersion"><i class="fas fa-save me-1"></i>Lưu phiên bản</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Danh sách phiên bản & Bản gốc -->
                    <div class="<?= in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true) ? 'col-lg-8' : 'col-lg-12' ?>">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white py-2 px-3 border-bottom">
                                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-layer-group text-danger me-2"></i>Dòng thời gian phiên bản</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th class="ps-3" style="width: 120px;">Phiên bản</th>
                                                <th>Nội dung thay đổi / Tóm tắt</th>
                                                <th style="width: 140px;">Người đăng</th>
                                                <th class="text-center" style="width: 130px;">Thời gian</th>
                                                <th class="text-center" style="width: 90px;">Tác vụ</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <!-- Bản gốc ban đầu -->
                                            <tr class="table-light">
                                                <td class="ps-3"><span class="badge bg-success">v1.0 (Gốc)</span></td>
                                                <td><span class="fst-italic text-muted">Văn bản ban hành gốc ban đầu</span></td>
                                                <td>Hệ thống</td>
                                                <td class="text-center"><?= date('d/m/Y', strtotime($doc['created_at'] ?? $doc['issued_date'])) ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($doc['file_path']) && file_exists($doc['file_path'])): ?>
                                                        <a href="<?= h($doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-danger" title="Xem bản gốc"><i class="fas fa-eye"></i></a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Các phiên bản mới -->
                                            <?php foreach ($versions as $ver): ?>
                                                <tr>
                                                    <td class="ps-3"><span class="badge bg-primary"><?= h($ver['version_number']) ?></span></td>
                                                    <td><?= nl2br(h($ver['change_summary'] ?: 'Không có ghi chú thay đổi')) ?></td>
                                                    <td><?= h($ver['full_name'] ?: ($ver['username'] ?? 'Người dùng')) ?></td>
                                                    <td class="text-center"><?= date('d/m/Y H:i', strtotime($ver['created_at'])) ?></td>
                                                    <td class="text-center">
                                                        <?php if (file_exists($ver['file_path'])): ?>
                                                            <a href="<?= h($ver['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-primary" title="Tải / Xem"><i class="fas fa-download"></i></a>
                                                        <?php else: ?>
                                                            <span class="text-danger small">Mất file</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formAddVersion');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitVersion');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';

            fetch('ajax_add_version.php', {
                method: 'POST',
                body: new FormData(form)
            })
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
                    alert(data.message || 'Có lỗi xảy ra');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu phiên bản';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu phiên bản';
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>