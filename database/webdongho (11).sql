-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2025 at 08:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `webdongho`
--

-- --------------------------------------------------------

--
-- Table structure for table `danhgia`
--

CREATE TABLE `danhgia` (
  `id` int(11) NOT NULL,
  `taikhoan_id` int(11) NOT NULL,
  `vatpham_id` int(11) NOT NULL,
  `sosao` tinyint(1) NOT NULL CHECK (`sosao` between 1 and 5),
  `binhluan` text DEFAULT NULL,
  `thoigian` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `giohang`
--

CREATE TABLE `giohang` (
  `id` int(11) NOT NULL,
  `taikhoan_id` int(11) NOT NULL,
  `vatpham_id` int(11) DEFAULT NULL,
  `phukien_id` int(11) DEFAULT NULL,
  `soluong` int(11) NOT NULL DEFAULT 1,
  `thoigian` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lichsunap`
--

CREATE TABLE `lichsunap` (
  `id` int(11) NOT NULL,
  `uid` varchar(50) NOT NULL,
  `taikhoan_id` int(11) NOT NULL,
  `phuongthuc` enum('Mua Hàng','Momo','Banking','Paypal') NOT NULL DEFAULT 'Momo',
  `coin` int(11) NOT NULL DEFAULT 0,
  `trangthai` enum('thành công','thất bại','đang xử lý') NOT NULL DEFAULT 'đang xử lý',
  `thoigian` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `lichsunap`
--
DELIMITER $$
CREATE TRIGGER `update_coin_after_lichsunap_insert` AFTER INSERT ON `lichsunap` FOR EACH ROW BEGIN
  IF NEW.trangthai = 'đang xử lý' AND NEW.coin > 0 THEN
    UPDATE taikhoan
    SET coin = coin + NEW.coin
    WHERE id = NEW.taikhoan_id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `lichsuthanhtoan`
--

CREATE TABLE `lichsuthanhtoan` (
  `id` int(11) NOT NULL,
  `taikhoan_id` int(11) NOT NULL,
  `vatpham_id` int(11) DEFAULT NULL,
  `phukien_id` int(11) DEFAULT NULL,
  `coin_id` int(11) NOT NULL,
  `sll` int(11) NOT NULL DEFAULT 1,
  `trangthai` enum('đã thanh toán','chưa thanh toán') NOT NULL DEFAULT 'chưa thanh toán',
  `thoigian` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phukien`
--

CREATE TABLE `phukien` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `loaiphukien` enum('Dây đồng hồ','Hộp đựng đồng hồ','Máy lên dây cót','Kính bảo vệ màn hình') NOT NULL,
  `giatien` int(11) NOT NULL,
  `mota` text DEFAULT NULL,
  `sll` int(11) NOT NULL,
  `uid_phukien` varchar(20) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `taikhoan`
--

CREATE TABLE `taikhoan` (
  `id` int(11) NOT NULL,
  `tentaikhoan` varchar(50) NOT NULL,
  `matkhau` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `avatar` text DEFAULT NULL,
  `coin` int(11) DEFAULT 0,
  `google_id` varchar(50) DEFAULT NULL,
  `facebook_id` varchar(50) DEFAULT NULL,
  `login_method` enum('normal','google','facebook') DEFAULT 'normal',
  `status` enum('active','banned') DEFAULT 'active',
  `thoigian` timestamp NOT NULL DEFAULT current_timestamp(),
  `fullname` varchar(100) DEFAULT NULL,
  `phone` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `taikhoan`
--

INSERT INTO `taikhoan` (`id`, `tentaikhoan`, `matkhau`, `email`, `avatar`, `coin`, `google_id`, `facebook_id`, `login_method`, `status`, `thoigian`, `fullname`, `phone`) VALUES
(1, '1', '1', 'levantri@gmail.com', NULL, 0, NULL, NULL, 'normal', 'active', '2025-04-26 16:48:40', NULL, NULL),
(2, 'vantri12', 'vantri12', 'vantri@gmail.com', NULL, 0, NULL, NULL, 'normal', 'active', '2025-04-26 17:50:54', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vatpham`
--

CREATE TABLE `vatpham` (
  `id` int(11) NOT NULL,
  `tenvatpham` varchar(100) NOT NULL,
  `mota` text DEFAULT NULL,
  `giatien` int(11) NOT NULL DEFAULT 0,
  `loaisanpham` enum('cơ','quartz','điện tử','thông minh') NOT NULL,
  `sll` int(11) NOT NULL DEFAULT 0,
  `uid_vatpham` varchar(20) NOT NULL,
  `url` text DEFAULT NULL,
  `chatlieu` enum('kim loại','da','silicone','vải và nato','milamese') NOT NULL,
  `gioitinh` enum('Nam','Nữ') NOT NULL,
  `thuonghieu` enum('Rolex','Omega','Patek Philippe','Hublot','TAG Heuer','Seiko','Tissot','Orient','Bulova','Casio','Fossil','Timex','Daniel Wellington') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `danhgia`
--
ALTER TABLE `danhgia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taikhoan_id` (`taikhoan_id`),
  ADD KEY `vatpham_id` (`vatpham_id`);

--
-- Indexes for table `giohang`
--
ALTER TABLE `giohang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taikhoan_id` (`taikhoan_id`),
  ADD KEY `vatpham_id` (`vatpham_id`),
  ADD KEY `phukien_id` (`phukien_id`);

--
-- Indexes for table `lichsunap`
--
ALTER TABLE `lichsunap`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taikhoan_id` (`taikhoan_id`);

--
-- Indexes for table `lichsuthanhtoan`
--
ALTER TABLE `lichsuthanhtoan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taikhoan_id` (`taikhoan_id`),
  ADD KEY `vatpham_id` (`vatpham_id`),
  ADD KEY `phukien_id` (`phukien_id`),
  ADD KEY `coin_id` (`coin_id`);

--
-- Indexes for table `phukien`
--
ALTER TABLE `phukien`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vatpham`
--
ALTER TABLE `vatpham`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `danhgia`
--
ALTER TABLE `danhgia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `giohang`
--
ALTER TABLE `giohang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lichsunap`
--
ALTER TABLE `lichsunap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lichsuthanhtoan`
--
ALTER TABLE `lichsuthanhtoan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phukien`
--
ALTER TABLE `phukien`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `taikhoan`
--
ALTER TABLE `taikhoan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vatpham`
--
ALTER TABLE `vatpham`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
