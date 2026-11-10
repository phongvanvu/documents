<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', '0');

include 'db.php';
include_once 'auth.php';

// Chỉ quản trị viên tối cao (Admin) mới có quyền can thiệp
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)$_SESSION['user_id'];

// 1. KIỂM TRA KẾT NỐI MÁY CHỦ SMTP (TEST SOCKET)
if ($action === 'test_smtp') {
    $host = trim($_POST['smtp_host'] ?? get_setting('smtp_host'));
    $port = intval($_POST['smtp_port'] ?? get_setting('smtp_port', 587));

    if (empty($host) || $port <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền máy chủ và cổng SMTP hợp lệ!']);
        exit;
    }

    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    if (is_resource($connection)) {
        fclose($connection);
        echo json_encode(['status' => 'success', 'message' => "Kết nối thành công đến máy chủ SMTP ($host:$port)!"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Không thể kết nối đến SMTP ($host:$port). Lỗi: $errstr ($errno)"]);
    }
    exit;
}

// 2. GỬI TIN NHẮN THỬ NGHIỆM TỚI WEBHOOK (TELEGRAM / DISCORD)
if ($action === 'test_webhook') {
    $webhook_url = trim($_POST['webhook_url'] ?? get_setting('telegram_webhook_url'));

    if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng cung cấp URL Webhook hợp lệ!']);
        exit;
    }

    $payload = json_encode([
        'text' => "🔔 [TEST] Thông báo thử nghiệm từ Hệ thống Quản lý Văn bản (" . date('d/m/Y H:i:s') . "). Kết nối Webhook hoạt động bình thường!",
        'content' => "🔔 [TEST] Thông báo thử nghiệm từ Hệ thống Quản lý Văn bản. Kết nối hoạt động bình thường!"
    ]);

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode(['status' => 'success', 'message' => 'Đã gửi thông báo thử nghiệm qua Webhook thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Gửi thất bại (Mã HTTP: $http_code). " . ($curl_err ? "Lỗi: $curl_err" : "")]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
exit;