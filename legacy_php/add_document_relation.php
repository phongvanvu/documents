<?php
session_start();
include 'db.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// 2. Nhận và xác thực dữ liệu
$doc_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$rel_doc_id = filter_input(INPUT_POST, 'related_document_id', FILTER_VALIDATE_INT);
$type = trim($_POST['relation_type'] ?? '');
$note = trim($_POST['note'] ?? '');

$allowed_types = ['Liên quan', 'Căn cứ pháp lý', 'Sửa đổi', 'Bổ sung', 'Thay thế', 'Bị thay thế', 'Hướng dẫn', 'Được hướng dẫn bởi', 'Liên quan nghiệp vụ'];

if (!$doc_id || !$rel_doc_id) {
    $_SESSION['flash_error'] = "Thiếu thông tin văn bản cần liên kết.";
    header("Location: " . ($doc_id ? "view_document.php?id=" . $doc_id : "dashboard.php"));
    exit;
}

if ($doc_id === $rel_doc_id) {
    $_SESSION['flash_error'] = "Không thể liên kết văn bản với chính nó.";
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}

if (!in_array($type, $allowed_types, true)) {
    $_SESSION['flash_error'] = "Loại liên kết không hợp lệ.";
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}

// 3. Kiểm tra xem cả 2 văn bản có tồn tại không
$stmt_chk = $conn->prepare("SELECT id FROM documents WHERE id IN (?, ?)");
$stmt_chk->bind_param("ii", $doc_id, $rel_doc_id);
$stmt_chk->execute();
if ($stmt_chk->get_result()->num_rows < 2) {
    $_SESSION['flash_error'] = "Một trong hai văn bản không tồn tại.";
    $stmt_chk->close();
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}
$stmt_chk->close();

// 4. Kiểm tra liên kết đã tồn tại hay chưa
$stmt_exists = $conn->prepare("SELECT id FROM document_relations WHERE (document_id = ? AND related_document_id = ?) OR (document_id = ? AND related_document_id = ?)");
$stmt_exists->bind_param("iiii", $doc_id, $rel_doc_id, $rel_doc_id, $doc_id);
$stmt_exists->execute();
if ($stmt_exists->get_result()->num_rows > 0) {
    $_SESSION['flash_error'] = "Liên kết giữa hai văn bản này đã tồn tại từ trước.";
    $stmt_exists->close();
    header("Location: view_document.php?id=" . $doc_id);
    exit;
}
$stmt_exists->close();

// 5. Thêm mối liên kết mới
$stmt_ins = $conn->prepare("INSERT INTO document_relations (document_id, related_document_id, relation_type, note) VALUES (?, ?, ?, ?)");
$stmt_ins->bind_param("iiss", $doc_id, $rel_doc_id, $type, $note);

if ($stmt_ins->execute()) {
    $stmt_ins->close();

    // Ghi Log hệ thống
    $user_id = (int)$_SESSION['user_id'];
    $action = "Thêm liên kết văn bản ID {$doc_id} -> ID {$rel_doc_id} ({$type})";
    $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        $log_stmt->close();
    }

    $_SESSION['flash_msg'] = "Đã thêm văn bản liên quan thành công!";
} else {
    $_SESSION['flash_error'] = "Không thể lưu liên kết văn bản: " . $conn->error;
}

header("Location: view_document.php?id=" . $doc_id);
exit;