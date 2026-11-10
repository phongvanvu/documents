<?php
session_start();
include 'db.php';

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy thông tin chi tiết của người dùng từ cơ sở dữ liệu
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, full_name, role, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Đặt tiêu đề trang
$title = "Hồ sơ cá nhân";
include 'header.php';
?>

<div id="sidebar">
    <?php include 'sidebar.php'; ?>
</div>

<div id="content">
    <div class="container-fluid py-4">
        <h3 class="fw-bold mb-4 text-danger"><i class="fas fa-id-card me-2"></i>HỒ SƠ CÁ NHÂN</h3>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-camera me-2"></i>Ảnh đại diện</h5>
                    </div>
                    <div class="card-body text-center">
                        <form action="update_avatar.php" method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-4 mt-2">
                                <?php
                                $current_avatar = !empty($user_info['avatar']) ? $user_info['avatar'] : 'uploads/avatars/default.jpg';
                                ?>
                                <img id="avatarPreview" src="<?php echo htmlspecialchars($current_avatar, ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="rounded-circle border border-3 border-danger shadow-sm" 
                                     style="width: 160px; height: 160px; object-fit: cover; aspect-ratio: 1/1;" alt="Avatar">
                            </div>
                            
                            <div class="mb-3 text-start">
                                <label for="avatarInput" class="form-label fw-semibold text-muted">Chọn ảnh mới (Tối đa 5MB):</label>
                                <input class="form-control" type="file" id="avatarInput" name="avatar" accept="image/png, image/jpeg, image/jpg, image/gif" required>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-upload me-2"></i>Cập nhật ảnh</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Chi tiết tài khoản</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="#" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tên đăng nhập (Username)</label>
                                <input type="text" class="form-control bg-light text-muted" value="<?php echo htmlspecialchars($user_info['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                                <div class="form-text">Tên đăng nhập là duy nhất và không thể thay đổi.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Họ và tên</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user_info['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Phân quyền (Vai trò)</label>
                                <?php 
                                $role_display = 'Người dùng (Viewer)';
                                if($user_info['role'] == 'admin') $role_display = 'Quản trị viên (Admin)';
                                if($user_info['role'] == 'editor') $role_display = 'Người chỉnh sửa (Editor)';
                                ?>
                                <input type="text" class="form-control bg-light text-danger fw-bold" value="<?php echo $role_display; ?>" readonly disabled>
                            </div>

                            <div class="text-end border-top pt-3 mt-4">
                                <button type="button" class="btn btn-primary px-4 fw-bold" onclick="alert('Chức năng cập nhật họ tên đang được phát triển!')"><i class="fas fa-save me-2"></i>Lưu thông tin</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarInput').addEventListener('change', function(event) {
    const [file] = event.target.files;
    if (file) {
        document.getElementById('avatarPreview').src = URL.createObjectURL(file);
    }
});
</script>

<?php include 'footer.php'; ?>