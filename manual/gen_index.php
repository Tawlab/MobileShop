<?php
// Generator script - run once then delete
// Writes index.php for the manual folder with proper UTF-8+Thai encoding

$target = __DIR__ . '/index.php';

$php = <<<'PHPEOF'
<?php
session_start();
require "../config/config.php";
if (!isset($_SESSION["user_id"])) { header("Location: ../global/login.php"); exit; }
$shop_info = $conn->query("SELECT shop_name FROM shop_info LIMIT 1")->fetch_assoc();
$sd = htmlspecialchars($shop_info["shop_name"] ?? "Mobile Shop");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>คู่มือการใช้งานระบบ — <?= $sd ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php require "../config/load_theme.php"; ?>
<style>
*{font-family:"Sarabun",sans-serif;}
body{background:#f4f7fb;color:#1f2937;}
.top-bar{background:#fff;border-bottom:1px solid #e5e7eb;padding:14px 32px;display:flex;align-items:center;gap:10px;font-size:.9rem;position:sticky;top:0;z-index:100;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.top-bar a{color:#10b981;text-decoration:none;font-weight:600;}
.top-bar a:hover{text-decoration:underline;}
.top-bar .sep{color:#d1d5db;}
.hero-banner{background:linear-gradient(135deg,#10b981 0%,#047857 55%,#065f46 100%);color:#fff;padding:55px 60px 45px;border-radius:0 0 40px 40px;margin-bottom:50px;position:relative;overflow:hidden;box-shadow:0 10px 40px rgba(16,185,129,.3);}
.hero-banner::before{content:"";position:absolute;top:-60%;right:-5%;width:460px;height:460px;border-radius:50%;background:rgba(255,255,255,.05);}
.hero-banner::after{content:"\f10b";font-family:"Font Awesome 6 Free";font-weight:900;position:absolute;right:60px;bottom:-30px;font-size:10rem;opacity:.08;}
.hero-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:30px;padding:6px 20px;font-size:.85rem;display:inline-block;margin-bottom:15px;backdrop-filter:blur(6px);}
.hero-title{font-size:2.4rem;font-weight:800;margin-bottom:10px;line-height:1.25;}
.hero-sub{font-size:1.05rem;opacity:.85;margin:0;}
.cw{max-width:1200px;margin:0 auto;padding:0 32px 60px;}
.sec-title{font-size:1.05rem;font-weight:700;color:#374151;display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #e5e7eb;}
.sec-dot{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;flex-shrink:0;}
.mc{background:#fff;border-radius:16px;padding:24px 18px;text-align:center;text-decoration:none!important;color:inherit;display:block;height:100%;transition:all .3s cubic-bezier(.25,.8,.25,1);box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.04);position:relative;overflow:hidden;}
.mc::after{content:"";position:absolute;bottom:0;left:0;width:100%;height:3px;transform:scaleX(0);transition:transform .3s ease;}
.mc:hover{transform:translateY(-6px);box-shadow:0 16px 35px rgba(0,0,0,.12);}
.mc:hover::after{transform:scaleX(1);}
.ci{width:62px;height:62px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.55rem;margin:0 auto 16px;transition:transform .3s ease;}
.mc:hover .ci{transform:scale(1.1) rotate(-5deg);}
.cl{font-size:1rem;font-weight:700;color:#111827;margin-bottom:6px;}
.cs{font-size:.8rem;color:#9ca3af;line-height:1.5;}
.ca{margin-top:14px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;padding:4px 14px;border-radius:20px;transition:all .2s;}
/* colour themes */
.tg .ci{background:#ecfdf5;color:#10b981;}.tg .mc::after{background:#10b981;}.tg .ca{background:#ecfdf5;color:#10b981;}.tg .mc:hover .ca{background:#10b981;color:#fff;}
.tb .ci{background:#eff6ff;color:#3b82f6;}.tb .mc::after{background:#3b82f6;}.tb .ca{background:#eff6ff;color:#3b82f6;}.tb .mc:hover .ca{background:#3b82f6;color:#fff;}
.tp .ci{background:#f5f3ff;color:#8b5cf6;}.tp .mc::after{background:#8b5cf6;}.tp .ca{background:#f5f3ff;color:#8b5cf6;}.tp .mc:hover .ca{background:#8b5cf6;color:#fff;}
.to .ci{background:#fff7ed;color:#f97316;}.to .mc::after{background:#f97316;}.to .ca{background:#fff7ed;color:#f97316;}.to .mc:hover .ca{background:#f97316;color:#fff;}
.tr .ci{background:#fef2f2;color:#ef4444;}.tr .mc::after{background:#ef4444;}.tr .ca{background:#fef2f2;color:#ef4444;}.tr .mc:hover .ca{background:#ef4444;color:#fff;}
.tt .ci{background:#f0fdfa;color:#14b8a6;}.tt .mc::after{background:#14b8a6;}.tt .ca{background:#f0fdfa;color:#14b8a6;}.tt .mc:hover .ca{background:#14b8a6;color:#fff;}
.ti .ci{background:#eef2ff;color:#6366f1;}.ti .mc::after{background:#6366f1;}.ti .ca{background:#eef2ff;color:#6366f1;}.ti .mc:hover .ca{background:#6366f1;color:#fff;}
.tpk .ci{background:#fdf2f8;color:#ec4899;}.tpk .mc::after{background:#ec4899;}.tpk .ca{background:#fdf2f8;color:#ec4899;}.tpk .mc:hover .ca{background:#ec4899;color:#fff;}
.ty .ci{background:#fefce8;color:#eab308;}.ty .mc::after{background:#eab308;}.ty .ca{background:#fefce8;color:#eab308;}.ty .mc:hover .ca{background:#eab308;color:#fff;}
.tgy .ci{background:#f9fafb;color:#6b7280;}.tgy .mc::after{background:#6b7280;}.tgy .ca{background:#f9fafb;color:#6b7280;}.tgy .mc:hover .ca{background:#6b7280;color:#fff;}
.sb{margin-bottom:50px;}
</style>
</head>
<body>
<!-- แถบนำทาง -->
<div class="top-bar">
  <a href="../global/home.php"><i class="fas fa-home me-1"></i>หน้าหลัก</a>
  <span class="sep">›</span>
  <span class="text-muted fw-semibold">📖 คู่มือการใช้งานระบบ</span>
</div>

<!-- ส่วนหัวหน้า -->
<div class="hero-banner">
  <div class="cw" style="padding-bottom:0;">
    <div class="hero-badge"><i class="fas fa-book-open me-2"></i>เอกสารอ้างอิงระบบ</div>
    <h1 class="hero-title">📱 คู่มือการใช้งานระบบ<br><?= $sd ?></h1>
    <p class="hero-sub">รวมคำแนะนำการใช้งานทุกฟังก์ชันในระบบ เพียงคลิกที่หัวข้อที่ต้องการ</p>
  </div>
</div>

<div class="cw">
<!-- 1. งานหลัก -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#10b981;"><i class="fas fa-star"></i></span>
    งานหลัก — ปฏิบัติงานประจำวัน
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tg"><a href="pages/dashboard.php" class="mc"><div class="ci"><i class="fas fa-tachometer-alt"></i></div><div class="cl">แดชบอร์ด</div><div class="cs">ดูภาพรวมยอดขาย ยอดซ่อม และสถิติสำคัญ</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tg"><a href="pages/sales.php" class="mc"><div class="ci"><i class="fas fa-cash-register"></i></div><div class="cl">การขายสินค้า (POS)</div><div class="cs">บันทึกบิลขาย รับชำระเงิน ออกใบเสร็จรับเงิน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 to"><a href="pages/repair.php" class="mc"><div class="ci"><i class="fas fa-tools"></i></div><div class="cl">การซ่อมโทรศัพท์</div><div class="cs">รับงานซ่อม อัปเดตสถานะ ออกใบเสร็จค่าซ่อม</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tb"><a href="pages/stock.php" class="mc"><div class="ci"><i class="fas fa-boxes"></i></div><div class="cl">สต็อคสินค้า</div><div class="cs">จัดการคลังสินค้า ตรวจจำนวน พิมพ์บาร์โค้ด</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tt"><a href="pages/purchase.php" class="mc"><div class="ci"><i class="fas fa-truck-loading"></i></div><div class="cl">รับเข้าสินค้า (PO)</div><div class="cs">สร้างใบสั่งซื้อ รับสินค้า อัปเดตสต็อคอัตโนมัติ</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 2. ข้อมูลสินค้า -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#3b82f6;"><i class="fas fa-mobile-alt"></i></span>
    ข้อมูลสินค้า — จัดการรายการและหมวดหมู่
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tb"><a href="pages/product.php" class="mc"><div class="ci"><i class="fas fa-mobile"></i></div><div class="cl">รายการสินค้า</div><div class="cs">จัดการรุ่น ราคา และรายละเอียดสินค้า</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tp"><a href="pages/prod_type.php" class="mc"><div class="ci"><i class="fas fa-tags"></i></div><div class="cl">ประเภทสินค้า</div><div class="cs">แบ่งหมวดหมู่ เช่น สมาร์ทโฟน แท็บเล็ต อุปกรณ์เสริม</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 ti"><a href="pages/prod_brand.php" class="mc"><div class="ci"><i class="fas fa-copyright"></i></div><div class="cl">ยี่ห้อสินค้า</div><div class="cs">จัดการแบรนด์ Samsung, Apple, Xiaomi ฯลฯ</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 to"><a href="pages/symptom.php" class="mc"><div class="ci"><i class="fas fa-stethoscope"></i></div><div class="cl">อาการเสีย</div><div class="cs">รายการอาการเสียสำหรับบันทึกงานซ่อม</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 3. ข้อมูลบุคคล -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#8b5cf6;"><i class="fas fa-users"></i></span>
    ข้อมูลบุคคล — ลูกค้า พนักงาน และผู้จำหน่าย
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tp"><a href="pages/customer.php" class="mc"><div class="ci"><i class="fas fa-user-friends"></i></div><div class="cl">ลูกค้า</div><div class="cs">บันทึกข้อมูล ดูประวัติการซื้อและการซ่อม</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tt"><a href="pages/supplier.php" class="mc"><div class="ci"><i class="fas fa-truck"></i></div><div class="cl">ผู้จำหน่าย</div><div class="cs">จัดการข้อมูลคู่ค้าและตัวแทนจำหน่าย</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 ti"><a href="pages/employee.php" class="mc"><div class="ci"><i class="fas fa-user-tie"></i></div><div class="cl">พนักงาน</div><div class="cs">บันทึกข้อมูลและจัดการบุคลากรในร้าน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tpk"><a href="pages/department.php" class="mc"><div class="ci"><i class="fas fa-sitemap"></i></div><div class="cl">แผนก</div><div class="cs">กำหนดโครงสร้างแผนกขององค์กร</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 4. รายงาน -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#f97316;"><i class="fas fa-chart-bar"></i></span>
    รายงาน — สรุปผลและวิเคราะห์ข้อมูล
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tg"><a href="pages/report_sales.php" class="mc"><div class="ci"><i class="fas fa-chart-line"></i></div><div class="cl">รายงานยอดขาย</div><div class="cs">สรุปยอดขายตามช่วงเวลา พนักงาน และยี่ห้อสินค้า</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 to"><a href="pages/report_repairs.php" class="mc"><div class="ci"><i class="fas fa-chart-pie"></i></div><div class="cl">รายงานยอดซ่อม</div><div class="cs">สรุปงานซ่อมตามช่วงเวลา ช่าง และยี่ห้อสินค้า</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 5. ตั้งค่าระบบ -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#6b7280;"><i class="fas fa-cogs"></i></span>
    ตั้งค่าระบบ — ร้านค้า ผู้ใช้งาน และสิทธิ์
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tg"><a href="pages/shop.php" class="mc"><div class="ci"><i class="fas fa-store"></i></div><div class="cl">ข้อมูลร้านค้า</div><div class="cs">แก้ไขชื่อร้าน ที่อยู่ เบอร์โทร และโลโก้</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tb"><a href="pages/branch.php" class="mc"><div class="ci"><i class="fas fa-code-branch"></i></div><div class="cl">สาขา</div><div class="cs">จัดการสาขาที่ให้บริการทั้งหมด</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tr"><a href="pages/users.php" class="mc"><div class="ci"><i class="fas fa-user-shield"></i></div><div class="cl">จัดการผู้ใช้งาน</div><div class="cs">เพิ่ม/แก้ไขบัญชีและรหัสผ่านผู้ใช้งาน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tr"><a href="pages/role.php" class="mc"><div class="ci"><i class="fas fa-user-tag"></i></div><div class="cl">บทบาทผู้ใช้งาน</div><div class="cs">กำหนด Role เช่น Admin, พนักงานขาย, ช่างซ่อม</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tr"><a href="pages/permission.php" class="mc"><div class="ci"><i class="fas fa-key"></i></div><div class="cl">สิทธิ์การใช้งาน</div><div class="cs">กำหนดสิทธิ์ว่า Role ใดเข้าถึงเมนูใดได้บ้าง</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tgy"><a href="pages/system_config.php" class="mc"><div class="ci"><i class="fas fa-palette"></i></div><div class="cl">ตั้งค่าธีมสี</div><div class="cs">ปรับแต่งธีมสีของระบบตามความชอบ</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tgy"><a href="pages/profile.php" class="mc"><div class="ci"><i class="fas fa-user-circle"></i></div><div class="cl">ข้อมูลส่วนตัว</div><div class="cs">แก้ไขโปรไฟล์และเปลี่ยนรหัสผ่านของตนเอง</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 6. ข้อมูลทั่วไป -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#eab308;"><i class="fas fa-globe"></i></span>
    ข้อมูลทั่วไป — ข้อมูลพื้นฐานสนับสนุนระบบ
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 ty"><a href="pages/prename.php" class="mc"><div class="ci"><i class="fas fa-font"></i></div><div class="cl">คำนำหน้านาม</div><div class="cs">จัดการคำนำหน้า เช่น นาย นาง นางสาว</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 ty"><a href="pages/religion.php" class="mc"><div class="ci"><i class="fas fa-pray"></i></div><div class="cl">ศาสนา</div><div class="cs">รายชื่อศาสนาสำหรับบันทึกข้อมูลพนักงาน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tg"><a href="pages/provinces.php" class="mc"><div class="ci"><i class="fas fa-map-marked-alt"></i></div><div class="cl">จังหวัด / อำเภอ / ตำบล</div><div class="cs">ข้อมูลพื้นที่สำหรับกรอกที่อยู่ในระบบ</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- 7. การเข้าสู่ระบบ -->
<div class="sb">
  <div class="sec-title">
    <span class="sec-dot" style="background:#14b8a6;"><i class="fas fa-sign-in-alt"></i></span>
    การเข้าสู่ระบบ — ล็อคอิน สมัคร และรีเซ็ตรหัสผ่าน
  </div>
  <div class="row g-4">
    <div class="col-6 col-md-4 col-xl-3 tt"><a href="pages/login.php" class="mc"><div class="ci"><i class="fas fa-sign-in-alt"></i></div><div class="cl">เข้าสู่ระบบ</div><div class="cs">วิธีล็อคอินด้วยชื่อผู้ใช้และรหัสผ่าน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tt"><a href="pages/register.php" class="mc"><div class="ci"><i class="fas fa-user-plus"></i></div><div class="cl">สมัครใช้งาน</div><div class="cs">ขั้นตอนสมัครบัญชีใหม่สำหรับเจ้าของร้าน</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
    <div class="col-6 col-md-4 col-xl-3 tt"><a href="pages/forgot_password.php" class="mc"><div class="ci"><i class="fas fa-unlock-alt"></i></div><div class="cl">ลืมรหัสผ่าน</div><div class="cs">วิธีรีเซ็ตรหัสผ่านผ่าน OTP หรืออีเมล</div><span class="ca"><i class="fas fa-book-open"></i> ดูคู่มือ</span></a></div>
  </div>
</div>

<!-- Footer -->
<div class="text-center pt-4 pb-2" style="border-top:1px solid #e5e7eb;">
  <small class="text-muted"><i class="fas fa-info-circle me-1"></i>คู่มือระบบ <?= $sd ?> &nbsp;|&nbsp; อัปเดต: <?= date("d/m/Y") ?></small>
</div>
</div><!-- /cw -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
PHPEOF;

file_put_contents($target, $php);
echo "Written " . filesize($target) . " bytes to index.php\n";
