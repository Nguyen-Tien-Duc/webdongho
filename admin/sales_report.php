<?php
session_start();
require_once "../database/db.php";

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}

// Xử lý lọc theo thời gian
$currentYear = date('Y');
$currentMonth = date('m');

// Mặc định lọc theo tháng hiện tại
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'month';
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Chuẩn bị điều kiện WHERE cho truy vấn
$whereClauses = ["l.trangthai = 'đã thanh toán'"];

if ($filterType == 'month') {
    $whereClauses[] = "YEAR(l.thoigian) = $selectedYear AND MONTH(l.thoigian) = $selectedMonth";
    $periodLabel = "Tháng $selectedMonth/$selectedYear";
} elseif ($filterType == 'year') {
    $whereClauses[] = "YEAR(l.thoigian) = $selectedYear";
    $periodLabel = "Năm $selectedYear";
} elseif ($filterType == 'custom') {
    $whereClauses[] = "l.thoigian BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
    $periodLabel = "Từ " . date('d/m/Y', strtotime($startDate)) . " đến " . date('d/m/Y', strtotime($endDate));
}

$whereClause = implode(' AND ', $whereClauses);

// Tổng doanh thu
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
    WHERE $whereClause
");
$totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

// Tổng số đơn hàng
$stmt = $conn->query("
    SELECT COUNT(*) as total_orders
    FROM lichsuthanhtoan l
    WHERE $whereClause
");
$totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

// Giá trị đơn hàng trung bình
$avgOrderValue = ($totalOrders > 0) ? $totalRevenue / $totalOrders : 0;

// Doanh thu theo loại sản phẩm
$stmt = $conn->query("
    SELECT 
        'Đồng hồ' as product_type,
        v.loaisanpham as category,
        SUM(v.giatien * l.sll) as revenue,
        COUNT(l.id) as orders,
        SUM(l.sll) as quantity
    FROM lichsuthanhtoan l
    JOIN vatpham v ON l.vatpham_id = v.id
    WHERE $whereClause
    GROUP BY v.loaisanpham
    
    UNION ALL
    
    SELECT 
        'Phụ kiện' as product_type,
        p.loaiphukien as category,
        SUM(p.giatien * l.sll) as revenue,
        COUNT(l.id) as orders,
        SUM(l.sll) as quantity
    FROM lichsuthanhtoan l
    JOIN phukien p ON l.phukien_id = p.id
    WHERE $whereClause
    GROUP BY p.loaiphukien
    
    ORDER BY revenue DESC
");
$categoryRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Doanh thu theo ngày trong khoảng thời gian
$dateGroupBy = "DATE(l.thoigian)";
$dateFormat = "DATE_FORMAT(l.thoigian, '%d/%m/%Y')";

if ($filterType == 'year') {
    $dateGroupBy = "MONTH(l.thoigian)";
    $dateFormat = "DATE_FORMAT(l.thoigian, '%m/%Y')";
}

$stmt = $conn->query("
    SELECT 
        $dateFormat as date_label,
        $dateGroupBy as date_group,
        SUM(CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.giatien * l.sll
            WHEN l.phukien_id IS NOT NULL THEN p.giatien * l.sll
            ELSE 0
        END) as daily_revenue,
        COUNT(DISTINCT l.id) as order_count
    FROM lichsuthanhtoan l
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    WHERE $whereClause
    GROUP BY date_group, date_label
    ORDER BY date_group ASC
");
$revenueByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Chuyển đổi dữ liệu cho biểu đồ
$chartLabels = [];
$chartRevenue = [];
$chartOrders = [];

foreach ($revenueByDate as $data) {
    $chartLabels[] = $data['date_label'];
    $chartRevenue[] = $data['daily_revenue'] / 1000; // Đơn vị nghìn đồng
    $chartOrders[] = $data['order_count'];
}

// Top 10 sản phẩm bán chạy
$stmt = $conn->query("
    SELECT 
        'Đồng hồ' as product_type,
        v.id, 
        v.tenvatpham as product_name,
        v.loaisanpham as category,
        v.giatien as price,
        SUM(l.sll) as quantity_sold,
        SUM(v.giatien * l.sll) as total_revenue
    FROM lichsuthanhtoan l
    JOIN vatpham v ON l.vatpham_id = v.id
    WHERE $whereClause
    GROUP BY v.id, v.tenvatpham, v.loaisanpham, v.giatien
    
    UNION ALL
    
    SELECT 
        'Phụ kiện' as product_type,
        p.id,
        p.ten as product_name,
        p.loaiphukien as category,
        p.giatien as price,
        SUM(l.sll) as quantity_sold,
        SUM(p.giatien * l.sll) as total_revenue
    FROM lichsuthanhtoan l
    JOIN phukien p ON l.phukien_id = p.id
    WHERE $whereClause
    GROUP BY p.id, p.ten, p.loaiphukien, p.giatien
    
    ORDER BY quantity_sold DESC, total_revenue DESC
    LIMIT 10
");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top khách hàng
$stmt = $conn->query("
    SELECT 
        t.id,
        t.tentaikhoan,
        t.email,
        COUNT(l.id) as total_orders,
        SUM(CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.giatien * l.sll
            WHEN l.phukien_id IS NOT NULL THEN p.giatien * l.sll
            ELSE 0
        END) as total_spent
    FROM lichsuthanhtoan l
    JOIN taikhoan t ON l.taikhoan_id = t.id
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    WHERE $whereClause
    GROUP BY t.id, t.tentaikhoan, t.email
    ORDER BY total_spent DESC
    LIMIT 10
");
$topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê doanh thu theo giới tính (chỉ đồng hồ)
if ($filterType != 'custom') {
    $stmt = $conn->query("
        SELECT 
            v.gioitinh,
            COUNT(l.id) as total_orders,
            SUM(v.giatien * l.sll) as total_revenue,
            SUM(l.sll) as quantity_sold
        FROM lichsuthanhtoan l
        JOIN vatpham v ON l.vatpham_id = v.id
        WHERE $whereClause
        GROUP BY v.gioitinh
    ");
    $genderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Chuẩn bị dữ liệu cho biểu đồ tròn
    $genderLabels = [];
    $genderRevenue = [];
    
    foreach ($genderStats as $stat) {
        $genderLabels[] = $stat['gioitinh'];
        $genderRevenue[] = $stat['total_revenue'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Doanh Thu - Web Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            <a class="nav-link active" href="sales_report.php">
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
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Page Title -->
            <div class="d-sm-flex align-items-center justify-content-between">
                <h1 class="h3 mb-0 text-gray-800">Báo Cáo Doanh Thu</h1>
            </div>
            
            <!-- Topbar Navbar -->
            <ul class="navbar-nav ms-auto">
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
            <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                <h1 class="h3 mb-0 text-gray-800">Báo cáo doanh thu: <?php echo $periodLabel; ?></h1>
                <div>
                    <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" onclick="window.print()">
                        <i class="fas fa-print fa-sm text-white-50"></i> In báo cáo
                    </button>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm export-btn">
                        <i class="fas fa-download fa-sm text-white-50"></i> Xuất Excel
                    </a>
                </div>
            </div>
            
            <!-- Filter Form -->
            <div class="row no-print">
                <div class="col-12">
                    <div class="form-filter">
                        <h5 class="filter-heading"><i class="fas fa-filter me-2"></i> Lọc báo cáo</h5>
                        <form action="sales_report.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="filter" class="form-label">Loại báo cáo</label>
                                <select name="filter" id="filter" class="form-select" onchange="toggleDateInputs()">
                                    <option value="month" <?php echo $filterType == 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                                    <option value="year" <?php echo $filterType == 'year' ? 'selected' : ''; ?>>Theo năm</option>
                                    <option value="custom" <?php echo $filterType == 'custom' ? 'selected' : ''; ?>>Tùy chỉnh</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3" id="monthSelector" style="display: <?php echo ($filterType != 'custom' && $filterType != 'year') ? 'block' : 'none'; ?>">
                                <label for="month" class="form-label">Tháng</label>
                                <select name="month" id="month" class="form-select">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $selectedMonth == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="year" class="form-label">Năm</label>
                                <select name="year" id="year" class="form-select">
                                    <?php for ($i = $currentYear - 5; $i <= $currentYear; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $selectedYear == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 date-range" id="startDateSelector" style="display: <?php echo $filterType == 'custom' ? 'block' : 'none'; ?>">
                                <label for="start_date" class="form-label">Từ ngày</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            
                            <div class="col-md-3 date-range" id="endDateSelector" style="display: <?php echo $filterType == 'custom' ? 'block' : 'none'; ?>">
                                <label for="end_date" class="form-label">Đến ngày</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Xem báo cáo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Print header (only visible when printing) -->
            <div class="d-none d-print-block mb-4">
                <div class="text-center">
                    <h2>BÁO CÁO DOANH THU</h2>
                    <h4><?php echo $periodLabel; ?></h4>
                    <p>Ngày xuất báo cáo: <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>
            
            <!-- Summary Stats Cards -->
            <div class="row">
                <!-- Total Revenue Card -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card card-revenue shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Tổng doanh thu
                                    </div>
                                    
<div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalRevenue, 0, ',', '.'); ?> đ</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Orders Card -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card card-orders shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng đơn hàng
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalOrders, 0, ',', '.'); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Average Order Value Card -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stat-card card-avg shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Giá trị đơn hàng trung bình
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgOrderValue, 0, ',', '.'); ?> đ</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Charts -->
            <div class="row">
                <!-- Revenue Trend Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Biểu đồ doanh thu theo thời gian</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Categories Revenue Pie Chart -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Doanh thu theo danh mục</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                            <div class="mt-4">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Danh mục</th>
                                                <th class="text-right">Doanh thu</th>
                                                <th class="text-right">%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categoryRevenue as $category): ?>
                                            <tr>
                                                <td>
                                                    <?php echo $category['product_type'] . ': ' . $category['category']; ?>
                                                </td>
                                                <td class="text-right">
                                                    <?php echo number_format($category['revenue'], 0, ',', '.'); ?> đ
                                                </td>
                                                <td class="text-right">
                                                    <?php echo number_format(($category['revenue'] / $totalRevenue) * 100, 1); ?>%
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
            
            <!-- Gender Distribution Chart (for watches only) -->
            <?php if (isset($genderStats) && count($genderStats) > 0): ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Phân bố doanh thu theo giới tính (đồng hồ)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 15rem;">
                                <canvas id="genderChart"></canvas>
                            </div>
                            <div class="mt-4">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Giới tính</th>
                                                <th class="text-right">Số lượng</th>
                                                <th class="text-right">Doanh thu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($genderStats as $stat): ?>
                                            <tr>
                                                <td><?php echo $stat['gioitinh']; ?></td>
                                                <td class="text-right"><?php echo number_format($stat['quantity_sold'], 0, ',', '.'); ?></td>
                                                <td class="text-right"><?php echo number_format($stat['total_revenue'], 0, ',', '.'); ?> đ</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Empty space for layout balance or additional chart in the future -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Thống kê chi tiết</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <h4>Số lượng sản phẩm bán ra</h4>
                                        <p class="h3 text-success">
                                            <?php 
                                            $totalProducts = 0;
                                            foreach ($categoryRevenue as $category) {
                                                $totalProducts += $category['quantity'];
                                            }
                                            echo number_format($totalProducts, 0, ',', '.');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <h4>Loại sản phẩm bán chạy nhất</h4>
                                        <p class="h3 text-primary">
                                            <?php 
                                            $bestCategory = '';
                                            $maxQuantity = 0;
                                            foreach ($categoryRevenue as $category) {
                                                if ($category['quantity'] > $maxQuantity) {
                                                    $maxQuantity = $category['quantity'];
                                                    $bestCategory = $category['product_type'] . ': ' . $category['category'];
                                                }
                                            }
                                            echo $bestCategory;
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Top Products Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top 10 sản phẩm bán chạy</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="topProductsTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Tên sản phẩm</th>
                                            <th>Loại</th>
                                            <th>Danh mục</th>
                                            <th>Giá</th>
                                            <th>Số lượng đã bán</th>
                                            <th>Doanh thu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach($topProducts as $product): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo $product['product_name']; ?></td>
                                            <td><?php echo $product['product_type']; ?></td>
                                            <td><?php echo $product['category']; ?></td>
                                            <td><?php echo number_format($product['price'], 0, ',', '.'); ?> đ</td>
                                            <td><?php echo number_format($product['quantity_sold'], 0, ',', '.'); ?></td>
                                            <td><?php echo number_format($product['total_revenue'], 0, ',', '.'); ?> đ</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top 10 khách hàng</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="topCustomersTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Tên tài khoản</th>
                                            <th>Email</th>
                                            <th>Số đơn hàng</th>
                                            <th>Tổng chi tiêu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; foreach($topCustomers as $customer): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo $customer['tentaikhoan']; ?></td>
                                            <td><?php echo $customer['email']; ?></td>
                                            <td><?php echo number_format($customer['total_orders'], 0, ',', '.'); ?></td>
                                            <td><?php echo number_format($customer['total_spent'], 0, ',', '.'); ?> đ</td>
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
        <!-- /.container-fluid -->
    </div>
    <!-- End of Main Content -->
    
    <!-- Footer -->
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Bản quyền &copy; Web Đồng Hồ 2025</span>
            </div>
        </div>
    </footer>
    <!-- End of Footer -->
    
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
    
    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Bạn chắc chắn muốn đăng xuất?</h5>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Chọn "Đăng xuất" bên dưới nếu bạn thực sự muốn kết thúc phiên làm việc hiện tại.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <a class="btn btn-primary" href="../logout.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap core JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Custom scripts -->
    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggleTop').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('active');
        });
        
        // DataTables initialization
        $(document).ready(function() {
            $('#topProductsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/vi.json'
                }
            });
            
            $('#topCustomersTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/vi.json'
                }
            });
        });
        
        // Date filters toggle
        function toggleDateInputs() {
            var filterType = document.getElementById('filter').value;
            
            if (filterType === 'month') {
                document.getElementById('monthSelector').style.display = 'block';
                document.getElementById('startDateSelector').style.display = 'none';
                document.getElementById('endDateSelector').style.display = 'none';
            } else if (filterType === 'year') {
                document.getElementById('monthSelector').style.display = 'none';
                document.getElementById('startDateSelector').style.display = 'none';
                document.getElementById('endDateSelector').style.display = 'none';
            } else if (filterType === 'custom') {
                document.getElementById('monthSelector').style.display = 'none';
                document.getElementById('startDateSelector').style.display = 'block';
                document.getElementById('endDateSelector').style.display = 'block';
            }
        }
        
        // Revenue Chart
        var ctx = document.getElementById('revenueChart').getContext('2d');
        var revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Doanh thu (nghìn đồng)',
                        data: <?php echo json_encode($chartRevenue); ?>,
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
                    },
                    {
                        label: 'Số đơn hàng',
                        data: <?php echo json_encode($chartOrders); ?>,
                        backgroundColor: 'rgba(28, 200, 138, 0)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: 'rgba(28, 200, 138, 1)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('vi-VN') + 'k';
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            display: false,
                        },
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 0) {
                                    label += context.parsed.y.toLocaleString('vi-VN') + 'k đồng';
                                } else {
                                    label += context.parsed.y.toLocaleString('vi-VN') + ' đơn';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Category Revenue Chart
        var categoryData = <?php 
            $categoryLabels = [];
            $categoryData = [];
            $backgroundColors = [
                'rgba(78, 115, 223, 0.8)',
                'rgba(28, 200, 138, 0.8)',
                'rgba(54, 185, 204, 0.8)',
                'rgba(246, 194, 62, 0.8)',
                'rgba(231, 74, 59, 0.8)',
                'rgba(133, 135, 150, 0.8)',
                'rgba(105, 0, 132, 0.8)',
                'rgba(0, 137, 132, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)'
            ];
            
            foreach ($categoryRevenue as $key => $category) {
                $categoryLabels[] = $category['product_type'] . ': ' . $category['category'];
                $categoryData[] = $category['revenue'];
            }
            
            echo json_encode([
                'labels' => $categoryLabels,
                'data' => $categoryData,
                'backgroundColors' => array_slice($backgroundColors, 0, count($categoryLabels))
            ]); 
        ?>;
        
        var ctxCategory = document.getElementById('categoryChart').getContext('2d');
        var categoryChart = new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.data,
                    backgroundColor: categoryData.backgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = sum ? Math.round((value / sum) * 100) : 0;
                                
                                return `${label}: ${value.toLocaleString('vi-VN')} đ (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        <?php if (isset($genderStats) && count($genderStats) > 0): ?>
        // Gender Distribution Chart
        var genderData = <?php 
            echo json_encode([
                'labels' => $genderLabels,
                'data' => $genderRevenue,
                'backgroundColors' => ['rgba(54, 185, 204, 0.8)', 'rgba(246, 194, 62, 0.8)']
            ]); 
        ?>;
        
        var ctxGender = document.getElementById('genderChart').getContext('2d');
        var genderChart = new Chart(ctxGender, {
            type: 'pie',
            data: {
                labels: genderData.labels,
                datasets: [{
                    data: genderData.data,
                    backgroundColor: genderData.backgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = sum ? Math.round((value / sum) * 100) : 0;
                                
                                return `${label}: ${value.toLocaleString('vi-VN')} đ (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Export to Excel functionality
        document.querySelector('.export-btn').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Chức năng xuất Excel đang được phát triển!');
        });
    </script>
</body>
</html>