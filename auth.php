<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Lấy danh sách quyền của vai trò hiện tại (được cache trong Session)
 */
function get_user_permissions() {
    global $conn;
    
    $role = $_SESSION['role'] ?? 'viewer';
    
    // Nếu là admin tối cao thì luôn có toàn bộ quyền
    if ($role === 'admin') {
        return ['*'];
    }

    if (isset($_SESSION['user_perms'])) {
        return $_SESSION['user_perms'];
    }

    $perms = [];
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT perm_key FROM role_permissions WHERE role = ?");
        if ($stmt) {
            $stmt->bind_param("s", $role);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $perms[] = $row['perm_key'];
            }
            $stmt->close();
        }
    }
    
    $_SESSION['user_perms'] = $perms;
    return $perms;
}

/**
 * Kiểm tra xem người dùng hiện tại có quyền cụ thể hay không
 * @param string $perm_key (ví dụ: 'documents.create')
 * @return bool
 */
function has_permission($perm_key) {
    $role = $_SESSION['role'] ?? 'viewer';
    if ($role === 'admin') return true;
    
    $perms = get_user_permissions();
    return in_array($perm_key, $perms, true);
}

/**
 * Chặn truy cập nếu không có quyền
 */
function require_permission($perm_key) {
    if (!has_permission($perm_key)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này!']);
            exit;
        }

        $title = "Từ chối truy cập";
        if (file_exists('header.php')) include 'header.php';
        echo '<div class="container mt-5 py-5 text-center">
                <div class="card shadow-sm border-0 mx-auto p-4" style="max-width: 500px;">
                    <i class="fas fa-user-shield text-danger fa-4x mb-3"></i>
                    <h4 class="fw-bold text-danger">Không có quyền truy cập</h4>
                    <p class="text-muted small">Tài khoản của bạn chưa được cấp quyền (<strong>' . htmlspecialchars($perm_key) . '</strong>) để sử dụng trang này.</p>
                    <a href="javascript:history.back()" class="btn btn-outline-danger btn-sm mt-2"><i class="fas fa-arrow-left me-1"></i>Quay lại</a>
                </div>
              </div>';
        if (file_exists('footer.php')) include 'footer.php';
        exit;
    }
}

/**
 * Ghi nhận hoặc làm mới hoạt động của phiên đăng nhập hiện tại
 */
function track_user_session() {
    global $conn;
    if (!isset($_SESSION['user_id']) || !isset($conn)) return;

    $user_id = (int)$_SESSION['user_id'];
    $session_token = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    // Kiểm tra xem phiên này có bị Admin xóa cưỡng chế không
    $stmt = $conn->prepare("SELECT id FROM user_sessions WHERE session_token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $session_token);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            // Phiên chưa có trong DB -> Thêm mới
            $ins = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW())");
            if ($ins) {
                $ins->bind_param("isss", $user_id, $session_token, $ip, $agent);
                $ins->execute();
                $ins->close();
            }
        } else {
            // Phiên đã tồn tại -> Cập nhật thời gian hoạt động mới nhất
            $up = $conn->prepare("UPDATE user_sessions SET last_activity = NOW(), ip_address = ?, user_agent = ? WHERE session_token = ?");
            if ($up) {
                $up->bind_param("sss", $ip, $agent, $session_token);
                $up->execute();
                $up->close();
            }
        }
        $stmt->close();
    }

    // Tự động dọn dẹp các phiên đăng nhập cũ hơn 30 ngày (xác suất 2% mỗi lượt truy vấn để tiết kiệm tài nguyên)
    if (mt_rand(1, 50) === 1) {
        $conn->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
}

// Gọi hàm theo dõi phiên tự động nếu đã đăng nhập
if (isset($_SESSION['user_id'])) {
    track_user_session();
}