<?php
session_start();
include 'db.php';

// 1. Kiểm tra đăng nhập & quyền
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$relation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'relation_id', FILTER_VALIDATE_INT);
$doc_id = filter_input(INPUT_GET, 'doc_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);

if (!$relation_id || !$doc_id) {
    $_SESSION['flash_error'] = "Thông tin liên kết không hợp lệ.";
    header("Location: dashboard.php");
    exit;
}

$allowed_types = ['Liên quan', 'Căn cứ pháp lý', 'Sửa đổi', 'Bổ sung', 'Thay thế', 'Bị thay thế', 'Hướng dẫn', 'Được hướng dẫn bởi', 'Liên quan nghiệp vụ'];

// 2. Xử lý khi Submit Form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relation_type = trim($_POST['relation_type'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if (!in_array($relation_type, $allowed_types, true)) {
        $_SESSION['flash_error'] = "Loại liên kết không hợp lệ.";
        header("Location: edit_document_relation.php?id={$relation_id}&doc_id={$doc_id}");
        exit;
    }

    $stmt_up = $conn->prepare("UPDATE document_relations SET relation_type = ?, note = ? WHERE id = ?");
    $stmt_up->bind_param("ssi", $relation_type, $note, $relation_id);

    if ($stmt_up->execute()) {
        $stmt_up->close();

        // Ghi Log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $action = "Cập nhật liên kết văn bản (ID liên kết: {$relation_id})";
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }

        $_SESSION['flash_msg'] = "Cập nhật liên kết văn bản thành công!";
        header("Location: view_document.php?id=" . $doc_id);
        exit;
    } else {
        $_SESSION['flash_error'] = "Không thể cập nhật liên kết: " . $conn->error;
        header("Location: edit_document_relation.php?id={$relation_id}&doc_id={$doc_id}");
        exit;
    }
}

// 3. Lấy dữ liệu liên kết hiện tại (GET)
$stmt = $conn->prepare("
    SELECT dr.*, 
           d1.document_number AS doc1_number, d1.content AS doc1_content,
           d2.document_number AS doc2_number, d2.content AS doc2_content
    FROM document_relations dr
    LEFT JOIN documents d1 ON dr.document_id = d1.id
    LEFT JOIN documents d2 ON dr.related_document_id = d2.id
    WHERE dr.id = ?
");
$stmt->bind_param("i", $relation_id);
$stmt->execute();
$relation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$relation) {
    $_SESSION['flash_error'] = "Mối liên kết không tồn tại hoặc đã bị xóa.";
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$title = "Sửa liên kết văn bản";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-4" style="max-width: 800px;">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>CHỈNH SỬA LIÊN KẾT VĂN BẢN</h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if (isset($_SESSION['flash_error'])): ?>
                            <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                        <?php endif; ?>

                        <!-- Thông tin 2 văn bản đang liên kết -->
                        <div class="bg-light p-3 rounded border mb-4">
                            <div class="mb-2">
                                <span class="text-muted small">Văn bản nguồn:</span>
                                <div class="fw-bold text-primary"><?= e($relation['doc1_number']) ?> - <span class="text-dark fw-normal"><?= e($relation['doc1_content']) ?></span></div>
                            </div>
                            <hr class="my-2">
                            <div>
                                <span class="text-muted small">Văn bản đích (liên kết):</span>
                                <div class="fw-bold text-danger"><?= e($relation['doc2_number']) ?> - <span class="text-dark fw-normal"><?= e($relation['doc2_content']) ?></span></div>
                            </div>
                        </div>

                        <form action="edit_document_relation.php" method="POST">
                            <input type="hidden" name="relation_id" value="<?= (int)$relation['id'] ?>">
                            <input type="hidden" name="document_id" value="<?= (int)$doc_id ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Loại liên kết <span class="text-danger">*</span></label>
                                <select name="relation_type" class="form-select" required>
                                    <?php foreach ($allowed_types as $t): ?>
                                        <option value="<?= e($t) ?>" <?= ($relation['relation_type'] === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Ghi chú liên kết</label>
                                <input type="text" name="note" class="form-control" value="<?= e($relation['note'] ?? '') ?>" placeholder="VD: Dùng làm căn cứ pháp lý...">
                            </div>

                            <div class="text-end border-top pt-3">
                                <a href="view_document.php?id=<?= (int)$doc_id ?>" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i>Hủy bỏ</a>
                                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fas fa-save me-1"></i>Lưu thay đổi</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>