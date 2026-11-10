<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username']) && isset($_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['full_name'] = $_COOKIE['full_name'] ?? '';
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
        $_SESSION['avatar'] = $_COOKIE['avatar'] ?? '';
    } else {
        header('Location: login.php');
        exit;
    }
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDateVN($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '1970-01-01') {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

function getStatusBadge($status) {
    $status = trim((string)$status);
    if ($status === 'Còn hiệu lực') return 'bg-success';
    if ($status === 'Hết hiệu lực') return 'bg-secondary';
    if ($status === 'Được thay thế') return 'bg-warning text-dark';
    return 'bg-light text-dark';
}

function get_count($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? intval($result->fetch_assoc()['total'] ?? 0) : 0;
}

// Avatar
$user_id = intval($_SESSION['user_id']);
$sql_avatar = "SELECT avatar FROM users WHERE id = ?";
$stmt_avatar = $conn->prepare($sql_avatar);
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$result_avatar = $stmt_avatar->get_result();
$_SESSION['avatar'] = ($result_avatar->num_rows > 0) ? ($result_avatar->fetch_assoc()['avatar'] ?? "uploads/avatars/default.jpg") : "uploads/avatars/default.jpg";
$stmt_avatar->close();

// TỐI ƯU HÓA TỐC ĐỘ: Gộp 9 truy vấn COUNT riêng lẻ thành 1 truy vấn duy nhất
$sql_stats = "SELECT 
    COUNT(*) AS total_documents,
    SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) AS total_favorite,
    SUM(CASE WHEN personal_note IS NOT NULL AND TRIM(personal_note) <> '' THEN 1 ELSE 0 END) AS total_has_note,
    SUM(CASE WHEN personal_note IS NULL OR TRIM(personal_note) = '' THEN 1 ELSE 0 END) AS total_no_note,
    SUM(CASE WHEN tags IS NOT NULL AND TRIM(tags) <> '' THEN 1 ELSE 0 END) AS total_has_tags,
    SUM(CASE WHEN tags IS NULL OR TRIM(tags) = '' THEN 1 ELSE 0 END) AS total_no_tags,
    SUM(CASE WHEN file_path IS NOT NULL AND TRIM(file_path) <> '' THEN 1 ELSE 0 END) AS total_has_file,
    SUM(CASE WHEN file_path IS NULL OR TRIM(file_path) = '' THEN 1 ELSE 0 END) AS total_no_file,
    SUM(CASE WHEN UPPER(document_number) LIKE '%-M%' 
               OR UPPER(document_number) LIKE '%/M%' 
               OR UPPER(document_number) LIKE '% M %' 
               OR UPPER(document_number) LIKE '%MẬT%' THEN 1 ELSE 0 END) AS total_secret
FROM documents";

$stats = $conn->query($sql_stats)->fetch_assoc();

$total_documents = $stats['total_documents'] ?? 0;
$total_favorite = $stats['total_favorite'] ?? 0;
$total_has_note = $stats['total_has_note'] ?? 0;
$total_no_note = $stats['total_no_note'] ?? 0;
$total_has_tags = $stats['total_has_tags'] ?? 0;
$total_no_tags = $stats['total_no_tags'] ?? 0;
$total_has_file = $stats['total_has_file'] ?? 0;
$total_no_file = $stats['total_no_file'] ?? 0;
$total_secret = $stats['total_secret'] ?? 0;

