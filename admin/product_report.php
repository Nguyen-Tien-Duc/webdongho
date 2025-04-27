<?php
// product_report.php
session_start();
require_once "../database/db.php";

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}

// Thống kê tổng sản phẩm theo loại
$stmt = $conn->query("
    SELECT 
        loaisanpham, 
        COUNT(*) as total_count,
        SUM(sll) as total_stock,
        AVG(giatien) as avg_price,
        MIN(giatien) as min_price,
        MAX(giatien) as max_price
    FROM vatpham
    GROUP BY loaisanpham
");
$productStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Phân tích giới tính
$stmt = $conn->query("
    SELECT 
        gioitinh, 
        COUNT(*) as count,
        AVG(giatien) as avg_price
    FROM vatpham
    GROUP BY gioitinh
");
$genderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Phân tích chất liệu
$stmt = $conn->query("
    SELECT 
        chatlieu, 
        COUNT(*) as count,
        AVG(giatien) as avg_price
    FROM vatpham
    GROUP BY chatlieu
    ORDER BY count DESC
");
$materialStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê bán hàng theo loại sản phẩm
$stmt = $conn->query("
    SELECT 
        v.loaisanpham,
        COUNT(l.id) as total_orders,
        SUM(l.sll) as total_sold,
        SUM(v.giatien * l.sll) as total_revenue
    FROM lichsuthanhtoan l
    JOIN vatpham v ON l.vatpham_id = v.id
    WHERE l.trangthai = 'đã thanh toán'
    GROUP BY v.loaisanpham
");
$salesByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Biểu đồ doanh thu theo loại sản phẩm cho Chart.js
$productTypes = [];
$salesData = [];
foreach ($salesByType as $data) {
    $productTypes[] = $data['loaisanpham'];
    $salesData[] = $data['total_revenue'] / 1000000; // Đơn vị triệu đồng
}

// Sản phẩm bán chạy nhất
$stmt = $conn->query("
    SELECT 
        v.id,
        v.tenvatpham,
        v.loaisanpham,
        v.giatien,
        v.gioitinh,
        v.chatlieu,
        v.sll as stock_left,
        SUM(l.sll) as total_sold,
        SUM(v.giatien * l.sll) as total_revenue
    FROM lichsuthanhtoan l
    JOIN vatpham v ON l.vatpham_id = v.id
    WHERE l.trangthai = 'đã thanh toán'
    GROUP BY v.id, v.tenvatpham, v.loaisanpham, v.giatien, v.gioitinh, v.chatlieu, v.sll
    ORDER BY total_sold DESC
    LIMIT 10
");
$bestSellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sản phẩm sắp hết hàng
$stmt = $conn->query("
    SELECT 
        id,
        tenvatpham,
        loaisanpham,
        giatien,
        sll,
        gioitinh,
        chatlieu
    FROM 
        vatpham
    WHERE 
        sll < 10
    ORDER BY 
        sll ASC
    LIMIT 
        10
");
$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sản phẩm tồn kho lâu (không có trong lịch sử thanh toán hoặc bán chậm)
$stmt = $conn->query("
    SELECT 
        v.id,
        v.tenvatpham,
        v.loaisanpham,
        v.giatien,
        v.sll,
        v.gioitinh,
        v.chatlieu,
        IFNULL(SUM(l.sll), 0) as total_sold
    FROM 
        vatpham v
    LEFT JOIN 
        lichsuthanhtoan l ON v.id = l.vatpham_id AND l.trangthai = 'đã thanh toán'
    GROUP BY 
        v.id, v.tenvatpham, v.loaisanpham, v.giatien, v.sll, v.gioitinh, v.chatlieu
    HAVING 
        total_sold = 0 OR total_sold < 5
    ORDER BY 
        total_sold ASC, v.sll DESC
    LIMIT 
        10
");
$slowMovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê đánh giá sản phẩm
$stmt = $conn->query("
    SELECT 
        v.loaisanpham,
        AVG(d.sosao) as avg_rating,
        COUNT(d.id) as total_reviews
    FROM 
        danhgia d
    JOIN 
        vatpham v ON d.vatpham_id = v.id
    GROUP BY 
        v.loaisanpham
");
$ratingsByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Biểu đồ đánh giá sản phẩm theo loại
$ratingTypes = [];
$ratingsData = [];
foreach ($ratingsByType as $data) {
    $ratingTypes[] = $data['loaisanpham'];
    $ratingsData[] = round($data['avg_rating'], 1);
}

// Phân tích theo khoảng giá
$stmt = $conn->query("
    SELECT 
        CASE
            WHEN giatien < 1000000 THEN 'Dưới 1 triệu'
            WHEN giatien BETWEEN 1000000 AND 3000000 THEN '1-3 triệu'
            WHEN giatien BETWEEN 3000001 AND 5000000 THEN '3-5 triệu'
            WHEN giatien BETWEEN 5000001 AND 10000000 THEN '5-10 triệu'
            WHEN giatien BETWEEN 10000001 AND 20000000 THEN '10-20 triệu'
            ELSE 'Trên 20 triệu'
        END as price_range,
        COUNT(*) as product_count,
        SUM(sll) as total_stock
    FROM 
        vatpham
    GROUP BY 
        price_range
    ORDER BY 
        MIN(giatien)
");
$priceRanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy dữ liệu biểu đồ phân phối giá
$priceRangeLabels = [];
$priceRangeCounts = [];
foreach ($priceRanges as $range) {
    $priceRangeLabels[] = $range['price_range'];
    $priceRangeCounts[] = $range['product_count'];
}

// Top sản phẩm có đánh giá cao nhất
$stmt = $conn->query("
    SELECT 
        v.id,
        v.tenvatpham,
        v.loaisanpham,
        v.giatien,
        AVG(d.sosao) as avg_rating,
        COUNT(d.id) as review_count
    FROM 
        vatpham v
    JOIN 
        danhgia d ON v.id = d.vatpham_id
    GROUP BY 
        v.id, v.tenvatpham, v.loaisanpham, v.giatien
    HAVING 
        review_count >= 3
    ORDER BY 
        avg_rating DESC, review_count DESC
    LIMIT 
        10
");
$topRated = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Phân tích ROI (Return on Investment) cho từng loại sản phẩm
// Giả định giá vốn là 70% giá bán cho mục đích demo
$stmt = $conn->query("
    SELECT 
        v.loaisanpham,
        SUM(v.giatien * l.sll) as total_revenue,
        SUM(v.giatien * l.sll * 0.7) as estimated_cost,
        SUM(v.giatien * l.sll) - SUM(v.giatien * l.sll * 0.7) as estimated_profit,
        (SUM(v.giatien * l.sll) - SUM(v.giatien * l.sll * 0.7)) / SUM(v.giatien * l.sll * 0.7) * 100 as roi_percent
    FROM 
        lichsuthanhtoan l
    JOIN 
        vatpham v ON l.vatpham_id = v.id
    WHERE 
        l.trangthai = 'đã thanh toán'
    GROUP BY 
        v.loaisanpham
");
$roiAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Sản Phẩm - Web Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <style>
                 :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --text-primary: #5a5c69;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', 'Segoe UI', Roboto, Arial, sans-serif;
            overflow-x: hidden;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            color: white;
            transition: all 0.3s;
            width: 225px;
            z-index: 999;
            position: fixed;
        }
        
        .sidebar-brand {
            height: 4.375rem;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 800;
            padding: 1.5rem 1rem;
            text-align: center;
            letter-spacing: 0.05rem;
            color: white;
        }
        
        .sidebar hr {
            margin: 0 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .sidebar .nav-item {
            position: relative;
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1.25rem;
            font-size: 0.85rem;
        }
        
        .sidebar .nav-link span {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            font-weight: 700;
        }
        
        .content {
            flex: 1;
            margin-left: 225px;
            transition: all 0.3s;
        }
        
        .content {
            flex: 1;
            margin-left: 225px;
            transition: all 0.3s;
        }
        
        .navbar {
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .topbar-divider {
            width: 0;
            border-right: 1px solid #e3e6f0;
            height: calc(4.375rem - 2rem);
            margin: auto 1rem;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h6 {
            margin-bottom: 0;
            font-weight: 700;
        }
        
        .chart-area, .chart-pie, .chart-bar {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        .progress-bar-container {
            height: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .progress {
            height: 1rem;
            background-color: #eaecf4;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .table-badge {
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 0.35rem;
            padding: 0.25rem 0.5rem;
        }
        
        .price-range-chart {
            height: 15rem;
        }
        
        .rating-star {
            color: var(--warning-color);
        }
        
        .low-stock-badge {
            background-color: var(--danger-color);
            color: white;
        }
        
        .premium-badge {
            background-color: var(--success-color);
            color: white;
        }
        
        .value-badge {
            background-color: var(--primary-color);
            color: white;
        }
        
        .budget-badge {
            background-color: var(--info-color);
            color: white;
        }
        
        .inventory-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .critical {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .good {
            background-color: #d4edda;
            color: #155724;
        }
        
        .excess {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .stat-card {
            padding: 1.25rem;
            color: white;
            margin-bottom: 1.5rem;
            border-radius: 0.35rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-primary {
            background: linear-gradient(45deg, #4e73df, #3a5ecf);
        }
        
        .stat-card-success {
            background: linear-gradient(45deg, #1cc88a, #13a06c);
        }
        
        .stat-card-info {
            background: linear-gradient(45deg, #36b9cc, #2aa1b3);
        }
        
        .stat-card-warning {
            background: linear-gradient(45deg, #f6c23e, #e3a818);
        }
        
        .stat-card-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0.3;
            font-size: 2.5rem;
        }
        
        .stat-card-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-card-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .stat-card-trend {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .trend-up {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .trend-down {
            color: rgba(255, 255, 255, 0.9);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -225px;
            }
            
            .content {
                margin-left: 0;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .content.active {
                margin-left: 225px;
            }
        }
        
        .table thead th {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
        }
        
        .search-form {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f2f4f8;
            border-radius: 2rem;
            padding: 0.375rem 0.75rem;
        }
        
        .search-form input {
            border: 0;
            background-color: transparent;
            padding: 0.25rem 0.5rem;
        }
        
        .search-form input:focus {
            outline: none;
        }
        
        .search-form button {
            border: 0;
            background-color: transparent;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="admin_dashboard.php" class="sidebar-brand d-flex align-items-center justify-content-center">
            <div class="sidebar-brand-icon">
                <i class="fas fa-watch"></i>
            </div>
            <div class="sidebar-brand-text mx-3">ADMIN</div>
        </a>
        
        <hr class="sidebar-divider">
        
        <div class="nav-item">
            <a class="nav-link" href="admin_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Bảng điều khiển</span>
            </a>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">
            Quản lý
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="manage_products.php">
                <i class="fas fa-fw fa-watch"></i>
                <span>Đồng hồ</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="manage_accessories.php">
                <i class="fas fa-fw fa-link"></i>
                <span>Phụ kiện</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="manage_users.php">
                <i class="fas fa-fw fa-users"></i>
                <span>Tài khoản</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="manage_orders.php">
                <i class="fas fa-fw fa-shopping-cart"></i>
                <span>Đơn hàng</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="manage_payments.php">
                <i class="fas fa-fw fa-money-bill"></i>
                <span>Thanh toán</span>
            </a>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">
            Báo cáo
        </div>
        
        <div class="nav-item">
            <a class="nav-link" href="sales_report.php">
                <i class="fas fa-fw fa-chart-line"></i>
                <span>Báo cáo doanh thu</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a class="nav-link active" href="product_report.php">
                <i class="fas fa-fw fa-chart-bar"></i>
                <span>Báo cáo sản phẩm</span>
            </a>
        </div>
        
        <hr class="sidebar-divider d-none d-md-block">
        
        <div class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle">
                <i class="fas fa-angle-left"></i>
            </button>
        </div>
    </div>
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Search Form -->
            <form class="d-none d-sm-inline-block me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search search-form">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0" placeholder="Tìm kiếm..." aria-label="Search">
                    <button class="btn" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- Topbar Navbar -->
            <ul class="navbar-nav ms-auto">
                <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                <li class="nav-item dropdown no-arrow d-sm-none">
                    <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-search fa-fw"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                        <form class="form-inline me-auto w-100 navbar-search">
                            <div class="input-group">
                                <input type="text" class="form-control bg-light border-0 small" placeholder="Tìm kiếm..." aria-label="Search">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button">
                                        <i class="fas fa-search fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </li>
                
                <!-- Nav Item - Notifications -->
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw"></i>
                        <!-- Counter - Alerts -->
                        <span class="badge bg-danger badge-counter">3+</span>
                    </a>
                    <!-- Dropdown - Alerts -->
                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header bg-primary">
                            Thông báo
                        </h6>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-danger">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">14/04/2025</div>
                                <span>5 sản phẩm sắp hết hàng!</span>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-success">
                                    <i class="fas fa-trophy text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">13/04/2025</div>
                                Mục tiêu doanh số tháng đã đạt!
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-chart-line text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">12/04/2025</div>
                                Đồng hồ thông minh tăng trưởng 20% trong tháng!
                            </div>
                        </a>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem tất cả thông báo</a>
                    </div>
                </li>
                
                <div class="topbar-divider d-none d-sm-block"></div>
                
                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small">Admin</span>
                        <img class="img-profile rounded-circle" src="https://source.unsplash.com/QAB-WJcbgJk/60x60">
                    </a>
                    <!-- Dropdown - User Information -->
                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                            Hồ sơ
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                            Cài đặt
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                            Đăng xuất
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- End of Topbar -->
        
        <!-- Begin Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Báo Cáo Sản Phẩm</h1>
                <div>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm me-2">
                        <i class="fas fa-file-excel fa-sm text-white-50"></i> Xuất Excel
                    </a>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-download fa-sm text-white-50"></i> Tạo báo cáo PDF
                    </a>
                </div>
            </div>
            
            <!-- Content Row - Stats Cards -->
            <div class="row">
                <!-- Thống kê sản phẩm theo loại -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-primary">
                        <div class="stat-card-icon">
                            <i class="fas fa-list-ul"></i>
                        </div>
                        <div class="stat-card-value">
                            <?php echo count($productStats); ?> loại
                        </div>
                        <div class="stat-card-label">
                            Tổng số loại sản phẩm
                        </div>
                        <div class="stat-card-trend trend-up">
                            <i class="fas fa-arrow-up me-1"></i> Đa dạng
                        </div>
                    </div>
                </div>
                
                <!-- Tổng số sản phẩm -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-success">
                        <div class="stat-card-icon">
                            <i class="fas fa-watch"></i>
                        </div>
                        <div class="stat-card-value">
                            <?php 
                                $totalProducts = 0;
                                foreach ($productStats as $stat) {
                                    $totalProducts += $stat['total_count'];
                                }
                                echo $totalProducts;
                            ?>
                        </div>
                        <div class="stat-card-label">
                            Tổng số sản phẩm
                        </div>
                        <div class="stat-card-trend trend-up">
                            <i class="fas fa-arrow-up me-1"></i> Đủ mẫu mã
                        </div>
                    </div>
                </div>
                
                <!-- Tổng tồn kho -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-info">
                        <div class="stat-card-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-card-value">
                            <?php 
                                $totalStock = 0;
                                foreach ($productStats as $stat) {
                                    $totalStock += $stat['total_stock'];
                                }
                                echo number_format($totalStock);
                            ?>
                        </div>
                        <div class="stat-card-label">
                            Tổng sản phẩm tồn kho
                        </div>
                        <div class="stat-card-trend">
                            <i class="fas fa-database me-1"></i> Cần quản lý
                        </div>
                    </div>
                </div>
                
                <!-- Giá trung bình -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-warning">
                        <div class="stat-card-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-card-value">
                            <?php 
                                $totalPrice = 0;
                                $totalCount = 0;
                                foreach ($productStats as $stat) {
                                    $totalPrice += $stat['avg_price'] * $stat['total_count'];
                                    $totalCount += $stat['total_count'];
                                }
                                $avgPrice = $totalCount > 0 ? $totalPrice / $totalCount : 0;
                                echo number_format($avgPrice) . 'đ';
                            ?>
                        </div>
                        <div class="stat-card-label">
                            Giá trung bình sản phẩm
                        </div>
                        <div class="stat-card-trend">
                            <i class="fas fa-balance-scale me-1"></i> Phân khúc giá
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - Charts -->
            <div class="row">
                <!-- Biểu đồ doanh thu theo loại sản phẩm -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Doanh thu theo loại sản phẩm</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">PDF</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">Chi tiết</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Biểu đồ đánh giá sản phẩm theo loại -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Đánh giá trung bình theo loại</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">PDF</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#">Chi tiết</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie">
                                <canvas id="ratingChart"></canvas>
                            </div>
                            <div class="mt-4 text-center small">
                                <?php foreach ($ratingsByType as $index => $data): ?>
                                <span class="me-2">
                                    <i class="fas fa-circle" style="color: <?php echo sprintf('hsl(%d, 70%%, 60%%)', $index * 50); ?>"></i> <?php echo $data['loaisanpham']; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - Tables -->
            <div class="row">
                <!-- Top sản phẩm bán chạy -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Top sản phẩm bán chạy</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">Xem tất cả</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="bestSellersTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Loại</th>
                                            <th>Đã bán</th>
                                            <th>Doanh thu</th>
                                            <th>Còn lại</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bestSellers as $product): ?>
                                        <tr>
                                            <td><?php echo $product['tenvatpham']; ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($product['loaisanpham']) {
                                                    case 'cơ': $badgeClass = 'bg-primary'; break;
                                                    case 'quartz': $badgeClass = 'bg-success'; break;
                                                    case 'điện tử': $badgeClass = 'bg-info'; break;
                                                    case 'thông minh': $badgeClass = 'bg-warning'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $product['loaisanpham']; ?></span>
                                            </td>
                                            <td><strong><?php echo $product['total_sold']; ?></strong></td>
                                            <td><?php echo number_format($product['total_revenue']); ?>đ</td>
                                            <td>
                                                <?php
                                                $stockClass = '';
                                                if ($product['stock_left'] <= 5) {
                                                    $stockClass = 'critical';
                                                } elseif ($product['stock_left'] <= 10) {
                                                    $stockClass = 'warning';
                                                } elseif ($product['stock_left'] <= 20) {
                                                    $stockClass = 'good';
                                                } else {
                                                    $stockClass = 'excess';
                                                }
                                                ?>
                                                <span class="inventory-status <?php echo $stockClass; ?>"><?php echo $product['stock_left']; ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sản phẩm sắp hết hàng -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Sản phẩm sắp hết hàng</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Thao tác:</div>
                                    <a class="dropdown-item" href="#">Đặt hàng thêm</a>
                                    <a class="dropdown-item" href="#">Xuất báo cáo</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="lowStockTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Loại</th>
                                            <th>Giá</th>
                                            <th>Còn lại</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lowStock as $product): ?>
                                        <tr>
                                            <td><?php echo $product['tenvatpham']; ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($product['loaisanpham']) {
                                                    case 'cơ': $badgeClass = 'bg-primary'; break;
                                                    case 'quartz': $badgeClass = 'bg-success'; break;
                                                    case 'điện tử': $badgeClass = 'bg-info'; break;
                                                    case 'thông minh': $badgeClass = 'bg-warning'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $product['loaisanpham']; ?></span>
                                            </td>
                                            <td><?php echo number_format($product['giatien']); ?>đ</td>
                                            <td><strong class="text-danger"><?php echo $product['sll']; ?></strong></td>
                                            <td>
                                                <?php if ($product['sll'] <= 3): ?>
                                                    <span class="badge bg-danger">Khẩn cấp</span>
                                                <?php elseif ($product['sll'] <= 5): ?>
                                                    <span class="badge bg-warning text-dark">Cần đặt hàng</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Sắp hết</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - Material Analysis -->
            <div class="row">
                <!-- Phân tích chất liệu -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Phân tích theo chất liệu</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">PDF</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-bar">
                                <canvas id="materialChart"></canvas>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <h5 class="small font-weight-bold">Phân bố chất liệu</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Chất liệu</th>
                                                <th>Số lượng</th>
                                                <th>Giá TB</th>
                                                <th>Tỷ lệ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalMaterialCount = 0;
                                            foreach ($materialStats as $material) {
                                                $totalMaterialCount += $material['count'];
                                            }
                                            
                                            foreach ($materialStats as $material): 
                                                $percentage = ($material['count'] / $totalMaterialCount) * 100;
                                            ?>
                                            <tr>
                                                <td><?php echo $material['chatlieu']; ?></td>
                                                <td><?php echo $material['count']; ?></td>
                                                <td><?php echo number_format($material['avg_price']); ?>đ</td>
                                                <td>
                                                    <div class="progress progress-bar-container">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Phân tích theo khoảng giá -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Phân tích theo khoảng giá</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">PDF</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="price-range-chart">
                                <canvas id="priceRangeChart"></canvas>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <h5 class="small font-weight-bold">Phân bố khoảng giá</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Khoảng giá</th>
                                                <th>Số lượng</th>
                                                <th>Tồn kho</th>
                                                <th>Phân loại</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($priceRanges as $range): ?>
                                            <tr>
                                                <td><?php echo $range['price_range']; ?></td>
                                                <td><?php echo $range['product_count']; ?></td>
                                                <td><?php echo $range['total_stock']; ?></td>
                                                <td>
                                                    <?php
                                                    $badgeClass = '';
                                                    switch ($range['price_range']) {
                                                        case 'Dưới 1 triệu': 
                                                            $badgeClass = 'budget-badge'; 
                                                            $label = 'Phổ thông';
                                                            break;
                                                        case '1-3 triệu': 
                                                            $badgeClass = 'value-badge'; 
                                                            $label = 'Tầm trung';
                                                            break;
                                                        case '3-5 triệu': 
                                                            $badgeClass = 'value-badge'; 
                                                            $label = 'Tầm trung+';
                                                            break;
                                                        case '5-10 triệu': 
                                                            $badgeClass = 'premium-badge'; 
                                                            $label = 'Cao cấp';
                                                            break;
                                                        case '10-20 triệu': 
                                                            $badgeClass = 'premium-badge'; 
                                                            $label = 'Luxury';
                                                            break;
                                                        default: 
                                                            $badgeClass = 'premium-badge'; 
                                                            $label = 'Ultra Luxury';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="table-badge <?php echo $badgeClass; ?>"><?php echo $label; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - ROI Analysis -->
            <div class="row">
                <!-- Phân tích ROI -->
                <div class="col-xl-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Phân tích ROI theo loại sản phẩm</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Xuất báo cáo:</div>
                                    <a class="dropdown-item" href="#">PDF</a>
                                    <a class="dropdown-item" href="#">Excel</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="roiTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Loại sản phẩm</th>
                                            <th>Tổng doanh thu</th>
                                            <th>Ước tính chi phí</th>
                                            <th>Lợi nhuận</th>
                                            <th>ROI (%)</th>
                                            <th>Đánh giá</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roiAnalysis as $roi): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($roi['loaisanpham']) {
                                                    case 'cơ': $badgeClass = 'bg-primary'; break;
                                                    case 'quartz': $badgeClass = 'bg-success'; break;
                                                    case 'điện tử': $badgeClass = 'bg-info'; break;
                                                    case 'thông minh': $badgeClass = 'bg-warning'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $roi['loaisanpham']; ?></span>
                                            </td>
                                            <td><?php echo number_format($roi['total_revenue']); ?>đ</td>
                                            <td><?php echo number_format($roi['estimated_cost']); ?>đ</td>
                                            <td><?php echo number_format($roi['estimated_profit']); ?>đ</td>
                                            <td><strong><?php echo number_format($roi['roi_percent'], 2); ?>%</strong></td>
                                            <td>
                                                <?php
                                                if ($roi['roi_percent'] >= 40) {
                                                    echo '<span class="badge bg-success">Rất tốt</span>';
                                                } elseif ($roi['roi_percent'] >= 30) {
                                                    echo '<span class="badge bg-info">Tốt</span>';
                                                } elseif ($roi['roi_percent'] >= 20) {
                                                    echo '<span class="badge bg-primary">Khá</span>';
                                                } elseif ($roi['roi_percent'] >= 10) {
                                                    echo '<span class="badge bg-warning text-dark">Trung bình</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Cần cải thiện</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - Rating Analysis -->
            <div class="row">
                <!-- Top đánh giá cao -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Top sản phẩm đánh giá cao</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Thao tác:</div>
                                    <a class="dropdown-item" href="#">Xem đánh giá chi tiết</a>
                                    <a class="dropdown-item" href="#">Xuất báo cáo</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="topRatedTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Loại</th>
                                            <th>Giá</th>
                                            <th>Số sao</th>
                                            <th>Lượt đánh giá</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topRated as $product): ?>
                                        <tr>
                                            <td><?php echo $product['tenvatpham']; ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($product['loaisanpham']) {
                                                    case 'cơ': $badgeClass = 'bg-primary'; break;
                                                    case 'quartz': $badgeClass = 'bg-success'; break;
                                                    case 'điện tử': $badgeClass = 'bg-info'; break;
                                                    case 'thông minh': $badgeClass = 'bg-warning'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $product['loaisanpham']; ?></span>
                                            </td>
                                            <td><?php echo number_format($product['giatien']); ?>đ</td>
                                            <td>
                                                <?php
                                                $rating = $product['avg_rating'];
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<i class="fas fa-star rating-star"></i>';
                                                    } elseif ($i - 0.5 <= $rating) {
                                                        echo '<i class="fas fa-star-half-alt rating-star"></i>';
                                                    } else {
                                                        echo '<i class="far fa-star rating-star"></i>';
                                                    }
                                                }
                                                echo ' <strong>' . number_format($rating, 1) . '</strong>';
                                                ?>
                                            </td>
                                            <td><?php echo $product['review_count']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sản phẩm tồn kho lâu -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Sản phẩm tồn kho lâu</h6>
                            <div class="dropdown no-arrow">
                                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <div class="dropdown-header">Thao tác:</div>
                                    <a class="dropdown-item" href="#">Đề xuất khuyến mãi</a>
                                    <a class="dropdown-item" href="#">Xuất báo cáo</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="slowMoversTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Tên sản phẩm</th>
                                            <th>Loại</th>
                                            <th>Giá</th>
                                            <th>Tồn kho</th>
                                            <th>Đã bán</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($slowMovers as $product): ?>
                                        <tr>
                                            <td><?php echo $product['tenvatpham']; ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                switch ($product['loaisanpham']) {
                                                    case 'cơ': $badgeClass = 'bg-primary'; break;
                                                    case 'quartz': $badgeClass = 'bg-success'; break;
                                                    case 'điện tử': $badgeClass = 'bg-info'; break;
                                                    case 'thông minh': $badgeClass = 'bg-warning'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $product['loaisanpham']; ?></span>
                                            </td>
                                            <td><?php echo number_format($product['giatien']); ?>đ</td>
                                            <td><strong><?php echo $product['sll']; ?></strong></td>
                                            <td>
                                                <span class="text-danger"><?php echo $product['total_sold']; ?></span>
                                                <?php if ($product['total_sold'] == 0): ?>
                                                    <span class="badge bg-danger">Không bán được</span>
                                                <?php elseif ($product['total_sold'] < 3): ?>
                                                    <span class="badge bg-warning text-dark">Bán chậm</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Row - Recommendations -->
            <div class="row">
                <div class="col-xl-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Đề xuất cải thiện kho hàng</h6>
                        </div>
                        <div class="card-body">
                            <div class="recommendation-cards">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6 mb-4">
                                        <div class="recommendation-card">
                                            <div class="recommendation-icon">
                                                <i class="fas fa-cart-plus"></i>
                                            </div>
                                            <h4>Nhập thêm hàng</h4>
                                            <p>5 sản phẩm sắp hết hàng cần được đặt thêm ngay lập tức</p>
                                            <a href="#" class="recommendation-action">Đặt hàng ngay</a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-4">
                                        <div class="recommendation-card">
                                            <div class="recommendation-icon">
                                                <i class="fas fa-percent"></i>
                                            </div>
                                            <h4>Khuyến mãi</h4>
                                            <p>10 sản phẩm tồn kho lâu nên được áp dụng chương trình giảm giá</p>
                                            <a href="#" class="recommendation-action">Tạo khuyến mãi</a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-4">
                                        <div class="recommendation-card">
                                            <div class="recommendation-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <h4>Tập trung vào</h4>
                                            <p>Đồng hồ thông minh có ROI cao nhất, nên tăng cường quảng cáo</p>
                                            <a href="#" class="recommendation-action">Chiến dịch marketing</a>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-4">
                                        <div class="recommendation-card">
                                            <div class="recommendation-icon">
                                                <i class="fas fa-tags"></i>
                                            </div>
                                            <h4>Điều chỉnh giá</h4>
                                            <p>Một số sản phẩm có giá cao hơn thị trường cần điều chỉnh</p>
                                            <a href="#" class="recommendation-action">Xem đề xuất giá</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End of Page Content -->
        
        <!-- Footer -->
        <footer class="footer mt-auto py-3 bg-white">
            <div class="container">
                <div class="copyright text-center">
                    <span>&copy; Web Đồng Hồ Admin Panel 2025</span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->
    </div>
    <!-- End of Content Wrapper -->
    
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
    
    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Bạn muốn đăng xuất?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">Chọn "Đăng xuất" bên dưới nếu bạn đã sẵn sàng kết thúc phiên làm việc.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <a class="btn btn-primary" href="login.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap core JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.0.1/chart.umd.min.js"></script>
    
    <!-- Custom scripts -->
    <script>
    $(document).ready(function() {
        // DataTables Initialization
        $('#bestSellersTable').DataTable({
            pageLength: 5,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Tất cả"]],
            language: {
                search: "Tìm kiếm:",
                lengthMenu: "Hiển thị _MENU_ mục",
                info: "Hiển thị _START_ đến _END_ của _TOTAL_ mục",
                infoEmpty: "Hiển thị 0 đến 0 của 0 mục",
                infoFiltered: "(lọc từ _MAX_ mục)",
                paginate: {
                    first: "Đầu",
                    last: "Cuối",
                    next: "Sau",
                    previous: "Trước"
                }
            }
        });
        
        $('#lowStockTable').DataTable({
            pageLength: 5,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Tất cả"]],
            language: {
                search: "Tìm kiếm:",
                lengthMenu: "Hiển thị _MENU_ mục",
                info: "Hiển thị _START_ đến _END_ của _TOTAL_ mục",
                infoEmpty: "Hiển thị 0 đến 0 của 0 mục",
                infoFiltered: "(lọc từ _MAX_ mục)",
                paginate: {
                    first: "Đầu",
                    last: "Cuối",
                    next: "Sau",
                    previous: "Trước"
                }
            }
        });
        
        $('#topRatedTable').DataTable({
            pageLength: 5,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Tất cả"]],
            language: {
                search: "Tìm kiếm:",
                lengthMenu: "Hiển thị _MENU_ mục",
                info: "Hiển thị _START_ đến _END_ của _TOTAL_ mục",
                infoEmpty: "Hiển thị 0 đến 0 của 0 mục",
                infoFiltered: "(lọc từ _MAX_ mục)",
                paginate: {
                    first: "Đầu",
                    last: "Cuối",
                    next: "Sau",
                    previous: "Trước"
                }
            }
        });
        
        $('#slowMoversTable').DataTable({
            pageLength: 5,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Tất cả"]],
            language: {
                search: "Tìm kiếm:",
                lengthMenu: "Hiển thị _MENU_ mục",
                info: "Hiển thị _START_ đến _END_ của _TOTAL_ mục",
                infoEmpty: "Hiển thị 0 đến 0 của 0 mục",
                infoFiltered: "(lọc từ _MAX_ mục)",
                paginate: {
                    first: "Đầu",
                    last: "Cuối",
                    next: "Sau",
                    previous: "Trước"
                }
            }
        });
        
        $('#roiTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Tất cả"]],
            language: {
                search: "Tìm kiếm:",
                lengthMenu: "Hiển thị _MENU_ mục",
                info: "Hiển thị _START_ đến _END_ của _TOTAL_ mục",
                infoEmpty: "Hiển thị 0 đến 0 của 0 mục",
                infoFiltered: "(lọc từ _MAX_ mục)",
                paginate: {
                    first: "Đầu",
                    last: "Cuối",
                    next: "Sau",
                    previous: "Trước"
                }
            }
        });
        
        // Charts for Reports
        
        // Doanh thu theo loại sản phẩm Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($productTypes); ?>,
                datasets: [{
                    label: 'Doanh thu (triệu đồng)',
                    data: <?php echo json_encode($salesData); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Doanh thu (triệu đồng)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' triệu đồng';
                            }
                        }
                    }
                }
            }
        });
        
        // Đánh giá trung bình theo loại Chart
        const ratingCtx = document.getElementById('ratingChart').getContext('2d');
        const ratingChart = new Chart(ratingCtx, {
            type: 'radar',
            data: {
                labels: <?php echo json_encode($ratingTypes); ?>,
                datasets: [{
                    label: 'Đánh giá trung bình',
                    data: <?php echo json_encode($ratingsData); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.3)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.r + ' sao';
                            }
                        }
                    }
                }
            }
        });
        
        // Phân tích chất liệu Chart
        const materialLabels = [];
        const materialData = [];
        const materialColors = [];
        
        <?php foreach ($materialStats as $index => $material): ?>
        materialLabels.push('<?php echo $material['chatlieu']; ?>');
        materialData.push(<?php echo $material['count']; ?>);
        materialColors.push('hsla(<?php echo $index * 30; ?>, 70%, 60%, 0.7)');
        <?php endforeach; ?>
        
        const materialCtx = document.getElementById('materialChart').getContext('2d');
        const materialChart = new Chart(materialCtx, {
            type: 'bar',
            data: {
                labels: materialLabels,
                datasets: [{
                    label: 'Số lượng sản phẩm',
                    data: materialData,
                    backgroundColor: materialColors,
                    borderColor: materialColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Phân tích khoảng giá Chart
        const priceRangeCtx = document.getElementById('priceRangeChart').getContext('2d');
        const priceRangeChart = new Chart(priceRangeCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($priceRangeLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($priceRangeCounts); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 205, 86, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 205, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + value + ' sản phẩm (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Toggle sidebar
        $('#sidebarToggle, #sidebarToggleTop').on('click', function() {
            $('body').toggleClass('sidebar-toggled');
            $('.sidebar').toggleClass('toggled');
        });

        // Close sidebar when window is less than 768px
        $(window).resize(function() {
            if ($(window).width() < 768) {
                $('.sidebar').addClass('toggled');
            }
        });
        
        // Scroll to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 100) {
                $('.scroll-to-top').fadeIn();
            } else {
                $('.scroll-to-top').fadeOut();
            }
        });
        
        $('.scroll-to-top').click(function() {
            $('html, body').animate({ scrollTop: 0 }, 800);
            return false;
        });

        // Add custom styling to tables
        $('.table').addClass('table-striped');
    });
    </script>
    </body>
    </html>