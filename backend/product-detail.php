<?php
session_start();
require '../database/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Xác định loại sản phẩm: vatpham hoặc phukien
$product_type = isset($_GET['phukien']) && $_GET['phukien'] === 'true' ? 'phukien' : 'vatpham';
$valid_types = ['vatpham', 'phukien'];
if (!in_array($product_type, $valid_types)) {
    $product_type = 'vatpham'; // Mặc định nếu type không hợp lệ
}

if (isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    
    // Truy vấn dựa vào loại sản phẩm
    if ($product_type == 'vatpham') {
        $sql = "SELECT * FROM vatpham WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        // Lấy thông tin đánh giá (chỉ áp dụng cho vatpham)
        if ($product) {
            $checkRating = $conn->prepare("SELECT * FROM danhgia WHERE taikhoan_id = ? AND vatpham_id = ?");
            $checkRating->execute([$user_id, $product_id]);
            $userRating = $checkRating->fetch();
            
            $allRatings = $conn->prepare("SELECT d.*, t.tentaikhoan FROM danhgia d JOIN taikhoan t ON d.taikhoan_id = t.id WHERE d.vatpham_id = ? ORDER BY d.thoigian DESC");
            $allRatings->execute([$product_id]);
            $ratings = $allRatings->fetchAll();
            
            $avgRating = $conn->prepare("SELECT AVG(sosao) as average FROM danhgia WHERE vatpham_id = ?");
            $avgRating->execute([$product_id]);
            $avgResult = $avgRating->fetch();
            $averageRating = $avgResult['average'] ? number_format($avgResult['average'], 1) : 'Chưa có đánh giá';
        }
    } else { // phukien
        $sql = "SELECT * FROM phukien WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        // Phụ kiện không có đánh giá, nên khởi tạo giá trị mặc định
        $ratings = [];
        $userRating = null;
        $averageRating = 'Không áp dụng';
    }

    if (!$product) {
        echo "<p>Không tìm thấy sản phẩm.</p>";
        exit;
    }
} else {
    echo "<p>Thiếu thông tin sản phẩm.</p>";
    exit;
}

// Xử lý thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $check = $conn->prepare("SELECT * FROM giohang WHERE taikhoan_id = ? AND " . 
                          ($product_type == 'vatpham' ? "vatpham_id = ? AND phukien_id IS NULL" : "phukien_id = ? AND vatpham_id IS NULL"));
    $check->execute([$user_id, $product_id]);
    $existing = $check->fetch();

    if ($existing) {
        $update = $conn->prepare("UPDATE giohang SET soluong = soluong + 1 WHERE id = ?");
        $update->execute([$existing['id']]);
    } else {
        $insert = $conn->prepare("INSERT INTO giohang (taikhoan_id, " . 
                               ($product_type == 'vatpham' ? "vatpham_id, phukien_id" : "phukien_id, vatpham_id") . 
                               ", soluong) VALUES (?, ?, " . 
                               ($product_type == 'vatpham' ? "NULL" : "NULL") . ", 1)");
        $insert->execute([$user_id, $product_id]);
    }
    echo "<script>alert('Đã thêm vào giỏ hàng!'); window.location.href='cart.php';</script>";
    exit;
}