// Các truy vấn cần bảng khác vẫn giữ nguyên
$total_with_attachments = get_count($conn, "SELECT COUNT(DISTINCT document_id) AS total FROM document_attachments");
$total_with_relations = get_count($conn, "
    SELECT COUNT(DISTINCT doc_id) AS total FROM (
        SELECT document_id AS doc_id FROM document_relations
        UNION
        SELECT related_document_id AS doc_id FROM document_relations
    ) x
");

// Thong ke hieu luc
$status_stats = [];
$result_status = $conn->query("
    SELECT COALESCE(NULLIF(TRIM(status), ''), 'Không xác định') AS status_name, COUNT(*) AS total
    FROM documents GROUP BY status_name ORDER BY total DESC
");
while ($row = $result_status->fetch_assoc()) $status_stats[] = $row;

// Top loai van ban
$type_stats = [];
$result_types = $conn->query("
    SELECT dt.type_name, COUNT(*) AS total FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    GROUP BY dt.id, dt.type_name ORDER BY total DESC LIMIT 10
");
while ($row = $result_types->fetch_assoc()) $type_stats[] = $row;

// Top co quan ban hanh
$authority_stats = [];
$result_authorities = $conn->query("
    SELECT ia.authority_name, COUNT(*) AS total FROM documents d
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    GROUP BY ia.id, ia.authority_name ORDER BY total DESC LIMIT 10
");
while ($row = $result_authorities->fetch_assoc()) $authority_stats[] = $row;

// Top tags
$tag_counts = [];
$result_tags = $conn->query("SELECT tags FROM documents WHERE tags IS NOT NULL AND TRIM(tags) <> ''");
while ($row = $result_tags->fetch_assoc()) {
    $raw_tags = explode(',', $row['tags'] ?? '');
    foreach ($raw_tags as $tag) {
        $tag = trim($tag);
        if ($tag === '') continue;
        $key = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        if (!isset($tag_counts[$key])) {
            $tag_counts[$key] = ['name' => $tag, 'total' => 0];
        }
        $tag_counts[$key]['total']++;
    }
}
uasort($tag_counts, function($a, $b) { return $b['total'] <=> $a['total']; });
$top_tags = array_slice($tag_counts, 0, 10, true);

// Van ban moi nhat
$recent_documents = [];
$result_recent = $conn->query("
    SELECT d.id, d.document_number, d.content, d.issued_date, d.status, d.is_favorite, dt.type_name, ia.authority_name
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    ORDER BY d.id DESC LIMIT 10
");
while ($row = $result_recent->fetch_assoc()) $recent_documents[] = $row;

$title = "Thống kê cá nhân";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">

        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-5">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-chart-pie me-2 text-danger"></i>Thống kê cá nhân
                        </h4>
                        <div class="text-muted small">
                            Tổng quan kho văn bản, tag, ghi chú, file, tài liệu kèm theo và quan hệ văn bản.
                        </div>
                    </div>

                    <a href="documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Về danh sách
                    </a>
                </div>

                <div class="row g-3 mb-4">
                    <?php
                    $cards = [
                        ['label' => 'Tổng văn bản', 'value' => $total_documents, 'icon' => 'fas fa-file-alt', 'class' => 'bg-primary'],
                        ['label' => 'Quan trọng', 'value' => $total_favorite, 'icon' => 'fas fa-star', 'class' => 'bg-warning text-dark'],
                        ['label' => 'Có ghi chú', 'value' => $total_has_note, 'icon' => 'fas fa-sticky-note', 'class' => 'bg-success'],
                        ['label' => 'Có tag', 'value' => $total_has_tags, 'icon' => 'fas fa-tags', 'class' => 'bg-info text-dark'],
                        ['label' => 'Có file chính', 'value' => $total_has_file, 'icon' => 'fas fa-paperclip', 'class' => 'bg-secondary'],
                        ['label' => 'Tài liệu kèm theo', 'value' => $total_with_attachments, 'icon' => 'fas fa-folder-open', 'class' => 'bg-danger'],
                        ['label' => 'Văn bản liên quan', 'value' => $total_with_relations, 'icon' => 'fas fa-link', 'class' => 'bg-dark'],
                        ['label' => 'Văn bản mật', 'value' => $total_secret, 'icon' => 'fas fa-lock', 'class' => 'bg-danger']
                    ];
                    ?>

                    <?php foreach ($cards as $card): ?>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100 rounded-3 card-hover">
                                <div class="card-body d-flex align-items-center p-3">
                                    <div class="rounded-circle <?php echo h($card['class']); ?> text-white d-flex align-items-center justify-content-center me-3 shadow-sm"
                                         style="width: 48px; height: 48px;">
                                        <i class="<?php echo h($card['icon']); ?> fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.75rem;"><?php echo h($card['label']); ?></div>
                                        <div class="fs-4 fw-bold lh-1 mt-1"><?php echo h($card['value']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 fw-bold text-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>Dữ liệu cần hoàn thiện
                            </div>
                            <div class="card-body">
                                <!-- Chuyển thành List Group, Link đổi sang documents.php -->
                                <div class="list-group list-group-flush">
                                    <a href="documents.php?data_filter=no_tags" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                        <span><i class="fas fa-tag me-2 text-muted"></i>Chưa có tag</span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo h($total_no_tags); ?></span>
                                    </a>
                                    <a href="documents.php?data_filter=no_note" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                        <span><i class="fas fa-sticky-note me-2 text-muted"></i>Chưa có ghi chú</span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo h($total_no_note); ?></span>
                                    </a>
                                    <a href="documents.php?personal_filter=no_file" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                        <span><i class="fas fa-file me-2 text-muted"></i>Chưa có file chính</span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo h($total_no_file); ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 fw-bold text-success">
                                <i class="fas fa-check-circle me-2"></i>Tình trạng hiệu lực
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php if (count($status_stats) > 0): ?>
                                        <?php foreach ($status_stats as $item): 
                                            $badge_class = getStatusBadge($item['status_name']);
                                        ?>
                                            <a href="documents.php?status=<?php echo urlencode($item['status_name']); ?>"
                                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                                                <span class="badge <?php echo $badge_class; ?> rounded-pill fw-normal"><?php echo h($item['status_name']); ?></span>
                                                <span class="fw-bold"><?php echo h($item['total']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted text-center mt-3">Chưa có dữ liệu.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0 fw-bold text-info">
                                <i class="fas fa-tags me-2"></i>Top tag / chủ đề
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php if (count($top_tags) > 0): ?>
                                    <?php foreach ($top_tags as $tag): ?>
                                        <a href="documents.php?search_query=<?php echo urlencode($tag['name']); ?>&search_column=tags"
                                           class="btn btn-outline-danger btn-sm rounded-pill">
                                            <?php echo h($tag['name']); ?>
                                            <span class="badge bg-danger ms-1"><?php echo h($tag['total']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted w-100 text-center mt-3">Chưa có tag nào.</div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-2 fw-bold text-primary">
                                <i class="fas fa-layer-group me-2"></i>Top loại văn bản
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 border-0">Loại văn bản</th>
                                            <th class="text-center border-0" style="width: 120px;">Số lượng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($type_stats as $item): ?>
                                            <tr>
                                                <td class="ps-3 text-muted"><?php echo h($item['type_name'] ?? 'Không xác định'); ?></td>
                                                <td class="text-center fw-bold"><?php echo h($item['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($type_stats) === 0): ?>
                                            <tr><td colspan="2" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 rounded-3">
                            <div class="card-header bg-white border-bottom-0 pt-3 pb-2 fw-bold text-secondary">
                                <i class="fas fa-building me-2"></i>Top cơ quan ban hành
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 border-0">Cơ quan ban hành</th>
                                            <th class="text-center border-0" style="width: 120px;">Số lượng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($authority_stats as $item): ?>
                                            <tr>
                                                <td class="ps-3 text-muted"><?php echo h($item['authority_name'] ?? 'Không xác định'); ?></td>
                                                <td class="text-center fw-bold"><?php echo h($item['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($authority_stats) === 0): ?>
                                            <tr><td colspan="2" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-2 fw-bold text-danger">
                        <i class="fas fa-clock me-2"></i>10 văn bản nhập gần đây
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle border-top mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 150px;">Số ký hiệu</th>
                                    <th>Trích yếu</th>
                                    <th style="width: 140px;">Loại</th>
                                    <th style="width: 160px;">Cơ quan</th>
                                    <th style="width: 120px;" class="text-center">Ngày BH</th>
                                    <th style="width: 130px;" class="text-center">Hiệu lực</th>
                                    <th style="width: 90px;" class="text-center">Xem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_documents as $doc): ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold">
                                            <?php if (!empty($doc['is_favorite'])): ?>
                                                <i class="fas fa-star text-warning me-1"></i>
                                            <?php endif; ?>
                                            <a href="view_document.php?id=<?php echo (int)$doc['id']; ?>" class="text-decoration-none text-dark hover-danger">
                                                <?php echo h($doc['document_number']); ?>
                                            </a>
                                        </td>
                                        <td class="text-truncate" style="max-width: 250px;" title="<?php echo h($doc['content']); ?>">
                                            <?php echo h($doc['content']); ?>
                                        </td>
                                        <td class="text-muted small"><?php echo h($doc['type_name']); ?></td>
                                        <td class="text-muted small"><?php echo h($doc['authority_name']); ?></td>
                                        <td class="text-center text-muted small"><?php echo h(formatDateVN($doc['issued_date'])); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo getStatusBadge($doc['status']); ?>">
                                                <?php echo h($doc['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="view_document.php?id=<?php echo (int)$doc['id']; ?>" class="btn btn-outline-primary btn-sm px-2 py-1">
                                                Xem
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($recent_documents) === 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có văn bản nào.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
    .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .card-hover:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .hover-danger:hover { color: #dc3545 !important; }
</style>

<?php include 'footer.php'; ?>