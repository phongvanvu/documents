<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', get_setting('site_title', 'Hệ thống Quản lý Văn bản'))</title>

    <!-- Fonts & Bootstrap 5 & FontAwesome (Local) -->
    <link href="{{ asset('assets/fonts/montserrat.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <script src="{{ asset('assets/plugins/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/chart.js') }}"></script>

    <!-- Custom Style -->
    <link rel="stylesheet" href="{{ asset('style.css') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    <script>
        if (localStorage.getItem("sidebarState") === "collapsed") {
            document.documentElement.classList.add("sidebar-collapsed");
        }
    </script>

    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .wrapper { display: flex; width: 100%; min-height: 100vh; }
        #sidebar {
            width: 250px;
            background: #dc3545;
            color: #fff;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            transition: all 0.3s;
            overflow-y: auto;
        }
        #content {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }
        .sidebar-collapsed #sidebar { width: 70px; }
        .sidebar-collapsed #sidebar .nav-text,
        .sidebar-collapsed #sidebar .avatar-container h6,
        .sidebar-collapsed #sidebar .avatar-container small { display: none; }
        .sidebar-collapsed #content { margin-left: 70px; width: calc(100% - 70px); }
        .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 4px;
            transition: all 0.2s;
        }
        .sidebar-menu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        .sidebar-menu .nav-link.active {
            background-color: #fff !important;
            color: #dc3545 !important;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        .sidebar-menu .nav-link.active i { color: #dc3545 !important; }
        .badge-expiring { background-color: #ffc107; color: #212529; }
    </style>
    @stack('styles')
</head>
<body>
<div class="wrapper">
    <!-- SIDEBAR -->
    <nav id="sidebar" class="p-2 p-md-3">
        <!-- Toggle button -->
        <div class="d-flex align-items-center justify-content-between mb-3 px-2">
            <span class="fs-4 cursor-pointer text-white" style="cursor: pointer;" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </span>
            <span class="nav-text fw-bold text-white small">MENU QUẢN TRỊ</span>
        </div>

        <!-- User Profile Header -->
        <div class="avatar-container text-center my-2 d-flex flex-column align-items-center">
            <img src="{{ auth()->user()->avatar_url }}" alt="Avatar"
                 class="rounded-circle border border-2 border-white shadow-sm"
                 style="width: 55px; height: 55px; object-fit: cover;">
            <div class="nav-text mt-2 w-100">
                <a href="{{ route('profile') }}" class="text-white text-decoration-none">
                    <h6 class="mb-0 fw-bold text-truncate">{{ auth()->user()->full_name ?: auth()->user()->username }}</h6>
                </a>
                <small class="text-white-50 text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 1px;">
                    {{ auth()->user()->role }}
                </small>
            </div>
        </div>

        <hr class="text-white-50 my-2">

        <!-- Navigation Menu -->
        <ul class="nav nav-pills flex-column mb-auto sidebar-menu">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') || request()->routeIs('home') ? 'active' : '' }}">
                    <i class="fas fa-chart-line me-2" style="width: 20px;"></i>
                    <span class="nav-text">Tổng quan</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.index') && !request()->has('favorite') && !request()->has('expiring_soon') ? 'active' : '' }}">
                    <i class="fas fa-file-alt me-2" style="width: 20px;"></i>
                    <span class="nav-text">Danh sách văn bản</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('documents.index', ['expiring_soon' => 30]) }}" class="nav-link d-flex align-items-center justify-content-between {{ request()->get('expiring_soon') ? 'active' : '' }}">
                    <div>
                        <i class="fas fa-bell me-2" style="width: 20px;"></i>
                        <span class="nav-text">Cảnh báo hết hạn</span>
                    </div>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('documents.index', ['favorite' => 1]) }}" class="nav-link {{ request()->get('favorite') ? 'active' : '' }}">
                    <i class="fas fa-star me-2" style="width: 20px;"></i>
                    <span class="nav-text">Văn bản quan trọng</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                    <i class="fas fa-chart-pie me-2" style="width: 20px;"></i>
                    <span class="nav-text">Báo cáo thống kê</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reports.print-register') }}" class="nav-link {{ request()->routeIs('reports.print-register') ? 'active' : '' }}">
                    <i class="fas fa-book me-2" style="width: 20px;"></i>
                    <span class="nav-text">Sổ theo dõi văn bản</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reports.mlns') }}" class="nav-link {{ request()->routeIs('reports.mlns') ? 'active' : '' }}">
                    <i class="fas fa-coins me-2" style="width: 20px;"></i>
                    <span class="nav-text">Mục lục ngân sách</span>
                </a>
            </li>

            @if(auth()->user()->isEditor())
                <li class="nav-item">
                    <a href="{{ route('documents.create') }}" class="nav-link {{ request()->routeIs('documents.create') ? 'active' : '' }}">
                        <i class="fas fa-plus-circle me-2" style="width: 20px;"></i>
                        <span class="nav-text">Thêm văn bản mới</span>
                    </a>
                </li>
            @endif

            <hr class="text-white-50 my-2">

            <!-- Quản trị danh mục & Admin -->
            @if(auth()->user()->isAdmin())
                <li class="nav-item">
                    <a href="{{ route('categories.types') }}" class="nav-link {{ request()->routeIs('categories.types') ? 'active' : '' }}">
                        <i class="fas fa-tags me-2" style="width: 20px;"></i>
                        <span class="nav-text">Loại văn bản</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('categories.authorities') }}" class="nav-link {{ request()->routeIs('categories.authorities') ? 'active' : '' }}">
                        <i class="fas fa-building me-2" style="width: 20px;"></i>
                        <span class="nav-text">Cơ quan ban hành</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('categories.merge') }}" class="nav-link {{ request()->routeIs('categories.merge') ? 'active' : '' }}">
                        <i class="fas fa-object-group me-2" style="width: 20px;"></i>
                        <span class="nav-text">Gộp danh mục trùng</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('reports.import') }}" class="nav-link {{ request()->routeIs('reports.import') ? 'active' : '' }}">
                        <i class="fas fa-file-import me-2" style="width: 20px;"></i>
                        <span class="nav-text">Nhập dữ liệu (CSV)</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('documents.trash') }}" class="nav-link {{ request()->routeIs('documents.trash') ? 'active' : '' }}">
                        <i class="fas fa-trash-alt me-2" style="width: 20px;"></i>
                        <span class="nav-text">Thùng rác</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="fas fa-users me-2" style="width: 20px;"></i>
                        <span class="nav-text">Quản lý người dùng</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('system.backups') }}" class="nav-link {{ request()->routeIs('system.backups') ? 'active' : '' }}">
                        <i class="fas fa-database me-2" style="width: 20px;"></i>
                        <span class="nav-text">Sao lưu CSDL</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('system.logs') }}" class="nav-link {{ request()->routeIs('system.logs') ? 'active' : '' }}">
                        <i class="fas fa-clipboard-list me-2" style="width: 20px;"></i>
                        <span class="nav-text">Nhật ký hệ thống</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('system.settings') }}" class="nav-link {{ request()->routeIs('system.settings') ? 'active' : '' }}">
                        <i class="fas fa-sliders-h me-2" style="width: 20px;"></i>
                        <span class="nav-text">Cấu hình hệ thống</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('system.info') }}" class="nav-link {{ request()->routeIs('system.info') ? 'active' : '' }}">
                        <i class="fas fa-server me-2" style="width: 20px;"></i>
                        <span class="nav-text">Thông tin máy chủ</span>
                    </a>
                </li>

                <hr class="text-white-50 my-2">
            @endif

            <li class="nav-item">
                <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}">
                    <i class="fas fa-id-card me-2" style="width: 20px;"></i>
                    <span class="nav-text">Hồ sơ cá nhân</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('change-password') }}" class="nav-link {{ request()->routeIs('change-password') ? 'active' : '' }}">
                    <i class="fas fa-key me-2" style="width: 20px;"></i>
                    <span class="nav-text">Đổi mật khẩu</span>
                </a>
            </li>

            <li class="nav-item mt-2">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="nav-link text-white bg-transparent border-0 w-100 text-start">
                        <i class="fas fa-sign-out-alt me-2" style="width: 20px;"></i>
                        <span class="nav-text">Đăng xuất</span>
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <!-- CONTENT WRAPPER -->
    <div id="content" class="flex-grow-1 d-flex flex-column">
        <!-- TOP NAVBAR -->
        <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-2 sticky-top">
            <div class="container-fluid p-0">
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-danger me-3 d-lg-none" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="fw-bold text-danger fs-5 d-none d-sm-inline">
                        <i class="fas fa-file-contract me-2"></i>{{ get_setting('site_title', 'HỆ THỐNG QUẢN LÝ VĂN BẢN') }}
                    </span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small d-none d-md-inline">
                        <i class="far fa-clock me-1"></i>{{ now()->format('d/m/Y') }}
                    </span>
                    <a href="{{ route('documents.create') }}" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="fas fa-plus me-1"></i>Thêm văn bản
                    </a>
                    <a href="{{ route('profile') }}" class="d-flex align-items-center text-decoration-none">
                        <img src="{{ auth()->user()->avatar_url }}" alt="Avatar"
                             class="rounded-circle border" style="width: 35px; height: 35px; object-fit: cover;">
                    </a>
                </div>
            </div>
        </header>

        <!-- MAIN BODY -->
        <main class="container-fluid px-4 py-4 flex-grow-1">
            <!-- FLASH MESSAGES -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-lg me-2"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
                    <div>{{ session('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                    <div class="fw-bold mb-1"><i class="fas fa-exclamation-circle me-1"></i>Vui lòng kiểm tra lại:</div>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>

        <!-- FOOTER -->
        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto">
            {{ get_setting('footer_copyright', '© 2026 Bản quyền thuộc phongvanvu') }} - {{ get_setting('parent_agency', 'Bệnh viện 19-8') }}
        </footer>
    </div>
</div>

<!-- Bootstrap Bundle JS (Local) -->
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

<script>
    function toggleSidebar() {
        document.documentElement.classList.toggle("sidebar-collapsed");
        var isCollapsed = document.documentElement.classList.contains("sidebar-collapsed");
        localStorage.setItem("sidebarState", isCollapsed ? "collapsed" : "expanded");
    }

    // Thiết lập CSRF cho toàn bộ request AJAX
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
</script>
@stack('scripts')
</body>
</html>
