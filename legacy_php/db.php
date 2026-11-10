<?php
// Thông tin kết nối cơ sở dữ liệu
$servername = "localhost"; 
$username = "root";        
$password = "";            
$dbname = "documents_db"; 

// Bật chế độ báo lỗi ngoại lệ (Exceptions) cho MySQLi để bắt lỗi an toàn hơn
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Tạo kết nối tới cơ sở dữ liệu
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Đặt charset utf8mb4 để hỗ trợ Full Unicode (bao gồm cả Emoji và ký tự đặc biệt)
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Ẩn chi tiết lỗi kết nối thật để bảo mật, chỉ hiện thông báo chung
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h3>Lỗi kết nối Cơ sở dữ liệu!</h3>
            <p>Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại cấu hình hệ thống.</p>
         </div>");
}

// Tự động chuyển trạng thái thành "Hết hiệu lực" đối với các văn bản đã quá ngày hết hạn
if (isset($conn) && !$conn->connect_error) {
    $conn->query("UPDATE documents 
                  SET status = 'Hết hiệu lực' 
                  WHERE deleted_at IS NULL 
                  AND expired_date IS NOT NULL 
                  AND expired_date < CURDATE() 
                  AND status != 'Hết hiệu lực'");
}

// Hàm ghi nhật ký hoạt động (Đã bổ sung bộ giáp chống Crash)
function log_activity($user_id, $action) {
    global $conn;
    
    $sql = "INSERT INTO logs (user_id, action) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    // Kiểm tra nếu chuẩn bị câu lệnh thành công (bảng logs tồn tại) mới chạy
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $action);
        $stmt->execute();
        $stmt->close();
    } else {
        // Ghi log lỗi vào file của server thay vì làm sập trang web
        error_log("Lỗi ghi log_activity: " . $conn->error);
    }
}

/**
 * Lấy giá trị cài đặt hệ thống từ bảng system_settings
 */
function get_setting($key, $default = '') {
    global $conn;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $settings_cache = [];
        if (isset($conn)) {
            $res = $conn->query("SELECT setting_key, setting_value FROM system_settings");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $settings_cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }

    return $settings_cache[$key] ?? $default;
}

// Hàm chống XSS / escape HTML an toàn dùng chung toàn hệ thống
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Giữ lại alias e() nếu có file cũ đang gọi
if (!function_exists('e')) {
    function e($value) {
        return h($value);
    }
}

// Hàm loại bỏ dấu tiếng Việt để tạo chuỗi/tên tệp an toàn
if (!function_exists('remove_vietnamese_accents')) {
    function remove_vietnamese_accents($str) {
        $chars = [
            'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
            'A' => ['Á','À','Ả','Ã','Ạ','Ă','Ắ','Ằ','Ẳ','Ẵ','Ặ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ'],
            'd' => ['đ'],
            'D' => ['Đ'],
            'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
            'E' => ['É','È','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ'],
            'i' => ['í','ì','ỉ','ĩ','ị'],
            'I' => ['Í','Ì','Ỉ','Ĩ','Ị'],
            'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
            'O' => ['Ó','Ò','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ'],
            'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
            'U' => ['Ú','Ù','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự'],
            'y' => ['ý','ỳ','ỷ','ỹ','ỵ'],
            'Y' => ['Ý','Ỳ','Ỷ','Ỹ','Ỵ'],
        ];
        foreach ($chars as $replacement => $originals) {
            $str = str_replace($originals, $replacement, $str);
        }
        return $str;
    }
}

// Hàm chuẩn hóa tên tệp tải lên: chuyển tiếng Việt không dấu, chống tràn MAX_PATH trên Windows
if (!function_exists('sanitize_upload_filename')) {
    function sanitize_upload_filename($filename, $max_base_len = 80) {
        $filename = str_replace('\\', '/', $filename);
        $clean_base = basename($filename);

        $ext = pathinfo($clean_base, PATHINFO_EXTENSION);
        $ext_part = $ext !== '' ? '.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $name_without_ext = pathinfo($clean_base, PATHINFO_FILENAME);
        $name_without_ext = remove_vietnamese_accents($name_without_ext);
        $name_without_ext = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name_without_ext);
        $name_without_ext = preg_replace('/_+/', '_', $name_without_ext);
        $name_without_ext = trim($name_without_ext, '_-');

        if ($name_without_ext === '') {
            $name_without_ext = 'document';
        }

        if (mb_strlen($name_without_ext, 'UTF-8') > $max_base_len) {
            $name_without_ext = mb_substr($name_without_ext, 0, $max_base_len, 'UTF-8');
            $name_without_ext = rtrim($name_without_ext, '_-');
        }

        return $name_without_ext . $ext_part;
    }
}
