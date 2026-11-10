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
        header('Location: login.php');
        exit;
    }
}

function h($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }

function formatDateVN($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '1970-01-01') return '';
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'viewer';

// Lấy thông tin avatar
$sql_avatar = "SELECT avatar FROM users WHERE id = ?";
$stmt_avatar = $conn->prepare($sql_avatar);
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$res_avatar = $stmt_avatar->get_result()->fetch_assoc();
$_SESSION['avatar'] = $res_avatar['avatar'] ?? "uploads/avatars/default.jpg";
$stmt_avatar->close();

// Bộ lọc & Tìm kiếm
$search_query = trim($_GET['search_query'] ?? '');
$search_column = $_GET['search_column'] ?? '';
$document_type_filter = $_GET['document_type_id'] ?? '';
$authority_filter = $_GET['authority_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$personal_filter = $_GET['personal_filter'] ?? '';
$issued_from = trim($_GET['issued_from'] ?? '');
$issued_to = trim($_GET['issued_to'] ?? '');
$effective_from = trim($_GET['effective_from'] ?? '');
$effective_to = trim($_GET['effective_to'] ?? '');
$data_filter = $_GET['data_filter'] ?? '';

$items_per_page = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$start_from = ($page - 1) * $items_per_page;

$sql_from = " FROM documents d 
    LEFT JOIN document_types dt ON d.document_type_id = dt.id 
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id ";

$conditions = ["d.deleted_at IS NULL"]; 
$params = [];
$param_types = "";

if ($search_query !== '') {
    $search_param = '%' . $search_query . '%';
    $allowed = ['document_number', 'content', 'issued_date', 'effective_date', 'status', 'file_path', 'tags', 'personal_note'];
    
    if (empty($search_column)) {
        $conditions[] = "(d.document_number LIKE ? OR d.content LIKE ? OR d.issued_date LIKE ? OR d.effective_date LIKE ? OR d.status LIKE ? OR d.file_path LIKE ? OR d.tags LIKE ? OR d.personal_note LIKE ? OR dt.type_name LIKE ? OR ia.authority_name LIKE ?)";
        $params = array_merge($params, array_fill(0, 10, $search_param));
        $param_types .= "ssssssssss";
    } elseif (in_array($search_column, $allowed, true)) {
        $conditions[] = "d.$search_column LIKE ?"; $params[] = $search_param; $param_types .= "s";
    } elseif ($search_column === 'type_name') {
        $conditions[] = "dt.type_name LIKE ?"; $params[] = $search_param; $param_types .= "s";
    } elseif ($search_column === 'authority_name') {
        $conditions[] = "ia.authority_name LIKE ?"; $params[] = $search_param; $param_types .= "s";
    }
}

if ($document_type_filter !== '') { $conditions[] = "d.document_type_id = ?"; $params[] = intval($document_type_filter); $param_types .= "i"; }
if ($authority_filter !== '') { $conditions[] = "d.authority_id = ?"; $params[] = intval($authority_filter); $param_types .= "i"; }
if ($status_filter !== '') { $conditions[] = "d.status = ?"; $params[] = $status_filter; $param_types .= "s"; }
if ($issued_from !== '') { $conditions[] = "d.issued_date >= ?"; $params[] = $issued_from; $param_types .= "s"; }
if ($issued_to !== '') { $conditions[] = "d.issued_date <= ?"; $params[] = $issued_to; $param_types .= "s"; }
if ($effective_from !== '') { $conditions[] = "d.effective_date >= ?"; $params[] = $effective_from; $param_types .= "s"; }
if ($effective_to !== '') { $conditions[] = "d.effective_date <= ?"; $params[] = $effective_to; $param_types .= "s"; }

if ($data_filter !== '') {
    if ($data_filter === 'has_attachment') $conditions[] = "EXISTS (SELECT 1 FROM document_attachments da WHERE da.document_id = d.id)";
    elseif ($data_filter === 'has_relation') $conditions[] = "EXISTS (SELECT 1 FROM document_relations dr WHERE dr.document_id = d.id OR dr.related_document_id = d.id)";
    elseif ($data_filter === 'no_tags') $conditions[] = "(d.tags IS NULL OR TRIM(d.tags) = '')";
    elseif ($data_filter === 'no_note') $conditions[] = "(d.personal_note IS NULL OR TRIM(d.personal_note) = '')";
    elseif ($data_filter === 'secret') $conditions[] = "(UPPER(d.document_number) LIKE '%-M%' OR UPPER(d.document_number) LIKE '%/M%' OR UPPER(d.document_number) LIKE '% M %' OR UPPER(d.document_number) LIKE '%MẬT%')";
}

if ($personal_filter !== '') {
    if ($personal_filter === 'favorite') $conditions[] = "d.is_favorite = 1";
    elseif ($personal_filter === 'has_note') $conditions[] = "d.personal_note IS NOT NULL AND TRIM(d.personal_note) <> ''";
    elseif ($personal_filter === 'has_tags') $conditions[] = "d.tags IS NOT NULL AND TRIM(d.tags) <> ''";
    elseif ($personal_filter === 'has_file') $conditions[] = "d.file_path IS NOT NULL AND TRIM(d.file_path) <> ''";
    elseif ($personal_filter === 'no_file') $conditions[] = "(d.file_path IS NULL OR TRIM(d.file_path) = '')";
    elseif ($personal_filter === 'pdf') $conditions[] = "LOWER(d.file_path) LIKE '%.pdf'";
    elseif ($personal_filter === 'word') $conditions[] = "(LOWER(d.file_path) LIKE '%.doc' OR LOWER(d.file_path) LIKE '%.docx')";
    elseif ($personal_filter === 'excel') $conditions[] = "(LOWER(d.file_path) LIKE '%.xls' OR LOWER(d.file_path) LIKE '%.xlsx')";
}

$where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";

// Đếm tổng số dòng
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total" . $sql_from . $where_clause);
if (!empty($params)) $stmt_count->bind_param($param_types, ...$params);
$stmt_count->execute();
$total_rows = intval($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_count->close();

// Lấy dữ liệu danh sách
$sql = "SELECT d.id, d.document_number, d.content, d.issued_date, d.effective_date, d.expired_date, d.status, d.file_path, d.is_favorite, d.tags, d.personal_note, dt.type_name, ia.authority_name" 
    . $sql_from . $where_clause 
    . " ORDER BY d.is_favorite DESC, d.issued_date DESC, d.id DESC LIMIT ? OFFSET ?";

$list_params = array_merge($params, [$items_per_page, $start_from]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types . "ii", ...$list_params);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$status_options = ['Còn hiệu lực' => 'Còn hiệu lực', 'Hết hiệu lực' => 'Hết hiệu lực', 'Được thay thế' => 'Được thay thế', 'Không xác định' => 'Không xác định', 'Chưa biết' => 'Chưa biết'];
$status_badges = ['Còn hiệu lực' => 'bg-success', 'Hết hiệu lực' => 'bg-secondary', 'Được thay thế' => 'bg-warning text-dark', 'Không xác định' => 'bg-light text-dark', 'Chưa biết' => 'bg-light text-dark'];

$quick_filters = [
    '' => ['label' => 'Tất cả', 'icon' => 'fas fa-list'], 'favorite' => ['label' => 'Quan trọng', 'icon' => 'fas fa-star'],
    'has_note' => ['label' => 'Có ghi chú', 'icon' => 'fas fa-sticky-note'], 'has_tags' => ['label' => 'Có tag', 'icon' => 'fas fa-tags'],
    'has_file' => ['label' => 'Có file', 'icon' => 'fas fa-paperclip'], 'no_file' => ['label' => 'Chưa có file', 'icon' => 'fas fa-unlink'],
    'pdf' => ['label' => 'PDF', 'icon' => 'fas fa-file-pdf'], 'word' => ['label' => 'Word', 'icon' => 'fas fa-file-word'], 'excel' => ['label' => 'Excel', 'icon' => 'fas fa-file-excel']
];

$export_url = 'export_documents.php?' . http_build_query([
    'search_query' => $search_query, 'search_column' => $search_column, 'document_type_id' => $document_type_filter,
    'authority_id' => $authority_filter, 'status' => $status_filter, 'personal_filter' => $personal_filter,
    'issued_from' => $issued_from, 'issued_to' => $issued_to, 'effective_from' => $effective_from, 'effective_to' => $effective_to, 'data_filter' => $data_filter
]);

$title = "Quản lý danh sách văn bản";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="col-md-12 mt-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0 fw-bold">Danh sách văn bản pháp luật</h4>
                        <div class="text-muted small">Tra cứu, lọc và quản lý văn bản theo số ký hiệu, loại văn bản, cơ quan ban hành... <span class="badge bg-light text-secondary border ms-1"><kbd>/</kbd> hoặc <kbd>Ctrl+K</kbd> để tìm</span></div>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (has_permission('documents.print')): ?>
                            <a href="print_register.php" target="_blank" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-print me-1"></i>In sổ theo dõi
                            </a>
                        <?php endif; ?>

                        <?php if (has_permission('documents.export')): ?>
                            <a href="<?php echo h($export_url); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel me-1"></i>Xuất Excel</a>
                        <?php endif; ?>

                        <?php if (has_permission('documents.create')): ?>
                            <a href="add_document.php" class="btn btn-sm btn-danger"><i class="fas fa-plus me-1"></i>Thêm văn bản</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Tìm kiếm -->
                <form action="documents.php" method="GET" class="mb-3">
                    <input type="hidden" name="personal_filter" value="<?php echo h($personal_filter); ?>">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <input type="text" id="mainSearchInput" name="search_query" class="form-control form-control-sm" placeholder="Tìm kiếm văn bản... (/)" value="<?php echo h($search_query); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="search_column" class="form-select form-select-sm">
                                <option value="">Tất cả các cột</option>
                                <?php 
                                $columns = ['document_number' => 'Số ký hiệu', 'content' => 'Trích yếu', 'type_name' => 'Loại văn bản', 'authority_name' => 'Cơ quan ban hành', 'issued_date' => 'Ngày ban hành', 'effective_date' => 'Ngày hiệu lực', 'status' => 'Tình trạng hiệu lực', 'tags' => 'Tag', 'personal_note' => 'Ghi chú'];
                                foreach ($columns as $val => $lbl): ?>
                                    <option value="<?php echo h($val); ?>" <?php echo ($search_column === $val) ? 'selected' : ''; ?>><?php echo h($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="document_type_id">
                                <option value="">Loại văn bản</option>
                                <?php
                                $types_result = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
                                while ($type = $types_result->fetch_assoc()): ?>
                                    <option value="<?php echo h($type['id']); ?>" <?php echo ($document_type_filter == $type['id']) ? 'selected' : ''; ?>><?php echo h($type['type_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" name="authority_id">
                                <option value="">Cơ quan ban hành</option>
                                <?php
                                $auths_result = $conn->query("SELECT id, authority_name FROM issuing_authorities ORDER BY authority_name");
                                while ($auth = $auths_result->fetch_assoc()): ?>
                                    <option value="<?php echo h($auth['id']); ?>" <?php echo ($authority_filter == $auth['id']) ? 'selected' : ''; ?>><?php echo h($auth['authority_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Hiệu lực</option>
                                <?php foreach ($status_options as $val => $lbl): ?>
                                    <option value="<?php echo h($val); ?>" <?php echo ($status_filter === $val) ? 'selected' : ''; ?>><?php echo h($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-search"></i></button></div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-md-2"><input type="date" name="issued_from" class="form-control form-control-sm" value="<?php echo h($issued_from); ?>" title="Từ ngày ban hành"></div>
                        <div class="col-md-2"><input type="date" name="issued_to" class="form-control form-control-sm" value="<?php echo h($issued_to); ?>" title="Đến ngày ban hành"></div>
                        <div class="col-md-2"><input type="date" name="effective_from" class="form-control form-control-sm" value="<?php echo h($effective_from); ?>" title="Từ ngày hiệu lực"></div>
                        <div class="col-md-2"><input type="date" name="effective_to" class="form-control form-control-sm" value="<?php echo h($effective_to); ?>" title="Đến ngày hiệu lực"></div>
                        <div class="col-md-3">
                            <select name="data_filter" class="form-select form-select-sm">
                                <option value="">Tình trạng dữ liệu</option>
                                <option value="has_attachment" <?php echo ($data_filter === 'has_attachment') ? 'selected' : ''; ?>>Có tài liệu đính kèm</option>
                                <option value="has_relation" <?php echo ($data_filter === 'has_relation') ? 'selected' : ''; ?>>Có văn bản liên quan</option>
                                <option value="no_tags" <?php echo ($data_filter === 'no_tags') ? 'selected' : ''; ?>>Chưa có tag</option>
                                <option value="no_note" <?php echo ($data_filter === 'no_note') ? 'selected' : ''; ?>>Chưa có ghi chú</option>
                                <option value="secret" <?php echo ($data_filter === 'secret') ? 'selected' : ''; ?>>Văn bản mật</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-filter"></i></button></div>
                    </div>

                    <?php if ($search_query !== '' || $search_column !== '' || $document_type_filter !== '' || $authority_filter !== '' || $status_filter !== '' || $personal_filter !== '' || $issued_from !== '' || $issued_to !== '' || $effective_from !== '' || $effective_to !== '' || $data_filter !== ''): ?>
                        <div class="mt-2">
                            <a href="documents.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i>Xóa bộ lọc</a>
                            <span class="text-muted small ms-2">Tìm thấy <?php echo h($total_rows); ?> văn bản.</span>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- Bộ lọc nhanh -->
                <div class="mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($quick_filters as $val => $item): 
                            $filter_url = 'documents.php' . ($val !== '' ? '?personal_filter=' . urlencode($val) : '');
                            $active_class = ($personal_filter === $val) ? 'btn-danger' : 'btn-outline-secondary';
                        ?>
                            <a href="<?php echo h($filter_url); ?>" class="btn <?php echo h($active_class); ?> btn-sm">
                                <i class="<?php echo h($item['icon']); ?> me-1"></i><?php echo h($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bảng Dữ Liệu -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle shadow-sm">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width: 150px;">Số ký hiệu</th>
                                <th>Trích yếu / Cơ quan ban hành</th>
                                <th style="width: 110px;">Ngày BH</th>
                                <th style="width: 110px;">Ngày HL</th>
                                <th style="width: 125px;">Hiệu lực</th>
                                <th style="width: 80px;">Tập tin</th>
                                <th style="width: 140px;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($documents) > 0): ?>
                                <?php foreach ($documents as $doc):
                                    $doc_num = $doc['document_number'] ?? '';
                                    $file_path = $doc['file_path'] ?? '';
                                    $doc_upper = mb_strtoupper($doc_num, 'UTF-8');
                                    $is_secret = (strpos($doc_upper, '-M') !== false || strpos($doc_upper, '/M') !== false || strpos($doc_upper, ' M ') !== false || strpos($doc_upper, 'MẬT') !== false);
                                    
                                    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                    $icon = '<i class="fas fa-file-alt text-secondary fs-4"></i>';
                                    if ($ext === 'pdf') $icon = '<i class="fas fa-file-pdf text-danger fs-4"></i>';
                                    elseif (in_array($ext, ['doc', 'docx'])) $icon = '<i class="fas fa-file-word text-primary fs-4"></i>';
                                    elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '<i class="fas fa-file-excel text-success fs-4"></i>';
                                    elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) $icon = '<i class="fas fa-file-image text-info fs-4"></i>';

                                    $status = trim($doc['status'] ?? 'Không xác định');
                                    $status_class = $status_badges[$status] ?? 'bg-light text-dark';
                                    $tags = array_filter(array_map('trim', explode(',', $doc['tags'] ?? '')));
                                    $personal_note = trim($doc['personal_note'] ?? '');
                                ?>
                                    <tr id="doc-row-<?php echo (int)$doc['id']; ?>">
                                        <!-- CỘT 1: SỐ KÝ HIỆU & FAVORITE & COPY -->
                                        <td class="text-center fw-semibold text-nowrap">
                                            <button type="button" class="btn btn-link p-0 border-0 text-decoration-none btn-toggle-favorite me-1" 
                                                    data-id="<?php echo (int)$doc['id']; ?>" 
                                                    title="<?php echo !empty($doc['is_favorite']) ? 'Bỏ đánh dấu quan trọng' : 'Đánh dấu quan trọng'; ?>">
                                                <i class="<?php echo !empty($doc['is_favorite']) ? 'fas fa-star text-warning' : 'far fa-star text-muted'; ?>"></i>
                                            </button>
                                            
                                            <a href="view_document.php?id=<?php echo h($doc['id']); ?>" class="text-decoration-none text-dark fw-bold me-1"><?php echo h($doc_num); ?></a>
                                            
                                            <button type="button" class="btn btn-link p-0 text-secondary border-0 btn-copy-docnum" 
                                                    data-clipboard="<?php echo h($doc_num); ?>" 
                                                    title="Sao chép số ký hiệu">
                                                <i class="far fa-copy text-muted" style="font-size: 0.85rem;"></i>
                                            </button>
                                        </td>
                                        
                                        <!-- CỘT 2: TRÍCH YẾU & GHI CHÚ -->
                                        <td class="text-start">
                                            <div class="mb-1">
                                                <a href="view_document.php?id=<?php echo h($doc['id']); ?>" class="text-decoration-none text-dark fw-semibold">
                                                    <?php echo h($doc['content']); ?>
                                                </a>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                <i class="fas fa-paste me-1 text-secondary"></i>Loại: <span class="text-dark fw-semibold"><?php echo h($doc['type_name'] ?? 'Chưa xác định'); ?></span>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="fas fa-building me-1 text-secondary"></i>Cơ quan BH: <span class="text-dark fw-semibold"><?php echo h($doc['authority_name'] ?? 'Chưa xác định'); ?></span>
                                            </div>

                                            <div class="mt-2 pt-1 border-top border-light d-flex flex-wrap align-items-center gap-1">
                                                <?php foreach ($tags as $tag): ?>
                                                    <a href="documents.php?search_query=<?php echo urlencode($tag); ?>&search_column=tags" class="badge bg-info text-dark text-decoration-none" style="font-size:0.7rem;"><?php echo h($tag); ?></a>
                                                <?php endforeach; ?>

                                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 ms-1 btn-edit-note" 
                                                        data-id="<?php echo (int)$doc['id']; ?>" 
                                                        data-docnum="<?php echo h($doc_num); ?>"
                                                        data-note="<?php echo h($personal_note); ?>"
                                                        title="Chỉnh sửa ghi chú">
                                                    <span class="note-badge-container">
                                                        <?php if (!empty($personal_note)): ?>
                                                            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning" style="font-size:0.7rem;">
                                                                <i class="fas fa-sticky-note text-warning me-1"></i><span class="note-text-preview"><?php echo h(mb_strimwidth($personal_note, 0, 30, '...')); ?></span>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border" style="font-size:0.7rem;">
                                                                <i class="far fa-sticky-note me-1"></i>+ Ghi chú
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                </button>
                                            </div>
                                        </td>

                                        <!-- CỘT 3, 4: NGÀY THÁNG -->
                                        <td class="text-center small"><?php echo h(formatDateVN($doc['issued_date'] ?? '')); ?></td>
                                        <td class="text-center small"><?php echo h(formatDateVN($doc['effective_date'] ?? '')); ?></td>
                                        
                                        <!-- CỘT 5: HIỆU LỰC -->
                                        <td class="text-center"><span class="badge <?php echo h($status_class); ?>"><?php echo h($status); ?></span></td>
                                        
                                        <!-- CỘT 6: TẬP TIN -->
                                        <td class="text-center">
                                            <?php if ($is_secret): ?>
                                                <span class="badge bg-danger p-2" data-bs-toggle="tooltip" title="Văn bản Mật"><i class="fas fa-lock fa-lg"></i></span>
                                            <?php elseif (!empty($file_path) && file_exists($file_path)): ?>
                                                <button type="button" class="btn btn-link p-0 border-0 btn-preview" 
                                                        data-file="<?= h($file_path) ?>" 
                                                        data-ext="<?= $ext ?>" 
                                                        data-title="<?= h($doc_num) ?>" 
                                                        title="Xem trước tài liệu">
                                                    <?= $icon ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-minus"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- CỘT 7: HÀNH ĐỘNG (THEO PHÂN QUYỀN RBAC) -->
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="view_document.php?id=<?php echo h($doc['id']); ?>" class="btn btn-outline-primary btn-sm px-2">Xem</a>
                                                <?php if (has_permission('documents.edit')): ?>
                                                    <a href="edit_document.php?id=<?php echo h($doc['id']); ?>" class="btn btn-outline-secondary btn-sm px-2">Sửa</a>
                                                <?php endif; ?>
                                                <?php if (has_permission('documents.delete')): ?>
                                                    <a href="#" class="btn btn-outline-danger btn-sm px-2 delete-btn" data-id="<?php echo h($doc['id']); ?>">Xóa</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-search fa-2x mb-3 text-light"></i><br>Không tìm thấy văn bản nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php 
                $total_pages = (int)ceil($total_rows / $items_per_page);
                if ($total_pages > 1): 
                    $base_url = '?' . http_build_query(['search_query' => $search_query, 'search_column' => $search_column, 'document_type_id' => $document_type_filter, 'authority_id' => $authority_filter, 'status' => $status_filter, 'personal_filter' => $personal_filter, 'issued_from' => $issued_from, 'issued_to' => $issued_to, 'effective_from' => $effective_from, 'effective_to' => $effective_to, 'data_filter' => $data_filter]) . '&page=';
                ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo h($base_url . '1'); ?>">&laquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?php echo h($base_url . ($page - 1)); ?>">&lsaquo;</a></li>
                            <?php endif; ?>
                            <?php
                            $start = max(1, $page - 2); $end = min($total_pages, $start + 4);
                            if ($end - $start < 4) $start = max(1, $end - 4);
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($i == $page) ? '#' : h($base_url . $i); ?>"><?php echo h($i); ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo h($base_url . ($page + 1)); ?>">&rsaquo;</a></li>
                                <li class="page-item"><a class="page-link" href="<?php echo h($base_url . $total_pages); ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Xem trước tài liệu -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="height: 90vh;">
        <div class="modal-content h-100 border-0 shadow-lg rounded-3">
            <div class="modal-header bg-danger text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="previewModalTitle">
                    <i class="fas fa-file-alt me-2"></i>Xem trước văn bản
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" id="btnDownloadFile" class="btn btn-sm btn-light" download title="Tải file về máy">
                        <i class="fas fa-download me-1"></i>Tải về
                    </a>
                    <a href="#" id="btnOpenNewTab" target="_blank" class="btn btn-sm btn-outline-light" title="Mở trong tab mới">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 d-flex justify-content-center align-items-center bg-light" id="previewContainer" style="overflow: hidden;"></div>
        </div>
    </div>
</div>

<!-- Modal Ghi chú nhanh -->
<div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header bg-danger text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="noteModalTitle">
                    <i class="fas fa-sticky-note me-2"></i>Ghi chú cá nhân
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <form id="formQuickNote">
                    <input type="hidden" id="noteDocId" name="id" value="">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-secondary mb-1">Nội dung ghi chú / Lưu ý:</label>
                        <textarea class="form-control" id="noteTextarea" name="personal_note" rows="4" placeholder="Nhập ghi chú cho văn bản này..."></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnClearNote"><i class="fas fa-trash-alt me-1"></i>Xóa ghi chú</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-sm btn-danger px-3" id="btnSaveNote"><i class="fas fa-save me-1"></i>Lưu lại</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Nút Back to Top -->
<button type="button" id="btnBackToTop" class="btn btn-danger btn-sm rounded-circle shadow position-fixed" style="bottom: 25px; right: 25px; width: 42px; height: 42px; display: none; z-index: 1050;" title="Lên đầu trang">
    <i class="fas fa-chevron-up"></i>
</button>

<style>
.search-highlight { background-color: #fff3cd; color: #856404; padding: 0 3px; border-radius: 3px; font-weight: 700; }
#btnBackToTop { transition: all 0.3s ease-in-out; }
#btnBackToTop:hover { transform: translateY(-3px); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));
    }

    <?php if (isset($_SESSION['flash_msg'])): ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: <?php echo json_encode($_SESSION['flash_msg'], JSON_UNESCAPED_UNICODE); ?>, showConfirmButton: false, timer: 3000 });
        }
        <?php unset($_SESSION['flash_msg']); ?>
    <?php endif; ?>

    // Phím tắt tìm kiếm ('/' hoặc 'Ctrl + K')
    document.addEventListener('keydown', function(e) {
        if ((e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') || (e.ctrlKey && e.key.toLowerCase() === 'k')) {
            e.preventDefault();
            const searchInput = document.getElementById('mainSearchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // 1-Click Copy số hiệu
    document.querySelectorAll('.btn-copy-docnum').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const textToCopy = this.getAttribute('data-clipboard');
            const icon = this.querySelector('i');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(() => {
                icon.className = 'fas fa-check text-success';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Đã chép: ${textToCopy}`, showConfirmButton: false, timer: 1500 });
                }
                setTimeout(() => { icon.className = 'far fa-copy text-muted'; }, 1500);
            });
        });
    });

    // Toggle Favorite AJAX
    document.querySelectorAll('.btn-toggle-favorite').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const button = this;
            const docId = button.getAttribute('data-id');
            const icon = button.querySelector('i');

            button.disabled = true;
            const formData = new FormData();
            formData.append('id', docId);

            fetch('ajax_toggle_favorite.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.is_favorite === 1) {
                        icon.className = 'fas fa-star text-warning';
                        button.setAttribute('title', 'Bỏ đánh dấu quan trọng');
                    } else {
                        icon.className = 'far fa-star text-muted';
                        button.setAttribute('title', 'Đánh dấu quan trọng');
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
                    }
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .finally(() => { button.disabled = false; });
        });
    });

    // Ghi chú nhanh AJAX
    const noteModalEl = document.getElementById('noteModal');
    const noteModal = noteModalEl ? new bootstrap.Modal(noteModalEl) : null;
    const noteDocId = document.getElementById('noteDocId');
    const noteTextarea = document.getElementById('noteTextarea');
    const noteModalTitle = document.getElementById('noteModalTitle');
    const btnSaveNote = document.getElementById('btnSaveNote');
    const btnClearNote = document.getElementById('btnClearNote');
    let currentNoteBtn = null;

    document.querySelectorAll('.btn-edit-note').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentNoteBtn = this;
            const docId = this.getAttribute('data-id');
            const docNum = this.getAttribute('data-docnum');
            const note = this.getAttribute('data-note') || '';

            noteDocId.value = docId;
            noteTextarea.value = note;
            noteModalTitle.innerHTML = `<i class="fas fa-sticky-note me-2"></i>Ghi chú: <strong>${docNum}</strong>`;

            if (noteModal) noteModal.show();
        });
    });

    function submitNoteUpdate(noteContent) {
        if (!currentNoteBtn) return;
        btnSaveNote.disabled = true;
        btnSaveNote.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';

        const formData = new FormData();
        formData.append('id', noteDocId.value);
        formData.append('personal_note', noteContent);

        fetch('ajax_update_note.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                currentNoteBtn.setAttribute('data-note', noteContent);
                const container = currentNoteBtn.querySelector('.note-badge-container');

                if (noteContent.trim() !== '') {
                    const shortText = noteContent.length > 30 ? noteContent.substring(0, 30) + '...' : noteContent;
                    container.innerHTML = `
                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning" style="font-size:0.7rem;">
                            <i class="fas fa-sticky-note text-warning me-1"></i><span class="note-text-preview">${shortText}</span>
                        </span>`;
                } else {
                    container.innerHTML = `
                        <span class="badge bg-light text-muted border" style="font-size:0.7rem;">
                            <i class="far fa-sticky-note me-1"></i>+ Ghi chú
                        </span>`;
                }

                if (noteModal) noteModal.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
                }
            } else {
                alert(data.message || 'Lỗi khi lưu ghi chú');
            }
        })
        .finally(() => {
            btnSaveNote.disabled = false;
            btnSaveNote.innerHTML = '<i class="fas fa-save me-1"></i>Lưu lại';
        });
    }

    const formQuickNote = document.getElementById('formQuickNote');
    if (formQuickNote) {
        formQuickNote.addEventListener('submit', function(e) {
            e.preventDefault();
            submitNoteUpdate(noteTextarea.value.trim());
        });
    }

    if (btnClearNote) {
        btnClearNote.addEventListener('click', function() {
            noteTextarea.value = '';
            submitNoteUpdate('');
        });
    }

    // Xem trước tài liệu (Preview Modal)
    const previewModalEl = document.getElementById('previewModal');
    if (previewModalEl) {
        const previewModal = new bootstrap.Modal(previewModalEl);
        const previewContainer = document.getElementById('previewContainer');
        const previewTitle = document.getElementById('previewModalTitle');
        const btnDownload = document.getElementById('btnDownloadFile');
        const btnOpenNewTab = document.getElementById('btnOpenNewTab');

        document.querySelectorAll('.btn-preview').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const filePath = this.getAttribute('data-file');
                const fileExt = this.getAttribute('data-ext');
                const docTitle = this.getAttribute('data-title');

                previewTitle.innerHTML = `<i class="fas fa-file-alt me-2"></i>Xem trước: <strong>${docTitle}</strong>`;
                btnDownload.href = filePath;
                btnOpenNewTab.href = filePath;

                if (fileExt === 'pdf') {
                    previewContainer.innerHTML = `<iframe src="${filePath}#toolbar=1" width="100%" height="100%" style="border: none;"></iframe>`;
                } else if (['png', 'jpg', 'jpeg', 'gif'].includes(fileExt)) {
                    previewContainer.innerHTML = `
                        <div class="p-3 text-center w-100 h-100 d-flex align-items-center justify-content-center" style="overflow: auto;">
                            <img src="${filePath}" class="img-fluid rounded shadow-sm" style="max-height: 100%; object-fit: contain;">
                        </div>`;
                } else {
                    previewContainer.innerHTML = `
                        <div class="text-center p-4">
                            <i class="fas fa-file-alt fa-4x mb-3 text-secondary"></i>
                            <h5 class="fw-bold mt-2">Định dạng .${fileExt.toUpperCase()}</h5>
                            <a href="${filePath}" class="btn btn-outline-danger px-4" download>
                                <i class="fas fa-download me-1"></i>Tải xuống tệp
                            </a>
                        </div>`;
                }

                previewModal.show();
            });
        });

        previewModalEl.addEventListener('hidden.bs.modal', function () {
            previewContainer.innerHTML = '';
        });
    }

    // Highlighting từ khóa
    const searchQuery = <?= json_encode($search_query, JSON_UNESCAPED_UNICODE) ?>;
    if (searchQuery && searchQuery.trim().length > 0) {
        const keyword = searchQuery.trim();
        const regex = new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        document.querySelectorAll('.table tbody tr td a, .table tbody tr td span.note-text-preview').forEach(el => {
            if (el.children.length === 0 && el.textContent.trim() !== '') {
                el.innerHTML = el.textContent.replace(regex, '<mark class="search-highlight">$1</mark>');
            }
        });
    }

    // Nút Back to Top
    const btnBackToTop = document.getElementById('btnBackToTop');
    if (btnBackToTop) {
        window.addEventListener('scroll', function() {
            btnBackToTop.style.display = (window.scrollY > 250) ? 'block' : 'none';
        });
        btnBackToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Xóa văn bản
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            let id = this.getAttribute('data-id');
            let url = 'delete_document.php?id=' + encodeURIComponent(id);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Chuyển vào thùng rác?', 
                    text: 'Văn bản này sẽ được chuyển vào thùng rác.', 
                    icon: 'warning',
                    showCancelButton: true, 
                    confirmButtonColor: '#d33', 
                    cancelButtonColor: '#6c757d', 
                    confirmButtonText: 'Đồng ý, xóa!', 
                    cancelButtonText: 'Không'
                }).then((res) => { if (res.isConfirmed) window.location.href = url; });
            } else {
                if (confirm('Bạn có chắc chắn muốn xóa văn bản này?')) window.location.href = url;
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>