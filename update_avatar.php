<?php
session_start();
include 'db.php'; // Kết nối cơ sở dữ liệu

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Kiểm tra xem có file được tải lên không
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar'])) {
    
    $file = $_FILES['avatar'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    // Lấy phần mở rộng của file (đuôi file)
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    // 1. Kiểm tra định dạng file
    if (in_array($file_ext, $allowed_ext)) {
        // 2. Kiểm tra có lỗi quá trình upload không
        if ($file_error === 0) {
            // 3. Giới hạn dung lượng (5MB = 5000000 bytes)
            if ($file_size < 5000000) {
                
                // Đổi tên file để tránh bị trùng (Ví dụ: user_1_1710000000.jpg)
                $new_file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
                $upload_path = 'uploads/avatars/' . $new_file_name;

                // Đảm bảo thư mục tồn tại
                if (!is_dir('uploads/avatars/')) {
                    mkdir('uploads/avatars/', 0777, true);
                }

                // Lấy đường dẫn ảnh cũ từ Database để lát nữa xóa
                $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $old_avatar = $result->fetch_assoc()['avatar'];
                $stmt->close();

                // 4. Di chuyển file từ bộ nhớ tạm vào thư mục chính thức
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    
                    // Cập nhật đường dẫn mới vào Database
                    $update_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $upload_path, $user_id);
                    
                    if ($update_stmt->execute()) {
                        // Xóa ảnh cũ khỏi ổ cứng (không xóa nếu đó là ảnh default.jpg)
                        if (!empty($old_avatar) && $old_avatar !== 'uploads/avatars/default.jpg' && file_exists($old_avatar)) {
                            unlink($old_avatar);
                        }

                        // Cập nhật lại Session để Sidebar hiển thị ngay ảnh mới
                        $_SESSION['avatar'] = $upload_path;

                        // Thành công, chuyển hướng về Dashboard (Hoặc trang cá nhân)
                        // Bạn có thể dùng SweetAlert2 bên trang đích để báo thành công
                        header("Location: dashboard.php"); 
                        exit;
                    }
                    $update_stmt->close();
                } else {
                    die("<div class='alert alert-danger text-center mt-5'>Đã có lỗi khi lưu file vào hệ thống.</div>");
                }
            } else {
                die("<div class='alert alert-warning text-center mt-5'>File ảnh quá lớn. Vui lòng chọn ảnh dưới 5MB.</div>");
            }
        } else {
            die("<div class='alert alert-danger text-center mt-5'>Có lỗi xảy ra trong quá trình tải file. Code: $file_error</div>");
        }
    } else {
        die("<div class='alert alert-warning text-center mt-5'>Chỉ chấp nhận định dạng ảnh JPG, JPEG, PNG hoặc GIF.</div>");
    }
} else {
    header("Location: profile.php");
    exit;
}
?>