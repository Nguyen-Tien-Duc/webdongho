<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/login1.php");
    exit();
}

require_once '../database/db.php'; // Kết nối cơ sở dữ liệu
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Kiểm tra trạng thái đăng nhập
$isLoggedIn = isset($_SESSION['tentaikhoan']);
$tentaikhoan = $isLoggedIn ? $_SESSION['tentaikhoan'] : '';

// Lấy thông tin người dùng nếu đã đăng nhập
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM taikhoan WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Lấy số lượng sản phẩm trong giỏ hàng
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giohang WHERE taikhoan_id = ?");
    $stmt->execute([$user_id]);
    $cartCount = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ - Group 3</title>
    <link rel="stylesheet" href="../frontend/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
      /* General Wrapper */
      #wrapper {
          padding: 20px 0;
      }

      /* Container */
      .container {
          max-width: 1200px;
          margin: 0 auto;
          padding: 0 15px;
      }

      /* Title Line Styling */
      .title-line {
          text-align: center;
          margin-bottom: 40px;
      }

      .title-line h3 {
          font-size: 2.5rem;
          font-weight: 700;
          color: #333;
          text-transform: uppercase;
          position: relative;
          display: inline-block;
          padding-bottom: 10px;
      }

      .title-line h3::after {
          content: '';
          position: absolute;
          bottom: 0;
          left: 50%;
          transform: translateX(-50%);
          width: 50px;
          height: 3px;
          background-color: #9d2a28;
      }

      /* About Us Section */
      .about-us {
          margin-bottom: 60px;
      }

      .box-content {
          background-color: #f8f9fa;
          padding: 30px;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      }

      .content p {
          font-size: 1.1rem;
          color: #555;
          line-height: 1.6;
          margin-bottom: 15px;
      }
      .btn-primary {
          display: inline-block;
          padding: 10px 20px;
          background-color: #9d2a28;
          color: #fff;
          text-decoration: none;
          border-radius: 5px;
          font-weight: 500;
          transition: background-color 0.3s ease;
      }

      .btn-primary:hover {
          background-color: #ea0905;
      }

      /* Management Team Section */
      .management-team {
          margin-bottom: 60px;
      }

      .list-management {
          display: flex;
          justify-content: space-between;
          gap: 30px;
      }

      .management-column {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 20px;
      }

      .list-management-item {
          display: flex;
          align-items: center;
          background-color: #fff;
          padding: 20px;
          border-radius: 8px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
          transition: transform 0.3s ease;
      }

      .list-management-item:hover {
          transform: translateY(-5px);
      }

      .management-avatar {
          flex: 0 0 100px;
      }

      .management-avatar img {
          width: 100px;
          height: 100px;
          border-radius: 50%;
          object-fit: cover;
          border: 2px solid #9d2a28;
      }

      .management-info {
          flex: 1;
          padding-left: 20px;
      }

      .management-info h3 {
          font-size: 1.5rem;
          font-weight: 600;
          color: #333;
          margin-bottom: 5px;
      }

      .management-info .role {
          font-size: 1rem;
          color: #ff0400;
          font-weight: 500;
          margin-bottom: 5px;
      }

      .management-info p {
          font-size: 1rem;
          color: #555;
          margin-bottom: 5px;
      }

      /* Contact Info Section */
      .contact-info {
          text-align: center;
          margin-bottom: 60px;
      }

      .social-media {
          display: flex;
          justify-content: center;
          gap: 20px;
      }

      .social-icon {
      display: inline-block;
      width: 50px;
      height: 50px;
      background-size: cover;
      background-position: center;
      border-radius: 50%;
      transition: transform 0.3s ease;
  }

      .social-icon.facebook {
          background-image: url('../frontend/assets/images/facebook.png'); /* Đường dẫn đến logo Facebook */
      }

      .social-icon.zalo {
          background-image: url('../frontend/assets/images/zalo.png'); /* Đường dẫn đến logo Zalo */
      }

      .social-icon.gmail {
          background-image: url('../frontend/assets/images/gmail.png'); /* Đường dẫn đến logo Gmail */
      }

      .social-icon:hover {
          transform: scale(1.1); /* Hiệu ứng phóng to khi hover */
      }

      /* Responsive Design */
      @media (max-width: 768px) {
          .list-management {
              flex-direction: column;
          }

          .title-line h3 {
              font-size: 2rem;
          }

          .management-avatar img {
              width: 80px;
              height: 80px;
          }

          .social-icon {
              width: 40px;
              height: 40px;
          }
      }

      @media (max-width: 576px) {
          .list-management-item {
              flex-direction: column;
              text-align: center;
          }

          .management-avatar {
              margin-bottom: 15px;
          }

          .management-info {
              padding-left: 0;
          }

          .title-line h3 {
              font-size: 1.8rem;
          }
      }
      .btn-primary {
      display: inline-block;
      padding: 10px 20px;
      background-color: #9d2a28;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
      font-weight: 500;
      transition: background-color 0.3s ease;
      }

      .btn-primary:hover {
          background-color: #9d2a28;
      }

      /* Add this CSS to center the button */
      .btn-primary-wrapper {
          display: flex;
          justify-content: center;
          align-items: center;
          margin-top: 20px; /* Optional: Add spacing above */
      }
      /* Tooltip styling */
      .social-icon {
          position: relative; /* Để tooltip định vị tương đối với icon */
      }

      .social-icon::after {
          content: attr(title); /* Lấy nội dung từ thuộc tính title */
          position: absolute;
          bottom: 100%; /* Hiển thị phía trên icon */
          left: 50%;
          transform: translateX(-50%);
          background-color: #333;
          color: #fff;
          padding: 5px 10px;
          border-radius: 4px;
          font-size: 0.9rem;
          white-space: nowrap;
          opacity: 0; /* Ẩn mặc định */
          visibility: hidden;
          transition: opacity 0.3s ease, visibility 0.3s ease;
          z-index: 10;
      }

      .social-icon:hover::after {
          opacity: 1; /* Hiển thị khi hover */
          visibility: visible;
      }

      /* Đảm bảo tooltip không bị cắt trên thiết bị nhỏ */
      @media (max-width: 576px) {
          .social-icon::after {
              font-size: 0.8rem;
              padding: 4px 8px;
          }
      }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="header-top">
            <div class="container">
                <div class="promotion-banner">
                    <a href="#">BỘ SƯU TẬP ĐỒNG HỒ MỚI VỀ!</a>
                    <p>ĐĂNG KÝ NHẬN THÔNG TIN MỚI NHẤT!</p>
                    <p>GẶP CHUYÊN GIA TƯ VẤN!</p>
                </div>
            </div>
        </div>
        <div class="header-main">
            <div class="container">
                <div class="logo">
                    <a href="index.php">
                        <img src="../frontend/assets/images/logo1.png" alt="Logo">
                    </a>
                </div>
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Tìm kiếm" autocomplete="off">
                    <button type="submit" class="search-button"></button>
                    <div id="search-suggestions" class="search-suggestions"></div>
                </div>
                <div class="header-actions">
                    <div class="cart">
                        <a href="../backend/cart.php" class="cart-icon">
                            <span class="cart-count" id="cart-count"><?= $cartCount ?></span>
                        </a>
                    </div>
                    <div class="user-section">
                        <?php if (!$isLoggedIn): ?>
                            <div class="login-btn" id="login-btn">
                                <a href="/login/login1.php" class="action-button">Đăng nhập</a>
                            </div>
                        <?php else: ?>
                            <div class="user-info" id="user-info">
                                <a href="profile.php" class="user-logo">
                                    <?php
                                    $base_url = "/webdongho";
                                    ?>
                                    <img src="<?= $user['avatar'] ? $base_url . '/' . htmlspecialchars($user['avatar']) : $base_url . '../frontend/assets/images/anh-dai-dien.png' ?>" alt="Avatar" width="120" height="120">
                                </a>
                                <span class="account-name" id="account-name"><?= htmlspecialchars($tentaikhoan) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <div class="account-btn">
                            <a href="logout.php" class="action-button">Đăng Xuất</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="container">
            <div class="banner-slider">
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner.png" alt="Banner 1">
                    <div class="banner-caption">
                        <h2>Khám Phá Bộ Sưu Tập Đồng Hồ</h2>
                        <p>Chất lượng vượt trội, phong cách dẫn đầu</p>
                    </div>
                </div>
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner1.png" alt="Banner 2">
                    <div class="banner-caption">
                        <h2>Đồng Hồ Cao Cấp</h2>
                        <p>Thể hiện đẳng cấp của bạn</p>
                    </div>
                </div>
                <div class="banner-slide">
                    <img src="../frontend/assets/images/baner2.png" alt="Banner 3">
                    <div class="banner-caption">
                        <h2>Ưu Đãi Đặc Biệt</h2>
                        <p>Nhanh tay sở hữu ngay hôm nay!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div id="wrapper">
        <div id="wp-content" class="container">
            <!-- Group Information -->
            <section class="about-us" id="about-us">
                <div class="title-line">
                    <h3>THÔNG TIN NHÓM</h3>
                </div>
                <div class="box-content">
                    <div class="content">
                        <p>
                            Nhóm 3 được thành lập với sứ mệnh xây dựng một website bán đồng hồ trực tuyến chuyên nghiệp, mang đến trải nghiệm mua sắm tiện lợi và đáng tin cậy. Chúng tôi là đội ngũ 4 thành viên đầy nhiệt huyết, mỗi người đảm nhận vai trò riêng biệt từ thiết kế giao diện, lập trình, quản lý nội dung đến kiểm thử chất lượng.
                        </p>
                        <p>
                            Lấy cảm hứng từ thương hiệu Đồng Hồ Hải Triều, dự án của chúng tôi hướng đến việc tạo ra một nền tảng thương mại điện tử hiện đại, đáp ứng nhu cầu của những người yêu thích đồng hồ chính hãng. Chúng tôi không ngừng học hỏi và cải thiện để mang đến sản phẩm chất lượng nhất.
                        </p>
                        <div class="btn-primary-wrapper">
                          <a href="#contact-info" class="btn btn-primary">Liên Hệ Với Chúng Tôi</a>
                        </div>
                    </div>
                </div>
            </section>

           <!-- Team Members (Chỉnh sửa) -->
           <section class="management-team" id="team">
            <div class="title-line">
                <h3>THÀNH VIÊN NHÓM</h3>
            </div>
            <div class="list-management">
                <div class="management-column">
                    <div class="list-management-item">
                        <div class="management-avatar">
                            <img src="../frontend/assets/images/anh-dai-dien.png" alt="Trương Văn Lợi">
                        </div>
                        <div class="management-info">
                            <h3>LÊ VĂN TRÍ</h3>
                            <p class="role">Trưởng Nhóm</p>
                            <p>MSSV: 054205002706</p>
                            <p>Vai trò: Quản lý dự án, lập trình backend</p>
                        </div>
                    </div>
                    <div class="list-management-item">
                        <div class="management-avatar">
                            <img src="../frontend/assets/images/anh-dai-dien.png" alt="Nguyễn Thị Mai">
                        </div>
                        <div class="management-info">
                            <h3>NGUYỄN TIẾN ĐỨC</h3>
                            <p class="role">Thành Viên</p>
                            <p>MSSV: 058204001364</p>
                            <p>Vai trò: Thiết kế giao diện, lập trình frontend</p>
                        </div>
                    </div>
                </div>
                <div class="management-column">
                    <div class="list-management-item">
                        <div class="management-avatar">
                            <img src="../frontend/assets/images/anh-dai-dien.png" alt="Phạm Văn Hưng">
                        </div>
                        <div class="management-info">
                            <h3>NGUYỄN NGỰ ĐĂNG</h3>
                            <p class="role">Thành Viên</p>
                            <p>MSSV: 054205005327</p>
                            <p>Vai trò: Quản lý nội dung, kiểm thử chất lượng</p>
                        </div>
                    </div>
                    <div class="list-management-item">
                        <div class="management-avatar">
                            <img src="../frontend/assets/images/anh-dai-dien.png" alt="Lê Thị Hoa">
                        </div>
                        <div class="management-info">
                            <h3>NGUYỄN HOÀNG MINH NHẬT</h3>
                            <p class="role">Thành Viên</p>
                            <p>MSSV: 054205002758</p>
                            <p>Vai trò: Hỗ trợ lập trình, quản lý tài liệu</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-info" id="contact-info">
          <div class="title-line">
              <h3>LIÊN HỆ</h3>
          </div>
          <div class="social-media">
              <a href="https://www.facebook.com/uctien.299663" class="social-icon facebook" aria-label="Facebook của NGUYỄN TIẾN ĐỨC" title="Theo dõi chúng tôi trên Facebook"></a>
              <a href="https://zalo.me/0565688921" class="social-icon zalo" aria-label="Zalo của NGUYỄN TIẾN ĐỨC" title="Liên hệ qua Zalo"></a>
              <a href="mailto:okokmen07@gmail.com?subject=Liên%20hệ%20với%20Group%203&body=Xin%20chào,%20tôi%20muốn%20liên%20hệ%20về%20dự%20án%20website%20bán%20đồng%20hồ." class="social-icon gmail" aria-label="Email của NGUYỄN TIẾN ĐỨC" title="Gửi email cho chúng tôi"></a>
          </div>
      </section>
    </div>
