<?php
session_start();
include 'db.php';

include_once 'auth.php';

// Khôi phục session từ cookie (phải chạy TRƯỚC khi kiểm tra quyền)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['username'], $_COOKIE['user_id'])) {
    $_SESSION['user_name'] = $_COOKIE['username'];
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
}

// Kiểm tra quyền (sau khi session đã được khôi phục đầy đủ)
require_permission('documents.edit');

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function get_options($table, $val_col, $disp_col, $selected) {
    global $conn;
    $res = $conn->query("SELECT `$val_col`, `$disp_col` FROM `$table` ORDER BY `$disp_col`");
    $options = '';
    while ($row = $res->fetch_assoc()) {
        $sel = ((string)$row[$val_col] === (string)$selected) ? 'selected' : '';
        $options .= "<option value=\"" . e($row[$val_col]) . "\" $sel>" . e($row[$disp_col]) . "</option>";
    }
    return $options;
}

// Lưu referer để quay lại đúng vị trí
if (isset($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $file = basename(parse_url($ref, PHP_URL_PATH) ?? '');
    if (!in_array($file, ['edit_document.php', 'update_document.php', 'view_document.php'], true)) {
        $_SESSION['original_list_page'] = $ref;
    }
}
$back_url = $_SESSION['original_list_page'] ?? 'dashboard.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    include 'header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger text-center fw-bold'>ID văn bản không hợp lệ.</div></div>";
    include 'footer.php'; exit;
}

$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->bind_param("i", $id); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    include 'header.php';
    echo "<div class='container mt-5'><div class='alert alert-warning text-center fw-bold'>Văn bản không tồn tại hoặc đã bị xóa.</div></div>";
    include 'footer.php'; exit;
}

$doc_num = $row['document_number'] ?? '';
$doc_upper = mb_strtoupper($doc_num, 'UTF-8');
$is_secret_default = (strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);

$title = "Sửa văn bản";
include 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .input-group > .ts-wrapper { flex: 1 1 auto; width: 1%; }
    .input-group > .ts-wrapper .ts-control { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; display: flex; align-items: center; }
    .ts-wrapper.focus .ts-control { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important; }
