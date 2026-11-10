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

function lowerText($text) {
    $text = trim((string)$text);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }

    return strtolower($text);
}

function splitTags($tags) {
    $items = explode(',', (string)$tags);
    $result = [];

    foreach ($items as $item) {
        $item = trim($item);

        if ($item === '') {
            continue;
        }

        $key = lowerText($item);

        if (!isset($result[$key])) {
            $result[$key] = $item;
        }
    }

    return $result;
}

function extractKeywords($text, $limit = 20) {
    $text = lowerText($text);

    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

    $stopwords = [
        'và', 'hoặc', 'của', 'cho', 'các', 'theo', 'trong', 'về', 'với', 'tại',
        'đến', 'từ', 'này', 'đó', 'được', 'bị', 'có', 'không', 'một', 'những',
        'ngày', 'tháng', 'năm', 'số', 'văn', 'bản', 'pháp', 'luật', 'quy', 'định',
        'quyet', 'dinh', 'van', 'ban', 'phap', 'luat', 'ngay', 'thang', 'nam',
        'the', 'this', 'and', 'or', 'of', 'to', 'for', 'in', 'on', 'with'
    ];

    $counts = [];

    foreach ($parts as $word) {
        $word = trim($word);

        if ($word === '') {
            continue;
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($word, 'UTF-8') < 3) {
                continue;
            }
        } else {
            if (strlen($word) < 3) {
                continue;
            }
        }

        if (in_array($word, $stopwords, true)) {
            continue;
        }

        if (!isset($counts[$word])) {
            $counts[$word] = 0;
        }

        $counts[$word]++;
    }

    arsort($counts);

    return array_slice(array_keys($counts), 0, $limit);
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

