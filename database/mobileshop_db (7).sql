-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 03, 2026 at 06:33 AM
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
-- Database: `mobileshop_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(6) NOT NULL,
  `home_no` varchar(20) DEFAULT NULL,
  `moo` varchar(20) DEFAULT NULL,
  `soi` varchar(50) DEFAULT NULL,
  `road` varchar(50) DEFAULT NULL,
  `village` varchar(50) DEFAULT NULL,
  `subdistricts_subdistrict_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `home_no`, `moo`, `soi`, `road`, `village`, `subdistricts_subdistrict_id`) VALUES
(0, NULL, NULL, NULL, NULL, NULL, 100101),
(3, NULL, NULL, NULL, NULL, NULL, 100107),
(4, '', '', '', '', NULL, 100110),
(5, '', '', '', '', '', 100102),
(6, '', '', '', '', '', 100110),
(7, '', '', '', '', '', 100111),
(8, '', '', '', '', '', 100107),
(9, '', '', '', '', '', 100110),
(10, '', '', '', '', '', 100110),
(11, '', '', '', '', '', 100107),
(12, '', '', '', '', '', 100110),
(13, '', '', '', '', '', 100111),
(14, '', '', '', '', '', 100111),
(15, '', '', '', '', '', 100110),
(16, NULL, NULL, NULL, NULL, NULL, 100101);

-- --------------------------------------------------------

--
-- Table structure for table `bill_details`
--

