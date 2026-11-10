<?php
session_start();
include 'db.php';

// 1. Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    die("Bạn không có quyền thực hiện thao tác này.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: documents.php");
    exit;
}

// 2. Nhận và làm sạch dữ liệu
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$doc_num = trim($_POST['document_number'] ?? '');
$doc_type_id = intval($_POST['document_type_id'] ?? 0);
$auth_id = intval($_POST['authority_id'] ?? 0);
$issued_date = trim($_POST['issued_date'] ?? '');
$effective_date = !empty($_POST['effective_date']) ? trim($_POST['effective_date']) : null;
$expired_date = !empty($_POST['expired_date']) ? trim($_POST['expired_date']) : null; // Lấy thêm ngày hết hạn
$status = trim($_POST['status'] ?? 'Không xác định');

// Tự động ép trạng thái thành 'Hết hiệu lực' nếu ngày hết hạn < ngày hiện tại
if (!empty($expired_date) && strtotime($expired_date) < strtotime(date('Y-m-d'))) {
    $status = 'Hết hiệu lực';
}

$content = trim($_POST['content'] ?? '');
$is_secret_chk = isset($_POST['is_secret_checkbox']);

if (!$id || empty($doc_num) || $doc_type_id <= 0 || $auth_id <= 0 || empty($issued_date) || empty($content)) {
    $_SESSION['flash_error'] = "Vui lòng điền đầy đủ các thông tin bắt buộc.";
    header("Location: edit_document.php?id=" . ($id ?: ''));
    exit;
}

// Kiểm tra trùng số hiệu văn bản (trừ văn bản hiện tại)
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM documents WHERE document_number = ? AND id != ? AND deleted_at IS NULL");
$stmt_check->bind_param("si", $doc_num, $id);
$stmt_check->execute();
$stmt_check->bind_result($count);
$stmt_check->fetch();
$stmt_check->close();

if ($count > 0) {
    $_SESSION['flash_error'] = "Số hiệu văn bản này đã tồn tại ở một bản ghi khác.";
    header("Location: edit_document.php?id=" . $id);
    exit;
}

// 3. Nhận diện Văn bản Mật
$doc_upper = mb_strtoupper($doc_num, 'UTF-8');
$is_secret = ($is_secret_chk || strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);

// Lấy thông tin cũ (file_path + content) để lưu phiên bản trước khi cập nhật
$stmt_old = $conn->prepare("SELECT file_path, content FROM documents WHERE id = ?");
$stmt_old->bind_param("i", $id);
$stmt_old->execute();
$old_data = $stmt_old->get_result()->fetch_assoc();
$old_file = $old_data['file_path'] ?? '';
$old_content = $old_data['content'] ?? '';
$stmt_old->close();

$new_file_path = $old_file;
$delete_old_file = false;

// 4. Xử lý logic đính kèm tệp
if ($is_secret) {
    // Văn bản Mật -> Xóa tệp cũ và không lưu đường dẫn
    $new_file_path = '';
    $delete_old_file = true;
} elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
    // Văn bản thường có tải tệp mới lên
    $file = $_FILES['document_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Đã thêm định dạng zip, rar
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip', 'rar'];

    if (!in_array($ext, $allowed, true) || $file['size'] > 120000000) {
        $_SESSION['flash_error'] = "Tệp đính kèm không đúng định dạng hoặc vượt quá 120MB.";
        header("Location: edit_document.php?id=" . $id);
        exit;
    }

    $upload_dir = 'uploads/documents/' . date('Y/m') . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            $_SESSION['flash_error'] = "Không thể tạo thư mục lưu trữ tệp trên máy chủ.";
            header("Location: edit_document.php?id=" . $id);
            exit;
        }
    }

    $safe_name = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file['name']);
    $target_path = $upload_dir . $safe_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $new_file_path = $target_path;
        $delete_old_file = true;
    } else {
        $_SESSION['flash_error'] = "Không thể lưu tệp vào máy chủ.";
        header("Location: edit_document.php?id=" . $id);
        exit;
    }
}

// 5. Cập nhật Cơ sở dữ liệu (Có thêm expired_date)
$sql = "UPDATE documents SET document_number = ?, document_type_id = ?, authority_id = ?, issued_date = ?, effective_date = ?, expired_date = ?, status = ?, content = ?, file_path = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("siissssssi", $doc_num, $doc_type_id, $auth_id, $issued_date, $effective_date, $expired_date, $status, $content, $new_file_path, $id);

if ($stmt->execute()) {
    $stmt->close();
    
    // ==========================================
    // TỰ ĐỘNG LƯU PHIÊN BẢN (VERSION SNAPSHOT)
    // Lưu trạng thái CŨ trước khi ghi đè
    // ==========================================
    try {
        // Chỉ lưu phiên bản nếu nội dung hoặc file thực sự thay đổi
        if ($old_content !== $content || $old_file !== $new_file_path) {
            // Tìm version_number tiếp theo cho văn bản này
            $stmt_max = $conn->prepare("SELECT MAX(CAST(version_number AS UNSIGNED)) AS max_ver FROM document_versions WHERE document_id = ?");
            $stmt_max->bind_param("i", $id);
            $stmt_max->execute();
            $max_ver = (int)($stmt_max->get_result()->fetch_assoc()['max_ver'] ?? 0);
            $stmt_max->close();
            $next_ver = (string)($max_ver + 1);

            // Ghi chú thay đổi
            $change_parts = [];
            if ($old_content !== $content) $change_parts[] = "Thay đổi nội dung trích yếu";
            if ($old_file !== $new_file_path) $change_parts[] = "Thay đổi tệp đính kèm";
            $change_parts[] = "Cập nhật bởi " . ($_SESSION['user_name'] ?? 'Người dùng');
            $change_note = implode('; ', $change_parts);

            // Lưu snapshot trạng thái CŨ (dùng $old_content và $old_file đã lấy TRƯỚC khi UPDATE)
            $user_id_ver = (int)$_SESSION['user_id'];
            $stmt_ver = $conn->prepare("INSERT INTO document_versions (document_id, version_number, file_path, content_summary, change_log, created_by, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_ver->bind_param("issssii", $id, $next_ver, $old_file, $old_content, $change_note, $user_id_ver, $user_id_ver);
            $stmt_ver->execute();
            $stmt_ver->close();
        }
    } catch (Exception $e) {
        // Không để lỗi lưu phiên bản làm gián đoạn luồng chính
        error_log("Lỗi lưu phiên bản tự động: " . $e->getMessage());
    }

    // Xóa tệp vật lý cũ nếu có yêu cầu
    if ($delete_old_file && !empty($old_file) && file_exists($old_file)) {
        @unlink($old_file);
    }

    // ==========================================
    // BỔ SUNG: GHI LOG HÀNH ĐỘNG CẬP NHẬT
    // ==========================================
    $log_user_id = (int)$_SESSION['user_id'];
    $log_action = "Cập nhật văn bản số hiệu: " . $doc_num;
    try {
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $log_user_id, $log_action);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) { }
    // ==========================================

    $_SESSION['flash_msg'] = "Cập nhật thông tin văn bản thành công!";
    header("Location: view_document.php?id=" . $id);
    exit;
} else {
    $_SESSION['flash_error'] = "Lỗi hệ thống: " . $stmt->error;
    header("Location: edit_document.php?id=" . $id);
    exit;
}