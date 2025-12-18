-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 18, 2025 at 11:51 AM
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
(1, NULL, NULL, NULL, NULL, NULL, 100101),
(2, NULL, NULL, NULL, NULL, NULL, 100101);

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
(0, NULL, 'สำนักงานใหญ่', '089746513', '2025-12-18 09:58:06', '2025-12-18 09:58:06', 0, 0),
(1, '999', 'สาขา9', '0888888888', '2025-11-12 22:30:57', '2025-11-24 01:30:13', 5, 1),
(2, NULL, 'สำนักงานใหญ่', '089999999999', '2025-12-18 10:11:14', '2025-12-18 10:11:14', 1, 2),
(3, NULL, 'สาขาปทุมธานี', '089999999999', '2025-12-18 13:33:59', '2025-12-18 13:33:59', 2, 3);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `cs_id` int(6) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(4) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
  `dept_name` varchar(50) NOT NULL,
  `dept_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `shop_info_shop_id`, `dept_name`, `dept_desc`, `create_at`, `update_at`) VALUES
(0, 0, 'Partner (เจ้าของร้าน)', NULL, '2025-12-18 09:58:06', '2025-12-18 09:58:06'),
(1, 1, 'ผู้จัดการร้าน', NULL, '2025-12-14 22:50:09', '2025-12-14 22:50:09'),
(2, 1, 'Partner', '', '2025-12-18 09:59:53', '2025-12-18 09:59:53'),
(3, 2, 'เจ้าของร้านค้า', NULL, '2025-12-18 10:11:14', '2025-12-18 10:11:14'),
(4, 2, 'HR', '', '2025-12-18 11:22:45', '2025-12-18 11:22:45'),
(5, 3, 'เจ้าของร้านค้า', NULL, '2025-12-18 13:33:59', '2025-12-18 13:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(4) NOT NULL,
  `district_name_th` varchar(50) NOT NULL,
  `district_name_en` varchar(50) DEFAULT NULL,
  `provinces_province_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `district_name_th`, `district_name_en`, `provinces_province_id`) VALUES
