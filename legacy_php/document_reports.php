<?php
session_start();
include 'db.php';
include_once 'auth.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'], $_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    } else {
        header("Location: login.php");
        exit;
    }
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Năm cần thống kê
$selected_year = intval($_GET['year'] ?? date('Y'));
if ($selected_year < 2000 || $selected_year > 2099) $selected_year = intval(date('Y'));

// 1. Thống kê tổng số lượng (KPIs)
$kpi = [
    'total' => 0,
    'active' => 0,
    'expired' => 0,
    'replaced' => 0,
    'secret' => 0,
    'has_file' => 0
];

$sql_kpi = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'Còn hiệu lực' THEN 1 ELSE 0 END) AS active,
    SUM(CASE WHEN status = 'Hết hiệu lực' THEN 1 ELSE 0 END) AS expired,
    SUM(CASE WHEN status = 'Được thay thế' THEN 1 ELSE 0 END) AS replaced,
    SUM(CASE WHEN UPPER(document_number) LIKE '%-M%' OR UPPER(document_number) LIKE '%/M%' OR UPPER(document_number) LIKE '% M %' OR UPPER(document_number) LIKE '%MẬT%' THEN 1 ELSE 0 END) AS secret,
    SUM(CASE WHEN file_path IS NOT NULL AND TRIM(file_path) != '' THEN 1 ELSE 0 END) AS has_file
FROM documents WHERE deleted_at IS NULL";

$res_kpi = $conn->query($sql_kpi);
if ($res_kpi) {
    $kpi = $res_kpi->fetch_assoc();
}

