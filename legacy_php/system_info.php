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

// Chỉ Quản trị viên (Admin) mới có quyền xem thông tin hệ thống
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = "Bạn không có quyền truy cập trang này.";
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Hàm tính dung lượng và đếm số lượng tệp trong 1 thư mục
function getDirInfo($dir) {
    $size = 0;
    $count = 0;
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $count++;
            }
        }
    }
    return ['size' => $size, 'count' => $count];
}

// 1. DUNG LƯỢNG Ổ CỨNG MÁY CHỦ
$disk_total = @disk_total_space(__DIR__) ?: 1;
$disk_free = @disk_free_space(__DIR__) ?: 0;
$disk_used = max(0, $disk_total - $disk_free);
$disk_used_percent = round(($disk_used / $disk_total) * 100, 1);

// 2. PHÂN BỔ DUNG LƯỢNG CÁC THƯ MỤC LƯU TRỮ
$uploads_info = getDirInfo(__DIR__ . '/uploads');
$docs_info    = getDirInfo(__DIR__ . '/uploads/documents');
$avatars_info = getDirInfo(__DIR__ . '/uploads/avatars');
$backups_info = getDirInfo(__DIR__ . '/backups');

// 3. THÔNG SỐ CSDL MYSQL
$db_version = $conn->server_info;
$db_size_res = $conn->query("SELECT SUM(data_length + index_length) AS db_size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$db_size_row = $db_size_res ? $db_size_res->fetch_assoc() : null;
$db_size = (int)($db_size_row['db_size'] ?? 0);

// 4. KIỂM TRA CÁC EXTENSION QUAN TRỌNG
$required_extensions = [
    'mysqli'   => ['name' => 'MySQLi Database Driver', 'desc' => 'Kết nối và truy vấn CSDL'],
    'mbstring' => ['name' => 'Multibyte String', 'desc' => 'Xử lý tiếng Việt có dấu, trích yếu văn bản'],
    'zip'      => ['name' => 'ZipArchive Extension', 'desc' => 'Nén và tải hàng loạt tệp văn bản'],
    'gd'       => ['name' => 'GD Graphics Library', 'desc' => 'Xử lý ảnh đại diện và đóng dấu bản quyền'],
    'curl'     => ['name' => 'cURL Extension', 'desc' => 'Hỗ trợ kết nối API và dịch vụ ngoài'],
    'openssl'  => ['name' => 'OpenSSL Module', 'desc' => 'Mã hóa và bảo mật dữ liệu']
];

$title = "Thông tin & Tình trạng hệ thống";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề trang -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-server text-danger me-2"></i>Giám sát Máy chủ & Dung lượng
                        </h4>
                        <div class="text-muted small">Kiểm tra thông số môi trường PHP, MySQL, tài nguyên ổ cứng và các thư mục lưu trữ số hóa</div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Làm mới dữ liệu
                    </button>
                </div>

                <!-- 1. TÌNH TRẠNG Ổ ĐĨA MÁY CHỦ -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-hdd text-primary me-2"></i>Tài nguyên Ổ cứng Máy chủ (Server Storage)
                        </h6>
                        <span class="badge <?= ($disk_used_percent > 85) ? 'bg-danger' : (($disk_used_percent > 65) ? 'bg-warning text-dark' : 'bg-success') ?> px-2 py-1">
                            <?= ($disk_used_percent > 85) ? 'Sắp đầy' : 'An toàn' ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-4">
                                <div class="text-muted small">Đã sử dụng:</div>
                                <h4 class="fw-bold text-dark mb-0"><?= formatBytes($disk_used) ?> <span class="fs-6 text-muted fw-normal">/ <?= formatBytes($disk_total) ?></span></h4>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Còn trống:</div>
                                <h4 class="fw-bold text-success mb-0"><?= formatBytes($disk_free) ?></h4>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="text-muted small">Tỷ lệ chiếm dụng:</div>
                                <h4 class="fw-bold text-primary mb-0"><?= $disk_used_percent ?>%</h4>
                            </div>
                        </div>

                        <div class="progress" style="height: 12px; border-radius: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated <?= ($disk_used_percent > 85) ? 'bg-danger' : (($disk_used_percent > 65) ? 'bg-warning' : 'bg-primary') ?>" 
                                 role="progressbar" 
                                 style="width: <?= $disk_used_percent ?>%" 
                                 aria-valuenow="<?= $disk_used_percent ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. CHI TIẾT DUNG LƯỢNG LƯU TRỮ HỆ THỐNG -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-danger bg-danger bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Tệp văn bản đính kèm</div>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= formatBytes($docs_info['size']) ?></h4>
                            <div class="small text-muted mt-1"><?= number_format($docs_info['count']) ?> tệp lưu trữ</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-warning bg-warning bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Bản sao lưu CSDL (.sql)</div>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= formatBytes($backups_info['size']) ?></h4>
                            <div class="small text-muted mt-1"><?= number_format($backups_info['count']) ?> bản sao lưu</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-info bg-info bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Ảnh đại diện (Avatars)</div>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= formatBytes($avatars_info['size']) ?></h4>
                            <div class="small text-muted mt-1"><?= number_format($avatars_info['count']) ?> ảnh</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-success bg-success bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Dung lượng Database MySQL</div>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= formatBytes($db_size) ?></h4>
                            <div class="small text-muted mt-1">Dữ liệu & Index</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- 3. BẢNG THÔNG SỐ CẤU HÌNH PHP & MÁY CHỦ -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fab fa-php text-primary me-2 fa-lg"></i>Môi trường PHP & Web Server
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <tbody>
                                            <tr>
                                                <td class="ps-3 text-muted" style="width: 200px;">Phiên bản PHP</td>
                                                <td class="fw-bold text-dark font-monospace"><?= PHP_VERSION ?></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Web Server Software</td>
                                                <td class="fw-medium text-dark font-monospace"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Phiên bản MySQL</td>
                                                <td class="fw-bold text-dark font-monospace"><?= h($db_version) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Giới hạn tải lên (Upload Max)</td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?= ini_get('upload_max_filesize') ?></span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Giới hạn POST (Post Max)</td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?= ini_get('post_max_size') ?></span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Bộ nhớ cấp phát (Memory Limit)</td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?= ini_get('memory_limit') ?></span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Thời gian thực thi tối đa</td>
                                                <td><span class="badge bg-light text-dark border font-monospace"><?= ini_get('max_execution_time') ?> giây</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 text-muted">Múi giờ hệ thống (Timezone)</td>
                                                <td class="text-dark"><?= date_default_timezone_get() ?> (<?= date('d/m/Y H:i:s') ?>)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. KIỂM TRA EXTENSION / MODULES -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-cubes text-danger me-2"></i>Trạng thái Modules / Extensions
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Tên Module</th>
                                                <th>Mô tả chức năng</th>
                                                <th class="text-center" style="width: 100px;">Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($required_extensions as $ext_key => $ext_val): 
                                                $is_loaded = extension_loaded($ext_key);
                                            ?>
                                                <tr>
                                                    <td class="ps-3 font-monospace fw-bold <?= $is_loaded ? 'text-dark' : 'text-danger' ?>">
                                                        <?= h($ext_key) ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark"><?= h($ext_val['name']) ?></div>
                                                        <div class="text-muted small" style="font-size: 0.75rem;"><?= h($ext_val['desc']) ?></div>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($is_loaded): ?>
                                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                                                <i class="fas fa-check me-1"></i>Sẵn sàng
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger px-2 py-1">
                                                                <i class="fas fa-times me-1"></i>Chưa bật
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>