<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Giao Diện</title>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Thanh bên (Sidebar) -->
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
            <div class="sidebar-sticky">
                <h4 class="sidebar-heading">Menu</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Quản lý người dùng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_logs.php">Nhật ký hoạt động</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_document_types.php">Quản lý loại văn bản</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_issuing_authorities.php">Quản lý nơi ban hành</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_document.php">Thêm văn bản mới</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user_guide.php">Hướng dẫn sử dụng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="change_password.php">Thay đổi mật khẩu</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php">Đăng xuất</a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Phần nội dung (Content) -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-4">
            <h1>Nội dung chính</h1>
            <p>Đây là phần nội dung chính của trang.</p>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
