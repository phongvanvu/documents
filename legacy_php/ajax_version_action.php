<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên hoặc người có quyền sửa văn bản mới được thao tác
if (!isset($_SESSION['user_id']) || (!has_permission('documents.edit') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$version_id = intval($_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

// 1. KHÔI PHỤC LẠI PHIÊN BẢN CŨ (ROLLBACK)
if ($action === 'rollback_version' && $version_id > 0) {
    try {
        $conn->begin_transaction();

        // Lấy thông tin bản cũ
        $stmt_v = $conn->prepare("SELECT document_id, version_number, file_path, content_summary FROM document_versions WHERE id = ? LIMIT 1");
        $stmt_v->bind_param("i", $version_id);
        $stmt_v->execute();
        $ver = $stmt_v->get_result()->fetch_assoc();
        $stmt_v->close();

        if (!$ver) {
            throw new Exception("Không tìm thấy phiên bản yêu cầu!");
        }

        $doc_id = (int)$ver['document_id'];

        // Lấy thông tin hiện tại của văn bản để lưu dự phòng trước khi rollback
        $stmt_cur = $conn->prepare("SELECT document_number, file_path, content FROM documents WHERE id = ? LIMIT 1");
        $stmt_cur->bind_param("i", $doc_id);
        $stmt_cur->execute();
        $cur_doc = $stmt_cur->get_result()->fetch_assoc();
        $stmt_cur->close();

        if (!$cur_doc) {
            throw new Exception("Văn bản gốc không tồn tại hoặc đã bị xóa!");
        }

        // Lưu trạng thái hiện tại thành một bản snapshot trước khi rollback
        $new_vnum = 'Pre-Rollback-' . date('His');
        $note = "Lưu tự động trước khi đảo ngược về phiên bản " . $ver['version_number'];
        $stmt_snap = $conn->prepare("INSERT INTO document_versions (document_id, version_number, file_path, content_summary, change_log, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_snap->bind_param("issssi", $doc_id, $new_vnum, $cur_doc['file_path'], $cur_doc['content'], $note, $user_id);
        $stmt_snap->execute();
        $stmt_snap->close();

        // Ghi đè văn bản gốc bằng dữ liệu bản rollback
        $stmt_up = $conn->prepare("UPDATE documents SET file_path = ?, content = ? WHERE id = ?");
        $stmt_up->bind_param("ssi", $ver['file_path'], $ver['content_summary'], $doc_id);
        $stmt_up->execute();
        $stmt_up->close();

        // Ghi log hệ thống
        $log_act = "Đảo ngược văn bản số hiệu [{$cur_doc['document_number']}] về phiên bản [{$ver['version_number']}]";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Đã đảo ngược thành công văn bản về phiên bản {$ver['version_number']}!"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
    }
    exit;
}

// 2. XÓA BẢN GHI PHIÊN BẢN CŨ
if ($action === 'delete_version' && $version_id > 0) {
    $stmt_del = $conn->prepare("DELETE FROM document_versions WHERE id = ?");
    $stmt_del->bind_param("i", $version_id);
    if ($stmt_del->execute()) {
        $stmt_del->close();
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa bản ghi phiên bản thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
exit;