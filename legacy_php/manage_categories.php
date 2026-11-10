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
if (!isset($_SESSION['user_id']) || (!has_permission('categories.manage') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang quản lý danh mục.";
    header('Location: documents.php');
    exit;
}

// 1. Lấy danh sách Loại văn bản
$types = $conn->query("SELECT dt.id, dt.type_name, COUNT(d.id) AS total_docs 
                       FROM document_types dt 
                       LEFT JOIN documents d ON dt.id = d.document_type_id AND d.deleted_at IS NULL 
                       GROUP BY dt.id 
                       ORDER BY dt.type_name ASC")->fetch_all(MYSQLI_ASSOC);

// 2. Lấy danh sách Cơ quan ban hành
$authorities = $conn->query("SELECT ia.id, ia.authority_name, COUNT(d.id) AS total_docs 
                             FROM issuing_authorities ia 
                             LEFT JOIN documents d ON ia.id = d.authority_id AND d.deleted_at IS NULL 
                             GROUP BY ia.id 
                             ORDER BY ia.authority_name ASC")->fetch_all(MYSQLI_ASSOC);

$title = "Quản lý Danh mục & Cơ quan ban hành";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-tags text-danger me-2"></i>Quản lý Danh mục & Cơ quan Ban hành
                        </h4>
                        <div class="text-muted small">Thêm mới, sửa đổi tên và giám sát số lượng văn bản đang áp dụng theo từng danh mục</div>
                    </div>
                    <div>
                        <a href="merge_categories.php" class="btn btn-outline-danger btn-sm shadow-sm fw-semibold">
                            <i class="fas fa-object-group me-1"></i>Công cụ Gộp danh mục trùng
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- KHỐI 1: QUẢN LÝ LOẠI VĂN BẢN -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-primary">
                                    <i class="fas fa-file-alt me-2"></i>Loại văn bản (<?= count($types) ?>)
                                </h6>
                                <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm btn-open-modal" data-target="type" data-mode="create">
                                    <i class="fas fa-plus me-1"></i>Thêm Loại VB
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="p-2 border-bottom bg-light">
                                    <input type="text" class="form-control form-control-sm search-category" placeholder="Tìm nhanh loại văn bản...">
                                </div>
                                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light text-secondary sticky-top">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">STT</th>
                                                <th>Tên loại văn bản</th>
                                                <th style="width: 100px;" class="text-center">Số VB</th>
                                                <th style="width: 110px;" class="text-center">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody class="category-tbody">
                                            <?php $i = 1; foreach ($types as $t): ?>
                                                <tr>
                                                    <td class="text-center text-muted"><?= $i++ ?></td>
                                                    <td class="fw-bold text-dark item-name"><?= h($t['type_name']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-light text-dark border px-2 py-1"><?= number_format($t['total_docs']) ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-open-modal" data-target="type" data-mode="edit" data-id="<?= (int)$t['id'] ?>" data-name="<?= h($t['type_name']) ?>" title="Sửa tên">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 btn-delete-cat" data-target="type" data-id="<?= (int)$t['id'] ?>" data-name="<?= h($t['type_name']) ?>" data-count="<?= (int)$t['total_docs'] ?>" title="Xóa">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KHỐI 2: QUẢN LÝ CƠ QUAN BAN HÀNH -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-success">
                                    <i class="fas fa-building me-2"></i>Cơ quan ban hành (<?= count($authorities) ?>)
                                </h6>
                                <button type="button" class="btn btn-sm btn-success px-3 shadow-sm btn-open-modal" data-target="authority" data-mode="create">
                                    <i class="fas fa-plus me-1"></i>Thêm Cơ quan
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="p-2 border-bottom bg-light">
                                    <input type="text" class="form-control form-control-sm search-category" placeholder="Tìm nhanh cơ quan ban hành...">
                                </div>
                                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light text-secondary sticky-top">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">STT</th>
                                                <th>Tên cơ quan / Đơn vị</th>
                                                <th style="width: 100px;" class="text-center">Số VB</th>
                                                <th style="width: 110px;" class="text-center">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody class="category-tbody">
                                            <?php $i = 1; foreach ($authorities as $a): ?>
                                                <tr>
                                                    <td class="text-center text-muted"><?= $i++ ?></td>
                                                    <td class="fw-bold text-dark item-name"><?= h($a['authority_name']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-light text-dark border px-2 py-1"><?= number_format($a['total_docs']) ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 btn-open-modal" data-target="authority" data-mode="edit" data-id="<?= (int)$a['id'] ?>" data-name="<?= h($a['authority_name']) ?>" title="Sửa tên">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 btn-delete-cat" data-target="authority" data-id="<?= (int)$a['id'] ?>" data-name="<?= h($a['authority_name']) ?>" data-count="<?= (int)$a['total_docs'] ?>" title="Xóa">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
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

<!-- MODAL THÊM / SỬA DANH MỤC -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form id="formCategoryModal">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="target" id="modalTarget" value="type">
                <input type="hidden" name="id" id="modalId" value="0">

                <div class="modal-header py-3">
                    <h6 class="modal-title fw-bold" id="modalTitle">Thêm mới danh mục</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark" id="modalLabelName">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="modalInputName" required placeholder="Nhập tên...">
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold" id="btnSaveModal">Lưu dữ liệu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const catModalEl = document.getElementById('categoryModal');
    const catModal = new bootstrap.Modal(catModalEl);
    const formModal = document.getElementById('formCategoryModal');

    // 1. Mở Modal Thêm / Sửa
    document.querySelectorAll('.btn-open-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const mode = this.getAttribute('data-mode');
            const targetText = (target === 'authority') ? 'Cơ quan ban hành' : 'Loại văn bản';

            document.getElementById('modalTarget').value = target;
            document.getElementById('modalAction').value = mode;

            if (mode === 'create') {
                document.getElementById('modalTitle').innerText = 'Thêm mới ' + targetText;
                document.getElementById('modalLabelName').innerText = 'Tên ' + targetText;
                document.getElementById('modalId').value = '0';
                document.getElementById('modalInputName').value = '';
            } else {
                document.getElementById('modalTitle').innerText = 'Sửa tên ' + targetText;
                document.getElementById('modalLabelName').innerText = 'Tên ' + targetText;
                document.getElementById('modalId').value = this.getAttribute('data-id');
                document.getElementById('modalInputName').value = this.getAttribute('data-name');
            }

            catModal.show();
            setTimeout(() => document.getElementById('modalInputName').focus(), 400);
        });
    });

    // 2. Submit lưu Modal bằng AJAX
    formModal.addEventListener('submit', function(e) {
        e.preventDefault();
        const btnSave = document.getElementById('btnSaveModal');
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';

        fetch('ajax_category_action.php', {
            method: 'POST',
            body: new FormData(formModal)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                catModal.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                }
                setTimeout(() => location.reload(), 1000);
            } else {
                alert(data.message || 'Lỗi');
                btnSave.disabled = false;
                btnSave.innerHTML = 'Lưu dữ liệu';
            }
        })
        .catch(err => {
            alert('Lỗi kết nối máy chủ');
            btnSave.disabled = false;
            btnSave.innerHTML = 'Lưu dữ liệu';
        });
    });

    // 3. Xóa Danh mục (Có chặn an toàn)
    document.querySelectorAll('.btn-delete-cat').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const count = parseInt(this.getAttribute('data-count'), 10);

            if (count > 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Không thể xóa!',
                        html: `Danh mục <strong>"${name}"</strong> hiện đang gắn với <strong>${count} văn bản</strong>.<br><br>Hãy dùng công cụ <strong>"Gộp danh mục"</strong> để chuyển toàn bộ văn bản sang danh mục khác trước khi xóa.`,
                        confirmButtonText: 'Đã hiểu'
                    });
                } else {
                    alert(`Không thể xóa! Đang có ${count} văn bản thuộc danh mục này.`);
                }
                return;
            }

            const executeDelete = () => {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('target', target);
                formData.append('id', id);

                fetch('ajax_category_action.php', { method: 'POST', body: formData })
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
                    title: 'Xác nhận xóa danh mục?',
                    html: `Bạn có chắc muốn xóa: <strong>${name}</strong>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa ngay',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeDelete(); });
            } else {
                if (confirm(`Xác nhận xóa "${name}"?`)) executeDelete();
            }
        });
    });

    // 4. Tìm kiếm nhanh danh mục phía Client
    document.querySelectorAll('.search-category').forEach(input => {
        input.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            const rows = this.closest('.card-body').querySelectorAll('.category-tbody tr');
            rows.forEach(r => {
                const text = r.querySelector('.item-name').innerText.toLowerCase();
                r.style.display = text.includes(val) ? '' : 'none';
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>