(1001, 'เขตพระนคร', 'Khet Phra Nakhon', 1),
(1002, 'เขตดุสิต', 'Khet Dusit', 1),
(1003, 'เขตหนองจอก', 'Khet Nong Chok', 1),
(1004, 'เขตบางรัก', 'Khet Bang Rak', 1),
(1005, 'เขตบางเขน', 'Khet Bang Khen', 1),
(1006, 'เขตบางกะปิ', 'Khet Bang Kapi', 1),
(1007, 'เขตปทุมวัน', 'Khet Pathum Wan', 1),
(1008, 'เขตป้อมปราบศัตรูพ่าย', 'Khet Pom Prap Sattru Phai', 1),
(1009, 'เขตพระโขนง', 'Khet Phra Khanong', 1),
(1010, 'เขตมีนบุรี', 'Khet Min Buri', 1),
(1011, 'เขตลาดกระบัง', 'Khet Lat Krabang', 1),
(1012, 'เขตยานนาวา', 'Khet Yan Nawa', 1),
(1013, 'เขตสัมพันธวงศ์', 'Khet Samphanthawong', 1),
(1014, 'เขตพญาไท', 'Khet Phaya Thai', 1),
(1015, 'เขตธนบุรี', 'Khet Thon Buri', 1),
(1016, 'เขตบางกอกใหญ่', 'Khet Bangkok Yai', 1),
(1017, 'เขตห้วยขวาง', 'Khet Huai Khwang', 1),
(1018, 'เขตคลองสาน', 'Khet Khlong San', 1),
(1019, 'เขตตลิ่งชัน', 'Khet Taling Chan', 1),
(1020, 'เขตบางกอกน้อย', 'Khet Bangkok Noi', 1),
(1021, 'เขตบางขุนเทียน', 'Khet Bang Khun Thian', 1),
(1022, 'เขตภาษีเจริญ', 'Khet Phasi Charoen', 1),
(1023, 'เขตหนองแขม', 'Khet Nong Khaem', 1),
(1024, 'เขตราษฎร์บูรณะ', 'Khet Rat Burana', 1),
(1025, 'เขตบางพลัด', 'Khet Bang Phlat', 1),
(1026, 'เขตดินแดง', 'Khet Din Daeng', 1),
(1027, 'เขตบึงกุ่ม', 'Khet Bueng Kum', 1),
(1028, 'เขตสาทร', 'Khet Sathon', 1),
(1029, 'เขตบางซื่อ', 'Khet Bang Sue', 1),
(1030, 'เขตจตุจักร', 'Khet Chatuchak', 1),
(1031, 'เขตบางคอแหลม', 'Khet Bang Kho Laem', 1),
(1032, 'เขตประเวศ', 'Khet Prawet', 1),
(1033, 'เขตคลองเตย', 'Khet Khlong Toei', 1),
(1034, 'เขตสวนหลวง', 'Khet Suan Luang', 1),
(1035, 'เขตจอมทอง', 'Khet Chom Thong', 1),
(1036, 'เขตดอนเมือง', 'Khet Don Mueang', 1),
(1037, 'เขตราชเทวี', 'Khet Ratchathewi', 1),
(1038, 'เขตลาดพร้าว', 'Khet Lat Phrao', 1),
(1039, 'เขตวัฒนา', 'Khet Watthana', 1),
(1040, 'เขตบางแค', 'Khet Bang Khae', 1),
(1041, 'เขตหลักสี่', 'Khet Lak Si', 1),
(1042, 'เขตสายไหม', 'Khet Sai Mai', 1),
(1043, 'เขตคันนายาว', 'Khet Khan Na Yao', 1),
(1044, 'เขตสะพานสูง', 'Khet Saphan Sung', 1),
(1045, 'เขตวังทองหลาง', 'Khet Wang Thonglang', 1),
(1046, 'เขตคลองสามวา', 'Khet Khlong Sam Wa', 1),
(1047, 'เขตบางนา', 'Khet Bang Na', 1),
(1048, 'เขตทวีวัฒนา', 'Khet Thawi Watthana', 1),
(1049, 'เขตทุ่งครุ', 'Khet Thung Khru', 1),
(1050, 'เขตบางบอน', 'Khet Bang Bon', 1),
(1101, 'เมืองสมุทรปราการ', 'Mueang Samut Prakan', 2),
(1102, 'บางบ่อ', 'Bang Bo', 2),
(1103, 'บางพลี', 'Bang Phli', 2),
(1104, 'พระประแดง', 'Phra Pradaeng', 2),
(1105, 'พระสมุทรเจดีย์', 'Phra Samut Chedi', 2),
(1106, 'บางเสาธง', 'Bang Sao Thong', 2),
(1201, 'เมืองนนทบุรี', 'Mueang Nonthaburi', 3),
(1202, 'บางกรวย', 'Bang Kruai', 3),
(1203, 'บางใหญ่', 'Bang Yai', 3),
(1204, 'บางบัวทอง', 'Bang Bua Thong', 3),
(1205, 'ไทรน้อย', 'Sai Noi', 3),
(1206, 'ปากเกร็ด', 'Pak Kret', 3),
(1301, 'เมืองปทุมธานี', 'Mueang Pathum Thani', 4),
(1302, 'คลองหลวง', 'Khlong Luang', 4),
(1303, 'ธัญบุรี', 'Thanyaburi', 4),
(1304, 'หนองเสือ', 'Nong Suea', 4),
(1305, 'ลาดหลุมแก้ว', 'Lat Lum Kaeo', 4),
(1306, 'ลำลูกกา', 'Lam Luk Ka', 4),
(1307, 'สามโคก', 'Sam Khok', 4),
(1401, 'พระนครศรีอยุธยา', 'Phra Nakhon Si Ayutthaya', 5),
(1402, 'ท่าเรือ', 'Tha Ruea', 5),
(1403, 'นครหลวง', 'Nakhon Luang', 5),
(1404, 'บางไทร', 'Bang Sai', 5),
(1405, 'บางบาล', 'Bang Ban', 5),
(1406, 'บางปะอิน', 'Bang Pa-in', 5),
(1407, 'บางปะหัน', 'Bang Pahan', 5),
(1408, 'ผักไห่', 'Phak Hai', 5),
(1409, 'ภาชี', 'Phachi', 5),
(1410, 'ลาดบัวหลวง', 'Lat Bua Luang', 5),
(1411, 'วังน้อย', 'Wang Noi', 5),
(1412, 'เสนา', 'Sena', 5),
(1413, 'บางซ้าย', 'Bang Sai', 5),
(1414, 'อุทัย', 'Uthai', 5),
(1415, 'มหาราช', 'Maha Rat', 5),
(1416, 'บ้านแพรก', 'Ban Phraek', 5),
(1501, 'เมืองอ่างทอง', 'Mueang Ang Thong', 6),
(1502, 'ไชโย', 'Chaiyo', 6),
(1503, 'ป่าโมก', 'Pa Mok', 6),
(1504, 'โพธิ์ทอง', 'Pho Thong', 6),
(1505, 'แสวงหา', 'Sawaeng Ha', 6),
(1506, 'วิเศษชัยชาญ', 'Wiset Chai Chan', 6),
(1507, 'สามโก้', 'Samko', 6),
(1601, 'เมืองลพบุรี', 'Mueang Lop Buri', 7),
(1602, 'พัฒนานิคม', 'Phatthana Nikhom', 7),
(1603, 'โคกสำโรง', 'Khok Samrong', 7),
(1604, 'ชัยบาดาล', 'Chai Badan', 7),
(1605, 'ท่าวุ้ง', 'Tha Wung', 7),
(1606, 'บ้านหมี่', 'Ban Mi', 7),
(1607, 'ท่าหลวง', 'Tha Luang', 7),
(1608, 'สระโบสถ์', 'Sa Bot', 7),
(1609, 'โคกเจริญ', 'Khok Charoen', 7),
(1610, 'ลำสนธิ', 'Lam Sonthi', 7),
(1611, 'หนองม่วง', 'Nong Muang', 7),
(1701, 'เมืองสิงห์บุรี', 'Mueang Sing Buri', 8),
(1702, 'บางระจัน', 'Bang Rachan', 8),
(1703, 'ค่ายบางระจัน', 'Khai Bang Rachan', 8),
(1704, 'พรหมบุรี', 'Phrom Buri', 8),
(1705, 'ท่าช้าง', 'Tha Chang', 8),
(1706, 'อินทร์บุรี', 'In Buri', 8),
(1801, 'เมืองชัยนาท', 'Mueang Chai Nat', 9),
(1802, 'มโนรมย์', 'Manorom', 9),
(1803, 'วัดสิงห์', 'Wat Sing', 9),
(1804, 'สรรพยา', 'Sapphaya', 9),
(1805, 'สรรคบุรี', 'Sankhaburi', 9),
(1806, 'หันคา', 'Hankha', 9),
(1807, 'หนองมะโมง', 'Nong Mamong', 9),
(1808, 'เนินขาม', 'Noen Kham', 9),
(1901, 'เมืองสระบุรี', 'Mueang Saraburi', 10),
(1902, 'แก่งคอย', 'Kaeng Khoi', 10),
(1903, 'หนองแค', 'Nong Khae', 10),
(1904, 'วิหารแดง', 'Wihan Daeng', 10),
(1905, 'หนองแซง', 'Nong Saeng', 10),
(1906, 'บ้านหมอ', 'Ban Mo', 10),
(1907, 'ดอนพุด', 'Don Phut', 10),
(1908, 'หนองโดน', 'Nong Don', 10),
(1909, 'พระพุทธบาท', 'Phra Phutthabat', 10),
(1910, 'เสาไห้', 'Sao Hai', 10),
(1911, 'มวกเหล็ก', 'Muak Lek', 10),
(1912, 'วังม่วง', 'Wang Muang', 10),
(1913, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 10),
(2001, 'เมืองชลบุรี', 'Mueang Chon Buri', 11),
(2002, 'บ้านบึง', 'Ban Bueng', 11),
(2003, 'หนองใหญ่', 'Nong Yai', 11),
(2004, 'บางละมุง', 'Bang Lamung', 11),
(2005, 'พานทอง', 'Phan Thong', 11),
(2006, 'พนัสนิคม', 'Phanat Nikhom', 11),
(2007, 'ศรีราชา', 'Si Racha', 11),
(2008, 'เกาะสีชัง', 'Ko Sichang', 11),
(2009, 'สัตหีบ', 'Sattahip', 11),
(2010, 'บ่อทอง', 'Bo Thong', 11),
(2011, 'เกาะจันทร์', 'Ko Chan', 11),
(2101, 'เมืองระยอง', 'Mueang Rayong', 12),
(2102, 'บ้านฉาง', 'Ban Chang', 12),
(2103, 'แกลง', 'Klaeng', 12),
(2104, 'วังจันทร์', 'Wang Chan', 12),
(2105, 'บ้านค่าย', 'Ban Khai', 12),
(2106, 'ปลวกแดง', 'Pluak Daeng', 12),
(2107, 'เขาชะเมา', 'Khao Chamao', 12),
(2108, 'นิคมพัฒนา', 'Nikhom Phatthana', 12),
(2201, 'เมืองจันทบุรี', 'Mueang Chanthaburi', 13),
(2202, 'ขลุง', 'Khlung', 13),
(2203, 'ท่าใหม่', 'Tha Mai', 13),
(2204, 'โป่งน้ำร้อน', 'Pong Nam Ron', 13),
(2205, 'มะขาม', 'Makham', 13),
(2206, 'แหลมสิงห์', 'Laem Sing', 13),
(2207, 'สอยดาว', 'Soi Dao', 13),
(2208, 'แก่งหางแมว', 'Kaeng Hang Maeo', 13),
(2209, 'นายายอาม', 'Na Yai Am', 13),
(2210, 'เขาคิชฌกูฏ', 'Khoa Khitchakut', 13),
(2301, 'เมืองตราด', 'Mueang Trat', 14),
(2302, 'คลองใหญ่', 'Khlong Yai', 14),
(2303, 'เขาสมิง', 'Khao Saming', 14),
(2304, 'บ่อไร่', 'Bo Rai', 14),
(2305, 'แหลมงอบ', 'Laem Ngop', 14),
(2306, 'เกาะกูด', 'Ko Kut', 14),
(2307, 'เกาะช้าง', 'Ko Chang', 14),
(2401, 'เมืองฉะเชิงเทรา', 'Mueang Chachoengsao', 15),
(2402, 'บางคล้า', 'Bang Khla', 15),
(2403, 'บางน้ำเปรี้ยว', 'Bang Nam Priao', 15),
(2404, 'บางปะกง', 'Bang Pakong', 15),
(2405, 'บ้านโพธิ์', 'Ban Pho', 15),
(2406, 'พนมสารคาม', 'Phanom Sarakham', 15),
(2407, 'ราชสาส์น', 'Ratchasan', 15),
(2408, 'สนามชัยเขต', 'Sanam Chai Khet', 15),
(2409, 'แปลงยาว', 'Plaeng Yao', 15),
(2410, 'ท่าตะเกียบ', 'Tha Takiap', 15),
(2411, 'คลองเขื่อน', 'Khlong Khuean', 15),
(2501, 'เมืองปราจีนบุรี', 'Mueang Prachin Buri', 16),
(2502, 'กบินทร์บุรี', 'Kabin Buri', 16),
(2503, 'นาดี', 'Na Di', 16),
(2506, 'บ้านสร้าง', 'Ban Sang', 16),
(2507, 'ประจันตคาม', 'Prachantakham', 16),
(2508, 'ศรีมหาโพธิ', 'Si Maha Phot', 16),
(2509, 'ศรีมโหสถ', 'Si Mahosot', 16),
(2601, 'เมืองนครนายก', 'Mueang Nakhon Nayok', 17),
(2602, 'ปากพลี', 'Pak Phli', 17),
(2603, 'บ้านนา', 'Ban Na', 17),
(2604, 'องครักษ์', 'Ongkharak', 17),
(2701, 'เมืองสระแก้ว', 'Mueang Sa Kaeo', 18),
(2702, 'คลองหาด', 'Khlong Hat', 18),
(2703, 'ตาพระยา', 'Ta Phraya', 18),
(2704, 'วังน้ำเย็น', 'Wang Nam Yen', 18),
(2705, 'วัฒนานคร', 'Watthana Nakhon', 18),
(2706, 'อรัญประเทศ', 'Aranyaprathet', 18),
(2707, 'เขาฉกรรจ์', 'Khao Chakan', 18),
(2708, 'โคกสูง', 'Khok Sung', 18),
(2709, 'วังสมบูรณ์', 'Wang Sombun', 18),
(3001, 'เมืองนครราชสีมา', 'Mueang Nakhon Ratchasima', 19),
(3002, 'ครบุรี', 'Khon Buri', 19),
(3003, 'เสิงสาง', 'Soeng Sang', 19),
(3004, 'คง', 'Khong', 19),
(3005, 'บ้านเหลื่อม', 'Ban Lueam', 19),
(3006, 'จักราช', 'Chakkarat', 19),
(3007, 'โชคชัย', 'Chok Chai', 19),
(3008, 'ด่านขุนทด', 'Dan Khun Thot', 19),
(3009, 'โนนไทย', 'Non Thai', 19),
(3010, 'โนนสูง', 'Non Sung', 19),
(3011, 'ขามสะแกแสง', 'Kham Sakaesaeng', 19),
(3012, 'บัวใหญ่', 'Bua Yai', 19),
(3013, 'ประทาย', 'Prathai', 19),
(3014, 'ปักธงชัย', 'Pak Thong Chai', 19),
(3015, 'พิมาย', 'Phimai', 19),
(3016, 'ห้วยแถลง', 'Huai Thalaeng', 19),
(3017, 'ชุมพวง', 'Chum Phuang', 19),
(3018, 'สูงเนิน', 'Sung Noen', 19),
(3019, 'ขามทะเลสอ', 'Kham Thale So', 19),
(3020, 'สีคิ้ว', 'Sikhio', 19),
(3021, 'ปากช่อง', 'Pak Chong', 19),
(3022, 'หนองบุญมาก', 'Nong Bunnak', 19),
(3023, 'แก้งสนามนาง', 'Kaeng Sanam Nang', 19),
(3024, 'โนนแดง', 'Non Daeng', 19),
(3025, 'วังน้ำเขียว', 'Wang Nam Khiao', 19),
(3026, 'เทพารักษ์', 'Thepharak', 19),
(3027, 'เมืองยาง', 'Mueang Yang', 19),
(3028, 'พระทองคำ', 'Phra Thong Kham', 19),
(3029, 'ลำทะเมนชัย', 'Lam Thamenchai', 19),
(3030, 'บัวลาย', 'Bua Lai', 19),
(3031, 'สีดา', 'Sida', 19),
(3032, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 19),
(3101, 'เมืองบุรีรัมย์', 'Mueang Buri Ram', 20),
(3102, 'คูเมือง', 'Khu Mueang', 20),
(3103, 'กระสัง', 'Krasang', 20),
(3104, 'นางรอง', 'Nang Rong', 20),
(3105, 'หนองกี่', 'Nong Ki', 20),
(3106, 'ละหานทราย', 'Lahan Sai', 20),
(3107, 'ประโคนชัย', 'Prakhon Chai', 20),
(3108, 'บ้านกรวด', 'Ban Kruat', 20),
(3109, 'พุทไธสง', 'Phutthaisong', 20),
(3110, 'ลำปลายมาศ', 'Lam Plai Mat', 20),
(3111, 'สตึก', 'Satuek', 20),
(3112, 'ปะคำ', 'Pakham', 20),
(3113, 'นาโพธิ์', 'Na Pho', 20),
(3114, 'หนองหงส์', 'Nong Hong', 20),
(3115, 'พลับพลาชัย', 'Phlapphla Chai', 20),
(3116, 'ห้วยราช', 'Huai Rat', 20),
(3117, 'โนนสุวรรณ', 'Non Suwan', 20),
(3118, 'ชำนิ', 'Chamni', 20),
(3119, 'บ้านใหม่ไชยพจน์', 'Ban Mai Chaiyaphot', 20),
(3120, 'โนนดินแดง', 'Din Daeng', 20),
(3121, 'บ้านด่าน', 'Ban Dan', 20),
(3122, 'แคนดง', 'Khaen Dong', 20),
(3123, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 20),
(3201, 'เมืองสุรินทร์', 'Mueang Surin', 21),
(3202, 'ชุมพลบุรี', 'Chumphon Buri', 21),
(3203, 'ท่าตูม', 'Tha Tum', 21),
(3204, 'จอมพระ', 'Chom Phra', 21),
(3205, 'ปราสาท', 'Prasat', 21),
(3206, 'กาบเชิง', 'Kap Choeng', 21),
(3207, 'รัตนบุรี', 'Rattanaburi', 21),
(3208, 'สนม', 'Sanom', 21),
(3209, 'ศีขรภูมิ', 'Sikhoraphum', 21),
(3210, 'สังขะ', 'Sangkha', 21),
(3211, 'ลำดวน', 'Lamduan', 21),
(3212, 'สำโรงทาบ', 'Samrong Thap', 21),
(3213, 'บัวเชด', 'Buachet', 21),
(3214, 'พนมดงรัก', 'Phanom Dong Rak', 21),
(3215, 'ศรีณรงค์', 'Si Narong', 21),
(3216, 'เขวาสินรินทร์', 'Khwao Sinarin', 21),
(3217, 'โนนนารายณ์', 'Non Narai', 21),
(3301, 'เมืองศรีสะเกษ', 'Mueang Si Sa Ket', 22),
(3302, 'ยางชุมน้อย', 'Yang Chum Noi', 22),
(3303, 'กันทรารมย์', 'Kanthararom', 22),
(3304, 'กันทรลักษ์', 'Kantharalak', 22),
(3305, 'ขุขันธ์', 'Khukhan', 22),
(3306, 'ไพรบึง', 'Phrai Bueng', 22),
(3307, 'ปรางค์กู่', 'Prang Ku', 22),
(3308, 'ขุนหาญ', 'Khun Han', 22),
(3309, 'ราษีไศล', 'Rasi Salai', 22),
(3310, 'อุทุมพรพิสัย', 'Uthumphon Phisai', 22),
(3311, 'บึงบูรพ์', 'Bueng Bun', 22),
(3312, 'ห้วยทับทัน', 'Huai Thap Than', 22),
(3313, 'โนนคูณ', 'Non Khun', 22),
(3314, 'ศรีรัตนะ', 'Si Rattana', 22),
(3315, 'น้ำเกลี้ยง', 'Nam Kliang', 22),
(3316, 'วังหิน', 'Wang Hin', 22),
(3317, 'ภูสิงห์', 'Phu Sing', 22),
(3318, 'เมืองจันทร์', 'Mueang Chan', 22),
(3319, 'เบญจลักษ์', 'Benchalak', 22),
(3320, 'พยุห์', 'Phayu', 22),
(3321, 'โพธิ์ศรีสุวรรณ', 'Pho Si Suwan', 22),
(3322, 'ศิลาลาด', 'Sila Lat', 22),
(3401, 'เมืองอุบลราชธานี', 'Mueang Ubon Ratchathani', 23),
(3402, 'ศรีเมืองใหม่', 'Si Mueang Mai', 23),
(3403, 'โขงเจียม', 'Khong Chiam', 23),
(3404, 'เขื่องใน', 'Khueang Nai', 23),
(3405, 'เขมราฐ', 'Khemarat', 23),
(3407, 'เดชอุดม', 'Det Udom', 23),
(3408, 'นาจะหลวย', 'Na Chaluai', 23),
(3409, 'น้ำยืน', 'Nam Yuen', 23),
(3410, 'บุณฑริก', 'Buntharik', 23),
(3411, 'ตระการพืชผล', 'Trakan Phuet Phon', 23),
(3412, 'กุดข้าวปุ้น', 'Kut Khaopun', 23),
(3414, 'ม่วงสามสิบ', 'Muang Sam Sip', 23),
(3415, 'วารินชำราบ', 'Warin Chamrap', 23),
(3419, 'พิบูลมังสาหาร', 'Phibun Mangsahan', 23),
(3420, 'ตาลสุม', 'Tan Sum', 23),
(3421, 'โพธิ์ไทร', 'Pho Sai', 23),
(3422, 'สำโรง', 'Samrong', 23),
(3424, 'ดอนมดแดง', 'Don Mot Daeng', 23),
(3425, 'สิรินธร', 'Sirindhorn', 23),
(3426, 'ทุ่งศรีอุดม', 'Thung Si Udom', 23),
(3429, 'นาเยีย', 'Na Yia', 23),
(3430, 'นาตาล', 'Na Tan', 23),
(3431, 'เหล่าเสือโก้ก', 'Lao Suea Kok', 23),
(3432, 'สว่างวีระวงศ์', 'Sawang Wirawong', 23),
(3433, 'น้ำขุ่น', 'Nam Khun', 23),
(3501, 'เมืองยโสธร', 'Mueang Yasothon', 24),
(3502, 'ทรายมูล', 'Sai Mun', 24),
(3503, 'กุดชุม', 'Kut Chum', 24),
(3504, 'คำเขื่อนแก้ว', 'Kham Khuean Kaeo', 24),
(3505, 'ป่าติ้ว', 'Pa Tio', 24),
(3506, 'มหาชนะชัย', 'Maha Chana Chai', 24),
(3507, 'ค้อวัง', 'Kho Wang', 24),
(3508, 'เลิงนกทา', 'Loeng Nok Tha', 24),
(3509, 'ไทยเจริญ', 'Thai Charoen', 24),
(3601, 'เมืองชัยภูมิ', 'Mueang Chaiyaphum', 25),
(3602, 'บ้านเขว้า', 'Ban Khwao', 25),
(3603, 'คอนสวรรค์', 'Khon Sawan', 25),
(3604, 'เกษตรสมบูรณ์', 'Kaset Sombun', 25),
(3605, 'หนองบัวแดง', 'Nong Bua Daeng', 25),
(3606, 'จัตุรัส', 'Chatturat', 25),
(3607, 'บำเหน็จณรงค์', 'Bamnet Narong', 25),
(3608, 'หนองบัวระเหว', 'Nong Bua Rawe', 25),
(3609, 'เทพสถิต', 'Thep Sathit', 25),
(3610, 'ภูเขียว', 'Phu Khiao', 25),
(3611, 'บ้านแท่น', 'Ban Thaen', 25),
(3612, 'แก้งคร้อ', 'Kaeng Khro', 25),
(3613, 'คอนสาร', 'Khon San', 25),
(3614, 'ภักดีชุมพล', 'Phakdi Chumphon', 25),
(3615, 'เนินสง่า', 'Noen Sa-nga', 25),
(3616, 'ซับใหญ่', 'Sap Yai', 25),
(3701, 'เมืองอำนาจเจริญ', 'Mueang Amnat Charoen', 26),
(3702, 'ชานุมาน', 'Chanuman', 26),
(3703, 'ปทุมราชวงศา', 'Pathum Ratchawongsa', 26),
(3704, 'พนา', 'Phana', 26),
(3705, 'เสนางคนิคม', 'Senangkhanikhom', 26),
(3706, 'หัวตะพาน', 'Hua Taphan', 26),
(3707, 'ลืออำนาจ', 'Lue Amnat', 26),
(3801, 'เมืองบึงกาฬ', 'Mueang Bueng Kan', 77),
(3802, 'เซกา', 'Seka', 77),
(3803, 'โซ่พิสัย', 'So Phisai', 77),
(3804, 'พรเจริญ', 'Phon Charoen', 77),
(3805, 'ศรีวิไล', 'Si Wilai', 77),
(3806, 'บึงโขงหลง', 'Bueng Khong Long', 77),
(3807, 'ปากคาด', 'Pak Khat', 77),
(3808, 'บุ่งคล้า', 'Bung Khla', 77),
(3901, 'เมืองหนองบัวลำภู', 'Mueang Nong Bua Lam Phu', 27),
(3902, 'นากลาง', 'Na Klang', 27),
(3903, 'โนนสัง', 'Non Sang', 27),
(3904, 'ศรีบุญเรือง', 'Si Bun Rueang', 27),
(3905, 'สุวรรณคูหา', 'Suwannakhuha', 27),
(3906, 'นาวัง', 'Na Wang', 27),
(4001, 'เมืองขอนแก่น', 'Mueang Khon Kaen', 28),
(4002, 'บ้านฝาง', 'Ban Fang', 28),
(4003, 'พระยืน', 'Phra Yuen', 28),
(4004, 'หนองเรือ', 'Nong Ruea', 28),
(4005, 'ชุมแพ', 'Chum Phae', 28),
(4006, 'สีชมพู', 'Si Chomphu', 28),
(4007, 'น้ำพอง', 'Nam Phong', 28),
(4008, 'อุบลรัตน์', 'Ubolratana', 28),
(4009, 'กระนวน', 'Kranuan', 28),
(4010, 'บ้านไผ่', 'Ban Phai', 28),
(4011, 'เปือยน้อย', 'Pueai Noi', 28),
(4012, 'พล', 'Phon', 28),
(4013, 'แวงใหญ่', 'Waeng Yai', 28),
(4014, 'แวงน้อย', 'Waeng Noi', 28),
(4015, 'หนองสองห้อง', 'Nong Song Hong', 28),
(4016, 'ภูเวียง', 'Phu Wiang', 28),
(4017, 'มัญจาคีรี', 'Mancha Khiri', 28),
(4018, 'ชนบท', 'Chonnabot', 28),
(4019, 'เขาสวนกวาง', 'Khao Suan Kwang', 28),
(4020, 'ภูผาม่าน', 'Phu Pha Man', 28),
(4021, 'ซำสูง', 'Sam Sung', 28),
(4022, 'โคกโพธิ์ไชย', 'Khok Pho Chai', 28),
(4023, 'หนองนาคำ', 'Nong Na Kham', 28),
(4024, 'บ้านแฮด', 'Ban Haet', 28),
(4025, 'โนนศิลา', 'Non Sila', 28),
(4029, 'เวียงเก่า', 'Wiang Kao', 28),
(4101, 'เมืองอุดรธานี', 'Mueang Udon Thani', 29),
(4102, 'กุดจับ', 'Kut Chap', 29),
(4103, 'หนองวัวซอ', 'Nong Wua So', 29),
(4104, 'กุมภวาปี', 'Kumphawapi', 29),
(4105, 'โนนสะอาด', 'Non Sa-at', 29),
(4106, 'หนองหาน', 'Nong Han', 29),
(4107, 'ทุ่งฝน', 'Thung Fon', 29),
(4108, 'ไชยวาน', 'Chai Wan', 29),
(4109, 'ศรีธาตุ', 'Si That', 29),
(4110, 'วังสามหมอ', 'Wang Sam Mo', 29),
(4111, 'บ้านดุง', 'Ban Dung', 29),
(4117, 'บ้านผือ', 'Ban Phue', 29),
(4118, 'น้ำโสม', 'Nam Som', 29),
(4119, 'เพ็ญ', 'Phen', 29),
(4120, 'สร้างคอม', 'Sang Khom', 29),
(4121, 'หนองแสง', 'Nong Saeng', 29),
(4122, 'นายูง', 'Na Yung', 29),
(4123, 'พิบูลย์รักษ์', 'Phibun Rak', 29),
(4124, 'กู่แก้ว', 'Ku Kaeo', 29),
(4125, 'ประจักษ์ศิลปาคม', 'rachak-sinlapakhom', 29),
(4201, 'เมืองเลย', 'Mueang Loei', 30),
(4202, 'นาด้วง', 'Na Duang', 30),
(4203, 'เชียงคาน', 'Chiang Khan', 30),
(4204, 'ปากชม', 'Pak Chom', 30),
(4205, 'ด่านซ้าย', 'Dan Sai', 30),
(4206, 'นาแห้ว', 'Na Haeo', 30),
(4207, 'ภูเรือ', 'Phu Ruea', 30),
(4208, 'ท่าลี่', 'Tha Li', 30),
(4209, 'วังสะพุง', 'Wang Saphung', 30),
(4210, 'ภูกระดึง', 'Phu Kradueng', 30),
(4211, 'ภูหลวง', 'Phu Luang', 30),
(4212, 'ผาขาว', 'Pha Khao', 30),
(4213, 'เอราวัณ', 'Erawan', 30),
(4214, 'หนองหิน', 'Nong Hin', 30),
(4301, 'เมืองหนองคาย', 'Mueang Nong Khai', 31),
(4302, 'ท่าบ่อ', 'Tha Bo', 31),
(4305, 'โพนพิสัย', 'Phon Phisai', 31),
(4307, 'ศรีเชียงใหม่', 'Si Chiang Mai', 31),
(4308, 'สังคม', 'Sangkhom', 31),
(4314, 'สระใคร', 'Sakhrai', 31),
(4315, 'เฝ้าไร่', 'Fao Rai', 31),
(4316, 'รัตนวาปี', 'Rattanawapi', 31),
(4317, 'โพธิ์ตาก', 'Pho Tak', 31),
(4401, 'เมืองมหาสารคาม', 'Mueang Maha Sarakham', 32),
(4402, 'แกดำ', 'Kae Dam', 32),
(4403, 'โกสุมพิสัย', 'Kosum Phisai', 32),
(4404, 'กันทรวิชัย', 'Kantharawichai', 32),
(4405, 'เชียงยืน', 'Kantharawichai', 32),
(4406, 'บรบือ', 'Borabue', 32),
(4407, 'นาเชือก', 'Na Chueak', 32),
(4408, 'พยัคฆภูมิพิสัย', 'Phayakkhaphum Phisai', 32),
(4409, 'วาปีปทุม', 'Wapi Pathum', 32),
(4410, 'นาดูน', 'Na Dun', 32),
(4411, 'ยางสีสุราช', 'Yang Sisurat', 32),
(4412, 'กุดรัง', 'Kut Rang', 32),
(4413, 'ชื่นชม', 'Chuen Chom', 32),
(4501, 'เมืองร้อยเอ็ด', 'Mueang Roi Et', 33),
(4502, 'เกษตรวิสัย', 'Kaset Wisai', 33),
(4503, 'ปทุมรัตต์', 'Pathum Rat', 33),
(4504, 'จตุรพักตรพิมาน', 'Chaturaphak Phiman', 33),
(4505, 'ธวัชบุรี', 'Thawat Buri', 33),
(4506, 'พนมไพร', 'Phanom Phrai', 33),
(4507, 'โพนทอง', 'Phon Thong', 33),
(4508, 'โพธิ์ชัย', 'Pho Chai', 33),
(4509, 'หนองพอก', 'Nong Phok', 33),
(4510, 'เสลภูมิ', 'Selaphum', 33),
(4511, 'สุวรรณภูมิ', 'Suwannaphum', 33),
(4512, 'เมืองสรวง', 'Mueang Suang', 33),
(4513, 'โพนทราย', 'Phon Sai', 33),
(4514, 'อาจสามารถ', 'At Samat', 33),
(4515, 'เมยวดี', 'Moei Wadi', 33),
(4516, 'ศรีสมเด็จ', 'Si Somdet', 33),
(4517, 'จังหาร', 'Changhan', 33),
(4518, 'เชียงขวัญ', 'Chiang Khwan', 33),
(4519, 'หนองฮี', 'Nong Hi', 33),
(4520, 'ทุ่งเขาหลวง', 'Thung Khao Luang', 33),
(4601, 'เมืองกาฬสินธุ์', 'Mueang Kalasin', 34),
(4602, 'นามน', 'Na Mon', 34),
(4603, 'กมลาไสย', 'Kamalasai', 34),
(4604, 'ร่องคำ', 'Rong Kham', 34),
(4605, 'กุฉินารายณ์', 'Kuchinarai', 34),
(4606, 'เขาวง', 'Khao Wong', 34),
(4607, 'ยางตลาด', 'Yang Talat', 34),
(4608, 'ห้วยเม็ก', 'Huai Mek', 34),
(4609, 'สหัสขันธ์', 'Sahatsakhan', 34),
(4610, 'คำม่วง', 'Kham Muang', 34),
(4611, 'ท่าคันโท', 'Tha Khantho', 34),
(4612, 'หนองกุงศรี', 'Nong Kung Si', 34),
(4613, 'สมเด็จ', 'Somdet', 34),
(4614, 'ห้วยผึ้ง', 'Huai Phueng', 34),
(4615, 'สามชัย', 'Sam Chai', 34),
(4616, 'นาคู', 'Na Khu', 34),
(4617, 'ดอนจาน', 'Don Chan', 34),
(4618, 'ฆ้องชัย', 'Khong Chai', 34),
(4701, 'เมืองสกลนคร', 'Mueang Sakon Nakhon', 35),
(4702, 'กุสุมาลย์', 'Kusuman', 35),
(4703, 'กุดบาก', 'Kut Bak', 35),
(4704, 'พรรณานิคม', 'Phanna Nikhom', 35),
(4705, 'พังโคน', 'Phang Khon', 35),
(4706, 'วาริชภูมิ', 'Waritchaphum', 35),
(4707, 'นิคมน้ำอูน', 'Nikhom Nam Un', 35),
(4708, 'วานรนิวาส', 'Wanon Niwat', 35),
(4709, 'คำตากล้า', 'Kham Ta Kla', 35),
(4710, 'บ้านม่วง', 'Ban Muang', 35),
(4711, 'อากาศอำนวย', 'Akat Amnuai', 35),
(4712, 'สว่างแดนดิน', 'Sawang Daen Din', 35),
(4713, 'ส่องดาว', 'Song Dao', 35),
(4714, 'เต่างอย', 'Tao Ngoi', 35),
(4715, 'โคกศรีสุพรรณ', 'Khok Si Suphan', 35),
(4716, 'เจริญศิลป์', 'Charoen Sin', 35),
(4717, 'โพนนาแก้ว', 'Phon Na Kaeo', 35),
(4718, 'ภูพาน', 'Phu Phan', 35),
(4801, 'เมืองนครพนม', 'Mueang Nakhon Phanom', 36),
(4802, 'ปลาปาก', 'Pla Pak', 36),
(4803, 'ท่าอุเทน', 'Tha Uthen', 36),
(4804, 'บ้านแพง', 'Ban Phaeng', 36),
(4805, 'ธาตุพนม', 'That Phanom', 36),
(4806, 'เรณูนคร', 'Renu Nakhon', 36),
(4807, 'นาแก', 'Na Kae', 36),
(4808, 'ศรีสงคราม', 'Si Songkhram', 36),
(4809, 'นาหว้า', 'Na Wa', 36),
(4810, 'โพนสวรรค์', 'Phon Sawan', 36),
(4811, 'นาทม', 'Na Thom', 36),
(4812, 'วังยาง', 'Wang Yang', 36),
(4901, 'เมืองมุกดาหาร', 'Mueang Mukdahan', 37),
(4902, 'นิคมคำสร้อย', 'Nikhom Kham Soi', 37),
(4903, 'ดอนตาล', 'Don Tan', 37),
(4904, 'ดงหลวง', 'Dong Luang', 37),
(4905, 'คำชะอี', 'Khamcha-i', 37),
(4906, 'หว้านใหญ่', 'Wan Yai', 37),
(4907, 'หนองสูง', 'Nong Sung', 37),
(5001, 'เมืองเชียงใหม่', 'Mueang Chiang Mai', 38),
(5002, 'จอมทอง', 'Chom Thong', 38),
(5003, 'แม่แจ่ม', 'Mae Chaem', 38),
(5004, 'เชียงดาว', 'Chiang Dao', 38),
(5005, 'ดอยสะเก็ด', 'Doi Saket', 38),
(5006, 'แม่แตง', 'Mae Taeng', 38),
(5007, 'แม่ริม', 'Mae Rim', 38),
(5008, 'สะเมิง', 'Samoeng', 38),
(5009, 'ฝาง', 'Fang', 38),
(5010, 'แม่อาย', 'Mae Ai', 38),
(5011, 'พร้าว', 'Phrao', 38),
(5012, 'สันป่าตอง', 'San Pa Tong', 38),
(5013, 'สันกำแพง', 'San Kamphaeng', 38),
(5014, 'สันทราย', 'San Sai', 38),
(5015, 'หางดง', 'Hang Dong', 38),
(5016, 'ฮอด', 'Hot', 38),
(5017, 'ดอยเต่า', 'Doi Tao', 38),
(5018, 'อมก๋อย', 'Omkoi', 38),
(5019, 'สารภี', 'Saraphi', 38),
(5020, 'เวียงแหง', 'Wiang Haeng', 38),
(5021, 'ไชยปราการ', 'Chai Prakan', 38),
(5022, 'แม่วาง', 'Mae Wang', 38),
(5023, 'แม่ออน', 'Mae On', 38),
(5024, 'ดอยหล่อ', 'Doi Lo', 38),
(5025, 'กัลยาณิวัฒนา', 'Galyani Vadhana', 38),
(5101, 'เมืองลำพูน', 'Mueang Lamphun', 39),
(5102, 'แม่ทา', 'Mae Tha', 39),
(5103, 'บ้านโฮ่ง', 'Ban Hong', 39),
(5104, 'ลี้', 'Li', 39),
(5105, 'ทุ่งหัวช้าง', 'Thung Hua Chang', 39),
(5106, 'ป่าซาง', 'Pa Sang', 39),
(5107, 'บ้านธิ', 'Ban Thi', 39),
(5108, 'เวียงหนองล่อง', 'Wiang Nong Long', 39),
(5201, 'เมืองลำปาง', 'Mueang Lampang', 40),
(5202, 'แม่เมาะ', 'Mae Mo', 40),
(5203, 'เกาะคา', 'Ko Kha', 40),
(5204, 'เสริมงาม', 'Soem Ngam', 40),
(5205, 'งาว', 'Ngao', 40),
(5206, 'แจ้ห่ม', 'Chae Hom', 40),
(5207, 'วังเหนือ', 'Wang Nuea', 40),
(5208, 'เถิน', 'Thoen', 40),
(5209, 'แม่พริก', 'Mae Phrik', 40),
(5210, 'แม่ทะ', 'Mae Tha', 40),
(5211, 'สบปราบ', 'Sop Prap', 40),
(5212, 'ห้างฉัตร', 'Hang Chat', 40),
(5213, 'เมืองปาน', 'Mueang Pan', 40),
(5301, 'เมืองอุตรดิตถ์', 'Mueang Uttaradit', 41),
(5302, 'ตรอน', 'Tron', 41),
(5303, 'ท่าปลา', 'Tha Pla', 41),
(5304, 'น้ำปาด', 'Nam Pat', 41),
(5305, 'ฟากท่า', 'Fak Tha', 41),
(5306, 'บ้านโคก', 'Ban Khok', 41),
(5307, 'พิชัย', 'Phichai', 41),
(5308, 'ลับแล', 'Laplae', 41),
(5309, 'ทองแสนขัน', 'Thong Saen Khan', 41),
(5401, 'เมืองแพร่', 'Mueang Phrae', 42),
(5402, 'ร้องกวาง', 'Rong Kwang', 42),
(5403, 'ลอง', 'Long', 42),
(5404, 'สูงเม่น', 'Sung Men', 42),
(5405, 'เด่นชัย', 'Den Chai', 42),
(5406, 'สอง', 'Song', 42),
(5407, 'วังชิ้น', 'Wang Chin', 42),
(5408, 'หนองม่วงไข่', 'Nong Muang Khai', 42),
(5501, 'เมืองน่าน', 'Mueang Nan', 43),
(5502, 'แม่จริม', 'Mae Charim', 43),
(5503, 'บ้านหลวง', 'Ban Luang', 43),
(5504, 'นาน้อย', 'Na Noi', 43),
(5505, 'ปัว', 'Pua', 43),
(5506, 'ท่าวังผา', 'Tha Wang Pha', 43),
(5507, 'เวียงสา', 'Wiang Sa', 43),
(5508, 'ทุ่งช้าง', 'Thung Chang', 43),
(5509, 'เชียงกลาง', 'Chiang Klang', 43),
(5510, 'นาหมื่น', 'Na Muen', 43),
(5511, 'สันติสุข', 'Santi Suk', 43),
(5512, 'บ่อเกลือ', 'Bo Kluea', 43),
(5513, 'สองแคว', 'Song Khwae', 43),
(5514, 'ภูเพียง', 'Phu Phiang', 43),
(5515, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 43),
(5601, 'เมืองพะเยา', 'Mueang Phayao', 44),
(5602, 'จุน', 'Chun', 44),
(5603, 'เชียงคำ', 'Chiang Kham', 44),
(5604, 'เชียงม่วน', 'Chiang Muan', 44),
(5605, 'ดอกคำใต้', 'Dok Khamtai', 44),
(5606, 'ปง', 'Pong', 44),
(5607, 'แม่ใจ', 'Mae Chai', 44),
(5608, 'ภูซาง', 'Phu Sang', 44),
(5609, 'ภูกามยาว', 'Phu Kamyao', 44),
(5701, 'เมืองเชียงราย', 'Mueang Chiang Rai', 45),
(5702, 'เวียงชัย', 'Wiang Chai', 45),
(5703, 'เชียงของ', 'Chiang Khong', 45),
(5704, 'เทิง', 'Thoeng', 45),
(5705, 'พาน', 'Phan', 45),
(5706, 'ป่าแดด', 'Pa Daet', 45),
(5707, 'แม่จัน', 'Mae Chan', 45),
(5708, 'เชียงแสน', 'Chiang Saen', 45),
(5709, 'แม่สาย', 'Mae Sai', 45),
(5710, 'แม่สรวย', 'Mae Suai', 45),
(5711, 'เวียงป่าเป้า', 'Wiang Pa Pao', 45),
(5712, 'พญาเม็งราย', 'Phaya Mengrai', 45),
(5713, 'เวียงแก่น', 'Wiang Kaen', 45),
(5714, 'ขุนตาล', 'Khun Tan', 45),
(5715, 'แม่ฟ้าหลวง', 'Mae Fa Luang', 45),
(5716, 'แม่ลาว', 'Mae Lao', 45),
(5717, 'เวียงเชียงรุ้ง', 'Wiang Chiang Rung', 45),
(5718, 'ดอยหลวง', 'Doi Luang', 45),
(5801, 'เมืองแม่ฮ่องสอน', 'Mueang Mae Hong Son', 46),
(5802, 'ขุนยวม', 'Khun Yuam', 46),
(5803, 'ปาย', 'Pai', 46),
(5804, 'แม่สะเรียง', 'Mae Sariang', 46),
(5805, 'แม่ลาน้อย', 'Mae La Noi', 46),
(5806, 'สบเมย', 'Sop Moei', 46),
(5807, 'ปางมะผ้า', 'Pang Mapha', 46),
(6001, 'เมืองนครสวรรค์', 'Mueang Nakhon Sawan', 47),
(6002, 'โกรกพระ', 'Krok Phra', 47),
(6003, 'ชุมแสง', 'Chum Saeng', 47),
(6004, 'หนองบัว', 'Nong Bua', 47),
(6005, 'บรรพตพิสัย', 'Banphot Phisai', 47),
(6006, 'เก้าเลี้ยว', 'Kao Liao', 47),
(6007, 'ตาคลี', 'Takhli', 47),
(6008, 'ท่าตะโก', 'Takhli', 47),
(6009, 'ไพศาลี', 'Phaisali', 47),
(6010, 'พยุหะคีรี', 'Phayuha Khiri', 47),
(6011, 'ลาดยาว', 'Phayuha Khiri', 47),
(6012, 'ตากฟ้า', 'Tak Fa', 47),
(6013, 'แม่วงก์', 'Mae Wong', 47),
(6014, 'แม่เปิน', 'Mae Poen', 47),
(6015, 'ชุมตาบง', 'Chum Ta Bong', 47),
(6101, 'เมืองอุทัยธานี', 'Mueang Uthai Thani', 48),
(6102, 'ทัพทัน', 'Thap Than', 48),
(6103, 'สว่างอารมณ์', 'Sawang Arom', 48),
(6104, 'หนองฉาง', 'Nong Chang', 48),
(6105, 'หนองขาหย่าง', 'Nong Khayang', 48),
(6106, 'บ้านไร่', 'Ban Rai', 48),
(6107, 'ลานสัก', 'Lan Sak', 48),
(6108, 'ห้วยคต', 'Huai Khot', 48),
(6201, 'เมืองกำแพงเพชร', 'Mueang Kamphaeng Phet', 49),
(6202, 'ไทรงาม', 'Sai Ngam', 49),
(6203, 'คลองลาน', 'Khlong Lan', 49),
(6204, 'ขาณุวรลักษบุรี', 'Khanu Woralaksaburi', 49),
(6205, 'คลองขลุง', 'Khlong Khlung', 49),
(6206, 'พรานกระต่าย', 'Phran Kratai', 49),
(6207, 'ลานกระบือ', 'Lan Krabue', 49),
(6208, 'ทรายทองวัฒนา', 'Sai Thong Watthana', 49),
(6209, 'ปางศิลาทอง', 'Pang Sila Thong', 49),
(6210, 'บึงสามัคคี', 'Bueng Samakkhi', 49),
(6211, 'โกสัมพีนคร', 'Kosamphi Nakhon', 49),
(6301, 'เมืองตาก', 'Mueang Tak', 50),
(6302, 'บ้านตาก', 'Ban Tak', 50),
(6303, 'สามเงา', 'Sam Ngao', 50),
(6304, 'แม่ระมาด', 'Mae Ramat', 50),
(6305, 'ท่าสองยาง', 'Tha Song Yang', 50),
(6306, 'แม่สอด', 'Mae Sot', 50),
(6307, 'พบพระ', 'Phop Phra', 50),
(6308, 'อุ้มผาง', 'Umphang', 50),
(6309, 'วังเจ้า', 'Wang Chao', 50),
(6401, 'เมืองสุโขทัย', 'Mueang Sukhothai', 51),
(6402, 'บ้านด่านลานหอย', 'Ban Dan Lan Hoi', 51),
(6403, 'คีรีมาศ', 'Khiri Mat', 51),
(6404, 'กงไกรลาศ', 'Kong Krailat', 51),
(6405, 'ศรีสัชนาลัย', 'Si Satchanalai', 51),
(6406, 'ศรีสำโรง', 'Si Samrong', 51),
(6407, 'สวรรคโลก', 'Sawankhalok', 51),
(6408, 'ศรีนคร', 'Si Nakhon', 51),
(6409, 'ทุ่งเสลี่ยม', 'Thung Saliam', 51),
(6501, 'เมืองพิษณุโลก', 'Mueang Phitsanulok', 52),
(6502, 'นครไทย', 'Nakhon Thai', 52),
(6503, 'ชาติตระการ', 'Chat Trakan', 52),
(6504, 'บางระกำ', 'Bang Rakam', 52),
(6505, 'บางกระทุ่ม', 'Bang Krathum', 52),
(6506, 'พรหมพิราม', 'Phrom Phiram', 52),
(6507, 'วัดโบสถ์', 'Wat Bot', 52),
(6508, 'วังทอง', 'Wang Thong', 52),
(6509, 'เนินมะปราง', 'Noen Maprang', 52),
(6601, 'เมืองพิจิตร', 'Mueang Phichit', 53),
(6602, 'วังทรายพูน', 'Wang Sai Phun', 53),
(6603, 'โพธิ์ประทับช้าง', 'Pho Prathap Chang', 53),
(6604, 'ตะพานหิน', 'Taphan Hin', 53),
(6605, 'บางมูลนาก', 'Bang Mun Nak', 53),
(6606, 'โพทะเล', 'Pho Thale', 53),
(6607, 'สามง่าม', 'Sam Ngam', 53),
(6608, 'ทับคล้อ', 'Tap Khlo', 53),
(6609, 'สากเหล็ก', 'Sak Lek', 53),
(6610, 'บึงนาราง', 'Bueng Na Rang', 53),
(6611, 'ดงเจริญ', 'Dong Charoen', 53),
(6612, 'วชิรบารมี', 'Wachirabarami', 53),
(6701, 'เมืองเพชรบูรณ์', 'Mueang Phetchabun', 54),
(6702, 'ชนแดน', 'Chon Daen', 54),
(6703, 'หล่มสัก', 'Lom Sak', 54),
(6704, 'หล่มเก่า', 'Lom Kao', 54),
(6705, 'วิเชียรบุรี', 'Wichian Buri', 54),
(6706, 'ศรีเทพ', 'Si Thep', 54),
(6707, 'หนองไผ่', 'Nong Phai', 54),
(6708, 'บึงสามพัน', 'Bueng Sam Phan', 54),
(6709, 'น้ำหนาว', 'Nam Nao', 54),
(6710, 'วังโป่ง', 'Wang Pong', 54),
(6711, 'เขาค้อ', 'Khao Kho', 54),
(7001, 'เมืองราชบุรี', 'Mueang Ratchaburi', 55),
(7002, 'จอมบึง', 'Chom Bueng', 55),
(7003, 'สวนผึ้ง', 'Suan Phueng', 55),
(7004, 'ดำเนินสะดวก', 'Damnoen Saduak', 55),
(7005, 'บ้านโป่ง', 'Ban Pong', 55),
(7006, 'บางแพ', 'Bang Phae', 55),
(7007, 'โพธาราม', 'Photharam', 55),
(7008, 'ปากท่อ', 'Pak Tho', 55),
(7009, 'วัดเพลง', 'Wat Phleng', 55),
(7010, 'บ้านคา', 'Ban Kha', 55),
(7074, 'ท้องถิ่นเทศบาลตำบลบ้านฆ้อง', 'Tet Saban Ban Kong', 55),
(7101, 'เมืองกาญจนบุรี', 'Mueang Kanchanaburi', 56),
(7102, 'ไทรโยค', 'Sai Yok', 56),
(7103, 'บ่อพลอย', 'Bo Phloi', 56),
(7104, 'ศรีสวัสดิ์', 'Si Sawat', 56),
(7105, 'ท่ามะกา', 'Tha Maka', 56),
(7106, 'ท่าม่วง', 'Tha Muang', 56),
(7107, 'ทองผาภูมิ', 'Pha Phum', 56),
(7108, 'สังขละบุรี', 'Sangkhla Buri', 56),
(7109, 'พนมทวน', 'Phanom Thuan', 56),
(7110, 'เลาขวัญ', 'Lao Khwan', 56),
(7111, 'ด่านมะขามเตี้ย', 'Dan Makham Tia', 56),
(7112, 'หนองปรือ', 'Nong Prue', 56),
(7113, 'ห้วยกระเจา', 'Huai Krachao', 56),
(7201, 'เมืองสุพรรณบุรี', 'Mueang Suphan Buri', 57),
(7202, 'เดิมบางนางบวช', 'Doem Bang Nang Buat', 57),
(7203, 'ด่านช้าง', 'Dan Chang', 57),
(7204, 'บางปลาม้า', 'Bang Pla Ma', 57),
(7205, 'ศรีประจันต์', 'Si Prachan', 57),
(7206, 'ดอนเจดีย์', 'Don Chedi', 57),
(7207, 'สองพี่น้อง', 'Song Phi Nong', 57),
(7208, 'สามชุก', 'Sam Chuk', 57),
(7209, 'อู่ทอง', 'U Thong', 57),
(7210, 'หนองหญ้าไซ', 'Nong Ya Sai', 57),
(7301, 'เมืองนครปฐม', 'Mueang Nakhon Pathom', 58),
(7302, 'กำแพงแสน', 'Kamphaeng Saen', 58),
(7303, 'นครชัยศรี', 'Nakhon Chai Si', 58),
(7304, 'ดอนตูม', 'Don Tum', 58),
(7305, 'บางเลน', 'Bang Len', 58),
(7306, 'สามพราน', 'Sam Phran', 58),
(7307, 'พุทธมณฑล', 'Phutthamonthon', 58),
(7401, 'เมืองสมุทรสาคร', 'Mueang Samut Sakhon', 59),
(7402, 'กระทุ่มแบน', 'Krathum Baen', 59),
(7403, 'บ้านแพ้ว', 'Ban Phaeo', 59),
(7501, 'เมืองสมุทรสงคราม', 'Mueang Samut Songkhram', 60),
(7502, 'บางคนที', 'Bang Khonthi', 60),
(7503, 'อัมพวา', 'Amphawa', 60),
(7601, 'เมืองเพชรบุรี', 'Mueang Phetchaburi', 61),
(7602, 'เขาย้อย', 'Khao Yoi', 61),
(7603, 'หนองหญ้าปล้อง', 'Nong Ya Plong', 61),
(7604, 'ชะอำ', 'Cha-am', 61),
(7605, 'ท่ายาง', 'Tha Yang', 61),
(7606, 'บ้านลาด', 'Ban Lat', 61),
(7607, 'บ้านแหลม', 'Ban Laem', 61),
(7608, 'แก่งกระจาน', 'Kaeng Krachan', 61),
(7701, 'เมืองประจวบคีรีขันธ์', 'Mueang Prachuap Khiri Khan', 62),
(7702, 'กุยบุรี', 'Kui Buri', 62),
(7703, 'ทับสะแก', 'Thap Sakae', 62),
(7704, 'บางสะพาน', 'Bang Saphan', 62),
(7705, 'บางสะพานน้อย', 'Bang Saphan Noi', 62),
(7706, 'ปราณบุรี', 'Pran Buri', 62),
(7707, 'หัวหิน', 'Hua Hin', 62),
(7708, 'สามร้อยยอด', 'Sam Roi Yot', 62),
(8001, 'เมืองนครศรีธรรมราช', 'Mueang Nakhon Si Thammarat', 63),
(8002, 'พรหมคีรี', 'Phrom Khiri', 63),
(8003, 'ลานสกา', 'Lan Saka', 63),
(8004, 'ฉวาง', 'Chawang', 63),
(8005, 'พิปูน', 'Phipun', 63),
(8006, 'เชียรใหญ่', 'Chian Yai', 63),
(8007, 'ชะอวด', 'Cha-uat', 63),
(8008, 'ท่าศาลา', 'Tha Sala', 63),
(8009, 'ทุ่งสง', 'Thung Song', 63),
(8010, 'นาบอน', 'Na Bon', 63),
(8011, 'ทุ่งใหญ่', 'Thung Yai', 63),
(8012, 'ปากพนัง', 'Pak Phanang', 63),
(8013, 'ร่อนพิบูลย์', 'Ron Phibun', 63),
(8014, 'สิชล', 'Sichon', 63),
(8015, 'ขนอม', 'Khanom', 63),
(8016, 'หัวไทร', 'Hua Sai', 63),
(8017, 'บางขัน', 'Bang Khan', 63),
(8018, 'ถ้ำพรรณรา', 'Tham Phannara', 63),
(8019, 'จุฬาภรณ์', 'Chulabhorn', 63),
(8020, 'พระพรหม', 'Phra Phrom', 63),
(8021, 'นบพิตำ', 'Nopphitam', 63),
(8022, 'ช้างกลาง', 'Chang Klang', 63),
(8023, 'เฉลิมพระเกียรติ', 'Chaloem Phra Kiat', 63),
(8101, 'เมืองกระบี่', 'Mueang Krabi', 64),
(8102, 'เขาพนม', 'Khao Phanom', 64),
(8103, 'เกาะลันตา', 'Ko Lanta', 64),
(8104, 'คลองท่อม', 'Khlong Thom', 64),
(8105, 'อ่าวลึก', 'Ao Luek', 64),
(8106, 'ปลายพระยา', 'Plai Phraya', 64),
(8107, 'ลำทับ', 'Lam Thap', 64),
(8108, 'เหนือคลอง', 'Nuea Khlong', 64),
(8201, 'เมืองพังงา', 'Mueang Phang-nga', 65),
(8202, 'เกาะยาว', 'Ko Yao', 65),
(8203, 'กะปง', 'Kapong', 65),
(8204, 'ตะกั่วทุ่ง', 'Takua Thung', 65),
(8205, 'ตะกั่วป่า', 'Takua Pa', 65),
(8206, 'คุระบุรี', 'Khura Buri', 65),
(8207, 'ทับปุด', 'Thap Put', 65),
(8208, 'ท้ายเหมือง', 'Thai Mueang', 65),
(8301, 'เมืองภูเก็ต', 'Mueang Phuket', 66),
(8302, 'กะทู้', 'Kathu', 66),
(8303, 'ถลาง', 'Thalang', 66),
(8401, 'เมืองสุราษฎร์ธานี', 'Mueang Surat Thani', 67),
(8402, 'กาญจนดิษฐ์', 'Kanchanadit', 67),
(8403, 'ดอนสัก', 'Don Sak', 67),
(8404, 'เกาะสมุย', 'Ko Samui', 67),
(8405, 'เกาะพะงัน', 'Ko Pha-ngan', 67),
(8406, 'ไชยา', 'Chaiya', 67),
(8407, 'ท่าชนะ', 'Tha Chana', 67),
(8408, 'คีรีรัฐนิคม', 'Khiri Rat Nikhom', 67),
(8409, 'บ้านตาขุน', 'Ban Ta Khun', 67),
(8410, 'พนม', 'Phanom', 67),
(8411, 'ท่าฉาง', 'Tha Chang', 67),
(8412, 'บ้านนาสาร', 'Ban Na San', 67),
(8413, 'บ้านนาเดิม', 'Ban Na Doem', 67),
(8414, 'เคียนซา', 'Khian Sa', 67),
(8415, 'เวียงสระ', 'Wiang Sa', 67),
(8416, 'พระแสง', 'Phrasaeng', 67),
(8417, 'พุนพิน', 'Phunphin', 67),
(8418, 'ชัยบุรี', 'Chai Buri', 67),
(8419, 'วิภาวดี', 'Vibhavadi', 67),
(8501, 'เมืองระนอง', 'Mueang Ranong', 68),
(8502, 'ละอุ่น', 'La-un', 68),
(8503, 'กะเปอร์', 'Kapoe', 68),
(8504, 'กระบุรี', 'Kra Buri', 68),
(8505, 'สุขสำราญ', 'Suk Samran', 68),
(8601, 'เมืองชุมพร', 'Mueang Chumphon', 69),
(8602, 'ท่าแซะ', 'Tha Sae', 69),
(8603, 'ปะทิว', 'Pathio', 69),
(8604, 'หลังสวน', 'Lang Suan', 69),
(8605, 'ละแม', 'Lamae', 69),
(8606, 'พะโต๊ะ', 'Phato', 69),
(8607, 'สวี', 'Sawi', 69),
(8608, 'ทุ่งตะโก', 'Thung Tako', 69),
(9001, 'เมืองสงขลา', 'Mueang Songkhla', 70),
(9002, 'สทิงพระ', 'Sathing Phra', 70),
(9003, 'จะนะ', 'Chana', 70),
(9004, 'นาทวี', 'Na Thawi', 70),
(9005, 'เทพา', 'Thepha', 70),
(9006, 'สะบ้าย้อย', 'Saba Yoi', 70),
(9007, 'ระโนด', 'Ranot', 70),
(9008, 'กระแสสินธุ์', 'Krasae Sin', 70),
(9009, 'รัตภูมิ', 'Rattaphum', 70),
(9010, 'สะเดา', 'Sadao', 70),
(9011, 'หาดใหญ่', 'Hat Yai', 70),
(9012, 'นาหม่อม', 'Na Mom', 70),
(9013, 'ควนเนียง', 'Khuan Niang', 70),
(9014, 'บางกล่ำ', 'Bang Klam', 70),
(9015, 'สิงหนคร', 'Singhanakhon', 70),
(9016, 'คลองหอยโข่ง', 'Khlong Hoi Khong', 70),
(9077, 'ท้องถิ่นเทศบาลตำบลสำนักขาม', 'Sum Nung Kam', 70),
(9101, 'เมืองสตูล', 'Mueang Satun', 71),
(9102, 'ควนโดน', 'Khuan Don', 71),
(9103, 'ควนกาหลง', 'Khuan Kalong', 71),
(9104, 'ท่าแพ', 'Tha Phae', 71),
(9105, 'ละงู', 'La-ngu', 71),
(9106, 'ทุ่งหว้า', 'Thung Wa', 71),
(9107, 'มะนัง', 'Manang', 71),
(9201, 'เมืองตรัง', 'Mueang Trang', 72),
(9202, 'กันตัง', 'Kantang', 72),
(9203, 'ย่านตาขาว', 'Yan Ta Khao', 72),
(9204, 'ปะเหลียน', 'Palian', 72),
(9205, 'สิเกา', 'Sikao', 72),
(9206, 'ห้วยยอด', 'Huai Yot', 72),
(9207, 'วังวิเศษ', 'Wang Wiset', 72),
(9208, 'นาโยง', 'Na Yong', 72),
(9209, 'รัษฎา', 'Ratsada', 72),
(9210, 'หาดสำราญ', 'Hat Samran', 72),
(9301, 'เมืองพัทลุง', 'Mueang Phatthalung', 73),
(9302, 'กงหรา', 'Kong Ra', 73),
(9303, 'เขาชัยสน', 'Khao Chaison', 73),
(9304, 'ตะโหมด', 'Tamot', 73),
(9305, 'ควนขนุน', 'Khuan Khanun', 73),
(9306, 'ปากพะยูน', 'Pak Phayun', 73),
(9307, 'ศรีบรรพต', 'Si Banphot', 73),
(9308, 'ป่าบอน', 'Pa Bon', 73),
(9309, 'บางแก้ว', 'Bang Kaeo', 73),
(9310, 'ป่าพะยอม', 'Pa Phayom', 73),
(9311, 'ศรีนครินทร์', 'Srinagarindra', 73),
(9401, 'เมืองปัตตานี', 'Mueang Pattani', 74),
(9402, 'โคกโพธิ์', 'Khok Pho', 74),
(9403, 'หนองจิก', 'Nong Chik', 74),
(9404, 'ปะนาเระ', 'Panare', 74),
(9405, 'มายอ', 'Mayo', 74),
(9406, 'ทุ่งยางแดง', 'Thung Yang Daeng', 74),
(9407, 'สายบุรี', 'Sai Buri', 74),
(9408, 'ไม้แก่น', 'Mai Kaen', 74),
(9409, 'ยะหริ่ง', 'Yaring', 74),
(9410, 'ยะรัง', 'Yarang', 74),
(9411, 'กะพ้อ', 'Kapho', 74),
(9412, 'แม่ลาน', 'Mae Lan', 74),
(9501, 'เมืองยะลา', 'Mueang Yala', 75),
(9502, 'เบตง', 'Betong', 75),
(9503, 'บันนังสตา', 'Bannang Sata', 75),
(9504, 'ธารโต', 'Than To', 75),
(9505, 'ยะหา', 'Yaha', 75),
(9506, 'รามัน', 'Raman', 75),
(9507, 'กาบัง', 'Kabang', 75),
(9508, 'กรงปินัง', 'Krong Pinang', 75),
(9601, 'เมืองนราธิวาส', 'Mueang Narathiwat', 76),
(9602, 'ตากใบ', 'Tak Bai', 76),
(9603, 'บาเจาะ', 'Bacho', 76),
(9604, 'ยี่งอ', 'Yi-ngo', 76),
(9605, 'ระแงะ', 'Ra-ngae', 76),
(9606, 'รือเสาะ', 'Rueso', 76),
(9607, 'ศรีสาคร', 'Si Sakhon', 76),
(9608, 'แว้ง', 'Waeng', 76),
(9609, 'สุคิริน', 'Sukhirin', 76),
(9610, 'สุไหงโก-ลก', 'Su-ngai Kolok', 76),
(9611, 'สุไหงปาดี', 'Su-ngai Padi', 76),
(9612, 'จะแนะ', 'Chanae', 76),
(9613, 'เจาะไอร้อง', 'Cho-airong', 76);

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
(0, 'P-0000', '-', 'ทดสอบ', 'ทดสอบ', NULL, NULL, '089746513', NULL, NULL, NULL, '2025-12-18 09:58:07', '2025-12-18 09:58:07', 'Male', 'Active', 100001, 0, 10, 0, 0, 0, NULL),
(100001, '10000000001', '1000000000001', 'ผู้จัดการ', 'ผู้จัดการ', NULL, NULL, '0800000009', NULL, NULL, NULL, '2025-12-14 22:58:04', '2025-12-14 22:58:04', 'Male', 'Active', 1, 1, 10, 1, 1, 1, NULL),
(100002, '10000000002', '-', 'user', 'user', NULL, NULL, '089999999999', NULL, NULL, NULL, '2025-12-18 10:11:14', '2025-12-18 10:11:14', 'Male', 'Active', 100001, 1, 10, 3, 2, 2, NULL),
(100003, '10000000003', '-', '๊User2', 'User2', NULL, NULL, '089999999999', NULL, NULL, NULL, '2025-12-18 13:33:59', '2025-12-18 13:33:59', 'Male', 'Active', 100001, 2, 10, 5, 3, 3, NULL);

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
(125, 'all_access', NULL, '2025-12-18 13:51:33', '2025-12-18 13:51:33');

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
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะ (1=ใช้งาน, 0=ไม่ใช้งาน)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prefixs`
--

INSERT INTO `prefixs` (`prefix_id`, `prefix_th`, `prefix_en`, `prefix_th_abbr`, `prefix_en_abbr`, `is_active`) VALUES
(100001, 'นาย', 'Mister', '', 'Mr.', 1),
(100002, 'นางสาว', '', '', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `prod_id` int(6) NOT NULL,
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

INSERT INTO `products` (`prod_id`, `shop_info_shop_id`, `prod_name`, `model_name`, `model_no`, `prod_desc`, `prod_price`, `create_at`, `update_at`, `prod_brands_brand_id`, `prod_types_type_id`, `price`) VALUES
(999999, 0, 'ค่าแรง', '-', '-', '', 350.00, '2025-11-24 20:13:42', '2025-11-24 20:13:42', 5, 4, 0.00);

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
(1, 3, 'ซัมซุง', 'Samsung', '2025-12-18 13:47:24', '2025-12-18 13:47:24');

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
(1, 0, 'โทรศัพท์มือถือ', 'SmartPhone', '2025-10-27 10:25:36', '2025-10-27 10:25:36'),
(2, 0, 'แท็บเล็ต', 'Tablet', '2025-11-02 11:35:13', '2025-11-02 11:35:13'),
(3, 0, 'อะไหล', NULL, '2025-11-02 11:35:13', '2025-11-02 11:35:13'),
(4, 0, 'บริการ', NULL, '2025-11-24 20:12:02', '2025-11-24 20:12:02');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `province_id` int(2) NOT NULL,
  `province_name_th` varchar(50) NOT NULL,
  `province_name_en` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`province_id`, `province_name_th`, `province_name_en`) VALUES
(1, 'กรุงเทพมหานคร', 'Bangkok'),
(2, 'สมุทรปราการ', 'Samut Prakan'),
(3, 'นนทบุรี', 'Nonthaburi'),
(4, 'ปทุมธานี', 'Pathum Thani'),
(5, 'พระนครศรีอยุธยา', 'Phra Nakhon Si Ayutthaya'),
(6, 'อ่างทอง', 'Ang Thong'),
(7, 'ลพบุรี', 'Lopburi'),
(8, 'สิงห์บุรี', 'Sing Buri'),
(9, 'ชัยนาท', 'Chai Nat'),
(10, 'สระบุรี', 'Saraburi'),
(11, 'ชลบุรี', 'Chon Buri'),
(12, 'ระยอง', 'Rayong'),
(13, 'จันทบุรี', 'Chanthaburi'),
(14, 'ตราด', 'Trat'),
(15, 'ฉะเชิงเทรา', 'Chachoengsao'),
(16, 'ปราจีนบุรี', 'Prachin Buri'),
(17, 'นครนายก', 'Nakhon Nayok'),
(18, 'สระแก้ว', 'Sa Kaeo'),
(19, 'นครราชสีมา', 'Nakhon Ratchasima'),
(20, 'บุรีรัมย์', 'Buri Ram'),
(21, 'สุรินทร์', 'Surin'),
(22, 'ศรีสะเกษ', 'Si Sa Ket'),
(23, 'อุบลราชธานี', 'Ubon Ratchathani'),
(24, 'ยโสธร', 'Yasothon'),
(25, 'ชัยภูมิ', 'Chaiyaphum'),
(26, 'อำนาจเจริญ', 'Amnat Charoen'),
(27, 'หนองบัวลำภู', 'Nong Bua Lam Phu'),
(28, 'ขอนแก่น', 'Khon Kaen'),
(29, 'อุดรธานี', 'Udon Thani'),
(30, 'เลย', 'Loei'),
(31, 'หนองคาย', 'Nong Khai'),
(32, 'มหาสารคาม', 'Maha Sarakham'),
(33, 'ร้อยเอ็ด', 'Roi Et'),
(34, 'กาฬสินธุ์', 'Kalasin'),
(35, 'สกลนคร', 'Sakon Nakhon'),
(36, 'นครพนม', 'Nakhon Phanom'),
(37, 'มุกดาหาร', 'Mukdahan'),
(38, 'เชียงใหม่', 'Chiang Mai'),
(39, 'ลำพูน', 'Lamphun'),
(40, 'ลำปาง', 'Lampang'),
(41, 'อุตรดิตถ์', 'Uttaradit'),
(42, 'แพร่', 'Phrae'),
(43, 'น่าน', 'Nan'),
(44, 'พะเยา', 'Phayao'),
(45, 'เชียงราย', 'Chiang Rai'),
(46, 'แม่ฮ่องสอน', 'Mae Hong Son'),
(47, 'นครสวรรค์', 'Nakhon Sawan'),
(48, 'อุทัยธานี', 'Uthai Thani'),
(49, 'กำแพงเพชร', 'Kamphaeng Phet'),
(50, 'ตาก', 'Tak'),
(51, 'สุโขทัย', 'Sukhothai'),
(52, 'พิษณุโลก', 'Phitsanulok'),
(53, 'พิจิตร', 'Phichit'),
(54, 'เพชรบูรณ์', 'Phetchabun'),
(55, 'ราชบุรี', 'Ratchaburi'),
(56, 'กาญจนบุรี', 'Kanchanaburi'),
(57, 'สุพรรณบุรี', 'Suphan Buri'),
(58, 'นครปฐม', 'Nakhon Pathom'),
(59, 'สมุทรสาคร', 'Samut Sakhon'),
(60, 'สมุทรสงคราม', 'Samut Songkhram'),
(61, 'เพชรบุรี', 'Phetchaburi'),
(62, 'ประจวบคีรีขันธ์', 'Prachuap Khiri Khan'),
(63, 'นครศรีธรรมราช', 'Nakhon Si Thammarat'),
(64, 'กระบี่', 'Krabi'),
(65, 'พังงา', 'Phangnga'),
(66, 'ภูเก็ต', 'Phuket'),
(67, 'สุราษฎร์ธานี', 'Surat Thani'),
(68, 'ระนอง', 'Ranong'),
(69, 'ชุมพร', 'Chumphon'),
(70, 'สงขลา', 'Songkhla'),
(71, 'สตูล', 'Satun'),
(72, 'ตรัง', 'Trang'),
(73, 'พัทลุง', 'Phatthalung'),
(74, 'ปัตตานี', 'Pattani'),
(75, 'ยะลา', 'Yala'),
(76, 'นราธิวาส', 'Narathiwat'),
(77, 'บึงกาฬ', 'Bueng Kan');

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

-- --------------------------------------------------------

--
-- Table structure for table `religions`
--

CREATE TABLE `religions` (
  `religion_id` int(2) NOT NULL,
  `religion_name_th` varchar(30) NOT NULL,
  `religion_name_en` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะ (1=ใช้งาน, 0=ไม่ใช้งาน)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `religions`
--

INSERT INTO `religions` (`religion_id`, `religion_name_th`, `religion_name_en`, `is_active`) VALUES
(10, 'พุทธ', '', 1);

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

-- --------------------------------------------------------

--
-- Table structure for table `repair_symptoms`
--

CREATE TABLE `repair_symptoms` (
  `repairs_repair_id` int(6) NOT NULL,
  `symptoms_symptom_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(3) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_desc`, `create_at`, `update_at`) VALUES
(1, 'Admin', 'Admin', '2025-12-14 20:38:15', '2025-12-18 13:57:00'),
(2, 'Partner', NULL, '2025-12-18 09:54:55', '2025-12-18 13:46:10'),
(3, 'SuperAdmin', NULL, '2025-12-18 13:51:08', '2025-12-18 13:52:05');

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
(1, 1, '2025-12-18 13:57:00'),
(1, 2, '2025-12-18 13:57:00'),
(1, 3, '2025-12-18 13:57:00'),
(1, 4, '2025-12-18 13:57:00'),
(1, 5, '2025-12-18 13:57:00'),
(1, 6, '2025-12-18 13:57:00'),
(1, 7, '2025-12-18 13:57:00'),
(1, 8, '2025-12-18 13:57:00'),
(1, 9, '2025-12-18 13:57:00'),
(1, 10, '2025-12-18 13:57:00'),
(1, 11, '2025-12-18 13:57:00'),
(1, 12, '2025-12-18 13:57:00'),
(1, 13, '2025-12-18 13:57:00'),
(1, 14, '2025-12-18 13:57:00'),
(1, 15, '2025-12-18 13:57:00'),
(1, 16, '2025-12-18 13:57:00'),
(1, 17, '2025-12-18 13:57:00'),
(1, 18, '2025-12-18 13:57:00'),
(1, 19, '2025-12-18 13:57:00'),
(1, 20, '2025-12-18 13:57:00'),
(1, 21, '2025-12-18 13:57:00'),
(1, 22, '2025-12-18 13:57:00'),
(1, 23, '2025-12-18 13:57:00'),
(1, 24, '2025-12-18 13:57:00'),
(1, 25, '2025-12-18 13:57:00'),
(1, 26, '2025-12-18 13:57:00'),
(1, 27, '2025-12-18 13:57:00'),
(1, 28, '2025-12-18 13:57:00'),
(1, 29, '2025-12-18 13:57:00'),
(1, 30, '2025-12-18 13:57:00'),
(1, 31, '2025-12-18 13:57:00'),
(1, 32, '2025-12-18 13:57:00'),
(1, 33, '2025-12-18 13:57:00'),
(1, 34, '2025-12-18 13:57:00'),
(1, 35, '2025-12-18 13:57:00'),
(1, 36, '2025-12-18 13:57:00'),
(1, 37, '2025-12-18 13:57:00'),
(1, 38, '2025-12-18 13:57:00'),
(1, 39, '2025-12-18 13:57:00'),
(1, 40, '2025-12-18 13:57:00'),
(1, 41, '2025-12-18 13:57:00'),
(1, 42, '2025-12-18 13:57:00'),
(1, 43, '2025-12-18 13:57:00'),
(1, 44, '2025-12-18 13:57:00'),
(1, 45, '2025-12-18 13:57:00'),
(1, 46, '2025-12-18 13:57:00'),
(1, 47, '2025-12-18 13:57:00'),
(1, 48, '2025-12-18 13:57:00'),
(1, 49, '2025-12-18 13:57:00'),
(1, 50, '2025-12-18 13:57:00'),
(1, 51, '2025-12-18 13:57:00'),
(1, 52, '2025-12-18 13:57:00'),
(1, 53, '2025-12-18 13:57:00'),
(1, 54, '2025-12-18 13:57:00'),
(1, 55, '2025-12-18 13:57:00'),
(1, 56, '2025-12-18 13:57:00'),
(1, 57, '2025-12-18 13:57:00'),
(1, 58, '2025-12-18 13:57:00'),
(1, 59, '2025-12-18 13:57:00'),
(1, 60, '2025-12-18 13:57:00'),
(1, 61, '2025-12-18 13:57:00'),
(1, 62, '2025-12-18 13:57:00'),
(1, 63, '2025-12-18 13:57:00'),
(1, 64, '2025-12-18 13:57:00'),
(1, 65, '2025-12-18 13:57:00'),
(1, 66, '2025-12-18 13:57:00'),
(1, 67, '2025-12-18 13:57:00'),
(1, 68, '2025-12-18 13:57:00'),
(1, 69, '2025-12-18 13:57:00'),
(1, 70, '2025-12-18 13:57:00'),
(1, 71, '2025-12-18 13:57:00'),
(1, 72, '2025-12-18 13:57:00'),
(1, 73, '2025-12-18 13:57:00'),
(1, 74, '2025-12-18 13:57:00'),
(1, 75, '2025-12-18 13:57:00'),
(1, 76, '2025-12-18 13:57:00'),
(1, 77, '2025-12-18 13:57:00'),
(1, 78, '2025-12-18 13:57:00'),
(1, 79, '2025-12-18 13:57:00'),
(1, 80, '2025-12-18 13:57:00'),
(1, 81, '2025-12-18 13:57:00'),
(1, 83, '2025-12-18 13:57:00'),
(1, 84, '2025-12-18 13:57:00'),
(1, 85, '2025-12-18 13:57:00'),
(1, 86, '2025-12-18 13:57:00'),
(1, 87, '2025-12-18 13:57:00'),
(1, 88, '2025-12-18 13:57:00'),
(1, 89, '2025-12-18 13:57:00'),
(1, 90, '2025-12-18 13:57:00'),
(1, 91, '2025-12-18 13:57:00'),
(1, 92, '2025-12-18 13:57:00'),
(1, 93, '2025-12-18 13:57:00'),
(1, 94, '2025-12-18 13:57:00'),
(1, 95, '2025-12-18 13:57:00'),
(1, 96, '2025-12-18 13:57:00'),
(1, 97, '2025-12-18 13:57:00'),
(1, 98, '2025-12-18 13:57:00'),
(1, 99, '2025-12-18 13:57:00'),
(1, 100, '2025-12-18 13:57:00'),
(1, 101, '2025-12-18 13:57:00'),
(1, 102, '2025-12-18 13:57:00'),
(1, 103, '2025-12-18 13:57:00'),
(1, 104, '2025-12-18 13:57:00'),
(1, 105, '2025-12-18 13:57:00'),
(1, 106, '2025-12-18 13:57:00'),
(1, 107, '2025-12-18 13:57:00'),
(1, 108, '2025-12-18 13:57:00'),
(1, 109, '2025-12-18 13:57:00'),
(1, 110, '2025-12-18 13:57:00'),
(1, 111, '2025-12-18 13:57:00'),
(1, 112, '2025-12-18 13:57:00'),
(1, 113, '2025-12-18 13:57:00'),
(1, 114, '2025-12-18 13:57:00'),
(1, 115, '2025-12-18 13:57:00'),
(1, 116, '2025-12-18 13:57:00'),
(1, 117, '2025-12-18 13:57:00'),
(1, 118, '2025-12-18 13:57:00'),
(1, 119, '2025-12-18 13:57:00'),
(1, 120, '2025-12-18 13:57:00'),
(1, 121, '2025-12-18 13:57:00'),
(1, 122, '2025-12-18 13:57:00'),
(1, 123, '2025-12-18 13:57:00'),
(1, 124, '2025-12-18 13:57:00'),
(1, 125, '2025-12-18 13:57:00'),
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
(3, 125, '2025-12-18 13:52:05');

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
(0, 'Test Shop', '-', '089746513', NULL, NULL, '2025-12-18 09:58:06', '2025-12-18 09:58:06', 0, NULL, NULL),
(1, 'ขุมทรัพย์', '00', '000', 'adisonsompeng49@gmail.com', NULL, '2025-11-05 14:42:06', '2025-11-05 14:42:19', 2, 'zrgy skqo qvkf dooc', '0808214241'),
(2, 'ทดสอบ', '-', '089999999999', NULL, NULL, '2025-12-18 10:11:14', '2025-12-18 10:11:14', 1, NULL, NULL),
(3, 'Test Shop2', '-', '089999999999', NULL, NULL, '2025-12-18 13:33:59', '2025-12-18 13:33:59', 2, NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `subdistricts`
--

CREATE TABLE `subdistricts` (
  `subdistrict_id` int(6) NOT NULL,
  `subdistrict_name_th` varchar(50) NOT NULL,
  `subdistrict_name_en` varchar(50) DEFAULT NULL,
  `zip_code` varchar(5) NOT NULL,
  `districts_district_id` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subdistricts`
