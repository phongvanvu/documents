<?php
session_start();
include 'db.php';

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

$document_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$document_id) {
    die("ID văn bản không hợp lệ.");
}

$stmt = $conn->prepare("SELECT id, document_number, file_path FROM documents WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc || empty($doc['file_path']) || !file_exists($doc['file_path'])) {
    die("Tệp PDF không tồn tại hoặc đã bị xóa.");
}

$doc_num = $doc['document_number'] ?? '';
$user_name = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Người dùng';
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$view_time = date('d/m/Y H:i');
$watermark_text = "$user_name - $user_ip - $view_time";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem văn bản: <?= h($doc_num) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background-color: #525659; }
        .viewer-container { position: relative; width: 100vw; height: calc(100vh - 45px); }
        .pdf-frame { width: 100%; height: 100%; border: none; }
        
        /* Lớp phủ watermark bảo mật */
        .watermark-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 9999;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-content: space-around;
            overflow: hidden;
            opacity: 0.18;
        }
        .watermark-item {
            transform: rotate(-30deg);
            font-size: 1.1rem;
            font-weight: 700;
            color: #d9534f;
            user-select: none;
            white-space: nowrap;
            margin: 40px;
        }
    </style>
</head>
<body>

<div class="bg-dark text-white px-3 py-2 d-flex justify-content-between align-items-center" style="height: 45px;">
    <div class="small fw-bold text-truncate" style="max-width: 60%;">
        <i class="fas fa-file-pdf text-danger me-2"></i><?= h($doc_num) ?>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger bg-opacity-75 small"><i class="fas fa-shield-alt me-1"></i>Bảo mật nội bộ</span>
        <button onclick="window.close()" class="btn btn-sm btn-outline-light py-0 px-2">Đóng</button>
    </div>
</div>

<div class="viewer-container">
    <div class="watermark-overlay">
        <?php for ($i = 0; $i < 24; $i++): ?>
            <div class="watermark-item"><?= h($watermark_text) ?></div>
        <?php endfor; ?>
    </div>

    <iframe src="<?= h($doc['file_path']) ?>#toolbar=1" class="pdf-frame"></iframe>
</div>

</body>
</html>