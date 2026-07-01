<?php
session_start();
include 'db.php'; // Kết nối đến cơ sở dữ liệu
include 'header.php'; // Bao gồm header

// Khởi tạo biến tìm kiếm
$search_query = isset($_GET['search_query']) ? htmlspecialchars($_GET['search_query'], ENT_QUOTES, 'UTF-8') : '';

// Truy vấn để lấy dữ liệu từ bảng mlns, tìm kiếm theo tên và mô tả
$sql = "SELECT id, ten, mota FROM mlns WHERE ten LIKE ? OR mota LIKE ?"; 
$stmt = $conn->prepare($sql);
$search_param = '%' . $search_query . '%';
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-5">
    <h2 class="text-center">Mục lục ngân sách</h2>

    <!-- Form tìm kiếm -->
    <form action="" method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="search_query" class="form-control" placeholder="Tìm kiếm theo tên hoặc mô tả..." value="<?php echo $search_query; ?>">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover mt-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên</th>
                    <th>Mô tả</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['ten']); ?></td>
                            <td style="text-align: left;"><?php echo htmlspecialchars($row['mota']); ?></td> <!-- Canh trái cột mô tả -->
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Không có dữ liệu</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="bg-light text-center py-3">
    <div class="container">
        <p class="mb-0">© <?php echo date("Y"); ?> Công ty của bạn. Tất cả quyền được bảo lưu.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
