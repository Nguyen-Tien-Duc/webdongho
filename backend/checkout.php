<?php
session_start();

$host = 'localhost';
$dbname = 'webdongho';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Kết nối CSDL thất bại: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để thanh toán.']);
        exit();
    }

    $taikhoan_id = $_SESSION['user_id'];

    $sql_user = "SELECT coin FROM taikhoan WHERE id = :taikhoan_id";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin tài khoản.']);
        exit();
    }

    $user_coin = $user['coin'];

    $sql_cart = "
        SELECT g.*, 
               COALESCE(v.giatien, p.giatien) as item_price,
               COALESCE(v.tenvatpham, p.ten) as item_name,
               CASE 
                   WHEN v.id IS NOT NULL THEN 'vatpham' 
                   ELSE 'phukien' 
               END as item_type
        FROM giohang g
        LEFT JOIN vatpham v ON g.vatpham_id = v.id
        LEFT JOIN phukien p ON g.phukien_id = p.id
        WHERE g.taikhoan_id = :taikhoan_id";
    
    $stmt_cart = $conn->prepare($sql_cart);
    $stmt_cart->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
    $stmt_cart->execute();

    $total_cost = 0;
    $cart_items = [];
    while ($item = $stmt_cart->fetch(PDO::FETCH_ASSOC)) {
        $item_cost = $item['item_price'] * $item['soluong'];
        $total_cost += $item_cost;
        $cart_items[] = $item;
    }

    if (count($cart_items) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Giỏ hàng của bạn đang trống.']);
        exit();
    }

    if ($user_coin < $total_cost) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không đủ coin để thanh toán. Vui lòng nạp thêm.']);
        exit();
    }

    $conn->beginTransaction();

    try {
        $new_coin_balance = $user_coin - $total_cost;
        $sql_update_user = "UPDATE taikhoan SET coin = :new_coin_balance WHERE id = :taikhoan_id";
        $stmt_update_user = $conn->prepare($sql_update_user);
        $stmt_update_user->bindParam(':new_coin_balance', $new_coin_balance, PDO::PARAM_INT);
        $stmt_update_user->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
        $stmt_update_user->execute();

        $unique_id = uniqid('PAY_', true);
        $sql_coin = "
            INSERT INTO lichsunap (uid, taikhoan_id, phuongthuc, coin, trangthai) 
            VALUES (:uid, :taikhoan_id, 'Mua Hàng', :coin_deducted, 'thành công')";
        $coin_deducted = $total_cost;
        $stmt_coin = $conn->prepare($sql_coin);
        $stmt_coin->bindParam(':uid', $unique_id, PDO::PARAM_STR);
        $stmt_coin->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
        $stmt_coin->bindParam(':coin_deducted', $coin_deducted, PDO::PARAM_INT);
        $stmt_coin->execute();
        $coin_id = $conn->lastInsertId();

        foreach ($cart_items as $item) {
            $vatpham_id = $item['vatpham_id'];
            $phukien_id = $item['phukien_id'];
            $soluong = $item['soluong'];

            if ($vatpham_id) {
                $sql_check = "SELECT sll FROM vatpham WHERE id = :vatpham_id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':vatpham_id', $vatpham_id, PDO::PARAM_INT);
                $stmt_check->execute();
                $product = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($product['sll'] < $soluong) {
                    throw new Exception("Sản phẩm " . $item['item_name'] . " không đủ số lượng.");
                }

                $sql_update_inventory = "UPDATE vatpham SET sll = sll - :soluong WHERE id = :vatpham_id";
                $stmt_update_inventory = $conn->prepare($sql_update_inventory);
                $stmt_update_inventory->bindParam(':soluong', $soluong, PDO::PARAM_INT);
                $stmt_update_inventory->bindParam(':vatpham_id', $vatpham_id, PDO::PARAM_INT);
                $stmt_update_inventory->execute();
            } elseif ($phukien_id) {
                $sql_check = "SELECT sll FROM phukien WHERE id = :phukien_id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bindParam(':phukien_id', $phukien_id, PDO::PARAM_INT);
                $stmt_check->execute();
                $product = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($product['sll'] < $soluong) {
                    throw new Exception("Phụ kiện " . $item['item_name'] . " không đủ số lượng.");
                }

                $sql_update_inventory = "UPDATE phukien SET sll = sll - :soluong WHERE id = :phukien_id";
                $stmt_update_inventory = $conn->prepare($sql_update_inventory);
                $stmt_update_inventory->bindParam(':soluong', $soluong, PDO::PARAM_INT);
                $stmt_update_inventory->bindParam(':phukien_id', $phukien_id, PDO::PARAM_INT);
                $stmt_update_inventory->execute();
            }

            $sql_payment = "
                INSERT INTO lichsuthanhtoan (taikhoan_id, vatpham_id, phukien_id, coin_id, sll, trangthai) 
                VALUES (:taikhoan_id, :vatpham_id, :phukien_id, :coin_id, :soluong, 'đã thanh toán')";
            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
            $stmt_payment->bindParam(':vatpham_id', $vatpham_id, PDO::PARAM_INT);
            $stmt_payment->bindParam(':phukien_id', $phukien_id, PDO::PARAM_INT);
            $stmt_payment->bindParam(':coin_id', $coin_id, PDO::PARAM_INT);
            $stmt_payment->bindParam(':soluong', $soluong, PDO::PARAM_INT);
            $stmt_payment->execute();
        }

        $sql_clear_cart = "DELETE FROM giohang WHERE taikhoan_id = :taikhoan_id";
        $stmt_clear_cart = $conn->prepare($sql_clear_cart);
        $stmt_clear_cart->bindParam(':taikhoan_id', $taikhoan_id, PDO::PARAM_INT);
        $stmt_clear_cart->execute();

        $conn->commit();

        echo json_encode([
            'status' => 'success', 
            'message' => 'Thanh toán thành công! Cảm ơn bạn đã mua hàng.',
            'redirect' => 'cart.php'
        ]);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Checkout Error: " . $e->getMessage() . " | Stack trace: " . $e->getTraceAsString());
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ']);
    exit();
}
?>