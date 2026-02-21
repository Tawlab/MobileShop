<?php
if (!function_exists('layout_head')) {

    function adjustColor(string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r = max(0, hexdec(substr($hex,0,2)) - 35);
        $g = max(0, hexdec(substr($hex,2,2)) - 35);
        $b = max(0, hexdec(substr($hex,4,2)) - 35);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    function adjustColorLight(string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r = min(255, hexdec(substr($hex,0,2)) + 220);
        $g = min(255, hexdec(substr($hex,2,2)) + 220);
        $b = min(255, hexdec(substr($hex,4,2)) + 220);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    function layout_head(string $title, string $icon, string $color, string $subtitle = '') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once '../../config/config.php';
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../global/login.php'); exit;
        }
        $q     = $conn->query("SELECT shop_name FROM shop_info LIMIT 1");
        $info  = $q ? $q->fetch_assoc() : [];
        $shop  = htmlspecialchars($info['shop_name'] ?? 'Mobile Shop');
        $t     = htmlspecialchars($title);
        $ic    = htmlspecialchars($icon);
        $c     = htmlspecialchars($color);
        $cd    = adjustColor($color);
        $cl    = adjustColorLight($color);
        $sub   = $subtitle ? '<p class="hero-sub">'.htmlspecialchars($subtitle).'</p>' : '';
        ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $t ?> — <?= $shop ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php require '../../config/load_theme.php'; ?>
<style>
/* ─── Base ──────────────────────────────── */
.phero-text h1 { color: #ffffff !important; font-size: 24pt; font-weight: 800; margin: 0; }
*, *::before, *::after { box-sizing: border-box; }
:root {
  --accent:      <?= $c ?>;
  --accent-dark: <?= $cd ?>;
  --accent-lite: <?= $cl ?>;
  --radius:      14px;
  --shadow:      0 2px 16px rgba(0,0,0,.07);
}
body { margin:0; background:#f6f8fb; font-family:'Sarabun',sans-serif; color:#1e293b; line-height:1.7; }

/* ─── Sticky Nav ─────────────────────────── */
.mnav {
  position:sticky; top:0; z-index:200;
  background:#fff; border-bottom:1px solid #e8edf2;
  display:flex; align-items:center; gap:0;
  height:56px; padding:0 24px;
  box-shadow:0 1px 6px rgba(0,0,0,.06);
}
.mnav-left { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }
.mnav-back {
  display:inline-flex; align-items:center; gap:6px;
  color:#64748b; text-decoration:none; font-size:.85rem; font-weight:600;
  padding:6px 12px; border-radius:8px; white-space:nowrap;
  transition:background .15s, color .15s;
}
.mnav-back:hover { background:#f1f5f9; color:var(--accent); }
.mnav-sep { color:#cbd5e1; font-size:1rem; }
.mnav-title {
  font-size:.9rem; font-weight:700; color:#334155;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.mnav-right { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.btn-print {
  display:inline-flex; align-items:center; gap:6px;
  background:var(--accent); color:#fff;
  border:none; border-radius:8px;
  padding:7px 16px; font-size:.82rem; font-weight:700;
  font-family:'Sarabun',sans-serif;
  cursor:pointer; transition:background .2s, transform .15s, box-shadow .2s;
  box-shadow:0 2px 8px rgba(0,0,0,.15);
  text-decoration:none;
}
.btn-print:hover { background:var(--accent-dark); transform:translateY(-1px); box-shadow:0 4px 14px rgba(0,0,0,.2); color:#fff; }
.btn-print:active { transform:translateY(0); }

/* ─── Page Hero  ─────────────────────────── */
.phero {
  background:linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
  color:#fff;
  padding:32px 40px 28px;
  border-radius:0 0 28px 28px;
  margin-bottom:36px;
  position:relative; overflow:hidden;
  box-shadow:0 6px 24px rgba(0,0,0,.12);
}
.phero::after {
  content:''; position:absolute; right:-40px; top:-40px;
  width:200px; height:200px; border-radius:50%;
  background:rgba(255,255,255,.07);
}
.phero-inner { display:flex; align-items:center; gap:20px; position:relative; z-index:1; }
.phero-icon {
  width:60px; height:60px; border-radius:16px; flex-shrink:0;
  background:rgba(255,255,255,.2); backdrop-filter:blur(8px);
  display:flex; align-items:center; justify-content:center;
  font-size:1.7rem;
}
.phero-text h1 { font-size:1.6rem; font-weight:800; margin:0 0 4px; line-height:1.2; }
.hero-sub { margin:0; opacity:.85; font-size:.92rem; }

/* ─── Content ────────────────────────────── */
.page-body { max-width:860px; margin:0 auto; padding:0 24px 64px; }

/* ─── Section Label ──────────────────────── */
.slabel {
  display:flex; align-items:center; gap:10px;
  font-size:1rem; font-weight:700; color:#334155;
  margin:32px 0 16px;
  padding-bottom:10px;
  border-bottom:2px solid #e2e8f0;
}
.slabel i { color:var(--accent); font-size:1rem; }
.slabel-dot {
  width:28px; height:28px; border-radius:7px; flex-shrink:0;
  background:var(--accent); color:#fff;
  display:flex; align-items:center; justify-content:center; font-size:.75rem;
}

/* ─── Info Box ───────────────────────────── */
.ibox {
  background:#fff; border-radius:var(--radius);
  padding:20px 24px; margin-bottom:18px;
  box-shadow:var(--shadow);
  border:1px solid rgba(0,0,0,.04);
}
.ibox p { margin:0; color:#475569; line-height:1.85; }

/* ─── Feature List ───────────────────────── */
.feat-item {
  display:flex; align-items:flex-start; gap:14px;
  padding:11px 0; border-bottom:1px solid #f1f5f9;
}
.feat-item:last-child { border-bottom:none; }
.feat-ico {
  width:36px; height:36px; border-radius:10px; flex-shrink:0;
  background:var(--accent-lite); color:var(--accent);
  display:flex; align-items:center; justify-content:center; font-size:.85rem;
}
.feat-text strong { display:block; color:#1e293b; font-size:.9rem; font-weight:700; margin-bottom:2px; }
.feat-text span   { color:#64748b; font-size:.83rem; line-height:1.5; }

/* ─── Step Cards ─────────────────────────── */
.step-card {
  display:flex; align-items:flex-start; gap:16px;
  background:#fff; border-radius:var(--radius);
  padding:20px 22px; margin-bottom:12px;
  box-shadow:var(--shadow); border:1px solid rgba(0,0,0,.04);
  border-left:4px solid var(--accent);
  page-break-inside:avoid;
}
.step-num {
  width:36px; height:36px; border-radius:50%; flex-shrink:0;
  background:var(--accent); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:.9rem; font-weight:800; margin-top:1px;
}
.step-body h5 { font-size:.95rem; font-weight:700; color:#1e293b; margin:0 0 4px; }
.step-body p  { font-size:.87rem; color:#64748b; margin:0; line-height:1.65; }

/* ─── Tip / Note ─────────────────────────── */
.tip-box, .note-box {
  display:flex; align-items:flex-start; gap:12px;
  border-radius:12px; padding:14px 18px;
  margin-bottom:18px; font-size:.88rem; line-height:1.7;
}
.tip-box  { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.note-box { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
.tip-box  i, .note-box i { font-size:1.1rem; margin-top:2px; flex-shrink:0; }
.tip-box  a { color:#166534; }
.note-box a { color:#92400e; }

/* ─── Status Table ───────────────────────── */
.status-table { width:100%; border-collapse:separate; border-spacing:0; border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); margin-bottom:18px; }
.status-table th { background:var(--accent); color:#fff; font-weight:700; padding:10px 16px; font-size:.85rem; text-align:left; }
.status-table td { padding:10px 16px; font-size:.85rem; border-bottom:1px solid #f1f5f9; background:#fff; }
.status-table tr:last-child td { border-bottom:none; }

/* ─── Print ──────────────────────────────── */
@media print {
  @page { size:A4; margin:18mm 16mm; }
  .mnav, .btn-print, .mnav-back, .mnav-sep { display:none !important; }
  body { background:#fff !important; font-size:11pt; }
  .phero {
    background:#fff !important; color:#000 !important;
    border:2px solid var(--accent); border-radius:8px;
    margin-bottom:20px; padding:16px 22px;
    box-shadow:none;
  }
  .phero-icon { background:var(--accent-lite) !important; color:var(--accent) !important; }
  .phero-text h1 { color:#000 !important; font-size:16pt; }
  .hero-sub { color:#333 !important; }
  .ibox, .step-card { box-shadow:none; border:1px solid #ddd; }
  .step-card { page-break-inside:avoid; }
  a { color:inherit !important; text-decoration:none !important; }
}
</style>
</head>
<body>
<!-- Nav -->
<nav class="mnav">
  <div class="mnav-left">
    <a href="../index.php" class="mnav-back"><i class="fas fa-arrow-left"></i> กลับ</a>
    <span class="mnav-sep">›</span>
    <span class="mnav-title"><?= $t ?></span>
  </div>
  <div class="mnav-right">
    <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> พิมพ์ PDF</button>
  </div>
</nav>
<!-- Hero -->
<div class="phero">
  <div class="phero-inner">
    <div class="phero-icon"><i class="fas fa-<?= $ic ?>"></i></div>
    <div class="phero-text">
      <h1><?= $t ?></h1>
      <?= $sub ?>
    </div>
  </div>
</div>
<!-- Content -->
<div class="page-body">
<?php
    } // end layout_head

    function layout_foot() {
?>
</div><!-- /page-body -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    } // end layout_foot
} // end if !function_exists