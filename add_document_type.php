<?php
// Khởi động session
session_start();

if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Kết nối đến cơ sở dữ liệu
include 'db.php';

$error = '';
$success = '';

// Khởi tạo biến để giữ giá trị đã nhập
$type_name = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $type_name = $_POST['type_name'];

    // Kiểm tra nếu form điền đầy đủ thông tin
    if (empty($type_name)) {
        $error = "Vui lòng nhập tên loại văn bản!";
    } else {
        // Kiểm tra xem loại văn bản đã tồn tại chưa
        $stmt = $conn->prepare("SELECT COUNT(*) FROM document_types WHERE type_name = ?");
        $stmt->bind_param("s", $type_name);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Loại văn bản đã tồn tại trong cơ sở dữ liệu. Vui lòng nhập tên khác.";
        } else {
            // Thêm loại văn bản vào cơ sở dữ liệu
            $stmt = $conn->prepare("INSERT INTO document_types (type_name) VALUES (?)");
            $stmt->bind_param("s", $type_name);

            if ($stmt->execute()) {
                $success = "Thêm loại văn bản thành công!";
                // Chuyển đến trang quản lý loại văn bản sau khi thành công
                header("Location: manage_document_types.php");
                exit();
            } else {
                $error = "Có lỗi xảy ra khi lưu vào cơ sở dữ liệu.";
            }

            $stmt->close();
        }
    }
}
include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-center mt-5">Thêm loại văn bản mới</h2>

        <!-- Hiển thị thông báo lỗi -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Hiển thị thông báo thành công -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="add_document_type.php" method="POST">
            <div class="form-group">
                <label for="type_name">Tên loại văn bản</label>
                <input type="text" class="form-control" id="type_name" name="type_name" value="<?php echo htmlspecialchars($type_name); ?>" required>
            </div>
            <button type="submit" class="btn btn-success btn-block">Thêm loại văn bản</button>
        </form>

        <div class="mt-3 text-center">
            <a href="manage_document_types.php" class="btn btn-primary">Quay lại Quản lý loại văn bản</a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>