<?php
session_start();
include 'db.php'; // Kết nối đến cơ sở dữ liệu

// Khởi tạo biến thông báo
$message = "";

// Kiểm tra nếu biểu mẫu được gửi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $full_name = isset($_POST['full_name']) ? htmlspecialchars(trim($_POST['full_name']), ENT_QUOTES, 'UTF-8') : '';
    $email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8') : '';
    $message_content = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8') : '';

    // Kiểm tra dữ liệu
    if (empty($full_name) || empty($email) || empty($message_content)) {
        $message = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Địa chỉ email không hợp lệ.";
    } else {
        // Lưu thông tin vào cơ sở dữ liệu
        $sql = "INSERT INTO contacts (full_name, email, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $full_name, $email, $message_content);

        if ($stmt->execute()) {
            $message = "Thông tin của bạn đã được gửi thành công!";
        } else {
            $message = "Có lỗi xảy ra. Vui lòng thử lại sau.";
        }
    }
}

include 'header.php'; // Bao gồm header
?>

<div class="container mt-5">
    <h2 class="text-center">Gửi Thông Tin Liên Hệ</h2>
    <div class="alert alert-info text-center">
        <?php echo $message; ?>
    </div>
    <a href="contact.php" class="btn btn-primary">Quay lại</a>
</div>

<?php include 'footer.php'; ?>
