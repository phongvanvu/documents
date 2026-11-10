<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền: Admin hoặc người có quyền categories.manage
if (!isset($_SESSION['user_id']) || (!has_permission('categories.manage') && ($_SESSION['role'] ?? '') !== 'admin')) {
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function formatDateVN($d) {
    if (empty($d) || in_array($d, ['0000-00-00', '1970-01-01'], true)) return '';
    $t = strtotime($d); return $t ? date('d/m/Y', $t) : '';
}
function formatBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
function normalizePath($path) {
    return str_replace('\\', '/', $path);
}

// =========================================================================
// 1. XỬ LÝ CÁC YÊU CẦU THAO TÁC TỆP VẬT LÝ (XÓA RÁC / XÓA BẢN GHI LỖI)
// =========================================================================
if (isset($_GET['delete_orphan'])) {
    $file_to_delete = $_GET['delete_orphan'];
    $real_base = realpath('uploads');
    $real_file = realpath($file_to_delete);
    
    if ($real_file && $real_base && strpos($real_file, $real_base) === 0 && is_file($real_file)) {
        unlink($real_file);
        $_SESSION['flash_msg'] = "Đã dọn dẹp thành công tệp rác!";
    } else {
        $_SESSION['flash_msg_error'] = "Không thể xóa tệp này (Lỗi đường dẫn hoặc tệp không tồn tại).";
    }
    header("Location: audit_documents.php");
    exit;
}

if (isset($_GET['delete_record'])) {
    $record_id = filter_input(INPUT_GET, 'delete_record', FILTER_VALIDATE_INT);
    if ($record_id) {
        $del_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $del_stmt->bind_param("i", $record_id);
        if ($del_stmt->execute()) {
            $_SESSION['flash_msg'] = "Đã xóa vĩnh viễn bản ghi văn bản khỏi hệ thống.";
        } else {
            $_SESSION['flash_msg_error'] = "Có lỗi xảy ra khi xóa bản ghi.";
        }
        $del_stmt->close();
    }
    header("Location: audit_documents.php");
    exit;
}

// =========================================================================
// 2. THU THẬP DỮ LIỆU RÀ SOÁT TỆP RÁC & TỆP MỒ CÔI
// =========================================================================
$missing_files = [];
$orphan_files = [];
$db_filepaths = [];
$total_orphan_size = 0;

// Lấy file từ documents chính
$res_docs = $conn->query("SELECT id, document_number, content, file_path, issued_date FROM documents WHERE deleted_at IS NULL AND file_path IS NOT NULL AND TRIM(file_path) != ''");
if ($res_docs) {
    while ($row = $res_docs->fetch_assoc()) {
        $path = normalizePath($row['file_path']);
        $doc_upper = mb_strtoupper($row['document_number'], 'UTF-8');
        $db_filepaths[] = $path;
        
        $is_secret = (strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);
        if (!$is_secret && !file_exists($path)) {
            $missing_files[] = $row;
        }
    }
}

// Lấy file từ document_attachments
try {
    $res_attach = $conn->query("SELECT attachment_path FROM document_attachments WHERE attachment_path IS NOT NULL AND TRIM(attachment_path) != ''");
    if ($res_attach) {
        while ($ra = $res_attach->fetch_assoc()) {
            $db_filepaths[] = normalizePath($ra['attachment_path']);
        }
    }
} catch (Exception $e) {}

// Lấy file từ document_versions (nếu có)
try {
    $res_vers = $conn->query("SELECT file_path FROM document_versions WHERE file_path IS NOT NULL AND TRIM(file_path) != ''");
    if ($res_vers) {
        while ($rv = $res_vers->fetch_assoc()) {
            $db_filepaths[] = normalizePath($rv['file_path']);
        }
    }
} catch (Exception $e) {}

// Quét thư mục uploads
function scanFolder($dir, &$results = array()) {
    if (!is_dir($dir)) return $results;
    $files = scandir($dir);
    foreach ($files as $value) {
        if ($value !== "." && $value !== "..") {
            $path = $dir . '/' . $value;
            if (!is_dir($path)) {
                $results[] = normalizePath($path);
            } else if ($value !== 'avatars') {
                scanFolder($path, $results);
            }
        }
    }
    return $results;
}

$all_physical_files = scanFolder('uploads');
foreach ($all_physical_files as $file) {
    if (in_array(basename($file), ['index.php', '.htaccess', 'default.jpg'], true)) continue;
    if (!in_array($file, $db_filepaths, true)) {
        $orphan_files[] = $file;
        $total_orphan_size += filesize($file);
    }
}

// Xử lý xóa toàn bộ file rác
if (isset($_GET['delete_all_orphans'])) {
    $deleted_count = 0;
    foreach ($orphan_files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deleted_count++;
        }
    }
    $_SESSION['flash_msg'] = "Đã dọn dẹp hàng loạt thành công $deleted_count tệp rác!";
    header("Location: audit_documents.php");
    exit;
}

