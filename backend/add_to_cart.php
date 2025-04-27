<?php
session_start();
require_once '../database/db.php';
if (!isset($_SESSION['user_id'])) {
    $_SESSION['return_url'] = $_SERVER['HTTP_REFERER'];
    header("Location: ../login/login1.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $product_type = isset($_POST['product_type']) ? $_POST['product_type'] : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $user_id = $_SESSION['user_id'];
    
    if ($product_id <= 0 || $quantity <= 0 || ($product_type !== 'vatpham' && $product_type !== 'phukien')) {
        $_SESSION['error_message'] = "Dữ liệu không hợp lệ";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    try {
        if ($product_type === 'vatpham') {
            $stmt = $conn->prepare("SELECT id, sll FROM vatpham WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                $_SESSION['error_message'] = "Sản phẩm không tồn tại";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }
            
            if ($product['sll'] < $quantity) {
                $_SESSION['error_message'] = "Số lượng vượt quá số lượng còn lại trong kho (còn " . $product['sll'] . " sản phẩm)";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }
            
            $checkStmt = $conn->prepare("SELECT id, soluong FROM giohang WHERE taikhoan_id = ? AND vatpham_id = ? AND phukien_id IS NULL");
            $checkStmt->execute([$user_id, $product_id]);
            $cartItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cartItem) {
                $newQuantity = $cartItem['soluong'] + $quantity;
                if ($newQuantity > $product['sll']) {
                    $_SESSION['error_message'] = "Số lượng vượt quá số lượng còn lại trong kho (còn " . $product['sll'] . " sản phẩm)";
                    header("Location: " . $_SERVER['HTTP_REFERER']);
                    exit();
                }
                
                $updateStmt = $conn->prepare("UPDATE giohang SET soluong = ? WHERE id = ?");
                $updateStmt->execute([$newQuantity, $cartItem['id']]);
            } else {
                $insertStmt = $conn->prepare("INSERT INTO giohang (taikhoan_id, vatpham_id, phukien_id, soluong) VALUES (?, ?, NULL, ?)");
                $insertStmt->execute([$user_id, $product_id, $quantity]);
            }
        } else if ($product_type === 'phukien') {
            $stmt = $conn->prepare("SELECT id, sll FROM phukien WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                $_SESSION['error_message'] = "Phụ kiện không tồn tại";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }
            
            if ($product['sll'] < $quantity) {
                $_SESSION['error_message'] = "Số lượng vượt quá số lượng còn lại trong kho (còn " . $product['sll'] . " sản phẩm)";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }
            $checkStmt = $conn->prepare("SELECT id, soluong FROM giohang WHERE taikhoan_id = ? AND phukien_id = ? AND vatpham_id IS NULL");
            $checkStmt->execute([$user_id, $product_id]);
            $cartItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cartItem) {
                $newQuantity = $cartItem['soluong'] + $quantity;
                if ($newQuantity > $product['sll']) {
                    $_SESSION['error_message'] = "Số lượng vượt quá số lượng còn lại trong kho (còn " . $product['sll'] . " sản phẩm)";
                    header("Location: " . $_SERVER['HTTP_REFERER']);
                    exit();
                }
                
                $updateStmt = $conn->prepare("UPDATE giohang SET soluong = ? WHERE id = ?");
                $updateStmt->execute([$newQuantity, $cartItem['id']]);
            } else {
                $insertStmt = $conn->prepare("INSERT INTO giohang (taikhoan_id, vatpham_id, phukien_id, soluong) VALUES (?, NULL, ?, ?)");
                $insertStmt->execute([$user_id, $product_id, $quantity]);
            }
        }
        
        $_SESSION['success_message'] = "Đã thêm sản phẩm vào giỏ hàng";
        header("Location: cart.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Đã xảy ra lỗi: " . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>