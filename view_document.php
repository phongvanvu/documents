<?php
session_start();
include 'db.php';

// Khôi phục session từ cookie
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

function h($val) { return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8'); }
function formatDateVN($d) {
    if (empty($d) || in_array($d, ['0000-00-00', '1970-01-01'], true)) return '';
    $t = strtotime($d); return $t ? date('d/m/Y', $t) : '';
}
function formatFileSize($b) {
    $b = (int)$b; if ($b <= 0) return '';
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024) return round($b / 1024, 2) . ' KB';
    return $b . ' B';
}

// Lưu referer để quay lại đúng trang
if (isset($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $file = basename(parse_url($ref, PHP_URL_PATH) ?? '');
    $blocked = ['edit_document.php', 'update_document.php', 'view_document.php', 'update_personal_document.php', 'add_document_relation.php', 'delete_document_relation.php', 'edit_document_relation.php', 'search_related_documents.php', 'add_document_attachment.php', 'delete_document_attachment.php', 'suggest_document_relations.php', 'document_versions.php'];
    if (!in_array($file, $blocked, true) && (empty(parse_url($ref, PHP_URL_HOST)) || parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? ''))) {
        $_SESSION['original_list_page'] = $ref;
    }
}
$back_url = $_SESSION['original_list_page'] ?? 'dashboard.php';
$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

function render_error($msg) {
    global $back_url; $title = "Lỗi truy cập"; include 'header.php';
    echo '<div class="container-fluid main"><div class="row flex-nowrap"><div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">';
    include 'sidebar.php';
    echo '</div><div class="content col py-5 text-center"><div class="card shadow-sm border-0 mx-auto p-4" style="max-width: 500px;"><i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i><h4 class="fw-bold">Không thể tải văn bản</h4><p class="text-muted">' . h($msg) . '</p><a href="' . h($back_url) . '" class="btn btn-primary mt-2"><i class="fas fa-arrow-left me-2"></i>Quay lại</a></div></div></div></div>';
    include 'footer.php'; exit;
}

if (!$document_id) render_error("ID văn bản không hợp lệ.");

// Lấy thông tin văn bản
$stmt = $conn->prepare("SELECT d.*, dt.type_name, ia.authority_name AS issuing_authority FROM documents d LEFT JOIN document_types dt ON d.document_type_id = dt.id LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id WHERE d.id = ?");
$stmt->bind_param("i", $document_id); $stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) render_error("Văn bản không tồn tại hoặc đã bị xóa.");

$doc_num = $document['document_number'] ?? '';
$doc_upper = mb_strtoupper($doc_num, 'UTF-8');
$is_secret = (strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);

$file_path = $document['file_path'] ?? '';
$file_exists = (!empty($file_path) && file_exists($file_path));
$is_pdf = (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'pdf');

$status = trim($document['status'] ?? 'Không xác định');
$status_badges = ['Còn hiệu lực' => 'bg-success', 'Hết hiệu lực' => 'bg-danger', 'Được thay thế' => 'bg-warning text-dark'];
$status_class = $status_badges[$status] ?? 'bg-secondary';

$tags = array_filter(array_map('trim', explode(',', $document['tags'] ?? '')));
$user_role = $_SESSION['role'] ?? 'viewer';

// Lấy danh sách văn bản liên quan (Xuôi & Ngược)
function get_relations($conn, $sql, $id) {
    $stmt = $conn->prepare($sql); $stmt->bind_param("i", $id); $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $res;
}

$out_relations = get_relations($conn, "SELECT dr.id AS relation_id, dr.relation_type, dr.note, rd.id AS related_id, rd.document_number, rd.content, rd.issued_date, dt.type_name, ia.authority_name FROM document_relations dr INNER JOIN documents rd ON dr.related_document_id = rd.id LEFT JOIN document_types dt ON rd.document_type_id = dt.id LEFT JOIN issuing_authorities ia ON rd.authority_id = ia.id WHERE dr.document_id = ? ORDER BY dr.id DESC", $document_id);
$in_relations = get_relations($conn, "SELECT dr.id AS relation_id, dr.relation_type, dr.note, sd.id AS source_id, sd.document_number, sd.content, sd.issued_date, dt.type_name, ia.authority_name FROM document_relations dr INNER JOIN documents sd ON dr.document_id = sd.id LEFT JOIN document_types dt ON sd.document_type_id = dt.id LEFT JOIN issuing_authorities ia ON sd.authority_id = ia.id WHERE dr.related_document_id = ? ORDER BY dr.id DESC", $document_id);

// Lấy tài liệu đính kèm
$stmt_att = $conn->prepare("SELECT * FROM document_attachments WHERE document_id = ? ORDER BY id DESC");
$stmt_att->bind_param("i", $document_id); $stmt_att->execute();
$attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_att->close();

