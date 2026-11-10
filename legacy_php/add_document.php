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
require_permission('documents.create');

// Tự động kiểm tra và tạo bảng document_relations nếu chưa có trong CSDL
$conn->query("CREATE TABLE IF NOT EXISTS document_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    related_document_id INT NOT NULL,
    relation_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (document_id),
    INDEX (related_document_id)
)");


function get_options($table, $val_col, $disp_col, $selected) {
    global $conn;
    $allowed = ['issuing_authorities' => ['id', 'authority_name'], 'document_types' => ['id', 'type_name']];
    if (!isset($allowed[$table])) return '';
    
    $res = $conn->query("SELECT `$val_col`, `$disp_col` FROM `$table` ORDER BY `$disp_col`");
    $options = '';
    while ($row = $res->fetch_assoc()) {
        $sel = ((string)$row[$val_col] === (string)$selected) ? 'selected' : '';
        $options .= "<option value=\"" . e($row[$val_col]) . "\" $sel>" . e($row[$disp_col]) . "</option>";
    }
    return $options;
}

function upload_file($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip', 'rar'];
    if (!in_array($ext, $allowed_exts, true) || $file['size'] > 120000000) return false;

    $dir = 'uploads/documents/' . date('Y/m') . '/';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }

    $safe_name = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file['name']);
    $path = $dir . $safe_name;
    return move_uploaded_file($file['tmp_name'], $path) ? $path : false;
}

function is_secret($doc_num, $chk) {
    $up = mb_strtoupper($doc_num, 'UTF-8');
    return $chk || strpos($up, '-M') !== false || strpos($up, '/M') !== false || strpos($up, ' M ') !== false || strpos($up, 'MẬT') !== false;
}

