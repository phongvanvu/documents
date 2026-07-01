<?php
// Bắt đầu session
session_start();

// Kiểm tra quyền truy cập
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'editor') {
    header("Location: dashboard.php");
    exit();
}

// Kết nối cơ sở dữ liệu
include 'db.php'; // Chứa thông tin kết nối đến cơ sở dữ liệu

$error = '';
$success = '';

// Kiểm tra xem có gửi form không
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $department_name = trim($_POST['authority_name']);

    // Kiểm tra xem tên cơ quan có bị bỏ trống không
    if (empty($department_name)) {
        $error = "Vui lòng điền tên cơ quan ban hành!";
    } else {
        // Kiểm tra xem cơ quan ban hành đã tồn tại chưa
        $stmt = $conn->prepare("SELECT COUNT(*) FROM issuing_authorities WHERE authority_name = ?");
        $stmt->bind_param("s", $department_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Cơ quan ban hành đã tồn tại!";
        } else {
            // Thêm cơ quan ban hành vào cơ sở dữ liệu
            $stmt = $conn->prepare("INSERT INTO issuing_authorities (authority_name) VALUES (?)");
            $stmt->bind_param("s", $department_name);

            if ($stmt->execute()) {
                $success = "Thêm cơ quan ban hành thành công!";
                // Ghi nhật ký hoạt động nếu cần
                // log_activity($_SESSION['user_id'], "Thêm cơ quan ban hành: $department_name");
            } else {
                $error = "Có lỗi xảy ra khi lưu vào cơ sở dữ liệu.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Nơi Ban Hành - Xử Lý</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <?php if (!empty($error)) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif (!empty($success)) : ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-secondary mt-3">Quay lại Trang Chủ</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
