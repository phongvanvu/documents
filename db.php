<?php
// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost"; // Địa chỉ máy chủ (thường là localhost)
$username = "root";        // Tên đăng nhập MySQL (mặc định là root)
$password = "";            // Mật khẩu MySQL (mặc định là rỗng nếu chưa đặt)
$dbname = "documents_db"; // Tên cơ sở dữ liệu

// Tạo kết nối tới cơ sở dữ liệu
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Đặt charset để hỗ trợ Unicode
$conn->set_charset("utf8");


// Hàm ghi nhật ký hoạt động
function log_activity($user_id, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $stmt->close();
}

?>