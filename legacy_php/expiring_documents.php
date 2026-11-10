<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function h($val) { return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8'); }

// Lấy danh sách văn bản sắp hết hạn trong 30 ngày tới
$sql = "SELECT d.id, d.document_number, d.content, d.expired_date, dt.type_name, ia.authority_name, DATEDIFF(d.expired_date, CURDATE()) as days_left 
        FROM documents d 
        LEFT JOIN document_types dt ON d.document_type_id = dt.id 
        LEFT JOIN issuing_authorities ia ON d.authority_id = ia.id 
        WHERE d.deleted_at IS NULL 
        AND d.expired_date IS NOT NULL 
        AND d.expired_date >= CURDATE() 
        AND d.expired_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        AND d.status != 'Hết hiệu lực'
        ORDER BY d.expired_date ASC";

$expiring_docs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$title = "Cảnh báo văn bản sắp hết hạn";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-3 mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0 text-dark fw-bold"><i class="fas fa-bell text-warning me-2"></i>Văn bản sắp hết hiệu lực</h4>
                        <div class="text-muted small mt-1">Danh sách các văn bản sẽ hết hạn trong vòng 30 ngày tới cần xem xét thay thế/gia hạn.</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="width: 150px;">Số ký hiệu</th>
                                        <th>Trích yếu / Cơ quan ban hành</th>
                                        <th class="text-center" style="width: 150px;">Ngày hết hạn</th>
                                        <th class="text-center" style="width: 150px;">Thời gian còn lại</th>
                                        <th class="text-center" style="width: 120px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($expiring_docs) > 0): ?>
                                        <?php foreach ($expiring_docs as $doc): 
                                            $days_left = (int)$doc['days_left'];
                                            // Đổi màu cảnh báo tùy theo số ngày còn lại
                                            if ($days_left <= 7) {
                                                $badge_color = 'bg-danger';
                                                $text_color = 'text-danger fw-bold';
                                            } elseif ($days_left <= 15) {
                                                $badge_color = 'bg-warning text-dark';
                                                $text_color = 'text-warning fw-bold';
                                            } else {
                                                $badge_color = 'bg-info text-dark';
                                                $text_color = 'text-primary';
                                            }
                                        ?>
                                            <tr>
                                                <td class="ps-4 fw-bold">
                                                    <a href="view_document.php?id=<?= h($doc['id']) ?>" class="text-decoration-none text-dark">
                                                        <?= h($doc['document_number']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="text-truncate mb-1" style="max-width: 450px;" title="<?= h($doc['content']) ?>">
                                                        <?= h($doc['content']) ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?= h($doc['type_name']) ?> - <?= h($doc['authority_name']) ?>
                                                    </div>
                                                </td>
                                                <td class="text-center <?= $text_color ?>">
                                                    <?= date('d/m/Y', strtotime($doc['expired_date'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $badge_color ?> px-3 py-2 rounded-pill fs-6 shadow-sm">
                                                        Còn <?= $days_left ?> ngày
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="edit_document.php?id=<?= h($doc['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Cập nhật gia hạn">
                                                        <i class="fas fa-edit"></i> Sửa
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i><br>Không có văn bản nào sắp hết hạn trong 30 ngày tới.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>