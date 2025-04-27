<?php
session_start();
require_once "../database/db.php";

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}


if (isset($_POST['action'])) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($_POST['action'] == 'ban' && $user_id > 0) {
        $stmt = $conn->prepare("UPDATE taikhoan SET status = 'banned' WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Đã khóa tài khoản thành công!";
    }
    if ($_POST['action'] == 'unban' && $user_id > 0) {
        $stmt = $conn->prepare("UPDATE taikhoan SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Đã mở khóa tài khoản thành công!";
    }
    if ($_POST['action'] == 'delete' && $user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM taikhoan WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Đã xóa tài khoản thành công!";
    }
    
    // Xử lý thêm coin
    if ($_POST['action'] == 'add_coin' && $user_id > 0 && isset($_POST['coin_amount'])) {
        $coin_amount = intval($_POST['coin_amount']);
        if ($coin_amount > 0) {
            $stmt = $conn->prepare("UPDATE taikhoan SET coin = coin + ? WHERE id = ?");
            $stmt->execute([$coin_amount, $user_id]);
            $message = "Đã thêm $coin_amount coin vào tài khoản thành công!";
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
$params = [];

if (!empty($search)) {
    $search_condition = "WHERE tentaikhoan LIKE ? OR email LIKE ? OR fullname LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$items_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$count_query = "SELECT COUNT(*) as total FROM taikhoan $search_condition";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $items_per_page);

// Lấy danh sách tài khoản
$user_query = "SELECT * FROM taikhoan $search_condition ORDER BY thoigian DESC LIMIT $offset, $items_per_page";
$stmt = $conn->prepare($user_query);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thống kê
$stmt = $conn->query("SELECT COUNT(*) as total FROM taikhoan WHERE status = 'active'");
$active_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM taikhoan WHERE status = 'banned'");
$banned_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT login_method, COUNT(*) as total FROM taikhoan GROUP BY login_method");
$login_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT DATE(thoigian) as date, COUNT(*) as count FROM taikhoan WHERE thoigian >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(thoigian) ORDER BY date ASC");
$registration_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Chuyển đổi dữ liệu đăng ký để sử dụng với Chart.js
$dates = [];
$counts = [];
foreach ($registration_data as $data) {
    $dates[] = date('d/m', strtotime($data['date']));
    $counts[] = $data['count'];
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tài Khoản - Web Đồng Hồ</title>
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
        
        .card-active {
            border-left-color: var(--success-color);
        }
        
        .card-banned {
            border-left-color: var(--danger-color);
        }
        
        .card-total {
            border-left-color: var(--primary-color);
        }
        
        .card-new {
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
        
        .avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .dropdown-toggle::after {
            display: none;
        }
        
        .chart-container {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        .user-table img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 1rem;
        }
        
        .action-buttons .btn {
            margin-right: 5px;
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
            <a class="nav-link active" href="manage_users.php">
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
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="toggle-sidebar me-3">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Search Form -->
            <form class="d-none d-sm-inline-block me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search search-form" method="GET" action="manage_users.php">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0" placeholder="Tìm kiếm tài khoản..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn" type="submit">
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
                        <form class="form-inline me-auto w-100 navbar-search" method="GET" action="manage_users.php">
                            <div class="input-group">
                                <input type="text" class="form-control bg-light border-0 small" placeholder="Tìm kiếm tài khoản..." name="search" value="<?php echo htmlspecialchars($search); ?>">
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
                                <span class="fw-bold">Báo cáo người dùng mới đã sẵn sàng!</span>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">14/04/2025</div>
                                5 tài khoản mới đã đăng ký hôm nay
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-danger">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">13/04/2025</div>
                                Có báo cáo spam từ người dùng!
                            </div>
                        </a>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem tất cả thông báo</a>
                    </div>
                </li>
                
                <!-- Nav Item - Messages -->
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-envelope fa-fw"></i>
                        <!-- Counter - Messages -->
                        <span class="badge bg-danger badge-counter">7</span>
                    </a>
                    <!-- Dropdown - Messages -->
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
                                <div class="text-truncate">Tôi quên mật khẩu tài khoản, có thể giúp đổi lại không?</div>
                                <div class="small text-gray-500">Nguyễn Văn A · 58m</div>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="dropdown-list-image me-3">
                                <img class="rounded-circle" src="https://source.unsplash.com/cssvEZacHvQ/60x60" alt="...">
                                <div class="status-indicator"></div>
                            </div>
                            <div>
                                <div class="text-truncate">Tài khoản của tôi đang bị khóa, làm sao để mở lại?</div>
                                <div class="small text-gray-500">Trần Thị B · 1d</div>
                            </div>
                        </a>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="dropdown-list-image me-3">
                                <img class="rounded-circle" src="https://source.unsplash.com/gpLvSyTKnT8/60x60" alt="...">
                                <div class="status-indicator bg-warning"></div>
                            </div>
                            <div>
                                <div class="text-truncate">Tôi muốn thay đổi email đăng nhập</div>
                                <div class="small text-gray-500">Lê Văn C · 2d</div>
                            </div>
                        </a>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem thêm tin nhắn</a>
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
                <h1 class="h3 mb-0 text-gray-800">Quản lý tài khoản</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus fa-sm text-white-50"></i> Thêm tài khoản mới
                </a>
            </div>
            
            <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Content Row - Stats Cards -->
            <div class="row">
                <!-- Tổng số tài khoản -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-total shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng tài khoản
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_users); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tài khoản đang hoạt động -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-active shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Đang hoạt động
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($active_users); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tài khoản bị khóa -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-banned shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Bị khóa
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($banned_users); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-slash fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tài khoản mới trong 24h -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card card-new shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Mới trong 24h
                                    </div>
                                    <?php
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM taikhoan WHERE thoigian >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $new_users_24h = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($new_users_24h); ?></div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
            
<!-- Content Row - Charts -->
<div class="row">
    <!-- User Registration Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Đăng ký tài khoản trong 7 ngày qua</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="userRegistrationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Method Chart -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Phương thức đăng nhập</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="loginMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User List -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Danh sách tài khoản</h6>
        <?php if (!empty($search)): ?>
        <div class="mt-2">
            <span class="text-muted">Kết quả tìm kiếm cho: <strong><?php echo htmlspecialchars($search); ?></strong></span>
            <a href="manage_users.php" class="ms-2 btn btn-sm btn-outline-secondary">Xóa tìm kiếm</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered user-table" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tài khoản</th>
                        <th>Email</th>
                        <th>Họ tên</th>
                        <th>Coin</th>
                        <th>Đăng nhập qua</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng ký</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="<?php echo !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : '../assets/img/default-avatar.png'; ?>" class="me-2">
                                <?php echo htmlspecialchars($user['tentaikhoan']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['fullname'] ?? 'Chưa cập nhật'); ?></td>
                        <td><?php echo number_format($user['coin']); ?></td>
                        <td>
                            <?php
                            switch ($user['login_method']) {
                                case 'normal':
                                    echo '<span class="badge bg-primary">Thông thường</span>';
                                    break;
                                case 'google':
                                    echo '<span class="badge bg-danger">Google</span>';
                                    break;
                                case 'facebook':
                                    echo '<span class="badge bg-info">Facebook</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-secondary">Không xác định</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge bg-success">Đang hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Đã bị khóa</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($user['thoigian'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewUserModal" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['tentaikhoan']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-fullname="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>"
                                    data-coin="<?php echo $user['coin']; ?>"
                                    data-method="<?php echo $user['login_method']; ?>"
                                    data-status="<?php echo $user['status']; ?>"
                                    data-date="<?php echo date('d/m/Y H:i', strtotime($user['thoigian'])); ?>"
                                    data-avatar="<?php echo !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : '../assets/img/default-avatar.png'; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#addCoinModal" data-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['tentaikhoan']); ?>">
                                    <i class="fas fa-coins"></i>
                                </button>
                                <?php if ($user['status'] == 'active'): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="ban">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="unban">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Bạn có chắc chắn muốn mở khóa tài khoản này?')">
                                            <i class="fas fa-unlock"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Hành động này không thể hoàn tác.')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
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
    </div>
    <!-- End of Content Wrapper -->
    
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
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">Chọn "Đăng xuất" bên dưới nếu bạn đã sẵn sàng kết thúc phiên làm việc hiện tại.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <a class="btn btn-primary" href="logout.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">Chi tiết tài khoản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <img id="userAvatar" src="" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p><strong>ID:</strong> <span id="userId"></span></p>
                            <p><strong>Tên tài khoản:</strong> <span id="userName"></span></p>
                            <p><strong>Email:</strong> <span id="userEmail"></span></p>
                            <p><strong>Họ tên:</strong> <span id="userFullname"></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><strong>Coin:</strong> <span id="userCoin"></span></p>
                            <p><strong>Phương thức đăng nhập:</strong> <span id="userMethod"></span></p>
                            <p><strong>Trạng thái:</strong> <span id="userStatus"></span></p>
                            <p><strong>Ngày đăng ký:</strong> <span id="userDate"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Coin Modal -->
    <div class="modal fade" id="addCoinModal" tabindex="-1" aria-labelledby="addCoinModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCoinModalLabel">Thêm coin cho tài khoản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_coin">
                        <input type="hidden" name="user_id" id="coinUserId">
                        
                        <div class="mb-3">
                            <label for="coinUsername" class="form-label">Tài khoản</label>
                            <input type="text" class="form-control" id="coinUsername" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="coinAmount" class="form-label">Số lượng coin</label>
                            <input type="number" class="form-control" id="coinAmount" name="coin_amount" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm coin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Thêm tài khoản mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="add_user.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="newUsername" class="form-label">Tên tài khoản</label>
                            <input type="text" class="form-control" id="newUsername" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" id="newPassword" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newEmail" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newFullname" class="form-label">Họ tên</label>
                            <input type="text" class="form-control" id="newFullname" name="fullname">
                        </div>
                        
                        <div class="mb-3">
                            <label for="newCoin" class="form-label">Coin</label>
                            <input type="number" class="form-control" id="newCoin" name="coin" value="0" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm tài khoản</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap core JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    
    <!-- Page level plugins -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
                $("body").toggleClass("sidebar-toggled");
                $(".sidebar").toggleClass("toggled");
                if ($(".sidebar").hasClass("toggled")) {
                    $('.sidebar .collapse').collapse('hide');
                }
            });
            
            // User Registration Chart
            var ctx = document.getElementById('userRegistrationChart').getContext('2d');
            var userRegistrationChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: 'Đăng ký mới',
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        tension: 0.3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Login Methods Chart
            var loginCtx = document.getElementById('loginMethodChart').getContext('2d');
            var loginData = {
                labels: ['Thông thường', 'Google', 'Facebook'],
                datasets: [{
                    data: [
                        <?php 
                        $normalCount = 0;
                        $googleCount = 0;
                        $facebookCount = 0;
                        
                        foreach ($login_methods as $method) {
                            if ($method['login_method'] == 'normal') $normalCount = $method['total'];
                            if ($method['login_method'] == 'google') $googleCount = $method['total'];
                            if ($method['login_method'] == 'facebook') $facebookCount = $method['total'];
                        }
                        
                        echo "$normalCount, $googleCount, $facebookCount";
                        ?>
                    ],
                    backgroundColor: ['#4e73df', '#e74a3b', '#36b9cc'],
                    hoverBackgroundColor: ['#2e59d9', '#be2617', '#2c9faf'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            };
            
            var loginMethodChart = new Chart(loginCtx, {
                type: 'doughnut',
                data: loginData,
                options: {
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // View User Modal
            $('#viewUserModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var username = button.data('username');
                var email = button.data('email');
                var fullname = button.data('fullname');
                var coin = button.data('coin');
                var method = button.data('method');
                var status = button.data('status');
                var date = button.data('date');
                var avatar = button.data('avatar');
                
                var modal = $(this);
                modal.find('#userId').text(id);
                modal.find('#userName').text(username);
                modal.find('#userEmail').text(email);
                modal.find('#userFullname').text(fullname || 'Chưa cập nhật');
                modal.find('#userCoin').text(new Intl.NumberFormat().format(coin));
                
                // Format method
                var methodText;
                switch(method) {
                    case 'normal':
                        methodText = '<span class="badge bg-primary">Thông thường</span>';
                        break;
                    case 'google':
                        methodText = '<span class="badge bg-danger">Google</span>';
                        break;
                    case 'facebook':
                        methodText = '<span class="badge bg-info">Facebook</span>';
                        break;
                    default:
                        methodText = '<span class="badge bg-secondary">Không xác định</span>';
                }
                modal.find('#userMethod').html(methodText);
                
                // Format status
                var statusText = status === 'active' ? 
                    '<span class="badge bg-success">Đang hoạt động</span>' : 
                    '<span class="badge bg-danger">Đã bị khóa</span>';
                modal.find('#userStatus').html(statusText);
                
                modal.find('#userDate').text(date);
                modal.find('#userAvatar').attr('src', avatar || '../assets/img/default-avatar.png');
            });
            
            // Add Coin Modal
            $('#addCoinModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var username = button.data('username');
                
                var modal = $(this);
                modal.find('#coinUserId').val(id);
                modal.find('#coinUsername').val(username);
                modal.find('#coinAmount').val('');
            });
        });
    </script>
</body>
</html>