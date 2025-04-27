<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login1.php');
    exit();
}
require_once '../database/db.php'; 
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['success' => false, 'message' => 'Direct access not allowed']));
}
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}
$response = ['success' => false, 'message' => 'Invalid request'];
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
        $cart_id = intval($_POST['cart_id']);
        $quantity = max(1, intval($_POST['quantity'])); 
        $user_id = $_SESSION['user_id'];
        
        try {
            $stmt = $conn->prepare("
                SELECT 
                    g.id, 
                    g.vatpham_id, 
                    g.phukien_id, 
                    COALESCE(v.sll, p.sll) as available_stock,
                    COALESCE(v.giatien, p.giatien) as price
                FROM 
                    giohang g
                LEFT JOIN 
                    vatpham v ON g.vatpham_id = v.id
                LEFT JOIN 
                    phukien p ON g.phukien_id = p.id
                WHERE 
                    g.id = ? AND g.taikhoan_id = ?
            ");
            
            $stmt->execute([$cart_id, $user_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception('Item not found in cart');
            }
            
            if ($quantity > $item['available_stock']) {
                throw new Exception('Not enough stock available. Maximum available: ' . $item['available_stock']);
            }
            $updateStmt = $conn->prepare("UPDATE giohang SET soluong = ? WHERE id = ? AND taikhoan_id = ?");
            $updateStmt->execute([$quantity, $cart_id, $user_id]);
            $itemTotal = $quantity * $item['price'];
            $totalStmt = $conn->prepare("
                SELECT 
                    SUM(g.soluong * COALESCE(v.giatien, p.giatien)) as total
                FROM 
                    giohang g
                LEFT JOIN 
                    vatpham v ON g.vatpham_id = v.id
                LEFT JOIN 
                    phukien p ON g.phukien_id = p.id
                WHERE 
                    g.taikhoan_id = ?
            ");
            
            $totalStmt->execute([$user_id]);
            $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
            $cartTotal = $totalResult['total'] ?? 0;
            
            $response = [
                'success' => true,
                'message' => 'Số lượng đã được cập nhật!',
                'item_total' => $itemTotal,
                'item_total_formatted' => number_format($itemTotal, 0, ',', '.') . ' đ',
                'cart_total' => $cartTotal,
                'cart_total_formatted' => number_format($cartTotal, 0, ',', '.') . ' đ'
            ];
            
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;