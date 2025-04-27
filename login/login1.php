<?php
session_start();
require '../database/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == "register") {
            $tentaikhoan = trim($_POST['tentaikhoan']);
            $email = trim($_POST['email']);
            $matkhau = trim($_POST['matkhau']);

            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $tentaikhoan)) {
                showMessage("❌ Tên tài khoản phải có ít nhất 8 ký tự, gồm cả chữ & số!", "login1.php", "error");
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                showMessage("❌ Email không hợp lệ!", "login1.php", "error");
                exit;
            }

            $stmt = $conn->prepare("SELECT email, tentaikhoan FROM taikhoan WHERE email = ? OR tentaikhoan = ?");
            $stmt->execute([$email, $tentaikhoan]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                if ($existingUser['email'] === $email) {
                    showMessage("❌ Email đã tồn tại!", "login1.php", "error");
                } elseif ($existingUser['tentaikhoan'] === $tentaikhoan) {
                    showMessage("❌ Tên tài khoản đã tồn tại!", "login1.php", "error");
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO taikhoan (tentaikhoan, email, matkhau, status) VALUES (?, ?, ?, 'active')");
                if ($stmt->execute([$tentaikhoan, $email, $matkhau])) {
                    $user_id = $conn->lastInsertId();
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['tentaikhoan'] = $tentaikhoan;
                    showMessage("✅ Đăng ký thành công! Đang chuyển hướng...", "../backend/index.php", "success");
                } else {
                    showMessage("❌ Lỗi đăng ký!", "login1.php", "error");
                }
            }
        }

        if ($action == "login") {
            $login_input = trim($_POST['login_input']);
            $matkhau = trim($_POST['matkhau']);
            $stmt = $conn->prepare("SELECT id, tentaikhoan, status FROM taikhoan WHERE (email = ? OR tentaikhoan = ?) AND matkhau = ?");
            $stmt->execute([$login_input, $login_input, $matkhau]);
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                if ($user['status'] == 'banned') {
                    showMessage("⚠️ Tài khoản của bạn đã bị cấm!", "account_banned.php", "error");
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['tentaikhoan'] = $user['tentaikhoan'];
                    showMessage("✅ Đăng nhập thành công! Đang chuyển hướng...", "../backend/index.php", "success");
                }
            } else {
                showMessage("❌ Sai tài khoản/email hoặc mật khẩu!", "login1.php", "error");
            }
        }

        if ($action == "admin_login") {
            $admin_password = trim($_POST['admin_password']);
            $admin_correct_password = "nhom3";
            if ($admin_password === $admin_correct_password) {
                $_SESSION['admin_logged_in'] = true;
                header("Location: ../admin/admin_dashboard.php");
                exit;
            } else {
                showMessage("❌ Sai mật khẩu admin!", "login1.php", "error");
            }
        }
    }
}

