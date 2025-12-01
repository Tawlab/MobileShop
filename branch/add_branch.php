<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_branch');

$error_message = '';
$return_url = $_GET['return_url'] ?? 'branch.php';

// (2) ดึงข้อมูลสำหรับ Dropdowns
$shop_result = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
$provinces_result = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result = $conn->query("SELECT district_id, district_name_th, provinces_province_id FROM districts");
// ดึง zip_code มาด้วย
$subdistricts_result = $conn->query("SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts");

$all_districts = $districts_result->fetch_all(MYSQLI_ASSOC);
$all_subdistricts = $subdistricts_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (4) รับค่าจากฟอร์ม
    $branch_name = trim($_POST['branch_name']);
    $branch_code = trim($_POST['branch_code']) ?: NULL;
    $branch_phone = trim($_POST['branch_phone']) ?: NULL;
    $shop_id = !empty($_POST['shop_info_shop_id']) ? (int)$_POST['shop_info_shop_id'] : NULL;

    // ข้อมูลที่อยู่
    $home_no = trim($_POST['home_no']) ?: NULL;
    $moo = trim($_POST['moo']) ?: NULL;
    $soi = trim($_POST['soi']) ?: NULL;
    $road = trim($_POST['road']) ?: NULL;
    $village = trim($_POST['village']) ?: NULL;
    $subdistricts_id = !empty($_POST['subdistricts_subdistrict_id']) ? (int)$_POST['subdistricts_subdistrict_id'] : NULL;

    // (5) ตรวจสอบข้อมูล (Server-side Validation)
    if (empty($branch_name)) {
        $error_message = 'กรุณากรอก "ชื่อสาขา"';
    } elseif (empty($shop_id)) {
        $error_message = 'กรุณาเลือก "สังกัดร้านค้า"';
    } elseif (empty($subdistricts_id)) {
        $error_message = 'กรุณาเลือกที่อยู่ (จังหวัด/อำเภอ/ตำบล) ให้ครบถ้วน';
    } else {
        // --- [แก้ไข] ตรวจสอบเบอร์โทรศัพท์ ---
        $phone_valid = true;
        if (!empty($branch_phone)) {
            // Regex: ขึ้นต้นด้วย 02,05,06,08,09 และตามด้วยตัวเลขอีก 8 หลัก (รวมเป็น 10 หลัก)
            if (!preg_match('/^(02|05|06|08|09)[0-9]{8}$/', $branch_phone)) {
                $phone_valid = false;
                $error_message = 'เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)';
            }
        }
        // ------------------------------------------

        if ($phone_valid) {
            $conn->begin_transaction();
            try {
                // 6.1) สร้าง ID สาขาใหม่ (Auto-generate)
                $sql_max_id = "SELECT IFNULL(MAX(branch_id), 0) as max_id FROM branches";
                $max_result = $conn->query($sql_max_id);
                $new_branch_id = $max_result->fetch_assoc()['max_id'] + 1;

                // 6.2) ตรวจสอบชื่อสาขาซ้ำ
                $stmt_check = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
                $stmt_check->bind_param("s", $branch_name);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    throw new Exception('ชื่อสาขา "' . htmlspecialchars($branch_name) . '" นี้มีอยู่แล้วในระบบ');
                }
                $stmt_check->close();

                // 6.3) สร้างข้อมูลที่อยู่ (Addresses)
                $sql_max_addr = "SELECT IFNULL(MAX(address_id), 0) as max_addr_id FROM addresses";
                $res_addr = $conn->query($sql_max_addr);
                $new_address_id = $res_addr->fetch_assoc()['max_addr_id'] + 1;

                $stmt_addr = $conn->prepare("INSERT INTO addresses (address_id, home_no, moo, soi, road, village, subdistricts_subdistrict_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_addr->bind_param("isssssi", $new_address_id, $home_no, $moo, $soi, $road, $village, $subdistricts_id);
                if (!$stmt_addr->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
                $stmt_addr->close();

                // 6.4) บันทึกข้อมูลสาขา (Branches)
                $sql_insert = "INSERT INTO branches (
                                    branch_id, branch_code, branch_name, branch_phone, 
                                    shop_info_shop_id, Addresses_address_id, 
                                    create_at, update_at
                               ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("isssii", $new_branch_id, $branch_code, $branch_name, $branch_phone, $shop_id, $new_address_id);

                if (!$stmt_insert->execute()) throw new Exception("บันทึกข้อมูลสาขาล้มเหลว: " . $stmt_insert->error);
                $stmt_insert->close();

                $conn->commit();
                $_SESSION['success'] = "เพิ่มสาขา '" . htmlspecialchars($branch_name) . "' เรียบร้อยแล้ว";

                header("Location: " . $return_url);
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มข้อมูลสาขา</title>
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
                    <h4 class="mb-4"><i class="bi bi-shop me-2"></i>เพิ่มข้อมูลสาขา</h4>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="add_branch.php" id="branchForm" novalidate>
                        <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">

                        <div class="form-section">
                            <h5>ข้อมูลสาขา</h5>
                            <table>
                                <tr>
                                    <td class="label-col"><label class="form-label required-label">ชื่อสาขา</label></td>
                                    <td>
                                        <input type="text" name="branch_name" class="form-control" required style="width: 300px;"
                                            placeholder="กรอกชื่อสาขา" value="<?= htmlspecialchars($_POST['branch_name'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกชื่อสาขา</div>
                                    </td>
                                    <td class="label-col"><label class="form-label ms-3">รหัสสาขา</label></td>
                                    <td>
                                        <input type="text" name="branch_code" class="form-control" style="width: 300px;"
                                            placeholder="กรอกรหัสสาขา" value="<?= htmlspecialchars($_POST['branch_code'] ?? '') ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col"><label class="form-label required-label">สังกัดร้านค้า</label></td>
                                    <td>
                                        <select name="shop_info_shop_id" class="form-select" style="width: 300px;" required>
                                            <option value="">-- เลือกร้านค้า --</option>
                                            <?php while ($shop = $shop_result->fetch_assoc()): ?>
                                                <option value="<?= $shop['shop_id'] ?>" <?= (isset($_POST['shop_info_shop_id']) && $_POST['shop_info_shop_id'] == $shop['shop_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($shop['shop_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกร้านค้า</div>
                                    </td>
                                    <td class="label-col"><label class="form-label ms-3">เบอร์โทร</label></td>
                                    <td>
                                        <input type="text" name="branch_phone" id="branch_phone" class="form-control"
                                            placeholder="0xxxxxxxxx (10 หลัก)" style="width: 300px;"
                                            value="<?= htmlspecialchars($_POST['branch_phone'] ?? '') ?>"
                                            maxlength="10">
                                        <div id="phone_error" class="error-feedback">เบอร์โทรไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)</div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5>ที่อยู่สาขา (จำเป็นต้องเลือก ตำบล)</h5>
                            <table>
                                <tr>
                                    <td class="label-col"><label class="form-label">บ้านเลขที่ / หมู่ที่</label></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="text" name="home_no" class="form-control" placeholder="บ้านเลขที่"
                                                style="width: 140px;" value="<?= htmlspecialchars($_POST['home_no'] ?? '') ?>">
                                            <input type="text" name="moo" class="form-control" placeholder="หมู่ที่"
                                                style="width: 150px;" value="<?= htmlspecialchars($_POST['moo'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="label-col"><label class="form-label ms-3">ซอย / หมู่บ้าน</label></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="text" name="soi" class="form-control" placeholder="ซอย"
                                                style="width: 140px;" value="<?= htmlspecialchars($_POST['soi'] ?? '') ?>">
                                            <input type="text" name="village" class="form-control" placeholder="หมู่บ้าน"
                                                style="width: 150px;" value="<?= htmlspecialchars($_POST['village'] ?? '') ?>">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col"><label class="form-label">ถนน</label></td>
                                    <td>
                                        <input type="text" name="road" class="form-control" placeholder="ถนน"
                                            style="width: 300px;" value="<?= htmlspecialchars($_POST['road'] ?? '') ?>">
                                    </td>
                                    <td class="label-col"><label class="form-label ms-3">รหัสไปรษณีย์</label></td>
                                    <td>
                                        <input type="text" id="zip_code" class="form-control bg-light"
                                            placeholder="รอเลือกตำบล" style="width: 300px;" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col"><label class="form-label required-label">จังหวัด</label></td>
                                    <td>
                                        <select id="province" class="form-select" style="width: 300px;" required>
                                            <option value="">-- เลือกจังหวัด --</option>
                                            <?php
                                            mysqli_data_seek($provinces_result, 0);
                                            while ($p = mysqli_fetch_assoc($provinces_result)) {
                                                echo "<option value='{$p['province_id']}'>" . htmlspecialchars($p['province_name_th']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกจังหวัด</div>
                                    </td>
                                    <td class="label-col"><label class="form-label ms-3 required-label">อำเภอ</label></td>
                                    <td>
                                        <select id="district" class="form-select" style="width: 300px;" required>
                                            <option value="">-- เลือกอำเภอ --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกอำเภอ</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col"><label class="form-label required-label">ตำบล</label></td>
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
                            <button type="submit" class="btn btn-add"><i class="bi bi-save-fill me-1"></i> บันทึก</button>
                            <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> ยกเลิก</a>
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

            // ข้อมูลที่อยู่จาก PHP
            const districts = <?php echo json_encode($all_districts); ?>;
            const subdistricts = <?php echo json_encode($all_subdistricts); ?>;

            const provinceSelect = document.getElementById('province');
            const districtSelect = document.getElementById('district');
            const subdistrictSelect = document.getElementById('subdistrict');
            const zipCodeInput = document.getElementById('zip_code');

            // [แก้ไข] Script ตรวจสอบเบอร์โทรศัพท์
            const phoneInput = document.getElementById('branch_phone');
            const phoneError = document.getElementById('phone_error');

            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // ลบทุกอย่างที่ไม่ใช่ตัวเลข
                    this.value = this.value.replace(/[^0-9]/g, '');

                    const value = this.value;
                    const phonePattern = /^(02|05|06|08|09)[0-9]{8}$/;

                    if (value.length > 0) {
                        if (!phonePattern.test(value)) {
                            phoneError.style.display = 'block';
                            phoneInput.classList.add('is-invalid');
                        } else {
                            phoneError.style.display = 'none';
                            phoneInput.classList.remove('is-invalid');
                        }
                    } else {
                        // ถ้าว่าง ให้ซ่อน Error (เพราะไม่ได้บังคับกรอก)
                        phoneError.style.display = 'none';
                        phoneInput.classList.remove('is-invalid');
                    }
                });
            }

            // จัดการ Dropdown จังหวัด -> อำเภอ
            if (provinceSelect) {
                provinceSelect.addEventListener('change', function() {
                    const provinceId = this.value;
                    districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                    zipCodeInput.value = ''; // Reset Zip
                    districtSelect.classList.remove('is-invalid');

                    if (provinceId) {
                        districts.forEach(district => {
                            if (district.provinces_province_id == provinceId) {
                                const option = document.createElement('option');
                                option.value = district.district_id;
                                option.textContent = district.district_name_th;
                                districtSelect.appendChild(option);
                            }
                        });
                    }
                });
            }

            // จัดการ Dropdown อำเภอ -> ตำบล
            if (districtSelect) {
                districtSelect.addEventListener('change', function() {
                    const districtId = this.value;
                    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                    zipCodeInput.value = ''; // Reset Zip
                    subdistrictSelect.classList.remove('is-invalid');

                    if (districtId) {
                        subdistricts.forEach(subdistrict => {
                            if (subdistrict.districts_district_id == districtId) {
                                const option = document.createElement('option');
                                option.value = subdistrict.subdistrict_id;
                                option.textContent = subdistrict.subdistrict_name_th;
                                option.dataset.zip = subdistrict.zip_code; // เก็บ Zip code ไว้
                                subdistrictSelect.appendChild(option);
                            }
                        });
                    }
                });
            }

            // แสดง Zip Code เมื่อเลือกตำบล
            if (subdistrictSelect) {
                subdistrictSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.dataset.zip) {
                        zipCodeInput.value = selectedOption.dataset.zip;
                    } else {
                        zipCodeInput.value = '';
                    }
                    if (this.value) this.classList.remove('is-invalid');
                });
            }

            // ตรวจสอบก่อน Submit
            const form = document.getElementById('branchForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    let isValid = true;

                    // ถ้าเบอร์โทรผิดรูปแบบ ห้ามส่งฟอร์ม
                    if (phoneInput && phoneInput.classList.contains('is-invalid')) {
                        isValid = false;
                        phoneInput.focus();
                    }

                    if (!form.checkValidity() || !isValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');

                    // ตรวจสอบ Dropdown
                    [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                        if (!select.value) select.classList.add('is-invalid');
                        else select.classList.remove('is-invalid');
                    });
                }, false);

                [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                    select.addEventListener('change', function() {
                        if (this.value) this.classList.remove('is-invalid');
                    });
                });
            }
        })();
    </script>
</body>

</html>