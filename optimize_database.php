<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên mới được vào trang này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: documents.php');
    exit;
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function formatBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Lấy thông tin thống kê trạng thái tất cả các bảng (TABLE STATUS)
$tables_info = [];
$total_data_size = 0;
$total_index_size = 0;
$total_free_size = 0;
$total_rows = 0;

$res = $conn->query("SHOW TABLE STATUS");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $data_len = (int)($row['Data_length'] ?? 0);
        $index_len = (int)($row['Index_length'] ?? 0);
        $data_free = (int)($row['Data_free'] ?? 0);
        $rows = (int)($row['Rows'] ?? 0);

        $total_data_size += $data_len;
        $total_index_size += $index_len;
        $total_free_size += $data_free;
        $total_rows += $rows;

        $tables_info[] = [
            'name'        => $row['Name'],
            'engine'      => $row['Engine'] ?? 'InnoDB',
            'rows'        => $rows,
            'data_size'   => $data_len,
            'index_size'  => $index_len,
            'free_size'   => $data_free,
            'total_size'  => $data_len + $index_len
        ];
    }
}

$title = "Tối ưu hóa Cơ sở dữ liệu";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Nút bấm tối ưu tất cả -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-tachometer-alt text-danger me-2"></i>Bảo trì & Tối ưu hóa CSDL
                        </h4>
                        <div class="text-muted small">Dọn dẹp phân mảnh ổ đĩa, tái tạo chỉ mục Index giúp tăng tốc truy vấn tìm kiếm văn bản</div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm" id="btnOptimizeAll">
                        <i class="fas fa-bolt me-1"></i>Tối ưu hóa toàn bộ bảng ngay
                    </button>
                </div>

                <!-- CÁC THẺ THỐNG KÊ TỔNG QUAN -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-primary bg-primary bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Tổng số bảng CSDL</div>
                            <h3 class="fw-bold text-primary mb-0 mt-1"><?= count($tables_info) ?> bảng</h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-success bg-success bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Tổng số dòng dữ liệu</div>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($total_rows) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-info bg-info bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Tổng dung lượng CSDL</div>
                            <h3 class="fw-bold text-info mb-0 mt-1"><?= formatBytes($total_data_size + $total_index_size) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm rounded-3 p-3 border-start border-4 border-warning bg-warning bg-opacity-10 h-100">
                            <div class="text-muted small fw-semibold">Dung lượng phân mảnh (Overhead)</div>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?= formatBytes($total_free_size) ?></h3>
                        </div>
                    </div>
                </div>

                <!-- BẢNG CHI TIẾT TỪNG BẢNG DỮ LIỆU -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-table text-secondary me-2"></i>Chi tiết dung lượng & Trạng thái phân mảnh các bảng
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light text-center text-secondary">
                                    <tr>
                                        <th style="width: 50px;">STT</th>
                                        <th class="text-start ps-3">Tên bảng</th>
                                        <th style="width: 100px;">Engine</th>
                                        <th style="width: 110px;">Số bản ghi</th>
                                        <th style="width: 120px;">Dữ liệu</th>
                                        <th style="width: 120px;">Chỉ mục (Index)</th>
                                        <th style="width: 140px;">Phân mảnh (Rác)</th>
                                        <th style="width: 120px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($tables_info as $tbl): 
                                        $has_waste = $tbl['free_size'] > 0;
                                    ?>
                                        <tr>
                                            <td class="text-center text-muted"><?= $i++ ?></td>
                                            <td class="ps-3 fw-bold text-dark font-monospace">
                                                <i class="fas fa-database text-primary me-2"></i><?= h($tbl['name']) ?>
                                            </td>
                                            <td class="text-center"><span class="badge bg-light text-secondary border"><?= h($tbl['engine']) ?></span></td>
                                            <td class="text-center fw-semibold"><?= number_format($tbl['rows']) ?></td>
                                            <td class="text-center text-muted"><?= formatBytes($tbl['data_size']) ?></td>
                                            <td class="text-center text-muted"><?= formatBytes($tbl['index_size']) ?></td>
                                            <td class="text-center">
                                                <?php if ($has_waste): ?>
                                                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning">
                                                        <i class="fas fa-exclamation-circle me-1 text-warning"></i><?= formatBytes($tbl['free_size']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>0 B</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2 btn-opt-single" data-table="<?= h($tbl['name']) ?>" title="Tối ưu bảng này">
                                                    <i class="fas fa-magic me-1"></i>Tối ưu
                                                </button>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tối ưu từng bảng lẻ
    document.querySelectorAll('.btn-opt-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const table = this.getAttribute('data-table');
            const button = this;

            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('action', 'optimize_single');
            formData.append('table', table);

            fetch('ajax_optimize_db.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2500 });
                    } else {
                        alert(data.message);
                    }
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(data.message || 'Lỗi');
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối máy chủ');
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
        });
    });

    // 2. Tối ưu toàn bộ CSDL
    const btnOptimizeAll = document.getElementById('btnOptimizeAll');
    if (btnOptimizeAll) {
        btnOptimizeAll.addEventListener('click', function() {
            const executeAll = () => {
                btnOptimizeAll.disabled = true;
                btnOptimizeAll.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang chống phân mảnh...';

                const formData = new FormData();
                formData.append('action', 'optimize_all');

                fetch('ajax_optimize_db.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Hoàn tất', text: data.message }).then(() => location.reload());
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    } else {
                        alert(data.message || 'Có lỗi xảy ra');
                        btnOptimizeAll.disabled = false;
                        btnOptimizeAll.innerHTML = '<i class="fas fa-bolt me-1"></i>Tối ưu hóa toàn bộ bảng ngay';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Lỗi kết nối máy chủ');
                    btnOptimizeAll.disabled = false;
                    btnOptimizeAll.innerHTML = '<i class="fas fa-bolt me-1"></i>Tối ưu hóa toàn bộ bảng ngay';
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Tối ưu hóa toàn bộ CSDL?',
                    text: 'Hệ thống sẽ dọn dẹp các phân mảnh và sắp xếp lại chỉ mục của tất cả các bảng để tăng tốc độ truy vấn.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Bắt đầu tối ưu',
                    cancelButtonText: 'Hủy'
                }).then(res => { if (res.isConfirmed) executeAll(); });
            } else {
                if (confirm('Bắt đầu tối ưu hóa và chống phân mảnh toàn bộ cơ sở dữ liệu?')) executeAll();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>