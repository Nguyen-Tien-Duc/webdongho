<?php
require_once '../database/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login1.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
try {
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
    if (!$review) {
        header('Location: review.php');
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sosao = isset($_POST['sosao']) ? (int)$_POST['sosao'] : 5;
        $binhluan = isset($_POST['binhluan']) ? trim($_POST['binhluan']) : '';
        if ($sosao < 1 || $sosao > 5) {
            $error = "Số sao phải từ 1 đến 5";
        } else {
            $update_stmt = $conn->prepare("
                UPDATE danhgia 
                SET sosao = :sosao, binhluan = :binhluan 
                WHERE id = :review_id AND taikhoan_id = :user_id
            ");
            
            $update_stmt->bindParam(':sosao', $sosao, PDO::PARAM_INT);
            $update_stmt->bindParam(':binhluan', $binhluan, PDO::PARAM_STR);
            $update_stmt->bindParam(':review_id', $review_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                header('Location: review.php?updated=1');
                exit();
            } else {
                $error = "Có lỗi xảy ra khi cập nhật đánh giá";
            }
        }
    }
} catch(PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa đánh giá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-review-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .edit-review-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
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
        
        .error-message {
            color: #f44336;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .star-rating {
            margin-bottom: 20px;
        }
        
        .star-rating input[type="radio"] {
            display: none;
        }
        
        .star-rating label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
        }
        
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        
        .buttons {
            display: flex;
            justify-content: space-between;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-primary {
            background-color: #4caf50;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
    </style>
</head>
<body>
    
    <div class="edit-review-container">
        <h1 class="edit-review-title">Sửa đánh giá</h1>
        
        <div class="product-info">
            <img src="/WebDongHo/<?php echo htmlspecialchars($review['vatpham_url']); ?>" alt="<?php echo htmlspecialchars($review['tenvatpham']); ?>" class="product-image">
            <div class="product-details">
                <h3><?php echo htmlspecialchars($review['tenvatpham']); ?></h3>
                <p class="brand-name"><?php echo htmlspecialchars($review['thuonghieu']); ?></p>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Đánh giá của bạn:</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?php echo $i; ?>" name="sosao" value="<?php echo $i; ?>" <?php echo ($review['sosao'] == $i) ? 'checked' : ''; ?>>
                        <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="binhluan">Bình luận:</label>
                <textarea id="binhluan" name="binhluan" rows="5"><?php echo htmlspecialchars($review['binhluan']); ?></textarea>
            </div>
            
            <div class="buttons">
                <button type="button" class="btn btn-secondary" onclick="location.href='review.php'">Hủy</button>
                <button type="submit" class="btn btn-primary">Cập nhật đánh giá</button>
            </div>
        </form>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-rating label');         
            stars.forEach(function(star, index) {
                star.addEventListener('click', function() {
                    const rating = 5 - index;
                    document.getElementById('star' + rating).checked = true;
                });
            });
        });
    </script>
</body>
</html>