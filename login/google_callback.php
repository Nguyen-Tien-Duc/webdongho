<?php
session_start();
require_once __DIR__ . "../database/db.php";
require_once __DIR__ . "/../google_config.php";
require_once __DIR__ . "/../google-api-php-client/vendor/autoload.php";

$google_client = new Google_Client();
$google_client->setClientId(GOOGLE_CLIENT_ID);
$google_client->setClientSecret(GOOGLE_CLIENT_SECRET);
$google_client->setRedirectUri(GOOGLE_REDIRECT_URI);
$google_client->addScope("email");
$google_client->addScope("profile");

if (isset($_GET["code"])) {
    try {
        $token = $google_client->fetchAccessTokenWithAuthCode($_GET["code"]);

        if (!isset($token["error"])) {
            $google_client->setAccessToken($token["access_token"]);
            $google_service = new Google_Service_Oauth2($google_client);
            $google_user = $google_service->userinfo->get();

            $google_id = $google_user->id;
            $email = $google_user->email;
            $name = $google_user->name;
            $avatar = $google_user->picture;

            // Kiểm tra xem tài khoản đã tồn tại chưa
            $stmt = $conn->prepare("SELECT id, tentaikhoan, status FROM taikhoan WHERE google_id = ? OR email = ?");
            $stmt->execute([$google_id, $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Kiểm tra nếu tài khoản bị cấm
                if ($user['status'] === 'banned') {
                    // Lưu thông báo lỗi vào session và chuyển hướng đến trang account_banned.php
                    $_SESSION['error_message'] = "Tài khoản đã bị cấm tại website";
                    header("Location: account_banned.php");
                    exit();
                }

                // Đăng nhập tài khoản đã có
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["tentaikhoan"];
            } else {
                // Tạo tài khoản mới
                $stmt = $conn->prepare("INSERT INTO taikhoan (tentaikhoan, email, avatar, google_id, matkhau, login_method) VALUES (?, ?, ?, ?, ?, 'google')");
                $stmt->execute([$name, $email, $avatar, $google_id, "GOOGLE_LOGIN"]);

                $_SESSION["user_id"] = $conn->lastInsertId();
                $_SESSION["username"] = $name;
            }

            header("Location: ../backend/information.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Lỗi đăng nhập Google: " . $e->getMessage());
    }
}

header("Location: login.php");
exit();
?>
