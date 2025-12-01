<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_supplier');

$error_message = '';
$supplier_id = $_GET['id'] ?? '';

// (2) ตรวจสอบ ID
if (empty($supplier_id)) {
    header("Location: supplier.php?error=not_found");
    exit();
}

// (3) ดึงข้อมูล Dropdowns ทั้งหมด
$prefix_result     = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th");
$provinces_result  = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");

// (ดึงทั้งหมดมาเก็บในตัวแปรสำหรับ JS)
$all_districts_result    = $conn->query("SELECT district_id, district_name_th, provinces_province_id FROM districts");
// [แก้ไข] ดึง zip_code มาด้วย
$all_subdistricts_result = $conn->query("SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts");

$all_districts = $all_districts_result->fetch_all(MYSQLI_ASSOC);
$all_subdistricts = $all_subdistricts_result->fetch_all(MYSQLI_ASSOC);

// (4) ดึงข้อมูล Supplier และ Address ที่จะแก้ไข
// [แก้ไข] ดึง zip_code ของตำบลปัจจุบันมาด้วย
$sql_data = "SELECT 
                s.*, 
                a.address_id, a.home_no, a.moo, a.soi, a.road, a.village,
                a.subdistricts_subdistrict_id,
                sd.districts_district_id,
                d.provinces_province_id,
                sd.zip_code 
            FROM suppliers s
            LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
            LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
            LEFT JOIN districts d ON sd.districts_district_id = d.district_id
            WHERE s.supplier_id = ?";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("s", $supplier_id);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$data = $result_data->fetch_assoc();
$stmt_data->close();

if (!$data) {
    header("Location: supplier.php?error=not_found");
    exit();
}

// (5) เก็บ ID ปัจจุบันไว้สำหรับ JS
$selected_address_id = $data['address_id'];
$selected_province_id = $data['provinces_province_id'];
$selected_district_id = $data['districts_district_id'];
$selected_subdistrict_id = $data['subdistricts_subdistrict_id'];
$current_zip_code = $data['zip_code']; // เก็บไปใช้แสดงผลตอนโหลดหน้าเว็บ


