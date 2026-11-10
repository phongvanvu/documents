<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên tối cao (Admin) mới có quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// 1. TẠO BẢN SAO LƯU MỚI (BACKUP DATABASE)
if ($action === 'create_backup') {
    try {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $sql_dump = "-- HE THONG QUAN LY VAN BAN PHAP LUAT\n";
        $sql_dump .= "-- Database Backup: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "-- MySQL Version: " . $conn->server_info . "\n\n";
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql_dump .= "SET time_zone = \"+07:00\";\n\n";

        foreach ($tables as $table) {
            // Lấy cấu trúc bảng (CREATE TABLE)
            $res_table = $conn->query("SHOW CREATE TABLE `$table`");
            $row_table = $res_table->fetch_row();
            $sql_dump .= "\n-- --------------------------------------------------------\n";
            $sql_dump .= "-- Cau truc bang `$table`\n";
            $sql_dump .= "-- --------------------------------------------------------\n";
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $row_table[1] . ";\n\n";

            // Lấy dữ liệu (INSERT INTO)
            $res_data = $conn->query("SELECT * FROM `$table`");
            $num_fields = $res_data->field_count;
            $row_count = $res_data->num_rows;

            if ($row_count > 0) {
                $sql_dump .= "-- Du lieu bang `$table`\n";
                while ($row = $res_data->fetch_row()) {
                    $sql_dump .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $num_fields; $j++) {
                        if (isset($row[$j])) {
                            $sql_dump .= '"' . $conn->real_escape_string($row[$j]) . '"';
                        } else {
                            $sql_dump .= 'NULL';
                        }
                        if ($j < ($num_fields - 1)) {
                            $sql_dump .= ', ';
                        }
                    }
                    $sql_dump .= ");\n";
                }
                $sql_dump .= "\n";
            }
        }

        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $filename = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
        $filepath = $backup_dir . $filename;

        if (file_put_contents($filepath, $sql_dump) !== false) {
            // Ghi log hệ thống
            $user_id = (int)$_SESSION['user_id'];
            $log_act = "Tạo bản sao lưu CSDL: $filename";
            try {
                $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
                if ($l) {
                    $l->bind_param("is", $user_id, $log_act);
                    $l->execute();
                    $l->close();
                }
            } catch (Exception $e) {}

            echo json_encode(['status' => 'success', 'message' => 'Đã tạo bản sao lưu CSDL thành công!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể ghi tệp vào thư mục backups/!']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit;
}

// 2. PHỤC HỒI DỮ LIỆU TỪ BẢN SAO LƯU (RESTORE DATABASE)
if ($action === 'restore_backup') {
    $filename = basename(trim($_POST['filename'] ?? ''));
    $filepath = $backup_dir . $filename;

    if (empty($filename) || !file_exists($filepath)) {
        echo json_encode(['status' => 'error', 'message' => 'Tệp sao lưu không tồn tại!']);
        exit;
    }

    try {
        $sql_content = file_get_contents($filepath);
        if ($sql_content === false) {
            throw new Exception("Không thể đọc tệp sao lưu.");
        }

        // Tắt khóa ngoại tạm thời và thực thi multi query
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        if ($conn->multi_query($sql_content)) {
            do {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        // Ghi log hệ thống
        $user_id = (int)$_SESSION['user_id'];
        $log_act = "Phục hồi toàn bộ CSDL từ bản sao lưu: $filename";
        try {
            $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
            if ($l) {
                $l->bind_param("is", $user_id, $log_act);
                $l->execute();
                $l->close();
            }
        } catch (Exception $e) {}

        echo json_encode(['status' => 'success', 'message' => 'Đã phục hồi dữ liệu hệ thống thành công!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi phục hồi: ' . $e->getMessage()]);
    }
    exit;
}

// 3. XÓA BẢN SAO LƯU (DELETE BACKUP)
if ($action === 'delete_backup') {
    $filename = basename(trim($_POST['filename'] ?? ''));
    $filepath = $backup_dir . $filename;

    if (!empty($filename) && file_exists($filepath)) {
        unlink($filepath);
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa bản sao lưu thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tệp không tồn tại hoặc đã bị xóa!']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;