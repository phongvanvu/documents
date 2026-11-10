<?php
session_start();
include 'db.php';
include 'header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <title>Trợ Giúp</title>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php"><img src="logo.webp" width=30px height=30px></img></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Đăng Nhập</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Liên Hệ</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="text-center">Hỗ Trợ Khách Hàng</h2>
        <p class="mt-4">Chào mừng bạn đến với trang hỗ trợ của chúng tôi! Dưới đây là một số thông tin hữu ích:</p>

        <h4>1. Câu Hỏi Thường Gặp (FAQ)</h4>
        <ul>
            <li><strong>Làm thế nào để đăng ký tài khoản?</strong> - Bạn có thể đăng ký tài khoản bằng cách liên hệ với chúng tôi.</li>
            <li><strong>Quên mật khẩu?</strong> - Nếu bạn quên mật khẩu, hãy sử dụng chức năng "Quên mật khẩu" trên trang đăng nhập.</li>
            <li><strong>Làm thế nào để liên hệ với chúng tôi?</strong> - Bạn có thể liên hệ qua trang liên hệ hoặc gửi email tới phongvanvu@gmail.com.</li>
        </ul>

        <h4>2. Liên Hệ Hỗ Trợ</h4>
        <p>Nếu bạn cần thêm hỗ trợ, vui lòng liên hệ với chúng tôi qua:</p>
        <ul>
            <li>Email: <a href="mailto:phongvanvu@gmail.com">phongvanvu@gmail.com</a></li>
            <li>Điện thoại: 0975760595</li>
        </ul>

        <h4>3. Giờ Làm Việc</h4>
        <p>Chúng tôi có mặt để hỗ trợ bạn vào các giờ làm việc sau:</p>
        <ul>
            <li>Thứ Hai đến Thứ Sáu: 9:00 - 18:00</li>
        </ul>
    </div>

    <footer class="bg-light text-center py-3">
        <div class="container">
            <p class="mb-0">© <?php echo date("Y"); ?> Công ty của bạn. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
