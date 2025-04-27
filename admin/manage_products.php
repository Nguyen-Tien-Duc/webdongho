<?php
// manage_products.php
session_start();
require_once "../database/db.php";

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ..../login/login1.php");
    exit;
}

// Xử lý thêm sản phẩm mới
if(isset($_POST['add_product'])) {
    $tenvatpham = $_POST['tenvatpham'];
    $mota = $_POST['mota'];
    $giatien = $_POST['giatien'];
    $loaisanpham = $_POST['loaisanpham'];
    $sll = $_POST['sll'];
    $chatlieu = $_POST['chatlieu'];
    $gioitinh = $_POST['gioitinh'];
    $thuonghieu = $_POST['thuonghieu']; // Added thuonghieu field
    
    // Tạo uid_vatpham ngẫu nhiên
    $uid_vatpham = "WD" . rand(10000, 99999);
    
    // Xử lý upload hình ảnh sản phẩm
    $url = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/products/";
        
        // Tạo thư mục nếu chưa tồn tại
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true); // tạo thư mục và các thư mục cha nếu cần
        }

        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = $uid_vatpham . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $url = "uploads/products/" . $new_filename;
        }
    }
    
    // Thêm vào cơ sở dữ liệu (now including thuonghieu)
    $stmt = $conn->prepare("INSERT INTO vatpham (tenvatpham, mota, giatien, loaisanpham, sll, uid_vatpham, url, chatlieu, gioitinh, thuonghieu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenvatpham, $mota, $giatien, $loaisanpham, $sll, $uid_vatpham, $url, $chatlieu, $gioitinh, $thuonghieu]);
    
    if($stmt->rowCount() > 0) {
        $success_msg = "Thêm sản phẩm thành công!";
    } else {
        $error_msg = "Có lỗi xảy ra khi thêm sản phẩm.";
    }
}

// Xử lý cập nhật sản phẩm
if(isset($_POST['update_product'])) {
    $id = $_POST['id'];
    $tenvatpham = $_POST['tenvatpham'];
    $mota = $_POST['mota'];
    $giatien = $_POST['giatien'];
    $loaisanpham = $_POST['loaisanpham'];
    $sll = $_POST['sll'];
    $chatlieu = $_POST['chatlieu'];
    $gioitinh = $_POST['gioitinh'];
    $thuonghieu = $_POST['thuonghieu']; // Added thuonghieu field
    
    $sql = "UPDATE vatpham SET tenvatpham = ?, mota = ?, giatien = ?, loaisanpham = ?, sll = ?, chatlieu = ?, gioitinh = ?, thuonghieu = ?";
    $params = [$tenvatpham, $mota, $giatien, $loaisanpham, $sll, $chatlieu, $gioitinh, $thuonghieu];
    
    // Xử lý update hình ảnh nếu có
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // Lấy uid_vatpham của sản phẩm cần update
        $stmt = $conn->prepare("SELECT uid_vatpham FROM vatpham WHERE id = ?");
        $stmt->execute([$id]);
        $uid_vatpham = $stmt->fetch(PDO::FETCH_ASSOC)['uid_vatpham'];
        
        $target_dir = "../uploads/products/";
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = $uid_vatpham . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $url = "uploads/products/" . $new_filename;
            $sql .= ", url = ?";
            $params[] = $url;
        }
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    if($stmt->rowCount() > 0) {
        $success_msg = "Cập nhật sản phẩm thành công!";
    } else {
        $info_msg = "Không có thay đổi nào được cập nhật.";
    }
}

