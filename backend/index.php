<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit();
}
require_once '../database/db.php'; 

$isLoggedIn = isset($_SESSION['tentaikhoan']);
$tentaikhoan = $isLoggedIn ? $_SESSION['tentaikhoan'] : '';

$limit = 8;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT * FROM vatpham WHERE gioitinh = 'Nam' LIMIT :start, :limit");
$stmt->bindParam(':start', $start, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$productsNam = $stmt->fetchAll(PDO::FETCH_ASSOC);
$countStmt = $conn->query("SELECT COUNT(*) FROM vatpham WHERE gioitinh = 'Nam'");
$totalNam = $countStmt->fetchColumn();
$totalPagesNam = ceil($totalNam / $limit);

$stmt = $conn->prepare("SELECT * FROM vatpham WHERE gioitinh = 'Nữ' LIMIT :start, :limit");
$stmt->bindParam(':start', $start, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$productsNu = $stmt->fetchAll(PDO::FETCH_ASSOC);
$countStmt = $conn->query("SELECT COUNT(*) FROM vatpham WHERE gioitinh = 'Nữ'");
$totalNu = $countStmt->fetchColumn();
$totalPagesNu = ceil($totalNu / $limit);

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn();
}

$searchQuery = "SELECT DISTINCT tenvatpham, id FROM vatpham 
                WHERE tenvatpham LIKE ? OR thuonghieu LIKE ? 
                ORDER BY tenvatpham ASC LIMIT 5";
$stmt = $conn->prepare($searchQuery);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đồng hồ đeo tay chính hãng 100%, cao cấp - Uy tín 34 năm</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        .has-megamenu:hover .mega-menu {
            display: flex;
        }

        .mega-menu {
            position: fixed;
            display: none;
            top: 210px;
            left: 50%;
            transform: translateX(-50%);
            width: 1200px;
            max-width: 90%;
            background-color: #fff;
            border: 1px solid #e1e1e1;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 20px;
            flex-wrap: nowrap;
            transition: opacity 0.3s ease;
        }

        .main-nav.fixed .mega-menu {
            top: 60px;
        }

        .mega-column {
            flex: 1;
            padding: 0 10px;
        }

        .mega-column h4 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #9d2a28;
            font-weight: bold;
            text-transform: uppercase;
        }

        .mega-column ul li {
            margin-bottom: 10px;
        }

        .mega-column ul li a {
            font-size: 13px;
            display: block;
        }

        .mega-column ul li a:hover {
            color: #9d2a28;
        }

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
                    <a href="../backend/contact.php">LIÊN HỆ</a>
                </li>
            </ul>
        </div>
    </nav>

    <section class="hero-banner">
        <div class="container">
            <div class="banner-slider">
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner.png" alt="Banner 1">
                </div>
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner1.png" alt="Banner 2">
                </div>
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner2.png" alt="Banner 3">
                </div>
            </div>
        </div>
    </section>

    <section class="filter-section">
        <div class="container">
            <h2 class="section-title">BỘ LỌC SẢN PHẨM</h2>
            <form id="filter-form" action="product.php" method="get">
                <div class="filter-container">
                    <div class="filter-group">
                        <h3>Thương hiệu</h3>
                        <select name="thuonghieu" id="filter-brand">
                            <option value="">Tất cả</option>
                            <option value="Rolex">Rolex</option>
                            <option value="Omega">Omega</option>
                            <option value="Patek Philippe">Patek Philippe</option>
                            <option value="Hublot">Hublot</option>
                            <option value="TAG Heuer">TAG Heuer</option>
                            <option value="Seiko">Seiko</option>
                            <option value="Tissot">Tissot</option>
                            <option value="Orient">Orient</option>
                            <option value="Bulova">Bulova</option>
                            <option value="Casio">Casio</option>
                            <option value="Fossil">Fossil</option>
                            <option value="Timex">Timex</option>
                            <option value="Daniel Wellington">Daniel Wellington</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <h3>Khoảng giá</h3>
                        <select name="price_range" id="filter-price">
                            <option value="">Tất cả</option>
                            <option value="0-2000000">Dưới 2 triệu</option>
                            <option value="2000000-3000000">2 triệu - 3 triệu</option>
                            <option value="3000000-4000000">3 triệu - 4 triệu</option>
                            <option value="4000000-5000000">4 triệu - 5 triệu</option>
                            <option value="5000000-999999999">Trên 5 triệu</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <h3>Kiểu dáng</h3>
                        <select name="loaisanpham" id="filter-type">
                            <option value="">Tất cả</option>
                            <option value="cơ">Đồng hồ cơ</option>
                            <option value="quartz">Đồng hồ quartz</option>
                            <option value="điện tử">Đồng hồ điện tử</option>
                            <option value="thông minh">Đồng hồ thông minh</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <h3>Giới tính</h3>
                        <select name="gioitinh" id="filter-category">
                            <option value="">Tất cả</option>
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <h3>Chất liệu dây</h3>
                        <select name="chatlieu" id="filter-strap">
                            <option value="">Tất cả</option>
                            <option value="kim loại">Dây kim loại</option>
                            <option value="da">Dây da</option>
                            <option value="silicone">Dây silicone</option>
                            <option value="vải và nato">Dây vải / Nato</option>
                            <option value="milamese">Dây Milanese</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="apply-filter-btn">Áp dụng bộ lọc</button>
            </form>
        </div>
    </section>

    <section style="height: auto;" class="best-sellers">
        <div class="container">
            <h2>ĐỒNG HỒ NAM BÁN CHẠY</h2>
            <div class="products-grid">
                <?php foreach ($productsNam as $product): ?>
                    <div class="product-item">
                        <a href="product-detail.php?product_id=<?= $product['id'] ?>" class="product-link">
                            <div class="product-image">
                                <img src="/WebDongHo/<?= htmlspecialchars($product['url']) ?>" alt="<?= htmlspecialchars($product['tenvatpham']) ?>" class="default-img">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($product['tenvatpham']) ?></h3>
                                <p class="product-desc"><?= mb_substr(htmlspecialchars($product['mota']), 0, 80) . (mb_strlen($product['mota']) > 80 ? '...' : '') ?></p>
                                <div class="product-price"><?= number_format($product['giatien'], 0, ',', '.') ?> ₫</div>
                                <button class="add-to-cart">Xem chi tiết</button>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 450px;" class="pagination">
                <?php for ($i = 1; $i <= $totalPagesNam; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    
    <section style="height: auto;" class="women-watches">
        <div class="container">
            <h2>ĐỒNG HỒ NỮ BÁN CHẠY</h2>
            <div class="products-grid">
                <?php foreach ($productsNu as $product): ?>
                    <div class="product-item">
                        <a href="product-detail.php?product_id=<?= $product['id'] ?>" class="product-link">
                            <div class="product-image">
                                <img src="/WebDongHo/<?= htmlspecialchars($product['url']) ?>" alt="<?= htmlspecialchars($product['tenvatpham']) ?>" class="default-img">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($product['tenvatpham']) ?></h3>
                                <p class="product-desc"><?= mb_substr(htmlspecialchars($product['mota']), 0, 80) . (mb_strlen($product['mota']) > 80 ? '...' : '') ?></p>
                                <div class="product-price"><?= number_format($product['giatien'], 0, ',', '.') ?> ₫</div>
                                <button class="add-to-cart">Xem chi tiết</button>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 450px;" class="pagination">
                <?php for ($i = 1; $i <= $totalPagesNu; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="services">
        <div class="container">
            <h2 class="section-title">CÁC DỊCH VỤ TẠI GROUP 3</h2>
            <div class="services-grid">
                <div class="service-item">
                    <img src="../frontend/assets/images/dv1.png" alt="Sửa chữa đồng hồ">
                    <h3>- Dịch vụ -<br>SỬA CHỮA ĐỒNG HỒ</h3>
                </div>
                <div class="service-item">
                    <img src="../frontend/assets/images/dv2.png" alt="In logo khắc laser">
                    <h3>- Dịch vụ -<br>IN LOGO KHẮC LASER</h3>
                </div>
                <div class="service-item">
                    <img src="../frontend/assets/images/dv3.png" alt="Khách hàng doanh nghiệp">
                    <h3>- Dịch vụ -<br>KHÁCH HÀNG DOANH NGHIỆP</h3>
                </div>
            </div>
        </div>
    </section>

    <section class="most-searched">
        <div class="container">
            <h2 class="section-title">ĐƯỢC TÌM KIẾM NHIỀU NHẤT</h2>
            <div class="search-items">
                <a href="#">Đồng hồ nam</a>
                <a href="#">Đồng hồ nữ</a>
                <a href="#">Đồng hồ cặp đôi</a>
                <a href="#">Đồng hồ Thụy Sỹ</a>
                <a href="#">Đồng hồ Casio</a>
                <a href="#">Đồng hồ Tissot</a>
                <a href="#">Đồng hồ Seiko</a>
                <a href="#">Đồng hồ Citizen</a>
            </div>
            <div class="search-banner">
                <img src="../frontend/assets/images/baner.png" alt="Search Banner">
            </div>
        </div>
    </section>

    <section class="reasons-to-buy">
        <div class="container">
            <h2 class="section-title">9 NỀN MUA ĐỒNG HỒ LÝ DO</h2>
            <div class="reasons-grid">
                <div class="reason-item">
                    <span class="reason-number">1</span>
                    <h3>100% CHÍNH HÃNG</h3>
                    <p>100% chính hãng (có giấy chứng nhận ủy quyền từ hãng), mua mới mỗi ngày. Không có bất kỳ rủi ro nào khi mua sắm, đến gặp 10 nếu phát hiện hàng giả.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">2</span>
                    <h3>SẢN PHẨM ĐƯỢC QC KỸ LƯỢNG</h3>
                    <p>Đồng hồ phải trải qua quy trình kiểm định chất lượng băng tay, nếu phát hiện lỗi (dù là nhỏ nhất) để trả về nhà sản xuất, 100% sản phẩm là hàng loại 1.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">3</span>
                    <h3>ĐỒNG HỒ ĐƯỢC BỌC NILON TRÁNH BỤI BẨN</h3>
                    <p>Sau khi QC hoàn tất, đồng hồ được bọc 1 lớp nilon mỏng để ngăn chặn bụi bẩn, trầy xước và mồ hôi xuên suốt quá trình trưng bày và tư vấn.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">4</span>
                    <h3>ĐỒNG HỒ CÓ ĐỘC ĐẮT TRONG HỢP XOAY CỘT</h3>
                    <p>Trực tiếp kiểm tra bằng ke, tất cả đồng hồ có độ độc cao, không lệch quá 10 giây/ngày, giúp đồng hồ hoạt động mượt mà, đảm bảo chất lượng tốt nhất.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">5</span>
                    <h3>MẪU MỚI CẬP NHẬT LIÊN TỤC, PHẦN PHỐI ĐỘC QUYỀN</h3>
                    <p>Hải Triều là nhà bán lẻ ủy quyền hàng đầu các thương hiệu cao cấp, nhập bổ sung mẫu mới liên tục để đáp ứng nhu cầu sở hữu nhanh chóng.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">6</span>
                    <h3>MỖI NHÂN VIÊN LÀ 1 CHUYÊN GIA VỀ ĐỒNG HỒ</h3>
                    <p>Giảm đốc các hãng Tissot, Doxa, DW, Rado, Seiko,... trực tiếp về Việt Nam đào tạo và cấp chứng nhận cho nhân viên Hải Triều, kiến thức chuyên sâu sản phẩm, kỹ năng tư vấn thông tin chính xác, tận tâm đến khách hàng.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">7</span>
                    <h3>TIÊU CHUẨN PHỤC VỤ 5C</h3>
                    <p>“Chừng – Cười – Chào – Chăm sóc – Cảm ơn” là tiêu chuẩn phục vụ 5C ngành thời trang cao cấp, giúp bạn có trải nghiệm mua sắm tuyệt vời tại Hải Triều.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">8</span>
                    <h3>KHÔNG GIAN MUA SẮM SANG TRỌNG</h3>
                    <p>Sản phẩm trưng bày thông minh, dễ chọn lựa. Hệ thống tủ, mút hướng, ánh sáng, cách bày trí đều đạt yêu cầu của các hãng để đảm bảo trải nghiệm mua sắm tốt nhất.</p>
                </div>
                <div class="reason-item">
                    <span class="reason-number">9</span>
                    <h3>BẢO HÀNH VƯỢT CHÍNH SÁCH</h3>
                    <p>Hết 1 năm bảo hành hãng, bạn được tặng thêm 3-4 năm bảo hành độc quyền tại Hải Triều, 1 đổi 1, miễn phí thay pin trọn đời, miễn phí vận chuyển toàn quốc, không thích – không sao – cứ trả lại vẫn được miễn phí vận chuyển.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Brand Logos -->
    <section class="brand-logos">
        <div class="container">
            <h2 class="section-title">THƯƠNG HIỆU NỔI BẬT</h2>
            <div class="logos-slider">
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/2508791179/3991453020.jpeg" alt="Seiko">
                </a>
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/1746122707/943233427.jpeg" alt="Casio">
                </a>
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/2232035981/2666808325.jpeg" alt="Daniel Wellington">
                </a>
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/3568445192/1438246614.jpeg" alt="Sokolov">
                </a>
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/1474473701/3036088384.jpeg" alt="Orient">
                </a>
                <a href="#" class="brand-logo">
                    <img src="https://ext.same-assets.com/303810766/1302936730.jpeg" alt="Citizen">
                </a>
            </div>
        </div>
    </section>

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

    <script src="assets/js/main.js"></script>
    <script>
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