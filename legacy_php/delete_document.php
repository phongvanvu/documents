<?php
session_start();
include 'db.php';

include_once 'auth.php';
require_permission('documents.delete');

// 1. Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    $_SESSION['flash_error'] = "Bạn không có quyền thực hiện thao tác xóa.";
    header('Location: documents.php');
    exit;
}

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$document_id) {
    $_SESSION['flash_error'] = "ID văn bản không hợp lệ.";
    header('Location: documents.php');
    exit;
}

// 2. Lấy thông tin văn bản để ghi Log
$stmt = $conn->prepare("SELECT document_number FROM documents WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $document_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) {
    $_SESSION['flash_error'] = "Văn bản không tồn tại hoặc đã nằm trong thùng rác.";
    header('Location: documents.php');
    exit;
}

$doc_num = $document['document_number'] ?? '';

// 3. Tiến hành XÓA MỀM (Cập nhật cột deleted_at)
// Không xóa file vật lý, không xóa liên kết phụ lục lúc này
$stmt_del = $conn->prepare("UPDATE documents SET deleted_at = NOW() WHERE id = ?");
$stmt_del->bind_param("i", $document_id);

if ($stmt_del->execute()) {
    $stmt_del->close();

    // 4. Ghi Log hành động
    $user_id = (int)$_SESSION['user_id'];
    $action = "Chuyển vào thùng rác VB số: " . $doc_num;
    
    // Sử dụng try-catch đề phòng bảng logs chưa được tạo chuẩn
    try {
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {}

    $_SESSION['flash_msg'] = "Đã chuyển văn bản " . $doc_num . " vào thùng rác!";
} else {
    $_SESSION['flash_error'] = "Có lỗi xảy ra khi xóa văn bản.";
}

header('Location: documents.php');
exit;