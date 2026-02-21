<?php
// ─── Auth ────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../config/config.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: ../global/login.php"); exit;
}
$q = $conn->query("SELECT * FROM shop_info LIMIT 1");
$info = $q ? $q->fetch_assoc() : [];
$shop = htmlspecialchars($info['shop_name'] ?? 'Mobile Shop');
$addr = htmlspecialchars($info['shop_address'] ?? '');

// ─── Override layout functions BEFORE requiring detail pages ─
// (detail pages do: require '_layout.php'; layout_head(…); …HTML…; layout_foot();)
// Since layout_head is already defined here, _layout.php skips its definition block.
function layout_head(string $title, string $icon, string $color, string $subtitle = '') {
    $t = htmlspecialchars($title);
    $ic = htmlspecialchars($icon);
    $c = htmlspecialchars($color);
    $sub = $subtitle ? '<p class="ch-sub">'.htmlspecialchars($subtitle).'</p>' : '';
    echo <<<HTML
<div class="chapter" style="border-top:3px solid {$c};">
  <div class="ch-head" style="color:{$c};">
    <i class="fas fa-{$ic}"></i>
    <div>
      <div class="ch-title">{$t}</div>
      {$sub}
    </div>
  </div>
HTML;
}
function layout_foot()   { echo '</div><!-- /chapter -->'; }
function adjustColor($h) { return $h; }
function adjustColorLight($h) { return '#f8f8f8'; }

