<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Khôi phục session từ cookie nếu cần
if (!isset($_SESSION['user_id']) && isset($_COOKIE['username'], $_COOKIE['user_id'])) {
    $_SESSION['user_name'] = $_COOKIE['username'];
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
}

// Kiểm tra quyền: Admin hoặc người có quyền xem báo cáo
if (!isset($_SESSION['user_id']) || (!has_permission('documents.view') && ($_SESSION['role'] ?? '') !== 'admin')) {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang này.";
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// 1. Thống kê tổng quan nhân sự
$total_users = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);
$active_today = (int)($conn->query("SELECT COUNT(DISTINCT user_id) AS c FROM logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0);

// 2. Bảng xếp hạng năng suất người dùng (dựa vào số lượng log hoặc văn bản tạo ra)
$leaderboard = $conn->query("SELECT u.id, u.username, u.full_name, u.role, u.avatar, 
                                    COUNT(l.id) AS total_actions,
                                    MAX(l.created_at) AS last_active
                             FROM users u 
                             LEFT JOIN logs l ON u.id = l.user_id 
                             GROUP BY u.id 
                             ORDER BY total_actions DESC")->fetch_all(MYSQLI_ASSOC);

$title = "Phân tích Năng suất & Hoạt động Người dùng";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-chart-line text-danger me-2"></i>Phân Tích Hoạt Động & Năng Suất Nhân Sự
                        </h4>
                        <div class="text-muted small">Đánh giá mức độ đóng góp, tần suất thao tác và hiệu suất làm việc của từng tài khoản trên hệ thống</div>
                    </div>
                </div>

                <!-- CARDS THỐNG KÊ TỔNG QUAN -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-primary bg-primary bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Tổng số tài khoản nhân sự</div>
                                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($total_users) ?> tài khoản</h3>
                                </div>
                                <i class="fas fa-users text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-success bg-success bg-opacity-10 h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold">Nhân sự hoạt động trong ngày</div>
                                    <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($active_today) ?> người</h3>
                                </div>
                                <i class="fas fa-user-check text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BẢNG XẾP HẠNG NĂNG SUẤT -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-trophy text-warning me-2"></i>Bảng xếp hạng năng suất thao tác hệ thống
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 60px;" class="text-center">Hạng</th>
                                        <th style="width: 280px;" class="ps-3">Tài khoản nhân sự</th>
                                        <th style="width: 140px;" class="text-center">Vai trò</th>
                                        <th style="width: 160px;" class="text-center">Tổng số thao tác</th>
                                        <th>Hoạt động gần nhất</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($leaderboard) > 0): ?>
                                        <?php 
                                        $rank = 1;
                                        foreach ($leaderboard as $row): 
                                            $u_role = $row['role'] ?? 'viewer';
                                            $role_badge = ($u_role === 'admin') ? 'bg-danger' : (($u_role === 'editor') ? 'bg-primary' : 'bg-secondary');
                                            $avatar = !empty($row['avatar']) ? $row['avatar'] : 'uploads/avatars/default.jpg';

                                            $rank_icon = $rank;
                                            if ($rank === 1) $rank_icon = '<i class="fas fa-medal text-warning fa-lg"></i> 1';
                                            elseif ($rank === 2) $rank_icon = '<i class="fas fa-medal text-secondary fa-lg"></i> 2';
                                            elseif ($rank === 3) $rank_icon = '<i class="fas fa-medal text-danger fa-lg" style="color: #cd7f32 !important;"></i> 3';
                                        ?>
                                            <tr>
                                                <td class="text-center fw-bold fs-6"><?= $rank_icon ?></td>
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= h($avatar) ?>" alt="Avatar" class="rounded-circle me-2 border" style="width: 36px; height: 36px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-bold text-dark mb-0"><?= h($row['full_name'] ?: $row['username']) ?></div>
                                                            <div class="text-muted" style="font-size: 0.75rem;">@<?= h($row['username']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $role_badge ?> text-uppercase" style="font-size: 0.65rem;"><?= h($u_role) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border px-2 py-1 fw-semibold font-monospace">
                                                        <?= number_format($row['total_actions']) ?> hành động
                                                    </span>
                                                </td>
                                                <td class="text-muted">
                                                    <?php if (!empty($row['last_active'])): ?>
                                                        <i class="far fa-clock me-1 text-secondary"></i><?= date('d/m/Y H:i:s', strtotime($row['last_active'])) ?>
                                                    <?php else: ?>
                                                        <span class="fst-italic text-muted">Chưa có hoạt động</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php $rank++; endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-chart-bar fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Chưa có dữ liệu thống kê nhân sự.</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>