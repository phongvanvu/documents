<!-- sidebar.php -->
<div class="d-flex flex-column text-white min-vh-100">
    <span class="hamburger" onclick="toggleSidebar()">&#9776;</span>
    <div class="avatar-container text-center my-4 align-items-center"> 
        <?php
        echo '<img src="' . $_SESSION['avatar'] . '" alt="Avatar" class="avatar img-fluid">';
        ?>
    </div>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link">
                <i class="bi bi-house-door fs-7 icon" data-bs-toggle="tooltip" title="Trang chính"></i><span class="nav-text">  Trang chính</span>
            </a>
        </li>
        <?php
        if ($_SESSION['role'] == 'admin'||$_SESSION['role'] == 'editor') { ?>
            <li class="nav-item">
                <a href="add_document.php" class="nav-link">
                    <i class="bi bi bi-plus-circle fs-7 icon" data-bs-toggle="tooltip" title="Thêm mới"></i><span class="nav-text">  Văn bản mới</span>
                </a>
            </li>
        <?php }
        if ($_SESSION['role'] == 'admin') { ?>
            <li class="nav-item">
                <a href="manage_users.php" class="nav-link">
                    <i class="bi bi-people fs-7 icon" data-bs-toggle="tooltip" title="Người dùng"></i><span class="nav-text">  Người dùng</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_document_types.php" class="nav-link">
                    <i class="bi bi-tag fs-7 icon" data-bs-toggle="tooltip" title="Loại văn bản"></i><span class="nav-text">  Loại văn bản</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_issuing_authorities.php" class="nav-link">
                    <i class="bi bi-building fs-7 icon" data-bs-toggle="tooltip" title="Nơi ban hành"></i><span class="nav-text">  Nơi ban hành</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_contacts.php" class="nav-link">
                    <i class="bi bi-envelope fs-7 icon" data-bs-toggle="tooltip" title="Liên hệ"></i><span class="nav-text">  Liên hệ</span>
                </a>
            </li>
        <?php } ?>
        <li class="nav-item">
            <a href="change_password.php" class="nav-link">
                <i class="bi bi-key fs-7 icon" data-bs-toggle="tooltip" title="Đổi mật khẩu"></i><span class="nav-text">  Đổi mật khẩu</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="huong_dan_su_dung.php" class="nav-link">
                <i class="bi bi-book fs-7 icon" data-bs-toggle="tooltip" title="Hướng dẫn sử dụng"></i><span class="nav-text">  Hướng dẫn sử dụng</span>
            </a>
        </li>
    </ul>
    <hr>
    <div><ul class="nav nav-pills flex-column mt-auto mb-4"><li class="nav-item">
        <a class="nav-link pass-font text-center" href="logout.php">
            <i class="bi bi-box-arrow-right fs-7 icon" data-bs-toggle="tooltip" title="Đăng xuất"></i><span class="nav-text"> Đăng xuất</span></a>
    </div></li></ul>
</div>
<script>
    function toggleSidebar() {
        var sidebar = document.getElementById("sidebar");
        var content = document.getElementById("content");
        sidebar.classList.toggle("collapsed");
        content.classList.toggle("collapsed");
    }
</script>