// Lấy thông tin người dùng
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
$isLoggedIn = isset($_SESSION['tentaikhoan']);
$tentaikhoan = $isLoggedIn ? $_SESSION['tentaikhoan'] : '';
// 
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    require_once '../database/db.php'; // Ensure database connection is included
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết sản phẩm</title>
    <link rel="stylesheet" href="../frontend//assets/css/style.css">
    <link rel="stylesheet" href="../frontend/assets/css/product-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #e1e1e1;
            border-top: none;
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }

        .suggestion-item:hover {
            background-color: #f9f9f9;
        }

        .suggestion-item img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            object-fit: cover;
        }

        .suggestion-content {
            display: flex;
            align-items: center;
        }

        .suggestion-info {
            flex: 1;
        }

        .suggestion-name {
            font-weight: 500;
            margin-bottom: 3px;
        }

        .suggestion-price {
            font-size: 12px;
            color: #9d2a28;
        }

        .product-desc {
            font-size: 13px;
            color: #777;
            margin: 5px 0;
            line-height: 1.4;
            height: 36px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-info {
            padding: 10px;
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
                                <li><a href="product.php?gioitinh=Nam&chatlieu=kim loại">Dây kim loại</a></li>
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

    
    <div class="pd_content">
        <div class="pd_image">
            <img src="/WebDongHo/<?= htmlspecialchars($product['url']) ?>" alt="<?= $product_type == 'vatpham' ? htmlspecialchars($product['tenvatpham']) : htmlspecialchars($product['ten']) ?>" class="default-img">
        </div>
        
        <div class="pd_info">
            <div class="pd_info_grid">
                <?php if ($product_type == 'vatpham'): ?>
                    <p><strong>Giới tính:</strong> <?= htmlspecialchars($product['gioitinh']) ?></p>
                    <p><strong>Chất liệu:</strong> <?= htmlspecialchars($product['chatlieu']) ?></p>
                    <p><strong>Loại:</strong> <?= htmlspecialchars($product['loaisanpham']) ?></p>
                    <p><strong>Thương hiệu:</strong> <?= htmlspecialchars($product['thuonghieu']) ?></p>
                <?php else: ?>
                    <p><strong>Loại phụ kiện:</strong> <?= htmlspecialchars($product['loaiphukien']) ?></p>
                    <p></p> <!-- Empty cell for grid alignment -->
                <?php endif; ?>
                <p><strong>Số lượng còn lại:</strong> <?= htmlspecialchars($product['sll']) ?></p>
                <p><strong>Giá:</strong> <?= number_format($product['giatien'], 0, ',', '.') ?> ₫</p>
            </div>
            
            <div class="pd_description">
                <p><strong>Mô tả</strong> <?= nl2br(htmlspecialchars($product['mota'])) ?></p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="product_type" value="<?= $product_type ?>">
                <button type="submit" name="add_to_cart" class="pd_add_to_cart">Thêm vào giỏ hàng</button>
            </form>
        </div>             
    
        <?php if ($product_type == 'vatpham'): // Chỉ hiển thị phần đánh giá cho vatpham ?>
        <div class="pd_review_section">
            <h2>Đánh giá sản phẩm</h2>
            <div class="pd_average_rating">
                <p>Đánh giá trung bình: <?= $averageRating ?></p>
                <?php if (isset($avgResult['average']) && $avgResult['average']): ?>
                <div class="stars">
                    <?php 
                    $avg = round($avgResult['average']);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $avg) {
                            echo '<i class="fas fa-star"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="pd_review_box">
                <?php if (!$userRating): ?>
                <h3>Viết đánh giá của bạn</h3>
                <form action="submit_rating.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    
                    <div class="rating">
                        <input type="radio" name="rating" value="5" id="5"><label for="5">☆</label>
                        <input type="radio" name="rating" value="4" id="4"><label for="4">☆</label>
                        <input type="radio" name="rating" value="3" id="3"><label for="3">☆</label>
                        <input type="radio" name="rating" value="2" id="2"><label for="2">☆</label>
                        <input type="radio" name="rating" value="1" id="1"><label for="1">☆</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Nhận xét:</label>
                        <textarea name="comment" id="comment" rows="4" style="width: 100%;"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_rating" class="btn" >Gửi đánh giá</button>
                </form>
                <?php else: ?>
                <h3>Đánh giá của bạn</h3>
                <div class="review-item">
                    <div class="stars">
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $userRating['sosao']) {
                                echo '<i class="fas fa-star"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div class="comment"><?= nl2br(htmlspecialchars($userRating['binhluan'])) ?></div>
                    <div class="date">Đánh giá vào: <?= date('d/m/Y H:i', strtotime($userRating['thoigian'])) ?></div>
                    <a href="edit_rating.php?id=<?= $userRating['id'] ?>&product_id=<?= $product_id ?>" class="btn">Chỉnh sửa</a>
                    <a href="delete_rating.php?id=<?= $userRating['id'] ?>&product_id=<?= $product_id ?>" class="btn" onclick="return confirm('Bạn có chắc muốn xóa đánh giá này?');">Xóa</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="pd_review_list">
                <h3>Tất cả đánh giá (<?= count($ratings) ?>)</h3>
                <?php if (count($ratings) > 0): ?>
                    <?php foreach ($ratings as $rating): ?>
                        <div class="review-item">
                            <div class="user"><?= htmlspecialchars($rating['tentaikhoan']) ?></div>
                            <div class="stars">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating['sosao']) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="comment"><?= nl2br(htmlspecialchars($rating['binhluan'])) ?></div>
                            <div class="date"><?= date('d/m/Y H:i', strtotime($rating['thoigian'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Chưa có đánh giá nào cho sản phẩm này.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Quay về trang chủ</a>
    </div>

    <?php
    // Gọi tới similar-products.php với tham số loại sản phẩm (vatpham hoặc phukien)
    include 'similar-products.php'; 
    ?>



    <!-- Footer Section -->
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
                            <li>Email: <a href="mailto:okokmen07@gmail.com">okokmen07@gmail.com</a></li>
                            <li>Địa chỉ: 70 Tô Kí</li>
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
        // Thêm JavaScript cho các chức năng tương tác nếu cần
        document.getElementById('nav-toggle').addEventListener('click', function() {
            document.querySelector('.main-nav').classList.toggle('active');
        });

    
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const searchSuggestions = document.getElementById('search-suggestions');
            let typingTimer;
            const doneTypingInterval = 30;

            searchInput.addEventListener('input', function() {
                clearTimeout(typingTimer);
                if (searchInput.value) {
                    typingTimer = setTimeout(getSuggestions, doneTypingInterval);
                } else {
                    searchSuggestions.style.display = 'none';
                }
            });

            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                    searchSuggestions.style.display = 'none';
                }
            });

            function getSuggestions() {
                const query = searchInput.value.trim();
                if (query.length < 2) {
                    searchSuggestions.style.display = 'none';
                    return;
                }

                fetch(`search_suggest.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchSuggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'suggestion-item';
                                div.innerHTML = `
                                    <div class="suggestion-content">
                                        <img src="/WebDongHo/${item.url || 'frontend/assets/images/no-image.png'}" alt="${item.tenvatpham}">
                                        <div class="suggestion-info">
                                            <div class="suggestion-name">${item.tenvatpham}</div>
                                            <div class="suggestion-price">${formatPrice(item.giatien)} ₫</div>
                                        </div>
                                    </div>
                                `;
                                div.addEventListener('click', function() {
                                    window.location.href = `product-detail.php?product_id=${item.id}`;
                                });
                                searchSuggestions.appendChild(div);
                            });
                            searchSuggestions.style.display = 'block';
                        } else {
                            searchSuggestions.innerHTML = '<div class="suggestion-item">Không tìm thấy sản phẩm</div>';
                            searchSuggestions.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Lỗi tìm kiếm:', error);
                    });
            }

            function formatPrice(price) {
                return Number(price).toLocaleString('vi-VN');
            }

            const filterForm = document.getElementById('filter-form');

            function setFilterValuesFromUrl() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('thuonghieu')) {
                    document.getElementById('filter-brand').value = urlParams.get('thuonghieu');
                }
                if (urlParams.has('price_range')) {
                    document.getElementById('filter-price').value = urlParams.get('price_range');
                }
                if (urlParams.has('loaisanpham')) {
                    document.getElementById('filter-type').value = urlParams.get('loaisanpham');
                }
                if (urlParams.has('gioitinh')) {
                    document.getElementById('filter-category').value = urlParams.get('gioitinh');
                }
                if (urlParams.has('chatlieu')) {
                    document.getElementById('filter-strap').value = urlParams.get('chatlieu');
                }
            }

            setFilterValuesFromUrl();
        });
    </script>
</body>
</html>