--

INSERT INTO `subdistricts` (`subdistrict_id`, `subdistrict_name_th`, `subdistrict_name_en`, `zip_code`, `districts_district_id`) VALUES
(100101, 'พระบรมมหาราชวัง', 'Phra Borom Maha Ratchawang', '10200', 1001),
(100102, 'วังบูรพาภิรมย์', 'Wang Burapha Phirom', '10200', 1001),
(100103, 'วัดราชบพิธ', 'Wat Ratchabophit', '10200', 1001),
(100104, 'สำราญราษฎร์', 'Samran Rat', '10200', 1001),
(100105, 'ศาลเจ้าพ่อเสือ', 'San Chao Pho Suea', '10200', 1001),
(100106, 'เสาชิงช้า', 'Sao Chingcha', '10200', 1001),
(100107, 'บวรนิเวศ', 'Bowon Niwet', '10200', 1001),
(100108, 'ตลาดยอด', 'Talat Yot', '10200', 1001),
(100109, 'ชนะสงคราม', 'Chana Songkhram', '10200', 1001),
(100110, 'บ้านพานถม', 'Ban Phan Thom', '10200', 1001),
(100111, 'บางขุนพรหม', 'Bang Khun Phrom', '10200', 1001),
(100112, 'วัดสามพระยา', 'Wat Sam Phraya', '10200', 1001);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(6) NOT NULL,
  `shop_info_shop_id` int(3) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `symptoms`
