<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username']) && isset($_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    } else {
        header('Location: login.php');
        exit;
    }
}

function h($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

// ==========================================
// 1. TỐI ƯU HÓA: GỘP 4 TRUY VẤN THỐNG KÊ THÀNH 1
// ==========================================
$sql_summary = "SELECT 
    COUNT(*) as total_docs,
    SUM(CASE WHEN status = 'Còn hiệu lực' THEN 1 ELSE 0 END) as valid_docs,
    SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as favorite_docs,
    SUM(CASE WHEN UPPER(document_number) LIKE '%-M%' 
               OR UPPER(document_number) LIKE '%/M%' 
               OR UPPER(document_number) LIKE '% M %' 
               OR UPPER(document_number) LIKE '%MẬT%' THEN 1 ELSE 0 END) as secret_docs
FROM documents";

$summary_result = $conn->query($sql_summary)->fetch_assoc();

$total_docs = $summary_result['total_docs'] ?? 0;
$valid_docs = $summary_result['valid_docs'] ?? 0;
$favorite_docs = $summary_result['favorite_docs'] ?? 0;
$secret_docs = $summary_result['secret_docs'] ?? 0;

// ==========================================
// 2. LẤY DỮ LIỆU BIỂU ĐỒ TRÒN (THEO TÌNH TRẠNG HIỆU LỰC)
// ==========================================
$status_query = $conn->query("SELECT status, COUNT(*) as count FROM documents GROUP BY status");
$chart_status_labels = [];
$chart_status_data = [];
$chart_status_colors = [];
$color_map = [
    'Còn hiệu lực' => '#198754', // Xanh lá
    'Hết hiệu lực' => '#6c757d', // Xám
    'Được thay thế' => '#ffc107', // Vàng
    'Không xác định' => '#0dcaf0', // Xanh nhạt
    'Chưa biết' => '#ced4da'
];
while ($row = $status_query->fetch_assoc()) {
    $st = $row['status'] ?: 'Chưa biết';
    $chart_status_labels[] = $st;
    $chart_status_data[] = $row['count'];
    $chart_status_colors[] = $color_map[$st] ?? '#007bff';
}

// ==========================================
// 3. LẤY DỮ LIỆU BIỂU ĐỒ CỘT (THEO LOẠI VĂN BẢN - TOP 5)
// ==========================================
$type_query = $conn->query("
    SELECT dt.type_name, COUNT(d.id) as count 
    FROM documents d 
    LEFT JOIN document_types dt ON d.document_type_id = dt.id 
    GROUP BY d.document_type_id 
    ORDER BY count DESC 
    LIMIT 5
");
$chart_type_labels = [];
$chart_type_data = [];
while ($row = $type_query->fetch_assoc()) {
    $chart_type_labels[] = $row['type_name'] ?? 'Khác';
    $chart_type_data[] = $row['count'];
}

// ==========================================
// 4. VĂN BẢN MỚI CẬP NHẬT GẦN ĐÂY
// ==========================================
$recent_docs = $conn->query("
    SELECT id, document_number, content, issued_date, status 
    FROM documents 
    ORDER BY id DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$title = "Tổng quan hệ thống";
include 'header.php';
?>

<!-- Chuyển Chart.js về Local để tăng tốc độ tải -->
<script src="assets/plugins/chart.js"></script>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="col-md-12 mt-3">
                <h4 class="mb-4">Tổng quan hệ thống</h4>

                <!-- HÀNG 1: CÁC THẺ THỐNG KÊ (CARDS) -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white shadow-sm h-100 border-0 rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="fs-1 me-3"><i class="fas fa-file-alt"></i></div>
                                <div>
                                    <h6 class="card-title mb-0 text-white-50">Tổng số văn bản</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo h($total_docs); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white shadow-sm h-100 border-0 rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="fs-1 me-3"><i class="fas fa-check-circle"></i></div>
                                <div>
                                    <h6 class="card-title mb-0 text-white-50">Còn hiệu lực</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo h($valid_docs); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark shadow-sm h-100 border-0 rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="fs-1 me-3"><i class="fas fa-star"></i></div>
                                <div>
                                    <h6 class="card-title mb-0 text-black-50">Quan trọng (Yêu thích)</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo h($favorite_docs); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white shadow-sm h-100 border-0 rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="fs-1 me-3"><i class="fas fa-lock"></i></div>
                                <div>
                                    <h6 class="card-title mb-0 text-white-50">Văn bản Mật</h6>
                                    <h2 class="mb-0 fw-bold"><?php echo h($secret_docs); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HÀNG 2: BIỂU ĐỒ THỐNG KÊ -->
                <div class="row mb-4">
                    <!-- Biểu đồ tròn: Hiệu lực -->
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                <h6 class="fw-bold text-secondary">Tỷ lệ tình trạng hiệu lực</h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px; display: flex; justify-content: center;">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Biểu đồ cột: Loại văn bản -->
                    <div class="col-md-7">
                        <div class="card shadow-sm border-0 h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                <h6 class="fw-bold text-secondary">Top 5 loại văn bản nhiều nhất</h6>
                            </div>
                            <div class="card-body">
                                <div style="height: 250px;">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HÀNG 3: VĂN BẢN MỚI THÊM -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center pt-3 pb-2">
                        <h6 class="fw-bold text-secondary mb-0">Văn bản mới cập nhật gần đây</h6>
                        <a href="documents.php" class="btn btn-sm btn-outline-danger">Xem tất cả <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Số ký hiệu</th>
                                        <th>Trích yếu</th>
                                        <th>Ngày ban hành</th>
                                        <th>Tình trạng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_docs as $doc): ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold">
                                            <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo h($doc['document_number']); ?>
                                            </a>
                                        </td>
                                        <td class="text-truncate" style="max-width: 350px;" title="<?php echo h($doc['content']); ?>">
                                            <?php echo h($doc['content']); ?>
                                        </td>
                                        <td>
                                            <?php echo (!empty($doc['issued_date']) && $doc['issued_date'] != '0000-00-00') ? date('d/m/Y', strtotime($doc['issued_date'])) : ''; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($doc['status'] == 'Còn hiệu lực') ? 'success' : (($doc['status'] == 'Hết hiệu lực') ? 'secondary' : 'warning text-dark'); ?>">
                                                <?php echo h($doc['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(count($recent_docs) == 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Chưa có dữ liệu</td>
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
    // 1. Vẽ biểu đồ Tròn (Doughnut) cho Tình trạng hiệu lực
    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        const ctxStatus = statusCanvas.getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_status_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_status_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // 2. Vẽ biểu đồ Cột (Bar) cho Loại văn bản
    const typeCanvas = document.getElementById('typeChart');
    if (typeCanvas) {
        const ctxType = typeCanvas.getContext('2d');
        new Chart(ctxType, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_type_labels); ?>,
                datasets: [{
                    label: 'Số lượng văn bản',
                    data: <?php echo json_encode($chart_type_data); ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>