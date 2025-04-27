<?php
// manage_orders.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}
require_once "../database/db.php";


// Xử lý cập nhật trạng thái đơn hàng nếu có yêu cầu
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE lichsuthanhtoan SET trangthai = :trangthai WHERE id = :id");
        $stmt->bindParam(':trangthai', $new_status);
        $stmt->bindParam(':id', $order_id);
        $stmt->execute();
        
        $success_message = "Cập nhật trạng thái đơn hàng thành công!";
    } catch (PDOException $e) {
        $error_message = "Lỗi cập nhật: " . $e->getMessage();
    }
}

// Xử lý xóa đơn hàng nếu có yêu cầu
if (isset($_POST['delete_order']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM lichsuthanhtoan WHERE id = :id");
        $stmt->bindParam(':id', $order_id);
        $stmt->execute();
        
        $success_message = "Xóa đơn hàng thành công!";
    } catch (PDOException $e) {
        $error_message = "Lỗi xóa đơn hàng: " . $e->getMessage();
    }
}

// Xử lý bộ lọc
$where_clause = "1=1"; // Always true condition to start with
$params = [];

if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
    $where_clause .= " AND l.trangthai = :trangthai";
    $params[':trangthai'] = $_GET['filter_status'];
}

if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
    $where_clause .= " AND DATE(l.thoigian) >= :date_from";
    $params[':date_from'] = $_GET['filter_date_from'];
}

if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
    $where_clause .= " AND DATE(l.thoigian) <= :date_to";
    $params[':date_to'] = $_GET['filter_date_to'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $where_clause .= " AND (t.tentaikhoan LIKE :search OR l.id LIKE :search_id)";
    $params[':search'] = $search_term;
    $params[':search_id'] = $_GET['search']; // Exact match for ID
}

// Truy vấn lấy danh sách đơn hàng với phân trang
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$query = "
    SELECT 
        l.id,  
        t.id as user_id,
        t.tentaikhoan,
        CASE 
            WHEN l.vatpham_id IS NOT NULL THEN v.tenvatpham
            WHEN l.phukien_id IS NOT NULL THEN p.ten
            ELSE 'Unknown'
        END as product_name,
        CASE 
            WHEN l.vatpham_id IS NOT NULL THEN 'Đồng hồ'
            WHEN l.phukien_id IS NOT NULL THEN 'Phụ kiện'
            ELSE 'Unknown'
        END as product_type,
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
    WHERE $where_clause
    ORDER BY l.thoigian DESC
    LIMIT :offset, :limit
";

// Truy vấn đếm tổng số đơn hàng để phân trang
$count_query = "
    SELECT COUNT(*) as total
    FROM lichsuthanhtoan l
    JOIN taikhoan t ON l.taikhoan_id = t.id
    LEFT JOIN vatpham v ON l.vatpham_id = v.id
    LEFT JOIN phukien p ON l.phukien_id = p.id
    WHERE $where_clause
";

try {
    // Thực hiện truy vấn đếm
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    // Thực hiện truy vấn chính
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi truy vấn: " . $e->getMessage();
    $orders = [];
    $total_pages = 0;
}

// Tính tổng doanh thu từ các đơn hàng đã thanh toán
$revenue_query = "
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
";

try {
    $stmt = $conn->query($revenue_query);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
} catch (PDOException $e) {
    $total_revenue = 0;
}

// Đếm đơn hàng theo trạng thái
$stats_query = "
    SELECT 
        trangthai, 
        COUNT(*) as count
    FROM lichsuthanhtoan
    GROUP BY trangthai
";