</div>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-top">
          <div class="container">
              <div class="footer-columns">
                  <div class="footer-column">
                      <h3>THÔNG TIN</h3>
                      <ul>
                          <li><a href="#">Về chúng tôi</a></li>
                          <li><a href="#">Chính sách bảo mật</a></li>
                          <li><a href="#">Điều khoản sử dụng</a></li>
                          <li><a href="#">Tuyển dụng</a></li>
                      </ul>
                  </div>
                  <div class="footer-column">
                      <h3>HỖ TRỢ</h3>
                      <ul>
                          <li><a href="#">Hướng dẫn mua hàng</a></li>
                          <li><a href="#">Chính sách đổi trả</a></li>
                          <li><a href="#">Chính sách bảo hành</a></li>
                          <li><a href="#">FAQ</a></li>
                      </ul>
                  </div>
                  <div class="footer-column">
                      <h3>LIÊN HỆ</h3>
                      <ul>
                          <li>Hotline: 1900.6777</li>
                          <li>Email: okokmen07@gmail.com</li>
                          <li>Địa chỉ: 70 Tô Ký</li>
                          <li>Thời gian làm việc: 9:00 - 21:30</li>
                      </ul>
                  </div>
                  <div class="footer-column">
                      <h3>THEO DÕI CHÚNG TÔI</h3>
                      <div class="social-media">
                          <a href="#" class="social-icon facebook"></a>
                          <a href="#" class="social-icon instagram"></a>
                          <a href="#" class="social-icon youtube"></a>
                          <a href="#" class="social-icon tiktok"></a>
                      </div>
                      <div class="payment-methods">
                          <img src="https://ext.same-assets.com/1280460479/1923935881.png" alt="Thanh toán">
                      </div>
                  </div>
              </div>
          </div>
      </div>
      <div class="footer-bottom">
          <div class="container">
              <p>© 2025 Đồng Hồ GROUP 3. All Rights Reserved.</p>
          </div>
      </div>
  </footer>
  
</body>
</html>