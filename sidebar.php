<?php 
$current_page = basename($_SERVER['PHP_SELF']);
$current_filter = $_GET['personal_filter'] ?? '';

if (!function_exists('nav_active')) {
    function nav_active($page, $cur_page, $filter = null, $cur_filter = null) {
        $is_active = ($page === $cur_page) && ($filter === null || $filter === $cur_filter);
        return [
            'class' => $is_active ? 'active bg-white text-danger fw-bold shadow-sm rounded-3' : 'text-white',
            'icon'  => $is_active ? 'text-danger' : 'text-white-50'
        ];
    }
}

$avatar_src = !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : 'uploads/avatars/default.jpg';
$user_name  = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Người dùng';
$user_role  = $_SESSION['role'] ?? 'viewer';

// Tính toán số lượng văn bản sắp hết hạn trong 30 ngày
$expiring_count = 0;
if (isset($conn)) {
    $sql_exp = "SELECT COUNT(*) as cnt FROM documents 
                WHERE deleted_at IS NULL 
                AND expired_date IS NOT NULL 
                AND expired_date >= CURDATE() 
                AND expired_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                AND status != 'Hết hiệu lực'";
    $res_exp = $conn->query($sql_exp);
    if ($res_exp) {
        $expiring_count = $res_exp->fetch_assoc()['cnt'] ?? 0;
    }
}
?>

