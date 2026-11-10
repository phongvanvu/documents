<?php
session_start();
include 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Helper trả về JSON và kết thúc script
function json_res($status, $data = []) {
    $res = array_merge(['status' => $status], is_string($data) ? ['message' => $data] : $data);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Kiểm tra đăng nhập (Khôi phục cookie nếu có)
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'], $_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    } else {
        json_res('error', 'Bạn chưa đăng nhập.');
    }
}

// 2. Nhận dữ liệu tìm kiếm
$cur_doc_id = filter_input(INPUT_GET, 'document_id', FILTER_VALIDATE_INT);
$query = trim($_GET['q'] ?? '');

if (!$cur_doc_id) {
    json_res('error', 'ID văn bản hiện tại không hợp lệ.');
}

if ($query === '') {
    json_res('success', ['documents' => []]);
}

$search_param = '%' . $query . '%';

// 3. Truy vấn danh sách văn bản phù hợp (Loại trừ văn bản hiện tại VÀ văn bản đã liên kết sẵn)
$sql = "
    SELECT 
        d.id,
        d.document_number,
        d.content,
        d.issued_date,
        d.status,
        dt.type_name,
        ia.authority_name
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.id
    LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id
    WHERE d.id <> ?
      AND NOT EXISTS (
          SELECT 1 FROM document_relations dr 
          WHERE (dr.document_id = ? AND dr.related_document_id = d.id)
             OR (dr.document_id = d.id AND dr.related_document_id = ?)
      )
      AND (
            d.document_number LIKE ?
            OR d.content LIKE ?
            OR d.status LIKE ?
            OR d.tags LIKE ?
            OR d.personal_note LIKE ?
            OR dt.type_name LIKE ?
            OR ia.authority_name LIKE ?
      )
    ORDER BY d.issued_date DESC, d.id DESC
    LIMIT 30
";

try {
    $stmt = $conn->prepare($sql);
    
    // Tự động tạo mảng tham số (3 tham số ID + 7 tham số tìm kiếm)
    $params = array_merge([$cur_doc_id, $cur_doc_id, $cur_doc_id], array_fill(0, 7, $search_param));
    $stmt->bind_param("iiisssssss", ...$params);

    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    json_res('success', ['documents' => $documents]);
} catch (mysqli_sql_exception $e) {
    error_log("search_related_documents error: " . $e->getMessage());
    json_res('error', 'Không thể tìm kiếm văn bản liên quan.');
}