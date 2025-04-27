<?php
require_once '../database/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$randomProducts = array();
$sql = "SELECT id, tenvatpham, giatien, loaisanpham, url, thuonghieu, chatlieu, gioitinh, mota FROM vatpham WHERE sll > 0 ORDER BY RAND() LIMIT 4";
$stmt = $conn->query($sql);
$randomProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = 'cart.php';
        header('Location: ../login/login1.php');
        exit;
    }
    $taikhoan_id = $_SESSION['user_id'];
    $vatpham_id = $_POST['vatpham_id'];
    $soluong = 1;
    $check_sql = "SELECT sll FROM vatpham WHERE id = ? AND sll > 0";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$vatpham_id]);
    $product_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
    if ($product_data) {
        if ($product_data['sll'] >= $soluong) {
            $check_cart_sql = "SELECT id, soluong FROM giohang WHERE taikhoan_id = ? AND vatpham_id = ?";
            $check_cart_stmt = $conn->prepare($check_cart_sql);
            $check_cart_stmt->execute([$taikhoan_id, $vatpham_id]);
            $cart_item = $check_cart_stmt->fetch(PDO::FETCH_ASSOC);
            if ($cart_item) {
                $new_quantity = $cart_item['soluong'] + $soluong;
                if ($new_quantity <= $product_data['sll']) {
                    $update_sql = "UPDATE giohang SET soluong = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([$new_quantity, $cart_item['id']]);
                } else {
                    $_SESSION['cart_error'] = "Số lượng sản phẩm vượt quá số lượng tồn kho.";
                    header('Location: cart.php');
                    exit;
                }
            } else {
                $insert_sql = "INSERT INTO giohang (taikhoan_id, vatpham_id, phukien_id, soluong) VALUES (?, ?, NULL, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->execute([$taikhoan_id, $vatpham_id, $soluong]);
            }

            $_SESSION['cart_success'] = "Đã thêm sản phẩm vào giỏ hàng thành công.";
            header('Location: cart.php');
            exit;
        } else {
            $_SESSION['cart_error'] = "Số lượng sản phẩm vượt quá số lượng tồn kho.";
        }
    } else {
        $_SESSION['cart_error'] = "Sản phẩm không tồn tại hoặc đã hết hàng.";
    }

    header('Location: cart.php');
    exit;
}
?>

<!--<style>
    /* Similar Products Section */
    .similar-products {
        padding: 30px 0;
        background-color: #fff;
        margin-bottom: 20px;
    }

    .similar-products h2 {
        font-size: 24px;
        margin-bottom: 30px;
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
    }

    .similar-products .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 30px;
    }

    .similar-products .product-item {
        background-color: #fff;
        border-radius: 5px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .similar-products .product-link {
        display: block;
    }

    .similar-products .product-image {
        height: 200px;
        overflow: hidden;
        position: relative;
    }

    .similar-products .product-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .similar-products .product-info {
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
        text-align: center;
    }

    .similar-products .product-name {
        font-size: 14px;
        margin-bottom: 0;
        font-weight: 500;
        height: 40px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .similar-products .product-details {
        font-size: 12px;
        color: #666;
        margin-bottom: 0;
        height: 40px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .similar-products .product-details p {
        margin: 0;
    }

    .similar-products .product-price {
        color: #9d2a28;
        font-weight: bold;
        font-size: 16px;
    }

    .similar-products .add-to-cart {
        display: block;
        margin: 0 auto;
        background-color: #9d2a28;
        color: #fff;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 12px;
        cursor: pointer;
    }
</style> -->

<link rel="stylesheet" href="../frontend/assets/css/style.css">

<section class="best-sellers similar-products">
    <div class="container">
        <h2>SẢN PHẨM TƯƠNG TỰ</h2>
        <div class="products-grid">
            <?php if (count($randomProducts) > 0): ?>
                <?php foreach ($randomProducts as $product): ?>
                    <?php 
                        $product_details = $product['mota'] ?? "{$product['thuonghieu']} – {$product['loaisanpham']} – {$product['gioitinh']}";
                        $formatted_price = number_format($product['giatien'], 0, ',', '.') . ' ₫';
                        $image_url = !empty($product['url']) ? $product['url'] : "../frontend/assets/images/RA-AS0105S30B-2025.png";
                    ?>
                    <div class="product-item">
                        <a href="product-detail.php?product_id=<?php echo $product['id']; ?>" class="product-link">
                            <div class="product-image">
                                <img src="/WebDongHo/<?php echo $image_url; ?>" alt="<?php echo $product['tenvatpham']; ?>" class="default-img">
                            </div>
                            <div class="product-info">
                                <h3 style="font-weight: bold;" class="product-name"><?php echo $product['tenvatpham']; ?></h3>
                                <div class="product-details">
                                    <p style="font-size: 13px;" ><?php echo $product_details; ?></p>
                                </div>
                                <div class="product-price"><?php echo $formatted_price; ?></div>
                            </div>
                        </a>
                        <form method="POST">
                            <input type="hidden" name="vatpham_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="add_to_cart" value="1">
                            <button style="margin-bottom: 10px;" type="submit" class="add-to-cart">Thêm vào giỏ</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Không có sản phẩm nào.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
