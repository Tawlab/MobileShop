<?php
session_start();
require '../config/config.php';

// ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$shop_info = $conn->query("SELECT shop_name FROM shop_info LIMIT 1")->fetch_assoc();
$shop_name_display = $shop_info['shop_name'] ?? 'Mobile Shop';
$uid = $_SESSION['user_id'];
$username = $_SESSION['username'];
$date_now = date('d/m/Y');
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - Mobile Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>

    <style>
        :root {
            --primary-green: #10b981;
            --dark-green: #047857;
            --light-green-bg: #f0fdf4;
            --card-hover-up: -8px;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
        }

        /* --- Header Banner --- */
        .welcome-banner {
            background: linear-gradient(120deg, var(--primary-green), var(--dark-green));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.25);
        }

        .welcome-banner h2 {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-banner i.bg-icon {
            position: absolute;
            right: -20px;
            bottom: -40px;
            font-size: 10rem;
            opacity: 0.15;
            transform: rotate(-15deg);
        }

        /* --- Section Header --- */
        .section-header {
            border-left: 5px solid var(--primary-green);
            padding-left: 15px;
            margin-bottom: 25px;
            margin-top: 10px;
            color: #4b5563;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
        }

        /* --- Menu Cards --- */
        .menu-card {
            background: #fff;
            border: none;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            height: 100%;
            display: block;
            text-decoration: none !important;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .menu-card:hover {
            transform: translateY(var(--card-hover-up));
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.15);
        }

        /* แถบสีด้านบนการ์ด */
        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-green);
            opacity: 0;
            transition: 0.3s;
        }

        .menu-card:hover::before {
            opacity: 1;
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: var(--light-green-bg);
            color: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
            transition: 0.3s;
        }

        .menu-card:hover .icon-box {
            background: var(--primary-green);
            color: #fff;
            transform: rotateY(180deg);
        }

        .card-title {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .card-desc {
            color: #9ca3af;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Small Cards for Sub-menus (General Data) */
        .small-card {
            padding: 15px;
            display: flex;
            align-items: center;
            text-align: left;
        }

        .small-card .icon-box {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
            margin: 0 15px 0 0;
        }

        .small-card:hover .icon-box {
            transform: none;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4 px-lg-5">

                <div class="welcome-banner fade-in">
                    <div class="row align-items-center position-relative" style="z-index: 2;">
                        <div class="col-md-8">
                            <h5 class="text-white-50 mb-1">ยินดีต้อนรับเข้าสู่ระบบจัดการ</h5>
                            <h1 class="fw-bold text-white mb-2"><?= htmlspecialchars($shop_name_display) ?> 📱</h1>
                            <p class="mb-0 text-white-50">สวัสดีคุณ <strong><?= htmlspecialchars($username) ?></strong> 👋 ขอให้วันนี้เป็นวันที่ดีในการทำงาน</p>
                            <div class="mt-3">
                                <span class="badge bg-white text-success rounded-pill px-3 py-2 shadow-sm">
                                    <i class="far fa-calendar-alt me-2"></i> <?= $date_now ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-store bg-icon"></i>
                </div>

                <div class="section-header"><i class="fas fa-star me-2"></i> งานหลัก (Core Operations)</div>
                <div class="row g-4 mb-5">

                    <?php if (hasPermission($conn, $uid, 'menu_dashboard')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../home/dashboard.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-tachometer-alt"></i></div>
                                <h5 class="card-title">แดชบอร์ด</h5>
                                <p class="card-desc">สรุปยอดขายและสถิติภาพรวม</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_sale')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../sales/sale_list.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-cash-register"></i></div>
                                <h5 class="card-title">ขายสินค้า (POS)</h5>
                                <p class="card-desc">ระบบขายหน้าร้าน ออกใบเสร็จ</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_repair')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../repair/repair_list.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-tools"></i></div>
                                <h5 class="card-title">งานซ่อม</h5>
                                <p class="card-desc">รับงานซ่อม อัปเดตสถานะ</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_stock')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../prod_stock/prod_stock.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-boxes"></i></div>
                                <h5 class="card-title">สต็อกสินค้า</h5>
                                <p class="card-desc">ตรวจสอบและจัดการคลังสินค้า</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_purchase')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../purchase/purchase_order.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-truck-loading"></i></div>
                                <h5 class="card-title">รับเข้าสินค้า (PO)</h5>
                                <p class="card-desc">สั่งซื้อและรับของจาก Supplier</p>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (hasPermission($conn, $uid, 'menu_product')): ?>
                    <div class="section-header"><i class="fas fa-mobile-alt me-2"></i> ข้อมูลสินค้า (Product Data)</div>
                    <div class="row g-4 mb-5">

                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../product/product.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-mobile"></i></div>
                                <h5 class="card-title">รายการสินค้า</h5>
                                <p class="card-desc">จัดการรุ่นสินค้าและราคา</p>
                            </a>
                        </div>

                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../prod_type/prodtype.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-tags"></i></div>
                                <h5 class="card-title">ประเภทสินค้า</h5>
                                <p class="card-desc">หมวดหมู่สินค้าต่างๆ</p>
                            </a>
                        </div>

                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../prod_brand/prodbrand.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-copyright"></i></div>
                                <h5 class="card-title">ยี่ห้อสินค้า</h5>
                                <p class="card-desc">แบรนด์สินค้า</p>
                            </a>
                        </div>

                        <?php if (hasPermission($conn, $uid, 'menu_repair')): ?>
                            <div class="col-6 col-md-4 col-xl-3">
                                <a href="../symptom/symptoms.php" class="menu-card">
                                    <div class="icon-box"><i class="fas fa-stethoscope"></i></div>
                                    <h5 class="card-title">อาการเสีย</h5>
                                    <p class="card-desc">ข้อมูลอาการสำหรับงานซ่อม</p>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="section-header"><i class="fas fa-users me-2"></i> บุคคล (People)</div>
                <div class="row g-4 mb-5">

                    <?php if (hasPermission($conn, $uid, 'menu_customer')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../customer/customer_list.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-user-friends"></i></div>
                                <h5 class="card-title">ลูกค้า</h5>
                                <p class="card-desc">ข้อมูลสมาชิกและประวัติ</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_supplier')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../supplier/supplier.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-truck"></i></div>
                                <h5 class="card-title">Suppliers</h5>
                                <p class="card-desc">ข้อมูลคู่ค้า/ตัวแทนจำหน่าย</p>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission($conn, $uid, 'menu_employee')): ?>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../employee/employee.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-user-tie"></i></div>
                                <h5 class="card-title">พนักงาน</h5>
                                <p class="card-desc">บุคลากรในร้าน</p>
                            </a>
                        </div>
                        <div class="col-6 col-md-4 col-xl-3">
                            <a href="../department/department.php" class="menu-card">
                                <div class="icon-box"><i class="fas fa-sitemap"></i></div>
                                <h5 class="card-title">แผนก</h5>
                                <p class="card-desc">โครงสร้างองค์กร</p>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (hasPermission($conn, $uid, 'menu_manage_shop') || hasPermission($conn, $uid, 'menu_manage_users') || hasPermission($conn, $uid, 'menu_general')): ?>

                    <div class="section-header mt-5">
                        <span class="bg-secondary text-white rounded p-1 me-2"><i class="fas fa-cogs"></i></span>
                        ตั้งค่าระบบ (System Config)
                    </div>

                    <div class="row g-3 mb-5">
                        <?php if (hasPermission($conn, $uid, 'manage_shop')): ?>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../shop/shop.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-store"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">ข้อมูลร้าน</h6>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (hasPermission($conn, $uid, 'branch')): ?>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../branch/branch.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-code-branch"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">สาขา</h6>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (hasPermission($conn, $uid, 'menu_manage_users')): ?>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../role/role.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-user-tag"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">บทบาท</h6>
                                </a>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../permission/permission.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-key"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">สิทธิ์ใช้งาน</h6>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (hasPermission($conn, $uid, 'menu_general')): ?>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../provinces/province.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-map-marked-alt"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">จังหวัด</h6>
                                </a>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../districts/districts.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-map"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">อำเภอ</h6>
                                </a>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../subdistricts/subdistricts.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-map-pin"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">ตำบล</h6>
                                </a>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../prename/prename.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-font"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">คำนำหน้า</h6>
                                </a>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <a href="../religion/religion.php" class="menu-card small-card h-100 d-flex align-items-center p-3 border-0 shadow-sm">
                                    <div class="icon-box me-3 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-pray"></i></div>
                                    <h6 class="mb-0 fw-bold text-dark">ศาสนา</h6>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>