// 2. Thống kê số lượng ban hành theo 12 tháng của năm đã chọn
$monthly_data = array_fill(1, 12, 0);
$stmt_month = $conn->prepare("SELECT MONTH(issued_date) AS m, COUNT(*) AS cnt 
                              FROM documents 
                              WHERE deleted_at IS NULL 
                                AND issued_date IS NOT NULL 
                                AND YEAR(issued_date) = ? 
                              GROUP BY MONTH(issued_date)");
$stmt_month->bind_param("i", $selected_year);
$stmt_month->execute();
$res_month = $stmt_month->get_result();
while ($row = $res_month->fetch_assoc()) {
    $monthly_data[intval($row['m'])] = intval($row['cnt']);
}
$stmt_month->close();

// 3. Phân bổ theo Loại văn bản
$type_stats = $conn->query("SELECT dt.type_name, COUNT(d.id) AS cnt 
                            FROM document_types dt 
                            LEFT JOIN documents d ON dt.id = d.document_type_id AND d.deleted_at IS NULL 
                            GROUP BY dt.id 
                            ORDER BY cnt DESC, dt.type_name ASC")->fetch_all(MYSQLI_ASSOC);

// 4. Phân bổ theo Cơ quan ban hành
$auth_stats = $conn->query("SELECT ia.authority_name, COUNT(d.id) AS cnt 
                            FROM issuing_authorities ia 
                            LEFT JOIN documents d ON ia.id = d.authority_id AND d.deleted_at IS NULL 
                            GROUP BY ia.id 
                            ORDER BY cnt DESC, ia.authority_name ASC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// 5. Danh sách các năm có văn bản (Đã sửa lỗi so sánh 0000-00-00)
$years_res = $conn->query("SELECT DISTINCT YEAR(issued_date) AS yr 
                           FROM documents 
                           WHERE deleted_at IS NULL 
                             AND issued_date IS NOT NULL 
                             AND YEAR(issued_date) > 1900 
                           ORDER BY yr DESC");
$available_years = [];
if ($years_res) {
    while ($y = $years_res->fetch_assoc()) {
        if (!empty($y['yr'])) $available_years[] = intval($y['yr']);
    }
}
if (!in_array($selected_year, $available_years, true)) {
    $available_years[] = $selected_year;
    rsort($available_years);
}

$title = "Báo cáo thống kê văn bản";
include 'header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-5">
                
                <!-- Tiêu đề & Bộ lọc năm -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie text-danger me-2"></i>Báo cáo Thống kê Tổng hợp</h4>
                        <div class="text-muted small">Số liệu phân tích tình trạng hiệu lực, cơ cấu văn bản và tiến độ ban hành</div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                            <label class="form-label small mb-0 text-nowrap fw-semibold">Năm báo cáo:</label>
                            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 100px;">
                                <?php foreach ($available_years as $yr): ?>
                                    <option value="<?= $yr ?>" <?= ($selected_year == $yr) ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="fas fa-print me-1"></i>In báo cáo</button>
                    </div>
                </div>

                <!-- 1. CÁC THẺ KPI TỔNG QUAN -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-primary bg-opacity-10 border-start border-4 border-primary p-3">
                            <div class="text-muted small fw-semibold">Tổng văn bản</div>
                            <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($kpi['total'] ?? 0) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-success bg-opacity-10 border-start border-4 border-success p-3">
                            <div class="text-muted small fw-semibold">Còn hiệu lực</div>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($kpi['active'] ?? 0) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-secondary bg-opacity-10 border-start border-4 border-secondary p-3">
                            <div class="text-muted small fw-semibold">Hết hiệu lực</div>
                            <h3 class="fw-bold text-secondary mb-0 mt-1"><?= number_format($kpi['expired'] ?? 0) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-warning bg-opacity-10 border-start border-4 border-warning p-3">
                            <div class="text-muted small fw-semibold">Được thay thế</div>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($kpi['replaced'] ?? 0) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-danger bg-opacity-10 border-start border-4 border-danger p-3">
                            <div class="text-muted small fw-semibold">Văn bản Mật</div>
                            <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($kpi['secret'] ?? 0) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="card border-0 shadow-sm rounded-3 bg-info bg-opacity-10 border-start border-4 border-info p-3">
                            <div class="text-muted small fw-semibold">Đã có tệp số hóa</div>
                            <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($kpi['has_file'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>

                <!-- 2. KHỐI BIỂU ĐỒ TRỰC QUAN -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-bar text-danger me-2"></i>Số lượng văn bản ban hành năm <?= $selected_year ?></h6>
                            </div>
                            <div class="card-body p-3">
                                <canvas id="monthlyChart" style="max-height: 280px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie text-danger me-2"></i>Tỷ lệ tình trạng hiệu lực</h6>
                            </div>
                            <div class="card-body p-3 d-flex justify-content-center align-items-center">
                                <canvas id="statusPieChart" style="max-height: 260px; max-width: 260px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. BẢNG CHI TIẾT THEO LOẠI VB VÀ NƠI BAN HÀNH -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-tags text-danger me-2"></i>Cơ cấu theo Loại văn bản</h6>
                                <span class="badge bg-light text-secondary border"><?= count($type_stats) ?> loại</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Loại văn bản</th>
                                                <th class="text-center" style="width: 100px;">Số lượng</th>
                                                <th style="width: 140px;">Tỷ lệ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_docs = max(1, (int)($kpi['total'] ?? 0));
                                            foreach ($type_stats as $ts): 
                                                $pct = round(($ts['cnt'] / $total_docs) * 100, 1);
                                            ?>
                                                <tr>
                                                    <td class="ps-3 fw-medium"><?= h($ts['type_name'] ?: 'Chưa phân loại') ?></td>
                                                    <td class="text-center fw-bold"><?= number_format($ts['cnt']) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                                <div class="progress-bar bg-danger" style="width: <?= $pct ?>%"></div>
                                                            </div>
                                                            <span class="text-muted small" style="font-size: 0.75rem;"><?= $pct ?>%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-3 h-100">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-building text-danger me-2"></i>Top 10 Cơ quan ban hành nhiều nhất</h6>
                                <span class="badge bg-light text-secondary border">Top 10</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Cơ quan ban hành</th>
                                                <th class="text-center" style="width: 100px;">Số lượng</th>
                                                <th style="width: 140px;">Tỷ lệ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($auth_stats as $as): 
                                                $pct = round(($as['cnt'] / $total_docs) * 100, 1);
                                            ?>
                                                <tr>
                                                    <td class="ps-3 fw-medium"><?= h($as['authority_name'] ?: 'Chưa phân loại') ?></td>
                                                    <td class="text-center fw-bold"><?= number_format($as['cnt']) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                                <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                                                            </div>
                                                            <span class="text-muted small" style="font-size: 0.75rem;"><?= $pct ?>%</span>
                                                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    const monthlyValues = <?= json_encode(array_values($monthly_data)) ?>;
    new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: ['Th 1', 'Th 2', 'Th 3', 'Th 4', 'Th 5', 'Th 6', 'Th 7', 'Th 8', 'Th 9', 'Th 10', 'Th 11', 'Th 12'],
            datasets: [{
                label: 'Số văn bản',
                data: monthlyValues,
                backgroundColor: 'rgba(220, 53, 69, 0.75)',
                borderColor: '#dc3545',
                borderWidth: 1.5,
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
                    ticks: { precision: 0 }
                }
            }
        }
    });

    const ctxPie = document.getElementById('statusPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Còn hiệu lực', 'Hết hiệu lực', 'Được thay thế'],
            datasets: [{
                data: [
                    <?= (int)($kpi['active'] ?? 0) ?>, 
                    <?= (int)($kpi['expired'] ?? 0) ?>, 
                    <?= (int)($kpi['replaced'] ?? 0) ?>
                ],
                backgroundColor: ['#198754', '#6c757d', '#ffc107'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 15 }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>