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

function formatFileSize($bytes) {
    $bytes = (int)$bytes;

    if ($bytes <= 0) {
        return '';
    }

    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }

    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

function buildInPlaceholders($count) {
    return implode(',', array_fill(0, $count, '?'));
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

$tag = trim($_GET['tag'] ?? '');

if ($tag === '') {
    $_SESSION['flash_error'] = 'Chưa chọn chủ đề.';
    header('Location: topics.php');
    exit;
}

$tag_key = normalizeTopicKey($tag);

// Lay tat ca van ban co tag, sau do loc dung tag bang PHP de tranh match sai kieu "y te" va "y te co so"
$sql_docs = "
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

$result_docs = $conn->query($sql_docs);

$documents = [];

while ($row = $result_docs->fetch_assoc()) {
    $raw_tags = explode(',', $row['tags'] ?? '');
    $matched = false;

    foreach ($raw_tags as $raw_tag) {
        if (normalizeTopicKey($raw_tag) === $tag_key) {
            $matched = true;
            break;
        }
    }

    if ($matched) {
        $documents[] = $row;
    }
}

$document_ids = array_map(function($doc) {
    return (int)$doc['id'];
}, $documents);

$total_documents = count($documents);
$total_favorite = 0;
$total_has_note = 0;
$total_has_file = 0;
$total_secret = 0;

$status_stats = [];
$type_stats = [];
$authority_stats = [];

foreach ($documents as $doc) {
    if (!empty($doc['is_favorite'])) {
        $total_favorite++;
    }

    if (!empty(trim($doc['personal_note'] ?? ''))) {
        $total_has_note++;
    }

    if (!empty(trim($doc['file_path'] ?? ''))) {
        $total_has_file++;
    }

    $doc_number = $doc['document_number'] ?? '';
    $doc_upper = function_exists('mb_strtoupper')
        ? mb_strtoupper($doc_number, 'UTF-8')
        : strtoupper($doc_number);

    if (
        strpos($doc_upper, '-M') !== false
        || strpos($doc_upper, '/M') !== false
        || strpos($doc_upper, ' M ') !== false
        || strpos($doc_upper, 'MẬT') !== false
    ) {
        $total_secret++;
    }

    $status_name = trim($doc['status'] ?? 'Không xác định');
    if ($status_name === '') {
        $status_name = 'Không xác định';
    }

    if (!isset($status_stats[$status_name])) {
        $status_stats[$status_name] = 0;
    }

    $status_stats[$status_name]++;

    $type_name = trim($doc['type_name'] ?? 'Không xác định');
    if ($type_name === '') {
        $type_name = 'Không xác định';
    }

    if (!isset($type_stats[$type_name])) {
        $type_stats[$type_name] = 0;
    }

    $type_stats[$type_name]++;

    $authority_name = trim($doc['authority_name'] ?? 'Không xác định');
    if ($authority_name === '') {
        $authority_name = 'Không xác định';
    }

    if (!isset($authority_stats[$authority_name])) {
        $authority_stats[$authority_name] = 0;
    }

    $authority_stats[$authority_name]++;
}

arsort($status_stats);
arsort($type_stats);
arsort($authority_stats);

// Lay tai lieu kem theo cua cac van ban trong chu de
$attachments_by_doc = [];
$total_attachments = 0;

if (count($document_ids) > 0) {
    $placeholders = buildInPlaceholders(count($document_ids));
    $types = str_repeat('i', count($document_ids));

    $sql_attachments = "
        SELECT
            id,
            document_id,
            attachment_title,
            attachment_type,
            attachment_path,
            original_name,
            file_ext,
            file_size,
            note,
            created_at
        FROM document_attachments
        WHERE document_id IN ($placeholders)
        ORDER BY created_at DESC, id DESC
    ";

    $stmt_attachments = $conn->prepare($sql_attachments);
    $stmt_attachments->bind_param($types, ...$document_ids);
    $stmt_attachments->execute();
    $result_attachments = $stmt_attachments->get_result();

    while ($row = $result_attachments->fetch_assoc()) {
        $doc_id = (int)$row['document_id'];

        if (!isset($attachments_by_doc[$doc_id])) {
            $attachments_by_doc[$doc_id] = [];
        }

        $attachments_by_doc[$doc_id][] = $row;
        $total_attachments++;
    }

    $stmt_attachments->close();
}

// Lay quan he van ban cua cac van ban trong chu de
$relations_by_doc = [];
$total_relations = 0;

if (count($document_ids) > 0) {
    $placeholders = buildInPlaceholders(count($document_ids));
    $types = str_repeat('i', count($document_ids));

    $sql_relations_out = "
        SELECT
            dr.id AS relation_id,
            dr.document_id,
            dr.related_document_id,
            dr.relation_type,
            dr.note,
            rd.document_number,
            rd.content,
            rd.issued_date,
            rd.status,
            dt.type_name,
            ia.authority_name
        FROM document_relations dr
        INNER JOIN documents rd ON dr.related_document_id = rd.id
        LEFT JOIN document_types dt ON rd.document_type_id = dt.id
        LEFT JOIN issuing_authorities ia ON rd.authority_id = ia.id
        WHERE dr.document_id IN ($placeholders)
        ORDER BY dr.created_at DESC, dr.id DESC
    ";

    $stmt_relations = $conn->prepare($sql_relations_out);
    $stmt_relations->bind_param($types, ...$document_ids);
    $stmt_relations->execute();
    $result_relations = $stmt_relations->get_result();

    while ($row = $result_relations->fetch_assoc()) {
        $doc_id = (int)$row['document_id'];

        if (!isset($relations_by_doc[$doc_id])) {
            $relations_by_doc[$doc_id] = [];
        }

        $row['direction'] = 'out';
        $relations_by_doc[$doc_id][] = $row;
        $total_relations++;
    }

    $stmt_relations->close();

    $sql_relations_in = "
        SELECT
            dr.id AS relation_id,
            dr.document_id,
            dr.related_document_id,
            dr.relation_type,
            dr.note,
            sd.document_number,
            sd.content,
            sd.issued_date,
            sd.status,
            sd.id AS source_id,
            dt.type_name,
            ia.authority_name
        FROM document_relations dr
        INNER JOIN documents sd ON dr.document_id = sd.id
        LEFT JOIN document_types dt ON sd.document_type_id = dt.id
        LEFT JOIN issuing_authorities ia ON sd.authority_id = ia.id
        WHERE dr.related_document_id IN ($placeholders)
        ORDER BY dr.created_at DESC, dr.id DESC
    ";

    $stmt_relations_in = $conn->prepare($sql_relations_in);
    $stmt_relations_in->bind_param($types, ...$document_ids);
    $stmt_relations_in->execute();
    $result_relations_in = $stmt_relations_in->get_result();

    while ($row = $result_relations_in->fetch_assoc()) {
        $doc_id = (int)$row['related_document_id'];

        if (!isset($relations_by_doc[$doc_id])) {
            $relations_by_doc[$doc_id] = [];
        }

        $row['direction'] = 'in';
        $relations_by_doc[$doc_id][] = $row;
        $total_relations++;
    }

    $stmt_relations_in->close();
}

$title = "Hồ sơ chủ đề: " . h($tag);
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
                            <i class="fas fa-folder-open me-2 text-danger"></i>
                            Hồ sơ chủ đề: <?php echo h($tag); ?>
                        </h4>

                        <div class="text-muted small">
                            Tổng hợp văn bản, ghi chú, tài liệu kèm theo và quan hệ văn bản theo một chủ đề.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="dashboard.php?search_query=<?php echo urlencode($tag); ?>&search_column=tags" class="btn btn-outline-danger">
                            <i class="fas fa-search me-1"></i>Lọc trên danh sách
                        </a>

                        <a href="topics.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Về chủ đề
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php
                    $cards = [
                        ['label' => 'Văn bản', 'value' => $total_documents, 'icon' => 'fas fa-file-alt', 'class' => 'bg-primary'],
                        ['label' => 'Quan trọng', 'value' => $total_favorite, 'icon' => 'fas fa-star', 'class' => 'bg-warning text-dark'],
                        ['label' => 'Có ghi chú', 'value' => $total_has_note, 'icon' => 'fas fa-sticky-note', 'class' => 'bg-success'],
                        ['label' => 'Có file chính', 'value' => $total_has_file, 'icon' => 'fas fa-paperclip', 'class' => 'bg-secondary'],
                        ['label' => 'Tài liệu kèm theo', 'value' => $total_attachments, 'icon' => 'fas fa-folder-open', 'class' => 'bg-danger'],
                        ['label' => 'Liên kết văn bản', 'value' => $total_relations, 'icon' => 'fas fa-link', 'class' => 'bg-dark'],
                        ['label' => 'Văn bản mật', 'value' => $total_secret, 'icon' => 'fas fa-lock', 'class' => 'bg-danger'],
                        ['label' => 'Loại văn bản', 'value' => count($type_stats), 'icon' => 'fas fa-layer-group', 'class' => 'bg-info text-dark']
                    ];
                    ?>

                    <?php foreach ($cards as $card): ?>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="rounded-circle <?php echo h($card['class']); ?> text-white d-flex align-items-center justify-content-center me-3"
                                         style="width: 52px; height: 52px;">
                                        <i class="<?php echo h($card['icon']); ?>"></i>
                                    </div>

                                    <div>
                                        <div class="text-muted small"><?php echo h($card['label']); ?></div>
                                        <div class="fs-4 fw-bold"><?php echo h($card['value']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light fw-bold">
                                <i class="fas fa-check-circle me-2 text-success"></i>Hiệu lực trong chủ đề
                            </div>

                            <div class="card-body">
                                <?php if (count($status_stats) > 0): ?>
                                    <?php foreach ($status_stats as $name => $count): ?>
                                        <div class="d-flex justify-content-between border-bottom py-2">
                                            <span><?php echo h($name); ?></span>
                                            <strong><?php echo h($count); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted">Chưa có dữ liệu.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light fw-bold">
                                <i class="fas fa-layer-group me-2 text-primary"></i>Loại văn bản
                            </div>

                            <div class="card-body">
                                <?php if (count($type_stats) > 0): ?>
                                    <?php $type_stats_limited = array_slice($type_stats, 0, 8, true); ?>
                                    <?php foreach ($type_stats_limited as $name => $count): ?>
                                        <div class="d-flex justify-content-between border-bottom py-2">
                                            <span><?php echo h($name); ?></span>
                                            <strong><?php echo h($count); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted">Chưa có dữ liệu.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-light fw-bold">
                                <i class="fas fa-building me-2 text-secondary"></i>Cơ quan ban hành
                            </div>

                            <div class="card-body">
                                <?php if (count($authority_stats) > 0): ?>
                                    <?php $authority_stats_limited = array_slice($authority_stats, 0, 8, true); ?>
                                    <?php foreach ($authority_stats_limited as $name => $count): ?>
                                        <div class="d-flex justify-content-between border-bottom py-2">
                                            <span><?php echo h($name); ?></span>
                                            <strong><?php echo h($count); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted">Chưa có dữ liệu.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($total_documents === 0): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                            <h5>Không có văn bản trong chủ đề này</h5>
                            <p class="mb-0">Chủ đề có thể đã bị đổi tên hoặc chưa được gắn cho văn bản nào.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light fw-bold">
                            <i class="fas fa-file-alt me-2 text-danger"></i>Danh sách văn bản trong chủ đề
                        </div>

                        <div class="card-body">
                            <?php foreach ($documents as $doc): ?>
                                <?php
                                $doc_id = (int)$doc['id'];
                                $doc_attachments = $attachments_by_doc[$doc_id] ?? [];
                                $doc_relations = $relations_by_doc[$doc_id] ?? [];
                                $status = trim($doc['status'] ?? 'Không xác định');
                                $status_badge = getStatusBadge($status);
                                $file_path = $doc['file_path'] ?? '';
                                $file_exists = (!empty($file_path) && file_exists($file_path));
                                ?>

                                <div class="card border shadow-sm mb-3">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold fs-6">
                                                <?php if (!empty($doc['is_favorite'])): ?>
                                                    <i class="fas fa-star text-warning me-1"></i>
                                                <?php endif; ?>

                                                <a href="view_document.php?id=<?php echo $doc_id; ?>" class="text-decoration-none">
                                                    <?php echo h($doc['document_number']); ?>
                                                </a>
                                            </div>

                                            <div class="small text-muted mt-1">
                                                <?php echo h($doc['type_name'] ?? ''); ?>
                                                <?php if (!empty($doc['authority_name'])): ?>
                                                    | <?php echo h($doc['authority_name']); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($doc['issued_date'])): ?>
                                                    | Ngày BH: <?php echo h(formatDateVN($doc['issued_date'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <span class="badge <?php echo h($status_badge); ?>">
                                                <?php echo h($status !== '' ? $status : 'Không xác định'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="fw-semibold mb-1">Trích yếu</div>
                                            <div><?php echo h($doc['content']); ?></div>
                                        </div>

                                        <?php if (!empty(trim($doc['personal_note'] ?? ''))): ?>
                                            <div class="alert alert-warning bg-warning bg-opacity-10 border-warning py-2">
                                                <div class="fw-semibold">
                                                    <i class="fas fa-sticky-note me-1"></i>Ghi chú cá nhân
                                                </div>
                                                <div style="white-space: pre-line;"><?php echo h($doc['personal_note']); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="fw-bold mb-2">
                                                        <i class="fas fa-paperclip me-1 text-primary"></i>File chính
                                                    </div>

                                                    <?php if ($file_exists): ?>
                                                        <a href="<?php echo h($file_path); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye me-1"></i>Xem file chính
                                                        </a>
                                                    <?php elseif (!empty($file_path)): ?>
                                                        <span class="badge bg-warning text-dark">Có đường dẫn nhưng mất file</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Chưa có file chính</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="fw-bold mb-2">
                                                        <i class="fas fa-folder-open me-1 text-danger"></i>Tài liệu kèm theo
                                                        <span class="badge bg-danger"><?php echo count($doc_attachments); ?></span>
                                                    </div>

                                                    <?php if (count($doc_attachments) === 0): ?>
                                                        <span class="text-muted">Chưa có tài liệu kèm theo</span>
                                                    <?php else: ?>
                                                        <?php foreach ($doc_attachments as $att): ?>
                                                            <?php
                                                            $att_path = $att['attachment_path'] ?? '';
                                                            $att_exists = (!empty($att_path) && file_exists($att_path));
                                                            ?>

                                                            <div class="mb-2 small">
                                                                <div class="fw-semibold">
                                                                    <?php echo h($att['attachment_title']); ?>
                                                                </div>

                                                                <div class="text-muted">
                                                                    <?php echo h($att['attachment_type'] ?? 'Khác'); ?>
                                                                    <?php if (!empty($att['file_size'])): ?>
                                                                        | <?php echo h(formatFileSize($att['file_size'])); ?>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if ($att_exists): ?>
                                                                    <a href="<?php echo h($att_path); ?>" target="_blank" class="btn btn-outline-secondary btn-sm mt-1">
                                                                        Xem
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark mt-1">Mất file</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="fw-bold mb-2">
                                                        <i class="fas fa-link me-1 text-dark"></i>Văn bản liên quan
                                                        <span class="badge bg-dark"><?php echo count($doc_relations); ?></span>
                                                    </div>

                                                    <?php if (count($doc_relations) === 0): ?>
                                                        <span class="text-muted">Chưa có văn bản liên quan</span>
                                                    <?php else: ?>
                                                        <?php foreach ($doc_relations as $rel): ?>
                                                            <?php
                                                            $target_id = ($rel['direction'] === 'out')
                                                                ? (int)$rel['related_document_id']
                                                                : (int)$rel['source_id'];

                                                            $direction_label = ($rel['direction'] === 'out')
                                                                ? 'Liên kết tới'
                                                                : 'Được liên kết bởi';
                                                            ?>

                                                            <div class="mb-2 small">
                                                                <div>
                                                                    <span class="badge bg-secondary">
                                                                        <?php echo h($direction_label); ?>
                                                                    </span>
                                                                    <span class="badge bg-primary">
                                                                        <?php echo h($rel['relation_type']); ?>
                                                                    </span>
                                                                </div>

                                                                <a href="view_document.php?id=<?php echo $target_id; ?>" class="fw-semibold text-decoration-none">
                                                                    <?php echo h($rel['document_number']); ?>
                                                                </a>

                                                                <div class="text-muted">
                                                                    <?php echo h($rel['type_name'] ?? ''); ?>
                                                                    <?php if (!empty($rel['issued_date'])): ?>
                                                                        | <?php echo h(formatDateVN($rel['issued_date'])); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer bg-white text-end">
                                        <a href="view_document.php?id=<?php echo $doc_id; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Xem chi tiết
                                        </a>

                                        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)): ?>
                                            <a href="edit_document.php?id=<?php echo $doc_id; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-edit me-1"></i>Sửa
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>