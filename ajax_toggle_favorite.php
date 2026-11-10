<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID không hợp lệ!']);
    exit;
}

// 1. Lấy trạng thái hiện tại
$stmt = $conn->prepare("SELECT is_favorite, document_number FROM documents WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(['status' => 'error', 'message' => 'Văn bản không tồn tại hoặc đã bị xóa!']);
    exit;
}

// 2. Đảo ngược trạng thái (0 -> 1 hoặc 1 -> 0)
$new_status = ((int)($res['is_favorite'] ?? 0) === 1) ? 0 : 1;

$stmt_up = $conn->prepare("UPDATE documents SET is_favorite = ? WHERE id = ?");
$stmt_up->bind_param("ii", $new_status, $id);

if ($stmt_up->execute()) {
    $stmt_up->close();
    
    // Ghi log hoạt động
    $user_id = (int)$_SESSION['user_id'];
    $act_text = $new_status ? "Đánh dấu sao quan trọng VB: " : "Bỏ đánh dấu sao VB: ";
    $act_text .= ($res['document_number'] ?? '');
    
    try {
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $act_text);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {}

    echo json_encode([
        'status' => 'success',
        'is_favorite' => $new_status,
        'message' => $new_status ? 'Đã thêm vào mục quan trọng' : 'Đã bỏ khỏi mục quan trọng'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật cơ sở dữ liệu!']);
}
$conn->close();