$error = '';
$doc_num = $doc_type_id = $auth_id = $issued_date = $effective_date = $expired_date = $content = '';
$status = 'Không xác định';
$is_secret_chk = false;
$relation_type = '';
$related_document_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_num = trim($_POST['document_number'] ?? '');
    $doc_type_id = intval($_POST['document_type_id'] ?? 0);
    $auth_id = intval($_POST['authority_id'] ?? 0);
    $issued_date = trim($_POST['issued_date'] ?? '');
    $effective_date = trim($_POST['effective_date'] ?? '');
    $expired_date = trim($_POST['expired_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Không xác định');
    $relation_type = trim($_POST['relation_type'] ?? '');
    $related_document_id = intval($_POST['related_document_id'] ?? 0);

    if (!empty($expired_date) && strtotime($expired_date) < strtotime(date('Y-m-d'))) {
        $status = 'Hết hiệu lực';
    }
    $content = trim($_POST['content'] ?? '');
    $is_secret_chk = isset($_POST['is_secret_checkbox']);

    if ($doc_num === '' || $doc_type_id <= 0 || $auth_id <= 0 || $issued_date === '' || $content === '') {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } else {
        $file_path = '';
        if (is_secret($doc_num, $is_secret_chk)) {
            $file_path = '';
        } else {
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file_path = upload_file($_FILES['document_file']);
                if (!$file_path) $error = 'Tải lên tệp thất bại. Kiểm tra lại định dạng và dung lượng.';
            } else {
                $error = 'Vui lòng chọn tệp văn bản đính kèm.';
            }
        }

        if ($error === '') {
            $eff_date_db = $effective_date !== '' ? $effective_date : null;
            $exp_date_db = $expired_date !== '' ? $expired_date : null;

            $stmt = $conn->prepare('INSERT INTO documents (document_number, document_type_id, authority_id, issued_date, effective_date, expired_date, status, content, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('siissssss', $doc_num, $doc_type_id, $auth_id, $issued_date, $eff_date_db, $exp_date_db, $status, $content, $file_path);

            if ($stmt->execute()) {
                $new_doc_id = $conn->insert_id;
                $stmt->close(); 
                
                // Lưu quan hệ liên kết
                if ($new_doc_id > 0 && $related_document_id > 0 && !empty($relation_type)) {
                    $stmt_rel = $conn->prepare("INSERT INTO document_relations (document_id, related_document_id, relation_type) VALUES (?, ?, ?)");
                    if ($stmt_rel) {
                        $stmt_rel->bind_param("iis", $new_doc_id, $related_document_id, $relation_type);
                        $stmt_rel->execute();
                        $stmt_rel->close();
                    }

                    if ($relation_type === 'thay_the') {
                        $stmt_up_old = $conn->prepare("UPDATE documents SET status = 'Được thay thế' WHERE id = ?");
                        if ($stmt_up_old) {
                            $stmt_up_old->bind_param("i", $related_document_id);
                            $stmt_up_old->execute();
                            $stmt_up_old->close();
                        }
                    }
                }

                // Ghi log
                $log_user_id = (int)$_SESSION['user_id'];
                $log_action = "Thêm mới văn bản số hiệu: " . $doc_num;
                try {
                    $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                    if ($log_stmt) {
                        $log_stmt->bind_param("is", $log_user_id, $log_action);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } catch (Exception $e) { }

                $_SESSION['flash_msg'] = 'Thêm văn bản thành công!';
                header('Location: documents.php');
                exit;
            }
            $error = 'Lỗi lưu dữ liệu: ' . $stmt->error;
            $stmt->close();
        }
    }
}

$existing_docs = $conn->query("SELECT id, document_number, content FROM documents WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 500")->fetch_all(MYSQLI_ASSOC);

$title = 'Thêm văn bản pháp luật';
include 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .input-group > .ts-wrapper { flex: 1 1 auto; width: 1%; }
    .input-group > .ts-wrapper .ts-control { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; display: flex; align-items: center; }
    .ts-wrapper.focus .ts-control { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important; }
    .cursor-pointer { cursor: pointer; }
</style>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2"></i>THÊM VĂN BẢN PHÁP LUẬT</h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($error): ?>
                            <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i><?= e($error) ?></div>
                        <?php endif; ?>

                        <form action="add_document.php" method="POST" enctype="multipart/form-data" id="mainAddDocForm">
                            <div class="row mb-3">
                                <!-- CỘT SỐ KÝ HIỆU & KIỂM TRA TRÙNG LẶP -->
                                <div class="col-md-6 position-relative">
                                    <label class="form-label fw-semibold">Số ký hiệu văn bản <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="docNumberInput" name="document_number" value="<?= e($doc_num) ?>" required placeholder="VD: 123/QĐ-UBND" autocomplete="off">
                                        <button class="btn btn-outline-secondary" type="button" id="btnManualCheckDuplicate" title="Kiểm tra trùng ngay">
                                            <i class="fas fa-search me-1"></i>Kiểm tra
                                        </button>
                                    </div>
                                    <div id="checkDocLoading" class="small text-muted mt-1" style="display: none;">
                                        <i class="fas fa-spinner fa-spin me-1 text-danger"></i>Đang kiểm tra số hiệu văn bản...
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ngày ban hành <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="issuedDateInput" name="issued_date" value="<?= e($issued_date) ?>" required>
                                </div>
                            </div>

                            <!-- KHUNG CẢNH BÁO TRÙNG SỐ HIỆU (KHÔNG DÙNG CLASS .ALERT ĐỂ TRÁNH BỊ TỰ ĐÓNG) -->
                            <div id="duplicateResultsContainer" class="mb-3" style="display: none;">
                                <div class="p-3 rounded-3 shadow-sm" style="background-color: #fff8e1; border: 1.5px solid #ffe082; border-left: 5px solid #dc3545;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold text-danger fs-6">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Phát hiện <span id="duplicateCountText" class="badge bg-danger rounded-pill">0</span> văn bản có số ký hiệu tương tự:
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnCloseDuplicateBox" title="Đóng cảnh báo này">
                                            <i class="fas fa-times me-1"></i>Đóng
                                        </button>
                                    </div>
                                    <div class="table-responsive bg-white rounded border">
                                        <table class="table table-sm table-hover align-middle mb-0 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 140px;">Số ký hiệu</th>
                                                    <th>Trích yếu nội dung</th>
                                                    <th style="width: 130px;">Cơ quan BH</th>
                                                    <th style="width: 95px;" class="text-center">Ngày BH</th>
                                                    <th style="width: 105px;" class="text-center">Hiệu lực</th>
                                                    <th style="width: 65px;" class="text-center">Xem</th>
                                                </tr>
                                            </thead>
                                            <tbody id="duplicateTableBody">
                                                <!-- Dữ liệu nạp động từ AJAX -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Loại văn bản <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" id="documentTypeSelect" name="document_type_id" required>
                                            <option value="">-- Chọn hoặc gõ tìm kiếm --</option>
                                            <?= get_options('document_types', 'id', 'type_name', $doc_type_id); ?>
                                        </select>
                                        <button class="btn btn-outline-danger px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalAddType" title="Thêm nhanh"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Cơ quan ban hành <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select" id="authoritySelect" name="authority_id" required>
                                            <option value="">-- Chọn hoặc gõ tìm kiếm --</option>
                                            <?= get_options('issuing_authorities', 'id', 'authority_name', $auth_id); ?>
                                        </select>
                                        <button class="btn btn-outline-danger px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalAddAuthority" title="Thêm nhanh"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Ngày có hiệu lực</label>
                                    <input type="date" class="form-control" id="effectiveDateInput" name="effective_date" value="<?= e($effective_date) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-warning"><i class="fas fa-calendar-times me-1"></i>Ngày hết hạn (Dự kiến)</label>
                                    <input type="date" class="form-control" name="expired_date" value="<?= e($expired_date) ?>" title="Bỏ trống nếu văn bản có hiệu lực vĩnh viễn">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Tình trạng hiệu lực</label>
                                    <select class="form-select" name="status">
                                        <?php foreach (['Còn hiệu lực', 'Hết hiệu lực', 'Được thay thế', 'Không xác định'] as $st): ?>
                                            <option value="<?= $st ?>" <?= ($status === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nội dung / Trích yếu <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="content" rows="3" required placeholder="Nhập trích yếu nội dung văn bản..."><?= e($content) ?></textarea>
                            </div>

                            <!-- KHỐI NGHIỆP VỤ: LIÊN KẾT VĂN BẢN -->
                            <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                                <label class="form-label fw-bold text-dark mb-1">
                                    <i class="fas fa-link text-danger me-1"></i>Mối liên kết văn bản (Nếu là văn bản thay thế, sửa đổi)
                                </label>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <select class="form-select" name="relation_type">
                                            <option value="thay_the">Thay thế cho</option>
                                            <option value="sua_doi">Sửa đổi / Bổ sung cho</option>
                                            <option value="huong_dan">Hướng dẫn thi hành cho</option>
                                            <option value="can_cu">Căn cứ theo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <select class="form-select" id="relatedDocSelect" name="related_document_id">
                                            <option value="">-- Chọn văn bản liên quan (nếu có) --</option>
                                            <?php foreach ($existing_docs as $ed): ?>
                                                <option value="<?= (int)$ed['id'] ?>" <?= ($related_document_id === (int)$ed['id']) ? 'selected' : '' ?>>
                                                    <?= e($ed['document_number']) ?> - <?= e(mb_strimwidth($ed['content'], 0, 60, '...')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-info-circle text-primary me-1"></i>Khi chọn <strong>"Thay thế cho"</strong>, hệ thống sẽ tự động cập nhật văn bản cũ sang trạng thái <strong>"Được thay thế"</strong>.
                                </div>
                            </div>

                            <div class="form-check mb-3 p-2 bg-danger bg-opacity-10 border border-danger rounded d-inline-block">
                                <input class="form-check-input ms-1 border-danger cursor-pointer" type="checkbox" id="isSecretCheckbox" name="is_secret_checkbox" value="1" <?= $is_secret_chk ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-danger ms-2 me-3 cursor-pointer" for="isSecretCheckbox">
                                    <i class="fas fa-lock me-1"></i> Văn bản Mật (Không yêu cầu tệp đính kèm)
                                </label>
                            </div>

                            <div class="mb-4 p-3 bg-light rounded border shadow-sm" id="fileUploadContainer">
                                <label class="form-label fw-semibold text-primary"><i class="fas fa-paperclip me-2"></i>Tệp đính kèm chính <span class="text-danger">*</span></label>
                                <input type="file" class="form-control form-control-lg" id="fileInput" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar" required>
                                <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Hỗ trợ PDF, Word, Excel, Ảnh, ZIP. Tối đa 120MB.</div>
                            </div>

                            <div class="mb-4 p-3 bg-danger bg-opacity-10 rounded border border-danger text-danger d-none shadow-sm" id="secretFileWarning">
                                <i class="fas fa-lock me-2"></i><strong>Đây là văn bản Mật.</strong> Hệ thống tự động vô hiệu hóa tải tệp.
                            </div>

                            <div class="text-end border-top pt-3">
                                <a href="documents.php" class="btn btn-outline-secondary me-2"><i class="fas fa-times me-1"></i>Hủy bỏ</a>
                                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-save me-1"></i>Lưu văn bản</button>
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
    const tsRel  = new TomSelect("#relatedDocSelect", { create: false, sortField: { field: "text", direction: "asc" } });

    // =========================================================================
    // KHỐI 1: XỬ LÝ KIỂM TRA TRÙNG LẶP (CHỐNG CHỚP TẮT & CHỐNG UNICODE RACE-CONDITION)
    // =========================================================================
    const docInput = document.getElementById("docNumberInput");
    const checkLoading = document.getElementById("checkDocLoading");
    const duplicateBox = document.getElementById("duplicateResultsContainer");
    const duplicateCount = document.getElementById("duplicateCountText");
    const duplicateTableBody = document.getElementById("duplicateTableBody");
    const btnManualCheck = document.getElementById("btnManualCheckDuplicate");
    const btnCloseBox = document.getElementById("btnCloseDuplicateBox");
    
    let checkTimeout = null;
    let activeRequestId = 0;

    function runVerifyDuplicate(forceAlert) {
        if (!docInput) return;
        const query = docInput.value.trim();

        if (query.length < 2) {
            duplicateBox.style.display = 'none';
            checkLoading.style.display = 'none';
            return;
        }

        checkLoading.style.display = 'block';
        const requestId = ++activeRequestId;

        fetch('ajax_check_duplicate_doc.php?document_number=' + encodeURIComponent(query))
            .then(res => res.text())
            .then(text => {
                if (requestId !== activeRequestId) return;
                checkLoading.style.display = 'none';

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Lỗi parse JSON:", text);
                    return;
                }

                if (data.status === 'success' && data.exists && data.documents && data.documents.length > 0) {
                    duplicateCount.innerText = data.count;
                    let rowsHtml = '';
                    data.documents.forEach(d => {
                        const bClass = d.status === 'Còn hiệu lực' ? 'bg-success' : (d.status === 'Hết hiệu lực' ? 'bg-secondary' : 'bg-warning text-dark');
                        rowsHtml += `
                            <tr>
                                <td class="fw-bold text-danger">${d.document_number}</td>
                                <td>${d.content}</td>
                                <td>${d.authority_name || 'Chưa rõ'}</td>
                                <td class="text-center">${d.issued_date_formatted}</td>
                                <td class="text-center"><span class="badge ${bClass}">${d.status}</span></td>
                                <td class="text-center">
                                    <a href="view_document.php?id=${d.id}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Mở xem"><i class="fas fa-external-link-alt"></i></a>
                                </td>
                            </tr>`;
                    });
                    duplicateTableBody.innerHTML = rowsHtml;
                    duplicateBox.style.display = 'block';
                } else {
                    duplicateBox.style.display = 'none';
                    duplicateTableBody.innerHTML = '';
                    if (forceAlert && typeof Swal !== 'undefined') {
                        Swal.fire({toast: true, position: 'top-end', icon: 'info', title: 'Không tìm thấy văn bản trùng số hiệu', showConfirmButton: false, timer: 2000});
                    }
                }
            })
            .catch(err => {
                if (requestId === activeRequestId) {
                    checkLoading.style.display = 'none';
                    console.error("Fetch Error:", err);
                }
            });
    }

    docInput.addEventListener("input", function() {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => { runVerifyDuplicate(false); }, 600);
    });

    if (btnManualCheck) {
        btnManualCheck.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearTimeout(checkTimeout);
            runVerifyDuplicate(true);
        });
    }

    if (btnCloseBox) {
        btnCloseBox.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            duplicateBox.style.display = 'none';
        });
    }

    // =========================================================================
    // KHỐI 2: XỬ LÝ VĂN BẢN MẬT (ĐỘC LẬP HOÀN TOÀN)
    // =========================================================================
    const secretCheckbox = document.getElementById("isSecretCheckbox");
    const fileContainer = document.getElementById("fileUploadContainer");
    const fileInput = document.getElementById("fileInput");
    const secretWarning = document.getElementById("secretFileWarning");

    function processSecretToggle() {
        const isSec = secretCheckbox.checked || ['-M', '/M', ' M ', 'MẬT'].some(k => docInput.value.toUpperCase().includes(k));
        if (isSec) {
            secretCheckbox.checked = true;
            fileContainer.classList.add("d-none");
            secretWarning.classList.remove("d-none");
            fileInput.removeAttribute("required");
            fileInput.value = "";
        } else {
            fileContainer.classList.remove("d-none");
            secretWarning.classList.add("d-none");
            fileInput.setAttribute("required", "required");
        }
    }

    secretCheckbox.addEventListener("change", processSecretToggle);
    docInput.addEventListener("input", processSecretToggle);
    processSecretToggle();

    // =========================================================================
    // KHỐI 3: CÁC XỬ LÝ NGÀY THÁNG VÀ MODAL
    // =========================================================================
    const expiredInput = document.querySelector('input[name="expired_date"]');
    const statusSelect = document.querySelector('select[name="status"]');
    if (expiredInput && statusSelect) {
        expiredInput.addEventListener('change', function() {
            if (this.value && new Date(this.value) < new Date().setHours(0,0,0,0)) {
                statusSelect.value = 'Hết hiệu lực';
            }
        });
    }

    const issuedInput = document.getElementById("issuedDateInput");
    const effectiveInput = document.getElementById("effectiveDateInput");
    issuedInput.addEventListener("change", function() {
        if (this.value && !effectiveInput.value) effectiveInput.value = this.value;
    });

    function handleAjaxSubmit(formId, btnId, modalId, tsInstance, url) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById(btnId);
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>...'; btn.disabled = true;
            fetch(url, { method: 'POST', body: new FormData(form) })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        tsInstance.addOption({ value: String(data.id), text: String(data.name) });
                        tsInstance.setValue(String(data.id));
                        form.reset(); bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    } else { alert(data.message); }
                })
                .finally(() => { btn.innerHTML = 'Lưu lại'; btn.disabled = false; });
        });
    }

    handleAjaxSubmit('formAddType', 'btnSubmitType', 'modalAddType', tsType, 'ajax_add_type.php');
    handleAjaxSubmit('formAddAuthority', 'btnSubmitAuthority', 'modalAddAuthority', tsAuth, 'ajax_add_authority.php');
});
</script>

<?php include 'footer.php'; ?>