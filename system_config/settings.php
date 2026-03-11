<?php
session_start();
require '../config/config.php';

// ตรวจสอบการล็อกอิน (ถ้ามีฟังก์ชันเช็คสิทธิ์ให้ใส่ตรงนี้)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงการตั้งค่าปัจจุบันของผู้ใช้
$sql = "SELECT * FROM systemconfig WHERE user_id = $user_id";
$result = mysqli_query($conn, $sql);
$config = mysqli_fetch_assoc($result);

// กำหนดค่า Default กรณีที่ยังไม่มีในฐานข้อมูล (ค่าเป็น NULL)
$theme_color = $config['theme_color'] ?? '#198754';
$bg_color = $config['background_color'] ?? '#f4f6f9';
$text_color = $config['text_color'] ?? '#212529';
$font_style = $config['font_style'] ?? 'Prompt';

$header_bg = $config['header_bg_color'] ?? '#198754';
$header_text = $config['header_text_color'] ?? '#ffffff';

$btn_add = $config['btn_add_color'] ?? '#0d6efd';
$btn_edit = $config['btn_edit_color'] ?? '#ffc107';
$btn_del = $config['btn_delete_color'] ?? '#dc3545';

$status_on = $config['status_on_color'] ?? '#198754';
$status_off = $config['status_off_color'] ?? '#6c757d';
$warn_bg = $config['warning_bg_color'] ?? '#ffc107';
$danger_txt = $config['danger_text_color'] ?? '#dc3545';

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่ารูปแบบระบบ (Theme Settings)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=<?= $font_style ?>:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: <?= $bg_color ?>;
            color: <?= $text_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
        }

        .page-title { color: <?= $theme_color ?>; font-weight: bold; }
        .btn-theme { background-color: <?= $theme_color ?>; color: #fff; border: none; }
        .btn-theme:hover { filter: brightness(0.9); color: #fff; }

        .setting-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .setting-card-header {
            background-color: #fff;
            border-bottom: 2px solid <?= $theme_color ?>;
            padding: 15px 20px;
            font-weight: bold;
            color: <?= $theme_color ?>;
        }

        .form-control-color {
            width: 100%;
            height: 45px;
            cursor: pointer;
            padding: 5px;
            border-radius: 8px;
        }
        
        .color-label { font-size: 0.9rem; font-weight: 500; margin-bottom: 5px; color: #555; }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        
        <div class="main-content w-100">
            <div class="container py-4" style="max-width: 1000px;">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="page-title"><i class="bi bi-palette-fill me-2"></i>ตั้งค่ารูปแบบเว็บไซต์ (Theme Settings)</h3>
                </div>

                <form id="settingsForm">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card setting-card">
                                <div class="setting-card-header"><i class="bi bi-display me-2"></i>1. พื้นฐานและการแสดงผล (Global)</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="color-label">ฟอนต์ตัวอักษร (Font Family)</label>
                                        <select name="font_style" class="form-select shadow-sm">
                                            <?php
                                            $fonts = ['Prompt', 'Sarabun', 'Kanit', 'Mitr', 'Noto Sans Thai'];
                                            foreach ($fonts as $font) {
                                                $selected = ($font_style == $font) ? 'selected' : '';
                                                echo "<option value='$font' $selected>$font</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="color-label">สีธีมหลัก (Theme Color)</label>
                                            <input type="color" name="theme_color" class="form-control form-control-color" value="<?= $theme_color ?>" title="สีเมนู, สีเน้นย้ำ">
                                        </div>
                                        <div class="col-6">
                                            <label class="color-label">สีพื้นหลังระบบ (Body BG)</label>
                                            <input type="color" name="background_color" class="form-control form-control-color" value="<?= $bg_color ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="color-label">สีตัวอักษรหลัก (Text Color)</label>
                                            <input type="color" name="text_color" class="form-control form-control-color" value="<?= $text_color ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card setting-card">
                                <div class="setting-card-header"><i class="bi bi-table me-2"></i>2. หัวตารางและแถบข้อมูล (Headers)</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="color-label">สีพื้นหลังหัวตาราง (Table Header BG)</label>
                                            <input type="color" name="header_bg_color" class="form-control form-control-color" value="<?= $header_bg ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="color-label">สีตัวอักษรหัวตาราง (Table Header Text)</label>
                                            <input type="color" name="header_text_color" class="form-control form-control-color" value="<?= $header_text ?>">
                                        </div>
                                    </div>
                                    <div class="alert alert-light border mt-3 mb-0 small">
                                        <i class="bi bi-info-circle text-info me-1"></i> สีหัวตารางจะแสดงผลในหน้ารายการข้อมูลต่างๆ เช่น รายการร้านค้า, รายการผู้ใช้
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card setting-card">
                                <div class="setting-card-header"><i class="bi bi-menu-button-wide-fill me-2"></i>3. สีปุ่มกด (Action Buttons)</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-4">
                                            <label class="color-label">ปุ่มเพิ่ม (Add)</label>
                                            <input type="color" name="btn_add_color" class="form-control form-control-color" value="<?= $btn_add ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="color-label">ปุ่มแก้ไข (Edit)</label>
                                            <input type="color" name="btn_edit_color" class="form-control form-control-color" value="<?= $btn_edit ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="color-label">ปุ่มลบ (Delete)</label>
                                            <input type="color" name="btn_delete_color" class="form-control form-control-color" value="<?= $btn_del ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card setting-card">
                                <div class="setting-card-header"><i class="bi bi-tags-fill me-2"></i>4. สีป้ายสถานะ (Badges & Alerts)</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="color-label">สถานะเปิด (Active)</label>
                                            <input type="color" name="status_on_color" class="form-control form-control-color" value="<?= $status_on ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="color-label">สถานะปิด (Inactive)</label>
                                            <input type="color" name="status_off_color" class="form-control form-control-color" value="<?= $status_off ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="color-label">พื้นหลังเตือน (Warning BG)</label>
                                            <input type="color" name="warning_bg_color" class="form-control form-control-color" value="<?= $warn_bg ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="color-label">ข้อความเตือน (Danger Text)</label>
                                            <input type="color" name="danger_text_color" class="form-control form-control-color" value="<?= $danger_txt ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border mt-3">
                        <button type="button" id="btnReset" class="btn btn-outline-danger px-4 rounded-pill">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> คืนค่าเริ่มต้นทั้งหมด
                        </button>
                        <button type="submit" class="btn btn-theme px-5 py-2 rounded-pill fw-bold shadow">
                            <i class="bi bi-save me-1"></i> บันทึกการตั้งค่า
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // บันทึกข้อมูล (Save Settings)
            document.getElementById('settingsForm').addEventListener('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'กำลังบันทึกการตั้งค่า...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData(this);

                fetch('save_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ!',
                            text: 'รูปแบบเว็บไซต์ถูกอัปเดตแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error'));
            });

            // คืนค่าเริ่มต้น (Reset Settings)
            document.getElementById('btnReset').addEventListener('click', function() {
                Swal.fire({
                    title: 'ยืนยันการคืนค่าเริ่มต้น?',
                    text: "สีและฟอนต์ทั้งหมดจะถูกรีเซ็ตกลับเป็นค่าดั้งเดิมของระบบ",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ยืนยันรีเซ็ต',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'กำลังรีเซ็ต...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                        fetch('reset_settings.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({ icon: 'success', title: 'คืนค่าสำเร็จ!', timer: 1500, showConfirmButton: false })
                                .then(() => window.location.reload());
                            } else {
                                Swal.fire('ผิดพลาด', data.message, 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error'));
                    }
                });
            });

        });
    </script>
</body>
</html>