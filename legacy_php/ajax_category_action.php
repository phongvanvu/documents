<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền
if (!isset($_SESSION['user_id']) || (!has_permission('categories.manage') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$target = trim($_POST['target'] ?? ''); // 'type' hoặc 'authority'
$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$user_id = (int)$_SESSION['user_id'];

$table = ($target === 'authority') ? 'issuing_authorities' : 'document_types';
$field_name = ($target === 'authority') ? 'authority_name' : 'type_name';
$field_fk = ($target === 'authority') ? 'authority_id' : 'document_type_id';
$title_label = ($target === 'authority') ? 'Cơ quan ban hành' : 'Loại văn bản';

// 1. THÊM MỚI DANH MỤC
if ($action === 'create') {
    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Tên danh mục không được để trống!']);
        exit;
    }

    // Kiểm tra trùng
    $check_stmt = $conn->prepare("SELECT id FROM `$table` WHERE LOWER(`$field_name`) = LOWER(?) LIMIT 1");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    if ($check_stmt->get_result()->fetch_assoc()) {
        $check_stmt->close();
        echo json_encode(['status' => 'error', 'message' => "$title_label này đã tồn tại trong hệ thống!"]);
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("INSERT INTO `$table` (`$field_name`) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        $stmt->close();

        // Ghi log
        $log_act = "Thêm mới $title_label: $name";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã thêm mới $title_label thành công!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 2. ĐỔI TÊN DANH MỤC (CẬP NHẬT)
if ($action === 'update') {
    if ($id <= 0 || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu cập nhật không hợp lệ!']);
        exit;
    }

    // Kiểm tra trùng với mục khác
    $check_stmt = $conn->prepare("SELECT id FROM `$table` WHERE LOWER(`$field_name`) = LOWER(?) AND id != ? LIMIT 1");
    $check_stmt->bind_param("si", $name, $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->fetch_assoc()) {
        $check_stmt->close();
        echo json_encode(['status' => 'error', 'message' => "Tên $title_label này đã trùng với một mục khác!"]);
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("UPDATE `$table` SET `$field_name` = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    if ($stmt->execute()) {
        $stmt->close();

        // Ghi log
        $log_act = "Cập nhật tên $title_label ID #$id thành: $name";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã cập nhật $title_label thành công!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

// 3. XÓA DANH MỤC (KIỂM TRA AN TOÀN RÀNG BUỘC)
if ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã danh mục không hợp lệ!']);
        exit;
    }

    // Kiểm tra xem danh mục có văn bản nào đang dùng không
    $stmt_chk = $conn->prepare("SELECT COUNT(*) AS total FROM documents WHERE `$field_fk` = ? AND deleted_at IS NULL");
    $stmt_chk->bind_param("i", $id);
    $stmt_chk->execute();
    $cnt = (int)($stmt_chk->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_chk->close();

    if ($cnt > 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Không thể xóa! Đang có $cnt văn bản thuộc danh mục này. Vui lòng sử dụng tính năng 'Gộp danh mục' để chuyển văn bản sang danh mục khác trước khi xóa."
        ]);
        exit;
    }

    $stmt_del = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt_del->bind_param("i", $id);
    if ($stmt_del->execute()) {
        $stmt_del->close();

        // Ghi log
        $log_act = "Xóa $title_label ID #$id (0 văn bản liên kết)";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => "Đã xóa $title_label thành công!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;