<?php
// Khởi động session
session_start();

// Kiểm tra nếu người dùng chưa đăng nhập hoặc không phải là admin
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

// Lấy ID loại văn bản từ URL
$type_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $type_name = $_POST['type_name'];

    // Kiểm tra nếu form điền đầy đủ thông tin
    if (empty($type_name)) {
        $error = "Vui lòng nhập tên loại văn bản!";
    } else {
        // Kiểm tra xem loại văn bản đã tồn tại chưa (ngoại trừ bản ghi hiện tại)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM document_types WHERE type_name = ? AND id != ?");
        $stmt->bind_param("si", $type_name, $type_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Loại văn bản đã tồn tại trong cơ sở dữ liệu. Vui lòng nhập tên khác.";
        } else {
            // Cập nhật loại văn bản vào cơ sở dữ liệu
            $stmt = $conn->prepare("UPDATE document_types SET type_name = ? WHERE id = ?");
            $stmt->bind_param("si", $type_name, $type_id);

            if ($stmt->execute()) {
                $success = "Cập nhật loại văn bản thành công!";
                // Chuyển đến trang quản lý loại văn bản sau khi thành công
                header("Location: manage_document_types.php");
                exit();
            } else {
                $error = "Có lỗi xảy ra khi lưu vào cơ sở dữ liệu.";
            }

            $stmt->close();
        }
    }
} else {
    // Lấy thông tin loại văn bản hiện tại
    $stmt = $conn->prepare("SELECT type_name FROM document_types WHERE id = ?");
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $stmt->bind_result($type_name);
    $stmt->fetch();
    $stmt->close();

    // Nếu không tìm thấy loại văn bản, chuyển về trang quản lý
    if (!$type_name) {
        header("Location: manage_document_types.php");
        exit();
    }
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-center mt-5">Chỉnh sửa loại văn bản</h2>

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

        <form action="edit_document_type.php?id=<?php echo $type_id; ?>" method="POST">
            <div class="form-group">
                <label for="type_name">Tên loại văn bản</label>
                <input type="text" class="form-control" id="type_name" name="type_name" value="<?php echo htmlspecialchars($type_name); ?>" required>
            </div>
            <button type="submit" class="btn btn-success btn-block">Cập nhật loại văn bản</button>
        </form>

        <div class="mt-3 text-center">
            <a href="manage_document_types.php" class="btn btn-primary">Quay lại Quản lý loại văn bản</a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
