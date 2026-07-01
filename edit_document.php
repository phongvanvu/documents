<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'editor'])) {
    die("Bạn không có quyền sửa tài liệu.");
}

include 'db.php';

if (!isset($_GET['id'])) {
    die("Thiếu ID văn bản.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Văn bản không tồn tại.");
}

$row = $result->fetch_assoc();

$types = $conn->query("SELECT id, type_name FROM document_types ORDER BY type_name");
$authorities = $conn->query("SELECT id, authority_name FROM issuing_authorities ORDER BY authority_name");

include 'header.php';
?>
<div class="container edit-wrapper">
    <h4 class="text-center mb-4 fw-bold">SỬA VĂN BẢN</h4>

    <form action="update_document.php" method="POST" class="edit-form">
        <input type="hidden" name="id" value="<?= $row['id'] ?>">

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Tiêu đề / Số văn bản</label>
                <input type="text" class="form-control"
                       name="document_number"
                       value="<?= htmlspecialchars($row['document_number']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Loại văn bản</label>
                <select class="form-control" name="document_type_id" required>
                    <?php while ($t = $types->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$row['document_type_id']?'selected':'' ?>>
                            <?= htmlspecialchars($t['type_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nơi ban hành</label>
                <select class="form-control" name="authority_id" required>
                    <?php while ($a = $authorities->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>" <?= $a['id']==$row['authority_id']?'selected':'' ?>>
                            <?= htmlspecialchars($a['authority_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Ngày ban hành</label>
                <input type="date" class="form-control"
                       name="issued_date"
                       value="<?= $row['issued_date'] ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ngày hiệu lực</label>
                <input type="date" class="form-control"
                       name="effective_date"
                       value="<?= $row['effective_date'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tình trạng</label>
                <select class="form-control" name="status" required>
                    <?php
                    foreach (['Còn hiệu lực','Hết hiệu lực','Không xác định','Chưa biết'] as $s) {
                        echo "<option value=\"$s\" ".($row['status']==$s?'selected':'').">$s</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Nội dung văn bản</label>
            <textarea class="form-control" name="content" rows="7" required><?= htmlspecialchars($row['content']) ?></textarea>
        </div>

        <div class="text-center">
            <button class="btn btn-success px-5">Cập nhật</button>
            <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Quay lại</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
