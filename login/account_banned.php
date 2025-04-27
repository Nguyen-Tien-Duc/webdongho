<?php
session_start();
if (!isset($_SESSION['error_message'])) {
    header("Location: login1.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản bị cấm</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            text-align: center;
            padding: 50px;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 500px;
            margin: 0 auto;
        }
        h1 {
            color: red;
        }
        p {
            font-size: 18px;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Tài khoản của bạn đã bị cấm</h1>
    <p><?php echo $_SESSION['error_message']; ?></p>
    <p>Vui lòng liên hệ với quản trị viên nếu có bất kỳ thắc mắc nào.</p>
    <p class="countdown">Bạn sẽ được chuyển về trang đăng nhập trong <span id="countdown">3</span> giây...</p>
</div>

<script>
    let countdown = 3;
    const countdownElement = document.getElementById('countdown');

    const interval = setInterval(function() {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown === 0) {
            clearInterval(interval);
            window.location.href = 'login1.php';
        }
    }, 1000);
</script>

</body>
</html>