CREATE TABLE `bill_details` (
  `detail_id` int(11) NOT NULL,
  `amount` int(3) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `bill_headers_bill_id` int(11) NOT NULL,
  `products_prod_id` int(6) NOT NULL,
  `prod_stocks_stock_id` int(6) DEFAULT NULL,
  `warranty_duration_months` int(3) DEFAULT NULL COMMENT 'ระยะประกัน (เดือน) ที่ตกลงตอนขาย',
  `warranty_note` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุการรับประกันเฉพาะบิลนี้'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_details`
--

INSERT INTO `bill_details` (`detail_id`, `amount`, `price`, `create_at`, `update_at`, `bill_headers_bill_id`, `products_prod_id`, `prod_stocks_stock_id`, `warranty_duration_months`, `warranty_note`) VALUES
(0, 1, 350.00, '2026-01-03 12:25:45', '2026-01-03 12:25:45', 4, 100001, NULL, NULL, NULL),
(1, 1, 15000.00, '2026-01-03 09:39:32', '2026-01-03 09:39:32', 1, 100002, 100001, NULL, NULL),
(2, 1, 15000.00, '2026-01-03 09:39:32', '2026-01-03 09:39:32', 1, 100002, 100008, NULL, NULL),
(3, 1, 15000.00, '2026-01-03 10:12:24', '2026-01-03 10:12:24', 2, 100002, 100006, NULL, NULL),
(4, 1, 15000.00, '2026-01-03 10:13:47', '2026-01-03 10:13:47', 3, 100002, 100007, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bill_headers`
--

CREATE TABLE `bill_headers` (
  `bill_id` int(11) NOT NULL,
  `bill_date` datetime NOT NULL,
  `receipt_date` datetime NOT NULL,
  `payment_method` enum('Cash','Credit','Banking','QR') NOT NULL,
  `bill_status` enum('Pending','Completed','Canceled') NOT NULL,
  `vat` decimal(10,2) NOT NULL,
  `comment` varchar(50) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `customers_cs_id` int(6) NOT NULL,
  `bill_type` enum('Sale','Repair') NOT NULL,
  `branches_branch_id` int(3) NOT NULL,
  `employees_emp_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_headers`
--

INSERT INTO `bill_headers` (`bill_id`, `bill_date`, `receipt_date`, `payment_method`, `bill_status`, `vat`, `comment`, `discount`, `create_at`, `update_at`, `customers_cs_id`, `bill_type`, `branches_branch_id`, `employees_emp_id`) VALUES
(1, '2026-01-03 09:39:32', '2026-01-03 09:39:39', 'Cash', 'Completed', 7.00, '', 0.00, '2026-01-03 09:39:32', '2026-01-03 09:39:32', 100001, 'Sale', 1, 10),
(2, '2026-01-03 10:12:24', '2026-01-03 10:13:08', 'Cash', 'Completed', 7.00, '', 0.00, '2026-01-03 10:12:24', '2026-01-03 10:12:24', 100001, 'Sale', 1, 10),
(3, '2026-01-03 10:13:41', '2026-01-03 10:13:41', 'Cash', 'Canceled', 7.00, ' [ยกเลิก: ทำผิด]', 0.00, '2026-01-03 10:13:41', '2026-01-03 10:22:39', 100001, 'Sale', 1, 10),
(4, '2026-01-03 12:21:55', '2026-01-03 12:25:54', 'Cash', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2026-01-03 12:21:55', '2026-01-03 12:21:55', 100001, 'Repair', 1, 9);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `branch_id` int(3) NOT NULL,
  `branch_code` varchar(30) DEFAULT NULL,
  `branch_name` varchar(50) NOT NULL,
  `branch_phone` varchar(20) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `Addresses_address_id` int(6) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `branch_phone`, `create_at`, `update_at`, `Addresses_address_id`, `shop_info_shop_id`) VALUES
(1, '', 'สาขา1', '', '2025-12-24 11:30:48', '2025-12-24 11:30:48', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `cs_id` int(6) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `branches_branch_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสสาขาที่ลูกค้าสังกัด',
  `cs_national_id` varchar(20) DEFAULT NULL,
  `firstname_th` varchar(30) NOT NULL,
  `lastname_th` varchar(30) NOT NULL,
  `firstname_en` varchar(30) DEFAULT NULL,
  `lastname_en` varchar(30) DEFAULT NULL,
  `cs_phone_no` varchar(20) NOT NULL,
  `cs_email` varchar(75) DEFAULT NULL,
  `cs_line_id` varchar(30) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `prefixs_prefix_id` int(6) NOT NULL,
  `Addresses_address_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`cs_id`, `shop_info_shop_id`, `branches_branch_id`, `cs_national_id`, `firstname_th`, `lastname_th`, `firstname_en`, `lastname_en`, `cs_phone_no`, `cs_email`, `cs_line_id`, `create_at`, `update_at`, `prefixs_prefix_id`, `Addresses_address_id`) VALUES
(100001, 1, 1, '', 'ดด', 'ดด', '', '', '0888888888', '', '', '2026-01-02 09:33:13', '2026-01-02 09:33:13', 100001, 6);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(4) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `branches_branch_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสสาขาที่สังกัด',
  `dept_name` varchar(50) NOT NULL,
  `dept_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `shop_info_shop_id`, `branches_branch_id`, `dept_name`, `dept_desc`, `create_at`, `update_at`) VALUES
(0, 0, 0, 'Partner (เจ้าของร้าน)', NULL, '2025-12-18 09:58:06', '2025-12-18 09:58:06'),
(1, 1, 0, 'ผู้จัดการร้าน', '', '2025-12-14 22:50:09', '2026-01-02 10:47:12'),
(2, 1, 0, 'Partner', '', '2025-12-18 09:59:53', '2026-01-02 10:47:09'),
(3, 0, 0, 'HR', '', '2025-12-24 03:37:29', '2025-12-24 03:37:29'),
(4, 0, 0, 'ช่างซ่อม', '', '2026-01-02 10:45:24', '2026-01-02 10:45:24'),
(5, 1, 1, 'ช่างซ่อม', '', '2026-01-02 10:54:31', '2026-01-02 10:54:31');

-- --------------------------------------------------------

--
-- Table structure for table `dept_permissions`
--

CREATE TABLE `dept_permissions` (
  `departments_dept_id` int(4) NOT NULL,
  `permissions_permission_id` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(4) NOT NULL,
  `district_name_th` varchar(50) NOT NULL,
  `district_name_en` varchar(50) DEFAULT NULL,
  `provinces_province_id` int(2) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสร้านค้า (0=ส่วนกลาง)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `district_name_th`, `district_name_en`, `provinces_province_id`, `shop_info_shop_id`) VALUES
(1001, 'เขตพระนคร', 'Khet Phra Nakhon', 1, 0),
(1002, 'เขตดุสิต', 'Khet Dusit', 1, 0),
(1003, 'เขตหนองจอก', 'Khet Nong Chok', 1, 0),
(1004, 'เขตบางรัก', 'Khet Bang Rak', 1, 0),
(1005, 'เขตบางเขน', 'Khet Bang Khen', 1, 0),
(1006, 'เขตบางกะปิ', 'Khet Bang Kapi', 1, 0),
(1007, 'เขตปทุมวัน', 'Khet Pathum Wan', 1, 0),
(1008, 'เขตป้อมปราบศัตรูพ่าย', 'Khet Pom Prap Sattru Phai', 1, 0),
(1009, 'เขตพระโขนง', 'Khet Phra Khanong', 1, 0),
(1010, 'เขตมีนบุรี', 'Khet Min Buri', 1, 0),
(1011, 'เขตลาดกระบัง', 'Khet Lat Krabang', 1, 0),
(1012, 'เขตยานนาวา', 'Khet Yan Nawa', 1, 0),
(1013, 'เขตสัมพันธวงศ์', 'Khet Samphanthawong', 1, 0),
(1014, 'เขตพญาไท', 'Khet Phaya Thai', 1, 0),
(1015, 'เขตธนบุรี', 'Khet Thon Buri', 1, 0),
(1016, 'เขตบางกอกใหญ่', 'Khet Bangkok Yai', 1, 0),
(1017, 'เขตห้วยขวาง', 'Khet Huai Khwang', 1, 0),
(1018, 'เขตคลองสาน', 'Khet Khlong San', 1, 0),
(1019, 'เขตตลิ่งชัน', 'Khet Taling Chan', 1, 0),
(1020, 'เขตบางกอกน้อย', 'Khet Bangkok Noi', 1, 0),
(1021, 'เขตบางขุนเทียน', 'Khet Bang Khun Thian', 1, 0),
(1022, 'เขตภาษีเจริญ', 'Khet Phasi Charoen', 1, 0),
(1023, 'เขตหนองแขม', 'Khet Nong Khaem', 1, 0),
(1024, 'เขตราษฎร์บูรณะ', 'Khet Rat Burana', 1, 0),
(1025, 'เขตบางพลัด', 'Khet Bang Phlat', 1, 0),
(1026, 'เขตดินแดง', 'Khet Din Daeng', 1, 0),
(1027, 'เขตบึงกุ่ม', 'Khet Bueng Kum', 1, 0),
(1028, 'เขตสาทร', 'Khet Sathon', 1, 0),
(1029, 'เขตบางซื่อ', 'Khet Bang Sue', 1, 0),
(1030, 'เขตจตุจักร', 'Khet Chatuchak', 1, 0),
(1031, 'เขตบางคอแหลม', 'Khet Bang Kho Laem', 1, 0),
(1032, 'เขตประเวศ', 'Khet Prawet', 1, 0),
(1033, 'เขตคลองเตย', 'Khet Khlong Toei', 1, 0),
(1034, 'เขตสวนหลวง', 'Khet Suan Luang', 1, 0),
(1035, 'เขตจอมทอง', 'Khet Chom Thong', 1, 0),
(1036, 'เขตดอนเมือง', 'Khet Don Mueang', 1, 0),
(1037, 'เขตราชเทวี', 'Khet Ratchathewi', 1, 0),
(1038, 'เขตลาดพร้าว', 'Khet Lat Phrao', 1, 0),
(1039, 'เขตวัฒนา', 'Khet Watthana', 1, 0),
(1040, 'เขตบางแค', 'Khet Bang Khae', 1, 0),
(1041, 'เขตหลักสี่', 'Khet Lak Si', 1, 0),
(1042, 'เขตสายไหม', 'Khet Sai Mai', 1, 0),
(1043, 'เขตคันนายาว', 'Khet Khan Na Yao', 1, 0),
(1044, 'เขตสะพานสูง', 'Khet Saphan Sung', 1, 0),
(1045, 'เขตวังทองหลาง', 'Khet Wang Thonglang', 1, 0),
(1046, 'เขตคลองสามวา', 'Khet Khlong Sam Wa', 1, 0),
(1047, 'เขตบางนา', 'Khet Bang Na', 1, 0),
(1048, 'เขตทวีวัฒนา', 'Khet Thawi Watthana', 1, 0),
(1049, 'เขตทุ่งครุ', 'Khet Thung Khru', 1, 0),
(1050, 'เขตบางบอน', 'Khet Bang Bon', 1, 0),
(1101, 'เมืองสมุทรปราการ', 'Mueang Samut Prakan', 2, 0),
(1102, 'บางบ่อ', 'Bang Bo', 2, 0),
(1103, 'บางพลี', 'Bang Phli', 2, 0),
(1104, 'พระประแดง', 'Phra Pradaeng', 2, 0),
(1105, 'พระสมุทรเจดีย์', 'Phra Samut Chedi', 2, 0),
(1106, 'บางเสาธง', 'Bang Sao Thong', 2, 0),
(1201, 'เมืองนนทบุรี', 'Mueang Nonthaburi', 3, 0),
(1202, 'บางกรวย', 'Bang Kruai', 3, 0),
(1203, 'บางใหญ่', 'Bang Yai', 3, 0),
(1204, 'บางบัวทอง', 'Bang Bua Thong', 3, 0),
(1205, 'ไทรน้อย', 'Sai Noi', 3, 0),
(1206, 'ปากเกร็ด', 'Pak Kret', 3, 0),
(1301, 'เมืองปทุมธานี', 'Mueang Pathum Thani', 4, 0),
(1302, 'คลองหลวง', 'Khlong Luang', 4, 0),
(1303, 'ธัญบุรี', 'Thanyaburi', 4, 0),
(1304, 'หนองเสือ', 'Nong Suea', 4, 0),
(1305, 'ลาดหลุมแก้ว', 'Lat Lum Kaeo', 4, 0),
(1306, 'ลำลูกกา', 'Lam Luk Ka', 4, 0),
(1307, 'สามโคก', 'Sam Khok', 4, 0),
(1401, 'พระนครศรีอยุธยา', 'Phra Nakhon Si Ayutthaya', 5, 0),
(1402, 'ท่าเรือ', 'Tha Ruea', 5, 0),
(1403, 'นครหลวง', 'Nakhon Luang', 5, 0),
(1404, 'บางไทร', 'Bang Sai', 5, 0),
(1405, 'บางบาล', 'Bang Ban', 5, 0),
(1406, 'บางปะอิน', 'Bang Pa-in', 5, 0),
(1407, 'บางปะหัน', 'Bang Pahan', 5, 0),
(1408, 'ผักไห่', 'Phak Hai', 5, 0),
(1409, 'ภาชี', 'Phachi', 5, 0),
(1410, 'ลาดบัวหลวง', 'Lat Bua Luang', 5, 0),
(1411, 'วังน้อย', 'Wang Noi', 5, 0),
(1412, 'เสนา', 'Sena', 5, 0),
(1413, 'บางซ้าย', 'Bang Sai', 5, 0),
(1414, 'อุทัย', 'Uthai', 5, 0),
(1415, 'มหาราช', 'Maha Rat', 5, 0),
(1416, 'บ้านแพรก', 'Ban Phraek', 5, 0),
(1501, 'เมืองอ่างทอง', 'Mueang Ang Thong', 6, 0),
(1502, 'ไชโย', 'Chaiyo', 6, 0),
(1503, 'ป่าโมก', 'Pa Mok', 6, 0),
(1504, 'โพธิ์ทอง', 'Pho Thong', 6, 0),
(1505, 'แสวงหา', 'Sawaeng Ha', 6, 0),
(1506, 'วิเศษชัยชาญ', 'Wiset Chai Chan', 6, 0),
(1507, 'สามโก้', 'Samko', 6, 0),
(1601, 'เมืองลพบุรี', 'Mueang Lop Buri', 7, 0),
(1602, 'พัฒนานิคม', 'Phatthana Nikhom', 7, 0),
(1603, 'โคกสำโรง', 'Khok Samrong', 7, 0),
(1604, 'ชัยบาดาล', 'Chai Badan', 7, 0),
(1605, 'ท่าวุ้ง', 'Tha Wung', 7, 0),
(1606, 'บ้านหมี่', 'Ban Mi', 7, 0),
(1607, 'ท่าหลวง', 'Tha Luang', 7, 0),
(1608, 'สระโบสถ์', 'Sa Bot', 7, 0),
(1609, 'โคกเจริญ', 'Khok Charoen', 7, 0),
(1610, 'ลำสนธิ', 'Lam Sonthi', 7, 0),
(1611, 'หนองม่วง', 'Nong Muang', 7, 0),
(1701, 'เมืองสิงห์บุรี', 'Mueang Sing Buri', 8, 0),
(1702, 'บางระจัน', 'Bang Rachan', 8, 0),
(1703, 'ค่ายบางระจัน', 'Khai Bang Rachan', 8, 0),
(1704, 'พรหมบุรี', 'Phrom Buri', 8, 0),
(1705, 'ท่าช้าง', 'Tha Chang', 8, 0),
(1706, 'อินทร์บุรี', 'In Buri', 8, 0),
(1801, 'เมืองชัยนาท', 'Mueang Chai Nat', 9, 0),
(1802, 'มโนรมย์', 'Manorom', 9, 0),
(1803, 'วัดสิงห์', 'Wat Sing', 9, 0),
(1804, 'สรรพยา', 'Sapphaya', 9, 0),
(1805, 'สรรคบุรี', 'Sankhaburi', 9, 0),
(1806, 'หันคา', 'Hankha', 9, 0),
(1807, 'หนองมะโมง', 'Nong Mamong', 9, 0),
(1808, 'เนินขาม', 'Noen Kham', 9, 0),
(1901, 'เมืองสระบุรี', 'Mueang Saraburi', 10, 0),
(1902, 'แก่งคอย', 'Kaeng Khoi', 10, 0),
(1903, 'หนองแค', 'Nong Khae', 10, 0),
(1904, 'วิหารแดง', 'Wihan Daeng', 10, 0),
(1905, 'หนองแซง', 'Nong Saeng', 10, 0),
(1906, 'บ้านหมอ', 'Ban Mo', 10, 0),
(1907, 'ดอนพุด', 'Don Phut', 10, 0),
(1908, 'หนองโดน', 'Nong Don', 10, 0),
(1909, 'พระพุทธบาท', 'Phra Phutthabat', 10, 0),
(1910, 'เสาไห้', 'Sao Hai', 10, 0),
(1911, 'มวกเหล็ก', 'Muak Lek', 10, 0),
(1912, 'วังม่วง', 'Wang Muang', 10, 0),
(1913, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 10, 0),
(2001, 'เมืองชลบุรี', 'Mueang Chon Buri', 11, 0),
(2002, 'บ้านบึง', 'Ban Bueng', 11, 0),
(2003, 'หนองใหญ่', 'Nong Yai', 11, 0),
(2004, 'บางละมุง', 'Bang Lamung', 11, 0),
(2005, 'พานทอง', 'Phan Thong', 11, 0),
(2006, 'พนัสนิคม', 'Phanat Nikhom', 11, 0),
(2007, 'ศรีราชา', 'Si Racha', 11, 0),
(2008, 'เกาะสีชัง', 'Ko Sichang', 11, 0),
(2009, 'สัตหีบ', 'Sattahip', 11, 0),
(2010, 'บ่อทอง', 'Bo Thong', 11, 0),
(2011, 'เกาะจันทร์', 'Ko Chan', 11, 0),
(2101, 'เมืองระยอง', 'Mueang Rayong', 12, 0),
(2102, 'บ้านฉาง', 'Ban Chang', 12, 0),
(2103, 'แกลง', 'Klaeng', 12, 0),
(2104, 'วังจันทร์', 'Wang Chan', 12, 0),
(2105, 'บ้านค่าย', 'Ban Khai', 12, 0),
(2106, 'ปลวกแดง', 'Pluak Daeng', 12, 0),
(2107, 'เขาชะเมา', 'Khao Chamao', 12, 0),
(2108, 'นิคมพัฒนา', 'Nikhom Phatthana', 12, 0),
(2201, 'เมืองจันทบุรี', 'Mueang Chanthaburi', 13, 0),
(2202, 'ขลุง', 'Khlung', 13, 0),
(2203, 'ท่าใหม่', 'Tha Mai', 13, 0),
(2204, 'โป่งน้ำร้อน', 'Pong Nam Ron', 13, 0),
(2205, 'มะขาม', 'Makham', 13, 0),
(2206, 'แหลมสิงห์', 'Laem Sing', 13, 0),
(2207, 'สอยดาว', 'Soi Dao', 13, 0),
(2208, 'แก่งหางแมว', 'Kaeng Hang Maeo', 13, 0),
(2209, 'นายายอาม', 'Na Yai Am', 13, 0),
(2210, 'เขาคิชฌกูฏ', 'Khoa Khitchakut', 13, 0),
(2301, 'เมืองตราด', 'Mueang Trat', 14, 0),
(2302, 'คลองใหญ่', 'Khlong Yai', 14, 0),
(2303, 'เขาสมิง', 'Khao Saming', 14, 0),
(2304, 'บ่อไร่', 'Bo Rai', 14, 0),
(2305, 'แหลมงอบ', 'Laem Ngop', 14, 0),
(2306, 'เกาะกูด', 'Ko Kut', 14, 0),
(2307, 'เกาะช้าง', 'Ko Chang', 14, 0),
(2401, 'เมืองฉะเชิงเทรา', 'Mueang Chachoengsao', 15, 0),
(2402, 'บางคล้า', 'Bang Khla', 15, 0),
(2403, 'บางน้ำเปรี้ยว', 'Bang Nam Priao', 15, 0),
(2404, 'บางปะกง', 'Bang Pakong', 15, 0),
(2405, 'บ้านโพธิ์', 'Ban Pho', 15, 0),
(2406, 'พนมสารคาม', 'Phanom Sarakham', 15, 0),
(2407, 'ราชสาส์น', 'Ratchasan', 15, 0),
(2408, 'สนามชัยเขต', 'Sanam Chai Khet', 15, 0),
(2409, 'แปลงยาว', 'Plaeng Yao', 15, 0),
(2410, 'ท่าตะเกียบ', 'Tha Takiap', 15, 0),
(2411, 'คลองเขื่อน', 'Khlong Khuean', 15, 0),
(2501, 'เมืองปราจีนบุรี', 'Mueang Prachin Buri', 16, 0),
(2502, 'กบินทร์บุรี', 'Kabin Buri', 16, 0),
(2503, 'นาดี', 'Na Di', 16, 0),
(2506, 'บ้านสร้าง', 'Ban Sang', 16, 0),
(2507, 'ประจันตคาม', 'Prachantakham', 16, 0),
(2508, 'ศรีมหาโพธิ', 'Si Maha Phot', 16, 0),
(2509, 'ศรีมโหสถ', 'Si Mahosot', 16, 0),
(2601, 'เมืองนครนายก', 'Mueang Nakhon Nayok', 17, 0),
(2602, 'ปากพลี', 'Pak Phli', 17, 0),
(2603, 'บ้านนา', 'Ban Na', 17, 0),
(2604, 'องครักษ์', 'Ongkharak', 17, 0),
(2701, 'เมืองสระแก้ว', 'Mueang Sa Kaeo', 18, 0),
(2702, 'คลองหาด', 'Khlong Hat', 18, 0),
(2703, 'ตาพระยา', 'Ta Phraya', 18, 0),
(2704, 'วังน้ำเย็น', 'Wang Nam Yen', 18, 0),
(2705, 'วัฒนานคร', 'Watthana Nakhon', 18, 0),
(2706, 'อรัญประเทศ', 'Aranyaprathet', 18, 0),
(2707, 'เขาฉกรรจ์', 'Khao Chakan', 18, 0),
(2708, 'โคกสูง', 'Khok Sung', 18, 0),
(2709, 'วังสมบูรณ์', 'Wang Sombun', 18, 0),
(3001, 'เมืองนครราชสีมา', 'Mueang Nakhon Ratchasima', 19, 0),
(3002, 'ครบุรี', 'Khon Buri', 19, 0),
(3003, 'เสิงสาง', 'Soeng Sang', 19, 0),
(3004, 'คง', 'Khong', 19, 0),
(3005, 'บ้านเหลื่อม', 'Ban Lueam', 19, 0),
(3006, 'จักราช', 'Chakkarat', 19, 0),
(3007, 'โชคชัย', 'Chok Chai', 19, 0),
(3008, 'ด่านขุนทด', 'Dan Khun Thot', 19, 0),
(3009, 'โนนไทย', 'Non Thai', 19, 0),
(3010, 'โนนสูง', 'Non Sung', 19, 0),
(3011, 'ขามสะแกแสง', 'Kham Sakaesaeng', 19, 0),
(3012, 'บัวใหญ่', 'Bua Yai', 19, 0),
(3013, 'ประทาย', 'Prathai', 19, 0),
(3014, 'ปักธงชัย', 'Pak Thong Chai', 19, 0),
(3015, 'พิมาย', 'Phimai', 19, 0),
(3016, 'ห้วยแถลง', 'Huai Thalaeng', 19, 0),
(3017, 'ชุมพวง', 'Chum Phuang', 19, 0),
(3018, 'สูงเนิน', 'Sung Noen', 19, 0),
(3019, 'ขามทะเลสอ', 'Kham Thale So', 19, 0),
(3020, 'สีคิ้ว', 'Sikhio', 19, 0),
(3021, 'ปากช่อง', 'Pak Chong', 19, 0),
(3022, 'หนองบุญมาก', 'Nong Bunnak', 19, 0),
(3023, 'แก้งสนามนาง', 'Kaeng Sanam Nang', 19, 0),
(3024, 'โนนแดง', 'Non Daeng', 19, 0),
(3025, 'วังน้ำเขียว', 'Wang Nam Khiao', 19, 0),
(3026, 'เทพารักษ์', 'Thepharak', 19, 0),
(3027, 'เมืองยาง', 'Mueang Yang', 19, 0),
(3028, 'พระทองคำ', 'Phra Thong Kham', 19, 0),
(3029, 'ลำทะเมนชัย', 'Lam Thamenchai', 19, 0),
(3030, 'บัวลาย', 'Bua Lai', 19, 0),
(3031, 'สีดา', 'Sida', 19, 0),
(3032, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 19, 0),
(3101, 'เมืองบุรีรัมย์', 'Mueang Buri Ram', 20, 0),
(3102, 'คูเมือง', 'Khu Mueang', 20, 0),
(3103, 'กระสัง', 'Krasang', 20, 0),
(3104, 'นางรอง', 'Nang Rong', 20, 0),
(3105, 'หนองกี่', 'Nong Ki', 20, 0),
(3106, 'ละหานทราย', 'Lahan Sai', 20, 0),
(3107, 'ประโคนชัย', 'Prakhon Chai', 20, 0),
(3108, 'บ้านกรวด', 'Ban Kruat', 20, 0),
(3109, 'พุทไธสง', 'Phutthaisong', 20, 0),
(3110, 'ลำปลายมาศ', 'Lam Plai Mat', 20, 0),
(3111, 'สตึก', 'Satuek', 20, 0),
(3112, 'ปะคำ', 'Pakham', 20, 0),
(3113, 'นาโพธิ์', 'Na Pho', 20, 0),
(3114, 'หนองหงส์', 'Nong Hong', 20, 0),
(3115, 'พลับพลาชัย', 'Phlapphla Chai', 20, 0),
(3116, 'ห้วยราช', 'Huai Rat', 20, 0),
(3117, 'โนนสุวรรณ', 'Non Suwan', 20, 0),
(3118, 'ชำนิ', 'Chamni', 20, 0),
(3119, 'บ้านใหม่ไชยพจน์', 'Ban Mai Chaiyaphot', 20, 0),
(3120, 'โนนดินแดง', 'Din Daeng', 20, 0),
(3121, 'บ้านด่าน', 'Ban Dan', 20, 0),
(3122, 'แคนดง', 'Khaen Dong', 20, 0),
(3123, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 20, 0),
(3201, 'เมืองสุรินทร์', 'Mueang Surin', 21, 0),
(3202, 'ชุมพลบุรี', 'Chumphon Buri', 21, 0),
(3203, 'ท่าตูม', 'Tha Tum', 21, 0),
(3204, 'จอมพระ', 'Chom Phra', 21, 0),
(3205, 'ปราสาท', 'Prasat', 21, 0),
(3206, 'กาบเชิง', 'Kap Choeng', 21, 0),
(3207, 'รัตนบุรี', 'Rattanaburi', 21, 0),
(3208, 'สนม', 'Sanom', 21, 0),
(3209, 'ศีขรภูมิ', 'Sikhoraphum', 21, 0),
(3210, 'สังขะ', 'Sangkha', 21, 0),
(3211, 'ลำดวน', 'Lamduan', 21, 0),
(3212, 'สำโรงทาบ', 'Samrong Thap', 21, 0),
(3213, 'บัวเชด', 'Buachet', 21, 0),
(3214, 'พนมดงรัก', 'Phanom Dong Rak', 21, 0),
(3215, 'ศรีณรงค์', 'Si Narong', 21, 0),
(3216, 'เขวาสินรินทร์', 'Khwao Sinarin', 21, 0),
(3217, 'โนนนารายณ์', 'Non Narai', 21, 0),
(3301, 'เมืองศรีสะเกษ', 'Mueang Si Sa Ket', 22, 0),
(3302, 'ยางชุมน้อย', 'Yang Chum Noi', 22, 0),
(3303, 'กันทรารมย์', 'Kanthararom', 22, 0),
(3304, 'กันทรลักษ์', 'Kantharalak', 22, 0),
(3305, 'ขุขันธ์', 'Khukhan', 22, 0),
(3306, 'ไพรบึง', 'Phrai Bueng', 22, 0),
(3307, 'ปรางค์กู่', 'Prang Ku', 22, 0),
(3308, 'ขุนหาญ', 'Khun Han', 22, 0),
(3309, 'ราษีไศล', 'Rasi Salai', 22, 0),
(3310, 'อุทุมพรพิสัย', 'Uthumphon Phisai', 22, 0),
(3311, 'บึงบูรพ์', 'Bueng Bun', 22, 0),
(3312, 'ห้วยทับทัน', 'Huai Thap Than', 22, 0),
(3313, 'โนนคูณ', 'Non Khun', 22, 0),
(3314, 'ศรีรัตนะ', 'Si Rattana', 22, 0),
(3315, 'น้ำเกลี้ยง', 'Nam Kliang', 22, 0),
(3316, 'วังหิน', 'Wang Hin', 22, 0),
(3317, 'ภูสิงห์', 'Phu Sing', 22, 0),
(3318, 'เมืองจันทร์', 'Mueang Chan', 22, 0),
(3319, 'เบญจลักษ์', 'Benchalak', 22, 0),
(3320, 'พยุห์', 'Phayu', 22, 0),
(3321, 'โพธิ์ศรีสุวรรณ', 'Pho Si Suwan', 22, 0),
(3322, 'ศิลาลาด', 'Sila Lat', 22, 0),
(3401, 'เมืองอุบลราชธานี', 'Mueang Ubon Ratchathani', 23, 0),
(3402, 'ศรีเมืองใหม่', 'Si Mueang Mai', 23, 0),
(3403, 'โขงเจียม', 'Khong Chiam', 23, 0),
(3404, 'เขื่องใน', 'Khueang Nai', 23, 0),
(3405, 'เขมราฐ', 'Khemarat', 23, 0),
(3407, 'เดชอุดม', 'Det Udom', 23, 0),
(3408, 'นาจะหลวย', 'Na Chaluai', 23, 0),
(3409, 'น้ำยืน', 'Nam Yuen', 23, 0),
(3410, 'บุณฑริก', 'Buntharik', 23, 0),
(3411, 'ตระการพืชผล', 'Trakan Phuet Phon', 23, 0),
(3412, 'กุดข้าวปุ้น', 'Kut Khaopun', 23, 0),
(3414, 'ม่วงสามสิบ', 'Muang Sam Sip', 23, 0),
(3415, 'วารินชำราบ', 'Warin Chamrap', 23, 0),
(3419, 'พิบูลมังสาหาร', 'Phibun Mangsahan', 23, 0),
(3420, 'ตาลสุม', 'Tan Sum', 23, 0),
(3421, 'โพธิ์ไทร', 'Pho Sai', 23, 0),
(3422, 'สำโรง', 'Samrong', 23, 0),
(3424, 'ดอนมดแดง', 'Don Mot Daeng', 23, 0),
(3425, 'สิรินธร', 'Sirindhorn', 23, 0),
(3426, 'ทุ่งศรีอุดม', 'Thung Si Udom', 23, 0),
(3429, 'นาเยีย', 'Na Yia', 23, 0),
(3430, 'นาตาล', 'Na Tan', 23, 0),
(3431, 'เหล่าเสือโก้ก', 'Lao Suea Kok', 23, 0),
(3432, 'สว่างวีระวงศ์', 'Sawang Wirawong', 23, 0),
(3433, 'น้ำขุ่น', 'Nam Khun', 23, 0),
(3501, 'เมืองยโสธร', 'Mueang Yasothon', 24, 0),
(3502, 'ทรายมูล', 'Sai Mun', 24, 0),
(3503, 'กุดชุม', 'Kut Chum', 24, 0),
(3504, 'คำเขื่อนแก้ว', 'Kham Khuean Kaeo', 24, 0),
(3505, 'ป่าติ้ว', 'Pa Tio', 24, 0),
(3506, 'มหาชนะชัย', 'Maha Chana Chai', 24, 0),
(3507, 'ค้อวัง', 'Kho Wang', 24, 0),
(3508, 'เลิงนกทา', 'Loeng Nok Tha', 24, 0),
(3509, 'ไทยเจริญ', 'Thai Charoen', 24, 0),
(3601, 'เมืองชัยภูมิ', 'Mueang Chaiyaphum', 25, 0),
(3602, 'บ้านเขว้า', 'Ban Khwao', 25, 0),
(3603, 'คอนสวรรค์', 'Khon Sawan', 25, 0),
(3604, 'เกษตรสมบูรณ์', 'Kaset Sombun', 25, 0),
(3605, 'หนองบัวแดง', 'Nong Bua Daeng', 25, 0),
(3606, 'จัตุรัส', 'Chatturat', 25, 0),
(3607, 'บำเหน็จณรงค์', 'Bamnet Narong', 25, 0),
(3608, 'หนองบัวระเหว', 'Nong Bua Rawe', 25, 0),
(3609, 'เทพสถิต', 'Thep Sathit', 25, 0),
(3610, 'ภูเขียว', 'Phu Khiao', 25, 0),
(3611, 'บ้านแท่น', 'Ban Thaen', 25, 0),
(3612, 'แก้งคร้อ', 'Kaeng Khro', 25, 0),
(3613, 'คอนสาร', 'Khon San', 25, 0),
(3614, 'ภักดีชุมพล', 'Phakdi Chumphon', 25, 0),
(3615, 'เนินสง่า', 'Noen Sa-nga', 25, 0),
(3616, 'ซับใหญ่', 'Sap Yai', 25, 0),
(3701, 'เมืองอำนาจเจริญ', 'Mueang Amnat Charoen', 26, 0),
(3702, 'ชานุมาน', 'Chanuman', 26, 0),
(3703, 'ปทุมราชวงศา', 'Pathum Ratchawongsa', 26, 0),
(3704, 'พนา', 'Phana', 26, 0),
(3705, 'เสนางคนิคม', 'Senangkhanikhom', 26, 0),
(3706, 'หัวตะพาน', 'Hua Taphan', 26, 0),
(3707, 'ลืออำนาจ', 'Lue Amnat', 26, 0),
(3801, 'เมืองบึงกาฬ', 'Mueang Bueng Kan', 77, 0),
(3802, 'เซกา', 'Seka', 77, 0),
(3803, 'โซ่พิสัย', 'So Phisai', 77, 0),
(3804, 'พรเจริญ', 'Phon Charoen', 77, 0),
(3805, 'ศรีวิไล', 'Si Wilai', 77, 0),
(3806, 'บึงโขงหลง', 'Bueng Khong Long', 77, 0),
(3807, 'ปากคาด', 'Pak Khat', 77, 0),
(3808, 'บุ่งคล้า', 'Bung Khla', 77, 0),
(3901, 'เมืองหนองบัวลำภู', 'Mueang Nong Bua Lam Phu', 27, 0),
(3902, 'นากลาง', 'Na Klang', 27, 0),
(3903, 'โนนสัง', 'Non Sang', 27, 0),
(3904, 'ศรีบุญเรือง', 'Si Bun Rueang', 27, 0),
(3905, 'สุวรรณคูหา', 'Suwannakhuha', 27, 0),
(3906, 'นาวัง', 'Na Wang', 27, 0),
(4001, 'เมืองขอนแก่น', 'Mueang Khon Kaen', 28, 0),
(4002, 'บ้านฝาง', 'Ban Fang', 28, 0),
(4003, 'พระยืน', 'Phra Yuen', 28, 0),
(4004, 'หนองเรือ', 'Nong Ruea', 28, 0),
(4005, 'ชุมแพ', 'Chum Phae', 28, 0),
(4006, 'สีชมพู', 'Si Chomphu', 28, 0),
(4007, 'น้ำพอง', 'Nam Phong', 28, 0),
(4008, 'อุบลรัตน์', 'Ubolratana', 28, 0),
(4009, 'กระนวน', 'Kranuan', 28, 0),
(4010, 'บ้านไผ่', 'Ban Phai', 28, 0),
(4011, 'เปือยน้อย', 'Pueai Noi', 28, 0),
(4012, 'พล', 'Phon', 28, 0),
(4013, 'แวงใหญ่', 'Waeng Yai', 28, 0),
(4014, 'แวงน้อย', 'Waeng Noi', 28, 0),
(4015, 'หนองสองห้อง', 'Nong Song Hong', 28, 0),
(4016, 'ภูเวียง', 'Phu Wiang', 28, 0),
(4017, 'มัญจาคีรี', 'Mancha Khiri', 28, 0),
(4018, 'ชนบท', 'Chonnabot', 28, 0),
(4019, 'เขาสวนกวาง', 'Khao Suan Kwang', 28, 0),
(4020, 'ภูผาม่าน', 'Phu Pha Man', 28, 0),
(4021, 'ซำสูง', 'Sam Sung', 28, 0),
(4022, 'โคกโพธิ์ไชย', 'Khok Pho Chai', 28, 0),
(4023, 'หนองนาคำ', 'Nong Na Kham', 28, 0),
(4024, 'บ้านแฮด', 'Ban Haet', 28, 0),
(4025, 'โนนศิลา', 'Non Sila', 28, 0),
(4029, 'เวียงเก่า', 'Wiang Kao', 28, 0),
(4101, 'เมืองอุดรธานี', 'Mueang Udon Thani', 29, 0),
(4102, 'กุดจับ', 'Kut Chap', 29, 0),
(4103, 'หนองวัวซอ', 'Nong Wua So', 29, 0),
(4104, 'กุมภวาปี', 'Kumphawapi', 29, 0),
(4105, 'โนนสะอาด', 'Non Sa-at', 29, 0),
(4106, 'หนองหาน', 'Nong Han', 29, 0),
(4107, 'ทุ่งฝน', 'Thung Fon', 29, 0),
(4108, 'ไชยวาน', 'Chai Wan', 29, 0),
(4109, 'ศรีธาตุ', 'Si That', 29, 0),
(4110, 'วังสามหมอ', 'Wang Sam Mo', 29, 0),
(4111, 'บ้านดุง', 'Ban Dung', 29, 0),
(4117, 'บ้านผือ', 'Ban Phue', 29, 0),
(4118, 'น้ำโสม', 'Nam Som', 29, 0),
(4119, 'เพ็ญ', 'Phen', 29, 0),
(4120, 'สร้างคอม', 'Sang Khom', 29, 0),
(4121, 'หนองแสง', 'Nong Saeng', 29, 0),
(4122, 'นายูง', 'Na Yung', 29, 0),
(4123, 'พิบูลย์รักษ์', 'Phibun Rak', 29, 0),
(4124, 'กู่แก้ว', 'Ku Kaeo', 29, 0),
(4125, 'ประจักษ์ศิลปาคม', 'rachak-sinlapakhom', 29, 0),
(4201, 'เมืองเลย', 'Mueang Loei', 30, 0),
(4202, 'นาด้วง', 'Na Duang', 30, 0),
(4203, 'เชียงคาน', 'Chiang Khan', 30, 0),
(4204, 'ปากชม', 'Pak Chom', 30, 0),
(4205, 'ด่านซ้าย', 'Dan Sai', 30, 0),
(4206, 'นาแห้ว', 'Na Haeo', 30, 0),
(4207, 'ภูเรือ', 'Phu Ruea', 30, 0),
(4208, 'ท่าลี่', 'Tha Li', 30, 0),
(4209, 'วังสะพุง', 'Wang Saphung', 30, 0),
(4210, 'ภูกระดึง', 'Phu Kradueng', 30, 0),
(4211, 'ภูหลวง', 'Phu Luang', 30, 0),
(4212, 'ผาขาว', 'Pha Khao', 30, 0),
(4213, 'เอราวัณ', 'Erawan', 30, 0),
(4214, 'หนองหิน', 'Nong Hin', 30, 0),
(4301, 'เมืองหนองคาย', 'Mueang Nong Khai', 31, 0),
(4302, 'ท่าบ่อ', 'Tha Bo', 31, 0),
(4305, 'โพนพิสัย', 'Phon Phisai', 31, 0),
(4307, 'ศรีเชียงใหม่', 'Si Chiang Mai', 31, 0),
(4308, 'สังคม', 'Sangkhom', 31, 0),
(4314, 'สระใคร', 'Sakhrai', 31, 0),
(4315, 'เฝ้าไร่', 'Fao Rai', 31, 0),
(4316, 'รัตนวาปี', 'Rattanawapi', 31, 0),
(4317, 'โพธิ์ตาก', 'Pho Tak', 31, 0),
(4401, 'เมืองมหาสารคาม', 'Mueang Maha Sarakham', 32, 0),
(4402, 'แกดำ', 'Kae Dam', 32, 0),
(4403, 'โกสุมพิสัย', 'Kosum Phisai', 32, 0),
(4404, 'กันทรวิชัย', 'Kantharawichai', 32, 0),
(4405, 'เชียงยืน', 'Kantharawichai', 32, 0),
(4406, 'บรบือ', 'Borabue', 32, 0),
(4407, 'นาเชือก', 'Na Chueak', 32, 0),
(4408, 'พยัคฆภูมิพิสัย', 'Phayakkhaphum Phisai', 32, 0),
(4409, 'วาปีปทุม', 'Wapi Pathum', 32, 0),
(4410, 'นาดูน', 'Na Dun', 32, 0),
(4411, 'ยางสีสุราช', 'Yang Sisurat', 32, 0),
(4412, 'กุดรัง', 'Kut Rang', 32, 0),
(4413, 'ชื่นชม', 'Chuen Chom', 32, 0),
(4501, 'เมืองร้อยเอ็ด', 'Mueang Roi Et', 33, 0),
(4502, 'เกษตรวิสัย', 'Kaset Wisai', 33, 0),
(4503, 'ปทุมรัตต์', 'Pathum Rat', 33, 0),
(4504, 'จตุรพักตรพิมาน', 'Chaturaphak Phiman', 33, 0),
(4505, 'ธวัชบุรี', 'Thawat Buri', 33, 0),
(4506, 'พนมไพร', 'Phanom Phrai', 33, 0),
(4507, 'โพนทอง', 'Phon Thong', 33, 0),
(4508, 'โพธิ์ชัย', 'Pho Chai', 33, 0),
(4509, 'หนองพอก', 'Nong Phok', 33, 0),
(4510, 'เสลภูมิ', 'Selaphum', 33, 0),
(4511, 'สุวรรณภูมิ', 'Suwannaphum', 33, 0),
(4512, 'เมืองสรวง', 'Mueang Suang', 33, 0),
(4513, 'โพนทราย', 'Phon Sai', 33, 0),
(4514, 'อาจสามารถ', 'At Samat', 33, 0),
(4515, 'เมยวดี', 'Moei Wadi', 33, 0),
(4516, 'ศรีสมเด็จ', 'Si Somdet', 33, 0),
(4517, 'จังหาร', 'Changhan', 33, 0),
(4518, 'เชียงขวัญ', 'Chiang Khwan', 33, 0),
(4519, 'หนองฮี', 'Nong Hi', 33, 0),
(4520, 'ทุ่งเขาหลวง', 'Thung Khao Luang', 33, 0),
(4601, 'เมืองกาฬสินธุ์', 'Mueang Kalasin', 34, 0),
(4602, 'นามน', 'Na Mon', 34, 0),
(4603, 'กมลาไสย', 'Kamalasai', 34, 0),
(4604, 'ร่องคำ', 'Rong Kham', 34, 0),
(4605, 'กุฉินารายณ์', 'Kuchinarai', 34, 0),
(4606, 'เขาวง', 'Khao Wong', 34, 0),
(4607, 'ยางตลาด', 'Yang Talat', 34, 0),
(4608, 'ห้วยเม็ก', 'Huai Mek', 34, 0),
(4609, 'สหัสขันธ์', 'Sahatsakhan', 34, 0),
(4610, 'คำม่วง', 'Kham Muang', 34, 0),
(4611, 'ท่าคันโท', 'Tha Khantho', 34, 0),
(4612, 'หนองกุงศรี', 'Nong Kung Si', 34, 0),
(4613, 'สมเด็จ', 'Somdet', 34, 0),
(4614, 'ห้วยผึ้ง', 'Huai Phueng', 34, 0),
(4615, 'สามชัย', 'Sam Chai', 34, 0),
(4616, 'นาคู', 'Na Khu', 34, 0),
(4617, 'ดอนจาน', 'Don Chan', 34, 0),
(4618, 'ฆ้องชัย', 'Khong Chai', 34, 0),
(4701, 'เมืองสกลนคร', 'Mueang Sakon Nakhon', 35, 0),
(4702, 'กุสุมาลย์', 'Kusuman', 35, 0),
(4703, 'กุดบาก', 'Kut Bak', 35, 0),
(4704, 'พรรณานิคม', 'Phanna Nikhom', 35, 0),
(4705, 'พังโคน', 'Phang Khon', 35, 0),
(4706, 'วาริชภูมิ', 'Waritchaphum', 35, 0),
(4707, 'นิคมน้ำอูน', 'Nikhom Nam Un', 35, 0),
(4708, 'วานรนิวาส', 'Wanon Niwat', 35, 0),
(4709, 'คำตากล้า', 'Kham Ta Kla', 35, 0),
(4710, 'บ้านม่วง', 'Ban Muang', 35, 0),
(4711, 'อากาศอำนวย', 'Akat Amnuai', 35, 0),
(4712, 'สว่างแดนดิน', 'Sawang Daen Din', 35, 0),
(4713, 'ส่องดาว', 'Song Dao', 35, 0),
(4714, 'เต่างอย', 'Tao Ngoi', 35, 0),
(4715, 'โคกศรีสุพรรณ', 'Khok Si Suphan', 35, 0),
(4716, 'เจริญศิลป์', 'Charoen Sin', 35, 0),
(4717, 'โพนนาแก้ว', 'Phon Na Kaeo', 35, 0),
(4718, 'ภูพาน', 'Phu Phan', 35, 0),
(4801, 'เมืองนครพนม', 'Mueang Nakhon Phanom', 36, 0),
(4802, 'ปลาปาก', 'Pla Pak', 36, 0),
(4803, 'ท่าอุเทน', 'Tha Uthen', 36, 0),
(4804, 'บ้านแพง', 'Ban Phaeng', 36, 0),
(4805, 'ธาตุพนม', 'That Phanom', 36, 0),
(4806, 'เรณูนคร', 'Renu Nakhon', 36, 0),
(4807, 'นาแก', 'Na Kae', 36, 0),
(4808, 'ศรีสงคราม', 'Si Songkhram', 36, 0),
(4809, 'นาหว้า', 'Na Wa', 36, 0),
(4810, 'โพนสวรรค์', 'Phon Sawan', 36, 0),
(4811, 'นาทม', 'Na Thom', 36, 0),
(4812, 'วังยาง', 'Wang Yang', 36, 0),
(4901, 'เมืองมุกดาหาร', 'Mueang Mukdahan', 37, 0),
(4902, 'นิคมคำสร้อย', 'Nikhom Kham Soi', 37, 0),
(4903, 'ดอนตาล', 'Don Tan', 37, 0),
(4904, 'ดงหลวง', 'Dong Luang', 37, 0),
(4905, 'คำชะอี', 'Khamcha-i', 37, 0),
(4906, 'หว้านใหญ่', 'Wan Yai', 37, 0),
(4907, 'หนองสูง', 'Nong Sung', 37, 0),
(5001, 'เมืองเชียงใหม่', 'Mueang Chiang Mai', 38, 0),
(5002, 'จอมทอง', 'Chom Thong', 38, 0),
(5003, 'แม่แจ่ม', 'Mae Chaem', 38, 0),
(5004, 'เชียงดาว', 'Chiang Dao', 38, 0),
(5005, 'ดอยสะเก็ด', 'Doi Saket', 38, 0),
(5006, 'แม่แตง', 'Mae Taeng', 38, 0),
(5007, 'แม่ริม', 'Mae Rim', 38, 0),
(5008, 'สะเมิง', 'Samoeng', 38, 0),
(5009, 'ฝาง', 'Fang', 38, 0),
(5010, 'แม่อาย', 'Mae Ai', 38, 0),
(5011, 'พร้าว', 'Phrao', 38, 0),
(5012, 'สันป่าตอง', 'San Pa Tong', 38, 0),
(5013, 'สันกำแพง', 'San Kamphaeng', 38, 0),
(5014, 'สันทราย', 'San Sai', 38, 0),
(5015, 'หางดง', 'Hang Dong', 38, 0),
(5016, 'ฮอด', 'Hot', 38, 0),
(5017, 'ดอยเต่า', 'Doi Tao', 38, 0),
(5018, 'อมก๋อย', 'Omkoi', 38, 0),
(5019, 'สารภี', 'Saraphi', 38, 0),
(5020, 'เวียงแหง', 'Wiang Haeng', 38, 0),
(5021, 'ไชยปราการ', 'Chai Prakan', 38, 0),
(5022, 'แม่วาง', 'Mae Wang', 38, 0),
(5023, 'แม่ออน', 'Mae On', 38, 0),
(5024, 'ดอยหล่อ', 'Doi Lo', 38, 0),
(5025, 'กัลยาณิวัฒนา', 'Galyani Vadhana', 38, 0),
(5101, 'เมืองลำพูน', 'Mueang Lamphun', 39, 0),
(5102, 'แม่ทา', 'Mae Tha', 39, 0),
(5103, 'บ้านโฮ่ง', 'Ban Hong', 39, 0),
(5104, 'ลี้', 'Li', 39, 0),
(5105, 'ทุ่งหัวช้าง', 'Thung Hua Chang', 39, 0),
(5106, 'ป่าซาง', 'Pa Sang', 39, 0),
(5107, 'บ้านธิ', 'Ban Thi', 39, 0),
(5108, 'เวียงหนองล่อง', 'Wiang Nong Long', 39, 0),
(5201, 'เมืองลำปาง', 'Mueang Lampang', 40, 0),
(5202, 'แม่เมาะ', 'Mae Mo', 40, 0),
(5203, 'เกาะคา', 'Ko Kha', 40, 0),
(5204, 'เสริมงาม', 'Soem Ngam', 40, 0),
(5205, 'งาว', 'Ngao', 40, 0),
(5206, 'แจ้ห่ม', 'Chae Hom', 40, 0),
(5207, 'วังเหนือ', 'Wang Nuea', 40, 0),
(5208, 'เถิน', 'Thoen', 40, 0),
(5209, 'แม่พริก', 'Mae Phrik', 40, 0),
(5210, 'แม่ทะ', 'Mae Tha', 40, 0),
(5211, 'สบปราบ', 'Sop Prap', 40, 0),
(5212, 'ห้างฉัตร', 'Hang Chat', 40, 0),
(5213, 'เมืองปาน', 'Mueang Pan', 40, 0),
(5301, 'เมืองอุตรดิตถ์', 'Mueang Uttaradit', 41, 0),
(5302, 'ตรอน', 'Tron', 41, 0),
(5303, 'ท่าปลา', 'Tha Pla', 41, 0),
(5304, 'น้ำปาด', 'Nam Pat', 41, 0),
(5305, 'ฟากท่า', 'Fak Tha', 41, 0),
(5306, 'บ้านโคก', 'Ban Khok', 41, 0),
(5307, 'พิชัย', 'Phichai', 41, 0),
(5308, 'ลับแล', 'Laplae', 41, 0),
(5309, 'ทองแสนขัน', 'Thong Saen Khan', 41, 0),
(5401, 'เมืองแพร่', 'Mueang Phrae', 42, 0),
(5402, 'ร้องกวาง', 'Rong Kwang', 42, 0),
(5403, 'ลอง', 'Long', 42, 0),
(5404, 'สูงเม่น', 'Sung Men', 42, 0),
(5405, 'เด่นชัย', 'Den Chai', 42, 0),
(5406, 'สอง', 'Song', 42, 0),
(5407, 'วังชิ้น', 'Wang Chin', 42, 0),
(5408, 'หนองม่วงไข่', 'Nong Muang Khai', 42, 0),
(5501, 'เมืองน่าน', 'Mueang Nan', 43, 0),
(5502, 'แม่จริม', 'Mae Charim', 43, 0),
(5503, 'บ้านหลวง', 'Ban Luang', 43, 0),
(5504, 'นาน้อย', 'Na Noi', 43, 0),
(5505, 'ปัว', 'Pua', 43, 0),
(5506, 'ท่าวังผา', 'Tha Wang Pha', 43, 0),
(5507, 'เวียงสา', 'Wiang Sa', 43, 0),
(5508, 'ทุ่งช้าง', 'Thung Chang', 43, 0),
(5509, 'เชียงกลาง', 'Chiang Klang', 43, 0),
(5510, 'นาหมื่น', 'Na Muen', 43, 0),
(5511, 'สันติสุข', 'Santi Suk', 43, 0),
(5512, 'บ่อเกลือ', 'Bo Kluea', 43, 0),
(5513, 'สองแคว', 'Song Khwae', 43, 0),
(5514, 'ภูเพียง', 'Phu Phiang', 43, 0),
(5515, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 43, 0),
(5601, 'เมืองพะเยา', 'Mueang Phayao', 44, 0),
(5602, 'จุน', 'Chun', 44, 0),
(5603, 'เชียงคำ', 'Chiang Kham', 44, 0),
(5604, 'เชียงม่วน', 'Chiang Muan', 44, 0),
(5605, 'ดอกคำใต้', 'Dok Khamtai', 44, 0),
(5606, 'ปง', 'Pong', 44, 0),
(5607, 'แม่ใจ', 'Mae Chai', 44, 0),
(5608, 'ภูซาง', 'Phu Sang', 44, 0),
(5609, 'ภูกามยาว', 'Phu Kamyao', 44, 0),
(5701, 'เมืองเชียงราย', 'Mueang Chiang Rai', 45, 0),
(5702, 'เวียงชัย', 'Wiang Chai', 45, 0),
(5703, 'เชียงของ', 'Chiang Khong', 45, 0),
(5704, 'เทิง', 'Thoeng', 45, 0),
(5705, 'พาน', 'Phan', 45, 0),
(5706, 'ป่าแดด', 'Pa Daet', 45, 0),
(5707, 'แม่จัน', 'Mae Chan', 45, 0),
(5708, 'เชียงแสน', 'Chiang Saen', 45, 0),
(5709, 'แม่สาย', 'Mae Sai', 45, 0),
(5710, 'แม่สรวย', 'Mae Suai', 45, 0),
(5711, 'เวียงป่าเป้า', 'Wiang Pa Pao', 45, 0),
(5712, 'พญาเม็งราย', 'Phaya Mengrai', 45, 0),
(5713, 'เวียงแก่น', 'Wiang Kaen', 45, 0),
(5714, 'ขุนตาล', 'Khun Tan', 45, 0),
(5715, 'แม่ฟ้าหลวง', 'Mae Fa Luang', 45, 0),
(5716, 'แม่ลาว', 'Mae Lao', 45, 0),
(5717, 'เวียงเชียงรุ้ง', 'Wiang Chiang Rung', 45, 0),
(5718, 'ดอยหลวง', 'Doi Luang', 45, 0),
(5801, 'เมืองแม่ฮ่องสอน', 'Mueang Mae Hong Son', 46, 0),
(5802, 'ขุนยวม', 'Khun Yuam', 46, 0),
(5803, 'ปาย', 'Pai', 46, 0),
(5804, 'แม่สะเรียง', 'Mae Sariang', 46, 0),
(5805, 'แม่ลาน้อย', 'Mae La Noi', 46, 0),
(5806, 'สบเมย', 'Sop Moei', 46, 0),
(5807, 'ปางมะผ้า', 'Pang Mapha', 46, 0),
(6001, 'เมืองนครสวรรค์', 'Mueang Nakhon Sawan', 47, 0),
(6002, 'โกรกพระ', 'Krok Phra', 47, 0),
(6003, 'ชุมแสง', 'Chum Saeng', 47, 0),
(6004, 'หนองบัว', 'Nong Bua', 47, 0),
(6005, 'บรรพตพิสัย', 'Banphot Phisai', 47, 0),
(6006, 'เก้าเลี้ยว', 'Kao Liao', 47, 0),
(6007, 'ตาคลี', 'Takhli', 47, 0),
(6008, 'ท่าตะโก', 'Takhli', 47, 0),
(6009, 'ไพศาลี', 'Phaisali', 47, 0),
(6010, 'พยุหะคีรี', 'Phayuha Khiri', 47, 0),
(6011, 'ลาดยาว', 'Phayuha Khiri', 47, 0),
(6012, 'ตากฟ้า', 'Tak Fa', 47, 0),
(6013, 'แม่วงก์', 'Mae Wong', 47, 0),
(6014, 'แม่เปิน', 'Mae Poen', 47, 0),
(6015, 'ชุมตาบง', 'Chum Ta Bong', 47, 0),
(6101, 'เมืองอุทัยธานี', 'Mueang Uthai Thani', 48, 0),
(6102, 'ทัพทัน', 'Thap Than', 48, 0),
(6103, 'สว่างอารมณ์', 'Sawang Arom', 48, 0),
(6104, 'หนองฉาง', 'Nong Chang', 48, 0),
(6105, 'หนองขาหย่าง', 'Nong Khayang', 48, 0),
(6106, 'บ้านไร่', 'Ban Rai', 48, 0),
(6107, 'ลานสัก', 'Lan Sak', 48, 0),
(6108, 'ห้วยคต', 'Huai Khot', 48, 0),
(6201, 'เมืองกำแพงเพชร', 'Mueang Kamphaeng Phet', 49, 0),
(6202, 'ไทรงาม', 'Sai Ngam', 49, 0),
(6203, 'คลองลาน', 'Khlong Lan', 49, 0),
(6204, 'ขาณุวรลักษบุรี', 'Khanu Woralaksaburi', 49, 0),
(6205, 'คลองขลุง', 'Khlong Khlung', 49, 0),
(6206, 'พรานกระต่าย', 'Phran Kratai', 49, 0),
(6207, 'ลานกระบือ', 'Lan Krabue', 49, 0),
(6208, 'ทรายทองวัฒนา', 'Sai Thong Watthana', 49, 0),
(6209, 'ปางศิลาทอง', 'Pang Sila Thong', 49, 0),
(6210, 'บึงสามัคคี', 'Bueng Samakkhi', 49, 0),
(6211, 'โกสัมพีนคร', 'Kosamphi Nakhon', 49, 0),
(6301, 'เมืองตาก', 'Mueang Tak', 50, 0),
(6302, 'บ้านตาก', 'Ban Tak', 50, 0),
(6303, 'สามเงา', 'Sam Ngao', 50, 0),
(6304, 'แม่ระมาด', 'Mae Ramat', 50, 0),
(6305, 'ท่าสองยาง', 'Tha Song Yang', 50, 0),
(6306, 'แม่สอด', 'Mae Sot', 50, 0),
(6307, 'พบพระ', 'Phop Phra', 50, 0),
(6308, 'อุ้มผาง', 'Umphang', 50, 0),
(6309, 'วังเจ้า', 'Wang Chao', 50, 0),
(6401, 'เมืองสุโขทัย', 'Mueang Sukhothai', 51, 0),
(6402, 'บ้านด่านลานหอย', 'Ban Dan Lan Hoi', 51, 0),
(6403, 'คีรีมาศ', 'Khiri Mat', 51, 0),
(6404, 'กงไกรลาศ', 'Kong Krailat', 51, 0),
(6405, 'ศรีสัชนาลัย', 'Si Satchanalai', 51, 0),
(6406, 'ศรีสำโรง', 'Si Samrong', 51, 0),
(6407, 'สวรรคโลก', 'Sawankhalok', 51, 0),
(6408, 'ศรีนคร', 'Si Nakhon', 51, 0),
(6409, 'ทุ่งเสลี่ยม', 'Thung Saliam', 51, 0),
(6501, 'เมืองพิษณุโลก', 'Mueang Phitsanulok', 52, 0),
(6502, 'นครไทย', 'Nakhon Thai', 52, 0),
(6503, 'ชาติตระการ', 'Chat Trakan', 52, 0),
(6504, 'บางระกำ', 'Bang Rakam', 52, 0),
(6505, 'บางกระทุ่ม', 'Bang Krathum', 52, 0),
(6506, 'พรหมพิราม', 'Phrom Phiram', 52, 0),
(6507, 'วัดโบสถ์', 'Wat Bot', 52, 0),
(6508, 'วังทอง', 'Wang Thong', 52, 0),
(6509, 'เนินมะปราง', 'Noen Maprang', 52, 0),
(6601, 'เมืองพิจิตร', 'Mueang Phichit', 53, 0),
(6602, 'วังทรายพูน', 'Wang Sai Phun', 53, 0),
(6603, 'โพธิ์ประทับช้าง', 'Pho Prathap Chang', 53, 0),
(6604, 'ตะพานหิน', 'Taphan Hin', 53, 0),
(6605, 'บางมูลนาก', 'Bang Mun Nak', 53, 0),
(6606, 'โพทะเล', 'Pho Thale', 53, 0),
(6607, 'สามง่าม', 'Sam Ngam', 53, 0),
(6608, 'ทับคล้อ', 'Tap Khlo', 53, 0),
(6609, 'สากเหล็ก', 'Sak Lek', 53, 0),
(6610, 'บึงนาราง', 'Bueng Na Rang', 53, 0),
(6611, 'ดงเจริญ', 'Dong Charoen', 53, 0),
(6612, 'วชิรบารมี', 'Wachirabarami', 53, 0),
(6701, 'เมืองเพชรบูรณ์', 'Mueang Phetchabun', 54, 0),
(6702, 'ชนแดน', 'Chon Daen', 54, 0),
(6703, 'หล่มสัก', 'Lom Sak', 54, 0),
(6704, 'หล่มเก่า', 'Lom Kao', 54, 0),
(6705, 'วิเชียรบุรี', 'Wichian Buri', 54, 0),
(6706, 'ศรีเทพ', 'Si Thep', 54, 0),
(6707, 'หนองไผ่', 'Nong Phai', 54, 0),
(6708, 'บึงสามพัน', 'Bueng Sam Phan', 54, 0),
(6709, 'น้ำหนาว', 'Nam Nao', 54, 0),
(6710, 'วังโป่ง', 'Wang Pong', 54, 0),
(6711, 'เขาค้อ', 'Khao Kho', 54, 0),
(7001, 'เมืองราชบุรี', 'Mueang Ratchaburi', 55, 0),
(7002, 'จอมบึง', 'Chom Bueng', 55, 0),
(7003, 'สวนผึ้ง', 'Suan Phueng', 55, 0),
(7004, 'ดำเนินสะดวก', 'Damnoen Saduak', 55, 0),
(7005, 'บ้านโป่ง', 'Ban Pong', 55, 0),
(7006, 'บางแพ', 'Bang Phae', 55, 0),
(7007, 'โพธาราม', 'Photharam', 55, 0),
(7008, 'ปากท่อ', 'Pak Tho', 55, 0),
(7009, 'วัดเพลง', 'Wat Phleng', 55, 0),
(7010, 'บ้านคา', 'Ban Kha', 55, 0),
(7074, 'ท้องถิ่นเทศบาลตำบลบ้านฆ้อง', 'Tet Saban Ban Kong', 55, 0),
(7101, 'เมืองกาญจนบุรี', 'Mueang Kanchanaburi', 56, 0),
(7102, 'ไทรโยค', 'Sai Yok', 56, 0),
(7103, 'บ่อพลอย', 'Bo Phloi', 56, 0),
(7104, 'ศรีสวัสดิ์', 'Si Sawat', 56, 0),
(7105, 'ท่ามะกา', 'Tha Maka', 56, 0),
(7106, 'ท่าม่วง', 'Tha Muang', 56, 0),
(7107, 'ทองผาภูมิ', 'Pha Phum', 56, 0),
(7108, 'สังขละบุรี', 'Sangkhla Buri', 56, 0),
(7109, 'พนมทวน', 'Phanom Thuan', 56, 0),
(7110, 'เลาขวัญ', 'Lao Khwan', 56, 0),
(7111, 'ด่านมะขามเตี้ย', 'Dan Makham Tia', 56, 0),
(7112, 'หนองปรือ', 'Nong Prue', 56, 0),
(7113, 'ห้วยกระเจา', 'Huai Krachao', 56, 0),
(7201, 'เมืองสุพรรณบุรี', 'Mueang Suphan Buri', 57, 0),
(7202, 'เดิมบางนางบวช', 'Doem Bang Nang Buat', 57, 0),
(7203, 'ด่านช้าง', 'Dan Chang', 57, 0),
(7204, 'บางปลาม้า', 'Bang Pla Ma', 57, 0),
(7205, 'ศรีประจันต์', 'Si Prachan', 57, 0),
(7206, 'ดอนเจดีย์', 'Don Chedi', 57, 0),
(7207, 'สองพี่น้อง', 'Song Phi Nong', 57, 0),
(7208, 'สามชุก', 'Sam Chuk', 57, 0),
(7209, 'อู่ทอง', 'U Thong', 57, 0),
(7210, 'หนองหญ้าไซ', 'Nong Ya Sai', 57, 0),
(7301, 'เมืองนครปฐม', 'Mueang Nakhon Pathom', 58, 0),
(7302, 'กำแพงแสน', 'Kamphaeng Saen', 58, 0),
(7303, 'นครชัยศรี', 'Nakhon Chai Si', 58, 0),
(7304, 'ดอนตูม', 'Don Tum', 58, 0),
(7305, 'บางเลน', 'Bang Len', 58, 0),
(7306, 'สามพราน', 'Sam Phran', 58, 0),
(7307, 'พุทธมณฑล', 'Phutthamonthon', 58, 0),
(7401, 'เมืองสมุทรสาคร', 'Mueang Samut Sakhon', 59, 0),
(7402, 'กระทุ่มแบน', 'Krathum Baen', 59, 0),
(7403, 'บ้านแพ้ว', 'Ban Phaeo', 59, 0),
(7501, 'เมืองสมุทรสงคราม', 'Mueang Samut Songkhram', 60, 0),
(7502, 'บางคนที', 'Bang Khonthi', 60, 0),
(7503, 'อัมพวา', 'Amphawa', 60, 0),
(7601, 'เมืองเพชรบุรี', 'Mueang Phetchaburi', 61, 0),
(7602, 'เขาย้อย', 'Khao Yoi', 61, 0),
(7603, 'หนองหญ้าปล้อง', 'Nong Ya Plong', 61, 0),
(7604, 'ชะอำ', 'Cha-am', 61, 0),
(7605, 'ท่ายาง', 'Tha Yang', 61, 0),
(7606, 'บ้านลาด', 'Ban Lat', 61, 0),
(7607, 'บ้านแหลม', 'Ban Laem', 61, 0),
(7608, 'แก่งกระจาน', 'Kaeng Krachan', 61, 0),
(7701, 'เมืองประจวบคีรีขันธ์', 'Mueang Prachuap Khiri Khan', 62, 0),
(7702, 'กุยบุรี', 'Kui Buri', 62, 0),
(7703, 'ทับสะแก', 'Thap Sakae', 62, 0),
(7704, 'บางสะพาน', 'Bang Saphan', 62, 0),
(7705, 'บางสะพานน้อย', 'Bang Saphan Noi', 62, 0),
(7706, 'ปราณบุรี', 'Pran Buri', 62, 0),
(7707, 'หัวหิน', 'Hua Hin', 62, 0),
(7708, 'สามร้อยยอด', 'Sam Roi Yot', 62, 0),
(8001, 'เมืองนครศรีธรรมราช', 'Mueang Nakhon Si Thammarat', 63, 0),
(8002, 'พรหมคีรี', 'Phrom Khiri', 63, 0),
(8003, 'ลานสกา', 'Lan Saka', 63, 0),
(8004, 'ฉวาง', 'Chawang', 63, 0),
(8005, 'พิปูน', 'Phipun', 63, 0),
(8006, 'เชียรใหญ่', 'Chian Yai', 63, 0),
(8007, 'ชะอวด', 'Cha-uat', 63, 0),
(8008, 'ท่าศาลา', 'Tha Sala', 63, 0),
(8009, 'ทุ่งสง', 'Thung Song', 63, 0),
(8010, 'นาบอน', 'Na Bon', 63, 0),
(8011, 'ทุ่งใหญ่', 'Thung Yai', 63, 0),
(8012, 'ปากพนัง', 'Pak Phanang', 63, 0),
(8013, 'ร่อนพิบูลย์', 'Ron Phibun', 63, 0),
(8014, 'สิชล', 'Sichon', 63, 0),
(8015, 'ขนอม', 'Khanom', 63, 0),
(8016, 'หัวไทร', 'Hua Sai', 63, 0),
(8017, 'บางขัน', 'Bang Khan', 63, 0),
(8018, 'ถ้ำพรรณรา', 'Tham Phannara', 63, 0),
(8019, 'จุฬาภรณ์', 'Chulabhorn', 63, 0),
(8020, 'พระพรหม', 'Phra Phrom', 63, 0),
(8021, 'นบพิตำ', 'Nopphitam', 63, 0),
(8022, 'ช้างกลาง', 'Chang Klang', 63, 0),
(8023, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 63, 0),
(8101, 'เมืองกระบี่', 'Mueang Krabi', 64, 0),
(8102, 'เขาพนม', 'Khao Phanom', 64, 0),
(8103, 'เกาะลันตา', 'Ko Lanta', 64, 0),
(8104, 'คลองท่อม', 'Khlong Thom', 64, 0),
(8105, 'อ่าวลึก', 'Ao Luek', 64, 0),
(8106, 'ปลายพระยา', 'Plai Phraya', 64, 0),
(8107, 'ลำทับ', 'Lam Thap', 64, 0),
(8108, 'เหนือคลอง', 'Nuea Khlong', 64, 0),
(8201, 'เมืองพังงา', 'Mueang Phang-nga', 65, 0),
(8202, 'เกาะยาว', 'Ko Yao', 65, 0),
(8203, 'กะปง', 'Kapong', 65, 0),
(8204, 'ตะกั่วทุ่ง', 'Takua Thung', 65, 0),
(8205, 'ตะกั่วป่า', 'Takua Pa', 65, 0),
(8206, 'คุระบุรี', 'Khura Buri', 65, 0),
(8207, 'ทับปุด', 'Thap Put', 65, 0),
(8208, 'ท้ายเหมือง', 'Thai Mueang', 65, 0),
(8301, 'เมืองภูเก็ต', 'Mueang Phuket', 66, 0),
(8302, 'กะทู้', 'Kathu', 66, 0),
(8303, 'ถลาง', 'Thalang', 66, 0),
(8401, 'เมืองสุราษฎร์ธานี', 'Mueang Surat Thani', 67, 0),
(8402, 'กาญจนดิษฐ์', 'Kanchanadit', 67, 0),
(8403, 'ดอนสัก', 'Don Sak', 67, 0),
(8404, 'เกาะสมุย', 'Ko Samui', 67, 0),
(8405, 'เกาะพะงัน', 'Ko Pha-ngan', 67, 0),
(8406, 'ไชยา', 'Chaiya', 67, 0),
(8407, 'ท่าชนะ', 'Tha Chana', 67, 0),
(8408, 'คีรีรัฐนิคม', 'Khiri Rat Nikhom', 67, 0),
(8409, 'บ้านตาขุน', 'Ban Ta Khun', 67, 0),
(8410, 'พนม', 'Phanom', 67, 0),
(8411, 'ท่าฉาง', 'Tha Chang', 67, 0),
(8412, 'บ้านนาสาร', 'Ban Na San', 67, 0),
(8413, 'บ้านนาเดิม', 'Ban Na Doem', 67, 0),
(8414, 'เคียนซา', 'Khian Sa', 67, 0),
(8415, 'เวียงสระ', 'Wiang Sa', 67, 0),
(8416, 'พระแสง', 'Phrasaeng', 67, 0),
(8417, 'พุนพิน', 'Phunphin', 67, 0),
(8418, 'ชัยบุรี', 'Chai Buri', 67, 0),
(8419, 'วิภาวดี', 'Vibhavadi', 67, 0),
(8501, 'เมืองระนอง', 'Mueang Ranong', 68, 0),
(8502, 'ละอุ่น', 'La-un', 68, 0),
(8503, 'กะเปอร์', 'Kapoe', 68, 0),
(8504, 'กระบุรี', 'Kra Buri', 68, 0),
(8505, 'สุขสำราญ', 'Suk Samran', 68, 0),
(8601, 'เมืองชุมพร', 'Mueang Chumphon', 69, 0),
(8602, 'ท่าแซะ', 'Tha Sae', 69, 0),
(8603, 'ปะทิว', 'Pathio', 69, 0),
(8604, 'หลังสวน', 'Lang Suan', 69, 0),
(8605, 'ละแม', 'Lamae', 69, 0),
(8606, 'พะโต๊ะ', 'Phato', 69, 0),
(8607, 'สวี', 'Sawi', 69, 0),
(8608, 'ทุ่งตะโก', 'Thung Tako', 69, 0),
(9001, 'เมืองสงขลา', 'Mueang Songkhla', 70, 0),
(9002, 'สทิงพระ', 'Sathing Phra', 70, 0),
(9003, 'จะนะ', 'Chana', 70, 0),
(9004, 'นาทวี', 'Na Thawi', 70, 0),
(9005, 'เทพา', 'Thepha', 70, 0),
(9006, 'สะบ้าย้อย', 'Saba Yoi', 70, 0),
(9007, 'ระโนด', 'Ranot', 70, 0),
(9008, 'กระแสสินธุ์', 'Krasae Sin', 70, 0),
(9009, 'รัตภูมิ', 'Rattaphum', 70, 0),
(9010, 'สะเดา', 'Sadao', 70, 0),
(9011, 'หาดใหญ่', 'Hat Yai', 70, 0),
(9012, 'นาหม่อม', 'Na Mom', 70, 0),
(9013, 'ควนเนียง', 'Khuan Niang', 70, 0),
(9014, 'บางกล่ำ', 'Bang Klam', 70, 0),
(9015, 'สิงหนคร', 'Singhanakhon', 70, 0),
(9016, 'คลองหอยโข่ง', 'Khlong Hoi Khong', 70, 0),
(9077, 'ท้องถิ่นเทศบาลตำบลสำนักขาม', 'Sum Nung Kam', 70, 0),
(9101, 'เมืองสตูล', 'Mueang Satun', 71, 0),
(9102, 'ควนโดน', 'Khuan Don', 71, 0),
(9103, 'ควนกาหลง', 'Khuan Kalong', 71, 0),
(9104, 'ท่าแพ', 'Tha Phae', 71, 0),
(9105, 'ละงู', 'La-ngu', 71, 0),
(9106, 'ทุ่งหว้า', 'Thung Wa', 71, 0),
(9107, 'มะนัง', 'Manang', 71, 0),
(9201, 'เมืองตรัง', 'Mueang Trang', 72, 0),
(9202, 'กันตัง', 'Kantang', 72, 0),
(9203, 'ย่านตาขาว', 'Yan Ta Khao', 72, 0),
(9204, 'ปะเหลียน', 'Palian', 72, 0),
(9205, 'สิเกา', 'Sikao', 72, 0),
(9206, 'ห้วยยอด', 'Huai Yot', 72, 0),
(9207, 'วังวิเศษ', 'Wang Wiset', 72, 0),
(9208, 'นาโยง', 'Na Yong', 72, 0),
(9209, 'รัษฎา', 'Ratsada', 72, 0),
(9210, 'หาดสำราญ', 'Hat Samran', 72, 0),
(9301, 'เมืองพัทลุง', 'Mueang Phatthalung', 73, 0),
(9302, 'กงหรา', 'Kong Ra', 73, 0),
(9303, 'เขาชัยสน', 'Khao Chaison', 73, 0),
(9304, 'ตะโหมด', 'Tamot', 73, 0),
(9305, 'ควนขนุน', 'Khuan Khanun', 73, 0),
(9306, 'ปากพะยูน', 'Pak Phayun', 73, 0),
(9307, 'ศรีบรรพต', 'Si Banphot', 73, 0),
(9308, 'ป่าบอน', 'Pa Bon', 73, 0),
(9309, 'บางแก้ว', 'Bang Kaeo', 73, 0),
(9310, 'ป่าพะยอม', 'Pa Phayom', 73, 0),
(9311, 'ศรีนครินทร์', 'Srinagarindra', 73, 0),
(9401, 'เมืองปัตตานี', 'Mueang Pattani', 74, 0),
(9402, 'โคกโพธิ์', 'Khok Pho', 74, 0),
(9403, 'หนองจิก', 'Nong Chik', 74, 0),
(9404, 'ปะนาเระ', 'Panare', 74, 0),
(9405, 'มายอ', 'Mayo', 74, 0),
(9406, 'ทุ่งยางแดง', 'Thung Yang Daeng', 74, 0),
(9407, 'สายบุรี', 'Sai Buri', 74, 0),
(9408, 'ไม้แก่น', 'Mai Kaen', 74, 0),
(9409, 'ยะหริ่ง', 'Yaring', 74, 0),
(9410, 'ยะรัง', 'Yarang', 74, 0),
(9411, 'กะพ้อ', 'Kapho', 74, 0),
(9412, 'แม่ลาน', 'Mae Lan', 74, 0),
(9501, 'เมืองยะลา', 'Mueang Yala', 75, 0),
(9502, 'เบตง', 'Betong', 75, 0),
(9503, 'บันนังสตา', 'Bannang Sata', 75, 0),
(9504, 'ธารโต', 'Than To', 75, 0),
(9505, 'ยะหา', 'Yaha', 75, 0),
(9506, 'รามัน', 'Raman', 75, 0),
(9507, 'กาบัง', 'Kabang', 75, 0),
(9508, 'กรงปินัง', 'Krong Pinang', 75, 0),
(9601, 'เมืองนราธิวาส', 'Mueang Narathiwat', 76, 0),
(9602, 'ตากใบ', 'Tak Bai', 76, 0),
(9603, 'บาเจาะ', 'Bacho', 76, 0),
(9604, 'ยี่งอ', 'Yi-ngo', 76, 0),
(9605, 'ระแงะ', 'Ra-ngae', 76, 0),
(9606, 'รือเสาะ', 'Rueso', 76, 0),
(9607, 'ศรีสาคร', 'Si Sakhon', 76, 0),
(9608, 'แว้ง', 'Waeng', 76, 0),
(9609, 'สุคิริน', 'Sukhirin', 76, 0),
(9610, 'สุไหงโก-ลก', 'Su-ngai Kolok', 76, 0),
(9611, 'สุไหงปาดี', 'Su-ngai Padi', 76, 0),
(9612, 'จะแนะ', 'Chanae', 76, 0),
(9613, 'เจาะไอร้อง', 'Cho-airong', 76, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(6) NOT NULL,
  `emp_code` varchar(20) NOT NULL,
  `emp_national_id` varchar(20) NOT NULL,
  `firstname_th` varchar(30) NOT NULL,
  `lastname_th` varchar(30) NOT NULL,
  `firstname_en` varchar(30) DEFAULT NULL,
  `lastname_en` varchar(30) DEFAULT NULL,
  `emp_phone_no` varchar(20) NOT NULL,
  `emp_email` varchar(75) DEFAULT NULL,
  `emp_line_id` varchar(30) DEFAULT NULL,
  `emp_birthday` date DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `emp_gender` enum('Male','Female') NOT NULL,
  `emp_status` enum('Active','Resigned') NOT NULL,
  `prefixs_prefix_id` int(6) NOT NULL,
  `Addresses_address_id` int(6) NOT NULL,
  `religions_religion_id` int(2) NOT NULL,
  `departments_dept_id` int(11) NOT NULL,
  `branches_branch_id` int(3) NOT NULL,
  `users_user_id` int(6) DEFAULT NULL COMMENT 'FK อ้างอิงตาราง users',
  `emp_image` varchar(255) DEFAULT NULL COMMENT 'ชื่อไฟล์รูปโปรไฟล์'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`emp_id`, `emp_code`, `emp_national_id`, `firstname_th`, `lastname_th`, `firstname_en`, `lastname_en`, `emp_phone_no`, `emp_email`, `emp_line_id`, `emp_birthday`, `create_at`, `update_at`, `emp_gender`, `emp_status`, `prefixs_prefix_id`, `Addresses_address_id`, `religions_religion_id`, `departments_dept_id`, `branches_branch_id`, `users_user_id`, `emp_image`) VALUES
(1, 'ADM-001', '0000000000000', 'ผู้ดูแลระบบ', 'ส่วนกลาง', NULL, NULL, '0000000000', NULL, NULL, NULL, '2025-12-21 20:09:35', '2025-12-21 20:09:35', 'Male', 'Active', 100001, 0, 10, 0, 0, 1, NULL),
(2, '1000000000001', '1222222222222', 'ทดสอบ', 'ทดสอบ', '', '', '0888888888', '', '', NULL, '2025-12-24 11:31:52', '2025-12-24 11:31:52', 'Male', 'Active', 100001, 5, 10, 1, 1, 2, NULL),
(9, 'EMP2601923', '', 'ทดสอบ', 'อิอิ', '', '', '0888888888', '', '', NULL, '2026-01-02 13:38:20', '2026-01-02 13:38:20', 'Male', 'Active', 100001, 14, 10, 5, 1, 3, NULL),
(10, 'EMP2601382', '', 'ด', 'ด', '', '', '0888888888', '', '', NULL, '2026-01-02 15:11:05', '2026-01-02 15:11:05', 'Male', 'Active', 100001, 15, 10, 5, 1, 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_id` int(11) NOT NULL,
  `amount` int(3) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `purchase_orders_purchase_id` int(11) NOT NULL,
  `products_prod_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_id`, `amount`, `price`, `create_at`, `update_at`, `purchase_orders_purchase_id`, `products_prod_id`) VALUES
(1, 3, 15000.00, '2026-01-03 00:49:03', '2026-01-03 00:49:03', 1, 100002),
(2, 3, 15000.00, '2026-01-03 01:13:00', '2026-01-03 01:13:00', 2, 100002),
(3, 5, 10000.00, '2026-01-03 01:17:12', '2026-01-03 01:17:12', 3, 100003);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(3) NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `permission_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_desc`, `create_at`, `update_at`) VALUES
(1, 'add_branch', NULL, '2025-11-22 08:11:18', '2025-11-22 08:11:18'),
(2, 'branch', NULL, '2025-11-22 08:11:50', '2025-11-22 08:11:50'),
(3, 'delete_branch', NULL, '2025-11-22 08:11:57', '2025-11-22 08:11:57'),
(4, 'edit_branch', NULL, '2025-11-22 08:12:04', '2025-11-22 08:12:04'),
(5, 'add_customer', NULL, '2025-11-22 08:12:28', '2025-11-22 08:12:28'),
(6, 'customer_list', NULL, '2025-11-22 08:12:36', '2025-11-22 08:12:36'),
(7, 'delete_customer', NULL, '2025-11-22 08:12:44', '2025-11-22 08:12:44'),
(8, 'edit_customer', NULL, '2025-11-22 08:13:11', '2025-11-22 08:13:11'),
(9, 'view_customer', NULL, '2025-11-22 08:13:20', '2025-11-22 08:13:20'),
(10, 'add_department', NULL, '2025-11-22 08:13:32', '2025-11-22 08:13:32'),
(11, 'delete_department', NULL, '2025-11-22 08:13:57', '2025-11-22 08:13:57'),
(12, 'department', NULL, '2025-11-22 08:14:06', '2025-11-22 08:14:06'),
(13, 'edit_department', NULL, '2025-11-22 08:14:14', '2025-11-22 08:14:14'),
(14, 'add_districts', NULL, '2025-11-22 08:14:26', '2025-11-22 08:14:26'),
(15, 'delete_districts', NULL, '2025-11-22 08:14:34', '2025-11-22 08:14:34'),
(16, 'districts', NULL, '2025-11-22 08:14:49', '2025-11-22 08:14:49'),
(17, 'edit_districts', NULL, '2025-11-22 08:15:29', '2025-11-22 08:15:29'),
(18, 'add_employee', NULL, '2025-11-22 08:15:42', '2025-11-22 08:15:42'),
(19, 'delete_employee', NULL, '2025-11-22 08:16:46', '2025-11-22 08:16:46'),
(20, 'edit_employee', NULL, '2025-11-22 08:16:57', '2025-11-22 08:16:57'),
(21, 'employee', NULL, '2025-11-22 08:17:03', '2025-11-22 08:17:03'),
(22, 'print_employee', NULL, '2025-11-22 08:17:10', '2025-11-22 08:17:10'),
(23, 'toggle_employee_status', NULL, '2025-11-22 08:17:21', '2025-11-22 08:17:21'),
(24, 'view_employee', NULL, '2025-11-22 08:17:29', '2025-11-22 08:17:29'),
(25, 'dashboard', NULL, '2025-11-22 08:17:53', '2025-11-22 08:17:53'),
(26, 'add_permission', NULL, '2025-11-22 08:18:34', '2025-11-22 08:18:34'),
(27, 'delete_permission', NULL, '2025-11-22 08:18:55', '2025-11-22 08:18:55'),
(28, 'edit_permission', NULL, '2025-11-22 08:19:01', '2025-11-22 08:19:01'),
(29, 'permission', NULL, '2025-11-22 08:19:19', '2025-11-22 08:19:19'),
(30, 'add_prename', NULL, '2025-11-22 08:19:27', '2025-11-22 08:19:27'),
(31, 'delete_prename', NULL, '2025-11-22 08:20:21', '2025-11-22 08:20:21'),
(32, 'edit_prename', NULL, '2025-11-22 08:20:27', '2025-11-22 08:20:27'),
(33, 'prename', NULL, '2025-11-22 08:20:34', '2025-11-22 08:20:34'),
(34, 'toggle_prename_status', NULL, '2025-11-22 08:20:39', '2025-11-22 08:20:39'),
(35, 'add_prodbrand', NULL, '2025-11-22 08:21:06', '2025-11-22 08:21:06'),
(36, 'delete_prodbrand', NULL, '2025-11-22 08:21:13', '2025-11-22 08:21:13'),
(37, 'edit_prodbrand', NULL, '2025-11-22 08:21:18', '2025-11-22 08:21:18'),
(38, 'prodbrand', NULL, '2025-11-22 08:21:23', '2025-11-22 08:21:23'),
(39, 'add_prodStock', NULL, '2025-11-22 08:21:31', '2025-11-22 08:21:31'),
(40, 'delete_prodstock', NULL, '2025-11-22 08:21:37', '2025-11-22 08:21:37'),
(41, 'edit_stock', NULL, '2025-11-22 08:21:43', '2025-11-22 08:21:43'),
(42, 'print_barcode', NULL, '2025-11-22 08:21:49', '2025-11-22 08:21:49'),
(43, 'prod_stock', NULL, '2025-11-22 08:21:54', '2025-11-22 08:21:54'),
(44, 'view_stock', NULL, '2025-11-22 08:22:00', '2025-11-22 08:22:00'),
(45, 'add_prodtype', NULL, '2025-11-22 08:22:31', '2025-11-22 08:22:31'),
(46, 'delete_prodtype', NULL, '2025-11-22 08:22:37', '2025-11-22 08:22:37'),
(47, 'edit_prodtype', NULL, '2025-11-22 08:22:42', '2025-11-22 08:22:42'),
(48, 'prodtype', NULL, '2025-11-22 08:22:48', '2025-11-22 08:22:48'),
(49, 'add_product', NULL, '2025-11-22 08:23:39', '2025-11-22 08:23:39'),
(50, 'delete_product', NULL, '2025-11-22 08:23:45', '2025-11-22 08:23:45'),
(51, 'edit_product', NULL, '2025-11-22 08:23:50', '2025-11-22 08:23:50'),
(52, 'product', NULL, '2025-11-22 08:23:56', '2025-11-22 08:23:56'),
(53, 'view_product', NULL, '2025-11-22 08:24:01', '2025-11-22 08:24:01'),
(54, 'add_province', NULL, '2025-11-22 08:24:59', '2025-11-22 08:24:59'),
(55, 'delete_province', NULL, '2025-11-22 08:25:05', '2025-11-22 08:25:05'),
(56, 'edit_province', NULL, '2025-11-22 08:25:10', '2025-11-22 08:25:10'),
(57, 'province', NULL, '2025-11-22 08:25:16', '2025-11-22 08:25:16'),
(58, 'add_purchase_order', NULL, '2025-11-22 08:25:26', '2025-11-22 08:25:26'),
(59, 'cancel_purchase_order', NULL, '2025-11-22 08:26:14', '2025-11-22 08:26:14'),
(60, 'edit_purchase_order', NULL, '2025-11-22 08:26:21', '2025-11-22 08:26:21'),
(61, 'purchase_order', NULL, '2025-11-22 08:26:27', '2025-11-22 08:26:27'),
(62, 'receive_po', NULL, '2025-11-22 08:26:34', '2025-11-22 08:26:34'),
(63, 'view_purchase_order', NULL, '2025-11-22 08:26:40', '2025-11-22 08:26:40'),
(64, 'add_religion', NULL, '2025-11-22 08:27:09', '2025-11-22 08:27:09'),
(65, 'delete_religion', NULL, '2025-11-22 08:27:15', '2025-11-22 08:27:15'),
(66, 'edit_religion', NULL, '2025-11-22 08:27:20', '2025-11-22 08:27:20'),
(67, 'religion', NULL, '2025-11-22 08:27:26', '2025-11-22 08:27:26'),
(68, 'toggle_religion_status', NULL, '2025-11-22 08:27:32', '2025-11-22 08:27:32'),
(69, 'add_repair', NULL, '2025-11-22 08:27:54', '2025-11-22 08:27:54'),
(70, 'bill_repair', NULL, '2025-11-22 08:28:00', '2025-11-22 08:28:00'),
(71, 'repair_list', NULL, '2025-11-22 08:28:06', '2025-11-22 08:28:06'),
(72, 'update_repair_status', NULL, '2025-11-22 08:28:12', '2025-11-22 08:28:12'),
(73, 'view_repair', NULL, '2025-11-22 08:28:17', '2025-11-22 08:28:17'),
(74, 'add_role', NULL, '2025-11-22 08:28:42', '2025-11-22 08:28:42'),
(75, 'delete_role', NULL, '2025-11-22 08:29:04', '2025-11-22 08:29:04'),
(76, 'edit_role', NULL, '2025-11-22 08:29:10', '2025-11-22 08:29:10'),
(77, 'role_permissions', NULL, '2025-11-22 08:29:15', '2025-11-22 08:29:15'),
(78, 'role', NULL, '2025-11-22 08:29:23', '2025-11-22 08:29:23'),
(79, 'add_sale', NULL, '2025-11-22 08:29:31', '2025-11-22 08:29:31'),
(80, 'cancel_sale', NULL, '2025-11-22 08:29:38', '2025-11-22 08:29:38'),
(81, 'detail_sale', NULL, '2025-11-22 08:29:44', '2025-11-22 08:29:44'),
(83, 'pay_qr', NULL, '2025-11-22 08:30:12', '2025-11-22 08:30:12'),
(84, 'payment_sale', NULL, '2025-11-22 08:31:06', '2025-11-22 08:31:06'),
(85, 'payment_select', NULL, '2025-11-22 08:31:12', '2025-11-22 08:31:12'),
(86, 'sale_list', NULL, '2025-11-22 08:31:19', '2025-11-22 08:31:19'),
(87, 'save_sale', NULL, '2025-11-22 08:31:24', '2025-11-22 08:31:24'),
(88, 'view_sale', NULL, '2025-11-22 08:31:35', '2025-11-22 08:31:35'),
(89, 'add_shop', NULL, '2025-11-22 08:32:08', '2025-11-22 08:32:08'),
(90, 'delete_shop', NULL, '2025-11-22 08:32:23', '2025-11-22 08:32:23'),
(91, 'edit_shop', NULL, '2025-11-22 08:32:30', '2025-11-22 08:32:30'),
(92, 'shop', NULL, '2025-11-22 08:32:36', '2025-11-22 08:32:36'),
(93, 'view_shop', NULL, '2025-11-22 08:32:41', '2025-11-22 08:32:41'),
(94, 'add_subdistricts', NULL, '2025-11-22 08:33:14', '2025-11-22 08:33:14'),
(95, 'delete_subdistrict', NULL, '2025-11-22 08:33:19', '2025-11-22 08:33:19'),
(96, 'edit_subdistrict', NULL, '2025-11-22 08:33:24', '2025-11-22 08:33:24'),
(97, 'subdistricts', NULL, '2025-11-22 08:33:30', '2025-11-22 08:33:30'),
(98, 'add_supplier', NULL, '2025-11-22 08:33:37', '2025-11-22 08:33:37'),
(99, 'delete_supplier', NULL, '2025-11-22 08:33:42', '2025-11-22 08:33:42'),
(100, 'edit_supplier', NULL, '2025-11-22 08:33:48', '2025-11-22 08:33:48'),
(101, 'supplier', NULL, '2025-11-22 08:33:53', '2025-11-22 08:33:53'),
(102, 'view_supplier', NULL, '2025-11-22 08:33:59', '2025-11-22 08:33:59'),
(103, 'add_symptom', NULL, '2025-11-22 08:34:16', '2025-11-22 08:34:16'),
(104, 'delete_symptom', NULL, '2025-11-22 08:34:21', '2025-11-22 08:34:21'),
(105, 'edit_symptom', NULL, '2025-11-22 08:34:28', '2025-11-22 08:34:28'),
(106, 'symptoms', NULL, '2025-11-22 08:34:33', '2025-11-22 08:34:33'),
(107, 'reset_settings', NULL, '2025-11-22 08:34:41', '2025-11-22 08:34:41'),
(108, 'save_settings', NULL, '2025-11-22 08:34:47', '2025-11-22 08:34:47'),
(109, 'settings', NULL, '2025-11-22 08:34:53', '2025-11-22 08:34:53'),
(110, 'menu_general', NULL, '2025-11-22 20:53:28', '2025-11-22 20:53:28'),
(111, 'menu_purchase', NULL, '2025-11-22 20:53:36', '2025-11-22 20:53:36'),
(112, 'menu_manage_shop', NULL, '2025-11-22 20:53:51', '2025-11-22 20:53:51'),
(113, 'menu_manage_users', NULL, '2025-11-22 20:54:00', '2025-11-22 20:54:00'),
(114, 'menu_employee', NULL, '2025-11-22 20:54:14', '2025-11-22 20:54:14'),
(115, 'menu_customer', NULL, '2025-11-22 20:54:36', '2025-11-22 20:54:36'),
(116, 'menu_supplier', NULL, '2025-11-22 20:54:52', '2025-11-22 20:54:52'),
(117, 'menu_stock', NULL, '2025-11-22 20:55:04', '2025-11-22 20:55:04'),
(118, 'menu_sale', NULL, '2025-11-22 20:55:21', '2025-11-22 20:55:21'),
(119, 'menu_repair', NULL, '2025-11-22 20:55:37', '2025-11-22 20:55:37'),
(120, 'menu_dashboard', NULL, '2025-11-22 20:57:17', '2025-11-22 20:57:17'),
(121, 'menu_product', NULL, '2025-11-22 20:57:40', '2025-11-22 20:57:40'),
(122, 'change_profile', NULL, '2025-11-22 22:30:48', '2025-11-22 22:30:48'),
(123, 'change_password', NULL, '2025-11-22 22:30:56', '2025-11-22 22:30:56'),
(124, 'centralinf', NULL, '2025-12-14 17:31:44', '2025-12-14 18:25:19'),
(125, 'all_access', NULL, '2025-12-18 13:51:33', '2025-12-18 13:51:33'),
(126, 'shop_role', NULL, '2025-12-18 19:31:21', '2025-12-18 19:31:21'),
(127, 'user_list', NULL, '2026-01-03 00:21:03', '2026-01-03 00:21:03');

