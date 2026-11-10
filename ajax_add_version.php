<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Không có quyền thực hiện!']);
    exit;
}

$document_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$version_num = trim($_POST['version_number'] ?? '');
$change_summary = trim($_POST['change_summary'] ?? '');
$user_id = (int)$_SESSION['user_id'];

if (!$document_id || empty($version_num) || !isset($_FILES['version_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đủ thông tin và chọn tệp!']);
    exit;
}

$file = $_FILES['version_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi tải tệp lên!']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Định dạng tệp không được hỗ trợ!']);
    exit;
}

$dir = 'uploads/versions/' . date('Y/m') . '/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo json_encode(['status' => 'error', 'message' => 'Không thể tạo thư mục lưu trữ phiên bản!']);
        exit;
    }
}

$safe_name = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file['name']);
$file_path = $dir . $safe_name;
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Không thể lưu tệp vào máy chủ!']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO document_versions (document_id, version_number, file_path, change_summary, uploaded_by) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isssi", $document_id, $version_num, $file_path, $change_summary, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    
    // Ghi log
    $log_act = "Tải lên phiên bản $version_num cho VB ID: $document_id";
    try {
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        $l->bind_param("is", $user_id, $log_act);
        $l->execute();
        $l->close();
    } catch (Exception $e) {}

    echo json_encode(['status' => 'success', 'message' => 'Đã lưu phiên bản mới thành công!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $stmt->error]);
}
$conn->close();