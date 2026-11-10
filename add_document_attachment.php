<?php
session_start();
include 'db.php';

// 1. Kiểm tra quyền truy cập & phương thức request
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    $_SESSION['flash_error'] = "Bạn không có quyền thêm tài liệu kèm theo.";
    header("Location: dashboard.php");
    exit;
}

// Hàm hỗ trợ lưu tệp lên máy chủ
function upload_attachment_file($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'txt', 'zip', 'rar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true) || (int)$file['size'] <= 0 || (int)$file['size'] > 120000000) {
        return false;
    }

    $upload_dir = 'uploads/document_attachments/' . date('Y/m') . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            return false;
        }
    }

    $safe_name = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file['name']);
    $target_path = $upload_dir . $safe_name;

    return move_uploaded_file($file['tmp_name'], $target_path) ? $target_path : false;
}

// 2. Nhận và xác thực dữ liệu
$doc_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$title = trim($_POST['attachment_title'] ?? '');
$type = trim($_POST['attachment_type'] ?? '');
$note = trim($_POST['note'] ?? '');
$user_id = (int)$_SESSION['user_id'];

$allowed_types = ['Ghi chú', 'Biểu mẫu', 'Excel tính toán', 'Công văn tham khảo', 'Tài liệu tham khảo', 'Bản trích điều khoản', 'Ảnh / scan', 'Khác'];

if (!$doc_id) {
    $_SESSION['flash_error'] = "ID văn bản không hợp lệ.";
    header("Location: dashboard.php");
    exit;
}

if (empty($title)) {
    $_SESSION['flash_error'] = "Vui lòng nhập tên tài liệu kèm theo.";
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}

if (!in_array($type, $allowed_types, true)) {
    $type = 'Khác';
}

if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = "Vui lòng chọn tệp tài liệu đính kèm.";
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}

// 3. Thực thi xử lý lưu tệp và cập nhật CSDL
try {
    // Kiểm tra văn bản tồn tại
    $stmt_check = $conn->prepare("SELECT id FROM documents WHERE id = ?");
    $stmt_check->bind_param("i", $doc_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        $stmt_check->close();
        $_SESSION['flash_error'] = "Văn bản không tồn tại.";
        header("Location: dashboard.php");
        exit;
    }
    $stmt_check->close();

    // Tải tệp lên máy chủ
    $file_path = upload_attachment_file($_FILES['attachment_file']);
    if (!$file_path) {
        $_SESSION['flash_error'] = "Tải tệp không thành công. Vui lòng kiểm tra định dạng hoặc dung lượng tệp (tối đa 120MB).";
        header("Location: view_document.php?id=" . $doc_id);
        exit;
    }

    $orig_name = basename($_FILES['attachment_file']['name']);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    $size = (int)$_FILES['attachment_file']['size'];

    // Lưu thông tin vào CSDL
    $sql = "INSERT INTO document_attachments (document_id, attachment_title, attachment_type, attachment_path, original_name, file_ext, file_size, note, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssisi", $doc_id, $title, $type, $file_path, $orig_name, $ext, $size, $note, $user_id);
    $stmt->execute();
    $stmt->close();

    // Ghi Log hệ thống
    $action = "Thêm tài liệu làm việc đính kèm: '{$title}' cho văn bản ID {$doc_id}";
    $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        $log_stmt->close();
    }

    $_SESSION['flash_msg'] = "Đã thêm tài liệu kèm theo thành công!";
} catch (mysqli_sql_exception $e) {
    $_SESSION['flash_error'] = "Lỗi hệ thống: Không thể thêm tài liệu đính kèm.";
    error_log("add_document_attachment error: " . $e->getMessage());
}

header("Location: view_document.php?id=" . $doc_id);
exit;