<?php
// manage_payments.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}
require_once "../database/db.php";

// Process actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    // Approve payment
    if ($action === 'approve') {
        // Update lichsunap status to success
        $stmt = $conn->prepare("UPDATE lichsunap SET trangthai = 'thành công' WHERE id = ?");
        $stmt->execute([$id]);
        
        // Add coins to user account
        $stmt = $conn->prepare("
            UPDATE taikhoan t
            JOIN lichsunap l ON t.id = l.taikhoan_id
            SET t.coin = t.coin + l.coin
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        
        // Redirect to avoid resubmission
        header("Location: manage_payments.php?status=approved");
        exit;
    }
    
    // Reject payment
    else if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE lichsunap SET trangthai = 'thất bại' WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: manage_payments.php?status=rejected");
        exit;
    }
}

// Get transaction filters
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query for payment transactions
$query = "
    SELECT 
        l.id,
        l.uid,
        t.tentaikhoan,
        t.email,
        l.phuongthuc,
        l.coin,
        l.trangthai,
        l.thoigian
    FROM lichsunap l
    JOIN taikhoan t ON l.taikhoan_id = t.id
    WHERE 1=1
";

// Apply filters
$params = [];

if (!empty($status_filter)) {
    $query .= " AND l.trangthai = ?";
    $params[] = $status_filter;
}

if (!empty($payment_method)) {
    $query .= " AND l.phuongthuc = ?";
    $params[] = $payment_method;
}

if (!empty($date_from)) {
    $query .= " AND DATE(l.thoigian) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(l.thoigian) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (t.tentaikhoan LIKE ? OR t.email LIKE ? OR l.uid LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records for pagination
$count_query = str_replace("SELECT 
        l.id,
        l.uid,
        t.tentaikhoan,
        t.email,
        l.phuongthuc,
        l.coin,
        l.trangthai,
        l.thoigian", "SELECT COUNT(*) as total", $query);

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->execute($params);
}
else {
    $stmt->execute();
}
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get paginated results
$query .= " ORDER BY l.thoigian DESC LIMIT $offset, $per_page";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->execute($params);
}
else {
    $stmt->execute();
}
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN trangthai = 'thành công' THEN 1 ELSE 0 END) as successful_transactions,
        SUM(CASE WHEN trangthai = 'thất bại' THEN 1 ELSE 0 END) as failed_transactions,
        SUM(CASE WHEN trangthai = 'đang xử lý' THEN 1 ELSE 0 END) as pending_transactions,
        SUM(CASE WHEN trangthai = 'thành công' THEN coin ELSE 0 END) as total_coins_added
    FROM lichsunap
";

// Apply date filters to statistics if present
$stats_params = [];
if (!empty($date_from)) {
    $stats_query .= " WHERE DATE(thoigian) >= ?";
    $stats_params[] = $date_from;
    
    if (!empty($date_to)) {
        $stats_query .= " AND DATE(thoigian) <= ?";
        $stats_params[] = $date_to;
    }
} else if (!empty($date_to)) {
    $stats_query .= " WHERE DATE(thoigian) <= ?";
    $stats_params[] = $date_to;
}

$stmt = $conn->prepare($stats_query);
if (!empty($stats_params)) {
    $stmt->execute($stats_params);
} else {
    $stmt->execute();
}
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get payment methods distribution
$methods_query = "
    SELECT 
        phuongthuc,
        COUNT(*) as count,
        SUM(CASE WHEN trangthai = 'thành công' THEN coin ELSE 0 END) as total_coins
    FROM lichsunap
    GROUP BY phuongthuc
";
$stmt = $conn->query($methods_query);
$payment_methods_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payment transactions (for the graph)
$recent_query = "
    SELECT 
        DATE(thoigian) as date,
        COUNT(*) as total_transactions,
        SUM(CASE WHEN trangthai = 'thành công' THEN coin ELSE 0 END) as coins_added
    FROM lichsunap
    WHERE thoigian >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY DATE(thoigian)
    ORDER BY date ASC
