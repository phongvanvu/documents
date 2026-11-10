<?php
// Khởi động session để có thể thao tác với nó
session_start();

// 1. Xóa toàn bộ biến trong bộ nhớ Session
session_unset();

// 2. Tiêu hủy hoàn toàn Session trên máy chủ
session_destroy();

// 3. XÓA SẠCH COOKIE "GHI NHỚ ĐĂNG NHẬP" (Rất quan trọng)
// Đặt thời gian hết hạn lùi về 1 giờ trước (time() - 3600) để trình duyệt tự động xóa
$past_time = time() - 3600;
setcookie('username', '', ['expires' => $past_time, 'path' => '/']);
setcookie('user_id', '', ['expires' => $past_time, 'path' => '/']);
setcookie('full_name', '', ['expires' => $past_time, 'path' => '/']);
setcookie('role', '', ['expires' => $past_time, 'path' => '/']);
setcookie('avatar', '', ['expires' => $past_time, 'path' => '/']);

// 4. Chuyển hướng người dùng về trang đăng nhập
header('Location: login.php');
exit;