$relation_types = ['Liên quan', 'Căn cứ pháp lý', 'Sửa đổi', 'Bổ sung', 'Thay thế', 'Bị thay thế', 'Hướng dẫn', 'Được hướng dẫn bởi', 'Liên quan nghiệp vụ'];
$attachment_types = ['Ghi chú', 'Biểu mẫu', 'Excel tính toán', 'Công văn tham khảo', 'Tài liệu tham khảo', 'Bản trích điều khoản', 'Ảnh / scan', 'Khác'];

$title = "Chi tiết văn bản: " . $doc_num;
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">

                <!-- Header Chi tiết -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-file-alt me-2"></i>VĂN BẢN SỐ: <?php echo h($doc_num); ?></h5>
                        <div class="d-flex gap-2">
                            <?php if (!empty($document['is_favorite'])): ?><span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Quan trọng</span><?php endif; ?>
                            <?php if ($is_secret): ?><span class="badge bg-dark"><i class="fas fa-lock me-1"></i>MẬT</span><?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <table class="table table-bordered align-middle mb-4">
                            <tbody>
                                <tr>
                                    <th class="bg-light" style="width: 20%;">Số ký hiệu</th>
                                    <td class="fw-bold text-danger fs-5"><?php echo h($doc_num); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">Trích yếu nội dung</th>
                                    <td class="lh-base fw-semibold" style="white-space: pre-line;"><?php echo h($document['content'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">Loại văn bản & Cơ quan BH</th>
                                    <td>
                                        <span class="badge bg-secondary me-2"><?php echo h($document['type_name'] ?? 'N/A'); ?></span>
                                        <span class="fw-semibold text-dark"><?php echo h($document['issuing_authority'] ?? 'N/A'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">Ngày ban hành / Hiệu lực</th>
                                    <td>
                                        <i class="far fa-calendar-alt me-1 text-muted"></i>BH: <strong><?php echo h(formatDateVN($document['issued_date'] ?? '')); ?></strong>
                                        <?php if (!empty(formatDateVN($document['effective_date'] ?? ''))): ?>
                                            <span class="ms-3 text-success"><i class="far fa-calendar-check me-1"></i>HL: <strong><?php echo h(formatDateVN($document['effective_date'])); ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($document['expired_date'])): ?>
                                            <span class="ms-3 text-danger"><i class="far fa-calendar-times me-1"></i>Hết hạn: <strong><?php echo h(formatDateVN($document['expired_date'])); ?></strong></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light">Tình trạng hiệu lực</th>
                                    <td><span class="badge <?php echo h($status_class); ?>"><?php echo h($status); ?></span></td>
                                </tr>
                                <tr>
                                    <th class="bg-light">Tệp đính kèm chính</th>
                                    <td>
                                        <?php if ($is_secret): ?>
                                            <span class="text-danger fw-semibold"><i class="fas fa-user-secret me-1"></i>Văn bản Mật - Không hiển thị tệp</span>
                                        <?php elseif ($file_exists): ?>
                                            <a href="<?php echo h($file_path); ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-bold"><i class="fas fa-file-download me-1"></i>Xem / Tải tệp chính</a>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Không có tệp đính kèm</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Cập nhật Đánh dấu & Ghi chú cá nhân -->
                        <div class="card bg-light border-0 p-3 mb-2">
                            <form action="update_personal_document.php" method="POST">
                                <input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_favorite" id="is_favorite" <?php echo !empty($document['is_favorite']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold text-dark" for="is_favorite">Văn bản quan trọng</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="tags" class="form-control form-control-sm" placeholder="Thẻ tag (cách nhau bởi dấu phẩy)" value="<?php echo h($document['tags'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="personal_note" class="form-control form-control-sm" placeholder="Ghi chú cá nhân..." value="<?php echo h($document['personal_note'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="text-end mt-2">
                                    <button type="submit" class="btn btn-danger btn-sm px-3"><i class="fas fa-save me-1"></i>Lưu thông tin cá nhân</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Khối Văn bản liên quan -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-link me-2 text-danger"></i>Văn bản liên quan</span>
                        <span class="badge bg-danger"><?php echo count($out_relations) + count($in_relations); ?></span>
                    </div>
                    <div class="card-body">
                        <!-- Form Thêm Liên Kết -->
                        <form action="add_document_relation.php" method="POST" class="mb-3" id="add_relation_form">
                            <input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
                            <input type="hidden" name="related_document_id" id="related_document_id">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <select name="relation_type" class="form-select form-select-sm" required>
                                        <?php foreach ($relation_types as $t): ?><option value="<?php echo h($t); ?>"><?php echo h($t); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="related_document_keyword" class="form-control" placeholder="Tìm số ký hiệu, trích yếu..." autocomplete="off">
                                        <button type="button" class="btn btn-outline-danger" id="search_related_document_btn"><i class="fas fa-search"></i></button>
                                    </div>
                                    <div id="selected_related_document" class="alert alert-success py-1 px-2 mt-1 d-none mb-0 small fw-bold"></div>
                                    <div id="related_document_results" class="list-group position-absolute w-100 shadow-sm z-3"></div>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Ghi chú liên kết">
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-plus me-1"></i>Thêm</button>
                                </div>
                            </div>
                        </form>

                        <!-- Danh sách liên kết -->
                        <?php if (count($out_relations) === 0 && count($in_relations) === 0): ?>
                            <div class="text-center text-muted py-3 small"><i class="fas fa-link fa-2x mb-2 text-light"></i><br>Chưa có văn bản liên quan.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle small">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="width: 130px;">Loại liên kết</th>
                                            <th>Số ký hiệu & Trích yếu</th>
                                            <th style="width: 110px;">Ngày BH</th>
                                            <th>Ghi chú</th>
                                            <th style="width: 100px;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_merge($out_relations, $in_relations) as $rel): 
                                            $is_out = isset($rel['related_id']);
                                            $rel_id = $is_out ? $rel['related_id'] : $rel['source_id'];
                                        ?>
                                            <tr>
                                                <td class="text-center"><span class="badge <?php echo $is_out ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo h($rel['relation_type']); ?></span></td>
                                                <td>
                                                    <a href="view_document.php?id=<?php echo (int)$rel_id; ?>" class="fw-bold text-decoration-none"><?php echo h($rel['document_number']); ?></a>
                                                    <div class="text-muted small"><?php echo h($rel['content']); ?></div>
                                                </td>
                                                <td class="text-center"><?php echo h(formatDateVN($rel['issued_date'] ?? '')); ?></td>
                                                <td><?php echo h($rel['note'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <a href="edit_document_relation.php?id=<?php echo (int)$rel['relation_id']; ?>&doc_id=<?php echo (int)$document['id']; ?>" class="btn btn-outline-secondary btn-sm py-0 px-2 me-1" title="Sửa liên kết"><i class="fas fa-edit"></i></a>
                                                    <form action="delete_document_relation.php" method="POST" class="delete-relation-form d-inline">
                                                        <input type="hidden" name="relation_id" value="<?php echo (int)$rel['relation_id']; ?>">
                                                        <input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Xóa liên kết"><i class="fas fa-times"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Khối Tài liệu làm việc kèm theo -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-folder-open me-2 text-danger"></i>Tài liệu làm việc đính kèm</span>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!$is_secret && (count($attachments) > 0 || $file_exists)): ?>
                                <a href="download_attachments_zip.php?document_id=<?php echo (int)$document['id']; ?>" class="btn btn-danger btn-sm py-0 px-2 fw-semibold" title="Tải tất cả tệp chính và phụ lục thành file ZIP">
                                    <i class="fas fa-file-archive me-1"></i>Tải toàn bộ (.ZIP)
                                </a>
                            <?php endif; ?>
                            <span class="badge bg-danger"><?php echo count($attachments); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (in_array($user_role, ['admin', 'editor'], true)): ?>
                            <form action="add_document_attachment.php" method="POST" enctype="multipart/form-data" class="mb-3">
                                <input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
                                <div class="row g-2">
                                    <div class="col-md-3"><input type="text" name="attachment_title" class="form-control form-control-sm" placeholder="Tên tài liệu..." required></div>
                                    <div class="col-md-2">
                                        <select name="attachment_type" class="form-select form-select-sm">
                                            <?php foreach ($attachment_types as $at): ?><option value="<?php echo h($at); ?>"><?php echo h($at); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><input type="file" name="attachment_file" class="form-control form-control-sm" required></div>
                                    <div class="col-md-3"><input type="text" name="note" class="form-control form-control-sm" placeholder="Ghi chú..."></div>
                                    <div class="col-md-1 d-grid"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-upload me-1"></i>Tải lên</button></div>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if (count($attachments) === 0): ?>
                            <div class="text-center text-muted py-3 small"><i class="fas fa-folder-open fa-2x mb-2 text-light"></i><br>Chưa có tài liệu kèm theo.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle small">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="width: 140px;">Loại</th>
                                            <th>Tên tài liệu</th>
                                            <th style="width: 100px;">Dung lượng</th>
                                            <th>Ghi chú</th>
                                            <th style="width: 110px;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attachments as $att): 
                                            $att_path = $att['attachment_path'] ?? '';
                                            $att_exists = (!empty($att_path) && file_exists($att_path));
                                        ?>
                                            <tr>
                                                <td class="text-center"><span class="badge bg-secondary"><?php echo h($att['attachment_type'] ?? 'Khác'); ?></span></td>
                                                <td class="fw-semibold"><?php echo h($att['attachment_title'] ?? basename($att_path)); ?></td>
                                                <td class="text-center"><?php echo h(formatFileSize($att['file_size'] ?? 0)); ?></td>
                                                <td><?php echo h($att['note'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <?php if ($att_exists): ?>
                                                        <a href="<?php echo h($att_path); ?>" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-2 me-1" title="Tải về / Xem"><i class="fas fa-download"></i></a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Tệp lỗi</span>
                                                    <?php endif; ?>
                                                    <?php if (in_array($user_role, ['admin', 'editor'], true)): ?>
                                                        <form action="delete_document_attachment.php" method="POST" class="delete-attachment-form d-inline">
                                                            <input type="hidden" name="attachment_id" value="<?php echo (int)$att['id']; ?>">
                                                            <input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_secret && $file_exists && $is_pdf): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-file-pdf me-2 text-danger"></i>Xem nhanh nội dung PDF</span>
                        <div class="d-flex gap-2">
                            <a href="view_pdf_watermark.php?id=<?= (int)$document['id'] ?>" target="_blank" class="btn btn-danger btn-sm" title="Mở chế độ xem có dấu mờ bảo mật">
                                <i class="fas fa-shield-alt me-1"></i>Xem bảo mật (Watermark)
                            </a>
                            <a href="<?php echo h($file_path); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Mở tab mới</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <iframe src="<?php echo h($file_path); ?>" style="width: 100%; height: 700px; border: 0;"></iframe>
                    </div>
                </div>
            <?php endif; ?>

                <!-- Nút điều hướng -->
                <div class="text-end pt-2">
                    <a href="document_versions.php?id=<?php echo (int)$document['id']; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-history me-1"></i>Lịch sử phiên bản
                    </a>
                    <a href="suggest_document_relations.php?id=<?php echo (int)$document['id']; ?>" class="btn btn-outline-danger me-2"><i class="fas fa-magic me-1"></i>Gợi ý liên kết</a>
                    <a href="<?php echo h($back_url); ?>" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i>Quay lại</a>
                    <?php if (in_array($user_role, ['admin', 'editor'], true)): ?>
                        <a href="edit_document.php?id=<?php echo (int)$document['id']; ?>" class="btn btn-warning fw-bold"><i class="fas fa-edit me-1"></i>Sửa văn bản</a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Flash Messages
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?php echo json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 3000 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: <?php echo json_encode($_SESSION['flash_error'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 3000 });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    // Autocomplete văn bản liên quan
    const relatedInput = document.getElementById('related_document_keyword');
    const relatedBtn = document.getElementById('search_related_document_btn');
    const relatedBox = document.getElementById('related_document_results');
    const relatedIdInput = document.getElementById('related_document_id');
    const selectedBox = document.getElementById('selected_related_document');

    function searchDocs() {
        let q = relatedInput.value.trim();
        if (q.length < 2) return;
        relatedBox.innerHTML = '<div class="list-group-item small text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Đang tìm...</div>';
        
        fetch('search_related_documents.php?document_id=<?php echo (int)$document['id']; ?>&q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success' || !data.documents.length) {
                    relatedBox.innerHTML = '<div class="list-group-item small text-muted">Không tìm thấy văn bản phù hợp</div>';
                    return;
                }
                let html = '';
                data.documents.forEach(d => {
                    html += `<button type="button" class="list-group-item list-group-item-action py-2 small" onclick="selectDoc(${d.id}, '${d.document_number}')">
                        <strong class="text-primary">${d.document_number}</strong> - ${d.content}
                    </button>`;
                });
                relatedBox.innerHTML = html;
            });
    }

    window.selectDoc = function(id, num) {
        relatedIdInput.value = id;
        selectedBox.innerText = 'Đã chọn: ' + num;
        selectedBox.classList.remove('d-none');
        relatedBox.innerHTML = '';
    };

    if (relatedBtn) relatedBtn.addEventListener('click', searchDocs);
    if (relatedInput) {
        relatedInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); searchDocs(); } });
    }

    // Confirm Delete
    document.querySelectorAll('.delete-relation-form, .delete-attachment-form').forEach(f => {
        f.addEventListener('submit', function(e) {
            e.preventDefault();
            if (confirm('Bạn có chắc chắn muốn xóa không?')) this.submit();
        });
    });
});
</script>

<?php include 'footer.php'; ?>