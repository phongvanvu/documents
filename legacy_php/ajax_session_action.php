<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên (Admin) mới có quyền cưỡng chế đăng xuất
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$session_id = intval($_POST['id'] ?? 0);
$current_session_token = session_id();
$admin_id = (int)$_SESSION['user_id'];

// 1. CƯỠNG CHẾ ĐĂNG XUẤT 1 PHIÊN CỤ THỂ
if ($action === 'terminate_single' && $session_id > 0) {
    // Lấy thông tin phiên trước khi xóa để ghi log
    $stmt_info = $conn->prepare("SELECT us.session_token, u.username FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.id = ?");
    $stmt_info->bind_param("i", $session_id);
    $stmt_info->execute();
    $session_data = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    if ($session_data) {
        if ($session_data['session_token'] === $current_session_token) {
            echo json_encode(['status' => 'error', 'message' => 'Bạn không thể hủy phiên làm việc hiện tại của chính mình từ đây!']);
            exit;
        }

        $stmt_del = $conn->prepare("DELETE FROM user_sessions WHERE id = ?");
        $stmt_del->bind_param("i", $session_id);
        if ($stmt_del->execute()) {
            $stmt_del->close();

            // Ghi log
            $log_act = "Cưỡng chế đăng xuất phiên làm việc của người dùng: " . $session_data['username'];
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $admin_id, $log_act);
                $l->execute();
                $l->close();
            }

            echo json_encode(['status' => 'success', 'message' => 'Đã chấm dứt phiên làm việc thành công!']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy phiên làm việc hoặc đã hết hạn!']);
    exit;
}

// 2. DỌN DẸP TOÀN BỘ PHIÊN HẾT HẠN (QUÁ 24 GIỜ KHÔNG HOẠT ĐỘNG)
if ($action === 'cleanup_inactive') {
    $res = $conn->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $affected = $conn->affected_rows;
    echo json_encode(['status' => 'success', 'message' => "Đã dọn dẹp $affected phiên làm việc không còn hoạt động!"]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;