// Xử lý xóa sản phẩm
if(isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Kiểm tra xem sản phẩm có đơn hàng nào không
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lichsuthanhtoan WHERE vatpham_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if($count > 0) {
        $error_msg = "Không thể xóa sản phẩm này vì đã có người đặt hàng!";
    } else {
        // Lấy thông tin hình ảnh để xóa file
        $stmt = $conn->prepare("SELECT url FROM vatpham WHERE id = ?");
        $stmt->execute([$id]);
        $url = $stmt->fetch(PDO::FETCH_ASSOC)['url'];
        
        // Xóa file hình ảnh nếu tồn tại
        if(!empty($url) && file_exists("../" . $url)) {
            unlink("../" . $url);
        }
        
        // Xóa sản phẩm khỏi database
        $stmt = $conn->prepare("DELETE FROM vatpham WHERE id = ?");
        $stmt->execute([$id]);
        
        if($stmt->rowCount() > 0) {
            $success_msg = "Xóa sản phẩm thành công!";
        } else {
            $error_msg = "Có lỗi xảy ra khi xóa sản phẩm.";
        }
    }
}

// Lấy danh sách tất cả sản phẩm
$stmt = $conn->query("SELECT * FROM vatpham ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm - Web Đồng Hồ</title>
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
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
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
            <a class="nav-link active" href="manage_products.php">
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
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
 <!-- Topbar -->
 <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

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
                <h1 class="h3 mb-0 text-gray-800">Quản Lý Sản Phẩm</h1>
                <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Thêm sản phẩm mới
                </button>
            </div>
            
            <?php if(isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($info_msg)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $info_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Products Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Danh Sách Sản Phẩm</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="productsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Hình ảnh</th>
                                    <th>Tên sản phẩm</th>
                                    <th>Loại</th>
                                    <th>Giá tiền</th>
                                    <th>Số lượng</th>
                                    <th>Chất liệu</th>
                                    <th>Giới tính</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if(!empty($product['url'])): ?>
                                        <img src="../<?php echo $product['url']; ?>" alt="<?php echo $product['tenvatpham']; ?>" class="product-img">
                                        <?php else: ?>
                                        <img src="../uploads/products/default.jpg" alt="Default Image" class="product-img">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['tenvatpham']; ?></td>
                                    <td><?php echo $product['loaisanpham']; ?></td>
                                    <td><?php echo number_format($product['giatien'], 0, ',', '.'); ?> đ</td>
                                    <td>
                                        <?php if($product['sll'] < 10): ?>
                                        <span class="text-danger"><?php echo $product['sll']; ?></span>
                                        <?php else: ?>
                                        <?php echo $product['sll']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['chatlieu']; ?></td>
                                    <td><?php echo $product['gioitinh']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-product" 
                                                data-id="<?php echo $product['id']; ?>"
                                                data-name="<?php echo $product['tenvatpham']; ?>"
                                                data-description="<?php echo htmlspecialchars($product['mota']); ?>"
                                                data-price="<?php echo $product['giatien']; ?>"
                                                data-category="<?php echo $product['loaisanpham']; ?>"
                                                data-quantity="<?php echo $product['sll']; ?>"
                                                data-material="<?php echo $product['chatlieu']; ?>"
                                                data-gender="<?php echo $product['gioitinh']; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editProductModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="manage_products.php?delete_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
        
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
    
<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Thêm Sản Phẩm Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tenvatpham" class="form-label">Tên sản phẩm</label>
                            <input type="text" class="form-control" id="tenvatpham" name="tenvatpham" required>
                        </div>
                        <div class="col-md-6">
                            <label for="giatien" class="form-label">Giá tiền</label>
                            <input type="number" class="form-control" id="giatien" name="giatien" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="loaisanpham" class="form-label">Loại sản phẩm</label>
                            <select class="form-select" id="loaisanpham" name="loaisanpham" required>
                                <option value="">Chọn loại</option>
                                <option value="cơ">Cơ</option>
                                <option value="quartz">Quartz</option>
                                <option value="điện tử">Điện tử</option>
                                <option value="thông minh">Thông minh</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="sll" class="form-label">Số lượng</label>
                            <input type="number" class="form-control" id="sll" name="sll" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="gioitinh" class="form-label">Giới tính</label>
                            <select class="form-select" id="gioitinh" name="gioitinh" required>
                                <option value="">Chọn giới tính</option>
                                <option value="Nam">Nam</option>
                                <option value="Nữ">Nữ</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="chatlieu" class="form-label">Chất liệu</label>
                            <select class="form-select" id="chatlieu" name="chatlieu" required>
                                <option value="">Chọn chất liệu</option>
                                <option value="kim loại">Kim loại</option>
                                <option value="da">Da</option>
                                <option value="silicone">Silicone</option>
                                <option value="vải và nato">Vải và NATO</option>
                                <option value="milamese">Milanese</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="thuonghieu" class="form-label">Thương hiệu</label>
                            <select class="form-select" id="thuonghieu" name="thuonghieu">
                                <option value="">Chọn thương hiệu</option>
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
                    </div>
                    <div class="mb-3">
                        <label for="mota" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="mota" name="mota" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Hình ảnh sản phẩm</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Thêm sản phẩm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductModalLabel">Cập Nhật Sản Phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_tenvatpham" class="form-label">Tên sản phẩm</label>
                            <input type="text" class="form-control" id="edit_tenvatpham" name="tenvatpham" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_giatien" class="form-label">Giá tiền</label>
                            <input type="number" class="form-control" id="edit_giatien" name="giatien" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_loaisanpham" class="form-label">Loại sản phẩm</label>
                            <select class="form-select" id="edit_loaisanpham" name="loaisanpham" required>
                                <option value="">Chọn loại</option>
                                <option value="cơ">Cơ</option>
                                <option value="quartz">Quartz</option>
                                <option value="điện tử">Điện tử</option>
                                <option value="thông minh">Thông minh</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_sll" class="form-label">Số lượng</label>
                            <input type="number" class="form-control" id="edit_sll" name="sll" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_gioitinh" class="form-label">Giới tính</label>
                            <select class="form-select" id="edit_gioitinh" name="gioitinh" required>
                                <option value="">Chọn giới tính</option>
                                <option value="Nam">Nam</option>
                                <option value="Nữ">Nữ</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_chatlieu" class="form-label">Chất liệu</label>
                            <select class="form-select" id="edit_chatlieu" name="chatlieu" required>
                                <option value="">Chọn chất liệu</option>
                                <option value="kim loại">Kim loại</option>
                                <option value="da">Da</option>
                                <option value="silicone">Silicone</option>
                                <option value="vải và nato">Vải và NATO</option>
                                <option value="milamese">Milanese</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_thuonghieu" class="form-label">Thương hiệu</label>
                            <select class="form-select" id="edit_thuonghieu" name="thuonghieu">
                                <option value="">Chọn thương hiệu</option>
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
                    </div>
                    <div class="mb-3">
                        <label for="edit_mota" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_mota" name="mota" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_image" class="form-label">Hình ảnh sản phẩm (để trống nếu không muốn thay đổi)</label>
                        <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="update_product" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>
    
    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Bạn muốn đăng xuất?</h5>
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
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('active');
        });
        
        document.getElementById('sidebarToggleTop').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.content').classList.toggle('active');
        });
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#productsTable').DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Vietnamese.json"
                },
                order: [[0, 'desc']]
            });
            
            // Fill edit modal with product data
            $('.edit-product').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const price = $(this).data('price');
                const category = $(this).data('category');
                const quantity = $(this).data('quantity');
                const material = $(this).data('material');
                const gender = $(this).data('gender');
                
                $('#edit_id').val(id);
                $('#edit_tenvatpham').val(name);
                $('#edit_mota').val(description);
                $('#edit_giatien').val(price);
                $('#edit_loaisanpham').val(category);
                $('#edit_sll').val(quantity);
                $('#edit_chatlieu').val(material);
                $('#edit_gioitinh').val(gender);
            });
        });
    </script>
</body>
</html>