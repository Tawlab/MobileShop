<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'edit_customer');

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
// GET CURRENT DATA
// -----------------------------------------------------------------------------
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบรหัสลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$cs_id = (int)$_GET['id'];

// ดึงข้อมูลลูกค้า + ที่อยู่ (เชื่อมโยงไปถึงจังหวัด)
$sql = "SELECT c.*, 
               a.address_id, a.home_no, a.moo, a.soi, a.road, 
               sd.subdistrict_id, d.district_id, p.province_id, sd.zip_code
        FROM customers c
        LEFT JOIN addresses a ON c.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts d ON sd.districts_district_id = d.district_id
        LEFT JOIN provinces p ON d.provinces_province_id = p.province_id
        WHERE c.cs_id = $cs_id";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลลูกค้า";
    header('Location: customer_list.php');
    exit;
}

$prefixes = mysqli_query($conn, "SELECT * FROM prefixs WHERE is_active = 1");

// -----------------------------------------------------------------------------
// HANDLE UPDATE (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า
    $prefix_id = $_POST['prefix_id'];
    $fname_th  = trim($_POST['firstname_th']);
    $lname_th  = trim($_POST['lastname_th']);
    $phone     = trim($_POST['cs_phone_no']);
    $fname_en  = trim($_POST['firstname_en']);
    $lname_en  = trim($_POST['lastname_en']);
    $email     = trim($_POST['cs_email']);
    $line_id   = trim($_POST['cs_line_id']);
    $national  = trim($_POST['cs_national_id']);

    $addr_id   = $data['address_id']; 
    $home_no   = trim($_POST['home_no']);
    $moo       = trim($_POST['moo']);
    $soi       = trim($_POST['soi']);
    $road      = trim($_POST['road']);
    $subdist_id = (int)$_POST['subdistrict_id'];

    if (empty($fname_th) || empty($lname_th) || empty($phone)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลสำคัญให้ครบ";
    } else {
        mysqli_autocommit($conn, false);
        try {
            // อัปเดตที่อยู่
            $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, subdistricts_subdistrict_id=? WHERE address_id=?";
            $stmt = $conn->prepare($sql_addr);
            $stmt->bind_param("ssssii", $home_no, $moo, $soi, $road, $subdist_id, $addr_id);
            $stmt->execute();

            // อัปเดตข้อมูลลูกค้า
            $sql_cus = "UPDATE customers SET 
                        cs_national_id=?, firstname_th=?, lastname_th=?, firstname_en=?, lastname_en=?, 
                        cs_phone_no=?, cs_email=?, cs_line_id=?, prefixs_prefix_id=?, update_at=NOW() 
                        WHERE cs_id=?";
            $stmt2 = $conn->prepare($sql_cus);
            $stmt2->bind_param("ssssssssii", $national, $fname_th, $lname_th, $fname_en, $lname_en, $phone, $email, $line_id, $prefix_id, $cs_id);
            $stmt2->execute();

            mysqli_commit($conn);
            $_SESSION['success'] = "แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว";
            header("Location: customer_list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แก้ไขข้อมูลลูกค้า - <?= htmlspecialchars($data['firstname_th']) ?></title>
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
                            <h4 class="header-title"><i class="fas fa-user-edit"></i> แก้ไขข้อมูลลูกค้า</h4>
                            <a href="customer_list.php" class="btn btn-secondary btn-sm">ยกเลิก/กลับ</a>
                        </div>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'];
                                                            unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <h6 class="text-muted border-bottom pb-2 mb-3">ข้อมูลส่วนตัว</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">คำนำหน้า</label>
                                    <select name="prefix_id" class="form-select" required>
                                        <?php foreach ($prefixes as $p): ?>
                                            <option value="<?= $p['prefix_id'] ?>" <?= $p['prefix_id'] == $data['prefixs_prefix_id'] ? 'selected' : '' ?>>
                                                <?= $p['prefix_th'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">ชื่อ (ไทย) <span class="text-danger">*</span></label>
                                    <input type="text" name="firstname_th" class="form-control" value="<?= htmlspecialchars($data['firstname_th']) ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล (ไทย) <span class="text-danger">*</span></label>
                                    <input type="text" name="lastname_th" class="form-control" value="<?= htmlspecialchars($data['lastname_th']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ชื่อ (อังกฤษ)</label>
                                    <input type="text" name="firstname_en" class="form-control" value="<?= htmlspecialchars($data['firstname_en']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">นามสกุล (อังกฤษ)</label>
                                    <input type="text" name="lastname_en" class="form-control" value="<?= htmlspecialchars($data['lastname_en']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                    <input type="text" name="cs_phone_no" class="form-control" value="<?= htmlspecialchars($data['cs_phone_no']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="cs_email" class="form-control" value="<?= htmlspecialchars($data['cs_email']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เลขบัตรประชาชน</label>
                                    <input type="text" name="cs_national_id" class="form-control" value="<?= htmlspecialchars($data['cs_national_id']) ?>" maxlength="13">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Line ID</label>
                                    <input type="text" name="cs_line_id" class="form-control" value="<?= htmlspecialchars($data['cs_line_id']) ?>">
                                </div>
                            </div>

                            <h6 class="text-muted border-bottom pb-2 mb-3 mt-4">ที่อยู่</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">บ้านเลขที่</label>
                                    <input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($data['home_no']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หมู่ที่</label>
                                    <input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($data['moo']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ซอย</label>
                                    <input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($data['soi']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ถนน</label>
                                    <input type="text" name="road" class="form-control" value="<?= htmlspecialchars($data['road']) ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                    <select id="province" class="form-select" onchange="loadDistricts(this.value)" required>
                                        <option value="">-- เลือกจังหวัด --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">อำเภอ/เขต <span class="text-danger">*</span></label>
                                    <select id="district" class="form-select" onchange="loadSubdistricts(this.value)" required>
                                        <option value="">-- เลือกอำเภอ --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ตำบล/แขวง <span class="text-danger">*</span></label>
                                    <select id="subdistrict" name="subdistrict_id" class="form-select" onchange="updateZipcode(this)" required>
                                        <option value="">-- เลือกตำบล --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">รหัสไปรษณีย์</label>
                                    <input type="text" id="zipcode" class="form-control bg-light" value="<?= $data['zip_code'] ?>" readonly>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-warning px-5 text-white"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </div>
                </div>

                <input type="hidden" id="old_province" value="<?= $data['province_id'] ?>">
                <input type="hidden" id="old_district" value="<?= $data['district_id'] ?>">
                <input type="hidden" id="old_subdistrict" value="<?= $data['subdistrict_id'] ?>">
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // โหลดข้อมูลตอนเข้าเว็บ และเลือกค่าเดิม
        window.onload = function() {
            const oldProv = document.getElementById('old_province').value;

            // โหลดจังหวัดทั้งหมด
            fetchData('get_provinces', 0, 'province', oldProv, () => {
                if (oldProv) {
                    const oldDist = document.getElementById('old_district').value;
                    loadDistricts(oldProv, oldDist, () => {
                        if (oldDist) {
                            const oldSub = document.getElementById('old_subdistrict').value;
                            loadSubdistricts(oldDist, oldSub);
                        }
                    });
                }
            });
        }

        // Helper: Fetch Data
        function fetchData(action, id, targetId, selectedValue = null, callback = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('id', id);

            fetch('edit_customer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById(targetId);
                    select.innerHTML = select.options[0].outerHTML; 

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
                            option.dataset.zip = item.zip_code;
                        }

                        if (selectedValue && option.value == selectedValue) {
                            option.selected = true;
                        }
                        select.add(option);
                    });

                    if (callback) callback();
                });
        }

        function loadDistricts(provId, selectedVal = null, callback = null) {
            document.getElementById('district').innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
            document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
            document.getElementById('zipcode').value = '';

            if (provId) fetchData('get_districts', provId, 'district', selectedVal, callback);
        }

        function loadSubdistricts(distId, selectedVal = null) {
            document.getElementById('subdistrict').innerHTML = '<option value="">-- เลือกตำบล --</option>';
            document.getElementById('zipcode').value = '';

            if (distId) fetchData('get_subdistricts', distId, 'subdistrict', selectedVal, () => {
                if (selectedVal) {
                    const subSelect = document.getElementById('subdistrict');
                    updateZipcode(subSelect);
                }
            });
        }

        function updateZipcode(select) {
            const zip = select.options[select.selectedIndex].dataset.zip;
            document.getElementById('zipcode').value = zip || '';
        }
    </script>

</body>

</html>