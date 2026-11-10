<?php
// Thông tin kết nối cơ sở dữ liệu
$host = 'localhost';
$username = 'root'; // Tên đăng nhập MySQL của bạn
$password = '';     // Mật khẩu MySQL của bạn
$database = 'documents_db'; // Tên cơ sở dữ liệu của bạn

// Tạo kết nối tới cơ sở dữ liệu
$conn = new mysqli($host, $username, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Câu lệnh SQL để thay thế cụm từ
$sql = "
    UPDATE documents
    SET file_path = REPLACE(file_path, 'VanBanHD/', 'uploads/');
";

// Thực thi câu lệnh
if ($conn->query($sql) === TRUE) {
    echo "Thay thế cụm từ thành công!";
} else {
    echo "Lỗi: " . $conn->error;
}

// Đóng kết nối
$conn->close();
?>