function showMessage($message, $redirectUrl, $type = "info") {
    $bgColor = $type == "success" ? "#4CAF50" : ($type == "error" ? "#F44336" : "#2196F3");
    $animation = $type == "success" ? "fadeInUp" : ($type == "error" ? "shakeX" : "fadeIn");
    echo "<html><head><meta charset='UTF-8'><title>Thông báo</title></head><body style='text-align:center;padding:100px;background:#f0f0f0;'>";
    echo "<div style='background:#fff;padding:30px;border-radius:10px;display:inline-block;'>";
    echo "<h2 style='color:$bgColor;'>$message</h2>";
    echo "<p>Chuyển hướng trong <span id='countdown'>3</span> giây...</p>";
    echo "<script>var seconds=3;var countdown=document.getElementById('countdown');var interval=setInterval(function(){seconds--;countdown.textContent=seconds;if(seconds<=0){clearInterval(interval);window.location.href='$redirectUrl';}},1000);</script>";
    echo "</div></body></html>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../frontend/assets/css/login.css">
    <title>Đăng Nhập</title>
</head>
<body>
    <div class="back-to-home">
        <a href="../frontend/pages/index.html" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Quay lại trang chủ
        </a>
    </div>

    <div class="container" id="container">
        <div class="form-container sign-in-container">
            <form action="login1.php" method="POST" id="loginForm">
                <h1 class="form-title">Đăng nhập</h1>
                <input type="hidden" name="action" value="login">
                <input type="text" name="login_input" id="login_input" placeholder="Email hoặc Tài khoản" required>
                <div class="error-message" id="login_input_error"></div>
                <input type="password" name="matkhau" id="login_matkhau" placeholder="Mật khẩu" required>
                <div class="error-message" id="login_matkhau_error"></div>
                <div class="forgot-password">
                    <a href="#">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="animate__animated">Đăng nhập</button>
                <div class="admin-login-link" id="adminLoginLink">Admin</div>
                <p class="mobile-toggle" id="mobile-signup">Chưa có tài khoản? <a href="#">Đăng ký ngay</a></p>
            </form>
        </div>

        <div class="form-container sign-up-container">
            <form action="login1.php" method="POST" id="registerForm">
                <h1 class="form-title">Tạo tài khoản</h1>
                <input type="hidden" name="action" value="register">
                <input type="text" name="tentaikhoan" id="tentaikhoan" placeholder="Tài khoản" required>
                <div class="error-message" id="tentaikhoan_error"></div>
                <input type="email" name="email" id="email" placeholder="Email" required>
                <div class="error-message" id="email_error"></div>
                <input type="password" name="matkhau" id="register_matkhau" placeholder="Mật khẩu" required>
                <div class="error-message" id="register_matkhau_error"></div>
                <button type="submit" class="animate__animated">Đăng ký</button>
                <p class="mobile-toggle" id="mobile-signin">Đã có tài khoản? <a href="#">Đăng nhập</a></p>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Chào mừng trở lại!</h1>
                    <p>Đăng nhập để kết nối với chúng tôi và tiếp tục trải nghiệm dịch vụ của chúng tôi</p>
                    <button class="ghost" id="signIn">Đăng nhập</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Xin chào!</h1>
                    <p>Đăng ký tài khoản và bắt đầu hành trình cùng chúng tôi</p>
                    <button class="ghost" id="signUp">Đăng ký</button>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-login-container" id="adminLoginContainer">
        <span class="admin-login-close" id="adminLoginClose">&times;</span>
        <div class="admin-login-form">
            <form action="login1.php" method="POST" id="adminForm">
                <h2>Admin Login</h2>
                <input type="hidden" name="action" value="admin_login">
                <input type="password" name="admin_password" id="admin_password" placeholder="Mật khẩu Admin" required>
                <div class="error-message" id="admin_password_error"></div>
                <button type="submit" class="animate__animated">Đăng nhập</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const signUpButton = document.getElementById('signUp');
            const signInButton = document.getElementById('signIn');
            const mobileSignUp = document.getElementById('mobile-signup');
            const mobileSignIn = document.getElementById('mobile-signin');
            const container = document.getElementById('container');
            const registerForm = document.getElementById('registerForm');
            const loginForm = document.getElementById('loginForm');
            const adminLoginLink = document.getElementById('adminLoginLink');
            const adminLoginContainer = document.getElementById('adminLoginContainer');
            const adminLoginClose = document.getElementById('adminLoginClose');
            const adminForm = document.getElementById('adminForm');

            function togglePanel(activate) {
                if (activate) {
                    container.classList.add('right-panel-active');
                    const signUpInputs = document.querySelectorAll('.sign-up-container input');
                    signUpInputs.forEach((input, index) => {
                        input.style.opacity = 0;
                        input.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            input.style.transition = 'all 0.4s ease';
                            input.style.opacity = 1;
                            input.style.transform = 'translateY(0)';
                        }, 100 + (index * 100));
                    });
                } else {
                    container.classList.remove('right-panel-active');
                    const signInInputs = document.querySelectorAll('.sign-in-container input');
                    signInInputs.forEach((input, index) => {
                        input.style.opacity = 0;
                        input.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            input.style.transition = 'all 0.4s ease';
                            input.style.opacity = 1;
                            input.style.transform = 'translateY(0)';
                        }, 100 + (index * 100));
                    });
                }
            }

            signUpButton.addEventListener('click', () => {
                togglePanel(true);
                signUpButton.classList.add('btn-pulse');
                setTimeout(() => signUpButton.classList.remove('btn-pulse'), 1500);
            });

            signInButton.addEventListener('click', () => {
                togglePanel(false);
                signInButton.classList.add('btn-pulse');
                setTimeout(() => signInButton.classList.remove('btn-pulse'), 1500);
            });

            if (mobileSignUp) {
                mobileSignUp.addEventListener('click', (e) => {
                    e.preventDefault();
                    togglePanel(true);
                });
            }

            if (mobileSignIn) {
                mobileSignIn.addEventListener('click', (e) => {
                    e.preventDefault();
                    togglePanel(false);
                });
            }

            adminLoginLink.addEventListener('click', () => {
                adminLoginContainer.style.display = 'flex';
                setTimeout(() => {
                    adminLoginContainer.classList.add('active');
                    document.getElementById('admin_password').focus();
                }, 10);
                createParticleEffect();
            });

            adminLoginClose.addEventListener('click', () => {
                adminLoginContainer.classList.remove('active');
                setTimeout(() => {
                    adminLoginContainer.style.display = 'none';
                    const particles = document.querySelector('.particles');
                    if (particles) particles.remove();
                }, 400);
            });

            window.addEventListener('click', (e) => {
                if (e.target === adminLoginContainer) {
                    adminLoginContainer.classList.remove('active');
                    setTimeout(() => {
                        adminLoginContainer.style.display = 'none';
                        const particles = document.querySelector('.particles');
                        if (particles) particles.remove();
                    }, 400);
                }
            });

            function createParticleEffect() {
                if (document.querySelector('.particles')) return;

                const particles = document.createElement('div');
                particles.className = 'particles';
                particles.style.position = 'fixed';
                particles.style.top = '0';
                particles.style.left = '0';
                particles.style.width = '100%';
                particles.style.height = '100%';
                particles.style.zIndex = '999';
                particles.style.pointerEvents = 'none';

                adminLoginContainer.insertBefore(particles, adminLoginContainer.firstChild);

                for (let i = 0; i < 50; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'particle';
                    particle.style.position = 'absolute';
                    particle.style.width = Math.random() * 5 + 2 + 'px';
                    particle.style.height = particle.style.width;
                    particle.style.background = `rgba(${Math.floor(Math.random() * 80) + 80}, ${Math.floor(Math.random() * 40) + 40}, ${Math.floor(Math.random() * 160) + 100}, ${Math.random() * 0.5 + 0.2})`;
                    particle.style.borderRadius = '50%';
                    particle.style.top = Math.random() * 100 + '%';
                    particle.style.left = Math.random() * 100 + '%';
                    particle.style.filter = 'blur(1px)';
                    particle.style.boxShadow = '0 0 10px rgba(81, 45, 168, 0.5)';
                    particle.style.animation = `float-particle ${Math.random() * 10 + 5}s linear infinite`;
                    particles.appendChild(particle);
                }

                if (!document.getElementById('particle-keyframes')) {
                    const style = document.createElement('style');
                    style.id = 'particle-keyframes';
                    style.innerHTML = `
                        @keyframes float-particle {
                            0% {
                                transform: translateY(0) rotate(0deg);
                                opacity: ${Math.random() * 0.5 + 0.5};
                            }
                            50% {
                                transform: translateY(-100px) rotate(180deg);
                                opacity: ${Math.random() * 0.2 + 0.2};
                            }
                            100% {
                                transform: translateY(0) rotate(360deg);
                                opacity: ${Math.random() * 0.5 + 0.5};
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            function validateInput(input, regex, errorElement, errorMessage) {
                const isValid = regex.test(input.value);
                if (!isValid) {
                    input.classList.add('input-error');
                    errorElement.style.display = 'block';
                    errorElement.textContent = errorMessage;
                    input.classList.add('btn-shake');
                    setTimeout(() => input.classList.remove('btn-shake'), 1000);
                } else {
                    input.classList.remove('input-error');
                    errorElement.style.display = 'none';
                    if (!input.parentElement.querySelector('.form-success-indicator')) {
                        const successIcon = document.createElement('i');
                        successIcon.className = 'fa-solid fa-check form-success-indicator';
                        input.parentElement.appendChild(successIcon);
                    }
                    input.parentElement.classList.add('input-success');
                }
                return isValid;
            }

            registerForm.addEventListener('submit', function(e) {
                let isValid = true;

                const tentaikhoan = document.getElementById('tentaikhoan');
                const tentaikhoanError = document.getElementById('tentaikhoan_error');
                isValid = validateInput(
                    tentaikhoan,
                    /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/,
                    tentaikhoanError,
                    'Tài khoản phải có ít nhất 8 ký tự và bao gồm cả chữ và số!'
                ) && isValid;

                const email = document.getElementById('email');
                const emailError = document.getElementById('email_error');
                isValid = validateInput(
                    email,
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                    emailError,
                    'Email không hợp lệ!'
                ) && isValid;

                const matkhau = document.getElementById('register_matkhau');
                const matkhauError = document.getElementById('register_matkhau_error');
                isValid = validateInput(
                    matkhau,
                    /.{6,}/,
                    matkhauError,
                    'Mật khẩu phải có ít nhất 6 ký tự!'
                ) && isValid;

                if (!isValid) {
                    e.preventDefault();
                } else {
                    const submitBtn = document.querySelector('#registerForm button');
                    submitBtn.classList.add('animate__pulse');
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
                    document.querySelector('.sign-up-container form').classList.add('form-slide-up');
                }
            });

            loginForm.addEventListener('submit', function(e) {
                let isValid = true;

                const loginInput = document.getElementById('login_input');
                const loginInputError = document.getElementById('login_input_error');
                isValid = validateInput(
                    loginInput,
                    /.+/,
                    loginInputError,
                    'Vui lòng nhập email hoặc tài khoản!'
                ) && isValid;

                const matkhau = document.getElementById('login_matkhau');
                const matkhauError = document.getElementById('login_matkhau_error');
                isValid = validateInput(
                    matkhau,
                    /.+/,
                    matkhauError,
                    'Vui lòng nhập mật khẩu!'
                ) && isValid;

                if (!isValid) {
                    e.preventDefault();
                } else {
                    const submitBtn = document.querySelector('#loginForm button');
                    submitBtn.classList.add('animate__pulse');
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
                    document.querySelector('.sign-in-container form').classList.add('form-slide-up');
                }
            });

            adminForm.addEventListener('submit', function(e) {
                let isValid = true;

                const adminPassword = document.getElementById('admin_password');
                const adminPasswordError = document.getElementById('admin_password_error');
                isValid = validateInput(
                    adminPassword,
                    /.+/,
                    adminPasswordError,
                    'Vui lòng nhập mật khẩu admin!'
                ) && isValid;

                if (!isValid) {
                    e.preventDefault();
                } else {
                    const submitBtn = document.querySelector('#adminForm button');
                    submitBtn.classList.add('animate__pulse');
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
                    document.querySelector('.admin-login-form').style.boxShadow = '0 0 20px rgba(81, 45, 168, 0.6)';
                    setTimeout(() => {
                        document.querySelector('.admin-login-form').style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.3)';
                    }, 1000);
                }
            });

            const allInputs = document.querySelectorAll('input');
            allInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('input-focus');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('input-focus');
                });
            });

            setTimeout(() => {
                const visibleForm = container.classList.contains('right-panel-active') ? 
                    '.sign-up-container' : '.sign-in-container';
                const formElements = document.querySelectorAll(`${visibleForm} h1, ${visibleForm} input, ${visibleForm} button`);
                formElements.forEach((el, index) => {
                    el.style.opacity = 0;
                    el.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        el.style.transition = 'all 0.5s ease';
                        el.style.opacity = 1;
                        el.style.transform = 'translateY(0)';
                    }, 100 + (index * 100));
                });
            }, 300);
        });
    </script>
</body>
</html>