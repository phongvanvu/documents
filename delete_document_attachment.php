<?php
session_start();
include 'db.php';

// 1. Kiểm tra đăng nhập, quyền hạn & phương thức POST
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    $_SESSION['flash_error'] = "Bạn không có quyền xóa tài liệu kèm theo.";
    header("Location: dashboard.php");
    exit;
}

// 2. Nhận và xác thực dữ liệu đầu vào
$attachment_id = filter_input(INPUT_POST, 'attachment_id', FILTER_VALIDATE_INT);
$document_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);

if (!$attachment_id || !$document_id) {
    $_SESSION['flash_error'] = "Thiếu thông tin tài liệu cần xóa.";
    header("Location: " . ($document_id ? "view_document.php?id=" . $document_id : "dashboard.php"));
    exit;
}

// 3. Thực thi kiểm tra và xóa
try {
    // Lấy tên tệp và đường dẫn lưu trữ
    $stmt_select = $conn->prepare("SELECT attachment_title, attachment_path FROM document_attachments WHERE id = ? AND document_id = ?");
    $stmt_select->bind_param("ii", $attachment_id, $document_id);
    $stmt_select->execute();
    $attachment = $stmt_select->get_result()->fetch_assoc();
    $stmt_select->close();

    if (!$attachment) {
        $_SESSION['flash_error'] = "Không tìm thấy tài liệu kèm theo.";
        header("Location: view_document.php?id=" . $document_id);
        exit;
    }

    $attachment_title = $attachment['attachment_title'] ?? '';
    $attachment_path  = $attachment['attachment_path'] ?? '';

    // Xóa bản ghi trong CSDL
    $stmt_delete = $conn->prepare("DELETE FROM document_attachments WHERE id = ? AND document_id = ?");
    $stmt_delete->bind_param("ii", $attachment_id, $document_id);
    $stmt_delete->execute();

    if ($stmt_delete->affected_rows > 0) {
        // Xóa tệp vật lý trên máy chủ nếu đường dẫn hợp lệ
        if (!empty($attachment_path) && file_exists($attachment_path)) {
            $normalized_path = str_replace('\\', '/', $attachment_path);
            if (strpos($normalized_path, 'uploads/document_attachments/') === 0) {
                @unlink($attachment_path);
            }
        }

        // Ghi Log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $action  = "Xóa tài liệu kèm theo: '{$attachment_title}' (ID: {$attachment_id}) khỏi văn bản ID {$document_id}";
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }

        $_SESSION['flash_msg'] = "Đã xóa tài liệu kèm theo thành công!";
    } else {
        $_SESSION['flash_error'] = "Không thể xóa tài liệu kèm theo khỏi cơ sở dữ liệu.";
    }

    $stmt_delete->close();
} catch (mysqli_sql_exception $e) {
    $_SESSION['flash_error'] = "Lỗi hệ thống: Không thể xóa tài liệu kèm theo.";
    error_log("delete_document_attachment error: " . $e->getMessage());
}

header("Location: view_document.php?id=" . $document_id);
exit;