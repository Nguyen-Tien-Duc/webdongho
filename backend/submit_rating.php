<?php
session_start();
require '../database/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = trim($_POST['comment']);
    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        die("Vui lòng chọn số sao từ 1 đến 5.");
    }
    $check = $conn->prepare("SELECT * FROM danhgia WHERE taikhoan_id = ? AND vatpham_id = ?");
    $check->execute([$user_id, $product_id]);
    
    if ($check->rowCount() > 0) {
        $update = $conn->prepare("UPDATE danhgia SET sosao = ?, binhluan = ?, thoigian = CURRENT_TIMESTAMP WHERE taikhoan_id = ? AND vatpham_id = ?");
        $update->execute([$rating, $comment, $user_id, $product_id]);
    } else {
        $insert = $conn->prepare("INSERT INTO danhgia (taikhoan_id, vatpham_id, sosao, binhluan) VALUES (?, ?, ?, ?)");
        $insert->execute([$user_id, $product_id, $rating, $comment]);
    }
    header("Location: product-detail.php?product_id=$product_id");
    exit;
} else {
    header("Location: index.php");
    exit;
}
?>