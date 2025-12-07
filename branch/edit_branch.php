<?php
session_start();
require '../config/config.php';

// ตรวจสอบสิทธิ์
checkPageAccess($conn, 'edit_branch');
 
// รับ ID สาขา
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($branch_id === 0) {
    $_SESSION['error'] = "ไม่พบรหัสสาขา";
    header('Location: branch.php');
    exit;
}

// ดึงข้อมูลสาขา + ที่อยู่
$sql_data = "SELECT b.*, a.*, 
                    s.subdistrict_name_th, s.zip_code,
                    s.districts_district_id, 
                    d.provinces_province_id 
             FROM branches b
             JOIN addresses a ON b.Addresses_address_id = a.address_id
             LEFT JOIN subdistricts s ON a.subdistricts_subdistrict_id = s.subdistrict_id
             LEFT JOIN districts d ON s.districts_district_id = d.district_id
             WHERE b.branch_id = $branch_id";

$data_result = mysqli_query($conn, $sql_data);
$data = mysqli_fetch_assoc($data_result);

if (!$data) {
    $_SESSION['error'] = "ไม่พบข้อมูลสาขา";
    header('Location: branch.php');
    exit;
}

// สำหรับ Dropdown
$shop_result = mysqli_query($conn, "SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
$provinces_result = mysqli_query($conn, "SELECT province_id, province_name_th FROM provinces ORDER BY province_name_th");
$districts_result = mysqli_query($conn, "SELECT district_id, district_name_th, provinces_province_id FROM districts ORDER BY district_name_th");
$subdistricts_result = mysqli_query($conn, "SELECT subdistrict_id, subdistrict_name_th, districts_district_id, zip_code FROM subdistricts ORDER BY subdistrict_name_th");

//Form Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $b_id = (int)$_POST['branch_id'];
    $a_id = (int)$_POST['address_id'];

    $branch_code = mysqli_real_escape_string($conn, trim($_POST['branch_code']));
    $branch_name = mysqli_real_escape_string($conn, trim($_POST['branch_name']));
    $branch_phone = mysqli_real_escape_string($conn, trim($_POST['branch_phone']));
    $shop_id = (int)$_POST['shop_info_shop_id'];

    $home_no = mysqli_real_escape_string($conn, trim($_POST['home_no']));
    $moo = mysqli_real_escape_string($conn, trim($_POST['moo']));
    $soi = mysqli_real_escape_string($conn, trim($_POST['soi']));
    $road = mysqli_real_escape_string($conn, trim($_POST['road']));
    $village = mysqli_real_escape_string($conn, trim($_POST['village']));
    $subdist_id = (int)$_POST['subdistricts_subdistrict_id'];

    // Validation
    if (empty($branch_name) || empty($subdist_id)) {
        $error_message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif (!empty($branch_phone) && !preg_match('/^(02|05|06|08|09)[0-9]{8}$/', $branch_phone)) {
        $error_message = "เบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)";
    } else {
        mysqli_autocommit($conn, false);
        try {
            $sql_addr = "UPDATE addresses SET home_no=?, moo=?, soi=?, road=?, village=?, subdistricts_subdistrict_id=? WHERE address_id=?";
            $stmt_a = $conn->prepare($sql_addr);
            $stmt_a->bind_param("sssssii", $home_no, $moo, $soi, $road, $village, $subdist_id, $a_id);
            if (!$stmt_a->execute()) throw new Exception("อัปเดตที่อยู่ไม่สำเร็จ");
            $stmt_a->close();
            $sql_br = "UPDATE branches SET branch_code=?, branch_name=?, branch_phone=?, shop_info_shop_id=?, update_at=NOW() WHERE branch_id=?";
            $stmt_b = $conn->prepare($sql_br);
            $stmt_b->bind_param("sssii", $branch_code, $branch_name, $branch_phone, $shop_id, $b_id);
            if (!$stmt_b->execute()) throw new Exception("อัปเดตสาขาไม่สำเร็จ");
            $stmt_b->close();

            mysqli_commit($conn);
            $_SESSION['success'] = "แก้ไขข้อมูลสาขาเรียบร้อยแล้ว";
            header('Location: branch.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขสาขา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        .btn-save {
            background-color: <?= $btn_edit_color ?>;
            color: black;
        }

        /* สำหรับ Error message */
        .error-feedback {
            font-size: 0.875em;
            color: #dc3545;
            display: none;
            margin-top: 0.25rem;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }
    </style>
</head>

<body>

    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>

        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container" style="max-width: 1000px;">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0" style="color: <?= $theme_color ?>;"><i class="fas fa-edit me-2"></i> แก้ไขข้อมูลสาขา</h3>
                        <a href="branch.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก</a>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger shadow-sm border-0 mb-4"><i class="fas fa-exclamation-triangle me-2"></i> <?= $error_message ?></div>
                    <?php endif; ?>

                    <form method="POST" id="editBranchForm">
                        <input type="hidden" name="branch_id" value="<?= $data['branch_id'] ?>">
                        <input type="hidden" name="address_id" value="<?= $data['Addresses_address_id'] ?>">

                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 text-secondary">ข้อมูลสาขา</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อสาขา <span class="text-danger">*</span></label>
                                        <input type="text" name="branch_name" class="form-control" required value="<?= htmlspecialchars($data['branch_name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">รหัสสาขา</label>
                                        <input type="text" name="branch_code" class="form-control" value="<?= htmlspecialchars($data['branch_code']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                        <input type="text" name="branch_phone" id="branch_phone" class="form-control"
                                            required maxlength="10" placeholder="0xxxxxxxxx (10 หลัก)"
                                            value="<?= htmlspecialchars($data['branch_phone']) ?>">
                                        <div id="phone_error" class="error-feedback">เบอร์โทรไม่ถูกต้อง (ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 02, 05, 06, 08, 09)</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">สังกัดร้านค้า <span class="text-danger">*</span></label>
                                        <select name="shop_info_shop_id" class="form-select" required>
                                            <?php while ($s = mysqli_fetch_assoc($shop_result)): ?>
                                                <option value="<?= $s['shop_id'] ?>" <?= ($data['shop_info_shop_id'] == $s['shop_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($s['shop_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 text-secondary">ที่อยู่สาขา</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">บ้านเลขที่</label>
                                        <input type="text" name="home_no" class="form-control" value="<?= htmlspecialchars($data['home_no']) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">หมู่ที่</label>
                                        <input type="text" name="moo" class="form-control" value="<?= htmlspecialchars($data['moo']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">หมู่บ้าน/อาคาร</label>
                                        <input type="text" name="village" class="form-control" value="<?= htmlspecialchars($data['village']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ซอย</label>
                                        <input type="text" name="soi" class="form-control" value="<?= htmlspecialchars($data['soi']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ถนน</label>
                                        <input type="text" name="road" class="form-control" value="<?= htmlspecialchars($data['road']) ?>">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                        <select id="province" class="form-select" required onchange="loadDistricts(this.value)">
                                            <option value="">-- เลือก --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">อำเภอ/เขต <span class="text-danger">*</span></label>
                                        <select id="district" class="form-select" required onchange="loadSubdistricts(this.value)" disabled>
                                            <option value="">-- เลือก --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ตำบล/แขวง <span class="text-danger">*</span></label>
                                        <select id="subdistrict" name="subdistricts_subdistrict_id" class="form-select" required onchange="updateZipcode(this)" disabled>
                                            <option value="">-- เลือก --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">รหัสไปรษณีย์</label>
                                        <input type="text" id="zipcode" class="form-control bg-light" readonly value="<?= htmlspecialchars($data['zip_code']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-save btn-lg shadow-sm"><i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // โหลดข้อมูล Master Data เป็น JSON
        const provinces = <?php
                            $p_arr = [];
                            while ($p = mysqli_fetch_assoc($provinces_result)) $p_arr[] = $p;
                            echo json_encode($p_arr);
                            ?>;
        const districts = <?php
                            $d_arr = [];
                            while ($d = mysqli_fetch_assoc($districts_result)) $d_arr[] = $d;
                            echo json_encode($d_arr);
                            ?>;
        const subdistricts = <?php
                                $s_arr = [];
                                while ($s = mysqli_fetch_assoc($subdistricts_result)) $s_arr[] = $s;
                                echo json_encode($s_arr);
                                ?>;

        // ค่าเดิมจาก DB
        const oldProv = "<?= $data['provinces_province_id'] ?>";
        const oldDist = "<?= $data['districts_district_id'] ?>";
        const oldSub = "<?= $data['subdistricts_subdistrict_id'] ?>";

        const provinceSelect = document.getElementById('province');
        const districtSelect = document.getElementById('district');
        const subdistrictSelect = document.getElementById('subdistrict');
        const zipInput = document.getElementById('zipcode');

        // ฟังก์ชัน Render Option
        function initProvinces() {
            provinces.forEach(p => {
                const opt = new Option(p.province_name_th, p.province_id);
                if (p.province_id == oldProv) opt.selected = true;
                provinceSelect.add(opt);
            });
            if (oldProv) loadDistricts(oldProv, oldDist);
        }

        function loadDistricts(provId, selectedVal = null) {
            districtSelect.innerHTML = '<option value="">-- เลือก --</option>';
            subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
            zipInput.value = '';
            districtSelect.disabled = true;
            subdistrictSelect.disabled = true;

            if (provId) {
                const filtered = districts.filter(d => d.provinces_province_id == provId);
                filtered.forEach(d => {
                    const opt = new Option(d.district_name_th, d.district_id);
                    if (selectedVal && d.district_id == selectedVal) opt.selected = true;
                    districtSelect.add(opt);
                });
                districtSelect.disabled = false;
                if (selectedVal) loadSubdistricts(selectedVal, oldSub);
            }
        }

        function loadSubdistricts(distId, selectedVal = null) {
            subdistrictSelect.innerHTML = '<option value="">-- เลือก --</option>';
            zipInput.value = '';
            subdistrictSelect.disabled = true;

            if (distId) {
                const filtered = subdistricts.filter(s => s.districts_district_id == distId);
                filtered.forEach(s => {
                    const opt = new Option(s.subdistrict_name_th, s.subdistrict_id);
                    opt.dataset.zip = s.zip_code;
                    if (selectedVal && s.subdistrict_id == selectedVal) {
                        opt.selected = true;
                        zipInput.value = s.zip_code;
                    }
                    subdistrictSelect.add(opt);
                });
                subdistrictSelect.disabled = false;
            }
        }

        function updateZipcode(select) {
            zipInput.value = select.options[select.selectedIndex].dataset.zip || '';
        }

        const phoneInput = document.getElementById("branch_phone");
        const phoneError = document.getElementById("phone_error");

        if (phoneInput) {
            phoneInput.addEventListener("input", function() {
                // ลบทุกอย่างที่ไม่ใช่ตัวเลข
                this.value = this.value.replace(/[^0-9]/g, '');

                const value = this.value;
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
                    // ถ้าว่าง ไม่ต้องแสดง Error 
                    phoneError.style.display = "none";
                    phoneInput.classList.remove("is-invalid");
                }
            });
        }

        // ตรวจสอบตอนกด Submit
        const form = document.getElementById('editBranchForm');
        form.addEventListener('submit', function(e) {
            if (phoneInput && phoneInput.classList.contains('is-invalid')) {
                e.preventDefault();
                phoneInput.focus();
            }
        });

        initProvinces();
    </script>
</body>

</html>
<?php mysqli_close($conn); ?>