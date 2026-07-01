<?php
session_start();

/* 1. Kiểm tra đăng nhập */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* 2. Kiểm tra quyền */
if (!in_array($_SESSION['role'], ['admin', 'editor'])) {
    die("Bạn không có quyền sửa văn bản.");
}

include 'db.php';

/* 3. Kiểm tra dữ liệu POST */
if (!isset($_POST['id'])) {
    die("Thiếu ID văn bản.");
}

/* 4. Lấy và xử lý dữ liệu */
$id = intval($_POST['id']);
$document_number   = trim($_POST['document_number']);
$document_type_id  = intval($_POST['document_type_id']);
$authority_id      = intval($_POST['authority_id']);
$issued_date       = $_POST['issued_date'];
$effective_date    = !empty($_POST['effective_date']) ? $_POST['effective_date'] : null;
$status            = $_POST['status'];
$content           = trim($_POST['content']);

/* 5. Validate bắt buộc */
if (
    $document_number === '' ||
    $document_type_id <= 0 ||
    $authority_id <= 0 ||
    $issued_date === '' ||
    $content === ''
) {
    die("Vui lòng điền đầy đủ thông tin bắt buộc.");
}

/* 6. Cập nhật DB */
$sql = "UPDATE documents SET
            document_number   = ?,
            document_type_id  = ?,
            authority_id      = ?,
            issued_date       = ?,
            effective_date    = ?,
            status            = ?,
            content           = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "siissssi",
    $document_number,
    $document_type_id,
    $authority_id,
    $issued_date,
    $effective_date,
    $status,
    $content,
    $id
);

/* 7. Thực thi */
if ($stmt->execute()) {
    header("Location: dashboard.php");
    exit;
}

/* 8. Lỗi */
error_log($stmt->error);
echo "Có lỗi xảy ra khi cập nhật văn bản.";

$stmt->close();
$conn->close();
