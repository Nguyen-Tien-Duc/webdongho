<?php
require_once '../database/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login1.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$review_id) {
    header('Location: reviews.php');
    exit();
}
try {
    $check_stmt = $conn->prepare("
        SELECT id FROM danhgia 
        WHERE id = :review_id AND taikhoan_id = :user_id
    ");
    $check_stmt->bindParam(':review_id', $review_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        header('Location: reviews.php');
        exit();
    }
    
    // Xử lý xóa
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
        $delete_stmt = $conn->prepare("
            DELETE FROM danhgia 
            WHERE id = :review_id AND taikhoan_id = :user_id
        ");
        
        $delete_stmt->bindParam(':review_id', $review_id, PDO::PARAM_INT);
        $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            header('Location: reviews.php?deleted=1');
            exit();
        } else {
            $error = "Có lỗi xảy ra khi xóa đánh giá";
        }
    }
    $stmt = $conn->prepare("
        SELECT d.*, v.tenvatpham, v.url as vatpham_url, v.thuonghieu
        FROM danhgia d
        JOIN vatpham v ON d.vatpham_id = v.id
        WHERE d.id = :review_id AND d.taikhoan_id = :user_id
    ");
    
    $stmt->bindParam(':review_id', $review_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa đánh giá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .delete-review-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .delete-review-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .confirmation-box {
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .confirmation-message {
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
        }
        
        .warning {
            color: #f44336;
            font-weight: bold;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 20px;
        }
        
        .product-details h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .brand-name {
            color: #666;
            font-style: italic;
            margin-bottom: 5px;
        }
        
        .stars {
            color: #ffc107;
            margin-bottom: 5px;
        }
        
        .comment {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .review-date {
            color: #888;
            font-size: 14px;
        }
        
        .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .error-message {
            color: #f44336;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
    
    <div class="delete-review-container">
        <h1 class="delete-review-title">Xóa đánh giá</h1>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="confirmation-box">
            <div class="confirmation-message">
                <p>Bạn có chắc chắn muốn xóa đánh giá này?</p>
                <p class="warning">Lưu ý: Hành động này không thể hoàn tác!</p>
            </div>
            
            <div class="product-info">
                <img src="<?php echo htmlspecialchars($review['vatpham_url']); ?>" alt="<?php echo htmlspecialchars($review['tenvatpham']); ?>" class="product-image">
                <div class="product-details">
                    <h3><?php echo htmlspecialchars($review['tenvatpham']); ?></h3>
                    <p class="brand-name"><?php echo htmlspecialchars($review['thuonghieu']); ?></p>
                    
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['sosao']): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <p class="comment"><?php echo htmlspecialchars($review['binhluan']); ?></p>
                    <p class="review-date">Đánh giá vào: <?php echo date('d/m/Y H:i', strtotime($review['thoigian'])); ?></p>
                </div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="confirm_delete" value="yes">
                <div class="buttons">
                    <button type="button" class="btn btn-secondary" onclick="location.href='reviews.php'">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>