// =========================================================================
// 3. THU THẬP DỮ LIỆU RÀ SOÁT LOGIC (ĐÃ SỬA LỖI SO SÁNH NGÀY 0000-00-00)
// =========================================================================
// Lỗi 1: Quá hạn nhưng status vẫn là 'Còn hiệu lực'
$sql_expired = "SELECT id, document_number, content, issued_date, expired_date, status 
                FROM documents 
                WHERE deleted_at IS NULL 
                  AND expired_date IS NOT NULL 
                  AND YEAR(expired_date) > 1900 
                  AND expired_date < CURDATE() 
                  AND status = 'Còn hiệu lực' 
                ORDER BY expired_date ASC";
$err_expired = $conn->query($sql_expired)->fetch_all(MYSQLI_ASSOC);

// Lỗi 2: Thiếu ngày có hiệu lực
$sql_no_eff = "SELECT id, document_number, content, issued_date, status 
               FROM documents 
               WHERE deleted_at IS NULL 
                 AND (effective_date IS NULL OR YEAR(effective_date) <= 1900) 
                 AND issued_date IS NOT NULL 
                 AND YEAR(issued_date) > 1900 
               ORDER BY id DESC LIMIT 50";
$err_no_eff = $conn->query($sql_no_eff)->fetch_all(MYSQLI_ASSOC);

// Lỗi 3: Số hiệu văn bản bị thừa khoảng trắng
$sql_trim = "SELECT id, document_number, content FROM documents WHERE deleted_at IS NULL AND document_number != TRIM(document_number)";
$err_trim = $conn->query($sql_trim)->fetch_all(MYSQLI_ASSOC);

