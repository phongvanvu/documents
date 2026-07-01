<?php
// Thông tin kết nối cơ sở dữ liệu
$host = 'localhost'; // Máy chủ MySQL
$user = 'root';      // Tên người dùng
$password = '';      // Mật khẩu
$database = 'documents_db'; // Tên cơ sở dữ liệu

// Kết nối đến cơ sở dữ liệu
$conn = new mysqli($host, $user, $password, $database);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Đường dẫn tệp sao lưu
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$backup_file = $backup_dir . 'backup_' . date('Ymd') . '.sql';

// Mở tệp để ghi
$handle = fopen($backup_file, 'w');

// Kiểm tra xem tệp có được mở thành công không
if (!$handle) {
    die("Không thể tạo tệp sao lưu. Kiểm tra quyền truy cập thư mục backups.");
}

// Lưu cấu trúc và dữ liệu của các bảng
$tables = $conn->query("SHOW TABLES");
if (!$tables) {
    die("Lỗi khi truy vấn danh sách bảng: " . $conn->error);
}

while ($row = $tables->fetch_row()) {
    $table = $row[0];

    // Lưu cấu trúc bảng
    $create_table = $conn->query("SHOW CREATE TABLE $table");
    if (!$create_table) {
        die("Lỗi khi lấy cấu trúc bảng $table: " . $conn->error);
    }

    $create_table_sql = $create_table->fetch_row();
    fwrite($handle, "\n\n" . $create_table_sql[1] . ";\n\n");

    // Lưu dữ liệu bảng
    $result = $conn->query("SELECT * FROM $table");
    if (!$result) {
        die("Lỗi khi lấy dữ liệu từ bảng $table: " . $conn->error);
    }

    $num_fields = $result->field_count;

    while ($row = $result->fetch_row()) {
        $data = "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $num_fields; $i++) {
            if (isset($row[$i])) {
                $data .= '"' . $conn->real_escape_string($row[$i]) . '"';
            } else {
                $data .= 'NULL';
            }

            if ($i < $num_fields - 1) {
                $data .= ',';
            }
        }
        $data .= ");\n";
        fwrite($handle, $data);
    }
}

// Đóng tệp và kết nối
fclose($handle);
//$conn->close();

// Kiểm tra xem tệp sao lưu đã được ghi thành công hay chưa
if (file_exists($backup_file)) {
    ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Thông báo</strong>
                <small class="text-muted">Vừa xong</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        <div class="toast-body">
            <?php echo "Sao lưu cơ sở dữ liệu thành công. Tệp đã được lưu tại: $backup_file"; ?>
        </div>
    </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
    </script>
<?php
} else {
    echo "Có lỗi xảy ra: tệp sao lưu không được tạo.";
}
?>
