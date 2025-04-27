<?php
session_start();
require_once "../database/db.php";
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}
$stmt = $conn->query("SELECT COUNT(*) as total FROM taikhoan");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM vatpham");
$totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM phukien");
$totalAccessories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM lichsuthanhtoan WHERE trangthai = 'đã thanh toán'");
$totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $conn->query("SELECT COUNT(*) as total FROM vatpham WHERE sll < 10");
$lowStockProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$stmt = $conn->query("
    SELECT 
        SUM(CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.giatien * l.sll
            WHEN l.phukien_id IS NOT NULL THEN p.giatien * l.sll
            ELSE 0
        END) as total_revenue
    FROM lichsuthanhtoan l
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    WHERE l.trangthai = 'đã thanh toán'
");
$totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
$stmt = $conn->query("
    SELECT 
        v.id, 
        v.tenvatpham, 
        v.giatien, 
        v.loaisanpham,
        v.sll,
        SUM(l.sll) as total_sold
    FROM lichsuthanhtoan l
    JOIN vatpham v ON l.vatpham_id = v.id
    WHERE l.trangthai = 'đã thanh toán'
    GROUP BY v.id, v.tenvatpham, v.giatien, v.loaisanpham, v.sll
    ORDER BY total_sold DESC
    LIMIT 5
");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->query("
    SELECT 
        id, 
        tentaikhoan, 
        email, 
        status, 
        thoigian, 
        coin
    FROM taikhoan 
    ORDER BY thoigian DESC 
    LIMIT 5
");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->query("
    SELECT 
        DATE(l.thoigian) as date,
        SUM(CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.giatien * l.sll
            WHEN l.phukien_id IS NOT NULL THEN p.giatien * l.sll
            ELSE 0
        END) as daily_revenue
    FROM lichsuthanhtoan l
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    WHERE l.trangthai = 'đã thanh toán' AND l.thoigian >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(l.thoigian)
    ORDER BY DATE(l.thoigian) ASC
");
$revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dates = [];
$revenues = [];
foreach ($revenueData as $data) {
    $dates[] = date('d/m', strtotime($data['date']));
    $revenues[] = $data['daily_revenue'] / 1000;
}
$stmt = $conn->query("
    SELECT 
        l.id,  
        t.tentaikhoan,
        CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.tenvatpham
            WHEN l.phukien_id IS NOT NULL THEN p.ten
            ELSE 'Unknown'
        END as product_name,
        l.sll,
        CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.giatien * l.sll
            WHEN l.phukien_id IS NOT NULL THEN p.giatien * l.sll
            ELSE 0
        END as total_price,
        l.trangthai,
        l.thoigian
    FROM lichsuthanhtoan l
    JOIN taikhoan t ON l.taikhoan_id = t.id
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    ORDER BY l.thoigian DESC
    LIMIT 5
");
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->query("
    SELECT 
        loaisanpham, 
        COUNT(*) as count
    FROM vatpham
    GROUP BY loaisanpham
");
$productCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoryLabels = [];
$categoryCounts = [];
foreach ($productCategories as $category) {
    $categoryLabels[] = $category['loaisanpham'];
    $categoryCounts[] = $category['count'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị - Web Đồng Hồ</title>
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
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            border-radius: 0.35rem;
        }
        
        .card-revenue {
            border-left-color: var(--success-color);
        }
        
        .card-orders {
            border-left-color: var(--primary-color);
        }
        
        .card-avg {
            border-left-color: var(--info-color);
        }
        
        .text-primary { color: var(--primary-color) !important; }
        .text-success { color: var(--success-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .card-title {
            margin-bottom: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .chart-container {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        .date-range-picker {
            position: relative;
        }
        
        .form-filter {
            background-color: #fff;
            padding: 1.25rem;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .filter-heading {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        /* Responsive adjustments */
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
        
        .info-box {
            background-color: #fff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        
        .info-box h4 {
            margin-top: 0;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .info-box p {
            margin-bottom: 0;
        }
        
        .top-product-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .top-product-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .top-product-info {
            flex: 1;
        }
        
        .top-product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .top-product-meta {
            font-size: 0.85rem;
            color: #858796;
        }
        
        .top-product-sales {
            text-align: right;
            white-space: nowrap;
        }
        
        .top-product-quantity {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .top-product-revenue {
            font-size: 0.85rem;
            color: var(--success-color);
        }
        
        .export-btn {
            margin-left: 0.5rem;
        }
        
        @media print {
            .sidebar, .navbar, .no-print {
                display: none !important;
            }
            
            .content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                margin-bottom: 1rem !important;
            }
            
            body {
                background-color: white !important;
            }
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
            <a class="nav-link active" href="admin_dashboard.php">
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
            <a class="nav-link" href="product_report.php">
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
    <div class="content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            <form class="d-none d-sm-inline-block me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search search-form">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0" placeholder="Tìm kiếm..." aria-label="Search">
                    <button class="btn" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <ul class="navbar-nav ms-auto">
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
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw"></i>
                        <span class="badge bg-danger badge-counter">3+</span>
                    </a>
                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header bg-primary">
                            Thông báo
                        </h6>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-primary">
                                    <i class="fas fa-file-alt text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">14/04/2025</div>
                                <span class="fw-bold">Báo cáo doanh thu tháng đã sẵn sàng!</span>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-success">
                                    <i class="fas fa-donate text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">13/04/2025</div>
                                10 đơn hàng mới đã được tạo
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">12/04/2025</div>
                                Cảnh báo: Một số sản phẩm đang hết hàng!
                            </div>
                        </a>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem tất cả thông báo</a>
                    </div>
                </li>
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-envelope fa-fw"></i>
                        <span class="badge bg-danger badge-counter">7</span>
                    </a>
                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="messagesDropdown">
                        <h6 class="dropdown-header bg-primary">
                            Tin nhắn
                        </h6>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="dropdown-list-image me-3">
                                <img class="rounded-circle" src="https://source.unsplash.com/Mv9hjnEUHR4/60x60" alt="...">
                                <div class="status-indicator bg-success"></div>
                            </div>
                            <div>
                                <div class="text-truncate">Đơn hàng của tôi khi nào sẽ được giao?</div>
                                <div class="small text-gray-500">Nguyễn Văn A · 58m</div>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="dropdown-list-image me-3">
                                <img class="rounded-circle" src="https://source.unsplash.com/cssvEZacHvQ/60x60" alt="...">
                                <div class="status-indicator"></div>
                            </div>
                            <div>
                                <div class="text-truncate">Tôi muốn đổi trả sản phẩm!</div>
                                <div class="small text-gray-500">Trần Thị B · 1d</div>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="dropdown-list-image me-3">
                                <img class="rounded-circle" src="https://source.unsplash.com/gpLvSyTKnT8/60x60" alt="...">
                                <div class="status-indicator bg-warning"></div>
                            </div>
                            <div>
                                <div class="text-truncate">Báo cáo tháng trước trông rất tốt!</div>
                                <div class="small text-gray-500">Lê Văn C · 2d</div>
                            </div>
                        </a>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem thêm tin nhắn</a>
                    </div>
                </li>
                
                <div class="topbar-divider d-none d-sm-block"></div>
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small">Admin</span>
                        <img class="img-profile rounded-circle" src="https://source.unsplash.com/QAB-WJcbgJk/60x60">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                            Hồ sơ
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                            Cài đặt
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-list fa-sm fa-fw me-2 text-gray-400"></i>
                            Nhật ký hoạt động
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
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Bảng điều khiển</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-download fa-sm text-white-50"></i> Tạo báo cáo
                </a>
            </div>
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-users shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tài khoản
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalUsers); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-revenue shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Doanh thu
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> đ</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-products shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Sản phẩm
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalProducts + $totalAccessories); ?></div>
                                </div>
                                <div class="col-auto">
    <i class="fas fa-watch fa-2x text-gray-300"></i>
</div>
</div>
            </div>
        </div>
    </div>
</div>
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card stat-card card-orders shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Đơn hàng
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalOrders); ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card stat-card card-stock shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Hàng tồn kho thấp
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($lowStockProducts); ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<div class="row">
<div class="col-xl-8 col-lg-7">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Doanh Thu 7 Ngày Gần Đây</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <div class="dropdown-header">Tùy chọn:</div>
                    <a class="dropdown-item" href="#">Xuất dữ liệu</a>
                    <a class="dropdown-item" href="#">Xem chi tiết</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#">Lọc theo ngày</a>
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
<div class="col-xl-4 col-lg-5">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Phân Loại Sản Phẩm</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <div class="dropdown-header">Tùy chọn:</div>
                    <a class="dropdown-item" href="#">Xuất dữ liệu</a>
                    <a class="dropdown-item" href="#">Xem chi tiết</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-pie pt-4 pb-2">
                <canvas id="productCategoryChart"></canvas>
            </div>
            <div class="mt-4 text-center small">
                <?php foreach ($categoryLabels as $index => $label): ?>
                <span class="me-2">
                    <i class="fas fa-circle" style="color: <?php echo 'hsl('.($index * 60).',70%,60%)'; ?>"></i> <?php echo $label; ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>
<div class="row">
<div class="col-xl-6 col-lg-6">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sản Phẩm Bán Chạy</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Tên sản phẩm</th>
                            <th>Loại</th>
                            <th>Giá</th>
                            <th>Số lượng bán</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $product): ?>
                        <tr>
                            <td><?php echo $product['tenvatpham']; ?></td>
                            <td><?php echo $product['loaisanpham']; ?></td>
                            <td><?php echo number_format($product['giatien'], 0, ',', '.'); ?> đ</td>
                            <td><?php echo $product['total_sold']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="col-xl-6 col-lg-6">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Đơn Hàng Gần Đây</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Sản phẩm</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo $order['tentaikhoan']; ?></td>
                            <td><?php echo $order['product_name']; ?> (x<?php echo $order['sll']; ?>)</td>
                            <td><?php echo number_format($order['total_price'], 0, ',', '.'); ?> đ</td>
                            <td>
                                <?php if ($order['trangthai'] == 'đã thanh toán'): ?>
                                <span class="badge bg-success">Đã thanh toán</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Chờ xử lý</span>
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
<div class="row">
<div class="col-12">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tài Khoản Mới Nhất</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên tài khoản</th>
                            <th>Email</th>
                            <th>Trạng thái</th>
                            <th>Ngày đăng ký</th>
                            <th>Coin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['tentaikhoan']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <?php if ($user['status'] == 'active'): ?>
                                <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Bị khóa</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['thoigian'])); ?></td>
                            <td><?php echo number_format($user['coin'], 0, ',', '.'); ?></td>
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

</div>
<footer class="sticky-footer bg-white">
<div class="container my-auto">
    <div class="copyright text-center my-auto">
        <span>Bản quyền &copy; Web Đồng Hồ 2025</span>
    </div>
</div>
</footer>
</div>
<a class="scroll-to-top rounded" href="#page-top">
<i class="fas fa-angle-up"></i>
</a>
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Bạn muốn đăng xuất?</h5>
            <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        <div class="modal-body">Chọn "Đăng xuất" bên dưới nếu bạn đã sẵn sàng kết thúc phiên làm việc hiện tại.</div>
        <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
            <a class="btn btn-primary" href="logout.php">Đăng xuất</a>
        </div>
    </div>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>

<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.content').classList.toggle('active');
});

document.getElementById('sidebarToggleTop').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.content').classList.toggle('active');
});

$(document).ready(function() {
    $('#dataTable').DataTable({
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Vietnamese.json"
        }
    });
});
var ctx = document.getElementById('revenueChart').getContext('2d');
var revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: 'Doanh thu (nghìn đ)',
            data: <?php echo json_encode($revenues); ?>,
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            borderColor: 'rgba(78, 115, 223, 1)',
            pointRadius: 3,
            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointBorderColor: 'rgba(78, 115, 223, 1)',
            pointHoverRadius: 5,
            pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
            pointHitRadius: 10,
            pointBorderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
            padding: {
                left: 10,
                right: 25,
                top: 25,
                bottom: 0
            }
        },
        scales: {
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + 'k';
                    }
                },
                grid: {
                    color: "rgb(234, 236, 244)",
                    zeroLineColor: "rgb(234, 236, 244)",
                    drawBorder: false,
                    borderDash: [2],
                    zeroLineBorderDash: [2]
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: "rgb(255, 255, 255)",
                bodyColor: "#858796",
                titleMarginBottom: 10,
                titleColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                padding: 15,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Doanh thu: ' + context.raw.toLocaleString() + '.000 đ';
                    }
                }
            }
        }
    }
});
var ctx2 = document.getElementById('productCategoryChart').getContext('2d');
var productCategoryChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($categoryLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($categoryCounts); ?>,
            backgroundColor: [
                'hsl(0, 70%, 60%)',
                'hsl(60, 70%, 60%)',
                'hsl(120, 70%, 60%)',
                'hsl(180, 70%, 60%)'
            ],
            hoverBackgroundColor: [
                'hsl(0, 70%, 50%)',
                'hsl(60, 70%, 50%)',
                'hsl(120, 70%, 50%)',
                'hsl(180, 70%, 50%)'
            ],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: "rgb(255, 255, 255)",
                bodyColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                padding: 15,
                displayColors: false
            }
        },
        cutout: '70%'
    }
});
</script>
</body>
</html>