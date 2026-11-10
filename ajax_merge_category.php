<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ cho phép người dùng có quyền quản lý danh mục / Admin
if (!isset($_SESSION['user_id']) || (!has_permission('categories.manage') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$type = trim($_POST['type'] ?? ''); // 'authority' hoặc 'document_type'
$source_id = intval($_POST['source_id'] ?? 0); // Danh mục nguồn (cũ/trùng)
$target_id = intval($_POST['target_id'] ?? 0); // Danh mục đích (chuẩn giữ lại)

if ($source_id <= 0 || $target_id <= 0 || $source_id === $target_id) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn 2 danh mục khác nhau hợp lệ!']);
    exit;
}

try {
    $conn->begin_transaction();

    if ($type === 'authority') {
        // Lấy tên để ghi log
        $stmt_s = $conn->prepare("SELECT authority_name FROM issuing_authorities WHERE id = ?");
        $stmt_s->bind_param("i", $source_id); $stmt_s->execute();
        $source_name = $stmt_s->get_result()->fetch_assoc()['authority_name'] ?? "ID $source_id";
        $stmt_s->close();

        $stmt_t = $conn->prepare("SELECT authority_name FROM issuing_authorities WHERE id = ?");
        $stmt_t->bind_param("i", $target_id); $stmt_t->execute();
        $target_name = $stmt_t->get_result()->fetch_assoc()['authority_name'] ?? "ID $target_id";
        $stmt_t->close();

        // 1. Chuyển toàn bộ văn bản sang authority_id mới
        $stmt_up = $conn->prepare("UPDATE documents SET authority_id = ? WHERE authority_id = ?");
        $stmt_up->bind_param("ii", $target_id, $source_id);
        $stmt_up->execute();
        $affected = $stmt_up->affected_rows;
        $stmt_up->close();

        // 2. Xóa cơ quan ban hành nguồn
        $stmt_del = $conn->prepare("DELETE FROM issuing_authorities WHERE id = ?");
        $stmt_del->bind_param("i", $source_id);
        $stmt_del->execute();
        $stmt_del->close();

        $log_text = "Gộp Cơ quan BH: [$source_name] vào [$target_name], đã chuyển $affected văn bản.";

    } elseif ($type === 'document_type') {
        // Lấy tên để ghi log
        $stmt_s = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
        $stmt_s->bind_param("i", $source_id); $stmt_s->execute();
        $source_name = $stmt_s->get_result()->fetch_assoc()['type_name'] ?? "ID $source_id";
        $stmt_s->close();

        $stmt_t = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
        $stmt_t->bind_param("i", $target_id); $stmt_t->execute();
        $target_name = $stmt_t->get_result()->fetch_assoc()['type_name'] ?? "ID $target_id";
        $stmt_t->close();

        // 1. Chuyển toàn bộ văn bản sang document_type_id mới
        $stmt_up = $conn->prepare("UPDATE documents SET document_type_id = ? WHERE document_type_id = ?");
        $stmt_up->bind_param("ii", $target_id, $source_id);
        $stmt_up->execute();
        $affected = $stmt_up->affected_rows;
        $stmt_up->close();

        // 2. Xóa loại văn bản nguồn
        $stmt_del = $conn->prepare("DELETE FROM document_types WHERE id = ?");
        $stmt_del->bind_param("i", $source_id);
        $stmt_del->execute();
        $stmt_del->close();

        $log_text = "Gộp Loại VB: [$source_name] vào [$target_name], đã chuyển $affected văn bản.";

    } else {
        throw new Exception("Loại danh mục không hợp lệ!");
    }

    // Ghi log
    $user_id = (int)$_SESSION['user_id'];
    $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($l) {
        $l->bind_param("is", $user_id, $log_text);
        $l->execute();
        $l->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "Đã gộp thành công! Đã chuyển $affected văn bản sang danh mục chuẩn."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
}
exit;