-- --------------------------------------------------------

--
-- Table structure for table `prefixs`
--

CREATE TABLE `prefixs` (
  `prefix_id` int(6) NOT NULL,
  `prefix_th` varchar(20) NOT NULL,
  `prefix_en` varchar(20) DEFAULT NULL,
  `prefix_th_abbr` varchar(20) DEFAULT NULL,
  `prefix_en_abbr` varchar(20) DEFAULT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะ (1=ใช้งาน, 0=ไม่ใช้งาน)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prefixs`
--

INSERT INTO `prefixs` (`prefix_id`, `prefix_th`, `prefix_en`, `prefix_th_abbr`, `prefix_en_abbr`, `shop_info_shop_id`, `is_active`) VALUES
(100001, 'นาย', 'Mister', '', 'Mr.', 0, 1),
(100002, 'นางสาว', '', '', '', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `prod_id` int(6) NOT NULL,
  `prod_code` varchar(20) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `prod_name` varchar(50) NOT NULL,
  `model_name` varchar(50) NOT NULL,
  `model_no` varchar(30) NOT NULL,
  `prod_desc` varchar(100) DEFAULT NULL,
  `prod_price` decimal(10,2) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `prod_brands_brand_id` int(4) NOT NULL,
  `prod_types_type_id` int(4) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`prod_id`, `prod_code`, `shop_info_shop_id`, `prod_name`, `model_name`, `model_no`, `prod_desc`, `prod_price`, `create_at`, `update_at`, `prod_brands_brand_id`, `prod_types_type_id`, `price`) VALUES
(100001, 'ser-2568', 0, 'ค่าแรง', '-', '-', '', 300.00, '2026-01-02 10:34:57', '2026-01-02 10:34:57', 3, 4, 0.00),
(100002, 'apple-xr01', 0, 'IPAD GEN9', 'GEN9', '999', '', 15000.00, '2026-01-02 14:08:02', '2026-01-02 14:08:02', 2, 2, 0.00),
(100003, 'apple-xr02', 0, 'Iphone Xr', 'Iphone Xr', '20001', '', 10000.00, '2026-01-02 14:39:51', '2026-01-02 14:39:51', 2, 1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `prod_brands`
--

CREATE TABLE `prod_brands` (
  `brand_id` int(4) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `brand_name_th` varchar(50) NOT NULL,
  `brand_name_en` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prod_brands`
--

INSERT INTO `prod_brands` (`brand_id`, `shop_info_shop_id`, `brand_name_th`, `brand_name_en`, `create_at`, `update_at`) VALUES
(2, 0, 'แอปเปิ้ล', 'Apple', '2025-12-18 18:02:04', '2025-12-18 18:02:04'),
(3, 0, 'บริการ', 'Service', '2025-12-18 23:08:31', '2025-12-18 23:08:31');

-- --------------------------------------------------------

--
-- Table structure for table `prod_stocks`
--

CREATE TABLE `prod_stocks` (
  `stock_id` int(6) NOT NULL,
  `branches_branch_id` int(3) NOT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_status` enum('In Stock','Sold','Damage','Reserved','Repair') NOT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL COMMENT 'เส้นทาง/ชื่อไฟล์รูปภาพ',
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `products_prod_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prod_stocks`
--

INSERT INTO `prod_stocks` (`stock_id`, `branches_branch_id`, `serial_no`, `price`, `stock_status`, `warranty_start_date`, `image_path`, `create_at`, `update_at`, `products_prod_id`) VALUES
(100001, 1, '1', 15000.00, 'Sold', NULL, NULL, '2026-01-02 14:23:59', '2026-01-03 09:39:32', 100002),
(100002, 0, 'service99999', 300.00, 'In Stock', NULL, NULL, '2026-01-02 16:48:32', '2026-01-02 16:48:32', 100001),
(100003, 0, '99545', 15000.00, 'In Stock', NULL, NULL, '2026-01-03 00:49:25', '2026-01-03 00:49:25', 100002),
(100004, 0, '555495', 15000.00, 'In Stock', NULL, NULL, '2026-01-03 00:49:26', '2026-01-03 00:49:26', 100002),
(100005, 0, '52114', 15000.00, 'In Stock', NULL, NULL, '2026-01-03 00:49:26', '2026-01-03 00:49:26', 100002),
(100006, 1, '5554', 15000.00, 'Sold', NULL, NULL, '2026-01-03 01:13:38', '2026-01-03 10:12:24', 100002),
(100007, 1, '555445', 15000.00, 'In Stock', NULL, NULL, '2026-01-03 01:13:39', '2026-01-03 10:22:39', 100002),
(100008, 1, '12453', 15000.00, 'Sold', NULL, NULL, '2026-01-03 01:13:39', '2026-01-03 09:39:32', 100002),
(100009, 1, '96555577', 10000.00, 'In Stock', NULL, NULL, '2026-01-03 01:32:53', '2026-01-03 01:32:53', 100003),
(100010, 1, '965555554', 10000.00, 'In Stock', NULL, NULL, '2026-01-03 01:32:53', '2026-01-03 01:32:53', 100003),
(100011, 1, '96555558', 10000.00, 'In Stock', NULL, NULL, '2026-01-03 01:32:53', '2026-01-03 01:32:53', 100003),
(100012, 1, '9655553', 10000.00, 'Sold', NULL, NULL, '2026-01-03 01:32:53', '2026-01-03 09:30:51', 100003),
(100013, 1, '96555541', 10000.00, 'In Stock', NULL, NULL, '2026-01-03 01:32:53', '2026-01-03 01:32:53', 100003),
(100014, 1, '177777', 0.00, 'Sold', NULL, NULL, '2026-01-03 12:21:55', '2026-01-03 12:26:34', 100002);

-- --------------------------------------------------------

--
-- Table structure for table `prod_types`
--

CREATE TABLE `prod_types` (
  `type_id` int(4) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `type_name_th` varchar(50) NOT NULL,
  `type_name_en` varchar(50) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prod_types`
--

INSERT INTO `prod_types` (`type_id`, `shop_info_shop_id`, `type_name_th`, `type_name_en`, `create_at`, `update_at`) VALUES
(1, 0, 'โทรศัพท์มือถือ', 'Smart Phone', '2025-12-18 23:06:31', '2025-12-18 23:06:31'),
(2, 0, 'แท็บเล็ต', 'Tablet', '2025-12-18 23:06:31', '2025-12-18 23:06:31'),
(3, 0, 'อะไหล่', NULL, '2025-12-18 23:06:31', '2025-12-18 23:06:31'),
(4, 0, 'บริการ', NULL, '2025-12-18 23:06:31', '2025-12-18 23:06:31');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `province_id` int(2) NOT NULL,
  `province_name_th` varchar(50) NOT NULL,
  `province_name_en` varchar(50) DEFAULT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสร้านค้า (0=ส่วนกลาง)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`province_id`, `province_name_th`, `province_name_en`, `shop_info_shop_id`) VALUES
(1, 'กรุงเทพมหานคร', 'Bangkok', 0),
(2, 'สมุทรปราการ', 'Samut Prakan', 0),
(3, 'นนทบุรี', 'Nonthaburi', 0),
(4, 'ปทุมธานี', 'Pathum Thani', 0),
(5, 'พระนครศรีอยุธยา', 'Phra Nakhon Si Ayutthaya', 0),
(6, 'อ่างทอง', 'Ang Thong', 0),
(7, 'ลพบุรี', 'Lopburi', 0),
(8, 'สิงห์บุรี', 'Sing Buri', 0),
(9, 'ชัยนาท', 'Chai Nat', 0),
(10, 'สระบุรี', 'Saraburi', 0),
(11, 'ชลบุรี', 'Chon Buri', 0),
(12, 'ระยอง', 'Rayong', 0),
(13, 'จันทบุรี', 'Chanthaburi', 0),
(14, 'ตราด', 'Trat', 0),
(15, 'ฉะเชิงเทรา', 'Chachoengsao', 0),
(16, 'ปราจีนบุรี', 'Prachin Buri', 0),
(17, 'นครนายก', 'Nakhon Nayok', 0),
(18, 'สระแก้ว', 'Sa Kaeo', 0),
(19, 'นครราชสีมา', 'Nakhon Ratchasima', 0),
(20, 'บุรีรัมย์', 'Buri Ram', 0),
(21, 'สุรินทร์', 'Surin', 0),
(22, 'ศรีสะเกษ', 'Si Sa Ket', 0),
(23, 'อุบลราชธานี', 'Ubon Ratchathani', 0),
(24, 'ยโสธร', 'Yasothon', 0),
(25, 'ชัยภูมิ', 'Chaiyaphum', 0),
(26, 'อำนาจเจริญ', 'Amnat Charoen', 0),
(27, 'หนองบัวลำภู', 'Nong Bua Lam Phu', 0),
(28, 'ขอนแก่น', 'Khon Kaen', 0),
(29, 'อุดรธานี', 'Udon Thani', 0),
(30, 'เลย', 'Loei', 0),
(31, 'หนองคาย', 'Nong Khai', 0),
(32, 'มหาสารคาม', 'Maha Sarakham', 0),
(33, 'ร้อยเอ็ด', 'Roi Et', 0),
(34, 'กาฬสินธุ์', 'Kalasin', 0),
(35, 'สกลนคร', 'Sakon Nakhon', 0),
(36, 'นครพนม', 'Nakhon Phanom', 0),
(37, 'มุกดาหาร', 'Mukdahan', 0),
(38, 'เชียงใหม่', 'Chiang Mai', 0),
(39, 'ลำพูน', 'Lamphun', 0),
(40, 'ลำปาง', 'Lampang', 0),
(41, 'อุตรดิตถ์', 'Uttaradit', 0),
(42, 'แพร่', 'Phrae', 0),
(43, 'น่าน', 'Nan', 0),
(44, 'พะเยา', 'Phayao', 0),
(45, 'เชียงราย', 'Chiang Rai', 0),
(46, 'แม่ฮ่องสอน', 'Mae Hong Son', 0),
(47, 'นครสวรรค์', 'Nakhon Sawan', 0),
(48, 'อุทัยธานี', 'Uthai Thani', 0),
(49, 'กำแพงเพชร', 'Kamphaeng Phet', 0),
(50, 'ตาก', 'Tak', 0),
(51, 'สุโขทัย', 'Sukhothai', 0),
(52, 'พิษณุโลก', 'Phitsanulok', 0),
(53, 'พิจิตร', 'Phichit', 0),
(54, 'เพชรบูรณ์', 'Phetchabun', 0),
(55, 'ราชบุรี', 'Ratchaburi', 0),
(56, 'กาญจนบุรี', 'Kanchanaburi', 0),
(57, 'สุพรรณบุรี', 'Suphan Buri', 0),
(58, 'นครปฐม', 'Nakhon Pathom', 0),
(59, 'สมุทรสาคร', 'Samut Sakhon', 0),
(60, 'สมุทรสงคราม', 'Samut Songkhram', 0),
(61, 'เพชรบุรี', 'Phetchaburi', 0),
(62, 'ประจวบคีรีขันธ์', 'Prachuap Khiri Khan', 0),
(63, 'นครศรีธรรมราช', 'Nakhon Si Thammarat', 0),
(64, 'กระบี่', 'Krabi', 0),
(65, 'พังงา', 'Phangnga', 0),
(66, 'ภูเก็ต', 'Phuket', 0),
(67, 'สุราษฎร์ธานี', 'Surat Thani', 0),
(68, 'ระนอง', 'Ranong', 0),
(69, 'ชุมพร', 'Chumphon', 0),
(70, 'สงขลา', 'Songkhla', 0),
(71, 'สตูล', 'Satun', 0),
(72, 'ตรัง', 'Trang', 0),
(73, 'พัทลุง', 'Phatthalung', 0),
(74, 'ปัตตานี', 'Pattani', 0),
(75, 'ยะลา', 'Yala', 0),
(76, 'นราธิวาส', 'Narathiwat', 0),
(77, 'บึงกาฬ', 'Bueng Kan', 0);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `purchase_id` int(11) NOT NULL,
  `purchase_date` datetime NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `suppliers_supplier_id` int(6) NOT NULL,
  `branches_branch_id` int(3) NOT NULL,
  `employees_emp_id` int(6) NOT NULL,
  `po_status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `cancel_comment` text DEFAULT NULL COMMENT 'เหตุผลการยกเลิก PO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`purchase_id`, `purchase_date`, `create_at`, `update_at`, `suppliers_supplier_id`, `branches_branch_id`, `employees_emp_id`, `po_status`, `cancel_comment`) VALUES
(1, '2026-01-03 00:48:00', '2026-01-03 00:49:03', '2026-01-03 00:49:03', 100001, 1, 10, 'Pending', NULL),
(2, '2026-01-03 01:12:00', '2026-01-03 01:13:00', '2026-01-03 01:13:39', 100001, 1, 10, 'Completed', NULL),
(3, '2026-01-03 01:16:00', '2026-01-03 01:17:12', '2026-01-03 01:32:53', 100001, 1, 10, 'Completed', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `religions`
--

CREATE TABLE `religions` (
  `religion_id` int(2) NOT NULL,
  `religion_name_th` varchar(30) NOT NULL,
  `religion_name_en` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะ (1=ใช้งาน, 0=ไม่ใช้งาน)',
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสร้านค้า (0=ส่วนกลาง)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `religions`
--

INSERT INTO `religions` (`religion_id`, `religion_name_th`, `religion_name_en`, `is_active`, `shop_info_shop_id`) VALUES
(10, 'พุทธ', '', 1, 0),
(11, 'อิสลาม', '', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `repair_id` int(6) NOT NULL,
  `branches_branch_id` int(3) NOT NULL,
  `repair_desc` varchar(100) DEFAULT NULL,
  `device_description` varchar(255) DEFAULT NULL,
  `repair_status` enum('รับเครื่อง','ประเมิน','รออะไหล่','กำลังซ่อม','ซ่อมเสร็จ','ส่งมอบ','ยกเลิก') NOT NULL,
  `estimated_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'ราคารับซ่อมประเมินเบื้องต้น',
  `accessories_list` varchar(255) DEFAULT NULL COMMENT 'รายการอุปกรณ์เสริมที่ลูกค้าให้มา',
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `customers_cs_id` int(6) NOT NULL,
  `prod_stocks_stock_id` int(6) NOT NULL,
  `bill_headers_bill_id` int(11) DEFAULT NULL,
  `employees_emp_id` int(6) NOT NULL,
  `assigned_employee_id` int(6) DEFAULT NULL COMMENT 'ช่าง/พนักงานที่รับผิดชอบงานซ่อม'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repairs`
--

INSERT INTO `repairs` (`repair_id`, `branches_branch_id`, `repair_desc`, `device_description`, `repair_status`, `estimated_cost`, `accessories_list`, `create_at`, `update_at`, `customers_cs_id`, `prod_stocks_stock_id`, `bill_headers_bill_id`, `employees_emp_id`, `assigned_employee_id`) VALUES
(100001, 1, '', '', 'ส่งมอบ', 0.01, '', '2026-01-03 12:21:55', '2026-01-03 12:26:34', 100001, 100014, 4, 9, 2);

-- --------------------------------------------------------

--
-- Table structure for table `repair_status_log`
--

CREATE TABLE `repair_status_log` (
  `log_id` int(11) NOT NULL,
  `repairs_repair_id` int(6) NOT NULL,
  `old_status` enum('รับเครื่อง','ประเมิน','รออะไหล่','กำลังซ่อม','ซ่อมเสร็จ','ส่งมอบ','ยกเลิก') DEFAULT NULL,
  `new_status` enum('รับเครื่อง','ประเมิน','รออะไหล่','กำลังซ่อม','ซ่อมเสร็จ','ส่งมอบ','ยกเลิก') NOT NULL,
  `update_by_employee_id` int(6) NOT NULL COMMENT 'พนักงานที่ทำการอัพเดทสถานะ',
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `comment` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุการอัพเดทสถานะ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repair_status_log`
--

INSERT INTO `repair_status_log` (`log_id`, `repairs_repair_id`, `old_status`, `new_status`, `update_by_employee_id`, `update_at`, `comment`) VALUES
(3, 100001, NULL, 'รับเครื่อง', 9, '2026-01-03 12:21:55', NULL),
(4, 100001, 'รับเครื่อง', 'ประเมิน', 1, '2026-01-03 12:24:40', ''),
(5, 100001, 'ประเมิน', 'ซ่อมเสร็จ', 1, '2026-01-03 12:25:35', ''),
(6, 100001, 'ซ่อมเสร็จ', 'ส่งมอบ', 1, '2026-01-03 12:25:54', 'ชำระเงินและส่งมอบอัตโนมัติ (Cash)'),
(7, 100001, 'ส่งมอบ', 'รับเครื่อง', 1, '2026-01-03 12:26:26', ''),
(8, 100001, 'รับเครื่อง', 'ส่งมอบ', 1, '2026-01-03 12:26:34', '');

-- --------------------------------------------------------

--
-- Table structure for table `repair_symptoms`
--

CREATE TABLE `repair_symptoms` (
  `repairs_repair_id` int(6) NOT NULL,
  `symptoms_symptom_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repair_symptoms`
--

INSERT INTO `repair_symptoms` (`repairs_repair_id`, `symptoms_symptom_id`) VALUES
(100001, 100002),
(100002, 100002);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(3) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_desc` varchar(100) DEFAULT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_desc`, `shop_info_shop_id`, `create_at`, `update_at`) VALUES
(1, 'Admin', 'Admin', 0, '2025-12-14 20:38:15', '2026-01-03 00:21:16'),
(2, 'Partner', NULL, 0, '2025-12-18 09:54:55', '2025-12-18 13:46:10'),
(3, 'SuperAdmin', NULL, 0, '2025-12-18 13:51:08', '2026-01-03 00:21:11');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `roles_role_id` int(3) NOT NULL,
  `permissions_permission_id` int(3) NOT NULL,
  `create_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`roles_role_id`, `permissions_permission_id`, `create_at`) VALUES
(1, 1, '2026-01-03 00:21:16'),
(1, 2, '2026-01-03 00:21:16'),
(1, 3, '2026-01-03 00:21:16'),
(1, 4, '2026-01-03 00:21:16'),
(1, 5, '2026-01-03 00:21:16'),
(1, 6, '2026-01-03 00:21:16'),
(1, 7, '2026-01-03 00:21:16'),
(1, 8, '2026-01-03 00:21:16'),
(1, 9, '2026-01-03 00:21:16'),
(1, 10, '2026-01-03 00:21:16'),
(1, 11, '2026-01-03 00:21:16'),
(1, 12, '2026-01-03 00:21:16'),
(1, 13, '2026-01-03 00:21:16'),
(1, 14, '2026-01-03 00:21:16'),
(1, 15, '2026-01-03 00:21:16'),
(1, 16, '2026-01-03 00:21:16'),
(1, 17, '2026-01-03 00:21:16'),
(1, 18, '2026-01-03 00:21:16'),
(1, 19, '2026-01-03 00:21:16'),
(1, 20, '2026-01-03 00:21:16'),
(1, 21, '2026-01-03 00:21:16'),
(1, 22, '2026-01-03 00:21:16'),
(1, 23, '2026-01-03 00:21:16'),
(1, 24, '2026-01-03 00:21:16'),
(1, 25, '2026-01-03 00:21:16'),
(1, 26, '2026-01-03 00:21:16'),
(1, 27, '2026-01-03 00:21:16'),
(1, 28, '2026-01-03 00:21:16'),
(1, 29, '2026-01-03 00:21:16'),
(1, 30, '2026-01-03 00:21:16'),
(1, 31, '2026-01-03 00:21:16'),
(1, 32, '2026-01-03 00:21:16'),
(1, 33, '2026-01-03 00:21:16'),
(1, 34, '2026-01-03 00:21:16'),
(1, 35, '2026-01-03 00:21:16'),
(1, 36, '2026-01-03 00:21:16'),
(1, 37, '2026-01-03 00:21:16'),
(1, 38, '2026-01-03 00:21:16'),
(1, 39, '2026-01-03 00:21:16'),
(1, 40, '2026-01-03 00:21:16'),
(1, 41, '2026-01-03 00:21:16'),
(1, 42, '2026-01-03 00:21:16'),
(1, 43, '2026-01-03 00:21:16'),
(1, 44, '2026-01-03 00:21:16'),
(1, 45, '2026-01-03 00:21:16'),
(1, 46, '2026-01-03 00:21:16'),
(1, 47, '2026-01-03 00:21:16'),
(1, 48, '2026-01-03 00:21:16'),
(1, 49, '2026-01-03 00:21:16'),
(1, 50, '2026-01-03 00:21:16'),
(1, 51, '2026-01-03 00:21:16'),
(1, 52, '2026-01-03 00:21:16'),
(1, 53, '2026-01-03 00:21:16'),
(1, 54, '2026-01-03 00:21:16'),
(1, 55, '2026-01-03 00:21:16'),
(1, 56, '2026-01-03 00:21:16'),
(1, 57, '2026-01-03 00:21:16'),
(1, 58, '2026-01-03 00:21:16'),
(1, 59, '2026-01-03 00:21:16'),
(1, 60, '2026-01-03 00:21:16'),
(1, 61, '2026-01-03 00:21:16'),
(1, 62, '2026-01-03 00:21:16'),
(1, 63, '2026-01-03 00:21:16'),
(1, 64, '2026-01-03 00:21:16'),
(1, 65, '2026-01-03 00:21:16'),
(1, 66, '2026-01-03 00:21:16'),
(1, 67, '2026-01-03 00:21:16'),
(1, 68, '2026-01-03 00:21:16'),
(1, 69, '2026-01-03 00:21:16'),
(1, 70, '2026-01-03 00:21:16'),
(1, 71, '2026-01-03 00:21:16'),
(1, 72, '2026-01-03 00:21:16'),
(1, 73, '2026-01-03 00:21:16'),
(1, 74, '2026-01-03 00:21:16'),
(1, 75, '2026-01-03 00:21:16'),
(1, 76, '2026-01-03 00:21:16'),
(1, 77, '2026-01-03 00:21:16'),
(1, 78, '2026-01-03 00:21:16'),
(1, 79, '2026-01-03 00:21:16'),
(1, 80, '2026-01-03 00:21:16'),
(1, 81, '2026-01-03 00:21:16'),
(1, 83, '2026-01-03 00:21:16'),
(1, 84, '2026-01-03 00:21:16'),
(1, 85, '2026-01-03 00:21:16'),
(1, 86, '2026-01-03 00:21:16'),
(1, 87, '2026-01-03 00:21:16'),
(1, 88, '2026-01-03 00:21:16'),
(1, 89, '2026-01-03 00:21:16'),
(1, 90, '2026-01-03 00:21:16'),
(1, 91, '2026-01-03 00:21:16'),
(1, 92, '2026-01-03 00:21:16'),
(1, 93, '2026-01-03 00:21:16'),
(1, 94, '2026-01-03 00:21:16'),
(1, 95, '2026-01-03 00:21:16'),
(1, 96, '2026-01-03 00:21:16'),
(1, 97, '2026-01-03 00:21:16'),
(1, 98, '2026-01-03 00:21:16'),
(1, 99, '2026-01-03 00:21:16'),
(1, 100, '2026-01-03 00:21:16'),
(1, 101, '2026-01-03 00:21:16'),
(1, 102, '2026-01-03 00:21:16'),
(1, 103, '2026-01-03 00:21:16'),
(1, 104, '2026-01-03 00:21:16'),
(1, 105, '2026-01-03 00:21:16'),
(1, 106, '2026-01-03 00:21:16'),
(1, 107, '2026-01-03 00:21:16'),
(1, 108, '2026-01-03 00:21:16'),
(1, 109, '2026-01-03 00:21:16'),
(1, 110, '2026-01-03 00:21:16'),
(1, 111, '2026-01-03 00:21:16'),
(1, 112, '2026-01-03 00:21:16'),
(1, 113, '2026-01-03 00:21:16'),
(1, 114, '2026-01-03 00:21:16'),
(1, 115, '2026-01-03 00:21:16'),
(1, 116, '2026-01-03 00:21:16'),
(1, 117, '2026-01-03 00:21:16'),
(1, 118, '2026-01-03 00:21:16'),
(1, 119, '2026-01-03 00:21:16'),
(1, 120, '2026-01-03 00:21:16'),
(1, 121, '2026-01-03 00:21:16'),
(1, 122, '2026-01-03 00:21:16'),
(1, 123, '2026-01-03 00:21:16'),
(1, 124, '2026-01-03 00:21:16'),
(1, 125, '2026-01-03 00:21:16'),
(1, 126, '2026-01-03 00:21:16'),
(1, 127, '2026-01-03 00:21:16'),
(2, 1, '2025-12-18 13:46:10'),
(2, 2, '2025-12-18 13:46:10'),
(2, 3, '2025-12-18 13:46:10'),
(2, 4, '2025-12-18 13:46:10'),
(2, 5, '2025-12-18 13:46:10'),
(2, 6, '2025-12-18 13:46:10'),
(2, 7, '2025-12-18 13:46:10'),
(2, 8, '2025-12-18 13:46:10'),
(2, 9, '2025-12-18 13:46:10'),
(2, 10, '2025-12-18 13:46:10'),
(2, 11, '2025-12-18 13:46:10'),
(2, 12, '2025-12-18 13:46:10'),
(2, 13, '2025-12-18 13:46:10'),
(2, 14, '2025-12-18 13:46:10'),
(2, 15, '2025-12-18 13:46:10'),
(2, 16, '2025-12-18 13:46:10'),
(2, 17, '2025-12-18 13:46:10'),
(2, 18, '2025-12-18 13:46:10'),
(2, 19, '2025-12-18 13:46:10'),
(2, 20, '2025-12-18 13:46:10'),
(2, 21, '2025-12-18 13:46:10'),
(2, 22, '2025-12-18 13:46:10'),
(2, 23, '2025-12-18 13:46:10'),
(2, 24, '2025-12-18 13:46:10'),
(2, 25, '2025-12-18 13:46:10'),
(2, 26, '2025-12-18 13:46:10'),
(2, 27, '2025-12-18 13:46:10'),
(2, 28, '2025-12-18 13:46:10'),
(2, 29, '2025-12-18 13:46:10'),
(2, 30, '2025-12-18 13:46:10'),
(2, 31, '2025-12-18 13:46:10'),
(2, 32, '2025-12-18 13:46:10'),
(2, 33, '2025-12-18 13:46:10'),
(2, 34, '2025-12-18 13:46:10'),
(2, 35, '2025-12-18 13:46:10'),
(2, 36, '2025-12-18 13:46:10'),
(2, 37, '2025-12-18 13:46:10'),
(2, 38, '2025-12-18 13:46:10'),
(2, 39, '2025-12-18 13:46:10'),
(2, 40, '2025-12-18 13:46:10'),
(2, 41, '2025-12-18 13:46:10'),
(2, 42, '2025-12-18 13:46:10'),
(2, 43, '2025-12-18 13:46:10'),
(2, 44, '2025-12-18 13:46:10'),
(2, 45, '2025-12-18 13:46:10'),
(2, 46, '2025-12-18 13:46:10'),
(2, 47, '2025-12-18 13:46:10'),
(2, 48, '2025-12-18 13:46:10'),
(2, 49, '2025-12-18 13:46:10'),
(2, 50, '2025-12-18 13:46:10'),
(2, 51, '2025-12-18 13:46:10'),
(2, 52, '2025-12-18 13:46:10'),
(2, 53, '2025-12-18 13:46:10'),
(2, 54, '2025-12-18 13:46:10'),
(2, 55, '2025-12-18 13:46:10'),
(2, 56, '2025-12-18 13:46:10'),
(2, 57, '2025-12-18 13:46:10'),
(2, 58, '2025-12-18 13:46:10'),
(2, 59, '2025-12-18 13:46:10'),
(2, 60, '2025-12-18 13:46:10'),
(2, 61, '2025-12-18 13:46:10'),
(2, 62, '2025-12-18 13:46:10'),
(2, 63, '2025-12-18 13:46:10'),
(2, 64, '2025-12-18 13:46:10'),
(2, 65, '2025-12-18 13:46:10'),
(2, 66, '2025-12-18 13:46:10'),
(2, 67, '2025-12-18 13:46:10'),
(2, 68, '2025-12-18 13:46:10'),
(2, 69, '2025-12-18 13:46:10'),
(2, 70, '2025-12-18 13:46:10'),
(2, 71, '2025-12-18 13:46:10'),
(2, 72, '2025-12-18 13:46:10'),
(2, 73, '2025-12-18 13:46:10'),
(2, 74, '2025-12-18 13:46:10'),
(2, 75, '2025-12-18 13:46:10'),
(2, 76, '2025-12-18 13:46:10'),
(2, 77, '2025-12-18 13:46:10'),
(2, 78, '2025-12-18 13:46:10'),
(2, 79, '2025-12-18 13:46:10'),
(2, 80, '2025-12-18 13:46:10'),
(2, 81, '2025-12-18 13:46:10'),
(2, 83, '2025-12-18 13:46:10'),
(2, 84, '2025-12-18 13:46:10'),
(2, 85, '2025-12-18 13:46:10'),
(2, 86, '2025-12-18 13:46:10'),
(2, 87, '2025-12-18 13:46:10'),
(2, 88, '2025-12-18 13:46:10'),
(2, 89, '2025-12-18 13:46:10'),
(2, 90, '2025-12-18 13:46:10'),
(2, 91, '2025-12-18 13:46:10'),
(2, 92, '2025-12-18 13:46:10'),
(2, 93, '2025-12-18 13:46:10'),
(2, 94, '2025-12-18 13:46:10'),
(2, 95, '2025-12-18 13:46:10'),
(2, 96, '2025-12-18 13:46:10'),
(2, 97, '2025-12-18 13:46:10'),
(2, 98, '2025-12-18 13:46:10'),
(2, 99, '2025-12-18 13:46:10'),
(2, 100, '2025-12-18 13:46:10'),
(2, 101, '2025-12-18 13:46:10'),
(2, 102, '2025-12-18 13:46:10'),
(2, 103, '2025-12-18 13:46:10'),
(2, 104, '2025-12-18 13:46:10'),
(2, 105, '2025-12-18 13:46:10'),
(2, 106, '2025-12-18 13:46:10'),
(2, 107, '2025-12-18 13:46:10'),
(2, 108, '2025-12-18 13:46:10'),
(2, 109, '2025-12-18 13:46:10'),
(2, 110, '2025-12-18 13:46:10'),
(2, 111, '2025-12-18 13:46:10'),
(2, 112, '2025-12-18 13:46:10'),
(2, 113, '2025-12-18 13:46:10'),
(2, 114, '2025-12-18 13:46:10'),
(2, 115, '2025-12-18 13:46:10'),
(2, 116, '2025-12-18 13:46:10'),
(2, 117, '2025-12-18 13:46:10'),
(2, 118, '2025-12-18 13:46:10'),
(2, 119, '2025-12-18 13:46:10'),
(2, 120, '2025-12-18 13:46:10'),
(2, 121, '2025-12-18 13:46:10'),
(2, 122, '2025-12-18 13:46:10'),
(2, 123, '2025-12-18 13:46:10'),
(3, 1, '2026-01-03 00:21:11'),
(3, 2, '2026-01-03 00:21:11'),
(3, 3, '2026-01-03 00:21:11'),
(3, 4, '2026-01-03 00:21:11'),
(3, 5, '2026-01-03 00:21:11'),
(3, 6, '2026-01-03 00:21:11'),
(3, 7, '2026-01-03 00:21:11'),
(3, 8, '2026-01-03 00:21:11'),
(3, 9, '2026-01-03 00:21:11'),
(3, 10, '2026-01-03 00:21:11'),
(3, 11, '2026-01-03 00:21:11'),
(3, 12, '2026-01-03 00:21:11'),
(3, 13, '2026-01-03 00:21:11'),
(3, 14, '2026-01-03 00:21:11'),
(3, 15, '2026-01-03 00:21:11'),
(3, 16, '2026-01-03 00:21:11'),
(3, 17, '2026-01-03 00:21:11'),
(3, 18, '2026-01-03 00:21:11'),
(3, 19, '2026-01-03 00:21:11'),
(3, 20, '2026-01-03 00:21:11'),
(3, 21, '2026-01-03 00:21:11'),
(3, 22, '2026-01-03 00:21:11'),
(3, 23, '2026-01-03 00:21:11'),
(3, 24, '2026-01-03 00:21:11'),
(3, 25, '2026-01-03 00:21:11'),
(3, 26, '2026-01-03 00:21:11'),
(3, 27, '2026-01-03 00:21:11'),
(3, 28, '2026-01-03 00:21:11'),
(3, 29, '2026-01-03 00:21:11'),
(3, 30, '2026-01-03 00:21:11'),
(3, 31, '2026-01-03 00:21:11'),
(3, 32, '2026-01-03 00:21:11'),
(3, 33, '2026-01-03 00:21:11'),
(3, 34, '2026-01-03 00:21:11'),
(3, 35, '2026-01-03 00:21:11'),
(3, 36, '2026-01-03 00:21:11'),
(3, 37, '2026-01-03 00:21:11'),
(3, 38, '2026-01-03 00:21:11'),
(3, 39, '2026-01-03 00:21:11'),
(3, 40, '2026-01-03 00:21:11'),
(3, 41, '2026-01-03 00:21:11'),
(3, 42, '2026-01-03 00:21:11'),
(3, 43, '2026-01-03 00:21:11'),
(3, 44, '2026-01-03 00:21:11'),
(3, 45, '2026-01-03 00:21:11'),
(3, 46, '2026-01-03 00:21:11'),
(3, 47, '2026-01-03 00:21:11'),
(3, 48, '2026-01-03 00:21:11'),
(3, 49, '2026-01-03 00:21:11'),
(3, 50, '2026-01-03 00:21:11'),
(3, 51, '2026-01-03 00:21:11'),
(3, 52, '2026-01-03 00:21:11'),
(3, 53, '2026-01-03 00:21:11'),
(3, 54, '2026-01-03 00:21:11'),
(3, 55, '2026-01-03 00:21:11'),
(3, 56, '2026-01-03 00:21:11'),
(3, 57, '2026-01-03 00:21:11'),
(3, 58, '2026-01-03 00:21:11'),
(3, 59, '2026-01-03 00:21:11'),
(3, 60, '2026-01-03 00:21:11'),
(3, 61, '2026-01-03 00:21:11'),
(3, 62, '2026-01-03 00:21:11'),
(3, 63, '2026-01-03 00:21:11'),
(3, 64, '2026-01-03 00:21:11'),
(3, 65, '2026-01-03 00:21:11'),
(3, 66, '2026-01-03 00:21:11'),
(3, 67, '2026-01-03 00:21:11'),
(3, 68, '2026-01-03 00:21:11'),
(3, 69, '2026-01-03 00:21:11'),
(3, 70, '2026-01-03 00:21:11'),
(3, 71, '2026-01-03 00:21:11'),
(3, 72, '2026-01-03 00:21:11'),
(3, 73, '2026-01-03 00:21:11'),
(3, 74, '2026-01-03 00:21:11'),
(3, 75, '2026-01-03 00:21:11'),
(3, 76, '2026-01-03 00:21:11'),
(3, 77, '2026-01-03 00:21:11'),
(3, 78, '2026-01-03 00:21:11'),
(3, 79, '2026-01-03 00:21:11'),
(3, 80, '2026-01-03 00:21:11'),
(3, 81, '2026-01-03 00:21:11'),
(3, 83, '2026-01-03 00:21:11'),
(3, 84, '2026-01-03 00:21:11'),
(3, 85, '2026-01-03 00:21:11'),
(3, 86, '2026-01-03 00:21:11'),
(3, 87, '2026-01-03 00:21:11'),
(3, 88, '2026-01-03 00:21:11'),
(3, 89, '2026-01-03 00:21:11'),
(3, 90, '2026-01-03 00:21:11'),
(3, 91, '2026-01-03 00:21:11'),
(3, 92, '2026-01-03 00:21:11'),
(3, 93, '2026-01-03 00:21:11'),
(3, 94, '2026-01-03 00:21:11'),
(3, 95, '2026-01-03 00:21:11'),
(3, 96, '2026-01-03 00:21:11'),
(3, 97, '2026-01-03 00:21:11'),
(3, 98, '2026-01-03 00:21:11'),
(3, 99, '2026-01-03 00:21:11'),
(3, 100, '2026-01-03 00:21:11'),
(3, 101, '2026-01-03 00:21:11'),
(3, 102, '2026-01-03 00:21:11'),
(3, 103, '2026-01-03 00:21:11'),
(3, 104, '2026-01-03 00:21:11'),
(3, 105, '2026-01-03 00:21:11'),
(3, 106, '2026-01-03 00:21:11'),
(3, 107, '2026-01-03 00:21:11'),
(3, 108, '2026-01-03 00:21:11'),
(3, 109, '2026-01-03 00:21:11'),
(3, 110, '2026-01-03 00:21:11'),
(3, 111, '2026-01-03 00:21:11'),
(3, 112, '2026-01-03 00:21:11'),
(3, 113, '2026-01-03 00:21:11'),
(3, 114, '2026-01-03 00:21:11'),
(3, 115, '2026-01-03 00:21:11'),
(3, 116, '2026-01-03 00:21:11'),
(3, 117, '2026-01-03 00:21:11'),
(3, 118, '2026-01-03 00:21:11'),
(3, 119, '2026-01-03 00:21:11'),
(3, 120, '2026-01-03 00:21:11'),
(3, 121, '2026-01-03 00:21:11'),
(3, 122, '2026-01-03 00:21:11'),
(3, 123, '2026-01-03 00:21:11'),
(3, 124, '2026-01-03 00:21:11'),
(3, 125, '2026-01-03 00:21:11'),
(3, 126, '2026-01-03 00:21:11'),
(3, 127, '2026-01-03 00:21:11'),
(4, 39, '2026-01-02 23:47:54'),
(4, 43, '2026-01-02 23:47:54'),
(4, 52, '2026-01-02 23:47:54'),
(4, 61, '2026-01-02 23:47:54'),
(4, 106, '2026-01-02 23:47:54'),
(4, 111, '2026-01-02 23:47:54'),
(4, 117, '2026-01-02 23:47:54'),
(4, 119, '2026-01-02 23:47:54'),
(4, 121, '2026-01-02 23:47:54');

-- --------------------------------------------------------

--
-- Table structure for table `shop_info`
--

CREATE TABLE `shop_info` (
  `shop_id` int(3) NOT NULL,
  `shop_name` varchar(50) NOT NULL,
  `tax_id` varchar(20) NOT NULL,
  `shop_phone` varchar(20) NOT NULL,
  `shop_email` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `Addresses_address_id` int(6) NOT NULL,
  `shop_app_password` varchar(50) DEFAULT NULL COMMENT 'รหัสผ่านแอปสำหรับส่งอีเมล',
  `promptpay_number` varchar(20) DEFAULT NULL COMMENT 'เบอร์พร้อมเพย์หรือเลขบัญชีสำหรับรับเงิน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_info`
--

INSERT INTO `shop_info` (`shop_id`, `shop_name`, `tax_id`, `shop_phone`, `shop_email`, `logo`, `create_at`, `update_at`, `Addresses_address_id`, `shop_app_password`, `promptpay_number`) VALUES
(0, 'ส่วนกลาง (Central)', '', '', NULL, NULL, '2025-12-21 20:01:35', '2025-12-21 20:01:35', 0, NULL, NULL),
(1, 'ขุมทรัพย์', '1234567890123', '0800000000', 'adisonsompeng49@gmail.com', NULL, '2025-12-21 21:04:43', '2025-12-21 21:04:43', 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST') NOT NULL,
  `ref_table` varchar(50) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `create_at` datetime DEFAULT current_timestamp(),
  `prod_stocks_stock_id` int(6) NOT NULL,
  `prodout_types_outtype_id` int(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`movement_id`, `movement_type`, `ref_table`, `ref_id`, `create_at`, `prod_stocks_stock_id`, `prodout_types_outtype_id`) VALUES
(1, 'IN', 'FREEBIE', NULL, '2026-01-02 14:23:59', 100001, NULL),
(2, 'IN', 'MANUAL_ENTRY', NULL, '2026-01-02 16:48:32', 100002, NULL),
(3, 'IN', 'order_details', 1, '2026-01-03 00:49:26', 100003, NULL),
(4, 'IN', 'order_details', 1, '2026-01-03 00:49:26', 100004, NULL),
(5, 'IN', 'order_details', 1, '2026-01-03 00:49:26', 100005, NULL),
(6, 'IN', 'order_details', 2, '2026-01-03 01:13:38', 100006, NULL),
(7, 'IN', 'order_details', 2, '2026-01-03 01:13:39', 100007, NULL),
(8, 'IN', 'order_details', 2, '2026-01-03 01:13:39', 100008, NULL),
(9, 'IN', 'order_details', 3, '2026-01-03 01:32:53', 100009, NULL),
(10, 'IN', 'order_details', 3, '2026-01-03 01:32:53', 100010, NULL),
(11, 'IN', 'order_details', 3, '2026-01-03 01:32:53', 100011, NULL),
(12, 'IN', 'order_details', 3, '2026-01-03 01:32:53', 100012, NULL),
(13, 'IN', 'order_details', 3, '2026-01-03 01:32:53', 100013, NULL),
(14, 'OUT', 'bill_headers', 0, '2026-01-03 09:30:51', 100012, NULL),
(15, 'OUT', 'bill_headers', 1, '2026-01-03 09:39:32', 100001, NULL),
(16, 'OUT', 'bill_headers', 1, '2026-01-03 09:39:32', 100008, NULL),
(17, 'OUT', 'bill_headers', 2, '2026-01-03 10:12:24', 100006, NULL),
(18, 'OUT', 'bill_headers', 3, '2026-01-03 10:13:47', 100007, NULL),
(19, 'ADJUST', 'bill_headers (Cancel)', 3, '2026-01-03 10:22:39', 100007, NULL),
(20, 'IN', 'repairs', 100001, '2026-01-03 12:21:55', 100014, NULL),
(21, 'OUT', 'repairs_return', 100001, '2026-01-03 12:25:54', 100014, NULL),
(22, 'OUT', 'deliver_repaired_job', 100001, '2026-01-03 12:26:34', 100014, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subdistricts`
--

CREATE TABLE `subdistricts` (
  `subdistrict_id` int(6) NOT NULL,
  `subdistrict_name_th` varchar(50) NOT NULL,
  `subdistrict_name_en` varchar(50) DEFAULT NULL,
  `zip_code` varchar(5) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสร้านค้า (0=ส่วนกลาง)',
  `districts_district_id` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subdistricts`
--

INSERT INTO `subdistricts` (`subdistrict_id`, `subdistrict_name_th`, `subdistrict_name_en`, `zip_code`, `shop_info_shop_id`, `districts_district_id`) VALUES
(100101, 'พระบรมมหาราชวัง', 'Phra Borom Maha Ratchawang', '10200', 0, 1001),
(100102, 'วังบูรพาภิรมย์', 'Wang Burapha Phirom', '10200', 0, 1001),
(100103, 'วัดราชบพิธ', 'Wat Ratchabophit', '10200', 0, 1001),
(100104, 'สำราญราษฎร์', 'Samran Rat', '10200', 0, 1001),
(100105, 'ศาลเจ้าพ่อเสือ', 'San Chao Pho Suea', '10200', 0, 1001),
(100106, 'เสาชิงช้า', 'Sao Chingcha', '10200', 0, 1001),
(100107, 'บวรนิเวศ', 'Bowon Niwet', '10200', 0, 1001),
(100108, 'ตลาดยอด', 'Talat Yot', '10200', 0, 1001),
(100109, 'ชนะสงคราม', 'Chana Songkhram', '10200', 0, 1001),
(100110, 'บ้านพานถม', 'Ban Phan Thom', '10200', 0, 1001),
(100111, 'บางขุนพรหม', 'Bang Khun Phrom', '10200', 0, 1001),
(100112, 'วัดสามพระยา', 'Wat Sam Phraya', '10200', 0, 1001);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(6) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `branches_branch_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสสาขาที่สังกัด',
  `co_name` varchar(50) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `contact_firstname` varchar(50) DEFAULT NULL,
  `contact_lastname` varchar(50) DEFAULT NULL,
  `supplier_email` varchar(50) DEFAULT NULL,
  `supplier_phone_no` varchar(20) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp(),
  `prefixs_prefix_id` int(6) DEFAULT NULL,
  `Addresses_address_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `shop_info_shop_id`, `branches_branch_id`, `co_name`, `tax_id`, `contact_firstname`, `contact_lastname`, `supplier_email`, `supplier_phone_no`, `create_at`, `update_at`, `prefixs_prefix_id`, `Addresses_address_id`) VALUES
(100001, 1, 1, 'Advice', '', '', '', '', '', '2026-01-02 10:16:10', '2026-01-02 10:24:01', 100002, 7);

-- --------------------------------------------------------

--
-- Table structure for table `symptoms`
--

CREATE TABLE `symptoms` (
  `symptom_id` int(6) NOT NULL,
  `symptom_name` varchar(50) NOT NULL,
  `symptom_desc` varchar(100) DEFAULT NULL,
  `shop_info_shop_id` int(3) NOT NULL DEFAULT 0 COMMENT 'รหัสร้านค้า (0=ส่วนกลาง)',
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `symptoms`
--

INSERT INTO `symptoms` (`symptom_id`, `symptom_name`, `symptom_desc`, `shop_info_shop_id`, `create_at`, `update_at`) VALUES
(100001, 'จอแตก', '', 0, '2025-12-19 01:03:51', '2025-12-19 01:03:51'),
(100002, 'ชาร์จไม่เข้า', '', 0, '2025-12-19 01:03:55', '2025-12-19 01:03:55'),
(100003, 'เปิดไม่ติด', '', 0, '2025-12-19 01:04:17', '2025-12-19 01:04:17');

-- --------------------------------------------------------

--
-- Table structure for table `systemconfig`
--

CREATE TABLE `systemconfig` (
  `user_id` int(6) NOT NULL,
  `theme_color` varchar(20) DEFAULT NULL,
  `background_color` varchar(20) DEFAULT NULL,
  `text_color` varchar(20) DEFAULT NULL,
  `font_style` varchar(100) DEFAULT NULL,
  `header_bg_color` varchar(20) DEFAULT NULL,
  `header_text_color` varchar(20) DEFAULT NULL,
  `btn_add_color` varchar(20) DEFAULT NULL,
  `btn_edit_color` varchar(20) DEFAULT NULL,
  `btn_delete_color` varchar(20) DEFAULT NULL,
  `status_on_color` varchar(20) DEFAULT NULL,
  `status_off_color` varchar(20) DEFAULT NULL,
  `warning_bg_color` varchar(20) DEFAULT NULL,
  `danger_text_color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `systemconfig`
--

INSERT INTO `systemconfig` (`user_id`, `theme_color`, `background_color`, `text_color`, `font_style`, `header_bg_color`, `header_text_color`, `btn_add_color`, `btn_edit_color`, `btn_delete_color`, `status_on_color`, `status_off_color`, `warning_bg_color`, `danger_text_color`) VALUES
(1, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', '#198754', '#ffc107', '#dc3545', NULL, NULL, NULL, NULL),
(0, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', '#198754', '#ffc107', '#dc3545', NULL, NULL, NULL, NULL),
(3, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(6) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_status` enum('Active','Inactive') NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `user_status`, `create_at`, `update_at`) VALUES
(1, 'a', '$2a$12$8xo6oDfcF8AQPQfqnwccMuHBbach6QMpe3wardo0ofIqxUqaxIVky', 'Active', '2025-12-21 20:46:11', '2025-12-21 20:46:11'),
(2, 'aa', '$2y$10$7cjT5E8f9dL9peFXk2n5PuouBsYcPiIlTBBJYrrHrDoUo0pRgHJkG', 'Active', '2025-12-24 11:31:52', '2025-12-24 11:31:52'),
(3, 'user', '$2y$10$XY6wId6fcHh8yF/xxuBCQ.BOUL2LagjAtQ4UArS8ZjMs44cE51Lzq', 'Active', '2026-01-02 13:38:20', '2026-01-02 13:38:20'),
(4, 'user2', '$2y$10$JAVANh1lIXYSxn9U5QlO7ufsPkLI.xrvV3rdyLpZupzS7/n0QKSq.', 'Active', '2026-01-02 15:11:05', '2026-01-02 15:11:05');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `roles_role_id` int(3) NOT NULL,
  `users_user_id` int(6) NOT NULL,
  `create_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`roles_role_id`, `users_user_id`, `create_at`) VALUES
(1, 1, '2025-11-12 23:13:30'),
(1, 2, '2025-11-22 22:28:30'),
(2, 0, '2025-12-18 09:58:07'),
(2, 2, '2025-12-18 10:11:14'),
(1, 1, '2025-12-21 20:09:35'),
(2, 2, '2025-12-24 11:31:52'),
(4, 0, '2026-01-02 10:55:12'),
(4, 3, '2026-01-02 13:38:20'),
(4, 4, '2026-01-02 15:11:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `subdistrict_id_Addresses` (`subdistricts_subdistrict_id`);

--
-- Indexes for table `bill_details`
--
ALTER TABLE `bill_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `bill_id_bill_details` (`bill_headers_bill_id`),
  ADD KEY `prod_id_bill_details` (`products_prod_id`),
  ADD KEY `stock_id_bill_details` (`prod_stocks_stock_id`);

--
-- Indexes for table `bill_headers`
--
ALTER TABLE `bill_headers`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `cs_id_bill_headers` (`customers_cs_id`),
  ADD KEY `emp_id_bill_headers` (`employees_emp_id`),
  ADD KEY `branch_id_bill_headers` (`branches_branch_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`branch_id`),
  ADD KEY `address_id_branches` (`Addresses_address_id`),
  ADD KEY `shop_id_branches` (`shop_info_shop_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`cs_id`),
  ADD KEY `prefix_id_customers` (`prefixs_prefix_id`),
  ADD KEY `address_id_customers` (`Addresses_address_id`),
  ADD KEY `branches_branch_id` (`branches_branch_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD KEY `branches_branch_id` (`branches_branch_id`);

--
-- Indexes for table `dept_permissions`
--
ALTER TABLE `dept_permissions`
  ADD PRIMARY KEY (`departments_dept_id`,`permissions_permission_id`),
  ADD KEY `fk_dp_perm` (`permissions_permission_id`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`district_id`),
  ADD KEY `province_id_districts` (`provinces_province_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD UNIQUE KEY `users_user_id` (`users_user_id`),
  ADD KEY `prefix_id_employees` (`prefixs_prefix_id`),
  ADD KEY `address_id_employees` (`Addresses_address_id`),
  ADD KEY `religion_id_employees` (`religions_religion_id`),
  ADD KEY `dept_id_employees` (`departments_dept_id`),
  ADD KEY `branch_id_employees` (`branches_branch_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `purchase_id_order_details` (`purchase_orders_purchase_id`),
  ADD KEY `prod_id_order_details` (`products_prod_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`);

--
-- Indexes for table `prefixs`
--
ALTER TABLE `prefixs`
  ADD PRIMARY KEY (`prefix_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`prod_id`),
  ADD UNIQUE KEY `unique_prod_code_per_shop` (`prod_code`,`shop_info_shop_id`),
  ADD KEY `fk_brand_product` (`prod_brands_brand_id`),
  ADD KEY `fk_type_product` (`prod_types_type_id`);

--
-- Indexes for table `prod_brands`
--
ALTER TABLE `prod_brands`
  ADD PRIMARY KEY (`brand_id`);

--
-- Indexes for table `prod_stocks`
--
ALTER TABLE `prod_stocks`
  ADD PRIMARY KEY (`stock_id`),
  ADD KEY `prod_id_prod_stocks` (`products_prod_id`);

--
-- Indexes for table `prod_types`
--
ALTER TABLE `prod_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`province_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `supplier_id_purchase_orders` (`suppliers_supplier_id`),
  ADD KEY `emp_id_purchase_orders` (`employees_emp_id`),
  ADD KEY `branch_id_purchase_orders` (`branches_branch_id`);

--
-- Indexes for table `religions`
--
ALTER TABLE `religions`
  ADD PRIMARY KEY (`religion_id`);

--
-- Indexes for table `repairs`
--
ALTER TABLE `repairs`
  ADD PRIMARY KEY (`repair_id`),
  ADD KEY `cs_id_repairs` (`customers_cs_id`),
  ADD KEY `emp_id_repairs` (`employees_emp_id`),
  ADD KEY `stock_id_repairs` (`prod_stocks_stock_id`),
  ADD KEY `bill_id_repairs` (`bill_headers_bill_id`),
  ADD KEY `fk_repairs_assigned_emp` (`assigned_employee_id`);

--
-- Indexes for table `repair_status_log`
--
ALTER TABLE `repair_status_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_repairlog_repair` (`repairs_repair_id`),
  ADD KEY `fk_repairlog_employee` (`update_by_employee_id`);

--
-- Indexes for table `repair_symptoms`
--
ALTER TABLE `repair_symptoms`
  ADD PRIMARY KEY (`repairs_repair_id`,`symptoms_symptom_id`),
  ADD KEY `symptom_id_repair_symptoms` (`symptoms_symptom_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`roles_role_id`,`permissions_permission_id`),
  ADD KEY `permission_id_role_permissions` (`permissions_permission_id`);

--
-- Indexes for table `shop_info`
--
ALTER TABLE `shop_info`
  ADD PRIMARY KEY (`shop_id`),
  ADD KEY `address_id_shop_info` (`Addresses_address_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `stock_id_stock_movements` (`prod_stocks_stock_id`),
  ADD KEY `outtype_id_stock_movements` (`prodout_types_outtype_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD KEY `branches_branch_id` (`branches_branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `repair_status_log`
--
ALTER TABLE `repair_status_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dept_permissions`
--
ALTER TABLE `dept_permissions`
  ADD CONSTRAINT `fk_dp_dept` FOREIGN KEY (`departments_dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dp_perm` FOREIGN KEY (`permissions_permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
