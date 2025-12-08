<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_supplier');

$error_message = '';

// รับ return_url
$return_url = $_GET['return_url'] ?? 'supplier.php';

// ดึงข้อมูลสำหรับ Dropdowns
$prefix_result     = $conn->query("SELECT prefix_id, prefix_th FROM prefixs WHERE is_active = 1 ORDER BY prefix_th");
$provinces_result  = $conn->query("SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result  = $conn->query("SELECT district_id, district_name_th, provinces_province_id FROM districts");
$subdistricts_result = $conn->query("SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts");

// เก็บข้อมูล dropdown 
$all_districts = $districts_result->fetch_all(MYSQLI_ASSOC);
$all_subdistricts = $subdistricts_result->fetch_all(MYSQLI_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //รับค่าจากฟอร์ม
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

    // Server-side Validation
    if (empty($co_name)) {
        $error_message = 'กรุณากรอก "ชื่อบริษัท"';
    } elseif (empty($subdistricts_id)) {
        $error_message = 'กรุณาเลือกที่อยู่ (จังหวัด/อำเภอ/ตำบล) ให้ครบถ้วน';
    } elseif (!empty($tax_id) && !ctype_digit($tax_id)) {
        // ตรวจสอบเลขผู้เสียภาษี ต้องเป็นตัวเลขเท่านั้น
        $error_message = 'เลขผู้เสียภาษีต้องเป็นตัวเลขเท่านั้น';
    } elseif (!empty($supplier_phone_no)) {
        // ตรวจสอบเบอร์โทร
        if (!preg_match('/^(02|05|06|08|09)[0-9]{7,8}$/', $supplier_phone_no)) {
            $error_message = 'เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 9-10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)';
        }
    }

    if (empty($error_message)) {
        $conn->begin_transaction();
        try {
            //หาค่าสูงสุดเดิม และสร้าง ID ใหม่
            $sql_max_id = "SELECT IFNULL(MAX(supplier_id), 100000) as max_id FROM suppliers";
            $max_result = $conn->query($sql_max_id);
            $max_row = $max_result->fetch_assoc();
            $new_supplier_id = $max_row['max_id'] + 1;

            // ตรวจสอบชื่อบริษัทซ้ำ
            $stmt_check = $conn->prepare("SELECT supplier_id FROM suppliers WHERE co_name = ?");
            $stmt_check->bind_param("s", $co_name);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception('ชื่อบริษัท "' . htmlspecialchars($co_name) . '" นี้มีอยู่แล้วในระบบ');
            }
            $stmt_check->close();

            //  สร้าง Address
            $sql_max_addr = "SELECT IFNULL(MAX(address_id), 0) as max_addr_id FROM addresses";
            $res_addr = $conn->query($sql_max_addr);
            $new_address_id = $res_addr->fetch_assoc()['max_addr_id'] + 1;

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

            // บันทึกข้อมูล Supplier
            $sql_insert = "INSERT INTO suppliers (
                                supplier_id, co_name, tax_id, 
                                contact_firstname, contact_lastname, 
                                supplier_email, supplier_phone_no, 
                                prefixs_prefix_id, Addresses_address_id, 
                                create_at, update_at
                           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param(
                "issssssii",
                $new_supplier_id,
                $co_name,
                $tax_id,
                $contact_firstname,
                $contact_lastname,
                $supplier_email,
                $supplier_phone_no,
                $prefixs_prefix_id,
                $new_address_id
            );

            if (!$stmt_insert->execute()) {
                throw new Exception("บันทึกข้อมูล Supplier ล้มเหลว: " . $stmt_insert->error);
            }
            $stmt_insert->close();

            // ถ้าสำเร็จทั้งหมด
            $conn->commit();

            $_SESSION['success'] = "เพิ่ม Supplier '" . htmlspecialchars($co_name) . "' (รหัส: $new_supplier_id) เรียบร้อยแล้ว";

            $redirect_url_on_save = $_POST['return_url'] ?? 'supplier.php';
            header("Location: " . $redirect_url_on_save);
            exit();
        } catch (Exception $e) {
            //  ถ้ายกเลิก
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มข้อมูลผู้จัดจำหน่าย</title>
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
                    <h4 class="mb-4"><i class="bi bi-truck me-2"></i>เพิ่มข้อมูลผู้จัดจำหน่าย</h4>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="add_supplier.php" id="supplierForm" novalidate>

                        <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">

                        <div class="form-section">
                            <h5>ข้อมูลผู้จัดจำหน่าย</h5>
                            <table>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label required-label">ชื่อบริษัท</label>
                                    </td>
                                    <td>
                                        <input type="text" name="co_name" class="form-control" required style="width: 300px;"
                                            placeholder="กรอกชื่อบริษัท" value="<?= htmlspecialchars($_POST['co_name'] ?? '') ?>">
                                        <div class="error-feedback">กรุณากรอกชื่อบริษัท</div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">เลขผู้เสียภาษี</label>
                                    </td>
                                    <td>
                                        <input type="text" name="tax_id" id="tax_id" class="form-control" style="width: 300px;"
                                            placeholder="กรอกเลขทะเบียนผู้เสียภาษี (ตัวเลขเท่านั้น)"
                                            value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>"
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
                                                    $selected = ($_POST['prefixs_prefix_id'] ?? '') == $p['prefix_id'] ? 'selected' : '';
                                                    echo "<option value='{$p['prefix_id']}' $selected>" . htmlspecialchars($p['prefix_th']) . "</option>";
                                                }
                                                ?>
                                            </select>

                                            <input type="text" name="contact_firstname" class="form-control"
                                                placeholder="กรอกชื่อผู้ติดต่อ" style="width: 190px;"
                                                value="<?= htmlspecialchars($_POST['contact_firstname'] ?? '') ?>">
                                        </div>
                                    </td>

                                    <td class="label-col">
                                        <label class="form-label ms-3">นามสกุล</label>
                                    </td>
                                    <td>
                                        <input type="text" name="contact_lastname" class="form-control"
                                            placeholder="กรอกนามสกุลผู้ติดต่อ" style="width: 300px;"
                                            value="<?= htmlspecialchars($_POST['contact_lastname'] ?? '') ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">
                                        <label class="form-label">เบอร์โทร</label>
                                    </td>
                                    <td>
                                        <input type="text" name="supplier_phone_no" id="supplier_phone_no"
                                            class="form-control" placeholder="0xxxxxxxxx (10 หลัก)"
                                            style="width: 300px;" value="<?= htmlspecialchars($_POST['supplier_phone_no'] ?? '') ?>"
                                            maxlength="10">
                                        <div class="error-feedback" id="phone_error">กรุณากรอกตัวเลขให้ถูกต้อง (ขึ้นต้น 02,05,06,08,09)</div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">อีเมล</label>
                                    </td>
                                    <td>
                                        <input type="email" name="supplier_email" class="form-control" placeholder="กรอกอีเมล"
                                            style="width: 300px;" value="<?= htmlspecialchars($_POST['supplier_email'] ?? '') ?>"
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
                                                style="width: 140px;" value="<?= htmlspecialchars($_POST['home_no'] ?? '') ?>">
                                            <input type="text" name="moo" class="form-control" placeholder="หมู่ที่"
                                                style="width: 150px;" value="<?= htmlspecialchars($_POST['moo'] ?? '') ?>">
                                        </div>
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">ซอย / หมู่บ้าน</label>
                                    </td>
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
                                    <td class="label-col">
                                        <label class="form-label">ถนน</label>
                                    </td>
                                    <td>
                                        <input type="text" name="road" class="form-control" placeholder="ถนน"
                                            style="width: 300px;" value="<?= htmlspecialchars($_POST['road'] ?? '') ?>">
                                    </td>
                                    <td class="label-col">
                                        <label class="form-label ms-3">รหัสไปรษณีย์</label>
                                    </td>
                                    <td>
                                        <input type="text" id="zip_code" class="form-control bg-light"
                                            placeholder="รอเลือกตำบล" style="width: 300px;" readonly>
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
                                                echo "<option value='{$p['province_id']}'>" . htmlspecialchars($p['province_name_th']) . "</option>";
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
                            <button type="submit" class="btn btn-add">
                                <i class="bi bi-save-fill me-1"></i> บันทึก
                            </button>

                            <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-outline-secondary">
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

            // ข้อมูล Address จาก PHP
            const districts = <?php echo json_encode($all_districts); ?>;
            const subdistricts = <?php echo json_encode($all_subdistricts); ?>;

            const provinceSelect = document.getElementById('province');
            const districtSelect = document.getElementById('district');
            const subdistrictSelect = document.getElementById('subdistrict');
            const zipCodeInput = document.getElementById('zip_code'); // [แก้ไข] ช่อง Zip Code

            // ฟังก์ชันจำกัดให้พิมพ์แค่ตัวเลข
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


            // Dropdown จังหวัด -> อำเภอ
            if (provinceSelect) {
                provinceSelect.addEventListener('change', function() {
                    const provinceId = this.value;
                    districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                    zipCodeInput.value = ''; // Reset Zip Code

                    districtSelect.classList.remove('is-invalid');
                    subdistrictSelect.classList.remove('is-invalid');

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

            // Dropdown อำเภอ -> ตำบล
            if (districtSelect) {
                districtSelect.addEventListener('change', function() {
                    const districtId = this.value;
                    subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                    zipCodeInput.value = ''; // Reset Zip Code
                    subdistrictSelect.classList.remove('is-invalid');

                    if (districtId) {
                        subdistricts.forEach(subdistrict => {
                            if (subdistrict.districts_district_id == districtId) {
                                const option = document.createElement('option');
                                option.value = subdistrict.subdistrict_id;
                                option.textContent = subdistrict.subdistrict_name_th;
                                // เก็บ Zip Code ไว้ใน data attribute
                                option.dataset.zip = subdistrict.zip_code;
                                subdistrictSelect.appendChild(option);
                            }
                        });
                    }
                });
            }

            // เมื่อเลือกตำบล -> แสดงรหัสไปรษณีย์
            if (subdistrictSelect) {
                subdistrictSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.dataset.zip) {
                        zipCodeInput.value = selectedOption.dataset.zip;
                    } else {
                        zipCodeInput.value = '';
                    }
                });
            }

            // Bootstrap Validation & Custom Logic
            const form = document.getElementById('supplierForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    let isValid = true;

                    // ตรวจสอบเบอร์โทร (Validation JS)
                    const phoneInput = document.getElementById('supplier_phone_no');
                    const phoneError = document.getElementById('phone_error');
                    if (phoneInput.value) {
                        // ขึ้นต้นด้วย 02,05,06,08,09 และมีความยาวรวม 9-10 หลัก
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

                    //  ตรวจสอบฟอร์มทั่วไป (required fields)
                    if (!form.checkValidity() || !isValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    form.classList.add('was-validated');

                    [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                        if (!select.value) {
                            select.classList.add('is-invalid');
                        } else {
                            select.classList.remove('is-invalid');
                        }
                    });

                }, false);

                // ซ่อน/แสดง .is-invalid เมื่อมีการเลือก
                [provinceSelect, districtSelect, subdistrictSelect].forEach(select => {
                    select.addEventListener('change', function() {
                        if (this.value) {
                            this.classList.remove('is-invalid');
                        } else {
                            if (form.classList.contains('was-validated')) {
                                this.classList.add('is-invalid');
                            }
                        }
                    });
                });
            }
        })();
    </script>
</body>

</html>