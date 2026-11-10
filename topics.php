<?php
session_start();

include 'db.php';

// Khoi phuc session tu cookie neu co
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

function normalizeTopicKey($text) {
    $text = trim((string)$text);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }

    return strtolower($text);
}

function getStatusBadge($status) {
    $status = trim((string)$status);

    if ($status === 'Còn hiệu lực') {
        return 'bg-success';
    }

    if ($status === 'Hết hiệu lực') {
        return 'bg-secondary';
    }

    if ($status === 'Được thay thế') {
        return 'bg-warning text-dark';
    }

    return 'bg-light text-dark';
}

// Lay avatar
$user_id = intval($_SESSION['user_id']);

$sql_avatar = "SELECT avatar FROM users WHERE id = ?";
$stmt_avatar = $conn->prepare($sql_avatar);
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$result_avatar = $stmt_avatar->get_result();

if ($result_avatar->num_rows > 0) {
    $_SESSION['avatar'] = $result_avatar->fetch_assoc()['avatar'] ?? "uploads/avatars/default.jpg";
} else {
    $_SESSION['avatar'] = "uploads/avatars/default.jpg";
}

$stmt_avatar->close();

// Lay tat ca van ban co tag
$sql = "
    SELECT
        d.id,
        d.document_number,
        d.content,
        d.issued_date,
        d.effective_date,
        d.status,
        d.file_path,
        d.is_favorite,
        d.tags,
        d.personal_note,
        dt.type_name,
        ia.authority_name
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    WHERE d.tags IS NOT NULL AND TRIM(d.tags) <> ''
    ORDER BY d.is_favorite DESC, d.issued_date DESC, d.id DESC
";

$result = $conn->query($sql);

$topics = [];

while ($row = $result->fetch_assoc()) {
    $raw_tags = explode(',', $row['tags'] ?? '');

    foreach ($raw_tags as $raw_tag) {
        $tag = trim($raw_tag);

        if ($tag === '') {
            continue;
        }

        $key = normalizeTopicKey($tag);

        if (!isset($topics[$key])) {
            $topics[$key] = [
                'name' => $tag,
                'count' => 0,
                'favorite_count' => 0,
                'note_count' => 0,
                'documents' => []
            ];
        }

        $topics[$key]['count']++;

        if (!empty($row['is_favorite'])) {
            $topics[$key]['favorite_count']++;
        }

        if (!empty(trim($row['personal_note'] ?? ''))) {
            $topics[$key]['note_count']++;
        }

        $topics[$key]['documents'][] = $row;
    }
}

uasort($topics, function($a, $b) {
    if ($a['count'] === $b['count']) {
        return strcmp($a['name'], $b['name']);
    }

    return $b['count'] <=> $a['count'];
});

$total_topics = count($topics);
$total_tagged_documents = 0;

foreach ($topics as $topic) {
    $total_tagged_documents += $topic['count'];
}

