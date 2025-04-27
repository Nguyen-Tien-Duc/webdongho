<?php
session_start();
require '../database/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit;
}
$user_id = $_SESSION['user_id'];
if (isset($_GET['id']) && isset($_GET['product_id'])) {
    $rating_id = $_GET['id'];
    $product_id = $_GET['product_id'];
    $check = $conn->prepare("SELECT * FROM danhgia WHERE id = ? AND taikhoan_id = ?");
    $check->execute([$rating_id, $user_id]);
    if ($check->rowCount() > 0) {
        $delete = $conn->prepare("DELETE FROM danhgia WHERE id = ? AND taikhoan_id = ?");
        $delete->execute([$rating_id, $user_id]);
        header("Location: product-detail.php?product_id=$product_id&deleted=1");
        exit;
    } else {
        header("Location: product-detail.php?product_id=$product_id&error=unauthorized");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>