$title = "Rà soát & Chuẩn hóa Dữ liệu";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-search-plus text-danger me-2"></i>Trung tâm Rà soát & Chuẩn hóa Dữ liệu
                        </h4>
                        <div class="text-muted small">Kiểm toán toàn diện: Tệp rác ổ cứng, tệp bị mất, trạng thái quá hạn và logic ngày tháng</div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Quét lại toàn bộ
                    </button>
                </div>

                <!-- CÁC THẺ THỐNG KÊ TỔNG QUAN -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-warning bg-warning bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Quá hạn chưa đổi status</div>
                                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= count($err_expired) ?></h3>
                                </div>
                                <i class="fas fa-calendar-times text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-primary bg-primary bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Thiếu ngày có hiệu lực</div>
                                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= count($err_no_eff) ?></h3>
                                </div>
                                <i class="fas fa-calendar-minus text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-danger bg-danger bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Mất tệp đính kèm vật lý</div>
                                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= count($missing_files) ?></h3>
                                </div>
                                <i class="fas fa-unlink text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-secondary bg-secondary bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Tệp rác mồ côi (Dung lượng)</div>
                                    <h3 class="fw-bold text-secondary mb-0 mt-1" style="font-size: 1.3rem;"><?= formatBytes($total_orphan_size) ?></h3>
                                </div>
                                <i class="fas fa-trash-alt text-secondary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHẦN 1: QUẢN LÝ DỮ LIỆU TỆP TIN VẬT LÝ -->
                <div class="row g-3 mb-4">
                    <!-- Khung Mất File Vật Lý -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom border-danger border-2 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Văn bản mất tệp vật lý (<?= count($missing_files) ?>)
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <p class="text-muted small mb-2">Bản ghi có trên hệ thống nhưng tệp đính kèm không tồn tại trên máy chủ (đã loại trừ văn bản Mật).</p>
                                <?php if (count($missing_files) > 0): ?>
                                    <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                                        <?php foreach ($missing_files as $doc): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                                <div class="pe-2 text-truncate">
                                                    <strong class="text-dark"><i class="fas fa-file-alt me-1 text-secondary"></i><?= h($doc['document_number']) ?></strong><br>
                                                    <small class="text-danger"><i class="fas fa-unlink me-1"></i><?= h($doc['file_path']) ?></small>
                                                </div>
                                                <div class="d-flex gap-1 text-nowrap">
                                                    <a href="edit_document.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-xs btn-outline-primary px-2" title="Sửa lại file">Sửa</a>
                                                    <button type="button" class="btn btn-xs btn-outline-danger px-2 delete-record-btn" data-id="<?= (int)$doc['id'] ?>" title="Xóa bản ghi">Xóa</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-success fst-italic small">
                                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>Tất cả bản ghi đều có tệp đính kèm đầy đủ.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Khung File Rác Mồ Côi -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom border-warning border-2 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-trash-alt text-warning me-2"></i>Tệp rác chưa liên kết (<?= count($orphan_files) ?>)
                                </h6>
                                <?php if (count($orphan_files) > 0): ?>
                                    <button type="button" class="btn btn-sm btn-danger fw-bold py-1 px-2" id="deleteAllOrphansBtn">
                                        <i class="fas fa-dumpster-fire me-1"></i>Xóa sạch rác
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-3">
                                <p class="text-muted small mb-2">Tệp nằm trên ổ cứng nhưng không thuộc văn bản nào. <strong class="text-danger">Dung lượng: <?= formatBytes($total_orphan_size) ?></strong></p>
                                <?php if (count($orphan_files) > 0): ?>
                                    <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                                        <?php foreach ($orphan_files as $file): 
                                            $fsize = formatBytes(filesize($file), 1);
                                        ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                                <div class="pe-2 text-truncate">
                                                    <i class="fas fa-file-code text-secondary me-1"></i>
                                                    <span class="small fw-semibold text-dark"><?= h(basename($file)) ?></span><br>
                                                    <span class="badge bg-light text-muted border"><?= $fsize ?></span>
                                                    <small class="text-muted ms-1"><?= h($file) ?></small>
                                                </div>
                                                <div class="d-flex gap-1 text-nowrap">
                                                    <a href="<?= h($file) ?>" target="_blank" class="btn btn-xs btn-outline-primary px-2">Xem</a>
                                                    <button type="button" class="btn btn-xs btn-outline-danger px-2 delete-orphan-btn" data-file="<?= h($file) ?>">Xóa</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-success fst-italic small">
                                        <i class="fas fa-shield-alt fa-2x mb-2 text-success"></i><br>Thư mục uploads rất sạch sẽ, không có tệp thừa.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHẦN 2: CHUẨN HÓA DỮ LIỆU HÀNH CHÍNH & LOGIC NGÀY THÁNG -->
                <!-- 1. Văn bản quá hạn chưa chuyển trạng thái -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-calendar-times text-warning me-2"></i>1. Văn bản quá hạn nhưng vẫn mang trạng thái "Còn hiệu lực" (<?= count($err_expired) ?>)
                        </h6>
                        <?php if (count($err_expired) > 0): ?>
                            <button type="button" class="btn btn-warning btn-sm fw-semibold text-dark btn-batch-fix" data-action="fix_expired_status">
                                <i class="fas fa-magic me-1"></i>Sửa hàng loạt -> "Hết hiệu lực"
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 150px;" class="ps-3">Số ký hiệu</th>
                                        <th>Trích yếu nội dung</th>
                                        <th style="width: 120px;" class="text-center">Ngày hết hạn</th>
                                        <th style="width: 120px;" class="text-center">Trạng thái hiện tại</th>
                                        <th style="width: 80px;" class="text-center">Tác vụ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($err_expired) > 0): ?>
                                        <?php foreach ($err_expired as $row): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-danger"><?= h($row['document_number']) ?></td>
                                                <td><?= h($row['content']) ?></td>
                                                <td class="text-center text-danger fw-semibold"><?= formatDateVN($row['expired_date']) ?></td>
                                                <td class="text-center"><span class="badge bg-success"><?= h($row['status']) ?></span></td>
                                                <td class="text-center">
                                                    <a href="edit_document.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" title="Sửa"><i class="fas fa-edit"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-3 text-muted fst-italic"><i class="fas fa-check-circle text-success me-1"></i>Trạng thái hiệu lực hoàn toàn chính xác.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. Văn bản thiếu ngày có hiệu lực -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-calendar-minus text-primary me-2"></i>2. Văn bản thiếu ngày có hiệu lực (<?= count($err_no_eff) ?>)
                        </h6>
                        <?php if (count($err_no_eff) > 0): ?>
                            <button type="button" class="btn btn-primary btn-sm fw-semibold btn-batch-fix" data-action="fix_missing_effective_date">
                                <i class="fas fa-clone me-1"></i>Gán tự động = "Ngày ban hành"
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 150px;" class="ps-3">Số ký hiệu</th>
                                        <th>Trích yếu nội dung</th>
                                        <th style="width: 120px;" class="text-center">Ngày ban hành</th>
                                        <th style="width: 120px;" class="text-center">Ngày hiệu lực</th>
                                        <th style="width: 80px;" class="text-center">Tác vụ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($err_no_eff) > 0): ?>
                                        <?php foreach ($err_no_eff as $row): ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark"><?= h($row['document_number']) ?></td>
                                                <td><?= h($row['content']) ?></td>
                                                <td class="text-center"><?= formatDateVN($row['issued_date']) ?></td>
                                                <td class="text-center text-muted fst-italic">Trống (NULL)</td>
                                                <td class="text-center">
                                                    <a href="edit_document.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" title="Sửa"><i class="fas fa-edit"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-3 text-muted fst-italic"><i class="fas fa-check-circle text-success me-1"></i>Tất cả văn bản đều đã có ngày hiệu lực.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Số ký hiệu bị thừa khoảng trắng -->
                <?php if (count($err_trim) > 0): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-cut text-info me-2"></i>3. Số ký hiệu văn bản bị thừa khoảng trắng đầu/cuối (<?= count($err_trim) ?>)
                        </h6>
                        <button type="button" class="btn btn-info btn-sm fw-semibold text-dark btn-batch-fix" data-action="trim_doc_numbers">
                            <i class="fas fa-compress-alt me-1"></i>Cắt khoảng trắng thừa
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 200px;" class="ps-3">Số ký hiệu hiện tại</th>
                                        <th>Trích yếu</th>
                                        <th style="width: 200px;">Sau khi chuẩn hóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($err_trim as $row): ?>
                                        <tr>
                                            <td class="ps-3"><code class="text-danger">"<?= h($row['document_number']) ?>"</code></td>
                                            <td><?= h($row['content']) ?></td>
                                            <td><code class="text-success">"<?= h(trim($row['document_number'])) ?>"</code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['flash_msg'] ?>', showConfirmButton: false, timer: 3500 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_msg_error'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: '<?= $_SESSION['flash_msg_error'] ?>', showConfirmButton: false, timer: 4000 });
        <?php unset($_SESSION['flash_msg_error']); ?>
    <?php endif; ?>

    document.querySelectorAll('.delete-orphan-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let file = this.getAttribute('data-file');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa vĩnh viễn tệp rác?',
                    text: 'Tệp này sẽ bị xóa hoàn toàn khỏi ổ cứng máy chủ.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa ngay',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) window.location.href = 'audit_documents.php?delete_orphan=' + encodeURIComponent(file); });
            } else {
                if (confirm('Xóa vĩnh viễn tệp rác này?')) window.location.href = 'audit_documents.php?delete_orphan=' + encodeURIComponent(file);
            }
        });
    });

    document.querySelectorAll('.delete-record-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let id = this.getAttribute('data-id');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xóa bản ghi mất tệp?',
                    text: 'Bản ghi này đã mất tệp đính kèm. Bạn có chắc muốn xóa khỏi CSDL?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xóa bản ghi',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) window.location.href = 'audit_documents.php?delete_record=' + id; });
            } else {
                if (confirm('Xác nhận xóa bản ghi này?')) window.location.href = 'audit_documents.php?delete_record=' + id;
            }
        });
    });

    const deleteAllBtn = document.getElementById('deleteAllOrphansBtn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Dọn sạch thùng rác?',
                    text: 'Xóa TẤT CẢ <?= count($orphan_files) ?> tệp rác sẽ giải phóng <?= formatBytes($total_orphan_size) ?> dung lượng.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Dọn sạch ngay!',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) window.location.href = 'audit_documents.php?delete_all_orphans=1'; });
            } else {
                if (confirm('Bạn có chắc muốn dọn sạch tất cả tệp rác?')) window.location.href = 'audit_documents.php?delete_all_orphans=1';
            }
        });
    }

    document.querySelectorAll('.btn-batch-fix').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const button = this;

            const executeFix = () => {
                button.disabled = true;
                const oldHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';

                const formData = new FormData();
                formData.append('action', action);

                fetch('ajax_audit_action.php', { method: 'POST', body: formData })
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
                        button.disabled = false;
                        button.innerHTML = oldHtml;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Lỗi kết nối máy chủ');
                    button.disabled = false;
                    button.innerHTML = oldHtml;
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận xử lý tự động?',
                    text: 'Hệ thống sẽ cập nhật hàng loạt cho toàn bộ văn bản trong danh sách.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý xử lý',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeFix(); });
            } else {
                if (confirm('Bạn có chắc muốn xử lý tự động hàng loạt?')) executeFix();
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>