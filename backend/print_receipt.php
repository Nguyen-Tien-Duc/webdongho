<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit();
}
require_once "../database/db.php";

$isLoggedIn = isset($_SESSION['tentaikhoan']);
$tentaikhoan = $isLoggedIn ? $_SESSION['tentaikhoan'] : '';
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn();
}
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
$user_id = $_SESSION['user_id'];

function getProductDetails($conn, $vatpham_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM vatpham WHERE id = :id");
        $stmt->bindParam(':id', $vatpham_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

function getAccessoryDetails($conn, $phukien_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM phukien WHERE id = :id");
        $stmt->bindParam(':id', $phukien_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

try {
    $stmt = $conn->prepare("SELECT lt.*, ls.coin 
                          FROM lichsuthanhtoan lt 
                          LEFT JOIN lichsunap ls ON lt.coin_id = ls.id 
                          WHERE lt.taikhoan_id = :user_id AND lt.trangthai = 'đã thanh toán'
                          ORDER BY lt.thoigian DESC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử mua hàng - Đồng Hồ Shop</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
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
                        <div class="mega-column">
                            <h4>ĐỒNG HỒ THÔNG MINH</h4>
                            <ul>
                                <li><a href="product.php?loaisanpham=thông minh">Xem tất cả</a></li>
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
                                <li><a href="product.php?phukien=true&loaiphukien=dây">Dây đồng hồ</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=hộp đựng">Hộp đựng đồng hồ</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=máy lên dây cót">Máy lên dây cót</a></li>
                                <li><a href="product.php?phukien=true&loaiphukien=kính bảo vệ màn hình">Kính bảo vệ màn hình</a></li>
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

    <div class="container my-5">
        <h1 class="text-center mb-4">LỊCH SỬ MUA HÀNG</h1>

        <?php if (empty($purchases)): ?>
            <div class="alert alert-info text-center">
                <p>Bạn chưa có giao dịch mua hàng nào.</p>
                <a href="profile.php" class="btn btn-primary mt-3">Thông Tin Tài Khoản</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($purchases as $purchase): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-primary text-white d-flex justify-content-between">
                                <span>
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($purchase['thoigian'])); ?>
                                </span>
                                <span class="badge bg-success">
                                    <?php echo $purchase['trangthai']; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php
                                if (!empty($purchase['vatpham_id'])) {
                                    $product = getProductDetails($conn, $purchase['vatpham_id']);
                                    if ($product): 
                                ?>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <?php if (!empty($product['url'])): ?>
                                                <img src="/WebDongHo/<?php echo $product['url']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['tenvatpham']); ?>" 
                                                     class="img-fluid rounded">
                                            <?php else: ?>
                                                <img src="../assets/images/default-watch.jpg" 
                                                     alt="Default image" 
                                                     class="img-fluid rounded">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-8">
                                            <h5 class="card-title"><?php echo htmlspecialchars($product['tenvatpham']); ?></h5>
                                            <p class="card-text text-muted">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['thuonghieu']); ?>
                                            </p>
                                            <p class="card-text">
                                                <strong>Loại:</strong> <?php echo htmlspecialchars($product['loaisanpham']); ?><br>
                                                <strong>Chất liệu:</strong> <?php echo htmlspecialchars($product['chatlieu']); ?><br>
                                                <strong>Dành cho:</strong> <?php echo htmlspecialchars($product['gioitinh']); ?><br>
                                                <strong>Số lượng:</strong> <?php echo $purchase['sll']; ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="text-danger">
                                                    <?php echo number_format($product['giatien'], 0, ',', '.'); ?> VNĐ
                                                </h5>
                                                <a href="product-detail.php?product_id=<?php echo $product['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-info-circle"></i> Chi tiết
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                } elseif (!empty($purchase['phukien_id'])) {
                                    $accessory = getAccessoryDetails($conn, $purchase['phukien_id']);
                                    if ($accessory): 
                                ?>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <?php if (!empty($accessory['url'])): ?>
                                                <img src="../uploads/<?php echo $accessory['url']; ?>" 
                                                     alt="<?php echo htmlspecialchars($accessory['ten']); ?>" 
                                                     class="img-fluid rounded">
                                            <?php else: ?>
                                                <img src="../assets/images/default-accessory.jpg" 
                                                     alt="Default image" 
                                                     class="img-fluid rounded">
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-8">
                                            <h5 class="card-title"><?php echo htmlspecialchars($accessory['ten']); ?></h5>
                                            <p class="card-text">
                                                <strong>Loại phụ kiện:</strong> <?php echo htmlspecialchars($accessory['loaiphukien']); ?><br>
                                                <strong>Số lượng:</strong> <?php echo $purchase['sll']; ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="text-danger">
                                                    <?php echo number_format($accessory['giatien'], 0, ',', '.'); ?> VNĐ
                                                </h5>
                                                <a href="../backend/accessory-detail.php?accessory_id=<?php echo $accessory['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-info-circle"></i> Chi tiết
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                } else {
                                    echo '<p class="text-muted">Thông tin sản phẩm không có sẵn.</p>';
                                }
                                ?>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <i class="fas fa-coins"></i> Đã dùng: 
                                        <?php echo number_format($purchase['coin_id'] > 0 ? $purchase['coin'] : 0, 0, ',', '.'); ?> VNĐ
                                    </span>
                                    <a href="javascript:void(0);" class="btn btn-sm btn-outline-secondary"
                                       onclick="printReceipt(<?php echo $purchase['id']; ?>)">
                                        <i class="fas fa-print"></i> In hóa đơn
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printReceipt(purchaseId) {
            window.open('print_receipt1.php?id=' + purchaseId, '_blank', 'width=800,height=600');
        }
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