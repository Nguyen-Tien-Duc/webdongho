<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit();
}
require_once "../database/db.php";
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Không tìm thấy thông tin hóa đơn");
}

$purchase_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
try {
    $stmt = $conn->prepare("SELECT lt.*, ls.coin 
                           FROM lichsuthanhtoan lt 
                           LEFT JOIN lichsunap ls ON lt.coin_id = ls.id 
                           WHERE lt.id = :purchase_id AND lt.taikhoan_id = :user_id");
    $stmt->bindParam(':purchase_id', $purchase_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$purchase) {
        die("Không tìm thấy thông tin hóa đơn");
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
try {
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
$product = null;
if (!empty($purchase['vatpham_id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM vatpham WHERE id = :id");
        $stmt->bindParam(':id', $purchase['vatpham_id']);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {

    }
}

$accessory = null;
if (!empty($purchase['phukien_id'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM phukien WHERE id = :id");
        $stmt->bindParam(':id', $purchase['phukien_id']);
        $stmt->execute();
        $accessory = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {

    }
}

$totalPrice = 0;
if ($product) {
    $totalPrice = $product['giatien'] * $purchase['sll'];
} elseif ($accessory) {
    $totalPrice = $accessory['giatien'] * $purchase['sll'];
}

$invoiceNumber = "HD" . date('Ymd', strtotime($purchase['thoigian'])) . $purchase_id;

$receiptTime = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa Đơn #<?php echo $invoiceNumber; ?> - Đồng Hồ Shop</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .receipt-header h1 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .receipt-header p {
            color: #7f8c8d;
            margin: 5px 0;
        }
        .receipt-info {
            margin-bottom: 20px;
        }
        .receipt-info table {
            width: 100%;
        }
        .receipt-info td {
            padding: 5px 0;
        }
        .product-details {
            margin: 20px 0;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .product-table th, .product-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .product-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .summary {
            text-align: right;
            margin-top: 20px;
            font-size: 18px;
        }
        .summary .total {
            font-weight: bold;
            font-size: 20px;
            color: #e74c3c;
        }
        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            text-align: center;
            width: 45%;
        }
        .signature .sign-line {
            margin: 50px auto 10px;
            width: 80%;
            border-bottom: 1px solid #333;
        }
        .thank-you {
            text-align: center;
            margin-top: 40px;
            padding: 20px 0;
            font-style: italic;
            color: #7f8c8d;
            border-top: 1px dashed #ddd;
        }
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        .print-button button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
        }
        .print-button button:hover {
            background-color: #2980b9;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background-color: #fff;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="print-button">
            <button onclick="window.print();">In Hóa Đơn</button>
        </div>
        
        <div class="receipt-header">
            <h1>HÓA ĐƠN MUA HÀNG</h1>
            <p>ĐỒNG HỒ SHOP - NHÓM 3</p>
            <p>Địa chỉ: 273 An Dương Vương, Phường 3, Quận 5, TP.HCM</p>
            <p>Điện thoại: 028.3835.4409 | Email: dongho.nhom3@gmail.com</p>
            <p>Website: www.dongho-nhom3.com</p>
        </div>
        
        <div class="receipt-info">
            <table>
                <tr>
                    <td><strong>Số hóa đơn:</strong></td>
                    <td><?php echo $invoiceNumber; ?></td>
                    <td><strong>Ngày mua:</strong></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($purchase['thoigian'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Khách hàng:</strong></td>
                    <td><?php echo htmlspecialchars($user['fullname'] ?? $user['tentaikhoan']); ?></td>
                    <td><strong>Xuất hóa đơn:</strong></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($receiptTime)); ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><strong>Phương thức:</strong></td>
                    <td>Thanh toán bằng coin</td>
                </tr>
            </table>
        </div>
        
        <div class="product-details">
            <h3>Chi Tiết Sản Phẩm</h3>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Loại</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['tenvatpham']); ?></td>
                        <td><?php echo htmlspecialchars($product['loaisanpham']); ?> - <?php echo htmlspecialchars($product['thuonghieu']); ?></td>
                        <td><?php echo number_format($product['giatien'], 0, ',', '.'); ?> VNĐ</td>
                        <td><?php echo $purchase['sll']; ?></td>
                        <td><?php echo number_format($totalPrice, 0, ',', '.'); ?> VNĐ</td>
                    </tr>
                    <?php elseif ($accessory): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($accessory['ten']); ?></td>
                        <td><?php echo htmlspecialchars($accessory['loaiphukien']); ?></td>
                        <td><?php echo number_format($accessory['giatien'], 0, ',', '.'); ?> VNĐ</td>
                        <td><?php echo $purchase['sll']; ?></td>
                        <td><?php echo number_format($totalPrice, 0, ',', '.'); ?> VNĐ</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="5">Không tìm thấy thông tin sản phẩm</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="summary">
                <p>Tổng tiền: <span class="total"><?php echo number_format($totalPrice, 0, ',', '.'); ?> VNĐ</span></p>
                <p>Số tiền sử dụng: <?php echo number_format($purchase['coin'], 0, ',', '.'); ?> VNĐ</p>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature">
                <div class="sign-line"></div>
                <p><strong>Lê Văn Trí</strong></p>
                <p>Đại diện cửa hàng</p>
            </div>
            <div class="signature">
                <div class="sign-line"></div>
                <p><strong><?php echo htmlspecialchars($user['tentaikhoan']); ?></strong></p>
                <p>Khách hàng</p>
            </div>
        </div>
        
        <div class="thank-you">
            <p>Cảm ơn quý khách đã mua hàng tại ĐỒNG HỒ SHOP - NHÓM 3!</p>
            <p>Chúng tôi rất vui khi được phục vụ quý khách và hy vọng sản phẩm sẽ mang lại trải nghiệm tuyệt vời.</p>
            <p>Mọi thắc mắc về sản phẩm, vui lòng liên hệ hotline: 1900.9696 (8:00 - 22:00)</p>
        </div>
    </div>
</body>
</html>