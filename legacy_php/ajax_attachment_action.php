<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền chỉnh sửa/xóa tài liệu
if (!isset($_SESSION['user_id']) || (!has_permission('documents.edit') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$attachment_id = intval($_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($action === 'delete_attachment' && $attachment_id > 0) {
    try {
        // Lấy thông tin tệp
        $stmt_info = $conn->prepare("SELECT a.attachment_path, d.document_number 
                                     FROM document_attachments a 
                                     LEFT JOIN documents d ON a.document_id = d.id 
                                     WHERE a.id = ? LIMIT 1");
        $stmt_info->bind_param("i", $attachment_id);
        $stmt_info->execute();
        $att = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        if (!$att) {
            echo json_encode(['status' => 'error', 'message' => 'Tệp phụ lục không tồn tại trong hệ thống!']);
            exit;
        }

        // Xóa file vật lý trên máy chủ
        $file_path = $att['attachment_path'];
        if (!empty($file_path) && file_exists($file_path) && is_file($file_path)) {
            @unlink($file_path);
        }

        // Xóa bản ghi trong CSDL
        $stmt_del = $conn->prepare("DELETE FROM document_attachments WHERE id = ?");
        $stmt_del->bind_param("i", $attachment_id);
        $stmt_del->execute();
        $stmt_del->close();

        // Ghi nhật ký hệ thống
        $file_title = basename($file_path);
        $doc_num = $att['document_number'] ?: "Văn bản";
        $log_act = "Xóa tệp phụ lục [$file_title] của văn bản số hiệu [$doc_num]";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã xóa tệp phụ lục và giải phóng dung lượng máy chủ thành công!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
exit;