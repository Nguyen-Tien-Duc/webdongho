<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit;
}

require_once '../database/db.php';

$isLoggedIn = isset($_SESSION['tentaikhoan']);
$tentaikhoan = $isLoggedIn ? $_SESSION['tentaikhoan'] : '';
$cartCount = 0;

// Get cart count for user
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn();
}

// Check if accessory_id is provided in the URL
if (!isset($_GET['accessory_id']) || !is_numeric($_GET['accessory_id'])) {
    header("Location: product.php?phukien=true");
    exit;
}

$accessory_id = (int)$_GET['accessory_id'];

try {
    // Get accessory details
    $stmt = $conn->prepare("SELECT * FROM phukien WHERE id = ? AND sll > 0");
    $stmt->execute([$accessory_id]);
    $accessory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$accessory) {
        header("Location: product.php?phukien=true");
        exit;
    }

    // Get user information if logged in
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }

    // Get similar accessories (same type)
    $stmt = $conn->prepare("SELECT * FROM phukien WHERE loaiphukien = ? AND id != ? AND sll > 0 ORDER BY id DESC LIMIT 4");
    $stmt->execute([$accessory['loaiphukien'], $accessory_id]);
    $similar_accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle add to cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!$isLoggedIn) {
        header("Location: ../login/login1.php");
        exit;
    }

    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Validate quantity
    if ($quantity < 1) {
        $quantity = 1;
    } else if ($quantity > $accessory['sll']) {
        $quantity = $accessory['sll'];
    }

    try {
        // Check if accessory already in cart
        $stmt = $conn->prepare("SELECT id, soluong FROM giohang WHERE taikhoan_id = ? AND phukien_id = ?");
        $stmt->execute([$_SESSION['user_id'], $accessory_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_item) {
            // Update quantity if already in cart
            $new_quantity = $existing_item['soluong'] + $quantity;
            if ($new_quantity > $accessory['sll']) {
                $new_quantity = $accessory['sll'];
            }
            
            $stmt = $conn->prepare("UPDATE giohang SET soluong = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing_item['id']]);
        } else {
            // Add new item to cart
            $stmt = $conn->prepare("INSERT INTO giohang (taikhoan_id, phukien_id, soluong) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $accessory_id, $quantity]);
        }

        // Update cart count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cartCount = $stmt->fetchColumn();

        // Add success message
        $_SESSION['cart_message'] = "Sản phẩm đã được thêm vào giỏ hàng!";
        
        // Redirect to prevent form resubmission
        header("Location: cart.php");
        exit;
    } catch(PDOException $e) {
        die("Error adding to cart: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($accessory['ten']) ?> - Shop Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        /* Container chính của trang chi tiết sản phẩm */
        .pd_content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-start;
        }

        .product-gallery {
            flex: 1;
            min-width: 300px;
            padding-right: 30px;
        }
        
        .product-gallery img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .product-info {
            flex: 1;
            min-width: 300px;
        }
        
        .product-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .product-description {
            margin-bottom: 30px;
            line-height: 1.6;
            color: #555;
        }
        
        .product-meta {
            margin-bottom: 30px;
        }
        
        .meta-item {
            margin-bottom: 10px;
            display: flex;
        }
        
        .meta-label {
            font-weight: bold;
            width: 120px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: #f1f1f1;
            border: none;
            border-radius: 4px;
            font-size: 18px;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            margin: 0 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .add-to-cart-btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #e74c3c;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .similar-products {
            margin: 60px 0;
        }
        
        .similar-products h2 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .pd_content {
                flex-direction: column;
                padding: 15px;
                margin: 20px auto;
            }
            
            .product-gallery, .product-info {
                width: 100%;
                padding-right: 0;
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-top">
            <div class="container">
                <div class="promotion-banner">
                    <a href="#">BỘ SƯU TẬP ĐỒNG HỒ MỚI VỀ!</a>
                    <p>ĐĂNG KÝ NHẬN THÔNG TIN MỚI NHẤT!</p>
                    <p>GẶP CHUYÊN GIA TƯ VẤN!</p>
                </div>
            </div>
        </div>
        <div class="header-main">
            <div class="container">
                <div class="logo">
                    <a href="index.php">
                        <img src="../frontend/assets/images/logo1.png" alt="Logo">
                    </a>
                </div>
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Tìm kiếm" autocomplete="off">
                    <button type="submit" class="search-button"></button>
                    <div id="search-suggestions" class="search-suggestions"></div>
                </div>
                <div class="header-actions">
                    <div class="cart">
                        <a href="../backend/cart.php" class="cart-icon">
                            <span class="cart-count" id="cart-count"><?= $cartCount ?></span>
                        </a>
                    </div>
                    <div class="user-section">
                        <?php if (!$isLoggedIn): ?>
                            <div class="login-btn" id="login-btn">
                                <a href="/login/login1.php" class="action-button">Đăng nhập</a>
                            </div>
                        <?php else: ?>
                            <div class="user-info" id="user-info">
                                <a href="profile.php" class="user-logo">
                                    <?php
                                    $base_url = "/webdongho";
                                    ?>
                                    <img src="<?= $user['avatar'] ? $base_url . '/' . htmlspecialchars($user['avatar']) : $base_url . '../frontend/assets/images/anh-dai-dien.png' ?>" alt="Avatar" width="120" height="120">
                                </a>
                                <span class="account-name" id="account-name"><?= htmlspecialchars($tentaikhoan) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <div class="account-btn">
                            <a href="logout.php" class="action-button">Đăng Xuất</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <button id="nav-toggle" class="nav-toggle">☰</button>
    <nav class="main-nav">
        <div class="container">
            <ul class="nav-list">
                <li class="has-megamenu">
                    <a href="#">THƯƠNG HIỆU</a>
                    <div class="mega-menu">
                        <div class="mega-column">
                            <h4>CAO CẤP</h4>
                            <ul>
                                <li><a href="product.php?thuonghieu=Rolex">Rolex</a></li>
                                <li><a href="product.php?thuonghieu=Omega">Omega</a></li>
                                <li><a href="product.php?thuonghieu=Patek Philippe">Patek Philippe</a></li>
                                <li><a href="product.php?thuonghieu=Hublot">Hublot</a></li>
                                <li><a href="product.php?thuonghieu=TAG Heuer">TAG Heuer</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>TRUNG CẤP</h4>
                            <ul>
                                <li><a href="product.php?thuonghieu=Seiko">Seiko</a></li>
                                <li><a href="product.php?thuonghieu=Tissot">Tissot</a></li>
                                <li><a href="product.php?thuonghieu=Orient">Orient</a></li>
                                <li><a href="product.php?thuonghieu=Bulova">Bulova</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>PHỔ THÔNG</h4>
                            <ul>
                                <li><a href="product.php?thuonghieu=Casio">Casio</a></li>
                                <li><a href="product.php?thuonghieu=Fossil">Fossil</a></li>
                                <li><a href="product.php?thuonghieu=Timex">Timex</a></li>
                                <li><a href="product.php?thuonghieu=Daniel Wellington">Daniel Wellington</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li class="has-megamenu">
                    <a href="product.php?gioitinh=Nam">NAM</a>
                    <div class="mega-menu">
                        <div class="mega-column">
                            <h4>KHOẢNG GIÁ</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nam&price_min=0&price_max=2000000">Dưới 2tr</a></li>
                                <li><a href="product.php?gioitinh=Nam&price_min=2000000&price_max=3000000">2tr - 3tr</a></li>
                                <li><a href="product.php?gioitinh=Nam&price_min=3000000&price_max=4000000">3tr - 4tr</a></li>
                                <li><a href="product.php?gioitinh=Nam&price_min=4000000&price_max=5000000">4tr - 5tr</a></li>
                                <li><a href="product.php?gioitinh=Nam&price_min=5000000">Trên 5tr</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>KIỂU DÁNG</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nam&loaisanpham=cơ">Đồng hồ cơ</a></li>
                                <li><a href="product.php?gioitinh=Nam&loaisanpham=quartz">Đồng hồ quartz</a></li>
                                <li><a href="product.php?gioitinh=Nam&loaisanpham=điện tử">Đồng hồ điện tử</a></li>
                                <li><a href="product.php?gioitinh=Nam&loaisanpham=thông minh">Đồng hồ thông minh</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>CHẤT LIỆU DÂY</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nam& Bs=">Dây kim loại</a></li>
                                <li><a href="product.php?gioitinh=Nam&chatlieu=da">Dây da</a></li>
                                <li><a href="product.php?gioitinh=Nam&chatlieu=silicone">Dây silicone</a></li>
                                <li><a href="product.php?gioitinh=Nam&chatlieu=vải và nato">Dây vải / NATO</a></li>
                                <li><a href="product.php?gioitinh=Nam&chatlieu=milamese">Dây Milanese</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li class="has-megamenu">
                    <a href="product.php?gioitinh=Nữ">NỮ</a>
                    <div class="mega-menu">
                        <div class="mega-column">
                            <h4>KHOẢNG GIÁ</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nữ&price_min=0&price_max=2000000">Dưới 2tr</a></li>
                                <li><a href="product.php?gioitinh=Nữ&price_min=2000000&price_max=3000000">2tr - 3tr</a></li>
                                <li><a href="product.php?gioitinh=Nữ&price_min=3000000&price_max=4000000">3tr - 4tr</a></li>
                                <li><a href="product.php?gioitinh=Nữ&price_min=4000000&price_max=5000000">4tr - 5tr</a></li>
                                <li><a href="product.php?gioitinh=Nữ&price_min=5000000">Trên 5tr</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>KIỂU DÁNG</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nữ&loaisanpham=cơ">Đồng hồ cơ</a></li>
                                <li><a href="product.php?gioitinh=Nữ&loaisanpham=quartz">Đồng hồ quartz</a></li>
                                <li><a href="product.php?gioitinh=Nữ&loaisanpham=điện tử">Đồng hồ điện tử</a></li>
                                <li><a href="product.php?gioitinh=Nữ&loaisanpham=thông minh">Đồng hồ thông minh</a></li>
                            </ul>
                        </div>
                        <div class="mega-column">
                            <h4>CHẤT LIỆU DÂY</h4>
                            <ul>
                                <li><a href="product.php?gioitinh=Nữ&chatlieu=kim loại">Dây kim loại</a></li>
                                <li><a href="product.php?gioitinh=Nữ&chatlieu=da">Dây da</a></li>
                                <li><a href="product.php?gioitinh=Nữ&chatlieu=silicone">Dây silicone</a></li>
                                <li><a href="product.php?gioitinh=Nữ&chatlieu=vải và nato">Dây vải / NATO</a></li>
                                <li><a href="product.php?gioitinh=Nữ&chatlieu=milamese">Dây Milanese</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li class="has-megamenu">
                    <a href="product.php?phukien=true">PHỤ KIỆN</a>
                    <div class="mega-menu">
                        <div class="mega-column">
                            <h4>PHỤ KIỆN</h4>
                            <ul>
                                <li><a href="product.php?phukien=true&loaiphukien=Dây đồng hồ">Dây đồng hồ</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=Hộp đựng đồng hồ">Hộp đựng đồng hồ</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=Máy lên dây cót">Máy lên dây cót</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=Kính bảo vệ màn hình">Kính bảo vệ màn hình</a></li>
                            </ul>
                        </div>
                    </div>
                </li>
                <li>
                    <a href="contact.php">LIÊN HỆ</a>
                </li>               
            </ul>
        </div>
    </nav>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Trang chủ</a> &gt; 
            <a href="product.php?phukien=true">Phụ kiện</a> &gt; 
            <a href="product.php?phukien=true&loaiphukien=<?= urlencode($accessory['loaiphukien']) ?>"><?= htmlspecialchars($accessory['loaiphukien']) ?></a> &gt; 
            <span><?= htmlspecialchars($accessory['ten']) ?></span>
        </div>
        
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="success-message">
                <?= $_SESSION['cart_message'] ?>
            </div>
            <?php unset($_SESSION['cart_message']); ?>
        <?php endif; ?>
        
        <div class="pd_content">
            <div class="product-gallery">
                <img src="../Uploads/<?= htmlspecialchars($accessory['url']) ?>" alt="<?= htmlspecialchars($accessory['ten']) ?>">
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($accessory['ten']) ?></h1>
                <div class="product-price"><?= number_format($accessory['giatien'], 0, ',', '.') ?> ₫</div>
                
                <div class="product-description">
                    <?= nl2br(htmlspecialchars($accessory['mota'])) ?>
                </div>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <div class="meta-label">Loại phụ kiện:</div>
                        <div><?= htmlspecialchars($accessory['loaiphukien']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Mã sản phẩm:</div>
                        <div><?= htmlspecialchars($accessory['uid_phukien']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Tình trạng:</div>
                        <div><?= $accessory['sll'] > 0 ? 'Còn hàng (' . $accessory['sll'] . ')' : 'Hết hàng' ?></div>
                    </div>
                </div>
                
                <form method="post" action="">
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn minus">-</button>
                        <input type="number" name="quantity" id="quantity" class="quantity-input" value="1" min="1" max="<?= $accessory['sll'] ?>">
                        <button type="button" class="quantity-btn plus">+</button>
                    </div>
                    
                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                        <i class="fas fa-shopping-cart"></i> Thêm vào giỏ hàng
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($similar_accessories)): ?>
            <div class="similar-products">
                <h2>Sản phẩm tương tự</h2>
                <div style="width: 300px;" class="products-grid">
                    <?php foreach ($similar_accessories as $similar): ?>
                        <div class="product-item">
                            <a href="accessory-detail.php?accessory_id=<?= $similar['id'] ?>" class="product-link">
                                <div class="product-image">
                                    <img src="../Uploads/<?= htmlspecialchars($similar['url']) ?>" alt="<?= htmlspecialchars($similar['ten']) ?>" class="default-img">
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name"><?= htmlspecialchars($similar['ten']) ?></h3>
                                    <p class="product-desc"><?= mb_substr(htmlspecialchars($similar['mota']), 0, 80) . (mb_strlen($similar['mota']) > 80 ? '...' : '') ?></p>
                                    <div class="product-price"><?= number_format($similar['giatien'], 0, ',', '.') ?> ₫</div>
                                    <button class="add-to-cart">Xem chi tiết</button>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
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
    document.addEventListener('DOMContentLoaded', () => {
    // Quantity selector
    const quantityControls = {
        minusBtn: document.querySelector('.quantity-btn.minus'),
        plusBtn: document.querySelector('.quantity-btn.plus'),
        input: document.getElementById('quantity'),
        maxQuantity: <?= $accessory['sll'] ?>
    };

    // Quantity button handlers
    quantityControls.minusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityControls.input.value);
        if (currentValue > 1) {
            quantityControls.input.value = currentValue - 1;
        }
    });

    quantityControls.plusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityControls.input.value);
        if (currentValue < quantityControls.maxQuantity) {
            quantityControls.input.value = currentValue + 1;
        }
    });

    // Quantity input validation
    quantityControls.input.addEventListener('change', function() {
        const currentValue = parseInt(this.value);
        if (isNaN(currentValue) || currentValue < 1) {
            this.value = 1;
        } else if (currentValue > quantityControls.maxQuantity) {
            this.value = quantityControls.maxQuantity;
        }
    });

    // Mobile navigation toggle
    const navToggle = document.getElementById('nav-toggle');
    const mainNav = document.querySelector('.main-nav');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', () => {
            mainNav.classList.toggle('active');
        });
    }

    // Search functionality
    const searchInput = document.getElementById('search-input');
    const searchSuggestions = document.getElementById('search-suggestions');

    if (searchInput && searchSuggestions) {
        let debounceTimer;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length > 0) {
                debounceTimer = setTimeout(() => {
                    fetch(`../backend/search_suggestions.php?query=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            searchSuggestions.innerHTML = '';

                            if (data.length > 0) {
                                searchSuggestions.style.display = 'block';

                                data.forEach(item => {
                                    const suggestionItem = document.createElement('div');
                                    suggestionItem.classList.add('suggestion-item');

                                    const itemLink = item.type === 'watch'
                                        ? `watch-detail.php?watch_id=${item.id}`
                                        : `accessory-detail.php?accessory_id=${item.id}`;

                                    suggestionItem.innerHTML = `
                                        <a href="${itemLink}">
                                            <img src="/WebDongHo/${item.url}" alt="${item.ten}">
                                            <div class="suggestion-info">
                                                <h4>${item.ten}</h4>
                                                <p>${new Intl.NumberFormat('vi-VN').format(item.giatien)} ₫</p>
                                            </div>
                                        </a>
                                    `;

                                    searchSuggestions.appendChild(suggestionItem);
                                });
                            } else {
                                searchSuggestions.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching search suggestions:', error);
                        });
                }, 300);
            } else {
                searchSuggestions.style.display = 'none';
            }
        });

        // Close search suggestions when clicking outside
        document.addEventListener('click', (event) => {
            if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                searchSuggestions.style.display = 'none';
            }
        });
    }
});
    </script>
</body>
</html>