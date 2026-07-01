<?php
// Khởi động session
session_start();
$title = "Hướng dẫn sử dụng";
include 'header.php'; // Bao gồm header
?>
<div class="container-fluid main">
    <div class="row flex-nowrap">
        <!-- Sidebar -->
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>
        <!-- Nội dung chính -->
        <div id="content" class="content" class="col py-3">
            <h2>Hướng dẫn sử dụng</h2>
            <p>Đây là trang hướng dẫn sử dụng hệ thống. Nội dung hướng dẫn chi tiết sẽ được thêm vào đây.</p> 
        </div>
    </div>
<?php
include 'footer.php'; // Bao gồm footer
?>
