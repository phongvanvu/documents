<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền
if (!isset($_SESSION['user_id']) || (!has_permission('documents.edit') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)$_SESSION['user_id'];

// 1. THÊM MỐI QUAN HỆ MỚI
if ($action === 'add_relation') {
    $doc_id = intval($_POST['document_id'] ?? 0);
    $related_id = intval($_POST['related_document_id'] ?? 0);
    $relation_type = trim($_POST['relation_type'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($doc_id <= 0 || $related_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn đầy đủ 2 văn bản!']);
        exit;
    }

    if ($doc_id === $related_id) {
        echo json_encode(['status' => 'error', 'message' => 'Một văn bản không thể thiết lập quan hệ với chính nó!']);
        exit;
    }

    $valid_types = ['Thay thế', 'Sửa đổi, bổ sung', 'Hướng dẫn', 'Căn cứ', 'Bãi bỏ'];
    if (!in_array($relation_type, $valid_types, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Loại quan hệ không hợp lệ!']);
        exit;
    }

    // Kiểm tra xem quan hệ đã tồn tại chưa
    $chk = $conn->prepare("SELECT id FROM document_relations WHERE document_id = ? AND related_document_id = ? AND relation_type = ? LIMIT 1");
    $chk->bind_param("iis", $doc_id, $related_id, $relation_type);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        echo json_encode(['status' => 'error', 'message' => 'Mối quan hệ này giữa 2 văn bản đã được tạo trước đó!']);
        exit;
    }
    $chk->close();

    $stmt = $conn->prepare("INSERT INTO document_relations (document_id, related_document_id, relation_type, note) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $doc_id, $related_id, $relation_type, $note);
    if ($stmt->execute()) {
        $stmt->close();

        // Ghi nhật ký
        $log_act = "Thiết lập liên kết văn bản ID #$doc_id [$relation_type] văn bản ID #$related_id";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã thiết lập mối quan hệ văn bản thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 2. XÓA MỐI QUAN HỆ
if ($action === 'delete_relation') {
    $relation_id = intval($_POST['id'] ?? 0);
    if ($relation_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã liên kết không hợp lệ!']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM document_relations WHERE id = ?");
    $stmt->bind_param("i", $relation_id);
    if ($stmt->execute()) {
        $stmt->close();

        $log_act = "Xóa mối liên kết văn bản ID #$relation_id";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã xóa mối liên kết văn bản thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
exit;