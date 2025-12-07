-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 07, 2025 at 03:13 PM
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
(0, NULL, NULL, NULL, NULL, NULL, 100002),
(1, '', '', '', '', NULL, 100002),
(2, NULL, NULL, NULL, NULL, NULL, 100002),
(3, '', '', '', '', '', 100002),
(4, '', '', '', '', '', 100002),
(5, '9', '9', '9', '9', '9', 100002),
(6, '', '', '', '', '', 100002),
(7, NULL, NULL, NULL, NULL, NULL, 100002),
(9, NULL, NULL, NULL, NULL, NULL, 100002),
(10, NULL, NULL, NULL, NULL, NULL, 100002),
(11, NULL, NULL, NULL, NULL, NULL, 100002),
(12, NULL, NULL, NULL, NULL, NULL, 100002),
(13, NULL, NULL, NULL, NULL, NULL, 100002),
(14, NULL, NULL, NULL, NULL, NULL, 100002),
(15, NULL, NULL, NULL, NULL, NULL, 100002);

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
(2, 1, 35000.00, '2025-11-20 16:46:13', '2025-11-20 16:46:13', 2, 100001, 100001, NULL, NULL),
(3, 1, 35000.00, '2025-11-21 03:22:59', '2025-11-21 03:22:59', 3, 100001, 100003, NULL, NULL),
(4, 1, 35000.00, '2025-11-21 03:27:46', '2025-11-21 03:27:46', 4, 100001, 100005, NULL, NULL),
(5, 1, 15000.00, '2025-11-22 23:27:38', '2025-11-22 23:27:38', 6, 100013, 100008, NULL, NULL),
(6, 1, 1500.00, '2025-11-22 23:30:42', '2025-11-22 23:30:42', 5, 100013, 100006, NULL, NULL),
(7, 1, 35000.00, '2025-11-23 18:27:32', '2025-11-23 18:27:32', 7, 100001, 100004, NULL, NULL),
(10, 1, 999.00, '2025-11-24 20:27:03', '2025-11-24 20:27:03', 10, 200002, 100020, NULL, NULL),
(11, 1, 1000.00, '2025-11-24 20:27:10', '2025-11-24 20:27:10', 10, 200001, 100019, NULL, NULL),
(12, 1, 500.00, '2025-11-24 20:27:18', '2025-11-24 20:27:18', 10, 999999, NULL, NULL, NULL),
(13, 1, 999.00, '2025-11-24 20:43:58', '2025-11-24 20:43:58', 11, 200002, 100021, NULL, NULL),
(14, 1, 500.00, '2025-11-24 20:44:13', '2025-11-24 20:44:13', 11, 999999, NULL, NULL, NULL),
(15, 1, 500.00, '2025-11-25 20:36:27', '2025-11-25 20:36:27', 15, 999999, NULL, NULL, NULL),
(16, 1, 999.00, '2025-11-25 20:36:40', '2025-11-25 20:36:40', 15, 200002, 100023, 3, NULL),
(17, 1, 1000.00, '2025-11-25 20:36:51', '2025-11-25 20:36:51', 15, 200001, 100029, 3, NULL),
(18, 1, 41500.00, '2025-11-27 09:11:29', '2025-11-27 09:11:29', 16, 110000, 100011, NULL, NULL),
(19, 1, 35000.00, '2025-11-27 09:11:44', '2025-11-27 09:11:44', 17, 100001, 100015, NULL, NULL),
(20, 1, 1500.00, '2025-11-27 09:48:34', '2025-11-27 09:48:34', 19, 100013, 100010, NULL, NULL),
(21, 1, 41500.00, '2025-11-27 09:53:17', '2025-11-27 09:53:17', 20, 110000, 100012, NULL, NULL),
(22, 1, 41500.00, '2025-11-27 21:58:09', '2025-11-27 21:58:09', 18, 110000, 100013, NULL, NULL),
(23, 1, 35000.00, '2025-11-29 11:11:51', '2025-11-29 11:11:51', 21, 100001, 100036, NULL, NULL);

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
(2, '2025-11-20 16:46:12', '2025-11-20 17:18:49', 'Cash', 'Completed', 7.00, '', 1000.00, '2025-11-20 16:46:12', '2025-11-20 16:46:12', 100003, 'Sale', 1, 1),
(3, '2025-11-21 03:22:59', '2025-11-21 03:23:01', 'Cash', 'Completed', 7.00, '', 0.00, '2025-11-21 03:22:59', '2025-11-21 03:22:59', 100003, 'Sale', 1, 1),
(4, '2025-11-21 03:27:46', '2025-11-21 03:27:51', 'Cash', 'Completed', 7.00, '', 0.00, '2025-11-21 03:27:46', '2025-11-21 03:27:46', 100003, 'Sale', 1, 1),
(5, '2025-11-21 03:55:40', '2025-11-21 04:00:04', 'Cash', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-21 03:55:40', '2025-11-21 04:00:04', 100003, 'Repair', 1, 1),
(6, '2025-11-22 23:27:38', '2025-11-22 23:27:46', 'QR', 'Completed', 7.00, '', 100.00, '2025-11-22 23:27:38', '2025-11-22 23:27:38', 100004, 'Sale', 1, 2),
(7, '2025-11-23 18:27:32', '2025-11-23 18:28:14', 'Cash', 'Completed', 7.00, '', 0.00, '2025-11-23 18:27:32', '2025-11-23 18:27:32', 100003, 'Sale', 1, 2),
(8, '2025-11-23 18:31:31', '2025-11-24 13:23:07', 'Cash', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-23 18:31:31', '2025-11-24 13:23:07', 100003, 'Repair', 1, 2),
(9, '2025-11-23 19:13:45', '2025-11-24 01:58:21', 'Cash', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-23 19:13:45', '2025-11-24 01:58:21', 100004, 'Repair', 1, 2),
(10, '2025-11-24 19:55:01', '2025-11-24 20:31:53', 'QR', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-24 19:55:01', '2025-11-24 20:31:53', 100003, 'Repair', 1, 1),
(11, '2025-11-24 20:43:24', '2025-11-24 20:44:34', 'QR', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-24 20:43:24', '2025-11-24 20:44:34', 100003, 'Repair', 1, 2),
(12, '2025-11-24 20:53:33', '2025-11-24 20:53:33', 'Cash', 'Canceled', 7.00, 'เปิดบิลซ่อม (Job Order) [ยกเลิก: ห] [ยกเลิก: ส]', 0.00, '2025-11-24 20:53:33', '2025-11-24 21:29:53', 100003, 'Repair', 1, 2),
(13, '2025-11-24 21:57:34', '2025-11-24 22:09:06', '', 'Canceled', 7.00, 'ไม่มีค่าใช้จ่าย/ข้ามขั้นตอน: ยกเลิก [ยกเลิก: ยกเลิ', 0.00, '2025-11-24 21:57:34', '2025-11-24 22:34:40', 100003, 'Repair', 1, 2),
(14, '2025-11-24 22:29:17', '2025-11-24 22:29:17', 'Cash', 'Canceled', 7.00, 'เปิดบิลซ่อม (Job Order) [ยกเลิก: ยกเลิก]', 0.00, '2025-11-24 22:29:17', '2025-11-24 22:33:21', 100004, 'Repair', 1, 2),
(15, '2025-11-25 20:09:07', '2025-11-25 20:36:57', 'QR', 'Completed', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-25 20:09:07', '2025-11-25 20:36:57', 100004, 'Repair', 1, 2),
(16, '2025-11-27 09:11:29', '2025-11-27 09:11:31', 'Cash', 'Completed', 7.00, '', 0.00, '2025-11-27 09:11:29', '2025-11-27 09:11:29', 100003, 'Sale', 1, 2),
(17, '2025-11-27 09:11:44', '2025-11-27 09:11:44', 'Cash', 'Canceled', 7.00, ' [ยกเลิก: ฟ]', 0.00, '2025-11-27 09:11:44', '2025-11-27 22:12:49', 1, 'Sale', 1, 2),
(18, '2025-11-27 09:12:53', '2025-11-27 09:12:53', 'Cash', 'Pending', 7.00, 'เปิดบิลซ่อม (Job Order)', 0.00, '2025-11-27 09:12:53', '2025-11-27 21:58:09', 100003, 'Repair', 1, 2),
(19, '2025-11-27 09:48:34', '2025-11-27 09:48:34', 'Cash', 'Pending', 7.00, '', 0.00, '2025-11-27 09:48:34', '2025-11-27 09:48:34', 100003, 'Sale', 1, 2),
(20, '2025-11-27 09:53:16', '2025-11-27 09:53:16', 'QR', 'Pending', 7.00, '', 0.00, '2025-11-27 09:53:16', '2025-11-27 09:53:16', 100003, 'Sale', 1, 2),
(21, '2025-11-29 11:11:51', '2025-11-29 11:11:54', 'Cash', 'Completed', 7.00, '', 0.00, '2025-11-29 11:11:51', '2025-11-29 11:11:51', 100003, 'Sale', 1, 2);

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
(1, '999', 'สาขา9', '0888888888', '2025-11-12 22:30:57', '2025-11-24 01:30:13', 5, 1),
(2, '77', 'สาขา1', '00', '2025-11-12 22:32:22', '2025-11-12 22:32:22', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `cs_id` int(6) NOT NULL,
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

INSERT INTO `customers` (`cs_id`, `cs_national_id`, `firstname_th`, `lastname_th`, `firstname_en`, `lastname_en`, `cs_phone_no`, `cs_email`, `cs_line_id`, `create_at`, `update_at`, `prefixs_prefix_id`, `Addresses_address_id`) VALUES
(1, '', 'ลูกค้า', 'ด', '', '', '0888888888', '', '', '2025-11-12 22:33:21', '2025-11-22 04:06:32', 100002, 1),
(100002, '1101100000000', 'ภูริพัฒน์', 'เอื้อสุนทร', '', '', '0812345678', 'papaman1719@gmail.com', NULL, '2025-11-19 03:14:24', '2025-11-19 03:14:24', 100002, 4),
(100003, '1101100000000', 'สมหญิง', 'ใจดี', 'Somchai', 'Jaidee', '0812345678', 'adisonsompeng49@gmail.com', NULL, '2025-11-19 03:15:22', '2025-11-19 03:15:22', 100002, 5),
(100004, NULL, 'อดิศร', 'สมเพ็ง', NULL, NULL, '0812345678', 'adisonsompen01@gmai.com', NULL, '2025-11-19 22:38:33', '2025-11-19 22:38:33', 100002, 0);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(4) NOT NULL,
  `dept_name` varchar(50) NOT NULL,
  `dept_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `dept_desc`, `create_at`, `update_at`) VALUES
(1000, 'การตลาด', '', '2025-10-27 11:57:22', '2025-10-27 11:57:22'),
(1002, 'HR', '', '2025-11-04 16:53:19', '2025-11-04 16:53:19'),
(1003, 'ช่างซ่อม', '', '2025-11-04 16:53:29', '2025-11-04 16:53:29'),
(1009, 'การบัญชี', '', '2025-11-22 22:04:45', '2025-11-22 22:04:54');

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
(1000, 'ดด', 'a', 10);

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
(1, '1000000000001', '1000000000011', 'ดด', 'ดด', NULL, NULL, '9999', NULL, NULL, '2025-11-12', '2025-11-12 23:13:30', '2025-11-12 23:13:30', 'Male', 'Resigned', 100002, 7, 1, 1003, 2, 1, 'emp_1000000000001_1763923843.jpg'),
(2, '1042567891', '1522222222220', 'อดิศร', 'สมเป็ง', NULL, NULL, '0888888812', 'adisonsompeng49@gmail.com', NULL, '2025-11-21', '2025-11-22 22:28:30', '2025-11-22 22:28:30', 'Male', 'Active', 100002, 9, 1, 1000, 2, 2, 'emp_1042567891_1763923857.jpg'),
(3, '100000000007', '1444444444458', 'ทดสอบ', 'ทดสอบ', NULL, NULL, '08999999999', NULL, NULL, '2025-11-13', '2025-11-27 21:36:51', '2025-11-27 21:36:51', 'Male', 'Active', 100001, 11, 1, 1003, 2, 3, NULL),
(4, '1000000000044', '1000000000015', 'ทดสอบ', 'ทดสอบ', NULL, NULL, '0888888888', NULL, NULL, '2025-12-18', '2025-12-06 00:30:39', '2025-12-06 00:30:39', 'Male', 'Active', 100002, 12, 1, 1000, 2, 4, NULL),
(5, '1000000000042', '1000000000077', 'ทดสอบ', 'ทดสอบ', NULL, NULL, '0888888888', NULL, NULL, '2025-12-18', '2025-12-06 00:31:12', '2025-12-06 00:31:12', 'Male', 'Active', 100002, 13, 1, 1000, 2, 5, NULL),
(6, '1000000000041', '1444444444444', '่นน', 'นน', NULL, NULL, '0888888888', NULL, NULL, '2025-12-03', '2025-12-06 00:33:14', '2025-12-06 00:33:14', 'Male', 'Active', 100001, 14, 1, 1002, 2, 6, NULL),
(7, '1000000000088', '1444444444555', '่นน', 'นน', NULL, NULL, '0888888888', NULL, NULL, '2025-12-03', '2025-12-06 00:37:41', '2025-12-06 00:37:41', 'Male', 'Active', 100001, 15, 1, 1002, 2, 7, '');

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
(38, 5, 35000.00, '2025-11-29 11:07:34', '2025-11-29 11:07:34', 19, 100001),
(39, 3, 999.00, '2025-11-29 11:07:34', '2025-11-29 11:07:34', 19, 200002);

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
(123, 'change_password', NULL, '2025-11-22 22:30:56', '2025-11-22 22:30:56');

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
(100001, 'นางสาว', 'Miss', '', '', 1),
(100002, 'นาย', 'Misterh', '', 'Mr', 1);

-- --------------------------------------------------------

--
-- Table structure for table `prodout_types`
--

CREATE TABLE `prodout_types` (
  `outtype_id` int(3) NOT NULL,
  `outtype_name` varchar(30) NOT NULL,
  `outtype_desc` varchar(100) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `prod_id` int(6) NOT NULL,
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

INSERT INTO `products` (`prod_id`, `prod_name`, `model_name`, `model_no`, `prod_desc`, `prod_price`, `create_at`, `update_at`, `prod_brands_brand_id`, `prod_types_type_id`, `price`) VALUES
(100001, 'Iphone  13 Pro Max', '13 Pro Max2', '13524644', '', 35000.00, '2025-10-27 18:55:48', '2025-10-28 09:13:54', 4, 1, 0.00),
(100013, 'Oppo A17', 'A17', '10000333', '', 1500.00, '2025-10-28 09:18:42', '2025-10-28 09:18:42', 2, 1, 0.00),
(100999, 'IPAD GEN9', 'GEN9', '999', '', 9990.00, '2025-11-23 18:47:48', '2025-11-23 18:47:48', 4, 2, 0.00),
(110000, 'OPPO Find X9 Pro', 'Find X9 Pro', '9999', '', 41500.00, '2025-11-02 12:28:28', '2025-11-02 12:28:28', 2, 1, 0.00),
(200001, 'จอ IPAD', 'GEN9', '20001', '', 1000.00, '2025-11-24 20:22:13', '2025-11-24 20:22:13', 2, 3, 0.00),
(200002, 'BATT IPAD', 'GEN9', '220002', '', 999.00, '2025-11-24 20:22:57', '2025-11-24 20:22:57', 2, 3, 0.00),
(999999, 'ค่าแรง', '-', '-', '', 350.00, '2025-11-24 20:13:42', '2025-11-24 20:13:42', 5, 4, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `prod_brands`
--

CREATE TABLE `prod_brands` (
  `brand_id` int(4) NOT NULL,
  `brand_name_th` varchar(50) NOT NULL,
  `brand_name_en` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prod_brands`
--

INSERT INTO `prod_brands` (`brand_id`, `brand_name_th`, `brand_name_en`, `create_at`, `update_at`) VALUES
(2, 'อ๊อปโป้', 'Oppo', '2025-10-26 10:51:54', '2025-10-26 10:51:54'),
(3, 'วีโว่', 'Vivo', '2025-10-26 10:51:54', '2025-10-26 10:51:54'),
(4, 'แอปเปิ้ล', 'Apple', '2025-10-26 10:51:54', '2025-10-26 10:51:54'),
(5, 'บริการ', 'serve', '2025-11-24 20:12:28', '2025-11-24 20:12:28');

-- --------------------------------------------------------

--
-- Table structure for table `prod_stocks`
--

CREATE TABLE `prod_stocks` (
  `stock_id` int(6) NOT NULL,
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

INSERT INTO `prod_stocks` (`stock_id`, `serial_no`, `price`, `stock_status`, `warranty_start_date`, `image_path`, `create_at`, `update_at`, `products_prod_id`) VALUES
(100001, '1', 35000.00, 'Sold', NULL, NULL, '2025-11-19 04:51:05', '2025-11-20 16:46:13', 100001),
(100002, '177777', 0.00, 'Repair', NULL, NULL, '2025-11-20 03:48:06', '2025-11-20 03:48:06', 100001),
(100003, '1001', 35000.00, 'Sold', NULL, NULL, '2025-11-21 03:06:01', '2025-11-21 03:22:59', 100001),
(100004, '1002', 35000.00, 'Sold', NULL, NULL, '2025-11-21 03:06:01', '2025-11-23 18:27:32', 100001),
(100005, '1003', 35000.00, 'Sold', NULL, NULL, '2025-11-21 03:06:01', '2025-11-21 03:27:46', 100001),
(100006, '2001', 1500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:01', '2025-11-21 03:06:01', 100013),
(100007, '2002', 1500.00, 'In Stock', NULL, NULL, '2025-11-21 03:06:02', '2025-11-21 03:06:02', 100013),
(100008, '2003', 1500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:02', '2025-11-22 23:27:38', 100013),
(100009, '2004', 1500.00, 'In Stock', NULL, NULL, '2025-11-21 03:06:02', '2025-11-21 03:06:02', 100013),
(100010, '2005', 1500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:02', '2025-11-27 09:48:34', 100013),
(100011, '3001', 41500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:02', '2025-11-27 09:11:29', 110000),
(100012, '3002', 41500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:02', '2025-11-27 09:53:17', 110000),
(100013, '3003', 41500.00, 'Sold', NULL, NULL, '2025-11-21 03:06:02', '2025-11-27 21:58:09', 110000),
(100014, '1777777', 0.00, 'Sold', NULL, NULL, '2025-11-21 03:55:40', '2025-11-24 20:45:56', 100001),
(100015, '999', 35000.00, 'In Stock', NULL, NULL, '2025-11-22 23:23:42', '2025-11-27 22:12:49', 100001),
(100016, '154412', 0.00, 'Repair', NULL, NULL, '2025-11-23 18:31:31', '2025-11-23 18:31:31', 100001),
(100017, '99945', 0.00, 'Repair', NULL, NULL, '2025-11-23 19:13:45', '2025-11-23 19:13:45', 100999),
(100018, '84552', 0.00, 'Sold', NULL, NULL, '2025-11-24 19:55:01', '2025-11-24 20:45:30', 100999),
(100019, '20003', 1000.00, 'Sold', NULL, NULL, '2025-11-24 20:23:19', '2025-11-24 20:23:19', 200001),
(100020, '6215452', 999.00, 'Sold', NULL, NULL, '2025-11-24 20:23:39', '2025-11-24 20:23:39', 200002),
(100021, '6215451', 999.00, 'Sold', NULL, NULL, '2025-11-24 20:23:39', '2025-11-24 20:23:39', 200002),
(100022, '99999', 0.00, 'Sold', NULL, NULL, '2025-11-24 20:43:23', '2025-11-24 20:45:23', 100013),
(100023, '7241212', 999.00, 'Sold', NULL, NULL, '2025-11-24 20:52:47', '2025-11-24 20:52:47', 200002),
(100024, '4564532', 0.00, 'Sold', NULL, NULL, '2025-11-24 20:53:33', '2025-11-24 22:45:33', 100001),
(100025, '12455455', 1000.00, 'In Stock', NULL, NULL, '2025-11-24 20:57:18', '2025-11-24 20:57:18', 200001),
(100026, '4545364', 1000.00, 'In Stock', NULL, NULL, '2025-11-24 20:57:18', '2025-11-24 20:57:18', 200001),
(100027, '75271', 1000.00, 'In Stock', NULL, NULL, '2025-11-24 20:57:18', '2025-11-24 20:57:18', 200001),
(100028, '41457', 1000.00, 'In Stock', NULL, NULL, '2025-11-24 20:57:18', '2025-11-24 20:57:18', 200001),
(100029, '7786755', 1000.00, 'Sold', NULL, NULL, '2025-11-24 20:57:18', '2025-11-24 20:57:18', 200001),
(100030, '177777555', 0.00, 'Sold', NULL, NULL, '2025-11-24 21:57:34', '2025-11-24 22:45:53', 100999),
(100031, '999875', 0.00, 'Sold', NULL, NULL, '2025-11-24 22:29:17', '2025-11-24 22:46:05', 100999),
(100032, '85442', 0.00, 'Sold', NULL, NULL, '2025-11-25 20:09:07', '2025-11-25 20:36:57', 100999),
(100033, '88888', 0.00, 'Repair', NULL, NULL, '2025-11-27 09:12:53', '2025-11-27 09:12:53', 100999),
(100034, '53415', 35000.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 100001),
(100035, '75677882', 35000.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 100001),
(100036, '74212', 35000.00, 'Sold', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:11:51', 100001),
(100037, '57638241', 35000.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 100001),
(100038, '22554211', 35000.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 100001),
(100039, '9995785', 999.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 200002),
(100040, '45421', 999.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 200002),
(100041, '57674111', 999.00, 'In Stock', NULL, NULL, '2025-11-29 11:08:20', '2025-11-29 11:08:20', 200002),
(100042, '6934958700016', 0.00, 'In Stock', NULL, NULL, '2025-11-30 14:38:52', '2025-11-30 14:38:52', 100999),
(100043, '6500301110803', 0.00, 'In Stock', NULL, NULL, '2025-11-30 14:38:52', '2025-11-30 14:38:52', 100999),
(100044, '8851959129173', 0.00, 'In Stock', NULL, NULL, '2025-11-30 14:38:52', '2025-11-30 14:38:52', 100999),
(100045, '8850051010594', 0.00, 'In Stock', NULL, NULL, '2025-11-30 14:38:52', '2025-11-30 14:38:52', 100999);

-- --------------------------------------------------------

--
-- Table structure for table `prod_types`
--

CREATE TABLE `prod_types` (
  `type_id` int(4) NOT NULL,
  `type_name_th` varchar(50) NOT NULL,
  `type_name_en` varchar(50) DEFAULT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prod_types`
--

INSERT INTO `prod_types` (`type_id`, `type_name_th`, `type_name_en`, `create_at`, `update_at`) VALUES
(1, 'โทรศัพท์มือถือ', 'SmartPhone', '2025-10-27 10:25:36', '2025-10-27 10:25:36'),
(2, 'แท็บเล็ต', 'Tablet', '2025-11-02 11:35:13', '2025-11-02 11:35:13'),
(3, 'อะไหล', NULL, '2025-11-02 11:35:13', '2025-11-02 11:35:13'),
(4, 'บริการ', NULL, '2025-11-24 20:12:02', '2025-11-24 20:12:02');

-- --------------------------------------------------------

--
-- Table structure for table `prod_warranty`
--

CREATE TABLE `prod_warranty` (
  `warranty_id` int(6) NOT NULL,
  `warranty_name` varchar(50) NOT NULL,
  `warranty_desc` varchar(100) DEFAULT NULL,
  `dur_mount` int(6) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  `update_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(10, 'กรุงเทพ', 'Bangkok');

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
(19, '2025-11-29 11:07:00', '2025-11-29 11:07:34', '2025-11-29 11:07:34', 100002, 1, 2, 'Pending', NULL);

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
(1, 'พุทธ', 'P', 1);

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `repair_id` int(6) NOT NULL,
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

INSERT INTO `repairs` (`repair_id`, `repair_desc`, `device_description`, `repair_status`, `estimated_cost`, `accessories_list`, `create_at`, `update_at`, `customers_cs_id`, `prod_stocks_stock_id`, `bill_headers_bill_id`, `employees_emp_id`, `assigned_employee_id`) VALUES
(100001, '', 'ฟ', 'ส่งมอบ', 0.01, '', '2025-11-20 03:48:07', '2025-11-21 03:18:14', 100004, 100002, NULL, 1, 1),
(100002, '', '้', 'ส่งมอบ', 0.01, '', '2025-11-21 03:55:40', '2025-11-24 20:45:56', 100003, 100014, 5, 1, 1),
(100003, '', 'ฟ', 'ส่งมอบ', 0.01, '', '2025-11-23 18:31:31', '2025-11-24 13:23:07', 100003, 100016, 8, 2, 2),
(100004, '', '', 'ส่งมอบ', 0.01, '', '2025-11-23 19:13:45', '2025-11-24 01:58:21', 100004, 100017, 9, 2, 1),
(100005, '', 'ฟ', 'ส่งมอบ', 0.01, '', '2025-11-24 19:55:01', '2025-11-24 20:45:30', 100003, 100018, 10, 1, 2),
(100006, '', 'ก', 'ส่งมอบ', 0.01, '', '2025-11-24 20:43:24', '2025-11-24 20:45:23', 100003, 100022, 11, 2, 2),
(100007, '', 'ฟ', 'ส่งมอบ', 0.01, '', '2025-11-24 20:53:34', '2025-11-24 22:45:33', 100003, 100024, 12, 2, 2),
(100008, '', 'นส', 'ส่งมอบ', 0.01, '', '2025-11-24 21:57:34', '2025-11-24 22:45:52', 100003, 100030, 13, 2, 2),
(100009, '', 'ส', 'ส่งมอบ', 0.01, '', '2025-11-24 22:29:17', '2025-11-24 22:46:04', 100004, 100031, 14, 2, 2),
(100010, '', 'เด', 'ส่งมอบ', 0.01, '', '2025-11-25 20:09:07', '2025-11-25 20:36:57', 100004, 100032, 15, 2, 2),
(100011, '', '', 'รับเครื่อง', 0.01, '', '2025-11-27 09:12:53', '2025-11-27 09:12:53', 100003, 100033, 18, 2, 2);

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
(55, 100010, NULL, 'รับเครื่อง', 2, '2025-11-25 20:09:08', NULL),
(56, 100010, 'รับเครื่อง', 'ซ่อมเสร็จ', 1, '2025-11-25 20:09:24', ''),
(57, 100010, 'ซ่อมเสร็จ', 'ส่งมอบ', 1, '2025-11-25 20:36:57', 'ชำระเงินผ่าน QR และส่งมอบอัตโนมัติ'),
(58, 100011, NULL, 'รับเครื่อง', 2, '2025-11-27 09:12:54', NULL);

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
(100010, 100001),
(100011, 100010);

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
(1, 'Admin', NULL, '2025-11-02 13:57:45', '2025-11-23 17:57:40'),
(2, 'user', NULL, '2025-11-23 19:50:20', '2025-11-27 09:39:55'),
(3, 'ทดสอบ', NULL, '2025-12-06 00:52:25', '2025-12-06 00:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `roles_role_id` int(3) NOT NULL,
  `permissions_permission_id` int(3) NOT NULL,
  `create_at` int(11) DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`roles_role_id`, `permissions_permission_id`, `create_at`) VALUES
(1, 1, 2147483647),
(1, 2, 2147483647),
(1, 3, 2147483647),
(1, 4, 2147483647),
(1, 5, 2147483647),
(1, 6, 2147483647),
(1, 7, 2147483647),
(1, 8, 2147483647),
(1, 9, 2147483647),
(1, 10, 2147483647),
(1, 11, 2147483647),
(1, 12, 2147483647),
(1, 13, 2147483647),
(1, 14, 2147483647),
(1, 15, 2147483647),
(1, 16, 2147483647),
(1, 17, 2147483647),
(1, 18, 2147483647),
(1, 19, 2147483647),
(1, 20, 2147483647),
(1, 21, 2147483647),
(1, 22, 2147483647),
(1, 23, 2147483647),
(1, 24, 2147483647),
(1, 25, 2147483647),
(1, 26, 2147483647),
(1, 27, 2147483647),
(1, 28, 2147483647),
(1, 29, 2147483647),
(1, 30, 2147483647),
(1, 31, 2147483647),
(1, 32, 2147483647),
(1, 33, 2147483647),
(1, 34, 2147483647),
(1, 35, 2147483647),
(1, 36, 2147483647),
(1, 37, 2147483647),
(1, 38, 2147483647),
(1, 39, 2147483647),
(1, 40, 2147483647),
(1, 41, 2147483647),
(1, 42, 2147483647),
(1, 43, 2147483647),
(1, 44, 2147483647),
(1, 45, 2147483647),
(1, 46, 2147483647),
(1, 47, 2147483647),
(1, 48, 2147483647),
(1, 49, 2147483647),
(1, 50, 2147483647),
(1, 51, 2147483647),
(1, 52, 2147483647),
(1, 53, 2147483647),
(1, 54, 2147483647),
(1, 55, 2147483647),
(1, 56, 2147483647),
(1, 57, 2147483647),
(1, 58, 2147483647),
(1, 59, 2147483647),
(1, 60, 2147483647),
(1, 61, 2147483647),
(1, 62, 2147483647),
(1, 63, 2147483647),
(1, 64, 2147483647),
(1, 65, 2147483647),
(1, 66, 2147483647),
(1, 67, 2147483647),
(1, 68, 2147483647),
(1, 69, 2147483647),
(1, 70, 2147483647),
(1, 71, 2147483647),
(1, 72, 2147483647),
(1, 73, 2147483647),
(1, 74, 2147483647),
(1, 75, 2147483647),
(1, 76, 2147483647),
(1, 77, 2147483647),
(1, 78, 2147483647),
(1, 79, 2147483647),
(1, 80, 2147483647),
(1, 81, 2147483647),
(1, 83, 2147483647),
(1, 84, 2147483647),
(1, 85, 2147483647),
(1, 86, 2147483647),
(1, 87, 2147483647),
(1, 88, 2147483647),
(1, 89, 2147483647),
(1, 90, 2147483647),
(1, 91, 2147483647),
(1, 92, 2147483647),
(1, 93, 2147483647),
(1, 94, 2147483647),
(1, 95, 2147483647),
(1, 96, 2147483647),
(1, 97, 2147483647),
(1, 98, 2147483647),
(1, 99, 2147483647),
(1, 100, 2147483647),
(1, 101, 2147483647),
(1, 102, 2147483647),
(1, 103, 2147483647),
(1, 104, 2147483647),
(1, 105, 2147483647),
(1, 106, 2147483647),
(1, 107, 2147483647),
(1, 108, 2147483647),
(1, 109, 2147483647),
(1, 110, 2147483647),
(1, 111, 2147483647),
(1, 112, 2147483647),
(1, 113, 2147483647),
(1, 114, 2147483647),
(1, 115, 2147483647),
(1, 116, 2147483647),
(1, 117, 2147483647),
(1, 118, 2147483647),
(1, 119, 2147483647),
(1, 120, 2147483647),
(1, 121, 2147483647),
(1, 122, 2147483647),
(1, 123, 2147483647),
(2, 2, 2147483647),
(2, 3, 2147483647),
(2, 4, 2147483647),
(2, 6, 2147483647),
(2, 7, 2147483647),
(2, 8, 2147483647),
(2, 9, 2147483647),
(2, 11, 2147483647),
(2, 12, 2147483647),
(2, 13, 2147483647),
(2, 15, 2147483647),
(2, 16, 2147483647),
(2, 17, 2147483647),
(2, 19, 2147483647),
(2, 20, 2147483647),
(2, 21, 2147483647),
(2, 22, 2147483647),
(2, 23, 2147483647),
(2, 24, 2147483647),
(2, 25, 2147483647),
(2, 27, 2147483647),
(2, 28, 2147483647),
(2, 29, 2147483647),
(2, 31, 2147483647),
(2, 32, 2147483647),
(2, 33, 2147483647),
(2, 34, 2147483647),
(2, 36, 2147483647),
(2, 37, 2147483647),
(2, 38, 2147483647),
(2, 40, 2147483647),
(2, 41, 2147483647),
(2, 42, 2147483647),
(2, 43, 2147483647),
(2, 44, 2147483647),
(2, 46, 2147483647),
(2, 47, 2147483647),
(2, 48, 2147483647),
(2, 50, 2147483647),
(2, 51, 2147483647),
(2, 52, 2147483647),
(2, 53, 2147483647),
(2, 55, 2147483647),
(2, 56, 2147483647),
(2, 57, 2147483647),
(2, 59, 2147483647),
(2, 60, 2147483647),
(2, 61, 2147483647),
(2, 62, 2147483647),
(2, 63, 2147483647),
(2, 65, 2147483647),
(2, 66, 2147483647),
(2, 67, 2147483647),
(2, 68, 2147483647),
(2, 70, 2147483647),
(2, 71, 2147483647),
(2, 72, 2147483647),
(2, 73, 2147483647),
(2, 75, 2147483647),
(2, 76, 2147483647),
(2, 77, 2147483647),
(2, 78, 2147483647),
(2, 80, 2147483647),
(2, 81, 2147483647),
(2, 83, 2147483647),
(2, 84, 2147483647),
(2, 85, 2147483647),
(2, 86, 2147483647),
(2, 87, 2147483647),
(2, 88, 2147483647),
(2, 90, 2147483647),
(2, 91, 2147483647),
(2, 92, 2147483647),
(2, 93, 2147483647),
(2, 95, 2147483647),
(2, 96, 2147483647),
(2, 97, 2147483647),
(2, 99, 2147483647),
(2, 100, 2147483647),
(2, 101, 2147483647),
(2, 102, 2147483647),
(2, 104, 2147483647),
(2, 105, 2147483647),
(2, 106, 2147483647),
(2, 107, 2147483647),
(2, 108, 2147483647),
(2, 109, 2147483647),
(2, 110, 2147483647),
(2, 111, 2147483647),
(2, 112, 2147483647),
(2, 113, 2147483647),
(2, 114, 2147483647),
(2, 115, 2147483647),
(2, 116, 2147483647),
(2, 117, 2147483647),
(2, 118, 2147483647),
(2, 119, 2147483647),
(2, 120, 2147483647),
(2, 121, 2147483647),
(2, 122, 2147483647),
(2, 123, 2147483647),
(3, 35, 2147483647),
(3, 37, 2147483647),
(3, 38, 2147483647),
(3, 45, 2147483647),
(3, 47, 2147483647),
(3, 48, 2147483647),
(3, 49, 2147483647),
(3, 52, 2147483647),
(3, 121, 2147483647);

-- --------------------------------------------------------

--
-- Table structure for table `share_prods`
--

CREATE TABLE `share_prods` (
  `share_prod_id` int(11) NOT NULL,
  `comment` varchar(100) DEFAULT NULL,
  `create_at` datetime DEFAULT current_timestamp(),
  `repairs_repair_id` int(6) NOT NULL,
  `prod_stocks_stock_id` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'ขุมทรัพย์', '00', '000', 'adisonsompeng49@gmail.com', NULL, '2025-11-05 14:42:06', '2025-11-05 14:42:19', 2, 'zrgy skqo qvkf dooc', '0808214241');

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
(1, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-19 04:51:05', 100001, NULL),
(2, 'IN', 'repairs', 100001, '2025-11-20 03:48:07', 100002, NULL),
(3, 'OUT', 'bill_headers', 2, '2025-11-20 16:46:13', 100001, NULL),
(4, 'IN', 'order_details', 34, '2025-11-21 03:06:01', 100003, NULL),
(5, 'IN', 'order_details', 34, '2025-11-21 03:06:01', 100004, NULL),
(6, 'IN', 'order_details', 34, '2025-11-21 03:06:01', 100005, NULL),
(7, 'IN', 'order_details', 35, '2025-11-21 03:06:01', 100006, NULL),
(8, 'IN', 'order_details', 35, '2025-11-21 03:06:02', 100007, NULL),
(9, 'IN', 'order_details', 35, '2025-11-21 03:06:02', 100008, NULL),
(10, 'IN', 'order_details', 35, '2025-11-21 03:06:02', 100009, NULL),
(11, 'IN', 'order_details', 35, '2025-11-21 03:06:02', 100010, NULL),
(12, 'IN', 'order_details', 36, '2025-11-21 03:06:02', 100011, NULL),
(13, 'IN', 'order_details', 36, '2025-11-21 03:06:02', 100012, NULL),
(14, 'IN', 'order_details', 36, '2025-11-21 03:06:02', 100013, NULL),
(15, 'OUT', 'bill_headers', 3, '2025-11-21 03:22:59', 100003, NULL),
(16, 'OUT', 'bill_headers', 4, '2025-11-21 03:27:46', 100005, NULL),
(17, 'IN', 'repairs', 100002, '2025-11-21 03:55:40', 100014, NULL),
(18, 'IN', 'order_details', 37, '2025-11-22 23:23:42', 100015, NULL),
(19, 'OUT', 'bill_headers', 6, '2025-11-22 23:27:38', 100008, NULL),
(20, 'OUT', 'bill_repair', 5, '2025-11-22 23:30:43', 100006, NULL),
(21, 'OUT', 'bill_headers', 7, '2025-11-23 18:27:32', 100004, NULL),
(22, 'IN', 'repairs', 100003, '2025-11-23 18:31:31', 100016, NULL),
(23, 'IN', 'repairs', 100004, '2025-11-23 19:13:45', 100017, NULL),
(24, 'IN', 'repairs', 100005, '2025-11-24 19:55:01', 100018, NULL),
(25, 'OUT', 'bill_repair', 10, '2025-11-24 20:14:35', 100015, NULL),
(26, 'OUT', 'bill_repair', 10, '2025-11-24 20:14:39', 100007, NULL),
(27, 'ADJUST', 'bill_repair_remove', 10, '2025-11-24 20:14:43', 100007, NULL),
(28, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:23:20', 100019, NULL),
(29, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:23:39', 100020, NULL),
(30, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:23:39', 100021, NULL),
(31, 'OUT', 'bill_repair', 10, '2025-11-24 20:27:03', 100020, NULL),
(32, 'OUT', 'bill_repair', 10, '2025-11-24 20:27:10', 100019, NULL),
(33, 'ADJUST', 'bill_repair_remove', 10, '2025-11-24 20:27:44', 100015, NULL),
(34, 'OUT', 'repairs_return', 100005, '2025-11-24 20:32:29', 100018, NULL),
(35, 'IN', 'repairs', 100006, '2025-11-24 20:43:24', 100022, NULL),
(36, 'OUT', 'bill_repair', 11, '2025-11-24 20:43:59', 100021, NULL),
(37, 'OUT', 'repairs_return', 100006, '2025-11-24 20:44:34', 100022, NULL),
(38, 'OUT', 'repairs_return', 100006, '2025-11-24 20:45:23', 100022, NULL),
(39, 'OUT', 'repairs_return', 100005, '2025-11-24 20:45:30', 100018, NULL),
(40, 'OUT', 'repairs_return', 100002, '2025-11-24 20:45:56', 100014, NULL),
(41, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:52:47', 100023, NULL),
(42, 'IN', 'repairs', 100007, '2025-11-24 20:53:34', 100024, NULL),
(43, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:57:18', 100025, NULL),
(44, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:57:18', 100026, NULL),
(45, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:57:18', 100027, NULL),
(46, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:57:18', 100028, NULL),
(47, 'IN', 'MANUAL_ENTRY', NULL, '2025-11-24 20:57:18', 100029, NULL),
(48, 'OUT', 'repair_cancelled_return', 100007, '2025-11-24 21:05:25', 100024, NULL),
(49, 'OUT', 'repair_cancelled_return', 100007, '2025-11-24 21:05:32', 100024, NULL),
(50, 'OUT', 'repair_cancelled_return', 100007, '2025-11-24 21:23:08', 100024, NULL),
(51, 'OUT', 'repair_cancelled_return', 100007, '2025-11-24 21:29:53', 100024, NULL),
(52, 'IN', 'repairs', 100008, '2025-11-24 21:57:34', 100030, NULL),
(53, 'OUT', 'deliver_repaired_job', 100008, '2025-11-24 22:09:25', 100030, NULL),
(54, 'OUT', 'deliver_repaired_job', 100008, '2025-11-24 22:09:30', 100030, NULL),
(55, 'IN', 'repairs', 100009, '2025-11-24 22:29:17', 100031, NULL),
(56, 'OUT', 'return_cancelled_device', 100007, '2025-11-24 22:45:33', 100024, NULL),
(57, 'OUT', 'return_cancelled_device', 100008, '2025-11-24 22:45:53', 100030, NULL),
(58, 'OUT', 'return_cancelled_device', 100009, '2025-11-24 22:46:05', 100031, NULL),
(59, 'IN', 'repairs', 100010, '2025-11-25 20:09:08', 100032, NULL),
(60, 'OUT', 'bill_repair', 15, '2025-11-25 20:36:41', 100023, NULL),
(61, 'OUT', 'bill_repair', 15, '2025-11-25 20:36:51', 100029, NULL),
(62, 'OUT', 'repairs_return', 100010, '2025-11-25 20:36:57', 100032, NULL),
(63, 'OUT', 'bill_headers', 16, '2025-11-27 09:11:29', 100011, NULL),
(64, 'OUT', 'bill_headers', 17, '2025-11-27 09:11:44', 100015, NULL),
(65, 'IN', 'repairs', 100011, '2025-11-27 09:12:54', 100033, NULL),
(66, 'OUT', 'bill_headers', 19, '2025-11-27 09:48:35', 100010, NULL),
(67, 'OUT', 'bill_headers', 20, '2025-11-27 09:53:17', 100012, NULL),
(68, 'OUT', 'bill_headers', 18, '2025-11-27 21:58:09', 100013, NULL),
(69, 'ADJUST', 'bill_headers (Cancel)', 17, '2025-11-27 22:12:49', 100015, NULL),
(70, 'IN', 'order_details', 38, '2025-11-29 11:08:20', 100034, NULL),
(71, 'IN', 'order_details', 38, '2025-11-29 11:08:20', 100035, NULL),
(72, 'IN', 'order_details', 38, '2025-11-29 11:08:20', 100036, NULL),
(73, 'IN', 'order_details', 38, '2025-11-29 11:08:20', 100037, NULL),
(74, 'IN', 'order_details', 38, '2025-11-29 11:08:20', 100038, NULL),
(75, 'IN', 'order_details', 39, '2025-11-29 11:08:20', 100039, NULL),
(76, 'IN', 'order_details', 39, '2025-11-29 11:08:20', 100040, NULL),
(77, 'IN', 'order_details', 39, '2025-11-29 11:08:20', 100041, NULL),
(78, 'OUT', 'bill_headers', 21, '2025-11-29 11:11:51', 100036, NULL),
(79, 'IN', 'manual_scan_web', 0, '2025-11-30 14:38:52', 100042, NULL),
(80, 'IN', 'manual_scan_web', 0, '2025-11-30 14:38:52', 100043, NULL),
(81, 'IN', 'manual_scan_web', 0, '2025-11-30 14:38:52', 100044, NULL),
(82, 'IN', 'manual_scan_web', 0, '2025-11-30 14:38:52', 100045, NULL);

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
(100002, 'ดกดด', 'dda', '11112', 1000);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(6) NOT NULL,
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

INSERT INTO `suppliers` (`supplier_id`, `co_name`, `tax_id`, `contact_firstname`, `contact_lastname`, `supplier_email`, `supplier_phone_no`, `create_at`, `update_at`, `prefixs_prefix_id`, `Addresses_address_id`) VALUES
(100001, 'A', NULL, NULL, NULL, NULL, NULL, '2025-11-12 22:58:23', '2025-11-12 22:58:23', NULL, 0),
(100002, 'Advice', NULL, NULL, NULL, NULL, NULL, '2025-11-22 22:37:05', '2025-11-22 22:37:05', NULL, 10);

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

--
-- Dumping data for table `symptoms`
--

INSERT INTO `symptoms` (`symptom_id`, `symptom_name`, `symptom_desc`, `create_at`, `update_at`) VALUES
(100001, 'จอแตก', '', '2025-11-19 01:41:40', '2025-11-19 01:49:44'),
(100002, 'ชาร์จไม่เข้า', '', '2025-11-19 02:48:10', '2025-11-19 02:48:10'),
(100003, 'เปิดไม่ติด', '', '2025-11-19 02:48:45', '2025-11-19 02:48:45'),
(100004, 'จอเป็นเส้น', '', '2025-11-19 02:48:57', '2025-11-19 02:48:57'),
(100005, 'ลำโพงไม่ดัง', '', '2025-11-19 02:49:12', '2025-11-19 02:49:12'),
(100006, 'ลำโพงแตก', '', '2025-11-19 02:49:24', '2025-11-19 02:49:24'),
(100007, 'ปุ่มหลุด', '', '2025-11-19 02:49:36', '2025-11-19 02:49:36'),
(100008, 'ปุ่มเพิ่ม-ลดเสียงเสีย', '', '2025-11-19 02:49:56', '2025-11-19 02:49:56'),
(100009, 'ปุ่ม Power เสีย', '', '2025-11-19 02:50:23', '2025-11-19 02:50:23'),
(100010, 'กล้องไม่ทำงาน', '', '2025-11-19 02:50:37', '2025-11-19 02:50:37');

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
(1, '#198754', '#ffffff', '#000000', 'Prompt', '#198754', '#ffffff', '#198754', '#ffc107', '#dc3545', '#198754', '#dc3545', '#fff3cd', '#dc3545'),
(3, '#198754', '#ff0000', '#000000', 'Sarabun', '#198754', '#ffffff', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, '#198754', '#ffffff', '#000000', 'Sarabun', '#198754', '#ffffff', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

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
(2, 'aa', '$2y$10$NsNomwPRcQFffjcB6an5jOubWw0HUJI5QHCUjC5a4J/Qgg7poxDdK', 'Active', '2025-11-22 22:28:30', '2025-11-23 16:28:25'),
(3, 'as', '$2y$10$wKqBiGIp57ZNL6P.dISfxOeHoh/jq/FhlQ0inbv0LCK7jWtvStliC', 'Active', '2025-11-27 21:36:51', '2025-11-27 21:36:51'),
(4, 'user', '$2y$10$Zx7Syj8d.9.e304vBEM7AOiJyoOYcthLfLnuJEa7hpYDr4W/lBOWK', 'Active', '2025-12-06 00:30:39', '2025-12-06 00:30:39'),
(5, 'user1', '$2y$10$6G04HD79g9q/sB1pKcUct.Dh0J8bzI7/0pDVBT.P8mzeq.icg3p0u', 'Active', '2025-12-06 00:31:12', '2025-12-06 00:31:12'),
(6, 'k', '$2y$10$NqhQUU/hj5yUs5JBQ01RvuCYPJIkLq56EUTttABqeifocV1WM5fEi', 'Active', '2025-12-06 00:33:14', '2025-12-06 00:33:14'),
(7, 'kk', '$2y$10$NWqInqwR2OU2AfXdAPKhUOoNaK31gcpfKNRbITSRHC8I5Ku2xCc2y', 'Active', '2025-12-06 00:37:41', '2025-12-06 00:37:41');

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
(3, 7, '2025-12-06 00:37:41');

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
-- Indexes for table `prodout_types`
--
ALTER TABLE `prodout_types`
  ADD PRIMARY KEY (`outtype_id`);

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
-- Indexes for table `prod_warranty`
--
ALTER TABLE `prod_warranty`
  ADD PRIMARY KEY (`warranty_id`);

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
-- Indexes for table `share_prods`
--
ALTER TABLE `share_prods`
  ADD PRIMARY KEY (`share_prod_id`),
  ADD KEY `repair_id_share_prods` (`repairs_repair_id`),
  ADD KEY `stock_id_share_prods` (`prod_stocks_stock_id`);

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
-- Indexes for table `subdistricts`
--
ALTER TABLE `subdistricts`
  ADD PRIMARY KEY (`subdistrict_id`),
  ADD KEY `district_id_subdistricts` (`districts_district_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `prefix_id_suppliers` (`prefixs_prefix_id`),
  ADD KEY `address_id_suppliers` (`Addresses_address_id`);

--
-- Indexes for table `symptoms`
--
ALTER TABLE `symptoms`
  ADD PRIMARY KEY (`symptom_id`);

--
-- Indexes for table `systemconfig`
--
ALTER TABLE `systemconfig`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`users_user_id`,`roles_role_id`),
  ADD KEY `role_id_user_roles` (`roles_role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bill_details`
--
ALTER TABLE `bill_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `bill_headers`
--
ALTER TABLE `bill_headers`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `repair_status_log`
--
ALTER TABLE `repair_status_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `subdistrict_id_Addresses` FOREIGN KEY (`subdistricts_subdistrict_id`) REFERENCES `subdistricts` (`subdistrict_id`);

--
-- Constraints for table `bill_details`
--
ALTER TABLE `bill_details`
  ADD CONSTRAINT `bill_id_bill_details` FOREIGN KEY (`bill_headers_bill_id`) REFERENCES `bill_headers` (`bill_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prod_id_bill_details` FOREIGN KEY (`products_prod_id`) REFERENCES `products` (`prod_id`),
  ADD CONSTRAINT `stock_id_bill_details` FOREIGN KEY (`prod_stocks_stock_id`) REFERENCES `prod_stocks` (`stock_id`);

--
-- Constraints for table `bill_headers`
--
ALTER TABLE `bill_headers`
  ADD CONSTRAINT `branch_id_bill_headers` FOREIGN KEY (`branches_branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `cs_id_bill_headers` FOREIGN KEY (`customers_cs_id`) REFERENCES `customers` (`cs_id`),
  ADD CONSTRAINT `emp_id_bill_headers` FOREIGN KEY (`employees_emp_id`) REFERENCES `employees` (`emp_id`);

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `address_id_branches` FOREIGN KEY (`Addresses_address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `shop_id_branches` FOREIGN KEY (`shop_info_shop_id`) REFERENCES `shop_info` (`shop_id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `address_id_customers` FOREIGN KEY (`Addresses_address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `prefix_id_customers` FOREIGN KEY (`prefixs_prefix_id`) REFERENCES `prefixs` (`prefix_id`);

--
-- Constraints for table `districts`
--
ALTER TABLE `districts`
  ADD CONSTRAINT `province_id_districts` FOREIGN KEY (`provinces_province_id`) REFERENCES `provinces` (`province_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `address_id_employees` FOREIGN KEY (`Addresses_address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `branch_id_employees` FOREIGN KEY (`branches_branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `dept_id_employees` FOREIGN KEY (`departments_dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `fk_employee_user` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `prefix_id_employees` FOREIGN KEY (`prefixs_prefix_id`) REFERENCES `prefixs` (`prefix_id`),
  ADD CONSTRAINT `religion_id_employees` FOREIGN KEY (`religions_religion_id`) REFERENCES `religions` (`religion_id`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `prod_id_order_details` FOREIGN KEY (`products_prod_id`) REFERENCES `products` (`prod_id`),
  ADD CONSTRAINT `purchase_id_order_details` FOREIGN KEY (`purchase_orders_purchase_id`) REFERENCES `purchase_orders` (`purchase_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_brand_product` FOREIGN KEY (`prod_brands_brand_id`) REFERENCES `prod_brands` (`brand_id`),
  ADD CONSTRAINT `fk_type_product` FOREIGN KEY (`prod_types_type_id`) REFERENCES `prod_types` (`type_id`);

--
-- Constraints for table `prod_stocks`
--
ALTER TABLE `prod_stocks`
  ADD CONSTRAINT `prod_id_prod_stocks` FOREIGN KEY (`products_prod_id`) REFERENCES `products` (`prod_id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `branch_id_purchase_orders` FOREIGN KEY (`branches_branch_id`) REFERENCES `branches` (`branch_id`),
  ADD CONSTRAINT `emp_id_purchase_orders` FOREIGN KEY (`employees_emp_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `supplier_id_purchase_orders` FOREIGN KEY (`suppliers_supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `repairs`
--
ALTER TABLE `repairs`
  ADD CONSTRAINT `bill_id_repairs` FOREIGN KEY (`bill_headers_bill_id`) REFERENCES `bill_headers` (`bill_id`),
  ADD CONSTRAINT `cs_id_repairs` FOREIGN KEY (`customers_cs_id`) REFERENCES `customers` (`cs_id`),
  ADD CONSTRAINT `emp_id_repairs` FOREIGN KEY (`employees_emp_id`) REFERENCES `employees` (`emp_id`),
  ADD CONSTRAINT `fk_repairs_assigned_emp` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_repairs_to_prod_stocks` FOREIGN KEY (`prod_stocks_stock_id`) REFERENCES `prod_stocks` (`stock_id`),
  ADD CONSTRAINT `stock_id_repairs` FOREIGN KEY (`prod_stocks_stock_id`) REFERENCES `prod_stocks` (`stock_id`);

--
-- Constraints for table `repair_status_log`
--
ALTER TABLE `repair_status_log`
  ADD CONSTRAINT `fk_repairlog_employee` FOREIGN KEY (`update_by_employee_id`) REFERENCES `employees` (`emp_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_repairlog_repair` FOREIGN KEY (`repairs_repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `repair_symptoms`
--
ALTER TABLE `repair_symptoms`
  ADD CONSTRAINT `repair_id_repair_symptoms` FOREIGN KEY (`repairs_repair_id`) REFERENCES `repairs` (`repair_id`),
  ADD CONSTRAINT `symptom_id_repair_symptoms` FOREIGN KEY (`symptoms_symptom_id`) REFERENCES `symptoms` (`symptom_id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `permission_id_role_permissions` FOREIGN KEY (`permissions_permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_id_role_permissions` FOREIGN KEY (`roles_role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;

--
-- Constraints for table `share_prods`
--
ALTER TABLE `share_prods`
  ADD CONSTRAINT `repair_id_share_prods` FOREIGN KEY (`repairs_repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_id_share_prods` FOREIGN KEY (`prod_stocks_stock_id`) REFERENCES `prod_stocks` (`stock_id`);

--
-- Constraints for table `shop_info`
--
ALTER TABLE `shop_info`
  ADD CONSTRAINT `address_id_shop_info` FOREIGN KEY (`Addresses_address_id`) REFERENCES `addresses` (`address_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `outtype_id_stock_movements` FOREIGN KEY (`prodout_types_outtype_id`) REFERENCES `prodout_types` (`outtype_id`),
  ADD CONSTRAINT `stock_id_stock_movements` FOREIGN KEY (`prod_stocks_stock_id`) REFERENCES `prod_stocks` (`stock_id`);

--
-- Constraints for table `subdistricts`
--
ALTER TABLE `subdistricts`
  ADD CONSTRAINT `district_id_subdistricts` FOREIGN KEY (`districts_district_id`) REFERENCES `districts` (`district_id`) ON UPDATE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `address_id_suppliers` FOREIGN KEY (`Addresses_address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `prefix_id_suppliers` FOREIGN KEY (`prefixs_prefix_id`) REFERENCES `prefixs` (`prefix_id`);

--
-- Constraints for table `systemconfig`
--
ALTER TABLE `systemconfig`
  ADD CONSTRAINT `user_id_systemconfig` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `role_id_user_roles` FOREIGN KEY (`roles_role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_id_user_roles` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
