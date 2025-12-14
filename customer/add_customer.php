<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_customer');

// [แก้ไข 1] รับค่า Shop ID จาก Session
$shop_id = $_SESSION['shop_id'];

// -----------------------------------------------------------------------------
//จัดการเส้นทางย้อนกลับ (Return URL)
// -----------------------------------------------------------------------------
// รับค่าจาก URL ถ้าไม่มีให้กลับไปหน้ารายชื่อลูกค้า
$return_url = isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'customer_list.php';

// -----------------------------------------------------------------------------
// AJAX HANDLER (สำหรับดึงข้อมูลที่อยู่)
// -----------------------------------------------------------------------------
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $data = [];

    if ($action === 'get_provinces') {
        $sql = "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_districts') {
        $sql = "SELECT district_id, district_name_th FROM districts WHERE provinces_province_id = $id ORDER BY district_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    } elseif ($action === 'get_subdistricts') {
        $sql = "SELECT subdistrict_id, subdistrict_name_th, zip_code FROM subdistricts WHERE districts_district_id = $id ORDER BY subdistrict_name_th";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

// -----------------------------------------------------------------------------
// HANDLE FORM SUBMIT (บันทึกข้อมูล)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $prefix_id = $_POST['prefix_id'];
    $fname_th  = trim($_POST['firstname_th']);
    $lname_th  = trim($_POST['lastname_th']);
    $phone     = trim($_POST['cs_phone_no']);
    $fname_en  = trim($_POST['firstname_en']);
    $lname_en  = trim($_POST['lastname_en']);
    $email     = trim($_POST['cs_email']);
    $line_id   = trim($_POST['cs_line_id']);
    $national  = trim($_POST['cs_national_id']);

    // ข้อมูลที่อยู่
    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $subdist_id = (int)$_POST['subdistrict_id'];

    // Validation
    if (empty($fname_th) || empty($lname_th) || empty($phone)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลสำคัญ (ชื่อ, นามสกุล, เบอร์โทร)";
    } elseif (empty($subdist_id)) {
        $_SESSION['error'] = "กรุณาเลือกที่อยู่ให้ครบถ้วน (จังหวัด/อำเภอ/ตำบล)";
    } else {
        mysqli_autocommit($conn, false);
        try {
            // รหัสลูกค้า
            $res_cs = mysqli_query($conn, "SELECT IFNULL(MAX(cs_id), 100000) + 1 as next_id FROM customers");
            $cs_id = mysqli_fetch_assoc($res_cs)['next_id'];

            // รหัสที่อยู่
            $res_addr = mysqli_query($conn, "SELECT IFNULL(MAX(address_id), 0) + 1 as next_id FROM addresses");
            $addr_id = mysqli_fetch_assoc($res_addr)['next_id'];

            // บันทึกที่อยู่ (Address)
            $sql_addr = "INSERT INTO addresses (address_id, home_no, moo, soi, road, subdistricts_subdistrict_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("issssi", $addr_id, $home_no, $moo, $soi, $road, $subdist_id);
            if (!$stmt->execute()) throw new Exception("บันทึกที่อยู่ไม่สำเร็จ");
            $stmt->close();

            // [แก้ไข 2] เพิ่ม shop_info_shop_id ลงใน INSERT
            $sql_cus = "INSERT INTO customers (
                            cs_id, cs_national_id, firstname_th, lastname_th, 
                            firstname_en, lastname_en, cs_phone_no, cs_email, cs_line_id, 
                            prefixs_prefix_id, Addresses_address_id, shop_info_shop_id, create_at, update_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt2 = $conn->prepare($sql_cus);
            // เพิ่ม type 'i' และตัวแปร $shop_id
            $stmt2->bind_param(
                "issssssssiis",
                $cs_id,
                $national,
                $fname_th,
                $lname_th,
                $fname_en,
                $lname_en,
                $phone,
                $email,
                $line_id,
                $prefix_id,
                $addr_id,
                $shop_id 
            );

            if (!$stmt2->execute()) throw new Exception("บันทึกข้อมูลลูกค้าไม่สำเร็จ");
            $stmt2->close();

            // สำเร็จ
            mysqli_commit($conn);
            $_SESSION['success'] = "เพิ่มลูกค้าคุณ $fname_th เรียบร้อยแล้ว (รหัส: $cs_id)";

            // เด้งกลับไปหน้าก่อนหน้า
            header("Location: $return_url");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงคำนำหน้าชื่อมาเตรียมไว้
$prefixes = mysqli_query($conn, "SELECT * FROM prefixs WHERE is_active = 1");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เพิ่มลูกค้าใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container {
            max-width: 900px;
            margin-top: 30px;
        }

        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .header-title {
            color: <?= $theme_color ?>;
            font-weight: bold;
        }

        .required {
            color: #dc3545;
            margin-left: 3px;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container">
                    <div class="card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="header-title"><i class="fas fa-user-plus me-2"></i>เพิ่มลูกค้าใหม่</h4>
                            <a href="<?= htmlspecialchars($return_url) ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i> ยกเลิก/ย้อนกลับ
                            </a>
                        </div>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'];
                                                                                unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_customer.php?return_to=<?= urlencode($return_url) ?>" class="needs-validation" novalidate>

                            <h6 class="text-muted border-bottom pb-2 mb-3">ข้อมูลส่วนตัว</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">คำนำหน้า <span class="required">*</span></label>
                                    <select name="prefix_id" class="form-select" required>
                                        <option value="">เลือก</option>
                                        <?php foreach ($prefixes as $p): ?>
                                            <option value="<?= $p['prefix_id'] ?>" <?= $p['prefix_id'] == 100002 ? 'selected' : '' ?>>
                                                <?= $p['prefix_th'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">ชื่อ (ไทย) <span class="required">*</span></label>
                                    <input type="text" name="firstname_th" class="form-control" required placeholder="เช่น สมชาย">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล (ไทย) <span class="required">*</span></label>
                                    <input type="text" name="lastname_th" class="form-control" required placeholder="เช่น ใจดี">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ชื่อ (อังกฤษ)</label>
                                    <input type="text" name="firstname_en" class="form-control" placeholder="e.g. Somchai">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">นามสกุล (อังกฤษ)</label>
                                    <input type="text" name="lastname_en" class="form-control" placeholder="e.g. Jaidee">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เบอร์โทรศัพท์ <span class="required">*</span></label>
                                    <input type="tel" name="cs_phone_no" class="form-control" required maxlength="10" placeholder="08xxxxxxxx">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="cs_email" class="form-control" placeholder="name@example.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เลขบัตรประชาชน</label>
                                    <input type="text" name="cs_national_id" class="form-control" maxlength="13" placeholder="13 หลัก (ถ้ามี)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Line ID</label>
                                    <input type="text" name="cs_line_id" class="form-control" placeholder="@lineid">
                                </div>
                            </div>

                            <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">ที่อยู่</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">บ้านเลขที่</label>
                                    <input type="text" name="home_no" class="form-control">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หมู่ที่</label>
                                    <input type="text" name="moo" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ซอย</label>
                                    <input type="text" name="soi" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ถนน</label>
                                    <input type="text" name="road" class="form-control">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">จังหวัด <span class="required">*</span></label>
                                    <select id="province" class="form-select" required onchange="loadDistricts(this.value)">
                                        <option value="">-- เลือกจังหวัด --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อำเภอ/เขต <span class="required">*</span></label>
                                    <select id="district" class="form-select" required onchange="loadSubdistricts(this.value)" disabled>
                                        <option value="">-- เลือกอำเภอ --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ตำบล/แขวง <span class="required">*</span></label>
                                    <select id="subdistrict" name="subdistrict_id" class="form-select" required onchange="updateZipcode(this)" disabled>
                                        <option value="">-- เลือกตำบล --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">รหัสไปรษณีย์</label>
                                    <input type="text" id="zipcode" class="form-control bg-light" readonly>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-success px-5">
                                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Form Validation ---
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // --- Address AJAX Logic ---

        // โหลดจังหวัดทันทีที่เข้าหน้าเว็บ
        window.onload = function() {
            fetchData('get_provinces', 0, 'province');
        }

        // ฟังก์ชันกลางสำหรับดึงข้อมูล
        function fetchData(action, id, targetId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);

            fetch('add_customer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById(targetId);

                    // Reset Dropdown ปลายทาง
                    if (targetId === 'district') {
                        select.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
                        document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('subdistrict').disabled = true;
                        document.getElementById('zipcode').value = '';
                    } else if (targetId === 'subdistrict') {
                        select.innerHTML = '<option value="">-- เลือกตำบล --</option>';
                        document.getElementById('zipcode').value = '';
                    }

                    // เติมข้อมูลใหม่
                    data.forEach(item => {
                        let option = document.createElement('option');
                        if (action === 'get_provinces') {
                            option.value = item.province_id;
                            option.text = item.province_name_th;
                        } else if (action === 'get_districts') {
                            option.value = item.district_id;
                            option.text = item.district_name_th;
                        } else if (action === 'get_subdistricts') {
                            option.value = item.subdistrict_id;
                            option.text = item.subdistrict_name_th;
                            option.dataset.zip = item.zip_code; // ฝัง zip ไว้ใน option
                        }
                        select.add(option);
                    });

                    // เปิดใช้งานถ้ามีข้อมูล
                    if (data.length > 0) select.disabled = false;
                })
                .catch(error => console.error('Error:', error));
        }

        // เมื่อเลือกจังหวัด -> โหลดอำเภอ
        function loadDistricts(provinceId) {
            if (provinceId) fetchData('get_districts', provinceId, 'district');
        }

        // เมื่อเลือกอำเภอ -> โหลดตำบล
        function loadSubdistricts(districtId) {
            if (districtId) fetchData('get_subdistricts', districtId, 'subdistrict');
        }

        // เมื่อเลือกตำบล -> แสดงรหัสไปรษณีย์
        function updateZipcode(select) {
            const zip = select.options[select.selectedIndex].dataset.zip;
            document.getElementById('zipcode').value = zip || '';
        }
    </script>

</body>

</html>