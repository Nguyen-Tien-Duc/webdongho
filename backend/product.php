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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Default title and page setup
$title = "Tất cả sản phẩm";
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$productsPerPage = 8;
$offset = ($page - 1) * $productsPerPage;

// Check if we're displaying watches or accessories
$is_phukien = isset($_GET['phukien']) && $_GET['phukien'] === 'true';

// Initialize arrays for SQL parameters
$params = [];
$orderBy = " ORDER BY id DESC";
$limit = " LIMIT $offset, $productsPerPage";

// Handle watches query
if (!$is_phukien) {
    $sql_vatpham = "SELECT * FROM vatpham WHERE sll > 0";
    
    // Apply filters for watches
    if (isset($_GET['gioitinh']) && !empty($_GET['gioitinh'])) {
        $sql_vatpham .= " AND gioitinh = :gioitinh";
        $params[':gioitinh'] = $_GET['gioitinh'];
        $title = "Đồng hồ " . $_GET['gioitinh'];
    }
    
    if (isset($_GET['thuonghieu']) && !empty($_GET['thuonghieu'])) {
        $sql_vatpham .= " AND thuonghieu = :thuonghieu";
        $params[':thuonghieu'] = $_GET['thuonghieu'];
        $title = "Đồng hồ " . $_GET['thuonghieu'];
    }
    
    if (isset($_GET['loaisanpham']) && !empty($_GET['loaisanpham'])) {
        $sql_vatpham .= " AND loaisanpham = :loaisanpham";
        $params[':loaisanpham'] = $_GET['loaisanpham'];
        $title = "Đồng hồ " . $_GET['loaisanpham'];
    }
    
    if (isset($_GET['chatlieu']) && !empty($_GET['chatlieu'])) {
        $sql_vatpham .= " AND chatlieu = :chatlieu";
        $params[':chatlieu'] = $_GET['chatlieu'];
        $title .= " dây " . $_GET['chatlieu'];
    }
    
    if (isset($_GET['price_min']) && is_numeric($_GET['price_min'])) {
        $sql_vatpham .= " AND giatien >= :price_min";
        $params[':price_min'] = $_GET['price_min'];
    }
    
    if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
        $sql_vatpham .= " AND giatien <= :price_max";
        $params[':price_max'] = $_GET['price_max'];
    }
    
    // Count total watches matching criteria (for pagination)
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*)", $sql_vatpham);
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    
    // Get watches for current page
    $sql_vatpham .= $orderBy . $limit;
    $stmt = $pdo->prepare($sql_vatpham);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Handle accessories query
    $sql_phukien = "SELECT * FROM phukien WHERE sll > 0";
    $title = "Phụ kiện đồng hồ";
    
    if (isset($_GET['loaiphukien']) && !empty($_GET['loaiphukien'])) {
        $sql_phukien .= " AND loaiphukien = :loaiphukien";
        $params[':loaiphukien'] = $_GET['loaiphukien'];
        $title = "Phụ kiện: " . $_GET['loaiphukien'];
    }
    
    if (isset($_GET['price_min']) && is_numeric($_GET['price_min'])) {
        $sql_phukien .= " AND giatien >= :price_min";
        $params[':price_min'] = $_GET['price_min'];
    }
    
    if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
        $sql_phukien .= " AND giatien <= :price_max";
        $params[':price_max'] = $_GET['price_max'];
    }
    
    // Count total accessories matching criteria (for pagination)
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*)", $sql_phukien);
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    
    // Get accessories for current page
    $sql_phukien .= $orderBy . $limit;
    $stmt = $pdo->prepare($sql_phukien);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user information
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Calculate total pages for pagination
$totalPages = ceil($total_products / $productsPerPage);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Shop Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        /* Cập nhật cho phần container trong product.php */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* Hiển thị 4 sản phẩm trên 1 hàng */
            gap: 30px; /* Khoảng cách giữa các sản phẩm */
            margin-top: 20px; /* Khoảng cách phía trên */
        }

        .product-item {
            background-color: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .product-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Đảm bảo hình ảnh không bị cắt */
        }

        .product-info {
            padding: 15px;
            text-align: center;
        }

        .product-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            height: 45px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Hiển thị tối đa 2 dòng */
            -webkit-box-orient: vertical;
        }

        .product-desc {
            font-size: 14px;
            color: #777;

            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Hiển thị tối đa 2 dòng */
            -webkit-box-orient: vertical;
        }

        .product-price {
            font-size: 16px;
            font-weight: bold;
            color: #9d2a28;

        }

        .add-to-cart {
            display: inline-block;
            padding: 8px 15px;
            background-color: #9d2a28;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }
        /* Căn giữa phần thông báo */
        .no-products {
            text-align: center; /* Căn giữa nội dung */
            margin-top: 50px; /* Tạo khoảng cách phía trên */
        }

        .no-products p {
            font-size: 18px;
            color: #555;
            margin-bottom: 20px;
        }

        .no-products .action-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #9d2a28;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
        }

        .no-products .action-button:hover {
            background-color:rgb(213, 25, 22);
        }

        .section-title {
            margin-top: 20px;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Chiều cao tối thiểu bằng chiều cao màn hình */
            margin: 0;
        }

        .container {
            flex: 1; /* Đẩy footer xuống dưới */
        }

        .footer {
            background-color: #9d1f25;
            color: #fff;
            padding: 20px 0;
            text-align: center;
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
                    <a href="contact.php">LIÊN HỆ</a>
                </li>               
            </ul>
        </div>
    </nav>

    <div style="width: 1200px" class="container">
        <h2 class="section-title"><?= htmlspecialchars($title) ?></h2>
        
        <?php if (count($products) > 0): ?>
            <div style="height: auto" class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-item">
                        <?php if (!$is_phukien): // For watches ?>
                            <a href="product-detail.php?product_id=<?= $product['id'] ?>" class="product-link">
                                <div class="product-image">
                                    <img src="/WebDongHo/<?= htmlspecialchars($product['url']) ?>" alt="<?= htmlspecialchars($product['tenvatpham']) ?>" class="default-img">
                                </div>
                                <div class="product-info">
                                    <h3 style="font-weight: bold;" class="product-name"><?= htmlspecialchars($product['tenvatpham']) ?></h3>
                                    <p class="product-desc"><?= mb_substr(htmlspecialchars($product['mota']), 0, 80) . (mb_strlen($product['mota']) > 80 ? '...' : '') ?></p>
                                    <div class="product-price"><?= number_format($product['giatien'], 0, ',', '.') ?> ₫</div>
                                    <button class="add-to-cart">Xem chi tiết</button>
                                </div>
                            </a>
                        <?php else: // For accessories ?>
                            <a href="accessory-detail.php?accessory_id=<?= $product['id'] ?>" class="product-link">
                                <div class="product-image">
                                    <img src="../uploads/<?= htmlspecialchars($product['url']) ?>" alt="<?= htmlspecialchars($product['ten']) ?>" class="default-img">
                                </div>
                                <div class="product-info">
                                    <h3 style="font-weight: bold;" class="product-name"><?= htmlspecialchars($product['ten']) ?></h3>
                                    <p class="product-desc"><?= mb_substr(htmlspecialchars($product['mota']), 0, 80) . (mb_strlen($product['mota']) > 80 ? '...' : '') ?></p>
                                    <div class="product-price"><?= number_format($product['giatien'], 0, ',', '.') ?> ₫</div>
                                    <button class="add-to-cart">Xem chi tiết</button>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <div style="height: 65px;" class="pagination">
                <?php
                // Generate query string for pagination links
                $queryParams = $_GET;
                unset($queryParams['page']); // Remove page parameter
                $queryString = http_build_query($queryParams);
                $queryString = $queryString ? "?$queryString&" : "?";
                
                // Previous page link
                if ($page > 1):
                ?>
                    <a href="<?= $queryString ?>page=<?= $page - 1 ?>">&laquo;</a>
                <?php endif; ?>
                
                <?php 
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="<?= $queryString ?>page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <!-- Next page link -->
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $queryString ?>page=<?= $page + 1 ?>">&raquo;</a>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="no-products">
                <p>Không tìm thấy sản phẩm phù hợp với điều kiện tìm kiếm.</p>
                <?php if ($is_phukien): ?>
                    <a href="product.php?phukien=true" class="action-button">Xem tất cả phụ kiện</a>
                <?php else: ?>
                    <a href="product.php" class="action-button">Xem tất cả sản phẩm</a>
                <?php endif; ?>
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
        // Add this script to the bottom of product.php, right before the closing </body> tag

document.addEventListener('DOMContentLoaded', function() {
    // Mobile navigation toggle
    const navToggle = document.getElementById('nav-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
        });
    }
    
    // Format price function for search suggestions
    function formatPrice(price) {
        return new Intl.NumberFormat('vi-VN', { 
            style: 'currency', 
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(price);
    }
    
    // Search suggestions continuation
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
                        
                        // Determine if this is a watch or accessory
                        const itemName = item.tenvatpham || item.ten;
                        const detailUrl = item.tenvatpham ? 
                            `product-detail.php?product_id=${item.id}` : 
                            `accessory-detail.php?accessory_id=${item.id}`;
                        
                        div.innerHTML = `
                            <div class="suggestion-content">
                                <img src="/WebDongHo/${item.url || 'frontend/assets/images/no-image.png'}" alt="${itemName}">
                                <div class="suggestion-info">
                                    <div class="suggestion-name">${itemName}</div>
                                    <div class="suggestion-price">${formatPrice(item.giatien)}</div>
                                </div>
                            </div>
                        `;
                        
                        div.addEventListener('click', function() {
                            window.location.href = detailUrl;
                        });
                        
                        searchSuggestions.appendChild(div);
                    });
                    
                    searchSuggestions.style.display = 'block';
                } else {
                    searchSuggestions.innerHTML = '<div class="no-suggestions">Không tìm thấy sản phẩm</div>';
                    searchSuggestions.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error fetching search suggestions:', error);
            });
    }

    // Handle search form submission
    const searchForm = document.querySelector('.search-box');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (query.length > 0) {
                window.location.href = `search_results.php?q=${encodeURIComponent(query)}`;
            }
        });
    }
    
    // Add search button event listener
    const searchButton = document.querySelector('.search-button');
    if (searchButton && searchInput) {
        searchButton.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query.length > 0) {
                window.location.href = `search_results.php?q=${encodeURIComponent(query)}`;
            }
        });
    }
    
    // Dropdown menu functionality for mobile
    const dropdownMenus = document.querySelectorAll('.has-megamenu');
    if (window.innerWidth <= 768) {
        dropdownMenus.forEach(menu => {
            const link = menu.querySelector('a');
            const megaMenu = menu.querySelector('.mega-menu');
            
            link.addEventListener('click', function(e) {
                e.preventDefault();
                dropdownMenus.forEach(otherMenu => {
                    if (otherMenu !== menu) {
                        otherMenu.querySelector('.mega-menu').classList.remove('active');
                    }
                });
                megaMenu.classList.toggle('active');
            });
        });
    }
});
</script>
</body>
</html>