</style>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>SỬA VĂN BẢN SỐ: <?= e($doc_num) ?></h5>
                        <span class="badge bg-dark fs-6 shadow-sm <?= $is_secret_default ? '' : 'd-none' ?>" id="headerSecretBadge"><i class="fas fa-lock me-1"></i> MẬT</span>
                    </div>

                    <div class="card-body p-4">
                        <form action="update_document.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Số ký hiệu văn bản <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="docNumberInput" name="document_number" value="<?= e($doc_num) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ngày ban hành <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="issuedDateInput" name="issued_date" value="<?= e($row['issued_date']) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Loại văn bản <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" id="documentTypeSelect" name="document_type_id" required>
                                            <option value="">-- Chọn hoặc gõ tìm kiếm --</option>
                                            <?= get_options('document_types', 'id', 'type_name', $row['document_type_id']); ?>
                                        </select>
                                        <button class="btn btn-outline-danger px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalAddType" title="Thêm nhanh"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Cơ quan ban hành <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" id="authoritySelect" name="authority_id" required>
                                            <option value="">-- Chọn hoặc gõ tìm kiếm --</option>
                                            <?= get_options('issuing_authorities', 'id', 'authority_name', $row['authority_id']); ?>
                                        </select>
                                        <button class="btn btn-outline-danger px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalAddAuthority" title="Thêm nhanh"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- ĐÃ SỬA: Chia thành 3 cột và thêm ô Ngày hết hạn -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Ngày có hiệu lực</label>
                                    <input type="date" class="form-control" id="effectiveDateInput" name="effective_date" value="<?= e($row['effective_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-warning"><i class="fas fa-calendar-times me-1"></i>Ngày hết hạn (Dự kiến)</label>
                                    <input type="date" class="form-control" name="expired_date" value="<?= e($row['expired_date'] ?? '') ?>" title="Bỏ trống nếu văn bản có hiệu lực vĩnh viễn">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Tình trạng hiệu lực</label>
                                    <select class="form-select" name="status">
                                        <?php foreach (['Còn hiệu lực', 'Hết hiệu lực', 'Được thay thế', 'Không xác định'] as $st): ?>
                                            <option value="<?= $st ?>" <?= ($row['status'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nội dung / Trích yếu <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="content" rows="3" required><?= e($row['content']) ?></textarea>
                            </div>

                            <div class="form-check mb-3 p-2 bg-danger bg-opacity-10 border border-danger rounded d-inline-block">
                                <input class="form-check-input ms-1 border-danger" type="checkbox" id="isSecretCheckbox" name="is_secret_checkbox" value="1" <?= $is_secret_default ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-danger ms-2 me-3" for="isSecretCheckbox">
                                    <i class="fas fa-lock me-1"></i> Văn bản Mật (Không yêu cầu tệp đính kèm)
                                </label>
                            </div>

                            <div class="mb-4 p-3 bg-light rounded border" id="fileUploadContainer">
                                <label class="form-label fw-semibold text-primary"><i class="fas fa-paperclip me-2"></i>Quản lý tệp đính kèm chính</label>
                                <div class="mb-2 small">
                                    <span class="fw-semibold">Tệp hiện tại: </span>
                                    <?php if (!empty($row['file_path']) && file_exists($row['file_path'])): ?>
                                        <a href="<?= e($row['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-2 py-0 px-2"><i class="fas fa-eye me-1"></i>Xem tệp</a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Chưa có tệp đính kèm</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="fileInput" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                                <div class="form-text">Bỏ trống nếu muốn giữ nguyên tệp cũ. Hỗ trợ PDF, Word, Excel, Ảnh. Tối đa 120MB.</div>
                            </div>

                            <div class="mb-4 p-3 bg-danger bg-opacity-10 rounded border border-danger text-danger d-none" id="secretFileWarning">
                                <i class="fas fa-lock me-2"></i><strong>Đây là văn bản Mật.</strong> Hệ thống sẽ tự động hủy đính kèm và xóa tệp vật lý cũ (nếu có) khi bấm lưu.
                            </div>

                            <div class="text-end border-top pt-3">
                                <a href="<?= e($back_url) ?>" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i>Quay lại</a>
                                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-save me-1"></i>Cập nhật văn bản</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Loại VB -->
<div class="modal fade" id="modalAddType" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Thêm loại văn bản</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formAddType">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên loại văn bản <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="type_name" required placeholder="VD: Nghị định, Thông tư...">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-outline-secondary me-1" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger" id="btnSubmitType">Lưu lại</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm Cơ quan BH -->
<div class="modal fade" id="modalAddAuthority" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Thêm cơ quan ban hành</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formAddAuthority">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên cơ quan ban hành <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="authority_name" required placeholder="VD: Bộ Y tế, Chính phủ...">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-outline-secondary me-1" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger" id="btnSubmitAuthority">Lưu lại</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const tsType = new TomSelect("#documentTypeSelect", { create: false, sortField: { field: "text", direction: "asc" } });
    const tsAuth = new TomSelect("#authoritySelect", { create: false, sortField: { field: "text", direction: "asc" } });
    
    const expiredInput = document.querySelector('input[name="expired_date"]');
    const statusSelect = document.querySelector('select[name="status"]');

    if (expiredInput && statusSelect) {
        expiredInput.addEventListener('change', function() {
            if (this.value) {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    statusSelect.value = 'Hết hiệu lực';
                }
            }
        });
    }

    function handleAjaxSubmit(formId, btnId, modalId, tsInstance, url) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById(btnId);
            btn.disabled = true;

            fetch(url, { method: 'POST', body: new FormData(form) })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Thêm thành công!', showConfirmButton: false, timer: 3000 });
                        tsInstance.addOption({ value: String(data.id), text: String(data.name) });
                        tsInstance.setValue(String(data.id));
                        form.reset();
                        bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    } else {
                        alert(data.message || 'Lỗi thêm dữ liệu');
                    }
                })
                .finally(() => btn.disabled = false);
        });
    }

    handleAjaxSubmit('formAddType', 'btnSubmitType', 'modalAddType', tsType, 'ajax_add_type.php');
    handleAjaxSubmit('formAddAuthority', 'btnSubmitAuthority', 'modalAddAuthority', tsAuth, 'ajax_add_authority.php');

    // Logic ngày tháng & Văn bản Mật
    const issuedInput = document.getElementById("issuedDateInput");
    const effectiveInput = document.getElementById("effectiveDateInput");
    const docInput = document.getElementById("docNumberInput");
    const secretCheckbox = document.getElementById("isSecretCheckbox");
    const fileContainer = document.getElementById("fileUploadContainer");
    const fileInput = document.getElementById("fileInput");
    const secretWarning = document.getElementById("secretFileWarning");
    const headerBadge = document.getElementById("headerSecretBadge");

    // ĐÃ SỬA: Bỏ validation cứng, chỉ tự động copy ngày khi trống để thao tác nhanh hơn
    issuedInput.addEventListener("change", function() {
        if (this.value) {
            if (!effectiveInput.value) effectiveInput.value = this.value;
        }
    });

    function toggleSecret() {
        const isSec = secretCheckbox.checked || ['-M', '/M', ' M ', 'MẬT'].some(k => docInput.value.toUpperCase().includes(k));
        if (isSec) {
            secretCheckbox.checked = true;
            fileContainer.classList.add("d-none");
            secretWarning.classList.remove("d-none");
            if (headerBadge) headerBadge.classList.remove("d-none");
            fileInput.value = "";
        } else {
            fileContainer.classList.remove("d-none");
            secretWarning.classList.add("d-none");
            if (headerBadge) headerBadge.classList.add("d-none");
        }
    }

    secretCheckbox.addEventListener("change", toggleSecret);
    docInput.addEventListener("input", toggleSecret);
    toggleSecret();
});
</script>

<?php include 'footer.php'; ?>