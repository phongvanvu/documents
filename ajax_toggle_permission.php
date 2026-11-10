<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$role = trim($_POST['role'] ?? '');
$perm_key = trim($_POST['perm_key'] ?? '');
$status = intval($_POST['status'] ?? 0);

if ($role === '' || $perm_key === '' || $role === 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ hoặc không thể sửa quyền của Admin!']);
    exit;
}

if ($status === 1) {
    // Thêm quyền
    $stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role, perm_key) VALUES (?, ?)");
    $stmt->bind_param("ss", $role, $perm_key);
    $stmt->execute();
    $stmt->close();
    $msg = "Đã cấp quyền thành công!";
} else {
    // Gỡ quyền
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role = ? AND perm_key = ?");
    $stmt->bind_param("ss", $role, $perm_key);
    $stmt->execute();
    $stmt->close();
    $msg = "Đã hủy quyền thành công!";
}

// Xóa cache quyền trong session hiện tại để có hiệu lực ngay
unset($_SESSION['user_perms']);

// Ghi log hệ thống
$user_id = (int)$_SESSION['user_id'];
$log_act = ($status === 1 ? "Cấp quyền " : "Hủy quyền ") . "$perm_key cho vai trò: $role";
try {
    $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($l) {
        $l->bind_param("is", $user_id, $log_act);
        $l->execute();
        $l->close();
    }
} catch (Exception $e) {}

echo json_encode(['status' => 'success', 'message' => $msg]);