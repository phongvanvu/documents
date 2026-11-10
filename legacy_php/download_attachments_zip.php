<?php
session_start();
include 'db.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'], $_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    } else {
        header('Location: login.php');
        exit;
    }
}

$document_id = filter_input(INPUT_GET, 'document_id', FILTER_VALIDATE_INT);
if (!$document_id) {
    die("ID văn bản không hợp lệ.");
}

// 2. Lấy thông tin văn bản chính
$stmt = $conn->prepare("SELECT id, document_number, file_path FROM documents WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    die("Văn bản không tồn tại hoặc đã bị xóa.");
}

// Kiểm tra văn bản Mật -> Chặn tải hàng loạt
$doc_num = $doc['document_number'] ?? '';
$doc_upper = mb_strtoupper($doc_num, 'UTF-8');
$is_secret = (strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);
if ($is_secret) {
    die("Văn bản Mật không cho phép tải tệp đính kèm.");
}

// 3. Thu thập danh sách tất cả các file cần nén
$files_to_zip = [];

// Thêm file chính (nếu có)
if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
    $files_to_zip[] = [
        'path' => $doc['file_path'],
        'name' => 'Van_ban_chinh_' . basename($doc['file_path'])
    ];
}

// Thêm các file phụ lục từ bảng document_attachments
$res_att = $conn->query("SHOW TABLES LIKE 'document_attachments'");
if ($res_att && $res_att->num_rows > 0) {
    // SỬA ĐÚNG TÊN CỘT: attachment_title và attachment_path
    $stmt_att = $conn->prepare("SELECT attachment_title, attachment_path FROM document_attachments WHERE document_id = ?");
    $stmt_att->bind_param("i", $document_id);
    $stmt_att->execute();
    $attachments = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_att->close();

    $idx = 1;
    foreach ($attachments as $att) {
        $p = $att['attachment_path'] ?? '';
        if (!empty($p) && file_exists($p)) {
            $ext = pathinfo($p, PATHINFO_EXTENSION);
            $custom_name = !empty($att['attachment_title']) ? preg_replace('/[^a-zA-Z0-9._-]/', '_', $att['attachment_title']) : ('Phu_luc_' . $idx);
            if (!str_ends_with(strtolower($custom_name), '.' . strtolower($ext))) {
                $custom_name .= '.' . $ext;
            }
            $files_to_zip[] = [
                'path' => $p,
                'name' => $custom_name
            ];
            $idx++;
        }
    }
}

if (empty($files_to_zip)) {
    die("<script>alert('Không tìm thấy tệp đính kèm vật lý nào trên máy chủ để tải xuống.'); window.history.back();</script>");
}

// 4. Khởi tạo ZipArchive
if (!extension_loaded('zip')) {
    die("Máy chủ PHP chưa bật extension ZipArchive.");
}

$zip = new ZipArchive();
$temp_zip = tempnam(sys_get_temp_dir(), 'doc_zip_');

if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Không thể tạo tệp nén ZIP.");
}

// Thêm từng file vào file ZIP
foreach ($files_to_zip as $f) {
    $zip->addFile($f['path'], $f['name']);
}
$zip->close();

// 5. Ghi log hoạt động
$user_id = (int)$_SESSION['user_id'];
$log_action = "Tải toàn bộ tệp đính kèm (ZIP) của VB: " . $doc_num;
try {
    $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user_id, $log_action);
        $log_stmt->execute();
        $log_stmt->close();
    }
} catch (Exception $e) {}

// 6. Gửi file ZIP về trình duyệt để tải xuống
$clean_docnum = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc_num);
$zip_download_name = "Tai_lieu_" . $clean_docnum . ".zip";

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_download_name . '"');
header('Content-Length: ' . filesize($temp_zip));
header('Pragma: no-cache');
header('Expires: 0');

readfile($temp_zip);

// Xóa file ZIP tạm trong bộ nhớ máy chủ
@unlink($temp_zip);
exit;