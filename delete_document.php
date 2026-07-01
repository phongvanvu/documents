<?php
// Khởi động session
session_start();

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kiểm tra xem người dùng có phải admin hoặc có quyền xóa tài liệu không
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'editor') {
    echo "Bạn không có quyền xóa tài liệu.";
    exit;
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Kiểm tra nếu ID tài liệu được gửi qua URL
if (isset($_GET['id'])) {
    $document_id = intval($_GET['id']);

    // Truy vấn để kiểm tra văn bản có tồn tại không
    $sql = "SELECT document_number FROM documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $document_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        // Văn bản không tồn tại
        echo "Văn bản không tồn tại.";
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->bind_result($document_number);
    $stmt->fetch();
    $stmt->close();


    // Xóa tài liệu khỏi cơ sở dữ liệu
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param("i", $document_id);
    
    if ($stmt->execute()) {
        // Ghi lại log hành động xóa
        $user_id = $_SESSION['user_id'];
        $action = "Xóa văn bản số hiệu: " . $document_number;
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        $log_stmt->close();

        // Chuyển hướng về trang dashboard với thông báo thành công
        $_SESSION['success_message'] = "Tài liệu đã được xóa thành công.";
        header('Location: dashboard.php');
        exit;
    } else {
        echo "Có lỗi xảy ra khi xóa tài liệu.";
    }
    $stmt->close();
} else {
    echo "Không có ID tài liệu nào được cung cấp.";
}

// Đóng kết nối cơ sở dữ liệu
$conn->close();
?>
