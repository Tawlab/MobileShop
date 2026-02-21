$content = @'
<?php
session_start();
require "../config/config.php";
if (!isset($_SESSION["user_id"])) { header("Location: ../global/login.php"); exit; }
$shop_info = $conn->query("SELECT shop_name FROM shop_info LIMIT 1")->fetch_assoc();
$shop_name_display = $shop_info["shop_name"] ?? "Mobile Shop";
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>คู่มือการใช้งานระบบ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php require "../config/load_theme.php"; ?>
<style>
*{font-family:"Sarabun",sans-serif;}
body{background:#f4f7fb;color:#1f2937;}
.top-bar{background:#fff;border-bottom:1px solid #e5e7eb;padding:14px 40px;display:flex;align-items:center;gap:10px;font-size:.9rem;}
.top-bar a{color:#10b981;text-decoration:none;font-weight:600;}
.top-bar a:hover{text-decoration:underline;}
.top-bar .sep{color:#d1d5db;}
.hero-banner{background:linear-gradient(135deg,#10b981 0%,#047857 50%,#065f46 100%);color:#fff;padding:60px 60px 50px;border-radius:0 0 40px 40px;margin-bottom:50px;position:relative;overflow:hidden;box-shadow:0 10px 40px rgba(16,185,129,.3);}
.hero-banner::before{content:"";position:absolute;top:-50%;right:-10%;width:400px;height:400px;border-radius:50%;background:rgba(255,255,255,.05);}
.hero-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:30px;padding:6px 20px;font-size:.85rem;display:inline-block;margin-bottom:15px;}
.hero-title{font-size:2.2rem;font-weight:800;margin-bottom:10px;line-height:1.2;}
.content-wrapper{max-width:1200px;margin:0 auto;padding:0 30px 60px;}
.section-title{font-size:1.05rem;font-weight:700;color:#374151;display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #e5e7eb;}
.section-title .icon-dot{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#fff;}
.module-card{background:#fff;border-radius:16px;padding:24px 20px;text-align:center;text-decoration:none!important;color:inherit;display:block;height:100%;transition:all .3s cubic-bezier(.25,.8,.25,1);box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.04);position:relative;overflow:hidden;}
.module-card::after{content:"";position:absolute;bottom:0;left:0;width:100%;height:3px;transform:scaleX(0);transition:transform .3s ease;}
.module-card:hover{transform:translateY(-6px);box-shadow:0 16px 35px rgba(0,0,0,.12);}
.module-card:hover::after{transform:scaleX(1);}
.card-icon{width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 16px;transition:transform .3s ease;}
.module-card:hover .card-icon{transform:scale(1.1) rotate(-5deg);}
.card-label{font-size:1rem;font-weight:700;color:#111827;margin-bottom:6px;}
.card-sub{font-size:.82rem;color:#9ca3af;line-height:1.5;}
.card-arrow{margin-top:14px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;padding:4px 14px;border-radius:20px;transition:all .2s;}
.section-block{margin-bottom:50px;}
.g-green .card-icon{background:#ecfdf5;color:#10b981;}.g-green .module-card::after{background:#10b981;}.g-green .card-arrow{background:#ecfdf5;color:#10b981;}.g-green .module-card:hover .card-arrow{background:#10b981;color:#fff;}
.g-blue .card-icon{background:#eff6ff;color:#3b82f6;}.g-blue .module-card::after{background:#3b82f6;}.g-blue .card-arrow{background:#eff6ff;color:#3b82f6;}.g-blue .module-card:hover .card-arrow{background:#3b82f6;color:#fff;}
.g-purple .card-icon{background:#f5f3ff;color:#8b5cf6;}.g-purple .module-card::after{background:#8b5cf6;}.g-purple .card-arrow{background:#f5f3ff;color:#8b5cf6;}.g-purple .module-card:hover .card-arrow{background:#8b5cf6;color:#fff;}
.g-orange .card-icon{background:#fff7ed;color:#f97316;}.g-orange .module-card::after{background:#f97316;}.g-orange .card-arrow{background:#fff7ed;color:#f97316;}.g-orange .module-card:hover .card-arrow{background:#f97316;color:#fff;}
.g-red .card-icon{background:#fef2f2;color:#ef4444;}.g-red .module-card::after{background:#ef4444;}.g-red .card-arrow{background:#fef2f2;color:#ef4444;}.g-red .module-card:hover .card-arrow{background:#ef4444;color:#fff;}
.g-teal .card-icon{background:#f0fdfa;color:#14b8a6;}.g-teal .module-card::after{background:#14b8a6;}.g-teal .card-arrow{background:#f0fdfa;color:#14b8a6;}.g-teal .module-card:hover .card-arrow{background:#14b8a6;color:#fff;}
.g-indigo .card-icon{background:#eef2ff;color:#6366f1;}.g-indigo .module-card::after{background:#6366f1;}.g-indigo .card-arrow{background:#eef2ff;color:#6366f1;}.g-indigo .module-card:hover .card-arrow{background:#6366f1;color:#fff;}
.g-pink .card-icon{background:#fdf2f8;color:#ec4899;}.g-pink .module-card::after{background:#ec4899;}.g-pink .card-arrow{background:#fdf2f8;color:#ec4899;}.g-pink .module-card:hover .card-arrow{background:#ec4899;color:#fff;}
.g-yellow .card-icon{background:#fefce8;color:#eab308;}.g-yellow .module-card::after{background:#eab308;}.g-yellow .card-arrow{background:#fefce8;color:#eab308;}.g-yellow .module-card:hover .card-arrow{background:#eab308;color:#fff;}
.g-gray .card-icon{background:#f9fafb;color:#6b7280;}.g-gray .module-card::after{background:#6b7280;}.g-gray .card-arrow{background:#f9fafb;color:#6b7280;}.g-gray .module-card:hover .card-arrow{background:#6b7280;color:#fff;}
</style>
</head>
<body>
<div class="top-bar">
  <a href="../global/home.php"><i class="fas fa-home me-1"></i> หน้าหลัก</a>
  <span class="sep">&rsaquo;</span>
  <span class="text-muted">คู่มือการใช้งานระบบ</span>
</div>
<div class="hero-banner">
  <div class="content-wrapper" style="padding-bottom:0;">
    <div class="hero-badge"><i class="fas fa-book-open me-2"></i>เอกสารคู่มือระบบ</div>
    <h1 class="hero-title">📱 คู่มือการใช้งานระบบ<br><?= htmlspecialchars($shop_name_display) ?></h1>
    <p style="font-size:1.05rem;opacity:.85;">รวมคำแนะนำการใช้งานฟังก์ชันต่างๆ ในระบบจัดการร้านค้าโทรศัพท์มือถือ<br>คลิกหัวข้อที่ต้องการเพื่ออ่านคำแนะนำโดยละเอียด</p>
  </div>
</div>
<div class="content-wrapper">

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#10b981;"><i class="fas fa-star"></i></span>
    งานหลัก — การดำเนินงานประจำวัน
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-green"><a href="pages/dashboard.php" class="module-card"><div class="card-icon"><i class="fas fa-tachometer-alt"></i></div><div class="card-label">แดชบอร์ด</div><div class="card-sub">ภาพรวมยอดขาย ยอดซ่อม และสถิติสำคัญของร้านค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-green"><a href="pages/sales.php" class="module-card"><div class="card-icon"><i class="fas fa-cash-register"></i></div><div class="card-label">การขายสินค้า (POS)</div><div class="card-sub">ระบบขายหน้าร้าน บันทึกบิล รับชำระเงิน ออกใบเสร็จรับเงิน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-orange"><a href="pages/repair.php" class="module-card"><div class="card-icon"><i class="fas fa-tools"></i></div><div class="card-label">การซ่อมโทรศัพท์</div><div class="card-sub">รับงานซ่อม บันทึกอาการเสีย ติดตามสถานะ ออกใบเสร็จซ่อม</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-blue"><a href="pages/stock.php" class="module-card"><div class="card-icon"><i class="fas fa-boxes"></i></div><div class="card-label">สต็อคสินค้า</div><div class="card-sub">ตรวจสอบจำนวนสินค้าคงเหลือ จัดการคลังสินค้าและบาร์โค้ด</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-teal"><a href="pages/purchase.php" class="module-card"><div class="card-icon"><i class="fas fa-truck-loading"></i></div><div class="card-label">การรับเข้าสินค้า (PO)</div><div class="card-sub">สร้างใบสั่งซื้อ รับสินค้าจากผู้จำหน่าย อัปเดตสต็อคอัตโนมัติ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#3b82f6;"><i class="fas fa-mobile-alt"></i></span>
    ข้อมูลสินค้า — จัดการรายการและหมวดหมู่สินค้า
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-blue"><a href="pages/product.php" class="module-card"><div class="card-icon"><i class="fas fa-mobile"></i></div><div class="card-label">รายการสินค้า</div><div class="card-sub">จัดการข้อมูลโทรศัพท์ รุ่น ราคา และรายละเอียดสินค้าทั้งหมด</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-purple"><a href="pages/prod_type.php" class="module-card"><div class="card-icon"><i class="fas fa-tags"></i></div><div class="card-label">ประเภทสินค้า</div><div class="card-sub">กำหนดหมวดหมู่ของสินค้า เช่น โทรศัพท์ แท็บเล็ต อุปกรณ์เสริม</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-indigo"><a href="pages/prod_brand.php" class="module-card"><div class="card-icon"><i class="fas fa-copyright"></i></div><div class="card-label">ยี่ห้อสินค้า</div><div class="card-sub">จัดการแบรนด์สินค้า เช่น Samsung, Apple, Xiaomi, OPPO</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-orange"><a href="pages/symptom.php" class="module-card"><div class="card-icon"><i class="fas fa-stethoscope"></i></div><div class="card-label">อาการเสีย</div><div class="card-sub">รายการอาการเสียสำหรับใช้ในระบบงานซ่อม เพิ่ม/แก้ไขอาการ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#8b5cf6;"><i class="fas fa-users"></i></span>
    ข้อมูลบุคคล — ลูกค้า พนักงาน และผู้จำหน่าย
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-purple"><a href="pages/customer.php" class="module-card"><div class="card-icon"><i class="fas fa-user-friends"></i></div><div class="card-label">ลูกค้า</div><div class="card-sub">บันทึกข้อมูลสมาชิก ประวัติการซื้อ และการซ่อมของลูกค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-teal"><a href="pages/supplier.php" class="module-card"><div class="card-icon"><i class="fas fa-truck"></i></div><div class="card-label">ผู้จำหน่าย (Supplier)</div><div class="card-sub">จัดการข้อมูลคู่ค้า ตัวแทนจำหน่าย และผู้ส่งสินค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-indigo"><a href="pages/employee.php" class="module-card"><div class="card-icon"><i class="fas fa-user-tie"></i></div><div class="card-label">พนักงาน</div><div class="card-sub">บันทึกข้อมูลพนักงาน เพิ่ม/แก้ไข/ปิดใช้งานบัญชีพนักงาน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-pink"><a href="pages/department.php" class="module-card"><div class="card-icon"><i class="fas fa-sitemap"></i></div><div class="card-label">แผนก</div><div class="card-sub">กำหนดโครงสร้างแผนกในองค์กร รองรับการจัดสรรพนักงาน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#f97316;"><i class="fas fa-chart-bar"></i></span>
    รายงาน — สรุปผลและวิเคราะห์ข้อมูล
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-green"><a href="pages/report_sales.php" class="module-card"><div class="card-icon"><i class="fas fa-chart-line"></i></div><div class="card-label">รายงานยอดขาย</div><div class="card-sub">ดูสรุปยอดขายตามช่วงเวลา พนักงาน ยี่ห้อ และประเภทสินค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-orange"><a href="pages/report_repairs.php" class="module-card"><div class="card-icon"><i class="fas fa-chart-pie"></i></div><div class="card-label">รายงานยอดซ่อม</div><div class="card-sub">ดูสรุปงานซ่อมตามช่วงเวลา พนักงาน ยี่ห้อ และประเภทสินค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#6b7280;"><i class="fas fa-cogs"></i></span>
    ตั้งค่าระบบ — ข้อมูลร้านและการจัดการผู้ใช้
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-green"><a href="pages/shop.php" class="module-card"><div class="card-icon"><i class="fas fa-store"></i></div><div class="card-label">ข้อมูลร้านค้า</div><div class="card-sub">แก้ไขชื่อร้าน ที่อยู่ เบอร์โทร และข้อมูลทั่วไปของร้านค้า</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-blue"><a href="pages/branch.php" class="module-card"><div class="card-icon"><i class="fas fa-code-branch"></i></div><div class="card-label">สาขา</div><div class="card-sub">จัดการสาขาของร้านค้า เพิ่ม/แก้ไข/ลบสาขาที่ให้บริการ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-red"><a href="pages/users.php" class="module-card"><div class="card-icon"><i class="fas fa-user-shield"></i></div><div class="card-label">จัดการผู้ใช้งาน</div><div class="card-sub">เพิ่ม/แก้ไขบัญชีผู้ใช้งาน รหัสผ่าน และข้อมูลในระบบ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-red"><a href="pages/role.php" class="module-card"><div class="card-icon"><i class="fas fa-user-tag"></i></div><div class="card-label">บทบาทผู้ใช้งาน</div><div class="card-sub">กำหนด Role เช่น ผู้ดูแลระบบ พนักงานขาย ช่างซ่อม ฯลฯ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-red"><a href="pages/permission.php" class="module-card"><div class="card-icon"><i class="fas fa-key"></i></div><div class="card-label">สิทธิ์การใช้งาน</div><div class="card-sub">กำหนดว่า Role ใดเข้าถึงเมนูและฟังก์ชันใดได้บ้าง</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-gray"><a href="pages/system_config.php" class="module-card"><div class="card-icon"><i class="fas fa-palette"></i></div><div class="card-label">ตั้งค่าธีม</div><div class="card-sub">ปรับแต่งธีมสีของระบบให้เหมาะกับความชอบของผู้ใช้งาน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-gray"><a href="pages/profile.php" class="module-card"><div class="card-icon"><i class="fas fa-user-circle"></i></div><div class="card-label">ข้อมูลส่วนตัว</div><div class="card-sub">แก้ไขข้อมูลส่วนตัว รูปโปรไฟล์ และเปลี่ยนรหัสผ่านของตนเอง</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#eab308;"><i class="fas fa-globe"></i></span>
    ข้อมูลทั่วไป — ข้อมูลสนับสนุนระบบ
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-yellow"><a href="pages/prename.php" class="module-card"><div class="card-icon"><i class="fas fa-font"></i></div><div class="card-label">คำนำหน้านาม</div><div class="card-sub">จัดการคำนำหน้าชื่อ เช่น นาย นาง นางสาว และอื่นๆ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-yellow"><a href="pages/religion.php" class="module-card"><div class="card-icon"><i class="fas fa-pray"></i></div><div class="card-label">ศาสนา</div><div class="card-sub">จัดการข้อมูลศาสนาสำหรับใช้ในการบันทึกข้อมูลพนักงาน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-green"><a href="pages/provinces.php" class="module-card"><div class="card-icon"><i class="fas fa-map-marked-alt"></i></div><div class="card-label">จังหวัด / อำเภอ / ตำบล</div><div class="card-sub">จัดการข้อมูลพื้นที่สำหรับกรอกที่อยู่ในระบบ</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="section-block">
  <div class="section-title">
    <span class="icon-dot" style="background:#14b8a6;"><i class="fas fa-sign-in-alt"></i></span>
    การเข้าใช้งานระบบ — ล็อคอิน สมัคร และรหัสผ่าน
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 g-teal"><a href="pages/login.php" class="module-card"><div class="card-icon"><i class="fas fa-sign-in-alt"></i></div><div class="card-label">เข้าสู่ระบบ</div><div class="card-sub">วิธีเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-teal"><a href="pages/register.php" class="module-card"><div class="card-icon"><i class="fas fa-user-plus"></i></div><div class="card-label">สมัครใช้งาน</div><div class="card-sub">ขั้นตอนการสมัครบัญชีใหม่สำหรับเจ้าของร้าน</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 g-teal"><a href="pages/forgot_password.php" class="module-card"><div class="card-icon"><i class="fas fa-unlock-alt"></i></div><div class="card-label">ลืมรหัสผ่าน</div><div class="card-sub">ขั้นตอนการรีเซ็ตรหัสผ่านผ่านอีเมลหรือ OTP</div><span class="card-arrow"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<div class="text-center pt-4 pb-2" style="border-top:1px solid #e5e7eb;">
  <small class="text-muted"><i class="fas fa-info-circle me-1"></i> คู่มือนี้จัดทำไว้สำหรับระบบ <?= htmlspecialchars($shop_name_display) ?> &nbsp;|&nbsp; อัปเดตล่าสุด: <?= date("d/m/Y") ?></small>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
'@

[IO.File]::WriteAllText("e:\xampp\htdocs\MobileShop\manual\index.php", $content, [Text.Encoding]::UTF8)
Write-Host "Done: $(Get-Item 'e:\xampp\htdocs\MobileShop\manual\index.php').Length bytes"
