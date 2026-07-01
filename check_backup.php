<?php
$backup_dir = 'backups/';
$backup_file = $backup_dir . 'backup_' . date('Ymd') . '.sql';

if (file_exists($backup_file)) {
    //echo "Sao lưu đã được thực hiện trong ngày.";
    $backup = 'Đã sao lưu';
} else {
    // Sao lưu chưa được thực hiện
    //echo "Chưa có sao lưu trong ngày. Đang thực hiện sao lưu...";
    $backup = 'Chưa sao lưu';
    include 'backup.php'; // Gọi script sao lưu
}
?>
