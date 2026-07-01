<?php
session_start();

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'editor')) {
    header("Location: index.php");
    exit();
}

include 'db.php';

/* ================== HÀM UPLOAD FILE (GIỮ THEO MÃ CŨ) ================== */
function upload_file($file) {
    $allowed_exts = ['pdf', 'doc', 'docx'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_exts) || $file['size'] > 120000000) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $file_path = $upload_dir . $safe_name;

    return move_uploaded_file($file['tmp_name'], $file_path) ? $file_path : false;
}

/* ================== BIẾN ================== */
$error = '';
$success = '';

$document_number = '';
$document_type_id = '';
$authority_id = '';
$issued_date = '';
$effective_date = '';
$content = '';
$file_name = '';

/* ================== XỬ LÝ POST ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $document_number  = trim($_POST['document_number']);
    $document_type_id = intval($_POST['document_type_id']);
    $authority_id     = intval($_POST['authority_id']);
    $issued_date      = $_POST['issued_date'];

    // FIX DATE
    $effective_date = !empty($_POST['effective_date']) ? $_POST['effective_date'] : null;

    $status  = $_POST['status'];
    $content = trim($_POST['content']);

    if ($document_number === '' || $document_type_id <= 0 || $authority_id <= 0 || $issued_date === '' || $content === '') {
        $error = "Vui lòng điền đầy đủ thông tin bắt buộc.";
    } else {

        // Kiểm tra trùng số hiệu
        $stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE document_number = ?");
        $stmt->bind_param("s", $document_number);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Số hiệu văn bản đã tồn tại.";
        } else {

            // Upload file
            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
                $file_name = upload_file($_FILES['document_file']);
                if (!$file_name) {
                    $error = "Tải lên tệp không thành công.";
                }
            } else {
                $error = "Vui lòng chọn tệp văn bản.";
            }

            if (!$error) {

                $stmt = $conn->prepare("
                    INSERT INTO documents
                    (document_number, document_type_id, authority_id,
                     issued_date, effective_date, status, content, file_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "siisssss",
                    $document_number,
                    $document_type_id,
                    $authority_id,
                    $issued_date,
                    $effective_date,
                    $status,
                    $content,
                    $file_name
                );

                if ($stmt->execute()) {
                    echo "<script>alert('Thêm văn bản thành công!'); window.location.href='index.php';</script>";
                    exit;
                } else {
                    $error = "Có lỗi xảy ra khi lưu dữ liệu.";
                }

                $stmt->close();
            }
        }
    }
}

/* ================== LẤY LOẠI VĂN BẢN ================== */
$result_types = $conn->query("SELECT id, type_name FROM document_types");

$title = "Thêm văn bản mới";
include 'header.php';
?>

<!-- SIDEBAR -->
<div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
    <?php include 'sidebar.php'; ?>
</div>

<!-- NỘI DUNG -->
<div id="content" class="col py-3">
    <div class="col-md-10 offset-md-1">

        <h3 class="text-center mb-4">THÔNG TIN VĂN BẢN</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form action="add_document.php" method="POST" enctype="multipart/form-data">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Số hiệu văn bản</label>
                    <input type="text" class="form-control" name="document_number"
                           value="<?= htmlspecialchars($document_number) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày ban hành</label>
                    <input type="date" class="form-control" name="issued_date"
                           value="<?= htmlspecialchars($issued_date) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Loại văn bản</label>
                <select class="form-control" name="document_type_id" required>
                    <option value="">Chọn loại văn bản</option>
                    <?php while ($row = $result_types->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= ($row['id'] == $document_type_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['type_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Nơi ban hành</label>
                <select class="form-control" name="authority_id" required>
                    <option value="">Chọn nơi ban hành</option>
                    <?= get_options('issuing_authorities', 'id', 'authority_name', $authority_id); ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Ngày có hiệu lực</label>
                <input type="date" class="form-control" name="effective_date">
            </div>

            <div class="mb-3">
                <label class="form-label">Tình trạng hiệu lực</label>
                <select class="form-control" name="status">
                    <option value="Còn hiệu lực">Còn hiệu lực</option>
                    <option value="Hết hiệu lực">Hết hiệu lực</option>
                    <option value="Không xác định">Không xác định</option>
                    <option value="Chưa biết" selected>Chưa biết</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Nội dung văn bản</label>
                <textarea class="form-control" name="content" rows="5" required><?= htmlspecialchars($content) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Tệp văn bản (PDF, DOC, DOCX)</label>
                <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx" required>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save me-1"></i> Ghi lại
                </button>
            </div>

        </form>
    </div>
</div>

<?php
include 'footer.php';

/* ================== HÀM GET OPTIONS (GIỮ NGUYÊN) ================== */
function get_options($table, $value_field, $display_field, $selected_value) {
    global $conn;
    $result = $conn->query("SELECT $value_field, $display_field FROM $table ORDER BY $display_field");
    $options = '';
    while ($row = $result->fetch_assoc()) {
        $selected = ($row[$value_field] == $selected_value) ? 'selected' : '';
        $options .= "<option value='{$row[$value_field]}' $selected>"
                  . htmlspecialchars($row[$display_field])
                  . "</option>";
    }
    return $options;
}
?>