// (6) เมื่อกดบันทึก (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $co_name = trim($_POST['co_name']);
    $tax_id = trim($_POST['tax_id']) ?: NULL;
    $prefixs_prefix_id = trim($_POST['prefixs_prefix_id']) ?: NULL;
    $contact_firstname = trim($_POST['contact_firstname']) ?: NULL;
    $contact_lastname = trim($_POST['contact_lastname']) ?: NULL;
    $supplier_phone_no = trim($_POST['supplier_phone_no']) ?: NULL;
    $supplier_email = trim($_POST['supplier_email']) ?: NULL;

    $home_no = trim($_POST['home_no']) ?: NULL;
    $moo = trim($_POST['moo']) ?: NULL;
    $soi = trim($_POST['soi']) ?: NULL;
    $road = trim($_POST['road']) ?: NULL;
    $village = trim($_POST['village']) ?: NULL;
    $subdistricts_id = trim($_POST['subdistricts_subdistrict_id']) ?: NULL;

    // (7) Server-side Validation
    if (empty($co_name)) {
        $error_message = 'กรุณากรอก "ชื่อบริษัท"';
    } elseif (empty($subdistricts_id)) {
        $error_message = 'กรุณาเลือกที่อยู่ (จังหวัด/อำเภอ/ตำบล) ให้ครบถ้วน';
    } elseif (!empty($tax_id) && !ctype_digit($tax_id)) {
        // [แก้ไข] ตรวจสอบเลขผู้เสียภาษี ต้องเป็นตัวเลขเท่านั้น
        $error_message = 'เลขผู้เสียภาษีต้องเป็นตัวเลขเท่านั้น';
    } elseif (!empty($supplier_phone_no)) {
        // [แก้ไข] ตรวจสอบเบอร์โทร (ขึ้นต้น 02,05,06,08,09 และยาว 9-10 หลัก)
        if (!preg_match('/^(02|05|06|08|09)[0-9]{7,8}$/', $supplier_phone_no)) {
            $error_message = 'เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 9-10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)';
        }
    }

    if (empty($error_message)) {

        // (8) --- Transaction ---
        $conn->begin_transaction();
        try {
            // 8.1) ตรวจสอบชื่อบริษัทซ้ำ
            $stmt_check = $conn->prepare("SELECT supplier_id FROM suppliers WHERE co_name = ? AND supplier_id != ?");
            $stmt_check->bind_param("ss", $co_name, $supplier_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception('ชื่อบริษัท "' . htmlspecialchars($co_name) . '" นี้มีอยู่แล้วในระบบ');
            }
            $stmt_check->close();

            // 8.2) อัปเดต Address
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
                $selected_address_id
            );
            $stmt_addr->execute();
            $stmt_addr->close();

            // 8.3) อัปเดต Supplier
            $sql_update = "UPDATE suppliers SET
                                co_name = ?, tax_id = ?, 
                                contact_firstname = ?, contact_lastname = ?, 
                                supplier_email = ?, supplier_phone_no = ?, 
                                prefixs_prefix_id = ?, 
                                update_at = NOW()
                           WHERE supplier_id = ?";

            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param(
                "ssssssis",
                $co_name,
                $tax_id,
                $contact_firstname,
                $contact_lastname,
                $supplier_email,
                $supplier_phone_no,
                $prefixs_prefix_id,
                $supplier_id
            );

            if (!$stmt_update->execute()) {
                throw new Exception("บันทึกข้อมูล Supplier ล้มเหลว: " . $stmt_update->error);
            }
            $stmt_update->close();

            // (9) ถ้าสำเร็จทั้งหมด
            $conn->commit();
            header("Location: supplier.php?success=edit");
            exit();
        } catch (Exception $e) {
            // (10) ถ้ายกเลิก (เกิด Error)
            $conn->rollback();
            $error_message = $e->getMessage();

            // (11) หาก Error, ให้โหลดข้อมูลจาก POST กลับเข้าไปในฟอร์ม
            $data = $_POST;
            $data['subdistricts_subdistrict_id'] = $subdistricts_id;

            $selected_subdistrict_id = $subdistricts_id;
            // ดึง Province/District ID จาก POSTed SubDistrict ID เพื่อคงสถานะ Dropdown
            if ($selected_subdistrict_id) {
                $sd_lookup = $conn->query("SELECT d.districts_district_id, dt.provinces_province_id, d.zip_code 
                                          FROM subdistricts d 
                                          JOIN districts dt ON d.districts_district_id = dt.district_id
                                          WHERE d.subdistrict_id = $selected_subdistrict_id");
                if ($sd_lookup && $sd_lookup->num_rows > 0) {
                    $lookup_data = $sd_lookup->fetch_assoc();
                    $selected_district_id = $lookup_data['districts_district_id'];
                    $selected_province_id = $lookup_data['provinces_province_id'];
                    $current_zip_code = $lookup_data['zip_code']; // อัปเดต Zip Code ตามที่เลือกค้างไว้
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลผู้จัดจำหน่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php include '../config/load_theme.php'; ?>
    <style>
        h5 {
            margin-top: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid <?= $theme_color ?>;
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .form-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px 25px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .label-col {
            width: 150px;
            font-weight: 500;
            vertical-align: top;
            padding-top: 10px;
        }

        .form-label {
            margin-bottom: 4px;
            font-weight: 500;
        }

        .error-feedback {
            font-size: 13px;
            color: #dc3545;
            margin-top: 4px;
            display: none;
        }

        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
        }

        .form-control.is-invalid~.error-feedback,
        .form-select.is-invalid~.error-feedback {
            display: block;
        }

        .required-label::after {
            content: " *";
            color: red;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container my-4">
                    <h4 class="mb-4"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลผู้จัดจำหน่าย (ID: <?= htmlspecialchars($supplier_id) ?>)</h4>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit_supplier.php?id=<?= htmlspecialchars($supplier_id) ?>" id="supplierForm" novalidate>

                        <div class="form-section">
                            <h5>ข้อมูลผู้จัดจำหน่าย</h5>
                            <table>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label required-label">ชื่อบริษัท</label>
                                    </td>
                                    <td>
                                        <input type="text" name="co_name" class="form-control" required style="width: 300px;"
                                            placeholder="กรอกชื่อบริษัท" value="<?= htmlspecialchars($data['co_name'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกชื่อบริษัท</div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">เลขผู้เสียภาษี</label>
                                    </td>
                                    <td>
                                        <input type="text" name="tax_id" id="tax_id" class="form-control" style="width: 300px;"
                                            placeholder="กรอกเลขทะเบียนผู้เสียภาษี (ตัวเลขเท่านั้น)"
                                            value="<?= htmlspecialchars($data['tax_id'] ?? '') ?>"
                                            maxlength="20">
                                        <div class="error-feedback">กรอกได้เฉพาะตัวเลขเท่านั้น</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5>ข้อมูลผู้ติดต่อ (ถ้ามี)</h5>
                            <table>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label">ชื่อผู้ติดต่อ</label>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <select name="prefixs_prefix_id" id="prefixs_prefix_id" class="form-select" style="width: 100px;">
                                                <option value=""> คำนำหน้า </option>
                                                <?php
                                                mysqli_data_seek($prefix_result, 0);
                                                while ($p = mysqli_fetch_assoc($prefix_result)) {
                                                    $selected = ($data['prefixs_prefix_id'] ?? '') == $p['prefix_id'] ? 'selected' : '';
                                                    echo "<option value='{$p['prefix_id']}' $selected>" . htmlspecialchars($p['prefix_th']) . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <input type="text" name="contact_firstname" class="form-control"
                                                placeholder="กรอกชื่อผู้ติดต่อ" style="width: 190px;"
                                                value="<?= htmlspecialchars($data['contact_firstname'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">นามสกุล</label>
                                    </td>
                                    <td>
                                        <input type="text" name="contact_lastname" class="form-control"
                                            placeholder="กรอกนามสกุลผู้ติดต่อ" style="width: 300px;"
                                            value="<?= htmlspecialchars($data['contact_lastname'] ?? '') ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label">เบอร์โทร</label>
                                    </td>
                                    <td>
                                        <input type="text" name="supplier_phone_no" id="supplier_phone_no"
                                            class="form-control" placeholder="0xxxxxxxxx (10 หลัก)"
                                            style="width: 300px;" value="<?= htmlspecialchars($data['supplier_phone_no'] ?? '') ?>"
                                            maxlength="10">
                                        <div class="error-feedback" id="phone_error">กรุณากรอกตัวเลขให้ถูกต้อง (ขึ้นต้น 02,05,06,08,09)</div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">อีเมล</label>
                                    </td>
                                    <td>
                                        <input type="email" name="supplier_email" class="form-control" placeholder="กรอกอีเมล"
                                            style="width: 300px;" value="<?= htmlspecialchars($data['supplier_email'] ?? '') ?>"
                                            maxlength="50">
                                        <div class="error-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5>ที่อยู่ (จำเป็นต้องเลือก ตำบล)</h5>
                            <table>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label">บ้านเลขที่ / หมู่ที่</label>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="text" name="home_no" class="form-control" placeholder="บ้านเลขที่"
                                                style="width: 140px;" value="<?= htmlspecialchars($data['home_no'] ?? '') ?>">
                                            <input type="text" name="moo" class="form-control" placeholder="หมู่ที่"
                                                style="width: 150px;" value="<?= htmlspecialchars($data['moo'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">ซอย / หมู่บ้าน</label>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="text" name="soi" class="form-control" placeholder="ซอย"
                                                style="width: 140px;" value="<?= htmlspecialchars($data['soi'] ?? '') ?>">
                                            <input type="text" name="village" class="form-control" placeholder="หมู่บ้าน"
                                                style="width: 150px;" value="<?= htmlspecialchars($data['village'] ?? '') ?>">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label">ถนน</label>
                                    </td>
                                    <td>
                                        <input type="text" name="road" class="form-control" placeholder="ถนน"
                                            style="width: 300px;" value="<?= htmlspecialchars($data['road'] ?? '') ?>">
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">รหัสไปรษณีย์</label>
                                    </td>
                                    <td>
                                        <input type="text" id="zip_code" class="form-control bg-light"
                                            placeholder="รอเลือกตำบล" style="width: 300px;" readonly
                                            value="<?= htmlspecialchars($current_zip_code ?? '') ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label required-label">จังหวัด</label>
                                    </td>
                                    <td>
                                        <select id="province" class="form-select" style="width: 300px;" required>
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
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3 required-label">อำเภอ</label>
                                    </td>
                                    <td>
                                        <select id="district" class="form-select" style="width: 300px;" required>
                                            <option value="">-- เลือกอำเภอ --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกอำเภอ</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label required-label">ตำบล</label>
                                    </td>
                                    <td>
                                        <select name="subdistricts_subdistrict_id" id="subdistrict" class="form-select" required style="width: 300px;">
                                            <option value="">-- เลือกตำบล --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกตำบล</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-edit"> <i class="bi bi-save-fill me-1"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                            <a href="supplier.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            'use strict';

            // (16.1) ข้อมูล Address ทั้งหมดจาก PHP
            const all_districts = <?php echo json_encode($all_districts); ?>;
            const all_subdistricts = <?php echo json_encode($all_subdistricts); ?>;

            // (16.2) ID ที่ถูกเลือกไว้ (จาก PHP)
            let selected_district_id = '<?= $selected_district_id ?>';
            let selected_subdistrict_id = '<?= $selected_subdistrict_id ?>';

            const provinceSelect = document.getElementById('province');
            const districtSelect = document.getElementById('district');
            const subdistrictSelect = document.getElementById('subdistrict');
            const zipCodeInput = document.getElementById('zip_code');

            // [แก้ไข] ฟังก์ชันจำกัดให้พิมพ์แค่ตัวเลข
            function restrictToNumbers(inputId) {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                }
            }
            restrictToNumbers('tax_id');
            restrictToNumbers('supplier_phone_no');


            // (16.3) ฟังก์ชันเติมอำเภอ
            function populateDistricts(provinceId) {
                districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                zipCodeInput.value = ''; // Reset Zip Code when province changes

                if (!provinceId) return;

                all_districts.forEach(district => {
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

            // (16.4) ฟังก์ชันเติมตำบล
            function populateSubdistricts(districtId) {
                subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                zipCodeInput.value = ''; // Reset Zip Code when district changes

                if (!districtId) return;

                all_subdistricts.forEach(subdistrict => {
                    if (subdistrict.districts_district_id == districtId) {
                        const option = document.createElement('option');
                        option.value = subdistrict.subdistrict_id;
                        option.textContent = subdistrict.subdistrict_name_th;
                        // [แก้ไข] เก็บ Zip Code ใน dataset
                        option.dataset.zip = subdistrict.zip_code;

                        if (subdistrict.subdistrict_id == selected_subdistrict_id) {
                            option.selected = true;
                            // [แก้ไข] ถ้าตรงกับที่เลือกไว้เดิม ให้เติม zip code กลับเข้าไป (กรณี Javascript render ใหม่)
                            if (!zipCodeInput.value) {
                                zipCodeInput.value = subdistrict.zip_code;
                            }
                        }
                        subdistrictSelect.appendChild(option);
                    }
                });
            }

            // (16.5) เมื่อเลือกจังหวัด -> โหลดอำเภอ
            provinceSelect.addEventListener('change', function() {
                selected_district_id = '';
                selected_subdistrict_id = '';
                populateDistricts(this.value);
            });

            // (16.6) เมื่อเลือกอำเภอ -> โหลดตำบล
            districtSelect.addEventListener('change', function() {
                selected_subdistrict_id = '';
                populateSubdistricts(this.value);
            });

            // [แก้ไข] เมื่อเลือกตำบล -> แสดงรหัสไปรษณีย์
            subdistrictSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.dataset.zip) {
                    zipCodeInput.value = selectedOption.dataset.zip;
                } else {
                    zipCodeInput.value = '';
                }
            });


            // (16.7) เรียกใช้งานครั้งแรกเมื่อโหลดหน้า
            if (provinceSelect.value) {
                populateDistricts(provinceSelect.value);
            }
            if (districtSelect.value) {
                populateSubdistricts(districtSelect.value);
            }

            // (16.8) Bootstrap Validation & Phone Validation
            const form = document.getElementById('supplierForm');
            form.addEventListener('submit', function(event) {
                let isValid = true;

                // [แก้ไข] ตรวจสอบเบอร์โทร (Regex Check)
                const phoneInput = document.getElementById('supplier_phone_no');
                const phoneError = document.getElementById('phone_error');
                if (phoneInput.value) {
                    const phonePattern = /^(02|05|06|08|09)[0-9]{7,8}$/;
                    if (!phonePattern.test(phoneInput.value)) {
                        phoneInput.classList.add('is-invalid');
                        phoneError.style.display = 'block';
                        isValid = false;
                    } else {
                        phoneInput.classList.remove('is-invalid');
                        phoneError.style.display = 'none';
                    }
                }

                if (!form.checkValidity() || !isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');

                [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                    if (!select.value) select.classList.add('is-invalid');
                });
            }, false);

            [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                select.addEventListener('change', function() {
                    if (this.value) this.classList.remove('is-invalid');
                });
            });

        })();
    </script>
</body>

</html>