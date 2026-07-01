<?php
require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Hàm để đọc nội dung từ tệp PDF
function getPdfContent($file_path) {
    $pdf = new Fpdi();
    $pdf->AddPage();  // Thêm trang mới
    $pdf->setSourceFile($file_path);  // Đặt nguồn tệp PDF
    $content = '';

    // Duyệt qua tất cả các trang của tệp PDF
    $pageCount = $pdf->setSourceFile($file_path);
    for ($page = 1; $page <= $pageCount; $page++) {
        $tplId = $pdf->importPage($page);  // Nhập trang
        $pdf->useTemplate($tplId);  // Sử dụng trang đã nhập
        // Hàm getText() không có sẵn trong FPDI. Cần một giải pháp khác để đọc văn bản.
    }

    return $content;
}

// Đọc nội dung tệp PDF
$file_path = 'D:\maychu\htdocs\documents\uploads\03.CD.pdf';
$content = getPdfContent($file_path);
echo '1'.nl2br(htmlspecialchars($content));  // Hiển thị nội dung
?>