<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_shop');

//  ดึงข้อมูล Address Dropdowns
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th, provinces_province_id FROM districts");
$subdistricts_result = mysqli_query($conn, "SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts");

//  เก็บข้อมูล dropdown 
$all_districts = $districts_result->fetch_all(MYSQLI_ASSOC);
$all_subdistricts = $subdistricts_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = mysqli_real_escape_string($conn, trim($_POST['shop_name']));
    $tax_id = mysqli_real_escape_string($conn, trim($_POST['tax_id']));
    $shop_phone = mysqli_real_escape_string($conn, trim($_POST['shop_phone'])) ?: NULL;
    $shop_email = mysqli_real_escape_string($conn, trim($_POST['shop_email'])) ?: NULL;

    // ข้อมูล addresses
    $home_no = mysqli_real_escape_string($conn, trim($_POST['home_no'])) ?: NULL;
    $moo = mysqli_real_escape_string($conn, trim($_POST['moo'])) ?: NULL;
    $soi = mysqli_real_escape_string($conn, trim($_POST['soi'])) ?: NULL;
    $road = mysqli_real_escape_string($conn, trim($_POST['road'])) ?: NULL;
    $village = mysqli_real_escape_string($conn, trim($_POST['village'])) ?: NULL;
    $subdistricts_id = !empty($_POST['subdistricts_id']) ? (int)$_POST['subdistricts_id'] : NULL;


    // จัดการอัปโหลดรูปภาพ (สูงสุด 4 รูป)
    $logo_filenames = [];
    if (!empty($_FILES['shop_images']['name'][0])) {
        $upload_dir = '../uploads/shops/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
        $max_files = 4;

        for ($i = 0; $i < min(count($_FILES['shop_images']['name']), $max_files); $i++) {
            if ($_FILES['shop_images']['error'][$i] == 0 && in_array($_FILES['shop_images']['type'][$i], $allowed_types)) {
                $file_name = time() . '_' . $i . '_' . basename($_FILES['shop_images']['name'][$i]);
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['shop_images']['tmp_name'][$i], $target_path)) {
                    $logo_filenames[] = $file_name;
                }
            }
        }
    }
    $logo_db_string = !empty($logo_filenames) ? implode(',', $logo_filenames) : NULL;


    // ตรวจสอบข้อมูล
    $errors = [];
    if (empty($shop_name)) $errors[] = "กรุณากรอกชื่อร้านค้า";
    if (empty($tax_id)) $errors[] = "กรุณากรอกเลขผู้เสียภาษี";
    if (empty($subdistricts_id)) $errors[] = "กรุณาเลือกที่อยู่ (จังหวัด/อำเภอ/ตำบล)";
    if (!empty($shop_email) && !filter_var($shop_email, FILTER_VALIDATE_EMAIL)) $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    // ตรวจสอบเบอร์โทร (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02,05,06,08,09)
    if (!empty($shop_phone)) {
        if (!preg_match('/^(02|05|06|08|09)[0-9]{8}$/', $shop_phone)) {
            $errors[] = "เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)";
        }
    }


    if (empty($errors)) {
        $conn->begin_transaction();
        try {

            // สร้าง Shop ID อัตโนมัติ (MAX + 1) 
            $sql_max_shop_id = "SELECT MAX(shop_id) AS max_id FROM shop_info";
            $max_shop_result = $conn->query($sql_max_shop_id);
            $max_shop_row = $max_shop_result->fetch_assoc();
            $new_shop_id = ($max_shop_row['max_id'] ?? 0) + 1;

            //  สร้าง Address ID อัตโนมัติ (MAX + 1) 
            $sql_max_addr_id = "SELECT MAX(address_id) AS max_id FROM addresses";
            $max_addr_result = $conn->query($sql_max_addr_id);
            $max_addr_row = $max_addr_result->fetch_assoc();
            $new_address_id = ($max_addr_row['max_id'] ?? 0) + 1;


            //สร้าง Address
            $stmt_addr = $conn->prepare("INSERT INTO addresses (
                address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_addr->bind_param(
                "isssssi", 
                $new_address_id,
                $home_no,
                $moo,
                $soi,
                $road,
                $village,
                $subdistricts_id
            );
            $stmt_addr->execute();
            $stmt_addr->close();


            // สร้าง Shop Info 
            $stmt_shop = $conn->prepare("INSERT INTO shop_info (
                shop_id, shop_name, tax_id, shop_phone, shop_email, logo, 
                Addresses_address_id, create_at, update_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

            $stmt_shop->bind_param(
                "isssssi",
                $new_shop_id,
                $shop_name,
                $tax_id,
                $shop_phone,
                $shop_email,
                $logo_db_string,
                $new_address_id
            );
            $stmt_shop->execute();
            $stmt_shop->close();

            // (9) ถ้าสำเร็จทั้งหมด
            $conn->commit();
            $_SESSION['success_message'] = "บันทึกข้อมูลร้านค้า (ID: $new_shop_id) สำเร็จ!";
            echo "<script>window.location.href = 'shop.php';</script>";
            exit;
        } catch (Exception $e) {
            // ถ้ายกเลิก
            $conn->rollback();
            // ตรวจสอบว่า Error เกิดจาก Primary Key (ID ซ้ำ
            if ($conn->errno == 1062) {
                //  Duplicate entry
                if (strpos($e->getMessage(), 'addresses.PRIMARY') !== false) {
                    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: Address ID ($new_address_id) นี้มีอยู่แล้ว";
                } elseif (strpos($e->getMessage(), 'shop_info.PRIMARY') !== false) {
                    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: Shop ID ($new_shop_id) นี้มีอยู่แล้ว";
                } else {
                    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = implode("\\n", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มข้อมูลร้านค้า</title>
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
            background: <?= $btn_add_color ?>;
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
                            <?php
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="custom-alert alert-error">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div>
                            <strong>ผิดพลาด!</strong><br>
                            <?php
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="container my-4">
                    <div class="page-header">
                        <h4 class="text-light"><i class="fas fa-plus-circle me-2"></i>เพิ่มข้อมูลร้านค้า</h4>
                    </div>

                    <form method="POST" id="shopForm" enctype="multipart/form-data" novalidate>
                        <div class="form-section">
                            <h5><i class="fas fa-store me-2"></i>ข้อมูลทั่วไป</h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label required-label">ชื่อร้านค้า</label>
                                    <input type="text" name="shop_name" id="shop_name" class="form-control" required maxlength="50"
                                        placeholder="ชื่อร้านค้า (ไทย หรือ อังกฤษ)"
                                        value="<?= htmlspecialchars($_POST['shop_name'] ?? '') ?>">
                                    <div class="error-feedback">กรุณากรอกชื่อร้านค้า</div>
                                </div>
                                <div>
                                    <label class="form-label required-label">เลขประจำตัวผู้เสียภาษี</label>
                                    <input type="text" name="tax_id" id="tax_id" class="form-control" required
                                        placeholder="เลขประจำตัวผู้เสียภาษี 13 หลัก"
                                        value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>" maxlength="20">
                                    <div class="error-feedback">กรุณากรอกเลขประจำตัวผู้เสียภาษี</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่ <span class="text-success small">(บังคับเลือก ตำบล/อำเภอ/จังหวัด)</span></h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label">บ้านเลขที่</label>
                                    <input type="text" name="home_no" id="home_no" class="form-control" placeholder="บ้านเลขที่"
                                        maxlength="20" value="<?= htmlspecialchars($_POST['home_no'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">หมู่ที่</label>
                                    <input type="text" name="moo" id="moo" class="form-control" placeholder="หมู่ที่"
                                        maxlength="20" value="<?= htmlspecialchars($_POST['moo'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">ซอย</label>
                                    <input type="text" name="soi" class="form-control" placeholder="ซอย"
                                        maxlength="50" value="<?= htmlspecialchars($_POST['soi'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label">ถนน</label>
                                    <input type="text" name="road" class="form-control" placeholder="ถนน"
                                        maxlength="50" value="<?= htmlspecialchars($_POST['road'] ?? '') ?>">
                                </div>
                                <div class="form-grid-full">
                                    <label class="form-label">หมู่บ้าน</label>
                                    <input type="text" name="village" class="form-control" placeholder="หมู่บ้าน"
                                        maxlength="50" value="<?= htmlspecialchars($_POST['village'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="form-label required-label">จังหวัด</label>
                                    <select id="provinceSelect" class="form-select" required>
                                        <option value="">-- เลือกจังหวัด --</option>
                                        <?php
                                        mysqli_data_seek($provinces_result, 0);
                                        while ($p = mysqli_fetch_assoc($provinces_result)) {
                                            echo "<option value='{$p['province_id']}'>" . htmlspecialchars($p['province_name_th']) . "</option>";
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
                                        maxlength="5" placeholder="(อัตโนมัติ)" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-phone-alt me-2"></i>ข้อมูลติดต่อ <span class="text-muted small">(ไม่บังคับ)</span></h5>
                            <div class="form-grid">
                                <div>
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="text" name="shop_phone" id="shop_phone" class="form-control"
                                        maxlength="10" placeholder="0xxxxxxxxx (10 หลัก)"
                                        value="<?= htmlspecialchars($_POST['shop_phone'] ?? '') ?>">
                                    <div id="phone_no_error" class="error-feedback">เบอร์โทรไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้น 02, 05, 06, 08, 09)</div>
                                </div>
                                <div>
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="shop_email" id="shop_email" class="form-control"
                                        maxlength="50" placeholder="example@email.com"
                                        value="<?= htmlspecialchars($_POST['shop_email'] ?? '') ?>">
                                    <div id="email_error" class="error-feedback">รูปแบบอีเมลไม่ถูกต้อง</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-images me-2"></i>รูปภาพร้านค้า (สูงสุด 4 รูป)</h5>
                            <div class="image-upload-container" onclick="document.getElementById('shop_images').click();">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #cbd5e0;"></i>
                                <p class="mb-2">คลิกเพื่อเลือกรูปภาพ หรือลากไฟล์มาวางที่นี่</p>
                                <input type="file" name="shop_images[]" id="shop_images" accept="image/*" multiple style="display: none;">
                            </div>
                            <div id="imagePreview" class="image-preview"></div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>บันทึกข้อมูล
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

        const provinceSelect = document.getElementById('provinceSelect');
        const districtSelect = document.getElementById('districtSelect');
        const subdistrictSelect = document.getElementById('subdistrictSelect');
        const zipcodeInput = document.getElementById('zip_code_display'); // (ช่องแสดงผล)

        provinceSelect.addEventListener('change', function() {
            const provinceId = this.value;
            districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';

            if (provinceId) {
                const filteredDistricts = districts.filter(d => d.provinces_province_id == provinceId);
                filteredDistricts.forEach(district => {
                    const option = document.createElement('option');
                    option.value = district.district_id;
                    option.textContent = district.district_name_th;
                    districtSelect.appendChild(option);
                });
            }
        });

        districtSelect.addEventListener('change', function() {
            const districtId = this.value;
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';

            if (districtId) {
                const filteredSubdistricts = subdistricts.filter(s => s.districts_district_id == districtId);
                filteredSubdistricts.forEach(subdistrict => {
                    const option = document.createElement('option');
                    option.value = subdistrict.subdistrict_id;
                    option.textContent = subdistrict.subdistrict_name_th;
                    subdistrictSelect.appendChild(option);
                });
            }
        });

        subdistrictSelect.addEventListener('change', function() {
            const subdistrictId = this.value;
            if (subdistrictId) {
                const selectedSubdistrict = subdistricts.find(s => s.subdistrict_id == subdistrictId);
                if (selectedSubdistrict && selectedSubdistrict.zip_code) {
                    zipcodeInput.value = selectedSubdistrict.zip_code;
                } else {
                    zipcodeInput.value = '';
                }
            } else {
                zipcodeInput.value = '';
            }
        });

        // Image upload
        let selectedFiles = [];
        const maxFiles = 4;
        document.getElementById('shop_images').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            if (selectedFiles.length + files.length > maxFiles) {
                showAlert('error', `สามารถอัปโหลดได้สูงสุด ${maxFiles} รูปเท่านั้น`);
                return;
            }
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    selectedFiles.push(file);
                    displayImage(file);
                }
            });
            updateFileInput();
        });

        function displayImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.getElementById('imagePreview');
                const itemDiv = document.createElement('div');
                itemDiv.className = 'image-preview-item';
                itemDiv.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removeImage(this, '${file.name}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(itemDiv);
            };
            reader.readAsDataURL(file);
        }

        function removeImage(button, fileName) {
            selectedFiles = selectedFiles.filter(file => file.name !== fileName);
            button.parentElement.remove();
            updateFileInput();
        }

        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('shop_images').files = dataTransfer.files;
        }

        const uploadContainer = document.querySelector('.image-upload-container');

        uploadContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadContainer.style.borderColor = '<?= $theme_color ?>';
            uploadContainer.style.backgroundColor = '#f0f8ff';
        });

        uploadContainer.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadContainer.style.borderColor = '#cbd5e0';
            uploadContainer.style.backgroundColor = '#f7fafc';
        });

        uploadContainer.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadContainer.style.borderColor = '#cbd5e0';
            uploadContainer.style.backgroundColor = '#f7fafc';

            const files = Array.from(e.dataTransfer.files);
            const imageFiles = files.filter(file => file.type.startsWith('image/'));

            if (selectedFiles.length + imageFiles.length > maxFiles) {
                showAlert('error', `สามารถอัปโหลดได้สูงสุด ${maxFiles} รูปเท่านั้น`);
                return;
            }

            imageFiles.forEach(file => {
                selectedFiles.push(file);
                displayImage(file);
            });

            updateFileInput();
        });

        // Form validation
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

        // Validation fields 
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
            // ลบตัวอักษรที่ไม่ใช่ตัวเลขออกทันที (พิมพ์ ก-ฮ หรือ a-z ไม่ได้)
            this.value = this.value.replace(/[^0-9]/g, '');

            const value = this.value.trim();

            //ตรวจสอบเงื่อนไข
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

        // Alert function
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

        // Form submission validation
        const form = document.getElementById('shopForm');
        form.addEventListener('submit', function(e) {
            let isValid = true;
            // ตรวจสอบ Required fields
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showError(field, 'กรุณากรอกข้อมูลในช่องนี้');
                    isValid = false;
                } else {
                    hideError(field);
                }
            });

            // ตรวจสอบ Format
            if (emailInput.value && emailInput.classList.contains('is-invalid')) isValid = false;
            if (phoneInput.value && phoneInput.classList.contains('is-invalid')) isValid = false;

            if (!isValid) {
                e.preventDefault();
                const firstError = form.querySelector('.is-invalid');
                if (firstError) firstError.focus();
                showAlert('error', 'กรุณาตรวจสอบข้อมูลให้ถูกต้อง');
            }
        });

        // Clear error on input
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

        // Auto-hide alerts
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