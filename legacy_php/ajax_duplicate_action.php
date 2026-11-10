<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ tài khoản có quyền documents.edit / documents.delete hoặc Admin mới được thao tác
if (!isset($_SESSION['user_id']) || (!has_permission('documents.edit') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)$_SESSION['user_id'];

// 1. GỘP 2 VĂN BẢN TRÙNG (GIỮ PRIMARY, CHUYỂN TOÀN BỘ QUAN HỆ TỪ SECONDARY)
if ($action === 'merge_duplicates') {
    $primary_id = intval($_POST['primary_id'] ?? 0);
    $duplicate_id = intval($_POST['duplicate_id'] ?? 0);

    if ($primary_id <= 0 || $duplicate_id <= 0 || $primary_id === $duplicate_id) {
        echo json_encode(['status' => 'error', 'message' => 'Lựa chọn hai văn bản gộp không hợp lệ!']);
        exit;
    }

    try {
        $conn->begin_transaction();

        // Kiểm tra tồn tại
        $stmt_p = $conn->prepare("SELECT document_number, file_path FROM documents WHERE id = ?");
        $stmt_p->bind_param("i", $primary_id);
        $stmt_p->execute();
        $primary_doc = $stmt_p->get_result()->fetch_assoc();
        $stmt_p->close();

        $stmt_d = $conn->prepare("SELECT document_number, file_path FROM documents WHERE id = ?");
        $stmt_d->bind_param("i", $duplicate_id);
        $stmt_d->execute();
        $dup_doc = $stmt_d->get_result()->fetch_assoc();
        $stmt_d->close();

        if (!$primary_doc || !$dup_doc) {
            throw new Exception("Không tìm thấy một trong hai văn bản cần gộp.");
        }

        // Chuyển phụ lục từ văn bản trùng sang văn bản chính
        $conn->query("UPDATE document_attachments SET document_id = $primary_id WHERE document_id = $duplicate_id");

        // Chuyển lịch sử phiên bản
        $conn->query("UPDATE document_versions SET document_id = $primary_id WHERE document_id = $duplicate_id");

        // Cập nhật lại các liên kết hiệu lực
        $conn->query("UPDATE document_relations SET document_id = $primary_id WHERE document_id = $duplicate_id AND related_document_id != $primary_id");
        $conn->query("UPDATE document_relations SET related_document_id = $primary_id WHERE related_document_id = $duplicate_id AND document_id != $primary_id");
        $conn->query("DELETE FROM document_relations WHERE document_id = $primary_id AND related_document_id = $primary_id");

        // Nếu bản chính chưa có file đính kèm mà bản trùng có thì chuyển qua
        if (empty($primary_doc['file_path']) && !empty($dup_doc['file_path'])) {
            $stmt_mv = $conn->prepare("UPDATE documents SET file_path = ? WHERE id = ?");
            $stmt_mv->bind_param("si", $dup_doc['file_path'], $primary_id);
            $stmt_mv->execute();
            $stmt_mv->close();
        } else {
            // Nếu file bản trùng thừa thì dọn tệp vật lý
            if (!empty($dup_doc['file_path']) && $dup_doc['file_path'] !== $primary_doc['file_path'] && file_exists($dup_doc['file_path'])) {
                @unlink($dup_doc['file_path']);
            }
        }

        // Xóa hẳn bản ghi trùng
        $conn->query("DELETE FROM documents WHERE id = $duplicate_id");

        // Ghi log
        $log_act = "Gộp văn bản trùng ID #$duplicate_id ({$dup_doc['document_number']}) vào văn bản chuẩn ID #$primary_id ({$primary_doc['document_number']})";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Đã gộp văn bản thành công và đồng bộ toàn bộ liên kết!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
    }
    exit;
}

// 2. CHUYỂN BẢN TRÙNG VÀO THÙNG RÁC
if ($action === 'soft_delete_duplicate') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE documents SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            $log_act = "Chuyển văn bản trùng lặp ID #$id vào thùng rác";
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id, $log_act);
                $l->execute();
                $l->close();
            }
            echo json_encode(['status' => 'success', 'message' => 'Đã đưa văn bản trùng vào thùng rác!']);
            exit;
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
exit;