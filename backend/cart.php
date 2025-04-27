<?php
session_start();
require_once '../database/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$cartStmt = $conn->prepare("
    SELECT 
        g.id as cart_id, 
        g.vatpham_id, 
        g.phukien_id, 
        g.soluong,
        COALESCE(v.tenvatpham, p.ten) as ten_san_pham,
        COALESCE(v.giatien, p.giatien) as gia_tien,
        COALESCE(v.url, p.url) as hinh_anh,
        COALESCE(v.mota, p.mota) as mota,
        COALESCE(v.sll, p.sll) as so_luong_con_lai,
        CASE 
            WHEN g.vatpham_id IS NOT NULL THEN 'Đồng hồ'
            WHEN g.phukien_id IS NOT NULL THEN 'Phụ kiện'
        END as loai_san_pham
    FROM giohang g
    LEFT JOIN vatpham v ON g.vatpham_id = v.id
    LEFT JOIN phukien p ON g.phukien_id = p.id
    WHERE g.taikhoan_id = ?
");
$cartStmt->execute([$user_id]);
$cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['gia_tien'] * $item['soluong'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'updateQuantity') {
        $cart_id = (int)$_POST['cart_id'];
        $quantity = (int)$_POST['quantity'];
        
        $itemInfoStmt = $conn->prepare("
            SELECT 
                g.vatpham_id, 
                g.phukien_id,
                COALESCE(v.sll, p.sll) as so_luong_con_lai,
                COALESCE(v.giatien, p.giatien) as gia_tien
            FROM giohang g
            LEFT JOIN vatpham v ON g.vatpham_id = v.id
            LEFT JOIN phukien p ON g.phukien_id = p.id
            WHERE g.id = ? AND g.taikhoan_id = ?
        ");
        $itemInfoStmt->execute([$cart_id, $user_id]);
        $itemInfo = $itemInfoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemInfo) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng']);
            exit();
        }
        
        if ($quantity > $itemInfo['so_luong_con_lai']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Số lượng vượt quá số lượng còn lại trong kho. Chỉ còn ' . $itemInfo['so_luong_con_lai'] . ' sản phẩm.',
                'max_quantity' => $itemInfo['so_luong_con_lai']
            ]);
            exit();
        }
        
        if ($quantity > 0) {
            $updateStmt = $conn->prepare("UPDATE giohang SET soluong = ? WHERE id = ? AND taikhoan_id = ?");
            $updateStmt->execute([$quantity, $cart_id, $user_id]);
            
            $itemTotal = $itemInfo['gia_tien'] * $quantity;
            $totalStmt = $conn->prepare("
                SELECT 
                    SUM(COALESCE(v.giatien, p.giatien) * g.soluong) as total
                FROM giohang g
                LEFT JOIN vatpham v ON g.vatpham_id = v.id
                LEFT JOIN phukien p ON g.phukien_id = p.id
                WHERE g.taikhoan_id = ?
            ");
            $totalStmt->execute([$user_id]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'itemTotal' => $itemTotal,
                'cartTotal' => $total['total']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Số lượng không hợp lệ']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'removeItem') {
        $cart_id = (int)$_POST['cart_id'];
        $removeStmt = $conn->prepare("DELETE FROM giohang WHERE id = ? AND taikhoan_id = ?");
        $removeStmt->execute([$cart_id, $user_id]);
        
        $totalStmt = $conn->prepare("
            SELECT 
                SUM(COALESCE(v.giatien, p.giatien) * g.soluong) as total
            FROM giohang g
            LEFT JOIN vatpham v ON g.vatpham_id = v.id
            LEFT JOIN phukien p ON g.phukien_id = p.id
            WHERE g.taikhoan_id = ?
        ");
        $totalStmt->execute([$user_id]);
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'cartTotal' => $total['total'] ?? 0
        ]);
        exit();
    }
    
    if ($_POST['action'] === 'checkout') {
        if ($user['coin'] < $totalPrice) {
            echo json_encode([
                'success' => false,
                'message' => 'Số dư không đủ để thanh toán. Vui lòng nạp thêm coin.'
            ]);
            exit();
        }
        
        $stockCheckStmt = $conn->prepare("
            SELECT 
                g.id as cart_id,
                g.vatpham_id,
                g.phukien_id,
                g.soluong as so_luong_dat,
                COALESCE(v.tenvatpham, p.ten) as ten_san_pham,
                COALESCE(v.sll, p.sll) as so_luong_con_lai
            FROM giohang g
            LEFT JOIN vatpham v ON g.vatpham_id = v.id
            LEFT JOIN phukien p ON g.phukien_id = p.id
            WHERE g.taikhoan_id = ?
        ");
        $stockCheckStmt->execute([$user_id]);
        $stockItems = $stockCheckStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $outOfStockItems = [];
        foreach ($stockItems as $item) {
            if ($item['so_luong_dat'] > $item['so_luong_con_lai']) {
                $outOfStockItems[] = [
                    'name' => $item['ten_san_pham'],
                    'requested' => $item['so_luong_dat'],
                    'available' => $item['so_luong_con_lai']
                ];
            }
        }
        
        if (!empty($outOfStockItems)) {
            $message = 'Một số sản phẩm không đủ số lượng: ';
            foreach ($outOfStockItems as $item) {
                $message .= $item['name'] . ' (yêu cầu: ' . $item['requested'] . ', còn lại: ' . $item['available'] . '), ';
            }
            $message = rtrim($message, ', ');
            
            echo json_encode([
                'success' => false,
                'message' => $message,
                'outOfStockItems' => $outOfStockItems
            ]);
            exit();
        }
        
        try {
            $conn->beginTransaction();
            
            $unique_id = uniqid('PAY_', true);
            $insertNapStmt = $conn->prepare("
                INSERT INTO lichsunap (uid, taikhoan_id, phuongthuc, coin, trangthai) 
                VALUES (?, ?, 'Thẻ Cào', ?, 'thành công')
            ");
            $insertNapStmt->execute([
                $unique_id,
                $user_id,
                $totalPrice
            ]);
            $coin_id = $conn->lastInsertId();
            
            $updateUserStmt = $conn->prepare("UPDATE taikhoan SET coin = coin - ? WHERE id = ?");
            $updateUserStmt->execute([$totalPrice, $user_id]);
            
            foreach ($cartItems as $item) {
                $insertStmt = $conn->prepare("
                    INSERT INTO lichsuthanhtoan (taikhoan_id, vatpham_id, phukien_id, coin_id, sll, trangthai) 
                    VALUES (?, ?, ?, ?, ?, 'đã thanh toán')
                ");
                $insertStmt->execute([
                    $user_id, 
                    $item['vatpham_id'], 
                    $item['phukien_id'], 
                    $coin_id, 
                    $item['soluong']
                ]);
                
                if ($item['vatpham_id']) {
                    $updateStmt = $conn->prepare("UPDATE vatpham SET sll = sll - ? WHERE id = ?");
                    $updateStmt->execute([$item['soluong'], $item['vatpham_id']]);
                }
                
                if ($item['phukien_id']) {
                    $updateStmt = $conn->prepare("UPDATE phukien SET sll = sll - ? WHERE id = ?");
                    $updateStmt->execute([$item['soluong'], $item['phukien_id']]);
                }
            }
            
            $clearCartStmt = $conn->prepare("DELETE FROM giohang WHERE taikhoan_id = ?");
            $clearCartStmt->execute([$user_id]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Thanh toán thành công!'
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Checkout Error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Đã xảy ra lỗi trong quá trình thanh toán: ' . $e->getMessage()
            ]);
        }
        exit();
    }
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng | Watch Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <link rel="stylesheet" href="../frontend/assets/css/cart.css">

    
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <img src="../frontend/assets/images/logo1.png" alt="Logo">
                    </a>
                </div>
                <div class="user-welcome">
                    <?php
                    $base_url = "/webdongho";
                    ?>
                    <img src="<?= $user['avatar'] ? $base_url . '/' . htmlspecialchars($user['avatar']) : $base_url . '/frontend/assets/images/anh-dai-dien.png' ?>" alt="Avatar" width="120" height="120">
                    <div>
                        <span>Xin chào, <?= htmlspecialchars($user['tentaikhoan']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div id="alert-container"></div>
        
        <section class="cart-section">
            <div class="container">
                <h2>GIỎ HÀNG CỦA BẠN</h2> 
                <?php if (count($cartItems) > 0): ?>
                    <div class="cart-wrapper">
                        <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item" data-id="<?= $item['cart_id'] ?>">
                                <img src="<?= $item['vatpham_id'] ? '/WebDongHo/' . htmlspecialchars($item['hinh_anh']) : '../uploads/' . htmlspecialchars($item['hinh_anh']) ?>" 
                                    alt="<?= htmlspecialchars($item['ten_san_pham']) ?>" 
                                    class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3 class="cart-item-name"><?= htmlspecialchars($item['ten_san_pham']) ?></h3>
                                    <div class="cart-item-quantity">
                                        <label for="quantity-<?= $item['cart_id'] ?>">Số lượng:</label>
                                        <input type="number" id="quantity-<?= $item['cart_id'] ?>" class="quantity-input" value="<?= $item['soluong'] ?>" min="1" max="<?= $item['so_luong_con_lai'] ?>" data-id="<?= $item['cart_id'] ?>">
                                    </div>
                                    <p class="cart-item-price"><?= number_format($item['gia_tien'] * $item['soluong']) ?> coin</p>
                                    <p class="cart-item Susanna low">
                                        Còn lại: <?= $item['so_luong_con_lai'] ?> sản phẩm
                                        <?php if ($item['so_luong_con_lai'] < 5): ?>
                                            <span>(Sắp hết hàng)</span>
                                        <?php endif; ?>
                                    </p>
                                <button class="remove-item" data-id="<?= $item['cart_id'] ?>">Xóa</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                        </div>
                        <div class="cart-summary">
                            <h3>Tổng cộng</h3>
                            <p class="cart-total"><?= number_format($totalPrice) ?> coin</p>
                            <button class="checkout-button" id="checkout-btn">Thanh toán</button>
                            <p style="margin-top: 15px; font-size: 14px; color: #666;">Số dư tài khoản: <strong><?= number_format($user['coin']) ?> coin</strong></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Giỏ hàng của bạn đang trống</p>
                        <a href="index.php" class="shop-now-button">Mua sắm ngay</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    
    <footer class="footer">
        <div class="footer-top">
            <div class="container">
                <div class="footer-columns">
                    <div class="footer-column">
                        <h3>THÔNG TIN</h3>
                        <ul>
                            <li><a href="#">Về chúng tôi</a></li>
                            <li><a href="#">Chính sách bảo mật</a></li>
                            <li><a href="#">Điều khoản sử dụng</a></li>
                            <li><a href="#">Tuyển dụng</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>HỖ TRỢ</h3>
                        <ul>
                            <li><a href="#">Hướng dẫn mua hàng</a></li>
                            <li><a href="#">Chính sách đổi trả</a></li>
                            <li><a href="#">Chính sách bảo hành</a></li>
                            <li><a href="#">FAQ</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>LIÊN HỆ</h3>
                        <ul>
                            <li>Hotline: 1900.6777</li>
                            <li>Email: okokmen07@gmail.com</li>
                            <li>Địa chỉ: 70 Tô Ký</li>
                            <li>Thời gian làm việc: 9:00 - 21:30</li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>THEO DÕI CHÚNG TÔI</h3>
                        <div class="social-media">
                            <a href="#" class="social-icon facebook"></a>
                            <a href="#" class="social-icon instagram"></a>
                            <a href="#" class="social-icon youtube"></a>
                            <a href="#" class="social-icon tiktok"></a>
                        </div>
                        <div class="payment-methods">
                            <img src="https://ext.same-assets.com/1280460479/1923935881.png" alt="Thanh toán">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>© 2025 Đồng Hồ GROUP 3. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function showAlert(message, type) {
                const alertContainer = document.getElementById('alert-container');
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.innerHTML = message; 
                alertContainer.appendChild(alert);   
                setTimeout(function() {
                    alert.remove();
                }, 5000);
            }

            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    const cartId = this.getAttribute('data-id');
                    const quantity = parseInt(this.value);
                    const maxQuantity = parseInt(this.getAttribute('max'));
                    
                    if (quantity < 1) {
                        this.value = 1;
                        return;
                    }
                    
                    if (quantity > maxQuantity) {
                        this.value = maxQuantity;
                        showAlert(`Số lượng sản phẩm không được vượt quá ${maxQuantity} (số lượng còn lại trong kho)`, 'warning');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'updateQuantity');
                    formData.append('cart_id', cartId);
                    formData.append('quantity', this.value);
                    
                    fetch('cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const priceElement = this.closest('.cart-item-details').querySelector('.cart-item-price');
                            priceElement.textContent = numberFormat(data.itemTotal) + ' coin';
                            
                            document.querySelector('.cart-total').textContent = numberFormat(data.cartTotal) + ' coin';
                        } else {
                            if (data.max_quantity) {
                                this.value = data.max_quantity;
                            }
                            showAlert(data.message, 'warning');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Đã xảy ra lỗi khi cập nhật giỏ hàng', 'danger');
                    });
                });
            });
            
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const cartId = this.getAttribute('data-id');
                    const cartItem = this.closest('.cart-item');
                    
                    if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?')) {
                        const formData = new FormData();
                        formData.append('action', 'removeItem');
                        formData.append('cart_id', cartId);
                        
                        fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                cartItem.remove();
                                
                                document.querySelector('.cart-total').textContent = numberFormat(data.cartTotal) + ' coin';
                                
                                if (document.querySelectorAll('.cart-item').length === 0) {
                                    location.reload(); // Tải lại trang để hiển thị giỏ hàng trống
                                }
                                
                                showAlert('Đã xóa sản phẩm khỏi giỏ hàng', 'success');
                            } else {
                                showAlert(data.message, 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('Đã xảy ra lỗi khi xóa sản phẩm', 'danger');
                        });
                    }
                });
            });
            
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    if (confirm('Bạn có chắc chắn muốn thanh toán giỏ hàng này?')) {
                        const formData = new FormData();
                        formData.append('action', 'checkout');
                        
                        fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showAlert(data.message, 'success');
                                setTimeout(() => {
                                    window.location.href = 'profile.php';
                                }, 2000);
                            } else {
                                if (data.outOfStockItems) {
                                    let errorMessage = 'Một số sản phẩm không đủ số lượng:<br>';
                                    data.outOfStockItems.forEach(item => {
                                        errorMessage += `- ${item.name}: Yêu cầu ${item.requested}, còn lại ${item.available}<br>`;
                                    });
                                    showAlert(errorMessage, 'danger');
                                } else {
                                    showAlert(data.message, 'danger');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('Đã xảy ra lỗi trong quá trình thanh toán', 'danger');
                        });
                    }
                });
            }
            
            function numberFormat(number) {
                return new Intl.NumberFormat('vi-VN').format(number);
            }
        });
    </script>
</body>
</html>