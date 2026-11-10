<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <title><?= h(get_setting('site_title', 'Quản lý văn bản')) ?></title>
    
    <!-- 1. Fonts (Local) -->
    <link href="assets/fonts/montserrat.css" rel="stylesheet">
    
    <!-- 2. Bootstrap CSS (Local) -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 3. FontAwesome Icons (Local) -->
    <!-- Đảm bảo đường dẫn này khớp với vị trí bạn lưu thư mục fontawesome -->
    <link href="assets/plugins/fontawesome/css/all.min.css" rel="stylesheet">
    
    <!-- 4. SweetAlert2 JS (Local) -->
    <!-- (Thông thường JS hay để ở Footer để tăng tốc độ tải trang, 
         nhưng giữ ở Header như cũ của bạn vẫn ổn nếu có các script gọi Swal ngay trong Body) -->
    <script src="assets/plugins/sweetalert2.all.min.js"></script>

    <!-- Custom Style -->
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">

    <!-- Anti-flicker Sidebar State -->
    <script>
        if (localStorage.getItem("sidebarState") === "collapsed") {
            document.documentElement.classList.add("sidebar-collapsed");
        }
    </script>
    
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    
    <style>
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #f8f9fa; 
        }
        .wrapper { 
            display: flex; 
            width: 100%; 
            align-items: stretch; 
            min-height: 100vh;
        }

        /* =======================================================
        TỐI ƯU GIAO DIỆN CHO MÀN HÌNH LAPTOP (1366x768)
        ======================================================= */
        @media (max-width: 1399px) {
            /* 1. Thu nhỏ font chữ tổng thể một chút */
            body {
                font-size: 0.875rem; /* ~14px thay vì 16px mặc định */
            }
            
            /* 2. Ép các khoảng trắng (Padding/Margin) gọn lại để tiết kiệm chiều dọc */
            .py-3 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
            .py-4 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
            .mb-4 { margin-bottom: 1rem !important; }
            .mb-5 { margin-bottom: 1.5rem !important; }
            .mt-4 { margin-top: 1rem !important; }
            .p-4 { padding: 1.25rem !important; }
            
            /* 3. Thu nhỏ các thẻ Thống kê (Cards) & Header Card */
            .card-body { padding: 1rem !important; }
            .card-header { padding: 0.75rem 1rem !important; }
            .fs-1 { font-size: 2rem !important; } /* Thu nhỏ icon to */
            .fs-4 { font-size: 1.25rem !important; } /* Thu nhỏ số liệu */
            
            /* 4. Tối ưu thanh Sidebar (Thu hẹp khoảng cách các menu) */
            .sidebar-menu .nav-link {
                padding: 0.4rem 1rem;
                font-size: 0.85rem;
            }
            .avatar-container .avatar {
                width: 45px !important; 
                height: 45px !important;
            }
            
            /* 5. Ép các ô input/select gọn lại */
            .form-control, .form-select, .btn {
                font-size: 0.85rem;
                padding: 0.375rem 0.75rem;
            }
            
            /* 6. Tối ưu bảng dữ liệu (Table) cực kỳ quan trọng */
            .table > :not(caption) > * > * {
                padding: 0.4rem 0.5rem; /* Thu nhỏ chiều cao mỗi dòng */
                font-size: 0.85rem;
            }
            
            /* 7. Thu nhỏ các tiêu đề H3, H4, H5 */
            h3 { font-size: 1.3rem !important; }
            h4 { font-size: 1.15rem !important; }
            h5 { font-size: 1.05rem !important; }
        }
    </style>
</head>
<body>
<div class="wrapper d-flex w-100">