<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_shop');

// ตรวจสอบ ID
$shop_id = $_GET['id'] ?? 0;
if (empty($shop_id)) {
    echo "<script>alert('Shop ID not found'); window.location='shop.php';</script>";
    exit;
}

// ดึงข้อมูล Address Dropdowns
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th, provinces_province_id FROM districts");
$subdistricts_result = mysqli_query($conn, "SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts");

//  เก็บข้อมูล dropdown 
$all_districts = $districts_result->fetch_all(MYSQLI_ASSOC);
$all_subdistricts = $subdistricts_result->fetch_all(MYSQLI_ASSOC);

// ดึงข้อมูลเดิม
$sql_data = "SELECT 
                s.*, 
                a.address_id, a.home_no, a.moo, a.soi, a.road, a.village,
                a.subdistricts_subdistrict_id,
                sd.districts_district_id,
                d.provinces_province_id,
                sd.zip_code
            FROM shop_info s
            LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
            LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
            LEFT JOIN districts d ON sd.districts_district_id = d.district_id
            WHERE s.shop_id = ?";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("i", $shop_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$data = $result_data->fetch_assoc();
$stmt_data->close();

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลร้านค้า'); window.location='shop.php';</script>";
    exit();
}

// เก็บ ID ปัจจุบันไว้สำหรับ JS และการ UPDATE
$current_address_id = $data['address_id'];
$selected_province_id = $data['provinces_province_id'];
$selected_district_id = $data['districts_district_id'];
$selected_subdistrict_id = $data['subdistricts_subdistrict_id'];
$current_logo_list = !empty($data['logo']) ? explode(',', $data['logo']) : [];


// จัดการบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = mysqli_real_escape_string($conn, trim($_POST['shop_name']));
    $tax_id = mysqli_real_escape_string($conn, trim($_POST['tax_id']));
    $shop_phone = mysqli_real_escape_string($conn, trim($_POST['shop_phone'])) ?: NULL;
    $shop_email = mysqli_real_escape_string($conn, trim($_POST['shop_email'])) ?: NULL;

    $home_no = mysqli_real_escape_string($conn, trim($_POST['home_no'])) ?: NULL;
    $moo = mysqli_real_escape_string($conn, trim($_POST['moo'])) ?: NULL;
    $soi = mysqli_real_escape_string($conn, trim($_POST['soi'])) ?: NULL;
    $road = mysqli_real_escape_string($conn, trim($_POST['road'])) ?: NULL;
    $village = mysqli_real_escape_string($conn, trim($_POST['village'])) ?: NULL;
    $subdistricts_id = !empty($_POST['subdistricts_id']) ? (int)$_POST['subdistricts_id'] : NULL;

    //  จัดการรูปภาพ
    $upload_dir = '../uploads/shops/';
    $final_logo_list = $_POST['existing_images'] ?? []; // รูปเดิมที่ยังเหลือ

    if (!empty($_FILES['shop_images']['name'][0])) {
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
        $max_files = 4;

        $new_files_count = count($_FILES['shop_images']['name']);

        if (count($final_logo_list) + $new_files_count > $max_files) {
            $_SESSION['error_message'] = "อัปโหลดรูปภาพได้สูงสุด $max_files รูป (คุณมี $new_files_count รูปใหม่ + " . count($final_logo_list) . " รูปเดิม)";
        } else {
            for ($i = 0; $i < $new_files_count; $i++) {
                if ($_FILES['shop_images']['error'][$i] == 0 && in_array($_FILES['shop_images']['type'][$i], $allowed_types)) {
                    $file_name = time() . '_' . $i . '_' . basename($_FILES['shop_images']['name'][$i]);
                    $target_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['shop_images']['tmp_name'][$i], $target_path)) {
                        $final_logo_list[] = $file_name; // เพิ่มรูปใหม่
                    }
                }
            }
        }
    }
    $logo_db_string = !empty($final_logo_list) ? implode(',', $final_logo_list) : NULL;

    // ตรวจสอบข้อมูล 
    $errors = [];
    if (empty($shop_name)) $errors[] = "กรุณากรอกชื่อร้านค้า";
    if (empty($tax_id)) $errors[] = "กรุณากรอกเลขผู้เสียภาษี";
    if (empty($subdistricts_id)) $errors[] = "กรุณาเลือกที่อยู่ (จังหวัด/อำเภอ/ตำบล)";

    // ตรวจสอบเบอร์โทรศัพท์
    if (!empty($shop_phone)) {
        if (!preg_match('/^(02|05|06|08|09)[0-9]{8}$/', $shop_phone)) {
            $errors[] = "เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)";
        }
    }

    if (empty($errors) && !isset($_SESSION['error_message'])) { 

        $conn->begin_transaction();
        try {
            // อัปเดต Address
            $stmt_addr = $conn->prepare("UPDATE addresses SET
                home_no = ?, moo = ?, soi = ?, road = ?, village = ?, 
                subdistricts_subdistrict_id = ?
                WHERE address_id = ?");
            $stmt_addr->bind_param(
                "sssssii",
                $home_no,
                $moo,
                $soi,
                $road,
                $village,
                $subdistricts_id,
                $current_address_id
            );
            $stmt_addr->execute();
            $stmt_addr->close();

            // อัปเดต Shop Info
            $stmt_shop = $conn->prepare("UPDATE shop_info SET
                shop_name = ?, tax_id = ?, shop_phone = ?, shop_email = ?, 
                logo = ?, update_at = NOW()
                WHERE shop_id = ?");

            $stmt_shop->bind_param(
                "sssssi",
                $shop_name,
                $tax_id,
                $shop_phone,
                $shop_email,
                $logo_db_string,
                $shop_id
            );
            $stmt_shop->execute();
            $stmt_shop->close();

            //  ถ้าสำเร็จทั้งหมด
            $conn->commit();
            $_SESSION['success_message'] = "แก้ไขข้อมูลร้านค้า (ID: $shop_id) สำเร็จ!";
            echo "<script>window.location.href = 'shop.php';</script>";
            exit;
        } catch (Exception $e) {
            // ถ้ายกเลิก
            $conn->rollback();
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
    } else {
        if (empty($errors)) {
            // (Error มาจาก Session (อัปโหลดรูป))
        } else {
            $_SESSION['error_message'] = implode("\\n", $errors);
        }
    }

    //  หาก Error, ให้โหลดข้อมูลจาก POST กลับเข้าไปในฟอร์ม
    $data = $_POST;
    $data['shop_id'] = $shop_id;
    $data['address_id'] = $current_address_id;
    $current_logo_list = $_POST['existing_images'] ?? [];
    $selected_subdistrict_id = $subdistricts_id;
    if ($selected_subdistrict_id) {
        $sd_lookup = $conn->query("SELECT d.districts_district_id, dt.provinces_province_id, d.zip_code
                                  FROM subdistricts d 
                                  JOIN districts dt ON d.districts_district_id = dt.district_id
                                  WHERE d.subdistrict_id = $selected_subdistrict_id");
        if ($sd_lookup && $sd_lookup->num_rows > 0) {
            $lookup_data = $sd_lookup->fetch_assoc();
            $selected_district_id = $lookup_data['districts_district_id'];
            $selected_province_id = $lookup_data['provinces_province_id'];
            $data['zip_code'] = $lookup_data['zip_code'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลร้านค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            font-size: 15px;
            color: <?= $text_color ?>;
            min-height: 100vh;
        }

        .container {
            max-width: 960px;
            padding: 20px 15px;
        }

        .page-header {
            background: <?= $theme_color ?>;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 28px;
        }

        h5 {
            margin-top: 0;
            padding-bottom: 15px;
            font-weight: 600;
            color: <?= $theme_color ?>;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
            font-size: 20px;
        }

        .form-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .form-label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 15px;
        }

        .form-control,
        .form-select {
            font-size: 14px;
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background-color: #f7fafc;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 3px rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.1);
            background-color: #fff;
        }

        .btn {
            padding: 12px 30px;
            font-weight: 500;
            font-size: 16px;
            border-radius: 10px;
            border: none;
        }

        .btn-success {
            background: <?= $btn_edit_color ?>;
            /* (ใช้ $btn_edit_color) */
            color: white;
        }

        .btn-success:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .required-label::after {
            content: " *";
            color: #e53e3e;
        }

        /* (CSS การอัปโหลดรูปภาพ) */
        .image-upload-container {
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background-color: #f7fafc;
            cursor: pointer;
        }

        .image-upload-container:hover {
            border-color: <?= $theme_color ?>;
            background-color: #f0f8ff;
        }

        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-image:hover {
            background: #dc2626;
        }

        /* (CSS Alerts) */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease;
            z-index: 1050;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }

        .error-feedback {
            font-size: 13px;
            color: #e53e3e;
            margin-top: 5px;
            display: none;
        }

        .is-invalid {
            border-color: #f56565 !important;
        }

        .is-invalid+.error-feedback,
        .is-invalid~.error-feedback {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="custom-alert alert-success">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <div>
                            <strong>สำเร็จ!</strong><br>
                            <?php echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="custom-alert alert-error">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div>
                            <strong>ผิดพลาด!</strong><br>
                            <?php echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="container my-4">
                    <div class="page-header">
                        <h4 class="text-light">
                            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูลร้านค้า (ID: <?= htmlspecialchars($shop_id) ?>)
                        </h4>
                    </div>

                    <form method="POST" id="shopForm" action="edit_shop.php?id=<?= htmlspecialchars($shop_id) ?>" enctype="multipart/form-data" novalidate>

                        <div class="form-section">
                            <h5><i class="fas fa-store me-2"></i>ข้อมูลทั่วไป</h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label required-label">ชื่อร้านค้า</label>
                                    <input type="text" name="shop_name" id="shop_name" class="form-control" required maxlength="50"
                                        value="<?= htmlspecialchars($data['shop_name'] ?? '') ?>">
                                    <div class="error-feedback">กรุณากรอกชื่อร้านค้า</div>
                                </div>
                                <div>
                                    <label class="form-label required-label">เลขประจำตัวผู้เสียภาษี</label>
                                    <input type="text" name="tax_id" id="tax_id" class="form-control" required
                                        value="<?= htmlspecialchars($data['tax_id'] ?? '') ?>" maxlength="20">
                                    <div class="error-feedback">กรุณากรอกเลขประจำตัวผู้เสียภาษี</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่</h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label">บ้านเลขที่</label>
                                    <input type="text" name="home_no" id="home_no" class="form-control"
                                        maxlength="20" value="<?= htmlspecialchars($data['home_no'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">หมู่ที่</label>
                                    <input type="text" name="moo" id="moo" class="form-control"
                                        maxlength="20" value="<?= htmlspecialchars($data['moo'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">ซอย</label>
                                    <input type="text" name="soi" class="form-control"
                                        maxlength="50" value="<?= htmlspecialchars($data['soi'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">ถนน</label>
                                    <input type="text" name="road" class="form-control"
                                        maxlength="50" value="<?= htmlspecialchars($data['road'] ?? '') ?>">
                                </div>
                                <div class="form-grid-full">
                                    <label class="form-label">หมู่บ้าน</label>
                                    <input type="text" name="village" class="form-control"
                                        maxlength="50" value="<?= htmlspecialchars($data['village'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label required-label">จังหวัด</label>
                                    <select id="provinceSelect" class="form-select" required>
                                        <option value="">-- เลือกจังหวัด --</option>
                                        <?php
                                        mysqli_data_seek($provinces_result, 0);
                                        while ($p = mysqli_fetch_assoc($provinces_result)) {
                                            $selected = ($selected_province_id == $p['province_id']) ? 'selected' : '';
                                            echo "<option value='{$p['province_id']}' $selected>" . htmlspecialchars($p['province_name_th']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="error-feedback">กรุณาเลือกจังหวัด</div>
                                </div>
                                <div>
                                    <label class="form-label required-label">อำเภอ</label>
                                    <select id="districtSelect" name="district" class="form-select" required>
                                        <option value="">-- เลือกอำเภอ --</option>
                                    </select>
                                    <div class="error-feedback">กรุณาเลือกอำเภอ</div>
                                </div>
                                <div>
                                    <label class="form-label required-label">ตำบล</label>
                                    <select name="subdistricts_id" id="subdistrictSelect" class="form-select" required>
                                        <option value="">-- เลือกตำบล --</option>
                                    </select>
                                    <div class="error-feedback">กรุณาเลือกตำบล</div>
                                </div>
                                <div>
                                    <label class="form-label">รหัสไปรษณีย์</label>
                                    <input type="text" name="zip_code_display" id="zip_code_display" class="form-control"
                                        maxlength="5" placeholder="(อัตโนมัติ)" readonly
                                        value="<?= htmlspecialchars($data['zip_code'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-phone-alt me-2"></i>ข้อมูลติดต่อ</h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="text" name="shop_phone" id="shop_phone" class="form-control"
                                        maxlength="10" placeholder="0xxxxxxxxx (10 หลัก)"
                                        value="<?= htmlspecialchars($data['shop_phone'] ?? '') ?>">
                                    <div id="phone_no_error" class="error-feedback">เบอร์โทรไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้น 02, 05, 06, 08, 09)</div>
                                </div>
                                <div>
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="shop_email" id="shop_email" class="form-control"
                                        maxlength="50" value="<?= htmlspecialchars($data['shop_email'] ?? '') ?>">
                                    <div id="email_error" class="error-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-images me-2"></i>รูปภาพร้านค้า (สูงสุด 4 รูป)</h5>

                            <label class="form-label">รูปภาพปัจจุบัน:</label>
                            <div id="existingImagePreview" class="image-preview mb-3">
                                <?php if (empty($current_logo_list)): ?>
                                    <p class="text-muted">ไม่มีรูปภาพ</p>
                                <?php else: ?>
                                    <?php foreach ($current_logo_list as $img_name): ?>
                                        <div class="image-preview-item">
                                            <img src="../uploads/shops/<?= htmlspecialchars($img_name) ?>" alt="Preview"
                                                onerror="this.parentElement.innerHTML = '<div class=\'image-preview-item\'>' + 
                                     '<i class=\'fas fa-exclamation-triangle\'></i><small>Error</small></div>'">
                                            <button type="button" class="remove-image" onclick="removeExistingImage(this, '<?= htmlspecialchars($img_name) ?>')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <input type="hidden" name="existing_images[]" value="<?= htmlspecialchars($img_name) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <hr>

                            <label class="form-label mt-3">อัปโหลดรูปใหม่ (หากเลือกไฟล์ใหม่ รูปเดิมจะถูกแทนที่):</label>
                            <div class="image-upload-container" onclick="document.getElementById('shop_images').click();">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #cbd5e0;"></i>
                                <p class="mb-2">คลิกเพื่อเลือกรูปภาพใหม่ (สูงสุด 4 รูป)</p>
                                <input type="file" name="shop_images[]" id="shop_images" accept="image/*" multiple style="display: none;">
                            </div>
                            <div id="newImagePreview" class="image-preview"></div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>บันทึกการเปลี่ยนแปลง
                            </button>
                            <a href="shop.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Address data
        const districts = <?php echo json_encode($all_districts); ?>;
        const subdistricts = <?php echo json_encode($all_subdistricts); ?>;

        // Address IDs ที่เลือกไว้
        let selected_province_id = '<?= $selected_province_id ?>';
        let selected_district_id = '<?= $selected_district_id ?>';
        let selected_subdistrict_id = '<?= $selected_subdistrict_id ?>';

        const provinceSelect = document.getElementById('provinceSelect');
        const districtSelect = document.getElementById('districtSelect');
        const subdistrictSelect = document.getElementById('subdistrictSelect');
        const zipcodeInput = document.getElementById('zip_code_display');

        // ฟังก์ชันเติมอำเภอ
        function populateDistricts(provinceId) {
            districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';
            if (!provinceId) return;

            districts.forEach(district => {
                if (district.provinces_province_id == provinceId) {
                    const option = document.createElement('option');
                    option.value = district.district_id;
                    option.textContent = district.district_name_th;
                    if (district.district_id == selected_district_id) {
                        option.selected = true;
                    }
                    districtSelect.appendChild(option);
                }
            });
        }

        // ฟังก์ชันเติมตำบล
        function populateSubdistricts(districtId) {
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';
            if (!districtId) return;

            subdistricts.forEach(subdistrict => {
                if (subdistrict.districts_district_id == districtId) {
                    const option = document.createElement('option');
                    option.value = subdistrict.subdistrict_id;
                    option.textContent = subdistrict.subdistrict_name_th;
                    if (subdistrict.subdistrict_id == selected_subdistrict_id) {
                        option.selected = true;
                    }
                    subdistrictSelect.appendChild(option);
                }
            });
            // เติม Zip Code หลังจากโหลดตำบลเสร็จ
            if (selected_subdistrict_id) {
                const selectedSub = subdistricts.find(s => s.subdistrict_id == selected_subdistrict_id);
                if (selectedSub) zipcodeInput.value = selectedSub.zip_code;
            }
        }

        // Event Listeners สำหรับ Dropdown
        provinceSelect.addEventListener('change', function() {
            selected_district_id = ''; 
            selected_subdistrict_id = '';
            populateDistricts(this.value);
        });

        districtSelect.addEventListener('change', function() {
            selected_subdistrict_id = '';
            populateSubdistricts(this.value);
        });

        subdistrictSelect.addEventListener('change', function() {
            const subdistrictId = this.value;
            if (subdistrictId) {
                const selectedSub = subdistricts.find(s => s.subdistrict_id == subdistrictId);
                if (selectedSub) zipcodeInput.value = selectedSub.zip_code;
            } else {
                zipcodeInput.value = '';
            }
        });

        // เรียกใช้งานครั้งแรกตอนโหลดหน้า
        if (selected_province_id) {
            populateDistricts(selected_province_id);
        }
        if (selected_district_id) {
            populateSubdistricts(selected_district_id);
        }

        // จัดการรูปภาพ
        let newSelectedFiles = []; 
        const maxFiles = 4;

        // ฟังก์ชันลบ "รูปเดิม" 
        function removeExistingImage(button, fileName) {
            button.parentElement.remove();

            const hiddenInput = document.querySelector(`input[name="existing_images[]"][value="${fileName}"]`);
            if (hiddenInput) {
                hiddenInput.remove();
            }

            if (document.getElementById('existingImagePreview').children.length === 0) {
                document.getElementById('existingImagePreview').innerHTML = '<p class="text-muted">ไม่มีรูปภาพ</p>';
            }
        }

        // ฟังก์ชันจัดการ "รูปใหม่"
        document.getElementById('shop_images').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const existingCount = document.querySelectorAll('input[name="existing_images[]"]').length;

            // เตือนถ้าไฟล์ใหม่ + ไฟล์เดิม เกิน 4
            if (existingCount + newSelectedFiles.length + files.length > maxFiles) {
                showAlert('error', `สามารถอัปโหลดได้สูงสุด ${maxFiles} รูปเท่านั้น`);
                e.target.value = null;
                return;
            }

            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    newSelectedFiles.push(file);
                    displayNewImage(file);
                }
            });
            updateNewFileInput();
        });

        function displayNewImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.getElementById('newImagePreview');
                const itemDiv = document.createElement('div');
                itemDiv.className = 'image-preview-item';
                itemDiv.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removeNewImage(this, '${file.name}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(itemDiv);
            };
            reader.readAsDataURL(file);
        }

        function removeNewImage(button, fileName) {
            newSelectedFiles = newSelectedFiles.filter(file => file.name !== fileName);
            button.parentElement.remove();
            updateNewFileInput();
        }

        function updateNewFileInput() {
            const dataTransfer = new DataTransfer();
            newSelectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('shop_images').files = dataTransfer.files;
        }

        // Validation และ Alerts

        function showError(input, message) {
            input.classList.add('is-invalid');
            let errorDiv = input.nextElementSibling;
            while (errorDiv && !errorDiv.classList.contains('error-feedback')) {
                errorDiv = errorDiv.nextElementSibling;
            }
            if (errorDiv && errorDiv.classList.contains('error-feedback')) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
            }
        }

        function hideError(input) {
            input.classList.remove('is-invalid');
            let errorDiv = input.nextElementSibling;
            while (errorDiv && !errorDiv.classList.contains('error-feedback')) {
                errorDiv = errorDiv.nextElementSibling;
            }
            if (errorDiv && errorDiv.classList.contains('error-feedback')) {
                errorDiv.style.display = 'none';
            }
        }

        const emailInput = document.getElementById("shop_email");
        const emailError = document.getElementById("email_error");
        emailInput.addEventListener("input", function() {
            const value = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (value && !emailRegex.test(value)) {
                emailError.style.display = "block";
                emailInput.classList.add("is-invalid");
            } else {
                emailError.style.display = "none";
                emailInput.classList.remove("is-invalid");
            }
        });

        const phoneInput = document.getElementById("shop_phone");
        const phoneError = document.getElementById("phone_no_error");

        phoneInput.addEventListener("input", function() {
            // ลบตัวอักษรที่ไม่ใช่ตัวเลขออกทันที
            this.value = this.value.replace(/[^0-9]/g, '');

            const value = this.value.trim();

            //  ตรวจสอบเงื่อนไข
            const phonePattern = /^(02|05|06|08|09)[0-9]{8}$/;

            if (value.length > 0) {
                if (!phonePattern.test(value)) {
                    phoneError.style.display = "block";
                    phoneInput.classList.add("is-invalid");
                } else {
                    phoneError.style.display = "none";
                    phoneInput.classList.remove("is-invalid");
                }
            } else {
                phoneError.style.display = "none";
                phoneInput.classList.remove("is-invalid");
            }
        });

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `custom-alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle fa-lg"></i>
                <div>
                    <strong>${type === 'success' ? 'สำเร็จ!' : 'ผิดพลาด!'}</strong><br>
                    ${message}
                </div>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => alertDiv.remove(), 300);
            }, 3000);
        }

        const form = document.getElementById('shopForm');
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showError(field, 'กรุณากรอกข้อมูลในช่องนี้');
                    isValid = false;
                } else {
                    hideError(field);
                }
            });

            if (emailInput.value && emailInput.classList.contains('is-invalid')) isValid = false;
            if (phoneInput.value && phoneInput.classList.contains('is-invalid')) isValid = false;

            if (!isValid) {
                e.preventDefault();
                const firstError = form.querySelector('.is-invalid');
                if (firstError) firstError.focus();
                showAlert('error', 'กรุณาตรวจสอบข้อมูลให้ถูกต้อง');
            }
        });

        form.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    hideError(this);
                }
            });
            element.addEventListener('change', function() {
                if (this.classList.contains('is-invalid')) {
                    hideError(this);
                }
            });
        });

        setTimeout(() => {
            const alerts = document.querySelectorAll('.custom-alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>