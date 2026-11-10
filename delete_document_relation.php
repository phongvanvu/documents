<?php
session_start();
include 'db.php';

// 1. Kiểm tra đăng nhập & phương thức gửi request
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// 2. Nhận và xác thực dữ liệu
$relation_id = filter_input(INPUT_POST, 'relation_id', FILTER_VALIDATE_INT);
$document_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);

if (!$relation_id || !$document_id) {
    $_SESSION['flash_error'] = "Thiếu thông tin liên kết cần xóa.";
    header("Location: " . ($document_id ? "view_document.php?id=" . $document_id : "dashboard.php"));
    exit;
}

// 3. Thực thi xóa liên kết văn bản
try {
    $sql = "DELETE FROM document_relations WHERE id = ? AND (document_id = ? OR related_document_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $relation_id, $document_id, $document_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash_msg'] = "Đã xóa liên kết văn bản thành công!";

        // Ghi Log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $action = "Xóa liên kết văn bản (ID liên kết: {$relation_id}) khỏi văn bản ID: {$document_id}";
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } else {
        $_SESSION['flash_error'] = "Không tìm thấy liên kết cần xóa hoặc liên kết không thuộc văn bản này.";
    }

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $_SESSION['flash_error'] = "Lỗi hệ thống: Không thể xóa liên kết văn bản.";
    error_log("delete_document_relation error: " . $e->getMessage());
}

header("Location: view_document.php?id=" . $document_id);
exit;