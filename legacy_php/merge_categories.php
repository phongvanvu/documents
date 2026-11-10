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

// =========================================================================
// XỬ LÝ LOGIC GỘP DỮ LIỆU (DATABASE TRANSACTION AN TOÀN)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action'] ?? '');
    $source_id = intval($_POST['source_id'] ?? 0);
    $target_id = intval($_POST['target_id'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];

    if ($source_id <= 0 || $target_id <= 0) {
        $_SESSION['flash_msg_error'] = "Vui lòng chọn đầy đủ danh mục nguồn và danh mục đích.";
        header("Location: merge_categories.php");
        exit;
    }

    if ($source_id === $target_id) {
        $_SESSION['flash_msg_error'] = "Lỗi Logic: Không thể gộp một danh mục vào chính nó.";
        header("Location: merge_categories.php");
        exit;
    }

    try {
        $conn->begin_transaction();

        if ($action === 'merge_type') {
            // Lấy tên Loại VB nguồn & đích để ghi log
            $stmt_s = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
            $stmt_s->bind_param("i", $source_id); $stmt_s->execute();
            $source_name = $stmt_s->get_result()->fetch_assoc()['type_name'] ?? "ID $source_id";
            $stmt_s->close();

            $stmt_t = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
            $stmt_t->bind_param("i", $target_id); $stmt_t->execute();
            $target_name = $stmt_t->get_result()->fetch_assoc()['type_name'] ?? "ID $target_id";
            $stmt_t->close();

            // 1. Chuyển tất cả văn bản từ Loại cũ sang Loại mới
            $stmt = $conn->prepare("UPDATE documents SET document_type_id = ? WHERE document_type_id = ?");
            $stmt->bind_param("ii", $target_id, $source_id);
            $stmt->execute();
            $affected_docs = $stmt->affected_rows;
            $stmt->close();
            
            // 2. Xóa Loại văn bản cũ
            $stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
            $stmt->bind_param("i", $source_id);
            $stmt->execute();
            $stmt->close();

            // Ghi nhật ký hệ thống
            $log_text = "Gộp Loại văn bản: [$source_name] vào [$target_name], đã chuyển $affected_docs văn bản.";
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id, $log_text);
                $l->execute();
                $l->close();
            }

            $_SESSION['flash_msg'] = "Đã gộp thành công! Có $affected_docs văn bản được chuyển sang loại mới.";

        } elseif ($action === 'merge_authority') {
            // Lấy tên Cơ quan BH nguồn & đích để ghi log
            $stmt_s = $conn->prepare("SELECT authority_name FROM issuing_authorities WHERE id = ?");
            $stmt_s->bind_param("i", $source_id); $stmt_s->execute();
            $source_name = $stmt_s->get_result()->fetch_assoc()['authority_name'] ?? "ID $source_id";
            $stmt_s->close();

            $stmt_t = $conn->prepare("SELECT authority_name FROM issuing_authorities WHERE id = ?");
            $stmt_t->bind_param("i", $target_id); $stmt_t->execute();
            $target_name = $stmt_t->get_result()->fetch_assoc()['authority_name'] ?? "ID $target_id";
            $stmt_t->close();

            // 1. Chuyển tất cả văn bản từ Cơ quan cũ sang Cơ quan mới
            $stmt = $conn->prepare("UPDATE documents SET authority_id = ? WHERE authority_id = ?");
            $stmt->bind_param("ii", $target_id, $source_id);
            $stmt->execute();
            $affected_docs = $stmt->affected_rows;
            $stmt->close();
            
            // 2. Xóa Cơ quan cũ
            $stmt = $conn->prepare("DELETE FROM issuing_authorities WHERE id = ?");
            $stmt->bind_param("i", $source_id);
            $stmt->execute();
            $stmt->close();

            // Ghi nhật ký hệ thống
            $log_text = "Gộp Cơ quan ban hành: [$source_name] vào [$target_name], đã chuyển $affected_docs văn bản.";
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id, $log_text);
                $l->execute();
                $l->close();
            }

            $_SESSION['flash_msg'] = "Đã gộp thành công! Có $affected_docs văn bản được cập nhật nơi ban hành.";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_msg_error'] = "Lỗi hệ thống SQL: " . $e->getMessage();
    }

    header("Location: merge_categories.php");
    exit;
}