try {
    $stmt = $conn->query($stats_query);
    $order_stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $order_stats[$row['trangthai']] = $row['count'];
    }
} catch (PDOException $e) {
    $order_stats = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Hàng - Web Đồng Hồ</title>
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
        
        .text-primary { color: var(--primary-color) !important; }
        .text-success { color: var(--success-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        
        .bg-primary { background-color: var(--primary-color) !important; }
        .bg-success { background-color: var(--success-color) !important; }
        .bg-info { background-color: var(--info-color) !important; }
        .bg-warning { background-color: var(--warning-color) !important; }
        .bg-danger { background-color: var(--danger-color) !important; }
        
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
        
        /* Filter panel styles */
        .filter-panel {
            background-color: #fff;
            border-radius: 0.35rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Pagination styles */
        .pagination {
            margin-top: 1rem;
            justify-content: center;
        }
        
        .page-link {
            color: var(--primary-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Status badge styles */
        .badge-paid {
            background-color: var(--success-color);
        }
        
        .badge-pending {
            background-color: var(--warning-color);
        }
        
        /* Action buttons */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
            <a class="nav-link active" href="manage_orders.php">
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
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Search Form -->
            <form class="d-none d-sm-inline-block me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search" action="manage_orders.php" method="GET">
                <div class="input-group">
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="Tìm kiếm đơn hàng..." aria-label="Search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button class="btn btn-primary" type="submit">
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
                        <form class="form-inline me-auto w-100 navbar-search" action="manage_orders.php" method="GET">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control bg-light border-0 small" placeholder="Tìm kiếm đơn hàng..." aria-label="Search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search fa-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </li>
                
                <!-- Nav Item - Alerts -->
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
                
                <div class="topbar-divider d-none d-sm-block"></div>
                
                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small">Admin</span>
                        <img class="img-profile rounded-circle" src="https://source.unsplash.com/QAB-WJcbgJk/60x60" style="width: 32px; height: 32px;">
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
        <!-- End of Topbar -->
        
        <!-- Begin Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Quản Lý Đơn Hàng</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-download fa-sm text-white-50"></i> Xuất báo cáo
                </a>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Content Row - Statistics Cards -->
            <div class="row">
                <!-- Tổng đơn hàng -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng đơn hàng
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($total_items); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Đã thanh toán -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Đã thanh toán
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($order_stats['đã thanh toán'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chưa thanh toán -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Chưa thanh toán
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($order_stats['chưa thanh toán'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tổng doanh thu -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Tổng doanh thu
                                        </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($total_revenue); ?> VNĐ
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Panel -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Bộ lọc đơn hàng</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="filterDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="filterDropdown">
                            <div class="dropdown-header">Tùy chọn:</div>
                            <a class="dropdown-item" href="manage_orders.php">Xóa bộ lọc</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportModal">Xuất dữ liệu</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="manage_orders.php" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="filter_status" class="form-label">Trạng thái</label>
                            <select class="form-select" id="filter_status" name="filter_status">
                                <option value="">Tất cả</option>
                                <option value="đã thanh toán" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'đã thanh toán') ? 'selected' : ''; ?>>Đã thanh toán</option>
                                <option value="chưa thanh toán" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'chưa thanh toán') ? 'selected' : ''; ?>>Chưa thanh toán</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_date_from" class="form-label">Từ ngày</label>
                            <input type="date" class="form-control" id="filter_date_from" name="filter_date_from" value="<?php echo isset($_GET['filter_date_from']) ? htmlspecialchars($_GET['filter_date_from']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="filter_date_to" class="form-label">Đến ngày</label>
                            <input type="date" class="form-control" id="filter_date_to" name="filter_date_to" value="<?php echo isset($_GET['filter_date_to']) ? htmlspecialchars($_GET['filter_date_to']) : ''; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Lọc</button>
                            <a href="manage_orders.php" class="btn btn-secondary">Đặt lại</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Order List Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách đơn hàng</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="ordersTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Khách hàng</th>
                                    <th>Sản phẩm</th>
                                    <th>Loại SP</th>
                                    <th>Số lượng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($orders) > 0): ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['id']; ?></td>
                                        <td>
                                            <a href="view_user.php?id=<?php echo $order['user_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($order['tentaikhoan']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['product_type']); ?></td>
                                        <td><?php echo $order['sll']; ?></td>
                                        <td><?php echo number_format($order['total_price']); ?> VNĐ</td>
                                        <td>
                                            <?php if ($order['trangthai'] === 'đã thanh toán'): ?>
                                                <span class="badge bg-success">Đã thanh toán</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Chưa thanh toán</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['thoigian'])); ?></td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- Nút xem chi tiết -->
                                                <button type="button" class="btn btn-info btn-sm me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewOrderModal" 
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-user="<?php echo htmlspecialchars($order['tentaikhoan']); ?>"
                                                    data-order-product="<?php echo htmlspecialchars($order['product_name']); ?>"
                                                    data-order-type="<?php echo htmlspecialchars($order['product_type']); ?>"
                                                    data-order-quantity="<?php echo $order['sll']; ?>"
                                                    data-order-price="<?php echo number_format($order['total_price']); ?>"
                                                    data-order-status="<?php echo $order['trangthai']; ?>"
                                                    data-order-date="<?php echo date('d/m/Y H:i', strtotime($order['thoigian'])); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <!-- Nút cập nhật trạng thái -->
                                                <button type="button" class="btn btn-primary btn-sm me-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStatusModal" 
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-status="<?php echo $order['trangthai']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <!-- Nút xóa đơn hàng -->
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteOrderModal" 
                                                    data-order-id="<?php echo $order['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Không có đơn hàng nào</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Phân trang -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo isset($_GET['filter_status']) ? '&filter_status='.$_GET['filter_status'] : ''; ?><?php echo isset($_GET['filter_date_from']) ? '&filter_date_from='.$_GET['filter_date_from'] : ''; ?><?php echo isset($_GET['filter_date_to']) ? '&filter_date_to='.$_GET['filter_date_to'] : ''; ?><?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['filter_status']) ? '&filter_status='.$_GET['filter_status'] : ''; ?><?php echo isset($_GET['filter_date_from']) ? '&filter_date_from='.$_GET['filter_date_from'] : ''; ?><?php echo isset($_GET['filter_date_to']) ? '&filter_date_to='.$_GET['filter_date_to'] : ''; ?><?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo isset($_GET['filter_status']) ? '&filter_status='.$_GET['filter_status'] : ''; ?><?php echo isset($_GET['filter_date_from']) ? '&filter_date_from='.$_GET['filter_date_from'] : ''; ?><?php echo isset($_GET['filter_date_to']) ? '&filter_date_to='.$_GET['filter_date_to'] : ''; ?><?php echo isset($_GET['search']) ? '&search='.$_GET['search'] : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
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
                <span>Copyright &copy; Web Đồng Hồ 2025</span>
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
                    <h5 class="modal-title" id="exampleModalLabel">Đăng xuất?</h5>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Chọn "Đăng xuất" nếu bạn muốn kết thúc phiên làm việc.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <a class="btn btn-primary" href="logout.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Order Modal -->
    <div class="modal fade" id="viewOrderModal" tabindex="-1" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewOrderModalLabel">Chi tiết đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">ID đơn hàng: <span id="modal-order-id"></span></h6>
                    </div>
                    <div class="mb-3">
                        <p class="mb-1"><strong>Khách hàng:</strong> <span id="modal-order-user"></span></p>
                        <p class="mb-1"><strong>Sản phẩm:</strong> <span id="modal-order-product"></span></p>
                        <p class="mb-1"><strong>Loại sản phẩm:</strong> <span id="modal-order-type"></span></p>
                        <p class="mb-1"><strong>Số lượng:</strong> <span id="modal-order-quantity"></span></p>
                        <p class="mb-1"><strong>Tổng tiền:</strong> <span id="modal-order-price"></span> VNĐ</p>
                        <p class="mb-1"><strong>Trạng thái:</strong> <span id="modal-order-status"></span></p>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <span id="modal-order-date"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Cập nhật trạng thái đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_orders.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="update-order-id">
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Trạng thái mới</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="đã thanh toán">Đã thanh toán</option>
                                <option value="chưa thanh toán">Chưa thanh toán</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Order Modal -->
    <div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteOrderModalLabel">Xác nhận xóa đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa đơn hàng này? Hành động này không thể hoàn tác.</p>
                    <form action="manage_orders.php" method="POST" id="deleteOrderForm">
                        <input type="hidden" name="order_id" id="delete-order-id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="deleteOrderForm" name="delete_order" class="btn btn-danger">Xóa</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Xuất dữ liệu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="export_orders.php" method="GET">
                        <div class="mb-3">
                            <label for="export_format" class="form-label">Định dạng</label>
                            <select class="form-select" id="export_format" name="format">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                                <option value="pdf">PDF (.pdf)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="export_date_from" class="form-label">Từ ngày</label>
                            <input type="date" class="form-control" id="export_date_from" name="date_from">
                        </div>
                        <div class="mb-3">
                            <label for="export_date_to" class="form-label">Đến ngày</label>
                            <input type="date" class="form-control" id="export_date_to" name="date_to">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="exportForm" class="btn btn-primary">Xuất</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap core JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    
    <!-- Custom scripts for all pages-->
    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('active');
        });
        
        // Mobile view sidebar toggle
        document.getElementById('sidebarToggleTop').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('active');
        });
        
        // View Order Modal
        document.getElementById('viewOrderModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const orderId = button.getAttribute('data-order-id');
            const orderUser = button.getAttribute('data-order-user');
            const orderProduct = button.getAttribute('data-order-product');
            const orderType = button.getAttribute('data-order-type');
            const orderQuantity = button.getAttribute('data-order-quantity');
            const orderPrice = button.getAttribute('data-order-price');
            const orderStatus = button.getAttribute('data-order-status');
            const orderDate = button.getAttribute('data-order-date');
            
            // Set modal data
            document.getElementById('modal-order-id').textContent = orderId;
            document.getElementById('modal-order-user').textContent = orderUser;
            document.getElementById('modal-order-product').textContent = orderProduct;
            document.getElementById('modal-order-type').textContent = orderType;
            document.getElementById('modal-order-quantity').textContent = orderQuantity;
            document.getElementById('modal-order-price').textContent = orderPrice;
            document.getElementById('modal-order-status').textContent = orderStatus === 'đã thanh toán' ? 'Đã thanh toán' : 'Chưa thanh toán';
            document.getElementById('modal-order-date').textContent = orderDate;
        });
        
        // Update Status Modal
        document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const orderId = button.getAttribute('data-order-id');
            const orderStatus = button.getAttribute('data-order-status');
            
            // Set form values
            document.getElementById('update-order-id').value = orderId;
            document.getElementById('new_status').value = orderStatus;
        });
        
        // Delete Order Modal
        document.getElementById('deleteOrderModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const orderId = button.getAttribute('data-order-id');
            
            // Set form value
            document.getElementById('delete-order-id').value = orderId;
        });
    </script>
</body>
</html>