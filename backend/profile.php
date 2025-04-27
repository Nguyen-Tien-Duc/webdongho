<?php
session_start();
require_once '../database/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit();
}

$uploadDir = '../Uploads/public/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $errors = [];
    
    if (!empty($phone)) {
        if (strlen($phone) !== 10) {
            $errors[] = "Số điện thoại phải có 10 số.";
        } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
            $errors[] = "Số điện thoại không hợp lệ. Phải bắt đầu bằng số 0.";
        }
    }
    
    $avatar_path = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $filesize = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "Chỉ chấp nhận file ảnh có định dạng PNG, GIF, JPG.";
        } elseif ($filesize > 5242880) { // 5MB
            $errors[] = "Kích thước file quá lớn. Tối đa 5MB.";
        } else {
            $new_filename = uniqid() . '.' . $ext;
            $avatar_path = 'Uploads/public/' . $new_filename;
            
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], '../' . $avatar_path)) {
                $errors[] = "Có lỗi khi tải file lên.";
            }
        }
    }
    
    if (empty($errors)) {
        if ($avatar_path) {
            $stmtUpdate = $conn->prepare("UPDATE taikhoan SET fullname = ?, phone = ?, avatar = ? WHERE id = ?");
            $stmtUpdate->execute([$fullname, $phone, $avatar_path, $user_id]);
        } else {
            $stmtUpdate = $conn->prepare("UPDATE taikhoan SET fullname = ?, phone = ? WHERE id = ?");
            $stmtUpdate->execute([$fullname, $phone, $user_id]);
        }
        
        $_SESSION['success_message'] = "Cập nhật thông tin thành công!";
        if (!empty($phone)) {
            $_SESSION['phone_message'] = "Hê hê, shop có số điện thoại của cưng rồi nhé, hôm nào shop rủ đi bay lắc";
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: profile.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_funds'])) {
    $amount = (int)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    
    if ($amount > 0) {
        $stmtInsert = $conn->prepare("INSERT INTO lichsunap (taikhoan_id, phuongthuc, coin) VALUES (?, ?, ?)");
        $stmtInsert->execute([$user_id, $payment_method, $amount]);
        $_SESSION['success_message'] = "Đã nạp $amount coin hoàn tất!";
    } else {
        $_SESSION['error_message'] = "Số tiền nạp không hợp lệ.";
    }
    header("Location: profile.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$orderStmt = $conn->prepare("
    SELECT 
        ltt.id, 
        ltt.sll, 
        ltt.trangthai, 
        ltt.thoigian,
        COALESCE(v.tenvatpham, p.ten) as ten_san_pham,
        COALESCE(v.giatien, p.giatien) as gia_tien,
        CASE 
            WHEN v.id IS NOT NULL THEN 'Đồng hồ'
            WHEN p.id IS NOT NULL THEN 'Phụ kiện'
            ELSE 'Không xác định'
        END as loai_san_pham,
        COALESCE(v.url, p.url) as hinh_anh
    FROM lichsuthanhtoan ltt
    LEFT JOIN vatpham v ON ltt.vatpham_id = v.id
    LEFT JOIN phukien p ON ltt.phukien_id = p.id
    WHERE ltt.taikhoan_id = ? 
    ORDER BY ltt.thoigian DESC
    LIMIT 10
");
$orderStmt->execute([$user_id]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

$depositStmt = $conn->prepare("SELECT * FROM lichsunap WHERE taikhoan_id = ? ORDER BY thoigian DESC LIMIT 5");
$depositStmt->execute([$user_id]);
$deposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);

$reviewStmt = $conn->prepare("
    SELECT d.id, d.sosao, d.binhluan, d.thoigian, v.tenvatpham, v.url
    FROM danhgia d
    JOIN vatpham v ON d.vatpham_id = v.id
    WHERE d.taikhoan_id = ?
    ORDER BY d.thoigian DESC
    LIMIT 5
");
$reviewStmt->execute([$user_id]);
$reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang cá nhân | <?= htmlspecialchars($user['tentaikhoan']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/profile.css"> 
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <style>
        .avatar-upload {
            position: relative;
            text-align: center;
            margin-bottom: 20px;
        }

        .avatar-edit {
            position: absolute;
            right: 30%;
            bottom: 0;
        }

        .avatar-edit input {
            display: none;
        }

        .avatar-edit label {
            display: inline-block;
            width: 34px;
            height: 34px;
            margin-bottom: 0;
            border-radius: 100%;
            background: #FFFFFF;
            border: 1px solid transparent;
            box-shadow: 0px 2px 4px 0px rgba(0,0,0,0.12);
            cursor: pointer;
            font-weight: normal;
            transition: all .2s ease-in-out;
            line-height: 34px;
        }

        .avatar-edit label:hover {
            background: #f1f1f1;
            border-color: #d6d6d6;
        }

        .phone-message {
            margin-top: 15px;
            padding: 10px;
            background-color: #ffe6e6;
            border-radius: 5px;
            color: #ff6666;
            font-style: italic;
            text-align: center;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <img src="../frontend/assets/images/logo1.png" alt="Logo">
                    </a>
                </div>
                <div class="user-welcome">
                    <?php
                    $base_url = "/webdongho";
                    ?>
                    <img src="<?= $user['avatar'] ? $base_url . '/' . htmlspecialchars($user['avatar']) : $base_url . '/frontend/assets/images/anh-dai-dien.png' ?>" alt="Avatar" width="120" height="120">
                    <div>
                        <span>Xin chào, <?= htmlspecialchars($user['tentaikhoan']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php elseif (isset($_SESSION['error_message'])): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="profile-container">
            <div class="sidebar">
                <div class="user-info">
                    <div class="avatar-wrapper">
                        <div class="avatar-upload">
                            <img src="<?= $user['avatar'] ? $base_url . '/' . htmlspecialchars($user['avatar']) : $base_url . '/frontend/assets/images/anh-dai-dien.png' ?>" alt="Avatar" width="120" height="120">
                            <div class="avatar-edit">
                                <label for="avatar-upload-btn" title="Thay đổi ảnh đại diện">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </div>
                        </div>
                    </div>
                    <h3><?= htmlspecialchars($user['fullname'] ?: $user['tentaikhoan']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <div class="user-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($user['coin']) ?></span>
                            <span class="stat-label">VNĐ</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= count($orders) ?></span>
                            <span class="stat-label">Đơn hàng</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= count($reviews) ?></span>
                            <span class="stat-label">Đánh giá</span>
                        </div>
                    </div>
                </div>
                <ul class="sidebar-menu">
                    <li><a href="#profile" class="tab-link active" data-tab="profile-tab"><i class="fas fa-user"></i> Thông tin cá nhân</a></li>
                    <li><a href="#orders" class="tab-link" data-tab="orders-tab"><i class="fas fa-shopping-bag"></i> Đơn hàng của tôi</a></li>
                    <li><a href="#deposits" class="tab-link" data-tab="deposits-tab"><i class="fas fa-wallet"></i> Lịch sử </a></li>
                    <li><a href="#reviews" class="tab-link" data-tab="reviews-tab"><i class="fas fa-star"></i> Đánh giá của tôi</a></li>
                    <li><a href="#" id="add-funds-btn"><i class="fas fa-coins"></i> Nạp tiền</a></li>
                    <li><a href="index.php"><i class="fas fa-home"></i> Về trang chủ</a></li>
                    <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Giỏ hàng</a></li>
                </ul>
            </div>

            <div class="main-content tab-content">
                <div id="profile-tab" class="card active">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-edit"></i> Chỉnh sửa thông tin cá nhân</h3>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="file" id="avatar-upload-btn" name="avatar" accept="image/png,image/gif,image/jpeg" style="display: none;">
                        <div class="form-group">
                            <label for="fullname">Họ và tên:</label>
                            <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?: '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Số điện thoại:</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?: '') ?>" 
                                   placeholder="Nhập số điện thoại (10 số)" maxlength="10" pattern="[0-9]{10}">
                            <small>Vui lòng nhập đúng 10 số, bắt đầu bằng số 0</small>
                            <?php if (isset($_SESSION['phone_message'])): ?>
                                <div class="phone-message">
                                    <i class="fas fa-grin-wink"></i> <?= $_SESSION['phone_message'] ?>
                                </div>
                                <?php unset($_SESSION['phone_message']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" class="form-control readonly" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="username">Tên tài khoản:</label>
                            <input type="text" id="username" class="form-control readonly" value="<?= htmlspecialchars($user['tentaikhoan']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="login_method">Phương thức đăng nhập:</label>
                            <input type="text" id="login_method" class="form-control readonly" value="<?= ucfirst($user['login_method']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="join_date">Ngày tham gia:</label>
                            <input type="text" id="join_date" class="form-control readonly" value="<?= date('d/m/Y', strtotime($user['thoigian'])) ?>" readonly>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 20px; display: block; margin-left: auto; margin-right: auto;">
                            <i class="fas fa-save"></i> Lưu thay đổi
                        </button>
                    </form>
                </div>
                <div id="orders-tab" class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-shopping-bag"></i> Đơn hàng của tôi</h3>
                        <div class="card-tools">
                            <a href="cart.php">Đến giỏ hàng <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <?php if (count($orders) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="order-table">
                                <thead>
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th>Sản phẩm</th>
                                        <th>Loại</th>
                                        <th>Số lượng</th>
                                        <th>Giá tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày mua</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <?php if ($order['hinh_anh']): ?>
                                                        <?php if ($order['loai_san_pham'] === 'Đồng hồ'): ?>
                                                            <img src="/WebDongHo/<?= htmlspecialchars($order['hinh_anh']) ?>" class="product-image" alt="<?= htmlspecialchars($order['ten_san_pham']) ?>">
                                                        <?php elseif ($order['loai_san_pham'] === 'Phụ kiện'): ?>
                                                            <img src="/WebDongHo/Uploads/<?= htmlspecialchars($order['hinh_anh']) ?>" class="product-image" alt="<?= htmlspecialchars($order['ten_san_pham']) ?>">
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 5px; display: flex; justify-content: center; align-items: center;">
                                                            <i class="fas fa-image" style="color: #aaa;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($order['ten_san_pham']) ?>
                                                </div>
                                            </td>
                                            <td><?= $order['loai_san_pham'] ?></td>
                                            <td><?= $order['sll'] ?></td>
                                            <td><?= number_format($order['gia_tien'] * $order['sll']) ?> coin</td>
                                            <td>
                                                <?php if ($order['trangthai'] === 'đã thanh toán'): ?>
                                                    <span class="status status-success">Đã thanh toán</span>
                                                <?php else: ?>
                                                    <span class="status status-pending">Chưa thanh toán</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($order['thoigian'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="print_receipt.php" class="btn btn-primary">Xem tất cả đơn hàng</a>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px;">
                            <i class="fas fa-shopping-bag" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                            <p>Bạn chưa có đơn hàng nào.</p>
                            <a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Mua sắm ngay</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="deposits-tab" class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-wallet"></i> Lịch sử nạp tiền</h3>
                        <div class="card-tools">
                            <a href="#" id="add-funds-btn2">Nạp thêm <i class="fas fa-plus"></i></a>
                        </div>
                    </div>
                    <?php if (count($deposits) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="deposit-table">
                                <thead>
                                    <tr>
                                        <th>Mã giao dịch</th>
                                        <th>Phương thức</th>
                                        <th>Số tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày nạp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($deposit['uid']) ?></td>
                                            <td><?= htmlspecialchars($deposit['phuongthuc']) ?></td>
                                            <td><?= number_format($deposit['coin']) ?> coin</td>
                                            <td>
                                                <?php if ($deposit['trangthai'] === 'thành công'): ?>
                                                    <span class="status status-success">Thành công</span>
                                                <?php elseif ($deposit['trangthai'] === 'thất bại'): ?>
                                                    <span class="status status-failed">Thất bại</span>
                                                <?php else: ?>
                                                    <span class="status status-pending">Đang xử lý</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($deposit['thoigian'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="#" id="add-funds-btn3" class="btn btn-primary">Nạp thêm coin</a>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px;">
                            <i class="fas fa-wallet" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                            <p>Bạn chưa có lịch sử nạp tiền nào.</p>
                            <a href="#" id="add-funds-btn4" class="btn btn-primary" style="margin-top: 15px;">Nạp tiền ngay</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="reviews-tab" class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-star"></i> Đánh giá của tôi</h3>
                    </div>
                    <?php if (count($reviews) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="review-table">
                                <thead>
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đánh giá</th>
                                        <th>Bình luận</th>
                                        <th>Ngày đánh giá</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <?php if ($review['url']): ?>
                                                        <img src="/WebDongHo/<?= htmlspecialchars($review['url']) ?>" class="product-image" alt="<?= htmlspecialchars($review['tenvatpham']) ?>">
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 5px; display: flex; justify-content: center; align-items: center;">
                                                            <i class="fas fa-image" style="color: #aaa;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($review['tenvatpham']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['sosao']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td><?= nl2br(htmlspecialchars($review['binhluan'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($review['thoigian'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="review.php" class="btn btn-primary">Xem tất cả đánh giá</a>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px;">
                            <i class="fas fa-star" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                            <p>Bạn chưa có đánh giá nào.</p>
                            <p style="margin-top: 10px;">Hãy mua sản phẩm và đánh giá để giúp người khác có quyết định tốt hơn!</p>
                            <a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Xem sản phẩm</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="add-funds-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="modal-header">
                <h4 class="modal-title"><i class="fas fa-coins"></i> Nạp tiền</h4>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Chọn phương thức thanh toán:</label>
                    <div class="payment-options">
                        <div class="payment-option active" data-method="momo">
                            <i class="fas fa-wallet" style="color: #a50064;"></i>
                            <span class="payment-option-name">MoMo</span>
                        </div>
                        <div class="payment-option" data-method="zalopay">
                            <i class="fas fa-wallet" style="color: #0068ff;"></i>
                            <span class="payment-option-name">ZaloPay</span>
                        </div>
                        <div class="payment-option" data-method="banking">
                            <i class="fas fa-university"></i>
                            <span class="payment-option-name">Banking</span>
                        </div>
                        <div class="payment-option" data-method="card">
                            <i class="fas fa-credit-card"></i>
                            <span class="payment-option-name">Thẻ</span>
                        </div>
                    </div>
                    <input type="hidden" name="payment_method" id="payment_method" value="momo">
                </div>
                <div class="form-group">
                    <label>Chọn số tiền:</label>
                    <div class="amount-options">
                        <div class="amount-option" data-amount="50000">50,000</div>
                        <div class="amount-option" data-amount="100000">100,000</div>
                        <div class="amount-option" data-amount="200000">200,000</div>
                        <div class="amount-option" data-amount="500000">500,000</div>
                    </div>
                    <div class="custom-amount">
                        <input type="number" id="amount" name="amount" class="form-control" placeholder="Nhập số tiền khác" value="50000">
                    </div>
                </div>
                <button type="submit" name="add_funds" class="btn btn-primary">
                    <i class="fas fa-check"></i> Xác nhận nạp tiền
                </button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-link').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content > div').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        const modal = document.getElementById('add-funds-modal');
        const addFundsBtns = [
            document.getElementById('add-funds-btn'),
            document.getElementById('add-funds-btn2'),
            document.getElementById('add-funds-btn3'),
            document.getElementById('add-funds-btn4')
        ].filter(btn => btn !== null);

        addFundsBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'flex';
            });
        });

        document.querySelector('.close-modal').addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('payment_method').value = this.getAttribute('data-method');
            });
        });

        document.querySelectorAll('.amount-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.amount-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('amount').value = this.getAttribute('data-amount');
            });
        });

        document.querySelector('.amount-option[data-amount="50000"]').classList.add('active');
    </script>
</body>
</html>