$title = "Chủ đề cá nhân";
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
                            <i class="fas fa-layer-group me-2 text-danger"></i>Chủ đề cá nhân
                        </h4>
                        <div class="text-muted small">
                            Danh sách các tag/chủ đề bạn đã gắn cho văn bản.
                        </div>
                    </div>

                    <!-- Đã sửa: dashboard.php thành documents.php -->
                    <a href="documents.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Về danh sách
                    </a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center me-3"
                                     style="width: 52px; height: 52px;">
                                    <i class="fas fa-tags fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Tổng số chủ đề</div>
                                    <div class="fs-4 fw-bold"><?php echo h($total_topics); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center me-3"
                                     style="width: 52px; height: 52px;">
                                    <i class="fas fa-file-alt fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Lượt gắn tag</div>
                                    <div class="fs-4 fw-bold"><?php echo h($total_tagged_documents); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center me-3"
                                     style="width: 52px; height: 52px;">
                                    <i class="fas fa-star fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Chủ đề phổ biến nhất</div>
                                    <div class="fs-6 fw-bold">
                                        <?php
                                        $first_topic = reset($topics);
                                        echo $first_topic ? h($first_topic['name']) . ' (' . h($first_topic['count']) . ')' : 'Chưa có dữ liệu';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($total_topics === 0): ?>
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="fas fa-tags fa-3x mb-3 text-light"></i>
                            <h5>Chưa có chủ đề nào</h5>
                            <p class="mb-0">Bạn hãy mở một văn bản, nhập tag/chủ đề ở phần ghi chú cá nhân.</p>
                            <!-- Nút gọi hành động -->
                            <a href="documents.php" class="btn btn-outline-danger mt-3">Đi đến danh sách văn bản</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($topics as $topic): ?>
                            <?php
                            $topic_url = 'topic_profile.php?tag=' . urlencode($topic['name']);
                            // Đã sửa: dashboard.php thành documents.php
                            $dashboard_url = 'documents.php?search_query=' . urlencode($topic['name']) . '&search_column=tags';
                            $preview_documents = array_slice($topic['documents'], 0, 5);
                            ?>

                            <div class="col-md-6 col-xl-4">
                                <div class="card border-0 shadow-sm h-100 rounded-3">
                                    <div class="card-header bg-white border-bottom-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                                        <div class="fw-bold fs-5 text-danger text-truncate pe-2">
                                            <i class="fas fa-tag me-1 text-danger opacity-75"></i><?php echo h($topic['name']); ?>
                                        </div>
                                        <span class="badge bg-danger rounded-pill px-3 py-2">
                                            <?php echo h($topic['count']); ?> VB
                                        </span>
                                    </div>

                                    <div class="card-body pt-0">
                                        <div class="d-flex gap-2 mb-3 flex-wrap">
                                            <?php if($topic['favorite_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark border border-warning">
                                                    <i class="fas fa-star me-1 text-danger"></i><?php echo h($topic['favorite_count']); ?> quan trọng
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if($topic['note_count'] > 0): ?>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="fas fa-sticky-note me-1 text-primary"></i><?php echo h($topic['note_count']); ?> có ghi chú
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php foreach ($preview_documents as $doc): ?>
                                            <div class="border-bottom pb-2 mb-2">
                                                <div class="fw-semibold text-truncate">
                                                    <?php if (!empty($doc['is_favorite'])): ?>
                                                        <i class="fas fa-star text-warning me-1"></i>
                                                    <?php endif; ?>
                                                    <a href="view_document.php?id=<?php echo (int)$doc['id']; ?>" class="text-decoration-none text-dark hover-danger">
                                                        <?php echo h($doc['document_number']); ?>
                                                    </a>
                                                </div>

                                                <div class="small text-muted text-truncate mt-1">
                                                    <i class="fas fa-file-alt me-1 opacity-50"></i><?php echo h($doc['type_name'] ?? ''); ?>
                                                    <?php if (!empty($doc['issued_date'])): ?>
                                                        | <i class="far fa-calendar-alt me-1 opacity-50"></i><?php echo h(formatDateVN($doc['issued_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($topic['count'] > 5): ?>
                                            <div class="small text-muted text-center mt-2 fst-italic">
                                                Còn <?php echo h($topic['count'] - 5); ?> văn bản khác...
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-footer bg-light border-0 rounded-bottom-3 d-flex justify-content-between p-3">
                                        <a href="<?php echo h($topic_url); ?>" class="btn btn-danger btn-sm px-3">
                                            <i class="fas fa-folder-open me-1"></i>Chi tiết
                                        </a>

                                        <a href="<?php echo h($dashboard_url); ?>" class="btn btn-outline-secondary btn-sm px-3">
                                            <i class="fas fa-filter me-1"></i>Lọc VB
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<style>
    .hover-danger:hover {
        color: #dc3545 !important;
    }
</style>

<?php include 'footer.php'; ?>