--

CREATE TABLE `symptoms` (
  `symptom_id` int(6) NOT NULL,
  `symptom_name` varchar(50) NOT NULL,
  `symptom_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', '#198754', '#ffc107', '#dc3545', '#198754', '#dc3545', '#fff3cd', '#dc3545');

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
(1, 'a', '$2y$10$rwBzTP4VT8Brn3i7H3SskOdfU8fsVFP7rQnX/t5qFENSUIw76ZJHi', 'Active', '2025-11-12 23:13:30', '2025-11-22 23:56:27'),
(0, 'test', '$2y$10$zrrulqtaJ.ZGoecZScYhf.46fHZ3vA4Rq.896wFMsoy8Js90kGw5K', 'Active', '2025-12-18 09:58:07', '2025-12-18 09:58:07'),
(2, 'user', '$2y$10$QH8NzxwAn6D/r67ExT1c0.xAE9AMpmb6bq87JtGuHZt.mVBh.wMta', 'Active', '2025-12-18 10:11:14', '2025-12-18 10:11:14'),
(3, 'user2', '$2y$10$wO3MLwpms7Ln9WYElV5sD.nD3Bfbzd2C/7kBf6.46UGzBQU0.F6fO', 'Active', '2025-12-18 13:33:59', '2025-12-18 13:33:59');

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
(2, 3, '2025-11-27 21:36:52'),
(2, 4, '2025-12-06 00:30:39'),
(2, 5, '2025-12-06 00:31:12'),
(2, 6, '2025-12-06 00:33:14'),
(3, 7, '2025-12-06 00:37:41'),
(2, 0, '2025-12-18 09:58:07'),
(2, 2, '2025-12-18 10:11:14'),
(2, 3, '2025-12-18 13:33:59');

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
  ADD KEY `address_id_customers` (`Addresses_address_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