";
$stmt = $conn->query($recent_query);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format chart data
$chart_dates = [];
$chart_transactions = [];
$chart_coins = [];

foreach ($recent_transactions as $day) {
    $chart_dates[] = date('d/m', strtotime($day['date']));
    $chart_transactions[] = $day['total_transactions'];
    $chart_coins[] = $day['coins_added'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thanh Toán - Web Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
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
            top: 0;
            left: 0;
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
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            border-radius: 0.35rem;
        }
        
        .card-total {
            border-left-color: var(--primary-color);
        }
        
        .card-successful {
            border-left-color: var(--success-color);
        }
        
        .card-pending {
            border-left-color: var(--warning-color);
        }
        
        .card-failed {
            border-left-color: var(--danger-color);
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
        
        .badge-pending {
            background-color: var(--warning-color);
            color: #fff;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: #fff;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: #fff;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .chart-container {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        .input-group-text {
            background-color: #f8f9fc;
            border-right: none;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #d1d3e2;
        }
        
        .form-control {
            border-left: none;
        }
        
        .input-group .form-control:focus ~ .input-group-text {
            border-color: #d1d3e2;
        }
        
        .filter-form {
            background-color: #fff;
            padding: 1rem;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1rem;
        }
        
        .transaction-id {
            font-family: monospace;
            font-weight: 600;
        }
        
        .payment-method-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .payment-method-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            border-radius: 0.25rem;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .badge-thecard {
            background-color: #9B59B6;
            color: white;
        }
        
        .badge-momo {
            background-color: #9C27B0;
            color: white;
        }
        
        .badge-banking {
            background-color: #007BFF;
            color: white;
        }
        
        .badge-paypal {
            background-color: #0079C1;
            color: white;
        }
        
        .transaction-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-pending {
            background-color: var(--warning-color);
        }
        
        .status-success {
            background-color: var(--success-color);
        }
        
        .status-failed {
            background-color: var(--danger-color);
        }
        
        .payment-method-chart {
            height: 250px;
        }
        
        .coin-amount {
            font-weight: 700;
            color: #FF9800;
        }
        
        .status-label {
            display: inline-flex;
            align-items: center;
            font-weight: 600;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Animation for alerts */
        .alert-fade {
            animation: fadeOut 5s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
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
        
        .datepicker {
            z-index: 1600 !important; /* Keep above modals */
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
            <a class="nav-link active" href="manage_payments.php">
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
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Topbar Navbar -->
            <ul class="navbar-nav ms-auto">
                <!-- Nav Item - Alerts -->
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw"></i>
                        <!-- Counter - Alerts -->
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
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">14/04/2025</div>
                                <span>Có 5 đơn nạp tiền đang chờ xử lý!</span>
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
                <h1 class="h3 mb-0 text-gray-800">Quản lý thanh toán</h1>
                <div>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fas fa-download fa-sm text-white-50"></i> Xuất báo cáo
                    </a>
                    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Thêm thanh toán
                    </a>
                </div>
            </div>
            
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'approved'): ?>
                    <div class="alert alert-success alert-dismissible fade show alert-fade" role="alert">
                        <strong>Thành công!</strong> Đã duyệt thanh toán và thêm coin cho người dùng.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['status'] == 'rejected'): ?>
                    <div class="alert alert-danger alert-dismissible fade show alert-fade" role="alert">
                        <strong>Từ chối!</strong> Đã từ chối yêu cầu thanh toán.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Content Row - Stats -->
            <div class="row">
                <!-- Total Transactions -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-total shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng giao dịch
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_transactions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Successful Transactions -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-successful shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Giao dịch thành công
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['successful_transactions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Transactions -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-pending shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Đang chờ xử lý
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pending_transactions']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Coins Added -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-failed shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Tổng Coin đã nạp
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_coins_added']); ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-coins fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Filter Form -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lọc Giao Dịch</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="manage_payments.php" class="row g-3 align-items-end">
            <div class="col-md-3 mb-3">
                <label for="status_filter" class="form-label">Trạng thái</label>
                <select name="status_filter" id="status_filter" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="thành công" <?php echo ($status_filter == 'thành công') ? 'selected' : ''; ?>>Thành công</option>
                    <option value="thất bại" <?php echo ($status_filter == 'thất bại') ? 'selected' : ''; ?>>Thất bại</option>
                    <option value="đang xử lý" <?php echo ($status_filter == 'đang xử lý') ? 'selected' : ''; ?>>Đang xử lý</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="payment_method" class="form-label">Phương thức</label>
                <select name="payment_method" id="payment_method" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="Thẻ Cào" <?php echo ($payment_method == 'Thẻ Cào') ? 'selected' : ''; ?>>Thẻ Cào</option>
                    <option value="Momo" <?php echo ($payment_method == 'Momo') ? 'selected' : ''; ?>>Momo</option>
                    <option value="Banking" <?php echo ($payment_method == 'Banking') ? 'selected' : ''; ?>>Banking</option>
                    <option value="Paypal" <?php echo ($payment_method == 'Paypal') ? 'selected' : ''; ?>>Paypal</option>
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <label for="date_from" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="date_to" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="search" class="form-label">Tìm kiếm</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Tên, Email, ID..." value="<?php echo $search; ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Lọc</button>
                <a href="manage_payments.php" class="btn btn-secondary">Đặt lại</a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Danh sách giao dịch</h6>
        <div>
            <span class="text-xs text-gray-600">Hiển thị <?php echo count($payments); ?> của <?php echo $total_records; ?> giao dịch</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="paymentsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Người dùng</th>
                        <th>Phương thức</th>
                        <th>Coin</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payments) > 0): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="transaction-id"><?php echo $payment['uid']; ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($payment['tentaikhoan']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($payment['email']); ?></div>
                                </td>
                                <td>
                                    <?php
                                    $method_class = 'badge-primary';
                                    $method_icon = 'fa-money-bill';
                                    
                                    switch ($payment['phuongthuc']) {
                                        case 'Thẻ Cào':
                                            $method_class = 'badge-thecard';
                                            $method_icon = 'fa-credit-card';
                                            break;
                                        case 'Momo':
                                            $method_class = 'badge-momo';
                                            $method_icon = 'fa-mobile-alt';
                                            break;
                                        case 'Banking':
                                            $method_class = 'badge-banking';
                                            $method_icon = 'fa-university';
                                            break;
                                        case 'Paypal':
                                            $method_class = 'badge-paypal';
                                            $method_icon = 'fa-paypal';
                                            break;
                                    }
                                    ?>
                                    <span class="payment-method-badge <?php echo $method_class; ?>">
                                        <i class="fas <?php echo $method_icon; ?> me-1"></i>
                                        <?php echo $payment['phuongthuc']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="coin-amount"><?php echo number_format($payment['coin']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'bg-warning text-dark';
                                    $status_dot = 'status-pending';
                                    
                                    if ($payment['trangthai'] == 'thành công') {
                                        $status_class = 'bg-success';
                                        $status_dot = 'status-success';
                                    } elseif ($payment['trangthai'] == 'thất bại') {
                                        $status_class = 'bg-danger';
                                        $status_dot = 'status-failed';
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <span class="transaction-status <?php echo $status_dot; ?>"></span>
                                        <?php echo ucfirst($payment['trangthai']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($payment['thoigian'])); ?>
                                </td>
                                <td>
                                    <?php if ($payment['trangthai'] == 'đang xử lý'): ?>
                                        <a href="manage_payments.php?action=approve&id=<?php echo $payment['id']; ?>" class="btn btn-success btn-sm btn-action me-1" onclick="return confirm('Xác nhận duyệt và thêm <?php echo number_format($payment['coin']); ?> coin cho tài khoản <?php echo htmlspecialchars($payment['tentaikhoan']); ?>?');">
                                            <i class="fas fa-check-circle"></i> Duyệt
                                        </a>
                                        <a href="manage_payments.php?action=reject&id=<?php echo $payment['id']; ?>" class="btn btn-danger btn-sm btn-action" onclick="return confirm('Bạn có chắc muốn từ chối giao dịch này?');">
                                            <i class="fas fa-times-circle"></i> Từ chối
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-info btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#transactionDetailModal" 
                                                data-id="<?php echo $payment['id']; ?>" 
                                                data-uid="<?php echo $payment['uid']; ?>"
                                                data-username="<?php echo htmlspecialchars($payment['tentaikhoan']); ?>"
                                                data-email="<?php echo htmlspecialchars($payment['email']); ?>"
                                                data-method="<?php echo $payment['phuongthuc']; ?>"
                                                data-coins="<?php echo $payment['coin']; ?>"
                                                data-status="<?php echo $payment['trangthai']; ?>"
                                                data-time="<?php echo date('d/m/Y H:i', strtotime($payment['thoigian'])); ?>">
                                            <i class="fas fa-eye"></i> Chi tiết
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Không có giao dịch nào</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-end">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?php echo (!empty($status_filter)) ? '&status_filter=' . $status_filter : ''; ?><?php echo (!empty($payment_method)) ? '&payment_method=' . $payment_method : ''; ?><?php echo (!empty($date_from)) ? '&date_from=' . $date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to=' . $date_to : ''; ?><?php echo (!empty($search)) ? '&search=' . $search : ''; ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo (!empty($status_filter)) ? '&status_filter=' . $status_filter : ''; ?><?php echo (!empty($payment_method)) ? '&payment_method=' . $payment_method : ''; ?><?php echo (!empty($date_from)) ? '&date_from=' . $date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to=' . $date_to : ''; ?><?php echo (!empty($search)) ? '&search=' . $search : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($status_filter)) ? '&status_filter=' . $status_filter : ''; ?><?php echo (!empty($payment_method)) ? '&payment_method=' . $payment_method : ''; ?><?php echo (!empty($date_from)) ? '&date_from=' . $date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to=' . $date_to : ''; ?><?php echo (!empty($search)) ? '&search=' . $search : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo (!empty($status_filter)) ? '&status_filter=' . $status_filter : ''; ?><?php echo (!empty($payment_method)) ? '&payment_method=' . $payment_method : ''; ?><?php echo (!empty($date_from)) ? '&date_from=' . $date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to=' . $date_to : ''; ?><?php echo (!empty($search)) ? '&search=' . $search : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo (!empty($status_filter)) ? '&status_filter=' . $status_filter : ''; ?><?php echo (!empty($payment_method)) ? '&payment_method=' . $payment_method : ''; ?><?php echo (!empty($date_from)) ? '&date_from=' . $date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to=' . $date_to : ''; ?><?php echo (!empty($search)) ? '&search=' . $search : ''; ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Analytics Row -->
<div class="row">
    <!-- Transactions Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Biểu đồ giao dịch 30 ngày qua</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="chartMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="chartMenuLink">
                        <div class="dropdown-header">Chart Options:</div>
                        <a class="dropdown-item chart-type" href="#" data-type="transactions">Số lượng giao dịch</a>
                        <a class="dropdown-item chart-type" href="#" data-type="coins">Số lượng Coin</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportChartModal">Xuất biểu đồ</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="transactionsChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="me-2">
                        <i class="fas fa-circle text-primary"></i> Tổng giao dịch
                    </span>
                    <span class="me-2">
                        <i class="fas fa-circle text-success"></i> Coin đã nạp
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Chart -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Phương thức thanh toán</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="methodMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="methodMenuLink">
                        <div class="dropdown-header">Chart Options:</div>
                        <a class="dropdown-item" href="#">Xem theo số lượng</a>
                        <a class="dropdown-item" href="#">Xem theo giá trị</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportMethodsModal">Xuất báo cáo</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="payment-method-chart">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
                <div class="mt-4 small">
                    <?php foreach ($payment_methods_stats as $method): ?>
                        <div class="mb-1">
                            <?php
                            $method_class = 'text-primary';
                            $method_icon = 'fa-money-bill';
                            
                            switch ($method['phuongthuc']) {
                                case 'Thẻ Cào':
                                    $method_class = 'text-purple';
                                    $method_icon = 'fa-credit-card';
                                    break;
                                case 'Momo':
                                    $method_class = 'text-pink';
                                    $method_icon = 'fa-mobile-alt';
                                    break;
                                case 'Banking':
                                    $method_class = 'text-info';
                                    $method_icon = 'fa-university';
                                    break;
                                case 'Paypal':
                                    $method_class = 'text-primary';
                                    $method_icon = 'fa-paypal';
                                    break;
                            }
                            ?>
                            <span class="me-2">
                                <i class="fas fa-circle <?php echo $method_class; ?>"></i>
                                <strong><?php echo $method['phuongthuc']; ?></strong>
                            </span>
                            <span class="float-end">
                                <?php echo number_format($method['count']); ?> giao dịch
                                <span class="text-warning ms-1">
                                    (<?php echo number_format($method['total_coins']); ?> coin)
                                </span>
                            </span>
                        </div>
                    <?php endforeach; ?>
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
            <span>© Web Đồng Hồ Admin 2025</span>
        </div>
    </div>
</footer>
<!-- End of Footer -->

</div>
<!-- End of Content Wrapper -->

<!-- Transaction Detail Modal -->
<div class="modal fade" id="transactionDetailModal" tabindex="-1" aria-labelledby="transactionDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionDetailModalLabel">Chi tiết giao dịch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="transaction-detail">
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Giao dịch ID:</div>
                        <div class="col-8 transaction-id" id="modal-uid"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Người dùng:</div>
                        <div class="col-8" id="modal-username"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Email:</div>
                        <div class="col-8" id="modal-email"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Phương thức:</div>
                        <div class="col-8" id="modal-method"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Số lượng Coin:</div>
                        <div class="col-8 coin-amount" id="modal-coins"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Trạng thái:</div>
                        <div class="col-8" id="modal-status"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Thời gian:</div>
                        <div class="col-8" id="modal-time"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <a href="#" class="btn btn-primary" id="print-transaction"><i class="fas fa-print"></i> In</a>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPaymentModalLabel">Thêm giao dịch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPaymentForm">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Người dùng</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Chọn người dùng</option>
                            <!-- PHP would populate user options here -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method_add" class="form-label">Phương thức thanh toán</label>
                        <select class="form-select" id="payment_method_add" name="payment_method" required>
                            <option value="Thẻ Cào">Thẻ Cào</option>
                            <option value="Momo">Momo</option>
                            <option value="Banking">Banking</option>
                            <option value="Paypal">Paypal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="coins" class="form-label">Số lượng Coin</label>
                        <input type="number" class="form-control" id="coins" name="coins" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="thành công">Thành công</option>
                            <option value="thất bại">Thất bại</option>
                            <option value="đang xử lý">Đang xử lý</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="submitPayment">Lưu</button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Xuất báo cáo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Định dạng</label>
                        <select class="form-select" id="export_format" name="export_format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="export_date_from" class="form-label">Từ ngày</label>
                        <input type="date" class="form-control" id="export_date_from" name="export_date_from">
                    </div>
                    <div class="mb-3">
                        <label for="export_date_to" class="form-label">Đến ngày</label>
                        <input type="date" class="form-control" id="export_date_to" name="export_date_to">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="include_details" name="include_details" checked>
                        <label class="form-check-label" for="include_details">Bao gồm chi tiết giao dịch</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="startExport">Xuất</button>
            </div>
        </div>
    </div>
</div>

<!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Bạn chắc chắn muốn đăng xuất?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Chọn "Đăng xuất" nếu bạn muốn kết thúc phiên làm việc hiện tại.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                <a class="btn btn-primary" href="logout.php">Đăng xuất</a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script>
// Initialize DataTable for better user experience
$(document).ready(function() {
    // Initialize any Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
    // Handle sidebar toggle
    $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
        $(".sidebar").toggleClass("active");
        $(".content").toggleClass("active");
    });

    // Transaction Detail Modal Functionality
    $('#transactionDetailModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const uid = button.data('uid');
        const username = button.data('username');
        const email = button.data('email');
        const method = button.data('method');
        const coins = button.data('coins');
        const status = button.data('status');
        const time = button.data('time');
        
        // Set content in modal
        $('#modal-uid').text(uid);
        $('#modal-username').text(username);
        $('#modal-email').text(email);
        $('#modal-method').text(method);
        $('#modal-coins').text(coins);
        
        // Set status with appropriate color
        let statusHtml = '';
        if (status === 'thành công') {
            statusHtml = '<span class="badge bg-success"><span class="transaction-status status-success"></span>Thành công</span>';
        } else if (status === 'thất bại') {
            statusHtml = '<span class="badge bg-danger"><span class="transaction-status status-failed"></span>Thất bại</span>';
        } else {
            statusHtml = '<span class="badge bg-warning text-dark"><span class="transaction-status status-pending"></span>Đang xử lý</span>';
        }
        $('#modal-status').html(statusHtml);
        
        $('#modal-time').text(time);
        
        // Set print link
        $('#print-transaction').attr('href', 'print_transaction.php?id=' + id);
    });
    
    // Print transaction functionality
    $('#print-transaction').on('click', function(e) {
        e.preventDefault();
        
        // Create a printable version of the transaction details
        const printContent = document.createElement('div');
        printContent.innerHTML = `
            <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
                <h2 style="text-align: center;">Chi tiết giao dịch</h2>
                <hr style="border-top: 1px solid #ddd;">
                <div style="margin-bottom: 10px;">
                    <strong>Giao dịch ID:</strong> ${$('#modal-uid').text()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Người dùng:</strong> ${$('#modal-username').text()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Email:</strong> ${$('#modal-email').text()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Phương thức:</strong> ${$('#modal-method').text()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Số lượng Coin:</strong> ${$('#modal-coins').text()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Trạng thái:</strong> ${$('#modal-status').text().trim()}
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Thời gian:</strong> ${$('#modal-time').text()}
                </div>
                <hr style="border-top: 1px solid #ddd; margin-top: 20px;">
                <p style="text-align: center; font-size: 12px; color: #666;">
                    In ngày ${new Date().toLocaleDateString('vi-VN')} lúc ${new Date().toLocaleTimeString('vi-VN')}
                </p>
            </div>
        `;
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Chi tiết giao dịch</title></head><body>');
        printWindow.document.write(printContent.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    });
    
    // Add Payment Form Submission
    $('#submitPayment').on('click', function() {
        // Validate form
        const form = document.getElementById('addPaymentForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Get form data
        const userId = $('#user_id').val();
        const paymentMethod = $('#payment_method_add').val();
        const coins = $('#coins').val();
        const status = $('#status').val();
        
        // Send AJAX request
        $.ajax({
            url: 'ajax/add_payment.php',
            type: 'POST',
            data: {
                user_id: userId,
                payment_method: paymentMethod,
                coins: coins,
                status: status
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // Show success message and reload page
                        alert('Thêm giao dịch thành công!');
                        window.location.reload();
                    } else {
                        // Show error message
                        alert('Lỗi: ' + data.message);
                    }
                } catch (e) {
                    alert('Đã xảy ra lỗi khi xử lý dữ liệu!');
                }
            },
            error: function() {
                alert('Đã xảy ra lỗi khi thêm giao dịch!');
            }
        });
    });
    
    // Export Report Functionality
    $('#startExport').on('click', function() {
        // Validate form
        const form = document.getElementById('exportForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Get form data
        const format = $('#export_format').val();
        const dateFrom = $('#export_date_from').val();
        const dateTo = $('#export_date_to').val();
        const includeDetails = $('#include_details').is(':checked');
        
        // Create form and submit
        const exportForm = document.createElement('form');
        exportForm.method = 'POST';
        exportForm.action = 'export_transactions.php';
        exportForm.target = '_blank';
        
        // Add hidden fields
        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            exportForm.appendChild(input);
        };
        
        addField('format', format);
        addField('date_from', dateFrom);
        addField('date_to', dateTo);
        addField('include_details', includeDetails ? '1' : '0');
        
        // Submit form
        document.body.appendChild(exportForm);
        exportForm.submit();
        document.body.removeChild(exportForm);
        
        // Close modal
        $('#exportModal').modal('hide');
    });

    // Initialize Transactions Chart with Chart.js
    const transactionsChart = new Chart(
        document.getElementById('transactionsChart').getContext('2d'),
        {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_dates); ?>,
                datasets: [
                    {
                        label: 'Giao dịch',
                        data: <?php echo json_encode($chart_transactions); ?>,
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
                        label: 'Coin',
                        data: <?php echo json_encode($chart_coins); ?>,
                        backgroundColor: 'transparent',
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
                        fill: false,
                        yAxisID: 'coins'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: "rgba(0, 0, 0, 0.05)"
                        }
                    },
                    coins: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            display: false
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
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label === 'Coin') {
                                    return label + ': ' + context.raw.toLocaleString() + ' coin';
                                } else {
                                    return label + ': ' + context.raw.toLocaleString() + ' giao dịch';
                                }
                            }
                        }
                    }
                }
            }
        }
    );
    
    // Toggle between chart views
    $('.chart-type').on('click', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        
        if (type === 'transactions') {
            transactionsChart.data.datasets[0].hidden = false;
            transactionsChart.data.datasets[1].hidden = true;
        } else if (type === 'coins') {
            transactionsChart.data.datasets[0].hidden = true;
            transactionsChart.data.datasets[1].hidden = false;
        }
        
        transactionsChart.update();
    });

    // Payment Methods Pie Chart
    const paymentMethodsChart = new Chart(
        document.getElementById('paymentMethodsChart').getContext('2d'),
        {
            type: 'doughnut',
            data: {
                labels: <?php 
                    $method_labels = [];
                    foreach ($payment_methods_stats as $method) {
                        $method_labels[] = $method['phuongthuc'];
                    }
                    echo json_encode($method_labels); 
                ?>,
                datasets: [{
                    data: <?php 
                        $method_counts = [];
                        foreach ($payment_methods_stats as $method) {
                            $method_counts[] = $method['count'];
                        }
                        echo json_encode($method_counts); 
                    ?>,
                    backgroundColor: ['#9B59B6', '#9C27B0', '#007BFF', '#0079C1'],
                    hoverBackgroundColor: ['#8E44AD', '#7B1FA2', '#0056b3', '#005F93'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: "rgb(255, 255, 255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                const label = context.label;
                                const value = context.raw;
                                const percentage = Math.round(value / context.chart.getDatasetMeta(0).total * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        }
    );

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-fade').alert('close');
    }, 5000);

    // Load users for the add payment form
    $.ajax({
        url: 'ajax/get_users.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            const select = $('#user_id');
            
            if (data && data.length > 0) {
                data.forEach(function(user) {
                    select.append(new Option(user.tentaikhoan + ' (' + user.email + ')', user.id));
                });
            } else {
                select.append(new Option('Không có người dùng', ''));
            }
        },
        error: function() {
            console.error('Không thể tải danh sách người dùng');
        }
    });
    
    // Format number inputs with comma separators
    $('.coin-amount').each(function() {
        const num = parseInt($(this).text().replace(/,/g, ''), 10);
        if (!isNaN(num)) {
            $(this).text(num.toLocaleString('vi-VN'));
        }
    });
});

// Handle dynamic table sorting
$('#paymentsTable th').on('click', function() {
    const table = $(this).parents('table').eq(0);
    const rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
    this.asc = !this.asc;
    if (!this.asc){rows = rows.reverse();}
    for (let i = 0; i < rows.length; i++){table.append(rows[i]);}
});

function comparer(index) {
    return function(a, b) {
        const valA = getCellValue(a, index);
        const valB = getCellValue(b, index);
        return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB);
    };
}

function getCellValue(row, index) { 
    return $(row).children('td').eq(index).text(); 
}
    </script>
    </body>
    </html>