<?php
session_start();
require_once "../database/db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login/login1.php");
    exit;
}

// Configure upload directory
$upload_dir = "../uploads/products/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle accessory deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Get the image URL before deleting
    $stmt = $conn->prepare("SELECT url FROM phukien WHERE id = ?");
    $stmt->execute([$delete_id]);
    $image_url = $stmt->fetch(PDO::FETCH_COLUMN);
    
    try {
        // Delete the image file if it exists and is in our uploads directory
        if ($image_url && strpos($image_url, '../uploads/products/') === 0) {
            if (file_exists($image_url)) {
                unlink($image_url);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM phukien WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success_message = "Phụ kiện đã được xóa thành công!";
    } catch (PDOException $e) {
        $error_message = "Lỗi khi xóa phụ kiện: " . $e->getMessage();
    }
}

// Handle change accessory status (active/inactive)
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $toggle_id = $_GET['toggle_status'];
    
    try {
        // First get current status
        $stmt = $conn->prepare("SELECT sll FROM phukien WHERE id = ?");
        $stmt->execute([$toggle_id]);
        $current_status = (int)$stmt->fetch(PDO::FETCH_COLUMN);
        
        // Toggle status
        $new_status = ($current_status === 0) ? 10 : 0;
        
        $stmt = $conn->prepare("UPDATE phukien SET sll = ? WHERE id = ?");
        $stmt->execute([$new_status, $toggle_id]);
        
        $status_text = ($new_status === 0) ? "ngừng bán" : "bắt đầu bán";
        $success_message = "Phụ kiện đã được $status_text thành công!";
    } catch (PDOException $e) {
        $error_message = "Lỗi khi thay đổi trạng thái phụ kiện: " . $e->getMessage();
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_items'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_items = $_POST['selected_items'];
    
    if (!empty($selected_items)) {
        try {
            if ($bulk_action === 'delete') {
                // Delete selected accessories
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                
                // First, get the image URLs
                $stmt = $conn->prepare("SELECT id, url FROM phukien WHERE id IN ($placeholders)");
                $stmt->execute($selected_items);
                $items_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Delete images
                foreach ($items_to_delete as $item) {
                    if ($item['url'] && strpos($item['url'], '../uploads/products/') === 0) {
                        if (file_exists($item['url'])) {
                            unlink($item['url']);
                        }
                    }
                }
                
                // Delete from database
                $stmt = $conn->prepare("DELETE FROM phukien WHERE id IN ($placeholders)");
                $stmt->execute($selected_items);
                
                $success_message = "Đã xóa " . count($selected_items) . " phụ kiện thành công!";
            } elseif ($bulk_action === 'activate') {
                // Activate selected accessories
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE phukien SET sll = 10 WHERE id IN ($placeholders) AND sll = 0");
                $stmt->execute($selected_items);
                
                $success_message = "Đã kích hoạt " . count($selected_items) . " phụ kiện thành công!";
            } elseif ($bulk_action === 'deactivate') {
                // Deactivate selected accessories
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE phukien SET sll = 0 WHERE id IN ($placeholders) AND sll > 0");
                $stmt->execute($selected_items);
                
                $success_message = "Đã vô hiệu hóa " . count($selected_items) . " phụ kiện thành công!";
            }
        } catch (PDOException $e) {
            $error_message = "Lỗi khi thực hiện hành động hàng loạt: " . $e->getMessage();
        }
    } else {
        $error_message = "Vui lòng chọn ít nhất một phụ kiện để thực hiện hành động.";
    }
}

// Handle add/edit accessory form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_accessory'])) {
    $accessory_id = isset($_POST['id']) ? $_POST['id'] : null;
    $ten = $_POST['ten'];
    $loaiphukien = $_POST['loaiphukien'];
    $giatien = $_POST['giatien'];
    $mota = $_POST['mota'];
    $sll = $_POST['sll'];
    $uid_phukien = isset($_POST['uid_phukien']) ? $_POST['uid_phukien'] : 'PK' . rand(1000, 9999);
    
    // Get current image URL if editing
    $current_image_url = '';
    if ($accessory_id) {
        $stmt = $conn->prepare("SELECT url FROM phukien WHERE id = ?");
        $stmt->execute([$accessory_id]);
        $current_image_url = $stmt->fetch(PDO::FETCH_COLUMN);
    }
    
    // Handle image upload
    $url = $current_image_url; // Default to current image if no new upload
    
    if (isset($_FILES['accessory_image']) && $_FILES['accessory_image']['error'] === UPLOAD_ERR_OK) {
        $temp_name = $_FILES['accessory_image']['tmp_name'];
        $file_name = $_FILES['accessory_image']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Only allow certain file extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Generate unique filename
            $new_file_name = 'accessory_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($temp_name, $upload_path)) {
                // Delete old image if exists and we're updating
                if ($accessory_id && $current_image_url && strpos($current_image_url, '../uploads/products/') === 0) {
                    if (file_exists($current_image_url)) {
                        unlink($current_image_url);
                    }
                }
                
                $url = $upload_path;
            } else {
                $error_message = "Không thể tải lên hình ảnh. Vui lòng thử lại.";
            }
        } else {
            $error_message = "Chỉ cho phép các file hình ảnh (jpg, jpeg, png, gif, webp).";
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === 'yes') {
        // Remove current image if requested
        if ($current_image_url && strpos($current_image_url, '../uploads/products/') === 0) {
            if (file_exists($current_image_url)) {
                unlink($current_image_url);
            }
        }
        $url = '';
    }
    
    try {
        if ($accessory_id) { // Update existing accessory
            $stmt = $conn->prepare("UPDATE phukien SET ten = ?, loaiphukien = ?, giatien = ?, mota = ?, sll = ?, url = ? WHERE id = ?");
            $stmt->execute([$ten, $loaiphukien, $giatien, $mota, $sll, $url, $accessory_id]);
            $success_message = "Phụ kiện đã được cập nhật thành công!";
        } else { // Add new accessory
            $stmt = $conn->prepare("INSERT INTO phukien (ten, loaiphukien, giatien, mota, sll, uid_phukien, url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ten, $loaiphukien, $giatien, $mota, $sll, $uid_phukien, $url]);
            $success_message = "Phụ kiện mới đã được thêm thành công!";
        }
    } catch (PDOException $e) {
        $error_message = "Lỗi khi lưu phụ kiện: " . $e->getMessage();
    }
}

