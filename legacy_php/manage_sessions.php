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

// Chỉ Admin mới được truy cập trang này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang quản lý phiên làm việc.";
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Đơn giản hóa User-Agent
function parseBrowser($agent) {
    if (stripos($agent, 'Chrome') !== false && stripos($agent, 'Edg') === false) return '<i class="fab fa-chrome text-warning me-1"></i>Chrome';
    if (stripos($agent, 'Edg') !== false) return '<i class="fab fa-edge text-primary me-1"></i>Edge';
    if (stripos($agent, 'Firefox') !== false) return '<i class="fab fa-firefox-browser text-danger me-1"></i>Firefox';
    if (stripos($agent, 'Safari') !== false && stripos($agent, 'Chrome') === false) return '<i class="fab fa-safari text-info me-1"></i>Safari';
    return '<i class="fas fa-globe text-secondary me-1"></i>Trình duyệt khác';
}

$current_token = session_id();

// Lấy danh sách phiên đang lưu
$sql = "SELECT us.id, us.user_id, us.session_token, us.ip_address, us.user_agent, us.last_activity, us.created_at, 
               u.username, u.full_name, u.role, u.avatar 
        FROM user_sessions us 
        JOIN users u ON us.user_id = u.id 
        ORDER BY us.last_activity DESC";
$sessions = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$total_sessions = count($sessions);
$online_threshold = 15 * 60; // 15 phút được coi là đang Online

$title = "Quản lý Phiên đăng nhập & Người dùng trực tuyến";
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
                            <i class="fas fa-user-clock text-danger me-2"></i>Phiên Đăng Nhập & Người Dùng Trực Tuyến
                        </h4>
                        <div class="text-muted small">Giám sát thiết bị, địa chỉ IP và cưỡng chế thu hồi phiên làm việc khi phát hiện dấu hiệu bất thường</div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm me-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Làm mới
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm shadow-sm" id="btnCleanupInactive">
                            <i class="fas fa-broom me-1"></i>Dọn dẹp phiên > 24h
                        </button>
                    </div>
                </div>

                <!-- BẢNG DANH SÁCH CÁC PHIÊN -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-network-wired text-secondary me-2"></i>Danh sách các phiên kết nối đang mở
                        </h6>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                            <?= $total_sessions ?> phiên đang ghi nhận
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">STT</th>
                                        <th style="width: 240px;">Tài khoản / Người dùng</th>
                                        <th style="width: 140px;">Địa chỉ IP</th>
                                        <th>Thiết bị & Trình duyệt</th>
                                        <th style="width: 170px;" class="text-center">Hoạt động gần nhất</th>
                                        <th style="width: 120px;" class="text-center">Trạng thái</th>
                                        <th style="width: 130px;" class="text-center">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($total_sessions > 0): ?>
                                        <?php 
                                        $i = 1;
                                        $now = time();
                                        foreach ($sessions as $s): 
                                            $is_current = ($s['session_token'] === $current_token);
                                            $diff = $now - strtotime($s['last_activity']);
                                            $is_online = ($diff <= $online_threshold);
                                            $avatar = !empty($s['avatar']) ? $s['avatar'] : 'uploads/avatars/default.jpg';
                                        ?>
                                            <tr class="<?= $is_current ? 'table-warning bg-opacity-25' : '' ?>">
                                                <td class="text-center text-muted fw-semibold"><?= $i++ ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= h($avatar) ?>" alt="Avatar" class="rounded-circle me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-bold text-dark mb-0">
                                                                <?= h($s['full_name'] ?: $s['username']) ?>
                                                                <?php if ($is_current): ?>
                                                                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Phiên này</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-muted small" style="font-size: 0.75rem;">@<?= h($s['username']) ?> (<?= strtoupper($s['role']) ?>)</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="font-monospace text-dark">
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i><?= h($s['ip_address'] ?: '127.0.0.1') ?>
                                                </td>
                                                <td>
                                                    <div class="text-dark fw-medium"><?= parseBrowser($s['user_agent']) ?></div>
                                                    <div class="text-muted small text-truncate" style="max-width: 260px; font-size: 0.75rem;" title="<?= h($s['user_agent']) ?>">
                                                        <?= h($s['user_agent']) ?>
                                                    </div>
                                                </td>
                                                <td class="text-center text-muted">
                                                    <i class="far fa-clock me-1 text-secondary"></i><?= date('d/m/Y H:i:s', strtotime($s['last_activity'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_online): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                            <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>Đang Online
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                                            Ngoại tuyến
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!$is_current): ?>
                                                        <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 btn-terminate-session" data-id="<?= (int)$s['id'] ?>" data-user="<?= h($s['username']) ?>" title="Đăng xuất tài khoản này">
                                                            <i class="fas fa-sign-out-alt me-1"></i>Hủy phiên
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small fst-italic">Đang sử dụng</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-user-clock fa-3x mb-2 text-light"></i>
                                                <div class="fw-semibold">Chưa có phiên kết nối nào được ghi nhận.</div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Cưỡng chế hủy phiên
    document.querySelectorAll('.btn-terminate-session').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-user');

            const executeTerminate = () => {
                const formData = new FormData();
                formData.append('action', 'terminate_single');
                formData.append('id', id);

                fetch('ajax_session_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert(data.message || 'Lỗi');
                    }
                })
                .catch(err => alert('Lỗi kết nối máy chủ'));
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hủy phiên làm việc?',
                    html: `Tài khoản <strong>${username}</strong> sẽ bị buộc đăng xuất ngay lập tức khỏi thiết bị này!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Đăng xuất ngay',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeTerminate(); });
            } else {
                if (confirm(`Bạn có chắc muốn cưỡng chế đăng xuất tài khoản ${username}?`)) executeTerminate();
            }
        });
    });

    // 2. Dọn dẹp phiên không hoạt động > 24h
    const btnCleanup = document.getElementById('btnCleanupInactive');
    if (btnCleanup) {
        btnCleanup.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'cleanup_inactive');

            fetch('ajax_session_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Hoàn tất', text: data.message }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                }
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>