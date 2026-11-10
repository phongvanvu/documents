<?php
session_start();

// Bảo mật: Chỉ người dùng đã đăng nhập và là Admin mới kích hoạt tiến trình này
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Không có quyền truy cập']);
    exit;
}

$backup_dir = 'backups/';
$backup_file = $backup_dir . 'backup_' . date('Ymd') . '.sql';

// Đảm bảo thư mục backups tồn tại
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

if (file_exists($backup_file)) {
    // Đã sao lưu thì không làm gì thêm
    echo json_encode(['status' => 'success', 'message' => 'Đã có bản sao lưu ngày hôm nay.']);
} else {
    // Nếu chưa sao lưu, gọi file backup.php chạy ngầm
    // Dùng ob_start() để ngăn file backup.php lỡ có in ra chữ gì làm hỏng cấu trúc JSON
    ob_start();
    include 'backup.php'; 
    $output = ob_get_clean(); 
    
    echo json_encode(['status' => 'success', 'message' => 'Đã tự động tạo bản sao lưu mới thành công.']);
}
?>