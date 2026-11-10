<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ Quản trị viên mới được phép thực hiện
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$table = trim($_POST['table'] ?? '');

try {
    if ($action === 'optimize_single' && !empty($table)) {
        // Tối ưu hóa 1 bảng cụ thể
        $stmt = $conn->prepare("OPTIMIZE TABLE `$table`");
        $stmt->execute();
        $stmt->close();

        // Cập nhật thống kê chỉ mục
        $conn->query("ANALYZE TABLE `$table`");

        echo json_encode(['status' => 'success', 'message' => "Đã tối ưu hóa và chống phân mảnh thành công bảng '$table'!"]);
        exit;
    }

    if ($action === 'optimize_all') {
        // Lấy danh sách tất cả các bảng
        $tables = [];
        $res = $conn->query("SHOW TABLES");
        while ($row = $res->fetch_row()) {
            $tables[] = $row[0];
        }

        $optimized_count = 0;
        foreach ($tables as $tbl) {
            $conn->query("OPTIMIZE TABLE `$tbl`");
            $conn->query("ANALYZE TABLE `$tbl`");
            $optimized_count++;
        }

        // Ghi log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $log_act = "Tối ưu hóa và chống phân mảnh toàn bộ CSDL ($optimized_count bảng)";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã tối ưu hóa thành công toàn bộ $optimized_count bảng trong CSDL!"]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
}
exit;