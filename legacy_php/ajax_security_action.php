<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên tối cao (Admin) mới có quyền can thiệp an ninh
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)$_SESSION['user_id'];

// 1. TỰ ĐỘNG KHÓA THỰC THI SCRIPT PHP TRONG THƯ MỤC UPLOADS VÀ BACKUPS
if ($action === 'harden_uploads') {
    $htaccess_content = "# Chặn thực thi mã nguồn PHP và các tệp nguy hiểm\n";
    $htaccess_content .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|exe|sh|py)$\">\n";
    $htaccess_content .= "    Order Allow,Deny\n";
    $htaccess_content .= "    Deny from all\n";
    $htaccess_content .= "</FilesMatch>\n";
    $htaccess_content .= "Options -Indexes\n";

    $targets = [__DIR__ . '/uploads/', __DIR__ . '/backups/'];
    $success = 0;

    foreach ($targets as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht_file = $dir . '.htaccess';
        if (file_put_contents($ht_file, $htaccess_content) !== false) {
            $success++;
        }
        // Tạo thêm file index.php rỗng chống Directory Listing
        $idx_file = $dir . 'index.php';
        if (!file_exists($idx_file)) {
            @file_put_contents($idx_file, "<?php header('HTTP/1.0 403 Forbidden'); exit; ?>");
        }
    }

    if ($success > 0) {
        $log_act = "Tự động thiết lập bảo vệ chống thực thi script độc hại cho thư mục uploads/ và backups/";
        $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        if ($l) {
            $l->bind_param("is", $user_id, $log_act);
            $l->execute();
            $l->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Đã thiết lập chặn thực thi script độc hại và cấm duyệt thư mục thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể ghi file .htaccess (Vui lòng kiểm tra quyền ghi máy chủ)!']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;