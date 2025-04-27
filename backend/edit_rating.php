<?php
session_start();
require '../database/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login1.php");
    exit;
}
$user_id = $_SESSION['user_id'];
if (isset($_GET['id']) && isset($_GET['product_id'])) {
    $rating_id = $_GET['id'];
    $product_id = $_GET['product_id'];
    $stmt = $conn->prepare("SELECT * FROM danhgia WHERE id = ? AND taikhoan_id = ?");
    $stmt->execute([$rating_id, $user_id]);
    $rating = $stmt->fetch();
    if (!$rating) {
        echo "<p>Không tìm thấy đánh giá hoặc bạn không có quyền chỉnh sửa.</p>";
        exit;
    }
    $product_stmt = $conn->prepare("SELECT tenvatpham FROM vatpham WHERE id = ?");
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch();
    
} else {
    header("Location: index.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    $new_rating = $_POST['rating'];
    $new_comment = trim($_POST['comment']);
    
    if (!is_numeric($new_rating) || $new_rating < 1 || $new_rating > 5) {
        $error = "Vui lòng chọn số sao từ 1 đến 5.";
    } else {
        $update = $conn->prepare("UPDATE danhgia SET sosao = ?, binhluan = ?, thoigian = CURRENT_TIMESTAMP WHERE id = ? AND taikhoan_id = ?");
        $update->execute([$new_rating, $new_comment, $rating_id, $user_id]);
        
        header("Location: product-detail.php?product_id=$product_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa đánh giá</title>
    <link rel="stylesheet" href="/frontend/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating > input {
            display: none;
        }
        .rating > label {
            position: relative;
            width: 1.1em;
            font-size: 30px;
            color: #FFD700;
            cursor: pointer;
        }
        .rating > label::before {
            content: "\2605";
            position: absolute;
            opacity: 0;
        }
        .rating > label:hover:before,
        .rating > label:hover ~ label:before {
            opacity: 1 !important;
        }
        .rating > input:checked ~ label:before {
            opacity: 1;
        }
        .rating:hover > input:checked ~ label:before {
            opacity: 0.4;
        }
        .edit-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Chỉnh sửa đánh giá</h1>
        <h2><?= htmlspecialchars($product['tenvatpham']) ?></h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="edit-form">
            <form method="POST">
                <div class="form-group">
                    <label>Đánh giá của bạn:</label>
                    <div class="rating">
                        <input type="radio" name="rating" value="5" id="5" <?= $rating['sosao'] == 5 ? 'checked' : '' ?>><label for="5">☆</label>
                        <input type="radio" name="rating" value="4" id="4" <?= $rating['sosao'] == 4 ? 'checked' : '' ?>><label for="4">☆</label>
                        <input type="radio" name="rating" value="3" id="3" <?= $rating['sosao'] == 3 ? 'checked' : '' ?>><label for="3">☆</label>
                        <input type="radio" name="rating" value="2" id="2" <?= $rating['sosao'] == 2 ? 'checked' : '' ?>><label for="2">☆</label>
                        <input type="radio" name="rating" value="1" id="1" <?= $rating['sosao'] == 1 ? 'checked' : '' ?>><label for="1">☆</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comment">Nhận xét:</label>
                    <textarea name="comment" id="comment" rows="4" style="width: 100%;"><?= htmlspecialchars($rating['binhluan']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="update_rating" class="btn">Cập nhật đánh giá</button>
                    <a href="product-detail.php?product_id=<?= $product_id ?>" class="btn">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>