// =========================================================================
// LẤY DỮ LIỆU ĐỔ VÀO SELECT BOX (KÈM SỐ LƯỢNG VĂN BẢN ĐANG DÙNG)
// =========================================================================
$types = $conn->query("SELECT dt.id, dt.type_name, COUNT(d.id) AS total_docs 
                       FROM document_types dt 
                       LEFT JOIN documents d ON dt.id = d.document_type_id AND d.deleted_at IS NULL 
                       GROUP BY dt.id 
                       ORDER BY dt.type_name ASC")->fetch_all(MYSQLI_ASSOC);

$authorities = $conn->query("SELECT ia.id, ia.authority_name, COUNT(d.id) AS total_docs 
                             FROM issuing_authorities ia 
                             LEFT JOIN documents d ON ia.id = d.authority_id AND d.deleted_at IS NULL 
                             GROUP BY ia.id 
                             ORDER BY ia.authority_name ASC")->fetch_all(MYSQLI_ASSOC);

$title = "Gộp danh mục";
include 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-control { border-radius: 8px !important; border-color: #ced4da; padding: 10px; }
    .ts-wrapper.focus .ts-control { border-color: #dc3545 !important; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important; }
    .ts-dropdown .active { background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; }
</style>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        
        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề trang -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-object-group text-danger me-2"></i>Công cụ Gộp & Chuẩn hóa Danh mục
                        </h4>
                        <div class="text-muted small">Khắc phục triệt để tình trạng trùng lặp danh mục do lỗi nhập liệu hoặc viết tắt khác nhau</div>
                    </div>
                </div>

                <div class="alert alert-info border-start border-4 border-info shadow-sm rounded-3 py-2 px-3 mb-4 small">
                    <i class="fas fa-info-circle me-2 text-info fs-5"></i>
                    <strong>Nguyên lý hoạt động:</strong> Toàn bộ văn bản thuộc <strong>"Danh mục BỊ XÓA (Nguồn)"</strong> sẽ được tự động chuyển sang <strong>"Danh mục GIỮ LẠI (Đích)"</strong>. Sau khi chuyển xong, danh mục nguồn sẽ bị xóa vĩnh viễn khỏi hệ thống.
                </div>

                <div class="row g-4">
                    <!-- KHỐI 1: GỘP LOẠI VĂN BẢN -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-header bg-primary text-white fw-bold py-3 d-flex align-items-center">
                                <i class="fas fa-tags me-2"></i>1. GỘP LOẠI VĂN BẢN
                            </div>
                            <div class="card-body p-4">
                                <form action="merge_categories.php" method="POST" class="merge-form">
                                    <input type="hidden" name="action" value="merge_type">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-danger small">
                                            <i class="fas fa-arrow-right me-1"></i>1. Loại văn bản BỊ XÓA (Nguồn) <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select ts-select" name="source_id" required>
                                            <option value="">-- Gõ để tìm loại văn bản cần xóa --</option>
                                            <?php foreach ($types as $t): ?>
                                                <option value="<?= (int)$t['id'] ?>">
                                                    <?= h($t['type_name']) ?> (<?= (int)$t['total_docs'] ?> văn bản)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small mt-1 text-muted"><i class="fas fa-arrow-down me-1"></i> Tất cả văn bản đang thuộc loại này sẽ được chuyển đi.</div>
                                    </div>

                                    <div class="text-center my-2 text-muted">
                                        <i class="fas fa-arrow-down fa-lg text-secondary"></i>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold text-success small">
                                            <i class="fas fa-check-circle me-1"></i>2. Loại văn bản GIỮ LẠI (Đích) <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select ts-select" name="target_id" required>
                                            <option value="">-- Gõ để tìm loại văn bản chuẩn --</option>
                                            <?php foreach ($types as $t): ?>
                                                <option value="<?= (int)$t['id'] ?>">
                                                    <?= h($t['type_name']) ?> (<?= (int)$t['total_docs'] ?> văn bản)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small mt-1 text-muted"><i class="fas fa-check me-1 text-success"></i> Danh mục này sẽ tiếp nhận các văn bản ở trên.</div>
                                    </div>

                                    <div class="text-end border-top pt-3 mt-2">
                                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                                            <i class="fas fa-compress-arrows-alt me-2"></i>Thực hiện gộp Loại VB
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- KHỐI 2: GỘP CƠ QUAN / NƠI BAN HÀNH -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 rounded-3 h-100">
                            <div class="card-header bg-success text-white fw-bold py-3 d-flex align-items-center">
                                <i class="fas fa-building me-2"></i>2. GỘP CƠ QUAN / NƠI BAN HÀNH
                            </div>
                            <div class="card-body p-4">
                                <form action="merge_categories.php" method="POST" class="merge-form">
                                    <input type="hidden" name="action" value="merge_authority">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold text-danger small">
                                            <i class="fas fa-arrow-right me-1"></i>1. Cơ quan BỊ XÓA (Nguồn) <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select ts-select" name="source_id" required>
                                            <option value="">-- Gõ để tìm cơ quan cần xóa --</option>
                                            <?php foreach ($authorities as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>">
                                                    <?= h($a['authority_name']) ?> (<?= (int)$a['total_docs'] ?> văn bản)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small mt-1 text-muted"><i class="fas fa-arrow-down me-1"></i> Tất cả văn bản thuộc cơ quan này sẽ được chuyển đi.</div>
                                    </div>

                                    <div class="text-center my-2 text-muted">
                                        <i class="fas fa-arrow-down fa-lg text-secondary"></i>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold text-success small">
                                            <i class="fas fa-check-circle me-1"></i>2. Cơ quan GIỮ LẠI (Đích) <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select ts-select" name="target_id" required>
                                            <option value="">-- Gõ để tìm cơ quan chuẩn --</option>
                                            <?php foreach ($authorities as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>">
                                                    <?= h($a['authority_name']) ?> (<?= (int)$a['total_docs'] ?> văn bản)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text small mt-1 text-muted"><i class="fas fa-check me-1 text-success"></i> Danh mục này sẽ tiếp nhận các văn bản ở trên.</div>
                                    </div>

                                    <div class="text-end border-top pt-3 mt-2">
                                        <button type="submit" class="btn btn-success px-4 fw-bold">
                                            <i class="fas fa-compress-arrows-alt me-2"></i>Thực hiện gộp Cơ quan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo Tom Select cho các thẻ select
    document.querySelectorAll('.ts-select').forEach((el) => {
        new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
    });

    // Bắt sự kiện submit form để kiểm tra logic và cảnh báo xác nhận
    document.querySelectorAll('.merge-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            const sourceVal = this.querySelector('select[name="source_id"]').value;
            const targetVal = this.querySelector('select[name="target_id"]').value;

            if (sourceVal === targetVal) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Lỗi Logic', text: 'Bạn không thể gộp một danh mục vào chính nó. Vui lòng chọn danh mục đích khác!', confirmButtonColor: '#d33' });
                } else {
                    alert('Bạn không thể gộp một danh mục vào chính nó!');
                }
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận gộp dữ liệu?',
                    text: 'Hành động này sẽ cập nhật tất cả văn bản sang danh mục đích và XÓA VĨNH VIỄN danh mục nguồn. Bạn không thể hoàn tác thao tác này!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đồng ý, gộp ngay!',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            } else {
                if (confirm('Hành động này sẽ cập nhật tất cả văn bản và xóa vĩnh viễn danh mục nguồn. Bạn có chắc chắn không?')) {
                    this.submit();
                }
            }
        });
    });

    // Flash Messages
    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= addslashes($_SESSION['flash_msg']) ?>', showConfirmButton: false, timer: 4000 });
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_msg_error'])): ?>
        if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: '<?= addslashes($_SESSION['flash_msg_error']) ?>', showConfirmButton: false, timer: 5000 });
        <?php unset($_SESSION['flash_msg_error']); ?>
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>