<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên tối cao (Admin) mới có quyền dọn dẹp log
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$days = intval($_POST['days'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($action === 'clear_old_logs') {
    if ($days <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Khoảng thời gian không hợp lệ!']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", $days);
    
    if ($stmt->execute()) {
        $deleted = $stmt->affected_rows;
        $stmt->close();

        // Ghi log chính hành động dọn dẹp này
        $log_act = "Dọn dẹp $deleted bản ghi nhật ký hệ thống cũ hơn $days ngày";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã dọn dẹp thành công $deleted bản ghi nhật ký cũ hơn $days ngày!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

if ($action === 'clear_all_logs') {
    if ($conn->query("TRUNCATE TABLE logs")) {
        // Ghi lại log đầu tiên
        $log_act = "Xóa toàn bộ dữ liệu nhật ký hệ thống";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã làm sạch toàn bộ nhật ký hệ thống!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;