require '../config/load_theme.php';
$today = date('d/m/Y');
$year  = date('Y');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>คู่มือการใช้งานระบบ — <?= $shop ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ─── Base ──────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;}
body{font-family:'Sarabun',sans-serif;background:#fff;color:#1e293b;margin:0;}

/* ─── Screen: print-all bar ─────────────────────────── */
.screen-bar{
  position:fixed;top:0;left:0;right:0;z-index:999;
  background:#10b981;color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 28px;box-shadow:0 2px 10px rgba(0,0,0,.15);
}
.screen-bar h2{margin:0;font-size:1rem;font-weight:700;}
.screen-bar .btn-go{
  background:#fff;color:#10b981;border:none;border-radius:8px;
  padding:7px 20px;font-weight:700;font-size:.88rem;font-family:'Sarabun',sans-serif;
  cursor:pointer;transition:transform .15s;
}
.screen-bar .btn-go:hover{transform:translateY(-1px);}
.screen-bar .btn-back{
  color:rgba(255,255,255,.85);text-decoration:none;font-size:.85rem;
  display:flex;align-items:center;gap:6px;
}
.screen-bar .btn-back:hover{color:#fff;}

.print-wrapper{
  max-width:900px;margin:72px auto 60px;padding:0 20px;
}

/* ─── Cover Page ─────────────────────────────────────── */
.cover-page{
  background:linear-gradient(160deg,#059669 0%,#047857 40%,#065f46 100%);
  color:#fff;
  border-radius:20px;
  padding:80px 60px;
  text-align:center;
  position:relative;overflow:hidden;
  margin-bottom:48px;
  min-height:500px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  box-shadow:0 8px 40px rgba(5,150,105,.25);
}
.cover-page::before{
  content:'';position:absolute;right:-60px;top:-60px;
  width:320px;height:320px;border-radius:50%;
  background:rgba(255,255,255,.07);
}
.cover-page::after{
  content:'';position:absolute;left:-40px;bottom:-40px;
  width:220px;height:220px;border-radius:50%;
  background:rgba(255,255,255,.05);
}
.cover-icon{
  width:100px;height:100px;
  border-radius:28px;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);
  display:flex;align-items:center;justify-content:center;font-size:3.2rem;
  margin:0 auto 28px;position:relative;z-index:1;
  box-shadow:0 4px 20px rgba(0,0,0,.15);
}
.cover-badge{
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
  border-radius:30px;padding:5px 20px;font-size:.82rem;
  display:inline-block;margin-bottom:20px;position:relative;z-index:1;
}
.cover-title{fontfont-size:2.6rem;font-weight:800;line-height:1.2;margin:0 0 10px;position:relative;z-index:1;color: #000;}
.cover-shop{font-size:1.3rem;font-weight:600;margin:0 0 32px;opacity:.9;position:relative;z-index:1;}
.cover-divider{width:80px;height:3px;background:rgba(255,255,255,.4);border-radius:4px;margin:0 auto 28px;position:relative;z-index:1;}
.cover-meta{font-size:.9rem;opacity:.75;line-height:1.9;position:relative;z-index:1;}
.cover-meta strong{opacity:1;font-weight:700;}
.cover-footer{
  margin-top:32px;padding:16px 32px;
  background:rgba(0,0,0,.15);border-radius:50px;
  font-size:.82rem;opacity:.7;position:relative;z-index:1;
}

/* ─── Chapter / Module Sections ─────────────────────── */
.chapter{
  background:#fff;border-radius:14px;padding:28px 32px;
  margin-bottom:32px;border:1px solid #e2e8f0;
  box-shadow:0 2px 12px rgba(0,0,0,.05);
  page-break-inside:avoid;
}
.ch-head{display:flex;align-items:flex-start;gap:14px;margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid #f1f5f9;}
.ch-head i{font-size:1.6rem;margin-top:4px;flex-shrink:0;}
.ch-title{font-size:1.25rem;font-weight:800;color:#1e293b;}
.ch-sub{font-size:.88rem;color:#64748b;margin:4px 0 0;}

/* ─── Reuse detail-page classes (same as _layout.php) ── */
.slabel{display:flex;align-items:center;gap:10px;font-size:.95rem;font-weight:700;color:#334155;margin:28px 0 14px;padding-bottom:10px;border-bottom:2px solid #e2e8f0;}
.slabel i{font-size:.9rem;}
.ibox{background:#f8fafc;border-radius:12px;padding:18px 22px;margin-bottom:16px;border:1px solid #e8edf4;}
.ibox p{margin:0;color:#475569;line-height:1.85;font-size:.9rem;}
.feat-item{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.feat-item:last-child{border-bottom:none;}
.feat-ico{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:#f0fdf4;color:#10b981;display:flex;align-items:center;justify-content:center;font-size:.8rem;}
.feat-text strong{display:block;color:#1e293b;font-size:.87rem;font-weight:700;margin-bottom:2px;}
.feat-text span{color:#64748b;font-size:.8rem;line-height:1.5;}
.step-card{display:flex;align-items:flex-start;gap:14px;background:#f8fafc;border-radius:12px;padding:16px 20px;margin-bottom:10px;border:1px solid #e8edf4;border-left:4px solid #10b981;page-break-inside:avoid;}
.step-num{width:32px;height:32px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;flex-shrink:0;}
.step-body h5{font-size:.9rem;font-weight:700;color:#1e293b;margin:0 0 3px;}
.step-body p{font-size:.83rem;color:#64748b;margin:0;line-height:1.6;}
.tip-box,.note-box{display:flex;align-items:flex-start;gap:10px;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;line-height:1.7;}
.tip-box{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
.note-box{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.tip-box i,.note-box i{font-size:1rem;margin-top:2px;flex-shrink:0;}
.status-table{width:100%;border-collapse:separate;border-spacing:0;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;margin-bottom:16px;}
.status-table th{background:#10b981;color:#fff;font-weight:700;padding:9px 14px;font-size:.82rem;text-align:left;}
.status-table td{padding:9px 14px;font-size:.82rem;border-bottom:1px solid #f1f5f9;background:#fff;}
.status-table tr:last-child td{border-bottom:none;}

/* ─── Category Divider ───────────────────── */
.cat-divider{
  display:flex;align-items:center;gap:12px;
  margin:48px 0 24px;padding:16px 20px;
  background:linear-gradient(135deg,#f0fdf4,#ecfdf5);
  border-radius:12px;border:1px solid #86efac;
}
.cat-divider .cat-icon{width:44px;height:44px;border-radius:12px;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.cat-divider h3{margin:0;font-size:1.05rem;font-weight:800;color:#065f46;}
.cat-divider p{margin:3px 0 0;font-size:.82rem;color:#047857;}

/* ─── Print Styles ───────────────────────── */
@media print {
  @page { size:A4; margin:15mm 14mm; }
  .screen-bar { display:none !important; }
  .print-wrapper { margin:0; max-width:100%; padding:0; }
  body { font-size:10pt; background:#fff; }

  /* cover breaks to its own page */
  .cover-page {
    color: #000;
    page-break-after:always;
    border-radius:0; margin:0;
    min-height:100vh; min-height:100dvh;
    box-shadow:none;
  }
  .cat-divider { page-break-before:always; page-break-inside:avoid; }
  .chapter { page-break-inside:avoid; box-shadow:none; }
  .step-card { page-break-inside:avoid; box-shadow:none; }
  .ibox { background:#f8fafc !important; }
  a { color:inherit !important; text-decoration:none !important; }
}
</style>
</head>
<body>

<!-- Screen-only: top bar -->
<div class="screen-bar">
  <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับ</a>
  <h2>📋 ตัวอย่างก่อนพิมพ์คู่มือทั้งหมด</h2>
  <button class="btn-go" onclick="window.print()"><i class="fas fa-print me-1"></i> พิมพ์ PDF ทันที</button>
</div>

<div class="print-wrapper">

<!-- ══════════════════════════════════════════ -->
<!-- หน้าปกคู่มือ                              -->
<!-- ══════════════════════════════════════════ -->
<div class="cover-page">
  <div class="cover-icon"><i class="fas fa-mobile-alt"></i></div>
  <div class="cover-badge"><i class="fas fa-book-open me-1"></i> เอกสารอ้างอิงระบบ</div>
  <h1 class="text-light cover-title">คู่มือการใช้งานระบบ</h1>
  <div class="cover-shop"><?= $shop ?></div>
  <div class="cover-divider"></div>
  <div class="cover-meta">
    <strong>ระบบจัดการร้านโทรศัพท์มือถือ</strong><br>
    Mobile Shop Management System<br><br>
    <?php if ($addr): ?>
    <i class="fas fa-map-marker-alt me-1"></i><?= $addr ?><br>
    <?php endif; ?>
    <i class="fas fa-calendar me-1"></i>วันที่พิมพ์: <?= $today ?><br>
    <i class="fas fa-layer-group me-1"></i>ครอบคลุมทุกโมดูลในระบบ
  </div>
  <div class="cover-footer">
    <i class="fas fa-shield-alt me-1"></i> เอกสารนี้จัดทำเพื่อใช้ภายในองค์กรเท่านั้น &copy; <?= $year ?>
  </div>
</div>

<!-- ══════════════════════════════════════════ -->
<!-- 1. งานหลัก                               -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-star"></i></div>
  <div>
    <h3>หมวดที่ 1 — งานหลัก</h3>
    <p>แดชบอร์ด, การขาย, การซ่อม, สต็อคสินค้า, การรับเข้าสินค้า</p>
  </div>
</div>

<?php
// Change CWD ให้ require ใน detail pages หา _layout.php ได้
chdir(__DIR__ . '/pages');
require 'dashboard.php';
require 'sales.php';
require 'repair.php';
require 'stock.php';
require 'purchase.php';

chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 2. ข้อมูลสินค้า                           -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-mobile-alt"></i></div>
  <div>
    <h3>หมวดที่ 2 — ข้อมูลสินค้า</h3>
    <p>รายการสินค้า, ประเภทสินค้า, ยี่ห้อสินค้า, อาการเสีย</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'product.php';
require 'prod_type.php';
require 'prod_brand.php';
require 'symptom.php';
chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 3. ข้อมูลบุคคล                            -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-users"></i></div>
  <div>
    <h3>หมวดที่ 3 — ข้อมูลบุคคล</h3>
    <p>ลูกค้า, ผู้จำหน่าย, พนักงาน, แผนก</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'customer.php';
require 'supplier.php';
require 'employee.php';
require 'department.php';
chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 4. รายงาน                                 -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-chart-bar"></i></div>
  <div>
    <h3>หมวดที่ 4 — รายงาน</h3>
    <p>รายงานยอดขาย, รายงานยอดซ่อม</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'report_sales.php';
require 'report_repairs.php';
chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 5. ตั้งค่าระบบ                            -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-cogs"></i></div>
  <div>
    <h3>หมวดที่ 5 — ตั้งค่าระบบ</h3>
    <p>ร้านค้า, สาขา, ผู้ใช้งาน, บทบาท, สิทธิ์, ธีม, โปรไฟล์</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'shop.php';
require 'branch.php';
require 'users.php';
require 'role.php';
require 'permission.php';
require 'system_config.php';
require 'profile.php';
chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 6. ข้อมูลทั่วไป                           -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-globe"></i></div>
  <div>
    <h3>หมวดที่ 6 — ข้อมูลทั่วไป</h3>
    <p>คำนำหน้านาม, ศาสนา, จังหวัด/อำเภอ/ตำบล</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'prename.php';
require 'religion.php';
require 'provinces.php';
chdir('../');
?>

<!-- ══════════════════════════════════════════ -->
<!-- 7. การเข้าสู่ระบบ                         -->
<!-- ══════════════════════════════════════════ -->
<div class="cat-divider">
  <div class="cat-icon"><i class="fas fa-sign-in-alt"></i></div>
  <div>
    <h3>หมวดที่ 7 — การเข้าสู่ระบบ</h3>
    <p>เข้าสู่ระบบ, สมัครใช้งาน, ลืมรหัสผ่าน</p>
  </div>
</div>

<?php
chdir(__DIR__ . '/pages');
require 'login.php';
require 'register.php';
require 'forgot_password.php';
chdir('../');
?>

<!-- back-cover note -->
<div style="text-align:center;padding:32px;margin-top:32px;border-top:1px solid #e2e8f0;">
  <small style="color:#94a3b8;">
    <i class="fas fa-info-circle me-1"></i>
    คู่มือระบบ <?= $shop ?> | พิมพ์: <?= $today ?>
  </small>
</div>

</div><!-- /print-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-trigger print after content loads (only if ?auto=1)
<?php if (($_GET['auto'] ?? '') === '1'): ?>
window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 800); });
<?php endif; ?>
</script>
</body>
</html>