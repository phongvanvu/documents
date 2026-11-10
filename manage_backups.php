<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên mới được vào trang này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Đọc danh sách các file sao lưu .sql
$backup_files = [];
$files = scandir($backup_dir);
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        $filepath = $backup_dir . $file;
        $backup_files[] = [
            'filename' => $file,
            'size'     => filesize($filepath),
            'mtime'    => filemtime($filepath)
        ];
    }
}

// Sắp xếp bản mới nhất lên đầu
usort($backup_files, function($a, $b) {
    return $b['mtime'] - $a['mtime'];
});

$title = "Quản lý sao lưu & Phục hồi CSDL";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                
                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-database text-danger me-2"></i>Sao lưu & Phục hồi Dữ liệu
                        </h4>
                        <div class="text-muted small">Tạo bản lưu trữ cơ sở dữ liệu MySQL an toàn và khôi phục khi cần thiết</div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm fw-semibold px-3 shadow-sm" id="btnCreateBackup">
                        <i class="fas fa-plus-circle me-1"></i>Tạo bản sao lưu mới (.SQL)
                    </button>
                </div>

                <!-- Cảnh báo lưu ý -->
                <div class="alert alert-warning border-start border-4 border-warning shadow-sm rounded-3 py-2 px-3 mb-3 small d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2 text-warning fs-5"></i>
                    <div>
                        <strong>Lưu ý an toàn:</strong> Quá trình phục hồi (Restore) sẽ ghi đè toàn bộ dữ liệu hiện tại bằng dữ liệu của bản sao lưu được chọn.
                    </div>
                </div>

                <!-- Bảng danh sách sao lưu -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-history text-secondary me-2"></i>Danh sách các bản sao lưu hiện có
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= count($backup_files) ?> bản sao lưu
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped-columns align-middle mb-0">
                                <thead class="table-light text-center small text-uppercase text-secondary">
                                    <tr>
                                        <th style="width: 60px;" class="py-3">STT</th>
                                        <th class="text-start ps-3">Tên tệp sao lưu (.SQL)</th>
                                        <th style="width: 130px;">Dung lượng</th>
                                        <th style="width: 180px;">Thời gian tạo</th>
                                        <th style="width: 250px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    <?php if (count($backup_files) > 0): ?>
                                        <?php $i = 1; foreach ($backup_files as $bf): ?>
                                            <tr>
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td class="ps-3 fw-bold text-dark font-monospace">
                                                    <i class="fas fa-file-code text-primary me-2 fs-6"></i><?= h($bf['filename']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border px-2 py-1 fw-semibold font-monospace">
                                                        <?= formatFileSize($bf['size']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center text-muted">
                                                    <i class="far fa-clock me-1 text-secondary"></i><?= date('d/m/Y H:i:s', $bf['mtime']) ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <div class="d-inline-flex gap-1">
                                                        <!-- Tải về -->
                                                        <a href="backups/<?= rawurlencode($bf['filename']) ?>" download class="btn btn-sm btn-outline-primary px-2 py-1" title="Tải tệp về máy">
                                                            <i class="fas fa-download me-1"></i>Tải về
                                                        </a>
                                                        <!-- Phục hồi -->
                                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark px-2 py-1 btn-restore-backup" data-file="<?= h($bf['filename']) ?>" title="Phục hồi CSDL từ bản này">
                                                            <i class="fas fa-undo-alt me-1 text-warning"></i>Phục hồi
                                                        </button>
                                                        <!-- Xóa -->
                                                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 btn-delete-backup" data-file="<?= h($bf['filename']) ?>" title="Xóa bản lưu này">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-database fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Chưa có bản sao lưu nào.</div>
                                                <div class="small">Bấm nút "Tạo bản sao lưu mới" ở trên để lưu trữ dữ liệu.</div>
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
    // 1. Tạo bản sao lưu mới
    const btnCreate = document.getElementById('btnCreateBackup');
    if (btnCreate) {
        btnCreate.addEventListener('click', function() {
            btnCreate.disabled = true;
            btnCreate.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xuất dữ liệu...';

            const formData = new FormData();
            formData.append('action', 'create_backup');

            fetch('ajax_backup_action.php', { method: 'POST', body: formData })
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
                    btnCreate.disabled = false;
                    btnCreate.innerHTML = '<i class="fas fa-plus-circle me-1"></i>Tạo bản sao lưu mới (.SQL)';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ');
                btnCreate.disabled = false;
                btnCreate.innerHTML = '<i class="fas fa-plus-circle me-1"></i>Tạo bản sao lưu mới (.SQL)';
            });
        });
    }

    // 2. Phục hồi dữ liệu
    document.querySelectorAll('.btn-restore-backup').forEach(btn => {
        btn.addEventListener('click', function() {
            const filename = this.getAttribute('data-file');
            
            const doRestore = () => {
                const formData = new FormData();
                formData.append('action', 'restore_backup');
                formData.append('filename', filename);

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Đang phục hồi CSDL...',
                        text: 'Vui lòng không tắt trình duyệt',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                }

                fetch('ajax_backup_action.php', { method: 'POST', body: formData })
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
                            Swal.fire({ icon: 'error', title: 'Thất bại', text: data.message });
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Lỗi phục hồi dữ liệu');
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận phục hồi CSDL?',
                    html: `Bạn có chắc chắn muốn khôi phục dữ liệu từ tệp:<br><strong class="text-primary font-monospace">${filename}</strong>?<br><span class="text-danger small mt-2 d-block">Dữ liệu hiện tại sẽ bị ghi đè hoàn toàn!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý phục hồi!',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) doRestore(); });
            } else {
                if (confirm(`Bạn có chắc muốn phục hồi từ file ${filename}? Dữ liệu hiện tại sẽ bị ghi đè.`)) {
                    doRestore();
                }
            }
        });
    });

    // 3. Xóa bản sao lưu
    document.querySelectorAll('.btn-delete-backup').forEach(btn => {
        btn.addEventListener('click', function() {
            const filename = this.getAttribute('data-file');
            
            const doDelete = () => {
                const formData = new FormData();
                formData.append('action', 'delete_backup');
                formData.append('filename', filename);

                fetch('ajax_backup_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa bản sao lưu này?',
                    html: `<span class="font-monospace text-danger">${filename}</span>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa ngay',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) doDelete(); });
            } else {
                if (confirm(`Xác nhận xóa tệp ${filename}?`)) doDelete();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>