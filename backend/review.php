<?php
require_once '../database/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login1.php');
    exit();
}
$user_id = $_SESSION['user_id'];
try {
    $stmt = $conn->prepare("
        SELECT d.*, v.tenvatpham, v.url as vatpham_url, v.thuonghieu
        FROM danhgia d
        JOIN vatpham v ON d.vatpham_id = v.id
        WHERE d.taikhoan_id = :user_id
        ORDER BY d.thoigian DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử đánh giá của tôi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .review-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .review-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .review-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .review-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .review-item:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .brand-name {
            color: #666;
            font-style: italic;
            margin-bottom: 10px;
        }
        
        .stars {
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .comment {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .review-date {
            color: #888;
            font-size: 14px;
            text-align: right;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        
        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .edit-btn, .delete-btn {
            padding: 5px 10px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .edit-btn {
            background-color: #4caf50;
            color: white;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
    </style>
</head>
<body>
    <div class="review-container">
        <h1 class="review-title">Lịch sử đánh giá của tôi</h1>
        
        <?php if (count($reviews) > 0): ?>
            <div class="review-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <img src="/WebDongHo/<?php echo htmlspecialchars($review['vatpham_url']); ?>" alt="<?php echo htmlspecialchars($review['tenvatpham']); ?>" class="product-image">
                        <h3 class="product-name"><?php echo htmlspecialchars($review['tenvatpham']); ?></h3>
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
                        
                        <div class="actions">
                            <button class="edit-btn" onclick="location.href='edit_review.php?id=<?php echo $review['id']; ?>'">Sửa</button>
                            <button class="delete-btn" onclick="if(confirm('Bạn có chắc muốn xóa đánh giá này?')) location.href='delete_review.php?id=<?php echo $review['id']; ?>'">Xóa</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-reviews">
                <p>Bạn chưa có đánh giá nào. Hãy mua sắm và đánh giá sản phẩm!</p>
                <button onclick="location.href='index.php'" style="padding: 10px 20px; background-color: #4caf50; color: white; border: none; border-radius: 4px; margin-top: 20px; cursor: pointer;">Xem sản phẩm</button>
            </div>
        <?php endif; ?>
    </div>
    
</body>
</html>