<div class="d-flex flex-column text-white min-vh-100 p-2 p-md-3">
    
    <!-- Toggle Button -->
    <div class="mb-3 text-center text-md-start">
        <span class="hamburger fs-4 cursor-pointer" style="cursor: pointer;" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </span>
    </div>

    <!-- User Profile Header -->
    <div class="avatar-container text-center my-2 d-flex flex-column align-items-center"> 
        <img src="<?= htmlspecialchars($avatar_src, ENT_QUOTES, 'UTF-8') ?>" 
             alt="Avatar" 
             class="avatar rounded-circle border border-2 border-white shadow-sm" 
             style="width: 60px; height: 60px; object-fit: cover;">

        <div class="nav-text mt-2 w-100">
            <a href="profile.php" class="text-white text-decoration-none" data-bs-toggle="tooltip" title="Xem hồ sơ">
                <h6 class="mb-0 fw-bold text-truncate"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></h6>
            </a>
            <small class="text-white-50 text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 1px;">
                <?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8') ?>
            </small>
        </div>
    </div>
    
    <hr class="text-white-50 w-100 my-3">

    <!-- Navigation Links -->
    <ul class="nav nav-pills flex-column mb-auto gap-2 w-100 sidebar-menu">

        <?php 
        $items = [
            ['page' => 'dashboard.php', 'filter' => '', 'icon' => 'fas fa-chart-line', 'label' => 'Tổng quan'],
            ['page' => 'expiring_documents.php', 'filter' => '', 'icon' => 'fas fa-bell', 'label' => 'Cảnh báo hết hạn', 'badge' => $expiring_count],
            ['page' => 'documents.php', 'filter' => '', 'icon' => 'fas fa-list-alt', 'label' => 'Danh sách văn bản'],
            ['page' => 'document_reports.php', 'filter' => '', 'icon' => 'fas fa-chart-pie', 'label' => 'Báo cáo thống kê'],
            ['page' => 'print_register.php', 'filter' => '', 'icon' => 'fas fa-book', 'label' => 'Sổ theo dõi văn bản'],
            ['page' => 'documents.php', 'filter' => 'favorite', 'icon' => 'fas fa-star', 'label' => 'Văn bản quan trọng'],
            ['page' => 'topics.php', 'filter' => '', 'icon' => 'fas fa-layer-group', 'label' => 'Chủ đề cá nhân'],
            ['page' => 'personal_stats.php', 'filter' => '', 'icon' => 'fas fa-chart-pie', 'label' => 'Thống kê cá nhân'],
            ['page' => 'manage_backups.php', 'icon' => 'fas fa-database', 'label' => 'Sao lưu & Phục hồi'],
        ];

        foreach ($items as $item): 
            $filter = $item['filter'] ?? null;
            $act = nav_active($item['page'], $current_page, $filter, $current_filter);
            $url = $item['page'] . ($filter !== '' && $filter !== null ? "?personal_filter=$filter" : '');
        ?>
            <li class="nav-item">
                <a href="<?= $url ?>" class="nav-link text-nowrap d-flex align-items-center justify-content-between <?= $act['class'] ?>">
                    <div class="d-flex align-items-center">
                        <i class="<?= $item['icon'] ?> fs-5 <?= $act['icon'] ?>" style="width: 25px;"></i>
                        <span class="nav-text ms-2"><?= $item['label'] ?></span>
                    </div>
                    <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill fw-bold shadow-sm nav-text"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>

        <!-- Role: Admin / Editor -->
        <?php if (in_array($user_role, ['admin', 'editor'], true)): 
            $act = nav_active('add_document.php', $current_page);
        ?>
            <li class="nav-item">
                <a href="add_document.php" class="nav-link text-nowrap d-flex align-items-center <?= $act['class'] ?>">
                    <i class="fas fa-plus-circle fs-5 <?= $act['icon'] ?>" style="width: 25px;"></i>
                    <span class="nav-text ms-2">Văn bản mới</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Role: Admin Only -->
        <?php if ($user_role === 'admin'): 
            $admin_items = [
                ['page' => 'manage_users.php', 'icon' => 'fas fa-users', 'label' => 'Người dùng'],
                ['page' => 'manage_document_types.php', 'icon' => 'fas fa-tags', 'label' => 'Loại văn bản'],
                ['page' => 'manage_issuing_authorities.php', 'icon' => 'fas fa-building', 'label' => 'Nơi ban hành'],
                ['page' => 'manage_contacts.php', 'icon' => 'fas fa-envelope', 'label' => 'Liên hệ'],
                ['page' => 'audit_documents.php', 'icon' => 'fas fa-search-plus', 'label' => 'Rà soát dữ liệu'],
                ['page' => 'merge_categories.php', 'icon' => 'fas fa-object-group', 'label' => 'Gộp danh mục'],
                ['page' => 'manage_categories.php', 'icon' => 'fas fa-tags', 'label' => 'Quản lý danh mục'],
                ['page' => 'manage_permissions.php', 'icon' => 'fas fa-user-shield', 'label' => 'Phân quyền nâng cao'],
                ['page' => 'optimize_database.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Tối ưu hóa CSDL'],
                ['page' => 'manage_logs.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'Nhật ký hệ thống'],
                ['page' => 'system_info.php', 'icon' => 'fas fa-server', 'label' => 'Thông tin máy chủ'],
                ['page' => 'system_settings.php', 'icon' => 'fas fa-sliders-h', 'label' => 'Cấu hình hệ thống'],
                ['page' => 'manage_trash.php', 'icon' => 'fas fa-trash-alt', 'label' => 'Thùng rác'],
                ['page' => 'import_documents.php', 'icon' => 'fas fa-file-import', 'label' => 'Nhập dữ liệu (CSV)'],
                ['page' => 'security_check.php', 'icon' => 'fas fa-shield-alt', 'label' => 'Kiểm tra an ninh'],
                ['page' => 'manage_sessions.php', 'icon' => 'fas fa-user-clock', 'label' => 'Phiên đăng nhập'],
                ['page' => 'export_documents.php', 'icon' => 'fas fa-file-archive', 'label' => 'Đóng gói & Xuất hồ sơ'],
                ['page' => 'manage_relations.php', 'icon' => 'fas fa-project-diagram', 'label' => 'Liên kết hiệu lực'],
                ['page' => 'manage_attachments.php', 'icon' => 'fas fa-paperclip', 'label' => 'Quản lý phụ lục'],
                ['page' => 'manage_versions.php', 'icon' => 'fas fa-history', 'label' => 'Quản lý phiên bản'],
                ['page' => 'user_analytics.php', 'icon' => 'fas fa-chart-line', 'label' => 'Thống kê nhân sự'],
                ['page' => 'system_notifications.php', 'icon' => 'fas fa-bell', 'label' => 'Cấu hình thông báo'],
                ['page' => 'manage_duplicates.php', 'icon' => 'fas fa-clone', 'label' => 'Rà soát trùng lặp'],
            ];

            foreach ($admin_items as $item):
                $act = nav_active($item['page'], $current_page);
        ?>
            <li class="nav-item">
                <a href="<?= $item['page'] ?>" class="nav-link text-nowrap d-flex align-items-center <?= $act['class'] ?>">
                    <i class="<?= $item['icon'] ?> fs-5 <?= $act['icon'] ?>" style="width: 25px;"></i>
                    <span class="nav-text ms-2"><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; endif; ?>
        
        <hr class="text-white-50 w-100 my-2">

        <!-- User Options -->
        <?php 
        $user_items = [
            ['page' => 'profile.php', 'icon' => 'fas fa-id-card', 'label' => 'Hồ sơ cá nhân'],
            ['page' => 'change_password.php', 'icon' => 'fas fa-key', 'label' => 'Đổi mật khẩu'],
            ['page' => 'huong_dan_su_dung.php', 'icon' => 'fas fa-book', 'label' => 'Hướng dẫn'],
        ];

        foreach ($user_items as $item):
            $act = nav_active($item['page'], $current_page);
        ?>
            <li class="nav-item">
                <a href="<?= $item['page'] ?>" class="nav-link text-nowrap d-flex align-items-center <?= $act['class'] ?>">
                    <i class="<?= $item['icon'] ?> fs-5 <?= $act['icon'] ?>" style="width: 25px;"></i>
                    <span class="nav-text ms-2"><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Logout Section -->
    <div class="mt-auto mb-2 w-100">
        <a class="nav-link text-nowrap text-white fw-bold bg-dark bg-opacity-25 rounded-3 logout-btn d-flex align-items-center py-2 px-3" href="logout.php">
            <i class="fas fa-sign-out-alt fs-5 text-white-50" style="width: 25px;"></i>
            <span class="nav-text ms-2">Đăng xuất</span>
        </a>
    </div>
</div>

<script>
function toggleSidebar() {
    var html = document.documentElement;
    var sidebar = document.getElementById("sidebar");
    var content = document.getElementById("content");
    
    if (sidebar) sidebar.classList.remove("collapsed");
    if (content) content.classList.remove("collapsed");

    html.classList.toggle("sidebar-collapsed");
    localStorage.setItem("sidebarState", html.classList.contains("sidebar-collapsed") ? "collapsed" : "expanded");
}

document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Đăng xuất?',
                    text: "Bạn có chắc chắn muốn thoát khỏi hệ thống?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Có, đăng xuất!',
                    cancelButtonText: 'Hủy'
                }).then((res) => { if (res.isConfirmed) window.location.href = 'logout.php'; });
            } else {
                if (confirm('Bạn có chắc chắn muốn đăng xuất?')) window.location.href = 'logout.php';
            }
        });
    }
});
</script>