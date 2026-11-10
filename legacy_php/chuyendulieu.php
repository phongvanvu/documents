<?php
// Kết nối cơ sở dữ liệu
$host = 'localhost';
$username = 'root';
$password = '';

// Tạo kết nối
$conn = new mysqli($host, $username, $password);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Cập nhật nhiều cột từ source_db sang target_db
$sql = "
    INSERT INTO documents_db.documents (title, document_type, issuing_authority, issued_date, content, file_path, created_at)
    SELECT  	sovanban,  	loaivanban, noibanhanh, ngaybanhanh, trichyeu, noiluuvanban, ngay_tai_len
    FROM qlvb. tbldanhmuc;
";

// Thực thi truy vấn
if ($conn->query($sql) === TRUE) {
    echo "Cập nhật dữ liệu thành công!";
} else {
    echo "Lỗi: " . $conn->error;
}

// Đóng kết nối
$conn->close();
?>
