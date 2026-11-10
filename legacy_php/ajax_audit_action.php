<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

if (!isset($_SESSION['user_id']) || (!has_permission('categories.manage') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)$_SESSION['user_id'];

// 1. Tự động cập nhật trạng thái các văn bản đã quá hạn sang "Hết hiệu lực"
if ($action === 'fix_expired_status') {
    $sql = "UPDATE documents 
            SET status = 'Hết hiệu lực' 
            WHERE deleted_at IS NULL 
              AND expired_date IS NOT NULL 
              AND YEAR(expired_date) > 1900 
              AND expired_date < CURDATE() 
              AND status = 'Còn hiệu lực'";
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        
        $log_act = "Tự động chuẩn hóa trạng thái hết hiệu lực cho $affected văn bản";
        try {
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        } catch (Exception $e) {}

        echo json_encode(['status' => 'success', 'message' => "Đã cập nhật $affected văn bản sang trạng thái 'Hết hiệu lực'!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 2. Điền tự động ngày hiệu lực bằng ngày ban hành (nếu ngày hiệu lực đang trống)
if ($action === 'fix_missing_effective_date') {
    $sql = "UPDATE documents 
            SET effective_date = issued_date 
            WHERE deleted_at IS NULL 
              AND (effective_date IS NULL OR YEAR(effective_date) <= 1900) 
              AND issued_date IS NOT NULL 
              AND YEAR(issued_date) > 1900";
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        echo json_encode(['status' => 'success', 'message' => "Đã bổ sung ngày hiệu lực cho $affected văn bản!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 3. Chuẩn hóa xóa khoảng trắng thừa ở số ký hiệu
if ($action === 'trim_doc_numbers') {
    $sql = "UPDATE documents 
            SET document_number = TRIM(document_number) 
            WHERE deleted_at IS NULL AND document_number != TRIM(document_number)";
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        echo json_encode(['status' => 'success', 'message' => "Đã chuẩn hóa định dạng số hiệu cho $affected văn bản!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;