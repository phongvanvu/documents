<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$document_id = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$is_favorite = isset($_POST['is_favorite']) ? 1 : 0;
$personal_note = trim($_POST['personal_note'] ?? '');
$tags = trim($_POST['tags'] ?? '');

if (!$document_id) {
    $_SESSION['flash_msg'] = "ID văn bản không hợp lệ.";
    header("Location: dashboard.php");
    exit;
}

$sql = "UPDATE documents 
        SET is_favorite = ?, personal_note = ?, tags = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issi", $is_favorite, $personal_note, $tags, $document_id);
$stmt->execute();
$stmt->close();

$_SESSION['flash_msg'] = "Đã lưu ghi chú cá nhân.";
header("Location: view_document.php?id=" . $document_id);
exit;