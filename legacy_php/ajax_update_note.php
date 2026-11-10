<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập đã hết hạn!']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$note = trim($_POST['personal_note'] ?? '');

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID văn bản không hợp lệ!']);
    exit;
}

// Cập nhật trường personal_note trong CSDL
$stmt = $conn->prepare("UPDATE documents SET personal_note = ? WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("si", $note, $id);

if ($stmt->execute()) {
    $stmt->close();
    
    // Ghi log hoạt động
    $user_id = (int)$_SESSION['user_id'];
    $act_text = "Cập nhật ghi chú cho văn bản ID: " . $id;
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
        'message' => 'Cập nhật ghi chú thành công!',
        'note' => $note
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật CSDL: ' . $stmt->error]);
}

$conn->close();