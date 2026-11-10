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
function formatDateVN($d) {
    if (empty($d) || in_array($d, ['0000-00-00', '1970-01-01'], true)) return '';
    $t = strtotime($d); return $t ? date('d/m/Y', $t) : '';
}

// Bộ lọc sổ văn bản
$issued_from = trim($_GET['issued_from'] ?? '');
$issued_to = trim($_GET['issued_to'] ?? '');
$authority_id = intval($_GET['authority_id'] ?? 0);
$type_id = intval($_GET['type_id'] ?? 0);
$status = trim($_GET['status'] ?? '');

$conditions = ["d.deleted_at IS NULL"];
$params = [];
$types = "";

if ($issued_from !== '') { $conditions[] = "d.issued_date >= ?"; $params[] = $issued_from; $types .= "s"; }
if ($issued_to !== '') { $conditions[] = "d.issued_date <= ?"; $params[] = $issued_to; $types .= "s"; }
if ($authority_id > 0) { $conditions[] = "d.authority_id = ?"; $params[] = $authority_id; $types .= "i"; }
if ($type_id > 0) { $conditions[] = "d.document_type_id = ?"; $params[] = $type_id; $types .= "i"; }
if ($status !== '') { $conditions[] = "d.status = ?"; $params[] = $status; $types .= "s"; }

$where = " WHERE " . implode(" AND ", $conditions);

$sql = "SELECT d.document_number, d.content, d.issued_date, d.effective_date, d.expired_date, d.status, dt.type_name, ia.authority_name 
        FROM documents d 
        LEFT JOIN document_types dt ON d.document_type_id = dt.id 
        LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
        $where 
        ORDER BY d.issued_date DESC, d.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$authorities = $conn->query("SELECT id, authority_name FROM issuing_authorities ORDER BY authority_name")->fetch_all(MYSQLI_ASSOC);
$document_types = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sổ theo dõi văn bản pháp luật</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 14px; background-color: #f8f9fa; color: #000; }
        .page-sheet { background: #fff; width: 297mm; min-height: 210mm; margin: 20px auto; padding: 15mm 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.15); border-radius: 4px; }
        .table-print th, .table-print td { border: 1px solid #000 !important; padding: 6px 8px; vertical-align: middle; }
        .table-print thead th { background-color: #f0f0f0 !important; text-align: center; font-weight: bold; }
        @media print {
            @page { size: A4 landscape; margin: 10mm 15mm; }
            body { background: #fff !important; margin: 0; padding: 0; }
            .page-sheet { width: 100% !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
            .no-print { display: none !important; }
            .table-print th, .table-print td { font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- Thanh điều khiển & Lọc (Ẩn khi in) -->
<div class="container-fluid no-print py-3 bg-white border-bottom shadow-sm mb-3">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a href="documents.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Về danh sách</a>
        
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-0">
            <input type="date" name="issued_from" class="form-control form-control-sm" value="<?= h($issued_from) ?>" title="Từ ngày ban hành">
            <input type="date" name="issued_to" class="form-control form-control-sm" value="<?= h($issued_to) ?>" title="Đến ngày ban hành">
            
            <select name="type_id" class="form-select form-select-sm" style="width: 150px;">
                <option value="">-- Loại VB --</option>
                <?php foreach ($document_types as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($type_id == $t['id']) ? 'selected' : '' ?>><?= h($t['type_name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="form-select form-select-sm" style="width: 150px;">
                <option value="">-- Tình trạng --</option>
                <option value="Còn hiệu lực" <?= ($status === 'Còn hiệu lực') ? 'selected' : '' ?>>Còn hiệu lực</option>
                <option value="Hết hiệu lực" <?= ($status === 'Hết hiệu lực') ? 'selected' : '' ?>>Hết hiệu lực</option>
                <option value="Được thay thế" <?= ($status === 'Được thay thế') ? 'selected' : '' ?>>Được thay thế</option>
            </select>

            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-filter me-1"></i>Lọc sổ</button>
        </form>

        <button onclick="window.print()" class="btn btn-success btn-sm fw-bold px-3"><i class="fas fa-print me-1"></i>In sổ (A4 Ngang)</button>
    </div>
</div>

<!-- Khung trang in A4 -->
<div class="page-sheet">
    <div class="row mb-3">
        <div class="col-5 text-center">
            <div class="text-uppercase fw-bold">CƠ QUAN / ĐƠN VỊ CHỦ QUẢN</div>
            <div class="text-uppercase fw-bold text-decoration-underline">BỘ PHẬN PHÁP CHẾ / VĂN PHÒNG</div>
        </div>
        <div class="col-7 text-center">
            <div class="text-uppercase fw-bold">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div class="fw-bold">Độc lập - Tự do - Hạnh phúc</div>
            <div>------------------------</div>
        </div>
    </div>

    <div class="text-center my-4">
        <h3 class="fw-bold"><?= h(get_setting('register_header_title', 'SỔ THEO DÕI VĂN BẢN')) ?></h3>
        <div class="fw-semibold"><?= h(get_setting('agency_name', '')) ?></div>
        <div class="fst-italic">
            <?php if (!empty($issued_from) || !empty($issued_to)): ?>
                (Từ ngày: <?= formatDateVN($issued_from) ?: '...' ?> đến ngày: <?= formatDateVN($issued_to) ?: '...' ?>)
            <?php else: ?>
                (Tổng hợp toàn bộ văn bản trong hệ thống)
            <?php endif; ?>
        </div>
    </div>

    <table class="table table-print w-100">
        <thead>
            <tr>
                <th style="width: 40px;">STT</th>
                <th style="width: 130px;">Số ký hiệu</th>
                <th style="width: 90px;">Ngày ban hành</th>
                <th style="width: 120px;">Cơ quan ban hành</th>
                <th style="width: 100px;">Loại văn bản</th>
                <th>Trích yếu nội dung</th>
                <th style="width: 90px;">Ngày hiệu lực</th>
                <th style="width: 110px;">Tình trạng</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($docs) > 0): ?>
                <?php $i = 1; foreach ($docs as $d): ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td class="text-center fw-bold"><?= h($d['document_number']) ?></td>
                        <td class="text-center"><?= formatDateVN($d['issued_date']) ?></td>
                        <td><?= h($d['authority_name'] ?? 'N/A') ?></td>
                        <td class="text-center"><?= h($d['type_name'] ?? 'N/A') ?></td>
                        <td class="text-justify"><?= nl2br(h($d['content'])) ?></td>
                        <td class="text-center"><?= formatDateVN($d['effective_date']) ?></td>
                        <td class="text-center"><?= h($d['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center py-4 fst-italic">Không có văn bản nào phù hợp tiêu chí lọc.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-3">
        <div class="col-4 text-center">
            <div class="fw-bold text-uppercase">NGƯỜI LẬP SỔ</div>
            <div class="fst-italic text-muted mt-5">(Ký và ghi rõ họ tên)</div>
        </div>
        <div class="col-4 text-center"></div>
        <div class="col-4 text-center">
            <div class="fst-italic">Ngày ..... tháng ..... năm 20...</div>
            <div class="fw-bold text-uppercase">THỦ TRƯỞNG ĐƠN VỊ</div>
            <div class="fst-italic text-muted mt-5">(Ký, đóng dấu)</div>
        </div>
    </div>
</div>

</body>
</html>