// Get accessory data for editing
$edit_accessory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM phukien WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_accessory = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all accessories with pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($current_page - 1) * $items_per_page;

// Get accessories with search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$price_min = isset($_GET['price_min']) ? (int)$_GET['price_min'] : null;
$price_max = isset($_GET['price_max']) ? (int)$_GET['price_max'] : null;
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'id';
$order_dir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';

// Build the SQL query based on filters
$sql_count = "SELECT COUNT(*) as total FROM phukien WHERE 1=1";
$sql = "SELECT * FROM phukien WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ten LIKE ? OR mota LIKE ? OR uid_phukien LIKE ?)";
    $sql_count .= " AND (ten LIKE ? OR mota LIKE ? OR uid_phukien LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_type)) {
    $sql .= " AND loaiphukien = ?";
    $sql_count .= " AND loaiphukien = ?";
    $params[] = $filter_type;
}

if ($price_min !== null) {
    $sql .= " AND giatien >= ?";
    $sql_count .= " AND giatien >= ?";
    $params[] = $price_min;
}

if ($price_max !== null) {
    $sql .= " AND giatien <= ?";
    $sql_count .= " AND giatien <= ?";
    $params[] = $price_max;
}

if ($stock_status === 'in_stock') {
    $sql .= " AND sll > 0";
    $sql_count .= " AND sll > 0";
} elseif ($stock_status === 'out_of_stock') {
    $sql .= " AND sll = 0";
    $sql_count .= " AND sll = 0";
} elseif ($stock_status === 'low_stock') {
    $sql .= " AND sll BETWEEN 1 AND 9";
    $sql_count .= " AND sll BETWEEN 1 AND 9";
}

