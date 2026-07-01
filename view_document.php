<?php
// Khởi động session
session_start();

// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

// Kiểm tra xem có ID của văn bản không
if (isset($_GET['id'])) {
    // Lấy ID văn bản từ tham số URL
    $document_id = intval($_GET['id']);

    // Truy vấn để lấy thông tin văn bản
    //$sql = "SELECT documents.*, document_types.type_name FROM documents JOIN document_types ON documents.document_type_id = document_types.id WHERE documents.id = ?";
    $sql = "SELECT 
                d.*,
                dt.type_name AS type_name,
                ia.authority_name AS issuing_authority
            FROM 
                documents d
            JOIN 
                document_types dt ON d.document_type_id = dt.id
            JOIN 
                issuing_authorities ia ON d.authority_id = ia.id
            WHERE d.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$document) {
        echo "<p class='text-danger'>Văn bản không tồn tại.</p>";
        exit();
    }
} else {
    echo "<p class='text-danger'>ID văn bản không hợp lệ.</p>";
    exit();
}
include 'header.php';
?>

<div class="container mt-5">
    <h2 class="text-center">Chi tiết Văn bản</h2>
    <?php if ($document): ?>
        <table class="table table-striped table-bordered table-hover">
            <tbody>
                <tr>
                    <th>Số hiệu văn bản</th>
                    <td><?php echo htmlspecialchars($document['document_number']); ?></td>
                </tr>
                <tr>
                    <th>Loại văn bản</th>
                    <td><?php echo htmlspecialchars($document['type_name']); ?></td> <!-- Hiển thị tên loại văn bản -->
                </tr>
                <tr>
                    <th>Cơ quan ban hành</th>
                    <td><?php echo htmlspecialchars($document['issuing_authority']); ?></td>
                </tr>
                <tr>
                    <th>Ngày ban hành</th>
                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($document['issued_date']))); ?></td>
                </tr>
                <?php 
                // Kiểm tra nếu ngày có hiệu lực không phải là 0 và không phải NULL
                if ($document['effective_date'] != '0000-00-00' && !is_null($document['effective_date'])): ?>
                    <tr>
                        <th>Ngày có hiệu lực</th>
                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($document['effective_date']))); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Nội dung</th>
                    <td><?php echo nl2br(htmlspecialchars($document['content'])); ?></td>
                </tr>
                <tr>
                    <th>Tình trạng hiệu lực</th>
                    <td><?php echo htmlspecialchars($document['status']); ?></td>
                </tr>
                <tr>
                    <th>Tệp đính kèm</th>
                    <td>
                        <?php if (!empty($document['file_path'])): ?>
                            <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">Xem tệp</a>
                        <?php else: ?>
                            Không có tệp đính kèm
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center">Không tìm thấy thông tin văn bản.</p>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="dashboard.php" class="btn btn-primary">Quay lại Bảng điều khiển</a>
    </div>
</div>

<?php
include 'footer.php';
?>
