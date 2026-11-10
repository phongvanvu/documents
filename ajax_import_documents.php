<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Kiểm tra quyền: Admin hoặc người có quyền documents.create
if (!isset($_SESSION['user_id']) || (!has_permission('documents.create') && ($_SESSION['role'] ?? '') !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này!']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$duplicate_action = trim($_POST['duplicate_action'] ?? 'skip'); // 'skip' hoặc 'update'

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn tệp CSV hợp lệ để tải lên!']);
    exit;
}

$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, ['csv', 'txt'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Định dạng tệp không được hỗ trợ. Vui lòng tải lên tệp định dạng .CSV!']);
    exit;
}

// Hàm chuẩn hóa ngày về định dạng YYYY-MM-DD
function parseDate($str) {
    $str = trim($str);
    if (empty($str)) return null;
    $str = str_replace('/', '-', $str);
    $time = strtotime($str);
    return $time ? date('Y-m-d', $time) : null;
}

// Caching danh mục để tối ưu tốc độ truy vấn
$types_cache = [];
$res_t = $conn->query("SELECT id, LOWER(TRIM(type_name)) AS tname FROM document_types");
if ($res_t) {
    while ($r = $res_t->fetch_assoc()) {
        $types_cache[$r['tname']] = (int)$r['id'];
    }
}

$auths_cache = [];
$res_a = $conn->query("SELECT id, LOWER(TRIM(authority_name)) AS aname FROM issuing_authorities");
if ($res_a) {
    while ($r = $res_a->fetch_assoc()) {
        $auths_cache[$r['aname']] = (int)$r['id'];
    }
}

// Mở đọc tệp CSV
$handle = fopen($file_tmp, 'r');
if (!$handle) {
    echo json_encode(['status' => 'error', 'message' => 'Không thể mở đọc tệp CSV trên máy chủ!']);
    exit;
}

// Bỏ qua BOM UTF-8 nếu có
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// Bỏ qua dòng tiêu đề (Header row)
fgetcsv($handle, 10000, ",");

$imported_count = 0;
$updated_count = 0;
$skipped_count = 0;
$row_index = 1;

try {
    $conn->begin_transaction();

    while (($row = fgetcsv($handle, 10000, ",")) !== false) {
        $row_index++;
        if (empty($row) || count($row) < 2) continue;

        $doc_num      = trim($row[0] ?? '');
        $type_name    = trim($row[1] ?? '');
        $auth_name    = trim($row[2] ?? '');
        $issued_date  = parseDate($row[3] ?? '');
        $eff_date     = parseDate($row[4] ?? '');
        $exp_date     = parseDate($row[5] ?? '');
        $status       = trim($row[6] ?? 'Còn hiệu lực');
        $content      = trim($row[7] ?? '');

        if (empty($doc_num)) continue;
        if (empty($content)) $content = 'Văn bản số ' . $doc_num;

        // 1. Tự động ánh xạ hoặc thêm mới Loại văn bản
        $type_id = 1;
        if (!empty($type_name)) {
            $t_lower = mb_strtolower($type_name, 'UTF-8');
            if (isset($types_cache[$t_lower])) {
                $type_id = $types_cache[$t_lower];
            } else {
                $stmt_it = $conn->prepare("INSERT INTO document_types (type_name) VALUES (?)");
                $stmt_it->bind_param("s", $type_name);
                $stmt_it->execute();
                $type_id = (int)$conn->insert_id;
                $stmt_it->close();
                $types_cache[$t_lower] = $type_id;
            }
        }

        // 2. Tự động ánh xạ hoặc thêm mới Cơ quan ban hành
        $auth_id = 1;
        if (!empty($auth_name)) {
            $a_lower = mb_strtolower($auth_name, 'UTF-8');
            if (isset($auths_cache[$a_lower])) {
                $auth_id = $auths_cache[$a_lower];
            } else {
                $stmt_ia = $conn->prepare("INSERT INTO issuing_authorities (authority_name) VALUES (?)");
                $stmt_ia->bind_param("s", $auth_name);
                $stmt_ia->execute();
                $auth_id = (int)$conn->insert_id;
                $stmt_ia->close();
                $auths_cache[$a_lower] = $auth_id;
            }
        }

        // 3. Kiểm tra trùng số hiệu
        $stmt_check = $conn->prepare("SELECT id FROM documents WHERE document_number = ? AND deleted_at IS NULL LIMIT 1");
        $stmt_check->bind_param("s", $doc_num);
        $stmt_check->execute();
        $existing = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($existing) {
            if ($duplicate_action === 'update') {
                $doc_id = (int)$existing['id'];
                $stmt_up = $conn->prepare("UPDATE documents SET document_type_id = ?, authority_id = ?, issued_date = ?, effective_date = ?, expired_date = ?, status = ?, content = ? WHERE id = ?");
                $stmt_up->bind_param("iisssssi", $type_id, $auth_id, $issued_date, $eff_date, $exp_date, $status, $content, $doc_id);
                $stmt_up->execute();
                $stmt_up->close();
                $updated_count++;
            } else {
                $skipped_count++;
            }
        } else {
            $stmt_ins = $conn->prepare("INSERT INTO documents (document_number, document_type_id, authority_id, issued_date, effective_date, expired_date, status, content) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("siisssss", $doc_num, $type_id, $auth_id, $issued_date, $eff_date, $exp_date, $status, $content);
            $stmt_ins->execute();
            $stmt_ins->close();
            $imported_count++;
        }
    }

    fclose($handle);

    // Ghi nhật ký
    $log_act = "Nhập dữ liệu hàng loạt từ CSV: Thêm mới $imported_count, Cập nhật $updated_count, Bỏ qua trùng $skipped_count văn bản";
    $l = $conn->prepare("INSERT INTO logs (user_id, action) VALUES (?, ?)");
    if ($l) {
        $l->bind_param("is", $user_id, $log_act);
        $l->execute();
        $l->close();
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Nhập dữ liệu thành công!",
        'data' => [
            'imported' => $imported_count,
            'updated'  => $updated_count,
            'skipped'  => $skipped_count
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    if (is_resource($handle)) fclose($handle);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý tại dòng ' . $row_index . ': ' . $e->getMessage()]);
}
exit;