// Get total count for pagination
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Complete the query for the actual results
$sql .= " ORDER BY $order_by $order_dir LIMIT $items_per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get accessory types for filter dropdown
$stmt = $conn->query("SELECT DISTINCT loaiphukien FROM phukien ORDER BY loaiphukien");
$accessory_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get low stock accessories
$stmt = $conn->query("SELECT COUNT(*) as total FROM phukien WHERE sll BETWEEN 1 AND 9");
$low_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get out of stock accessories
$stmt = $conn->query("SELECT COUNT(*) as total FROM phukien WHERE sll = 0");
$out_of_stock_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total accessories count
$stmt = $conn->query("SELECT COUNT(*) as total FROM phukien");
$total_accessories_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Phụ Kiện - Web Đồng Hồ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/css/selectize.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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
        
        .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 3px;
            transition: transform 0.3s;
        }
        
        .preview-image:hover {
            transform: scale(1.1);
        }
        
        .badge-stock {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
        
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: var(--success-color);
        }
        
        .status-inactive {
            background-color: var(--danger-color);
        }
        
        .status-low {
            background-color: var(--warning-color);
        }
        
        .alert-count {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
        }
        
        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .dashboard-stat {
            padding: 1.5rem;
            border-left: 4px solid;
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .stat-primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-success {
            border-left-color: var(--success-color);
        }
        
        .stat-warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-danger {
            border-left-color: var(--danger-color);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: #dddfeb;
        }
        
        .image-preview-container {
            position: relative;
            display: inline-block;
        }
        
        .image-preview-container .remove-image {
            position: absolute;
            top: -5px;
            right: -5px;
            color: white;
            background-color: #e74a3b;
            border-radius: 50%;
            padding: 0.1rem 0.3rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        
        #image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        
        .custom-file-upload {
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            background-color: #f8f9fc;
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
        }
        
        .price-range-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .price-range-container input {
            flex: 1;
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
            
            .price-range-container {
                flex-direction: column;
                gap: 5px;
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
            <a class="nav-link active" href="manage_accessories.php">
                <i class="fas fa-fw fa-link"></i>
                <span>Phụ kiện</span>
                <?php if ($low_stock_count > 0 || $out_of_stock_count > 0): ?>
                <span class="badge bg-danger alert-count"><?php echo $low_stock_count + $out_of_stock_count; ?></span>
                <?php endif; ?>
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
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                <i class="fa fa-bars"></i>
            </button>
            <form class="d-none d-sm-inline-block me-auto ms-md-3 my-2 my-md-0 mw-100 navbar-search">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0" placeholder="Tìm kiếm..." aria-label="Search">
                    <button class="btn btn-primary" type="button">
                        <i class="fas fa-search fa-sm"></i>
                    </button>
                </div>
            </form>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw"></i>
                        <?php if ($low_stock_count > 0 || $out_of_stock_count > 0): ?>
                        <span class="badge bg-danger badge-counter"><?php echo $low_stock_count + $out_of_stock_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header">
                            Thông báo
                        </h6>
                        <?php if ($low_stock_count > 0): ?>
                        <a class="dropdown-item d-flex align-items-center" href="?stock_status=low_stock">
                            <div class="me-3">
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-exclamation-triangle text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500"><?php echo date("d/m/Y"); ?></div>
                                <span class="font-weight-bold"><?php echo $low_stock_count; ?> phụ kiện sắp hết hàng</span>
                            </div>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($out_of_stock_count > 0): ?>
                        <a class="dropdown-item d-flex align-items-center" href="?stock_status=out_of_stock">
                            <div class="me-3">
                                <div class="icon-circle bg-danger">
                                    <i class="fas fa-times text-white"></i>
                                </div>
                            </div>
                            <div>
                            <div class="small text-gray-500"><?php echo date("d/m/Y"); ?></div>
                                <span class="font-weight-bold"><?php echo $out_of_stock_count; ?> phụ kiện đã hết hàng</span>
                            </div>
                        </a>
                        <?php endif; ?>
                        
                        <a class="dropdown-item text-center small text-gray-500" href="#">Xem tất cả thông báo</a>
                    </div>
                </li>

                <div class="topbar-divider d-none d-sm-block"></div>

                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small">Admin</span>
                        <img class="img-profile rounded-circle" src="../assets/img/admin-avatar.png" width="30" height="30">
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
                        <a class="dropdown-item" href="../admin/logout.php" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                            Đăng xuất
                        </a>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Begin Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Quản Lý Phụ Kiện</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addAccessoryModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Thêm phụ kiện mới
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Tổng số phụ kiện</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_accessories_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-link fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Còn hàng</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_accessories_count - $out_of_stock_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Sắp hết hàng</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Hết hàng</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $out_of_stock_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accessories Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách phụ kiện</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <select name="bulk_action" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                    <option value="">-- Hành động --</option>
                                    <option value="delete">Xóa</option>
                                    <option value="activate">Kích hoạt</option>
                                    <option value="deactivate">Vô hiệu hóa</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Áp dụng</button>
                            </div>
                            <div>
                                <span class="text-muted">Hiển thị <?php echo count($accessories); ?> / <?php echo $total_items; ?> phụ kiện</span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>ID</th>
                                        <th>Hình ảnh</th>
                                        <th>Tên phụ kiện</th>
                                        <th>Loại</th>
                                        <th>Giá</th>
                                        <th>SL tồn</th>
                                        <th>Trạng thái</th>
                                        <th>Thời gian tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($accessories) > 0): ?>
                                        <?php foreach($accessories as $accessory): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input item-checkbox" type="checkbox" name="selected_items[]" value="<?php echo $accessory['id']; ?>">
                                                </div>
                                            </td>
                                            <td><?php echo $accessory['id']; ?></td>
                                            <td>
                                                <?php if(!empty($accessory['url'])): ?>
                                                <img src="<?php echo htmlspecialchars($accessory['url']); ?>" alt="<?php echo htmlspecialchars($accessory['ten']); ?>" class="preview-image">
                                                <?php else: ?>
                                                <div class="text-center text-muted">Không có ảnh</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($accessory['ten']); ?></td>
                                            <td><?php echo htmlspecialchars($accessory['loaiphukien']); ?></td>
                                            <td><?php echo number_format($accessory['giatien'], 0, ',', '.'); ?> đ</td>
                                            <td>
                                                <?php 
                                                if($accessory['sll'] > 9): 
                                                    echo '<span class="badge bg-success">' . $accessory['sll'] . '</span>';
                                                elseif($accessory['sll'] > 0): 
                                                    echo '<span class="badge bg-warning">' . $accessory['sll'] . '</span>';
                                                else: 
                                                    echo '<span class="badge bg-danger">0</span>';
                                                endif; 
                                                ?>
                                            </td>
                                            <td>
                                                <?php if($accessory['sll'] > 0): ?>
                                                <div>
                                                    <span class="status-circle status-active"></span> Đang bán
                                                </div>
                                                <?php else: ?>
                                                <div>
                                                    <span class="status-circle status-inactive"></span> Ngừng bán
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($accessory['timestamp'])); ?></td>
                                            <td>
                                                <div class="d-flex">
                                                    <a href="?edit=<?php echo $accessory['id']; ?>" class="btn btn-sm btn-primary btn-icon me-1" data-bs-toggle="tooltip" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?toggle_status=<?php echo $accessory['id']; ?>" class="btn btn-sm <?php echo $accessory['sll'] > 0 ? 'btn-warning' : 'btn-success'; ?> btn-icon me-1" data-bs-toggle="tooltip" title="<?php echo $accessory['sll'] > 0 ? 'Ngừng bán' : 'Bắt đầu bán'; ?>">
                                                        <i class="fas <?php echo $accessory['sll'] > 0 ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $accessory['id']; ?>" class="btn btn-sm btn-danger btn-icon" onclick="return confirm('Bạn có chắc chắn muốn xóa phụ kiện này?');" data-bs-toggle="tooltip" title="Xóa">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center">Không tìm thấy phụ kiện nào</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo isset($_GET['stock_status']) ? '&stock_status=' . urlencode($stock_status) : ''; ?><?php echo isset($_GET['price_min']) ? '&price_min=' . urlencode($price_min) : ''; ?><?php echo isset($_GET['price_max']) ? '&price_max=' . urlencode($price_max) : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by=' . urlencode($order_by) : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir=' . urlencode($order_dir) : ''; ?><?php echo isset($_GET['per_page']) ? '&per_page=' . urlencode($items_per_page) : ''; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo isset($_GET['stock_status']) ? '&stock_status=' . urlencode($stock_status) : ''; ?><?php echo isset($_GET['price_min']) ? '&price_min=' . urlencode($price_min) : ''; ?><?php echo isset($_GET['price_max']) ? '&price_max=' . urlencode($price_max) : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by=' . urlencode($order_by) : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir=' . urlencode($order_dir) : ''; ?><?php echo isset($_GET['per_page']) ? '&per_page=' . urlencode($items_per_page) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo isset($_GET['stock_status']) ? '&stock_status=' . urlencode($stock_status) : ''; ?><?php echo isset($_GET['price_min']) ? '&price_min=' . urlencode($price_min) : ''; ?><?php echo isset($_GET['price_max']) ? '&price_max=' . urlencode($price_max) : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by=' . urlencode($order_by) : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir=' . urlencode($order_dir) : ''; ?><?php echo isset($_GET['per_page']) ? '&per_page=' . urlencode($items_per_page) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo isset($_GET['stock_status']) ? '&stock_status=' . urlencode($stock_status) : ''; ?><?php echo isset($_GET['price_min']) ? '&price_min=' . urlencode($price_min) : ''; ?><?php echo isset($_GET['price_max']) ? '&price_max=' . urlencode($price_max) : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by=' . urlencode($order_by) : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir=' . urlencode($order_dir) : ''; ?><?php echo isset($_GET['per_page']) ? '&per_page=' . urlencode($items_per_page) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['filter_type']) ? '&filter_type=' . urlencode($filter_type) : ''; ?><?php echo isset($_GET['stock_status']) ? '&stock_status=' . urlencode($stock_status) : ''; ?><?php echo isset($_GET['price_min']) ? '&price_min=' . urlencode($price_min) : ''; ?><?php echo isset($_GET['price_max']) ? '&price_max=' . urlencode($price_max) : ''; ?><?php echo isset($_GET['order_by']) ? '&order_by=' . urlencode($order_by) : ''; ?><?php echo isset($_GET['order_dir']) ? '&order_dir=' . urlencode($order_dir) : ''; ?><?php echo isset($_GET['per_page']) ? '&per_page=' . urlencode($items_per_page) : ''; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Accessory Modal -->
    <div class="modal fade" id="<?php echo $edit_accessory ? 'editAccessoryModal' : 'addAccessoryModal'; ?>" tabindex="-1" aria-labelledby="<?php echo $edit_accessory ? 'editAccessoryModalLabel' : 'addAccessoryModalLabel'; ?>" aria-hidden="true" <?php echo $edit_accessory ? 'data-bs-backdrop="static"' : ''; ?>>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?php echo $edit_accessory ? 'editAccessoryModalLabel' : 'addAccessoryModalLabel'; ?>">
                        <?php echo $edit_accessory ? 'Cập nhật phụ kiện' : 'Thêm phụ kiện mới'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <?php if($edit_accessory): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_accessory['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="ten" class="form-label">Tên phụ kiện <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten" name="ten" value="<?php echo $edit_accessory ? htmlspecialchars($edit_accessory['ten']) : ''; ?>" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="loaiphukien" class="form-label">Loại phụ kiện <span class="text-danger">*</span></label>
                                <select class="form-select" id="loaiphukien" name="loaiphukien" required>
                                    <option value="">-- Chọn loại phụ kiện --</option>
                                    <option value="Dây đồng hồ" <?php echo ($edit_accessory && $edit_accessory['loaiphukien'] == 'Dây đồng hồ') ? 'selected' : ''; ?>>Dây đồng hồ</option>
                                    <option value="Hộp Đựng Đồng Hồ" <?php echo ($edit_accessory && $edit_accessory['loaiphukien'] == 'Hộp Đựng Đồng Hồ') ? 'selected' : ''; ?>>Hộp Đựng Đồng Hồ</option>
                                    <option value="Máy Lên Dây Cót" <?php echo ($edit_accessory && $edit_accessory['loaiphukien'] == 'Máy Lên Dây Cót') ? 'selected' : ''; ?>>Máy Lên Dây Cót</option>
                                    <option value="Kính Bảo Vệ Màn Hình" <?php echo ($edit_accessory && $edit_accessory['loaiphukien'] == 'Kính Bảo Vệ Màn Hình') ? 'selected' : ''; ?>>Kính Bảo Vệ Màn Hình</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="giatien" class="form-label">Giá tiền <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="giatien" name="giatien" value="<?php echo $edit_accessory ? htmlspecialchars($edit_accessory['giatien']) : ''; ?>" required>
                                    <span class="input-group-text">VND</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sll" class="form-label">Số lượng <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="sll" name="sll" min="0" value="<?php echo $edit_accessory ? htmlspecialchars($edit_accessory['sll']) : '10'; ?>" required>
                                <div class="form-text">Đặt số lượng = 0 để ngừng bán sản phẩm</div>
                            </div>
                            <div class="col-md-6">
                                <label for="uid_phukien" class="form-label">Mã phụ kiện</label>
                                <input type="text" class="form-control" id="uid_phukien" name="uid_phukien" value="<?php echo $edit_accessory ? htmlspecialchars($edit_accessory['uid_phukien']) : ''; ?>" placeholder="Tự động tạo nếu để trống">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mota" class="form-label">Mô tả</label>
                            <textarea class="form-control" id="mota" name="mota" rows="4"><?php echo $edit_accessory ? htmlspecialchars($edit_accessory['mota']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="accessory_image" class="form-label">Hình ảnh</label>
                            <input type="file" class="form-control" id="accessory_image" name="accessory_image" accept="image/*" onchange="previewImage(this);">
                            <div class="form-text">Cho phép các định dạng: JPG, JPEG, PNG, GIF, WEBP. Tối đa 2MB.</div>
                            
                            <?php if($edit_accessory && !empty($edit_accessory['url'])): ?>
                            <div class="mt-2 image-preview-container">
                                <img src="<?php echo htmlspecialchars($edit_accessory['url']); ?>" id="image-preview" alt="Preview">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="yes">
                                    <label class="form-check-label" for="remove_image">Xóa hình ảnh hiện tại</label>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="mt-2">
                                <img src="" id="image-preview" alt="Preview" style="display: none; max-width: 200px; max-height: 200px;">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" name="submit_accessory" class="btn btn-primary">
                                <?php echo $edit_accessory ? 'Cập nhật' : 'Thêm mới'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Bạn muốn đăng xuất?</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">Chọn "Đăng xuất" bên dưới nếu bạn đã sẵn sàng kết thúc phiên hiện tại.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <a class="btn btn-primary" href="../login/logout.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Toggle sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.querySelector('#sidebarToggle');
            const sidebarToggleTop = document.querySelector('#sidebarToggleTop');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
            
            if (sidebarToggleTop) {
                sidebarToggleTop.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize select all checkbox
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.item-checkbox');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }
            
            // Initialize the modal if editing
            <?php if($edit_accessory): ?>
            const editModal = new bootstrap.Modal(document.getElementById('editAccessoryModal'));
            editModal.show();
            <?php endif; ?>
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>