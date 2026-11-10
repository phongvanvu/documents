<?php
// Bật đệm đầu ra để loại bỏ mọi khoảng trắng/ký tự thừa trước JSON
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

// Tắt thông báo warning đè lên JSON
error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';

// Khôi phục session nếu có cookie
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['username'], $_COOKIE['user_id'])) {
        $_SESSION['user_name'] = $_COOKIE['username'];
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['role'] = $_COOKIE['role'] ?? 'viewer';
    }
}

$doc_num = trim($_GET['document_number'] ?? '');

// Xóa sạch buffer trước khi xuất JSON
ob_clean();

if ($doc_num === '' || mb_strlen($doc_num, 'UTF-8') < 2) {
    echo json_encode([
        'status' => 'success', 
        'exists' => false, 
        'count' => 0, 
        'documents' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Tìm kiếm chính xác hoặc chứa chuỗi số hiệu
    $search_like = '%' . $doc_num . '%';

    $sql = "SELECT d.id, d.document_number, d.content, d.issued_date, d.status, dt.type_name, ia.authority_name 
            FROM documents d 
            LEFT JOIN document_types dt ON d.document_type_id = dt.id 
            LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
            WHERE d.deleted_at IS NULL 
              AND (d.document_number LIKE ? OR d.document_number = ?)
            ORDER BY (d.document_number = ?) DESC, d.issued_date DESC, d.id DESC 
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Lỗi prepare SQL: " . $conn->error);
    }

    $stmt->bind_param("sss", $search_like, $doc_num, $doc_num);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    $documents = [];
    foreach ($results as $r) {
        $documents[] = [
            'id' => (int)$r['id'],
            'document_number' => $r['document_number'] ?? '',
            'content' => !empty($r['content']) ? mb_strimwidth($r['content'], 0, 85, '...') : '(Chưa có trích yếu)',
            'authority_name' => $r['authority_name'] ?? 'Chưa rõ',
            'issued_date_formatted' => (!empty($r['issued_date']) && $r['issued_date'] !== '0000-00-00') 
                ? date('d/m/Y', strtotime($r['issued_date'])) 
                : 'Chưa rõ',
            'status' => $r['status'] ?? 'Không xác định'
        ];
    }

    echo json_encode([
        'status' => 'success',
        'exists' => count($documents) > 0,
        'count' => count($documents),
        'documents' => $documents
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;