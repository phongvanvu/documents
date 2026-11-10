<?php
session_start();
include 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Helper trả về JSON ngắn gọn
function json_res($status, $data = []) {
    $res = array_merge(['status' => $status], is_string($data) ? ['message' => $data] : $data);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Kiểm tra đăng nhập, quyền hạn & phương thức POST
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    json_res('error', 'Bạn không có quyền thực hiện thao tác này.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_res('error', 'Phương thức yêu cầu không hợp lệ.');
}

// 2. Nhận và làm sạch dữ liệu
$type_name = trim($_POST['type_name'] ?? '');

if ($type_name === '') {
    json_res('error', 'Tên loại văn bản không được để trống.');
}

// 3. Kiểm tra trùng lặp trong CSDL
try {
    $stmt = $conn->prepare("SELECT id FROM document_types WHERE type_name = ?");
    $stmt->bind_param("s", $type_name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        json_res('error', 'Loại văn bản này đã tồn tại trong hệ thống.');
    }
    $stmt->close();

    // 4. Thêm loại văn bản mới
    $stmt_ins = $conn->prepare("INSERT INTO document_types (type_name) VALUES (?)");
    $stmt_ins->bind_param("s", $type_name);
    
    if ($stmt_ins->execute()) {
        $new_id = $stmt_ins->insert_id;
        $stmt_ins->close();

        // Ghi Log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $action  = "Thêm nhanh Loại văn bản: '{$type_name}' (ID: {$new_id})";
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }

        json_res('success', ['id' => $new_id, 'name' => $type_name]);
    } else {
        json_res('error', 'Lỗi hệ thống khi lưu vào cơ sở dữ liệu.');
    }
} catch (mysqli_sql_exception $e) {
    error_log("ajax_add_type error: " . $e->getMessage());
    json_res('error', 'Có lỗi xảy ra trong quá trình xử lý.');
}