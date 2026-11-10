<?php
session_start();
error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền: Admin hoặc người có quyền documents.view
if (!isset($_SESSION['user_id']) || (!has_permission('documents.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    die("Bạn không có quyền thực hiện chức năng này.");
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$authority_id = intval($_POST['authority_id'] ?? $_GET['authority_id'] ?? 0);
$type_id = intval($_POST['type_id'] ?? $_GET['type_id'] ?? 0);
$status = trim($_POST['status'] ?? $_GET['status'] ?? '');
$date_from = trim($_POST['date_from'] ?? $_GET['date_from'] ?? '');
$date_to = trim($_POST['date_to'] ?? $_GET['date_to'] ?? '');

$conditions = ["d.deleted_at IS NULL"];
$params = [];
$param_types = "";

if ($authority_id > 0) {
    $conditions[] = "d.authority_id = ?";
    $params[] = $authority_id;
    $param_types .= "i";
}

if ($type_id > 0) {
    $conditions[] = "d.document_type_id = ?";
    $params[] = $type_id;
    $param_types .= "i";
}

if ($status !== '') {
    $conditions[] = "d.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

if ($date_from !== '') {
    $conditions[] = "d.issued_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to !== '') {
    $conditions[] = "d.issued_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = " WHERE " . implode(" AND ", $conditions);

// 1. XUẤT SỔ MỤC LỤC SANG TỆP CSV / EXCEL
if ($action === 'export_csv') {
    $sql = "SELECT d.document_number, dt.type_name, ia.authority_name, d.issued_date, d.effective_date, d.expired_date, d.status, d.content, d.file_path 
            FROM documents d 
            LEFT JOIN document_types dt ON d.document_type_id = dt.id 
            LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
            $where_clause 
            ORDER BY d.issued_date DESC, d.id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=muc_luc_van_ban_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    // Ghi mã BOM để Excel mở đúng dấu tiếng Việt
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['STT', 'Số ký hiệu', 'Loại văn bản', 'Cơ quan ban hành', 'Ngày ban hành', 'Ngày hiệu lực', 'Ngày hết hạn', 'Trạng thái', 'Trích yếu nội dung', 'Tệp đính kèm']);

    $stt = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $stt++,
            $row['document_number'],
            $row['type_name'] ?? 'Chưa phân loại',
            $row['authority_name'] ?? 'Chưa rõ',
            !empty($row['issued_date']) ? date('d/m/Y', strtotime($row['issued_date'])) : '',
            !empty($row['effective_date']) ? date('d/m/Y', strtotime($row['effective_date'])) : '',
            !empty($row['expired_date']) ? date('d/m/Y', strtotime($row['expired_date'])) : '',
            $row['status'],
            $row['content'],
            !empty($row['file_path']) ? basename($row['file_path']) : 'Không có'
        ]);
    }

    $stmt->close();
    fclose($out);
    exit;
}

// 2. ĐÓNG GÓI TỆP ĐÍNH KÈM THÀNH TỆP NÉN ZIP
if ($action === 'export_zip') {
    if (!class_exists('ZipArchive')) {
        die("Máy chủ chưa kích hoạt extension ZipArchive của PHP.");
    }

    $sql = "SELECT d.id, d.document_number, d.file_path 
            FROM documents d 
            $where_clause 
            AND d.file_path IS NOT NULL AND TRIM(d.file_path) != '' 
            ORDER BY d.id DESC 
            LIMIT 500"; // Giới hạn tối đa 500 văn bản mỗi lần tải để tránh timeout

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($docs)) {
        die("<script>alert('Không có tệp văn bản nào phù hợp với bộ lọc!'); window.history.back();</script>");
    }

    $zip_filename = 'ho_so_van_ban_' . date('Ymd_His') . '.zip';
    $zip_filepath = sys_get_temp_dir() . '/' . $zip_filename;

    $zip = new ZipArchive();
    if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Không thể khởi tạo file nén trên máy chủ.");
    }

    $added_files = 0;
    foreach ($docs as $doc) {
        $file = $doc['file_path'];
        if (file_exists($file) && is_file($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            // Chuẩn hóa tên file lưu trong ZIP: SoKyHieu.ext
            $safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $doc['document_number']);
            $entry_name = $safe_name . '_' . $doc['id'] . '.' . $ext;
            $zip->addFile($file, $entry_name);
            $added_files++;
        }
    }

    $zip->close();

    if ($added_files === 0) {
        @unlink($zip_filepath);
        die("<script>alert('Các văn bản được chọn đều không có tệp vật lý trên ổ cứng!'); window.history.back();</script>");
    }

    // Ghi nhật ký thao tác
    $user_id = (int)$_SESSION['user_id'];
    $log_act = "Đóng gói tải về tệp ZIP chứa $added_files tài liệu đính kèm";
    $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($l) {
        $l->bind_param("is", $user_id, $log_act);
        $l->execute();
        $l->close();
    }

    // Trả file ZIP về trình duyệt
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_filepath));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zip_filepath);
    @unlink($zip_filepath);
    exit;
}

die("Yêu cầu không hợp lệ.");