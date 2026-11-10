<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ tài khoản có quyền documents.delete hoặc Admin mới được thao tác
if (!isset($_SESSION['user_id']) || (!has_permission('documents.delete') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$id = intval($_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

// Hàm hỗ trợ xóa file vật lý an toàn
function safeUnlink($path) {
    if (!empty($path) && file_exists($path) && is_file($path)) {
        @unlink($path);
    }
}

// 1. KHÔI PHỤC 1 VĂN BẢN
if ($action === 'restore_single' && $id > 0) {
    $stmt = $conn->prepare("UPDATE documents SET deleted_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();

        // Ghi log
        $log_act = "Khôi phục văn bản ID #$id từ thùng rác";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã khôi phục văn bản thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 2. KHÔI PHỤC TẤT CẢ VĂN BẢN
if ($action === 'restore_all') {
    $res = $conn->query("UPDATE documents SET deleted_at = NULL WHERE deleted_at IS NOT NULL");
    if ($res) {
        $affected = $conn->affected_rows;
        $log_act = "Khôi phục toàn bộ $affected văn bản từ thùng rác";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã khôi phục thành công $affected văn bản!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 3. XÓA VĨNH VIỄN 1 VĂN BẢN (XÓA CẢ TỆP VÀ QUAN HỆ LIÊN ĐỚI)
if ($action === 'force_delete_single' && $id > 0) {
    try {
        $conn->begin_transaction();

        // Lấy thông tin tệp chính
        $stmt_doc = $conn->prepare("SELECT document_number, file_path FROM documents WHERE id = ?");
        $stmt_doc->bind_param("i", $id);
        $stmt_doc->execute();
        $doc = $stmt_doc->get_result()->fetch_assoc();
        $stmt_doc->close();

        if ($doc) {
            // Xóa file chính
            safeUnlink($doc['file_path']);

            // Xóa file và bản ghi phụ lục (document_attachments)
            $res_att = $conn->query("SELECT attachment_path FROM document_attachments WHERE document_id = $id");
            if ($res_att) {
                while ($att = $res_att->fetch_assoc()) {
                    safeUnlink($att['attachment_path']);
                }
            }
            $conn->query("DELETE FROM document_attachments WHERE document_id = $id");

            // Xóa file và bản ghi lịch sử phiên bản (document_versions)
            $res_ver = $conn->query("SELECT file_path FROM document_versions WHERE document_id = $id");
            if ($res_ver) {
                while ($ver = $res_ver->fetch_assoc()) {
                    safeUnlink($ver['file_path']);
                }
            }
            $conn->query("DELETE FROM document_versions WHERE document_id = $id");

            // Xóa quan hệ liên kết
            $conn->query("DELETE FROM document_relations WHERE document_id = $id OR related_document_id = $id");

            // Xóa bản ghi trong documents
            $stmt_del = $conn->prepare("DELETE FROM documents WHERE id = ?");
            $stmt_del->bind_param("i", $id);
            $stmt_del->execute();
            $stmt_del->close();

            // Ghi log
            $log_act = "Xóa vĩnh viễn văn bản số hiệu: " . ($doc['document_number'] ?? "#$id");
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id, $log_act);
                $l->execute();
                $l->close();
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa vĩnh viễn văn bản và toàn bộ tệp đính kèm!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi xóa dữ liệu: ' . $e->getMessage()]);
    }
    exit;
}

// 4. DỌN SẠCH THÙNG RÁC (EMPTY TRASH)
if ($action === 'empty_trash') {
    try {
        $conn->begin_transaction();

        $res_docs = $conn->query("SELECT id, file_path FROM documents WHERE deleted_at IS NOT NULL");
        $deleted_count = 0;

        if ($res_docs) {
            while ($row = $res_docs->fetch_assoc()) {
                $doc_id = (int)$row['id'];
                safeUnlink($row['file_path']);

                // Xóa phụ lục
                $res_att = $conn->query("SELECT attachment_path FROM document_attachments WHERE document_id = $doc_id");
                if ($res_att) {
                    while ($att = $res_att->fetch_assoc()) {
                        safeUnlink($att['attachment_path']);
                    }
                }
                $conn->query("DELETE FROM document_attachments WHERE document_id = $doc_id");

                // Xóa phiên bản
                $res_ver = $conn->query("SELECT file_path FROM document_versions WHERE document_id = $doc_id");
                if ($res_ver) {
                    while ($ver = $res_ver->fetch_assoc()) {
                        safeUnlink($ver['file_path']);
                    }
                }
                $conn->query("DELETE FROM document_versions WHERE document_id = $doc_id");

                // Xóa liên kết
                $conn->query("DELETE FROM document_relations WHERE document_id = $doc_id OR related_document_id = $doc_id");

                $deleted_count++;
            }
        }

        $conn->query("DELETE FROM documents WHERE deleted_at IS NOT NULL");

        // Ghi log
        $log_act = "Dọn sạch thùng rác: Đã xóa vĩnh viễn $deleted_count văn bản";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Đã dọn sạch thùng rác ($deleted_count văn bản đã bị xóa vĩnh viễn)!"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi dọn thùng rác: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;