function render_error_page($message) {
    $title = 'Lỗi gợi ý văn bản liên quan';
    include 'header.php';

    echo '<div class="container-fluid main"><div class="row flex-nowrap">';
    echo '<div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">';
    include 'sidebar.php';
    echo '</div>';
    echo '<div id="content" class="content col py-3">';
    echo '<div class="container-fluid mt-5">';
    echo '<div class="card shadow-sm border-0 mx-auto" style="max-width: 650px;">';
    echo '<div class="card-body text-center p-5">';
    echo '<i class="fas fa-exclamation-triangle text-warning fa-4x mb-3"></i>';
    echo '<h4 class="fw-bold text-dark">Không thể tạo gợi ý</h4>';
    echo '<p class="text-muted">' . h($message) . '</p>';
    echo '<a href="dashboard.php" class="btn btn-primary mt-3">';
    echo '<i class="fas fa-arrow-left me-2"></i>Về danh sách';
    echo '</a>';
    echo '</div></div></div></div></div></div>';

    include 'footer.php';
    exit;
}

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$document_id) {
    render_error_page('ID văn bản không hợp lệ.');
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

// Lay van ban hien tai
$sql_current = "
    SELECT
        d.*,
        dt.type_name,
        ia.authority_name
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    WHERE d.id = ?
";

$stmt_current = $conn->prepare($sql_current);
$stmt_current->bind_param("i", $document_id);
$stmt_current->execute();
$result_current = $stmt_current->get_result();
$current_document = $result_current->fetch_assoc();
$stmt_current->close();

if (!$current_document) {
    render_error_page('Văn bản này không tồn tại hoặc đã bị xóa.');
}

$current_tags = splitTags($current_document['tags'] ?? '');
$current_keywords = extractKeywords(
    ($current_document['document_number'] ?? '') . ' ' . ($current_document['content'] ?? '') . ' ' . ($current_document['personal_note'] ?? ''),
    25
);

// Lay danh sach ID da lien ket de loai tru
$linked_ids = [];
$linked_ids[(int)$document_id] = true;

$sql_linked = "
    SELECT related_document_id AS linked_id
    FROM document_relations
    WHERE document_id = ?

    UNION

    SELECT document_id AS linked_id
    FROM document_relations
    WHERE related_document_id = ?
";

$stmt_linked = $conn->prepare($sql_linked);
$stmt_linked->bind_param("ii", $document_id, $document_id);
$stmt_linked->execute();
$result_linked = $stmt_linked->get_result();

while ($row = $result_linked->fetch_assoc()) {
    $linked_ids[(int)$row['linked_id']] = true;
}

$stmt_linked->close();

// Tao dieu kien truy van ung vien
$conditions = [
    "d.id <> ?"
];

$params = [$document_id];
$param_types = "i";

$or_conditions = [];

if (!empty($current_document['document_type_id'])) {
    $or_conditions[] = "d.document_type_id = ?";
    $params[] = intval($current_document['document_type_id']);
    $param_types .= "i";
}

if (!empty($current_document['authority_id'])) {
    $or_conditions[] = "d.authority_id = ?";
    $params[] = intval($current_document['authority_id']);
    $param_types .= "i";
}

foreach ($current_tags as $tag_name) {
    $or_conditions[] = "d.tags LIKE ?";
    $params[] = '%' . $tag_name . '%';
    $param_types .= "s";
}

$keyword_for_sql = array_slice($current_keywords, 0, 8);

foreach ($keyword_for_sql as $keyword) {
    $or_conditions[] = "d.content LIKE ?";
    $params[] = '%' . $keyword . '%';
    $param_types .= "s";

    $or_conditions[] = "d.document_number LIKE ?";
    $params[] = '%' . $keyword . '%';
    $param_types .= "s";
}

if (count($or_conditions) > 0) {
    $conditions[] = "(" . implode(" OR ", $or_conditions) . ")";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Lay ung vien
$sql_candidates = "
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
        d.document_type_id,
        d.authority_id,
        dt.type_name,
        ia.authority_name
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    $where_clause
    ORDER BY d.issued_date DESC, d.id DESC
    LIMIT 300
";

$stmt_candidates = $conn->prepare($sql_candidates);
$stmt_candidates->bind_param($param_types, ...$params);
$stmt_candidates->execute();
$result_candidates = $stmt_candidates->get_result();

$candidates = [];

while ($row = $result_candidates->fetch_assoc()) {
    $candidate_id = (int)$row['id'];

    if (isset($linked_ids[$candidate_id])) {
        continue;
    }

    $score = 0;
    $reasons = [];

    // Diem theo tag
    $candidate_tags = splitTags($row['tags'] ?? '');
    $shared_tags = [];

    foreach ($current_tags as $key => $tag_name) {
        if (isset($candidate_tags[$key])) {
            $shared_tags[] = $tag_name;
        }
    }

    if (count($shared_tags) > 0) {
        $tag_score = min(60, count($shared_tags) * 25);
        $score += $tag_score;
        $reasons[] = 'Trùng tag: ' . implode(', ', $shared_tags);
    }

    // Diem theo loai van ban
    if (
        !empty($current_document['document_type_id'])
        && !empty($row['document_type_id'])
        && (int)$current_document['document_type_id'] === (int)$row['document_type_id']
    ) {
        $score += 12;
        $reasons[] = 'Cùng loại văn bản';
    }

    // Diem theo co quan ban hanh
    if (
        !empty($current_document['authority_id'])
        && !empty($row['authority_id'])
        && (int)$current_document['authority_id'] === (int)$row['authority_id']
    ) {
        $score += 10;
        $reasons[] = 'Cùng cơ quan ban hành';
    }

    // Diem theo tu khoa trung lap
    $candidate_keywords = extractKeywords(
        ($row['document_number'] ?? '') . ' ' . ($row['content'] ?? '') . ' ' . ($row['personal_note'] ?? ''),
        35
    );

    $overlap_keywords = array_values(array_intersect($current_keywords, $candidate_keywords));

    if (count($overlap_keywords) > 0) {
        $overlap_limited = array_slice($overlap_keywords, 0, 6);
        $keyword_score = min(30, count($overlap_keywords) * 5);
        $score += $keyword_score;
        $reasons[] = 'Trùng từ khóa: ' . implode(', ', $overlap_limited);
    }

    // Diem nho theo tinh trang hieu luc
    $current_status = trim($current_document['status'] ?? '');
    $candidate_status = trim($row['status'] ?? '');

    if ($current_status !== '' && $current_status === $candidate_status) {
        $score += 3;
        $reasons[] = 'Cùng tình trạng hiệu lực';
    }

    if ($score <= 0) {
        continue;
    }

    $confidence = 'Thấp';

    if ($score >= 60) {
        $confidence = 'Cao';
    } elseif ($score >= 30) {
        $confidence = 'Trung bình';
    }

    $suggested_relation_type = 'Liên quan nghiệp vụ';

    if (count($shared_tags) > 0) {
        $suggested_relation_type = 'Liên quan nghiệp vụ';
    } elseif (
        !empty($current_document['document_type_id'])
        && !empty($row['document_type_id'])
        && (int)$current_document['document_type_id'] === (int)$row['document_type_id']
    ) {
        $suggested_relation_type = 'Liên quan';
    }

    $row['score'] = $score;
    $row['confidence'] = $confidence;
    $row['reasons'] = $reasons;
    $row['suggested_relation_type'] = $suggested_relation_type;

    $candidates[] = $row;
}

$stmt_candidates->close();

usort($candidates, function($a, $b) {
    if ($a['score'] === $b['score']) {
        return strcmp((string)($b['issued_date'] ?? ''), (string)($a['issued_date'] ?? ''));
    }

    return $b['score'] <=> $a['score'];
});

$suggestions = array_slice($candidates, 0, 20);

$relation_type_options = [
    'Liên quan',
    'Căn cứ pháp lý',
    'Sửa đổi',
    'Bổ sung',
    'Thay thế',
    'Bị thay thế',
    'Hướng dẫn',
    'Được hướng dẫn bởi',
    'Liên quan nghiệp vụ'
];

$title = 'Gợi ý văn bản liên quan';
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
                            <i class="fas fa-magic me-2 text-danger"></i>Gợi ý tự động văn bản liên quan
                        </h4>
                        <div class="text-muted small">
                            Hệ thống tự phân tích tag, loại văn bản, cơ quan ban hành và từ khóa trong trích yếu để đề xuất liên kết.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="view_document.php?id=<?php echo (int)$document_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Về chi tiết
                        </a>

                        <a href="suggest_document_relations.php?id=<?php echo (int)$document_id; ?>" class="btn btn-danger">
                            <i class="fas fa-sync-alt me-1"></i>Làm mới gợi ý
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash_msg'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-1"></i>
                        <?php echo h($_SESSION['flash_msg']); ?>
                    </div>
                    <?php unset($_SESSION['flash_msg']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo h($_SESSION['flash_error']); ?>
                    </div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light fw-bold">
                        <i class="fas fa-file-alt me-2 text-primary"></i>Văn bản đang phân tích
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="fw-bold fs-5 text-primary">
                                    <?php echo h($current_document['document_number']); ?>
                                </div>

                                <div class="mt-2">
                                    <?php echo h($current_document['content']); ?>
                                </div>

                                <?php $current_tag_items = splitTags($current_document['tags'] ?? ''); ?>

                                <?php if (count($current_tag_items) > 0): ?>
                                    <div class="mt-3">
                                        <?php foreach ($current_tag_items as $tag_item): ?>
                                            <span class="badge bg-info text-dark me-1">
                                                <?php echo h($tag_item); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th class="text-muted">Loại</th>
                                        <td><?php echo h($current_document['type_name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Cơ quan</th>
                                        <td><?php echo h($current_document['authority_name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Ngày BH</th>
                                        <td><?php echo h(formatDateVN($current_document['issued_date'] ?? '')); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Hiệu lực</th>
                                        <td>
                                            <span class="badge <?php echo h(getStatusBadge($current_document['status'] ?? '')); ?>">
                                                <?php echo h($current_document['status'] ?? 'Không xác định'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if (count($current_keywords) > 0): ?>
                            <div class="mt-3 small text-muted">
                                <span class="fw-semibold">Từ khóa phân tích:</span>
                                <?php echo h(implode(', ', array_slice($current_keywords, 0, 15))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div class="fw-bold">
                            <i class="fas fa-lightbulb me-2 text-warning"></i>Danh sách gợi ý
                        </div>

                        <span class="badge bg-danger">
                            <?php echo count($suggestions); ?> gợi ý
                        </span>
                    </div>

                    <div class="card-body">
                        <?php if (count($suggestions) === 0): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-lightbulb fa-3x mb-3 text-light"></i>
                                <h5>Chưa tìm thấy gợi ý phù hợp</h5>
                                <p class="mb-0">
                                    Văn bản này có thể chưa có tag, ít từ khóa đặc trưng, hoặc các văn bản liên quan đã được liên kết trước đó.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 135px;" class="text-center">Độ phù hợp</th>
                                            <th>Văn bản được gợi ý</th>
                                            <th style="width: 230px;">Lý do gợi ý</th>
                                            <th style="width: 220px;">Thêm liên kết</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($suggestions as $item): ?>
                                            <?php
                                            $confidence_class = 'bg-secondary';

                                            if ($item['confidence'] === 'Cao') {
                                                $confidence_class = 'bg-success';
                                            } elseif ($item['confidence'] === 'Trung bình') {
                                                $confidence_class = 'bg-warning text-dark';
                                            }

                                            $note_text = 'Gợi ý tự động: ' . implode('; ', $item['reasons']);
                                            ?>

                                            <tr>
                                                <td class="text-center">
                                                    <span class="badge <?php echo h($confidence_class); ?> fs-6">
                                                        <?php echo h($item['confidence']); ?>
                                                    </span>

                                                    <div class="small text-muted mt-1">
                                                        Điểm: <?php echo h($item['score']); ?>
                                                    </div>
                                                </td>

                                                <td>
                                                    <div class="fw-bold">
                                                        <?php if (!empty($item['is_favorite'])): ?>
                                                            <i class="fas fa-star text-warning me-1"></i>
                                                        <?php endif; ?>

                                                        <a href="view_document.php?id=<?php echo (int)$item['id']; ?>" class="text-decoration-none">
                                                            <?php echo h($item['document_number']); ?>
                                                        </a>
                                                    </div>

                                                    <div class="small text-muted mt-1">
                                                        <?php echo h($item['type_name'] ?? ''); ?>
                                                        <?php if (!empty($item['authority_name'])): ?>
                                                            | <?php echo h($item['authority_name']); ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['issued_date'])): ?>
                                                            | Ngày BH: <?php echo h(formatDateVN($item['issued_date'])); ?>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="mt-2">
                                                        <?php echo h($item['content']); ?>
                                                    </div>

                                                    <?php $candidate_tags = splitTags($item['tags'] ?? ''); ?>

                                                    <?php if (count($candidate_tags) > 0): ?>
                                                        <div class="mt-2">
                                                            <?php foreach ($candidate_tags as $tag_item): ?>
                                                                <span class="badge bg-info text-dark me-1">
                                                                    <?php echo h($tag_item); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php foreach ($item['reasons'] as $reason): ?>
                                                        <div class="small border-bottom py-1">
                                                            <i class="fas fa-check text-success me-1"></i>
                                                            <?php echo h($reason); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </td>

                                                <td>
                                                    <form action="add_document_relation.php" method="POST" class="suggest-add-form">
                                                        <input type="hidden" name="document_id" value="<?php echo (int)$document_id; ?>">
                                                        <input type="hidden" name="related_document_id" value="<?php echo (int)$item['id']; ?>">

                                                        <div class="mb-2">
                                                            <select name="relation_type" class="form-select form-select-sm">
                                                                <?php foreach ($relation_type_options as $type): ?>
                                                                    <option value="<?php echo h($type); ?>"
                                                                        <?php echo ($type === $item['suggested_relation_type']) ? 'selected' : ''; ?>>
                                                                        <?php echo h($type); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-2">
                                                            <input type="text"
                                                                   name="note"
                                                                   class="form-control form-control-sm"
                                                                   value="<?php echo h($note_text); ?>">
                                                        </div>

                                                        <button type="submit" class="btn btn-danger btn-sm w-100">
                                                            <i class="fas fa-plus me-1"></i>Thêm liên kết
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.suggest-add-form').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Thêm liên kết?',
                    text: 'Hệ thống sẽ thêm văn bản này vào danh sách văn bản liên quan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Thêm',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            } else {
                if (confirm('Thêm văn bản này vào danh sách văn bản liên quan?')) {
                    form.submit();
                }
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>