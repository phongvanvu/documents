<?php
session_start();
include 'db.php';
include_once 'auth.php';

// Chỉ Admin mới được truy cập trang này
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: documents.php');
    exit;
}

// Lấy danh sách toàn bộ quyền theo module
$perms_raw = $conn->query("SELECT * FROM permissions ORDER BY module ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$modules = [];
foreach ($perms_raw as $p) {
    $modules[$p['module']][] = $p;
}

// Danh sách các vai trò (roles) trong hệ thống
$roles = [
    'viewer' => 'Người xem (Viewer)',
    'editor' => 'Biên tập viên (Editor)',
    'admin'  => 'Quản trị viên (Admin)'
];

// Lấy các quyền hiện tại của từng role
$role_perms_res = $conn->query("SELECT * FROM role_permissions")->fetch_all(MYSQLI_ASSOC);
$matrix = [];
foreach ($role_perms_res as $rp) {
    $matrix[$rp['role']][$rp['perm_key']] = true;
}

$title = "Quản lý phân quyền nâng cao";
include 'header.php';
?>

<div class="container-fluid main">
    <div class="row flex-nowrap">
        <div id="sidebar" class="col-auto col-md-3 col-xl-2 px-0 bg-danger sidebar">
            <?php include 'sidebar.php'; ?>
        </div>

        <div id="content" class="content col py-3">
            <div class="container-fluid mt-2 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold mb-0"><i class="fas fa-user-shield text-danger me-2"></i>Ma trận phân quyền hệ thống</h4>
                        <div class="text-muted small">Cấu hình chi tiết các quyền hành động cho từng vai trò người dùng (Thay đổi có hiệu lực ngay lập tức)</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-danger text-center">
                                    <tr>
                                        <th style="width: 250px;" class="text-start ps-3">Hành động / Quyền hạn</th>
                                        <th style="width: 180px;">Mã định danh (Key)</th>
                                        <?php foreach ($roles as $r_key => $r_name): ?>
                                            <th style="width: 140px;"><?= htmlspecialchars($r_name) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules as $mod_name => $perms): ?>
                                        <tr class="table-light">
                                            <td colspan="<?= 2 + count($roles) ?>" class="fw-bold text-danger text-uppercase ps-3 py-2">
                                                <i class="fas fa-folder me-2"></i>Module: <?= htmlspecialchars($mod_name) ?>
                                            </td>
                                        </tr>
                                        <?php foreach ($perms as $p): ?>
                                            <tr>
                                                <td class="ps-4 fw-medium"><?= htmlspecialchars($p['perm_name']) ?></td>
                                                <td class="text-muted small"><code><?= htmlspecialchars($p['perm_key']) ?></code></td>
                                                
                                                <?php foreach ($roles as $r_key => $r_name): 
                                                    $checked = isset($matrix[$r_key][$p['perm_key']]);
                                                    $is_admin = ($r_key === 'admin');
                                                ?>
                                                    <td class="text-center">
                                                        <div class="form-check form-switch d-flex justify-content-center mb-0">
                                                            <input class="form-check-input perm-toggle" 
                                                                   type="checkbox" 
                                                                   data-role="<?= $r_key ?>" 
                                                                   data-perm="<?= $p['perm_key'] ?>" 
                                                                   <?= $checked ? 'checked' : '' ?> 
                                                                   <?= $is_admin ? 'disabled title="Admin luôn có toàn quyền"' : '' ?>>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.perm-toggle').forEach(input => {
        input.addEventListener('change', function() {
            const role = this.getAttribute('data-role');
            const perm = this.getAttribute('data-perm');
            const isChecked = this.checked ? 1 : 0;
            const toggle = this;

            toggle.disabled = true;

            const formData = new FormData();
            formData.append('role', role);
            formData.append('perm_key', perm);
            formData.append('status', isChecked);

            fetch('ajax_toggle_permission.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                } else {
                    toggle.checked = !isChecked; // Khôi phục lại nếu lỗi
                    alert(data.message || 'Lỗi cập nhật quyền!');
                }
            })
            .catch(err => {
                console.error(err);
                toggle.checked = !isChecked;
                alert('Lỗi kết nối máy chủ');
            })
            .finally(() => {
                toggle.disabled = false;
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>