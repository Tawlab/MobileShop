<?php
session_start();
require '../config/config.php';

// ดึงคำนำหน้าชื่อมาแสดง พร้อม Prefix English
$prefixes = mysqli_query($conn, "SELECT * FROM prefixs WHERE is_active = 1 ORDER BY prefix_id ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .register-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
        }

        .card-header-custom {
            background: #198754;
            color: white;
            padding: 25px;
            text-align: center;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
        }

        .step-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 20px;
            z-index: 1;
            transition: all 0.3s;
        }

        .step-item.active {
            background: #198754;
            color: white;
            box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
        }

        .step-line {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.4s;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-label {
            font-weight: 500;
            font-size: 0.95rem;
        }

        .btn-nav {
            border-radius: 50px;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-nav:hover {
            transform: translateY(-2px);
        }

        .is-valid-custom {
            border-color: #198754 !important;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="register-card mx-auto">
                    <div class="card-header-custom">
                        <h3 class="mb-0"><i class="bi bi-person-badge-fill me-2"></i>ลงทะเบียนพาร์ทเนอร์</h3>
                        <p class="mb-0 opacity-75">สร้างบัญชีเพื่อเริ่มต้นบริหารร้านค้าของคุณ</p>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="step-indicator">
                                <div class="step-line"></div>
                                <div class="step-item active" id="indicator1">1</div>
                                <div class="step-item" id="indicator2">2</div>
                            </div>
                            <small class="text-muted fw-bold" id="stepLabel">ข้อมูลผู้ดูแลระบบ</small>
                        </div>

                        <form id="registerForm" novalidate>
                            <input type="hidden" name="existing_shop_id" id="existing_shop_id" value="">

                            <div class="form-step active" id="step1">
                                <h5 class="text-success mb-3 border-bottom pb-2"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลผู้ดูแลระบบ</h5>

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">คำนำหน้า (TH)</label>
                                        <select class="form-select select2" name="prefix_id" id="prefix_id" onchange="updatePrefixEn()">
                                            <?php while ($row = mysqli_fetch_assoc($prefixes)): ?>
                                                <option value="<?= $row['prefix_id'] ?>" data-en="<?= htmlspecialchars($row['prefix_en'] ?? '') ?>">
                                                    <?= $row['prefix_th'] ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ชื่อ (ภาษาไทย) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control input-thai" name="firstname_th" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">นามสกุล (ภาษาไทย) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control input-thai" name="lastname_th" required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">คำนำหน้า (EN)</label>
                                        <input type="text" class="form-control bg-light" id="prefix_en" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">First Name (EN) <span class="text-muted small">(ไม่บังคับ)</span></label>
                                        <input type="text" class="form-control input-eng" name="firstname_en">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Last Name (EN) <span class="text-muted small">(ไม่บังคับ)</span></label>
                                        <input type="text" class="form-control input-eng" name="lastname_en">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรศัพท์มือถือ <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control check-phone" name="emp_phone" maxlength="10" required placeholder="08XXXXXXXX">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">อีเมลส่วนตัว <span class="text-muted small">(ไม่บังคับ / หากกรอกต้องยืนยัน OTP)</span></label>
                                        <div class="input-group">
                                            <input type="email" class="form-control check-email" name="emp_email" id="emp_email" placeholder="example@email.com">
                                            <button type="button" id="btnSendOTP" class="btn btn-outline-success" style="display:none;">ส่ง OTP</button>
                                        </div>
                                    </div>

                                    <div id="otpBox" class="col-md-6 offset-md-6 mt-2" style="display:none;">
                                        <div class="p-3 border rounded bg-light shadow-sm">
                                            <label class="small fw-bold text-success mb-2">รหัส OTP 6 หลักได้ถูกส่งไปยังอีเมลของคุณแล้ว</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" id="otp_code" class="form-control" maxlength="6" placeholder="******">
                                                <button type="button" id="btnVerifyOTP" class="btn btn-success">ยืนยัน</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" name="username" id="username" required placeholder="ภาษาอังกฤษ หรือ ตัวเลข (>6 ตัว)">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                            <input type="password" class="form-control" name="password" id="password" required placeholder="ขั้นต่ำ 6 ตัวอักษร มีตัวอักษรและตัวเลข">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="button" class="btn btn-success btn-nav" onclick="validateStep1()">ถัดไป <i class="bi bi-arrow-right ms-1"></i></button>
                                </div>
                            </div>

                            <div class="form-step" id="step2">
                                <h5 class="text-success mb-3 border-bottom pb-2"><i class="bi bi-shop me-2"></i>ข้อมูลร้านค้า / สาขา</h5>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อร้านค้าหลัก <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="shop_name" id="shop_name" required placeholder="ชื่อร้านค้า/บริษัท">
                                        <div id="shop_name_feedback" class="form-text"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อสาขาที่ลงทะเบียน <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_name" id="branch_name" required value="สำนักงานใหญ่">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรร้าน <span class="text-muted small">(ไม่บังคับ หากใช้เบอร์เดียวกับส่วนตัว)</span></label>
                                        <input type="tel" class="form-control" name="shop_phone" id="shop_phone" maxlength="10">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">อีเมลร้านค้า <span class="text-muted small">(ไม่บังคับ / หากกรอกต้องยืนยัน OTP)</span></label>
                                        <div class="input-group">
                                            <input type="email" class="form-control" name="shop_email" id="shop_email" placeholder="shop@email.com">
                                            <button type="button" id="btnSendShopOTP" class="btn btn-outline-success" style="display:none;">ส่ง OTP</button>
                                        </div>
                                    </div>

                                    <div id="shopOtpBox" class="col-md-6 offset-md-6 mt-2" style="display:none;">
                                        <div class="p-3 border rounded bg-light shadow-sm">
                                            <label class="small fw-bold text-success mb-2">รหัส OTP 6 หลักถูกส่งไปยังอีเมลร้านค้าแล้ว</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" id="shop_otp_code" class="form-control" maxlength="6" placeholder="******">
                                                <button type="button" id="btnVerifyShopOTP" class="btn btn-success">ยืนยัน</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">เลขประจำตัวผู้เสียภาษี <span class="text-muted small">(ไม่บังคับ)</span></label>
                                        <input type="text" class="form-control" name="shop_tax_id" id="shop_tax_id" maxlength="13" placeholder="ระบุ 13 หลัก">
                                    </div>
                                </div>

                                <div class="col-md-12 mb-2">
                                    <div class="accordion" id="addressAccordion">
                                        <div class="accordion-item border-0 bg-light rounded-3">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed bg-light text-success fw-bold rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddress">
                                                    <i class="bi bi-geo-alt-fill me-2"></i> ระบุรายละเอียดที่อยู่ (บ้านเลขที่/หมู่/ซอย/ถนน) - ไม่บังคับ
                                                </button>
                                            </h2>
                                            <div id="collapseAddress" class="accordion-collapse collapse">
                                                <div class="accordion-body pt-3">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label small">บ้านเลขที่</label>
                                                            <input type="text" class="form-control" name="home_no" id="home_no" placeholder="เช่น 123/45">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small">หมู่ที่</label>
                                                            <input type="text" class="form-control" name="moo" id="moo" placeholder="เช่น 9">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small">หมู่บ้าน / อาคาร</label>
                                                            <input type="text" class="form-control" name="village" id="village" placeholder="เช่น หมู่บ้านสุขสันต์">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">ซอย</label>
                                                            <input type="text" class="form-control" name="soi" id="soi" placeholder="เช่น ซอยสุขุมวิท 1">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small">ถนน</label>
                                                            <input type="text" class="form-control" name="road" id="road" placeholder="เช่น ถนนสุขุมวิท">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-2">
                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label small">จังหวัด <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="province_select" required style="width: 100%">
                                                <option value="">-- เลือกจังหวัด --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">อำเภอ/เขต <span class="text-danger">*</span></label>
                                            <select class="form-select select2" id="district_select" required style="width: 100%" disabled>
                                                <option value="">-- เลือกอำเภอ --</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small">ตำบล/แขวง <span class="text-danger">*</span></label>
                                            <select class="form-select select2" name="subdistrict_id" id="subdistrict_select" required style="width: 100%" disabled>
                                                <option value="">-- เลือกตำบล --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">รหัสไปรษณีย์</label>
                                            <input type="text" class="form-control bg-light" id="zipcode" readonly>
                                        </div>

                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-5">
                                    <button type="button" class="btn btn-secondary btn-nav" onclick="prevStep()"><i class="bi bi-arrow-left me-1"></i> ย้อนกลับ</button>
                                    <button type="submit" class="btn btn-success btn-nav"><i class="bi bi-check-circle me-1"></i> ยืนยันการสมัคร</button>
                                </div>
                            </div>

                        </form>

                        <div class="text-center mt-4">
                            <a href="../global/login.php" class="text-decoration-none text-muted small">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let isEmailVerified = true; // ของ Step 1
        let isShopEmailVerified = true; // เพิ่มใหม่: ของ Step 2 (ค่าเริ่มต้น true เพราะไม่บังคับกรอก)

        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5'
            });
            updatePrefixEn(); // โหลด Prefix Eng ทันที

            // กรองภาษาการพิมพ์
            $('.input-thai').on('input', function() {
                this.value = this.value.replace(/[^ก-๙\s]/g, '');
            });
            $('.input-eng').on('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            });
            $('.check-phone, #shop_tax_id').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            // 1. ตรวจสอบ Username
            $('#username').on('blur', function() {
                let el = $(this);
                let usr = el.val().trim();
                if (usr.length > 0) {
                    $.post('check_availability.php', {
                        action: 'check_username',
                        username: usr
                    }, function(res) {
                        if (res.status === 'invalid' || res.status === 'taken') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('ชื่อผู้ใช้ไม่ถูกต้อง', res.message, 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
            });

            // 2. ตรวจสอบ Password
            $('#password').on('blur', function() {
                let el = $(this);
                let pwd = el.val();
                if (pwd.length > 0) {
                    $.post('check_availability.php', {
                        action: 'check_password',
                        password: pwd
                    }, function(res) {
                        if (res.status === 'invalid') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('รหัสผ่านไม่ปลอดภัย', res.message, 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
            });

            // 3. ตรวจสอบเบอร์โทรซ้ำ
            $('.check-phone').on('blur', function() {
                let el = $(this);
                let phone = el.val().trim();
                // แพทเทิร์น: ต้องขึ้นต้นด้วย 06, 08 หรือ 09 และตามด้วยตัวเลขอีก 8 ตัว (รวมเป็น 10 หลัก)
                const phoneRegex = /^(06|08|09)\d{8}$/;

                if (phone.length > 0) {
                    if (!phoneRegex.test(phone)) {
                        // ถ้าไม่ตรงตามแพทเทิร์น
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 06, 08 หรือ 09 และมี 10 หลัก', 'warning');
                    } else {
                        // ถ้าผ่านแพทเทิร์น ค่อยยิง AJAX ตรวจสอบเบอร์ซ้ำ
                        $.post('check_availability.php', {
                            action: 'check_phone',
                            phone: phone
                        }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid').removeClass('is-valid-custom');
                                Swal.fire('ข้อมูลซ้ำ', res.message || 'เบอร์โทรศัพท์นี้มีในระบบแล้ว', 'warning');
                            } else {
                                el.removeClass('is-invalid').addClass('is-valid-custom');
                            }
                        }, 'json');
                    }
                } else {
                    // กรณีไม่ได้กรอก (ปล่อยว่าง)
                    el.removeClass('is-invalid is-valid-custom');
                }
            });

            // 4. ระบบ OTP และตรวจสอบอีเมลซ้ำ
            $('#emp_email').on('input', function() {
                const email = $(this).val().trim();
                if (email.length > 0) {
                    $('#btnSendOTP').fadeIn();
                    isEmailVerified = false; // หากพิมพ์อีเมล บังคับต้องยืนยัน
                } else {
                    $('#btnSendOTP').fadeOut();
                    $('#otpBox').fadeOut();
                    isEmailVerified = true;
                    $(this).removeClass('is-invalid is-valid-custom');
                }
            });

            $('.check-email').on('blur', function() {
                let el = $(this);
                let email = el.val();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (email.length > 0 && !emailRegex.test(email)) {
                    el.addClass('is-invalid').removeClass('is-valid-custom');
                    Swal.fire('ผิดพลาด', 'รูปแบบอีเมลไม่ถูกต้อง', 'warning');
                } else if (email.length > 0) {
                    $.post('check_availability.php', {
                        action: 'check_email',
                        email: email
                    }, function(res) {
                        if (res.status === 'taken') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('อีเมลซ้ำ', res.message, 'warning');
                            if (el.attr('id') === 'emp_email') $('#btnSendOTP').hide();
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                            if (el.attr('id') === 'emp_email') $('#btnSendOTP').show();
                        }
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
            });

            // ปุ่มส่ง OTP
            $('#btnSendOTP').click(function() {
                const email = $('#emp_email').val();
                if ($('#emp_email').hasClass('is-invalid') || !email) return;

                $(this).prop('disabled', true).text('กำลังส่ง...');
                fetch('send_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            emp_email: email
                        }) // ใช้ API ส่ง OTP ตัวเดิม
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลส่วนตัวของคุณแล้ว', 'success');
                            $('#otpBox').fadeIn();
                        } else Swal.fire('ผิดพลาด', data.message, 'error');
                    }).catch(err => {
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ส่งอีเมลได้', 'error');
                    }).finally(() => {
                        $('#btnSendOTP').prop('disabled', false).html('ส่ง OTP');
                    });
            });

            // ปุ่มยืนยัน OTP
            $('#btnVerifyOTP').click(function() {
                const otp = $('#otp_code').val();
                if (otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลักให้ครบ', 'warning');

                fetch('verify_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            otp: otp
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('สำเร็จ', 'ยืนยันอีเมลสำเร็จ', 'success');
                            isEmailVerified = true;
                            $('#otpBox').fadeOut();
                            $('#btnSendOTP').fadeOut();
                            $('#emp_email').addClass('is-valid-custom').prop('readonly', true);
                        } else Swal.fire('รหัสไม่ถูกต้อง', data.message, 'error');
                    });
            });

            // ตรวจสอบเลขภาษี
            $('#shop_tax_id').on('blur', function() {
                let el = $(this);
                let tax = el.val();
                if (tax.length > 0 && tax.length !== 13) {
                    el.addClass('is-invalid');
                    Swal.fire('รูปแบบผิดพลาด', 'เลขผู้เสียภาษีต้องมี 13 หลัก', 'warning');
                } else if (tax.length === 13) {
                    $.post('check_availability.php', {
                        action: 'check_tax_id',
                        tax_id: tax
                    }, function(res) {
                        if (res.status === 'taken') {
                            el.addClass('is-invalid');
                            Swal.fire('ข้อมูลซ้ำ', res.message, 'warning');
                        } else el.removeClass('is-invalid').addClass('is-valid-custom');
                    }, 'json');
                } else el.removeClass('is-invalid is-valid-custom');
            });

            // ตรวจสอบชื่อร้านและสาขา (เหมือนเดิม)
            $('#shop_name').on('blur', function() {
                let shopName = $(this).val().trim();
                if (shopName.length > 0) {
                    $.post('check_availability.php', {
                        action: 'check_shop_name',
                        shop_name: shopName
                    }, function(data) {
                        if (data.status === 'exists') {
                            Swal.fire({
                                title: 'พบชื่อร้านค้านี้ในระบบ!',
                                html: `มีร้านค้าชื่อ <b>"${data.shop_name}"</b> อยู่แล้ว<br>ต้องการเพิ่มสาขาใหม่ในร้านค้านี้ใช่หรือไม่?`,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: 'ใช่, เพิ่มสาขา',
                                cancelButtonText: 'ไม่, จะเปลี่ยนชื่อใหม่'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $('#existing_shop_id').val(data.shop_id);
                                    if (data.tax_id && data.tax_id !== '-') $('#shop_tax_id').val(data.tax_id).prop('readonly', true);
                                    $('#shop_name').addClass('is-valid-custom');
                                    $('#shop_name_feedback').html('<span class="text-success"><i class="bi bi-check-circle"></i> เชื่อมต่อกับร้านค้าเดิมแล้ว</span>');
                                } else {
                                    $('#existing_shop_id').val('');
                                    $('#shop_tax_id').val('').prop('readonly', false);
                                    $('#shop_name').val('').focus();
                                    $('#shop_name_feedback').text('');
                                }
                            });
                        } else {
                            $('#existing_shop_id').val('');
                            $('#shop_tax_id').prop('readonly', false);
                            $('#shop_name').removeClass('is-invalid');
                            $('#shop_name_feedback').text('');
                        }
                    }, 'json');
                }
            });
            // ---------------------------------------------------------
            // 1. ตรวจสอบเบอร์โทรร้านค้า (shop_phone) + เช็ค Pattern
            // ---------------------------------------------------------
            $('#shop_phone').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, ''); // พิมพ์ได้แค่ตัวเลข
            });

            $('#shop_phone').on('blur', function() {
                let el = $(this);
                let phone = el.val().trim();

                const phoneRegex = /^(06|08|09)\d{8}$/;

                if (phone.length > 0) {
                    if (!phoneRegex.test(phone)) {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรร้านค้าต้องขึ้นต้นด้วย 06, 08 หรือ 09 และมี 10 หลัก', 'warning');
                    } else {
                        $.post('check_availability.php', {
                            action: 'check_phone',
                            phone: phone
                        }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid').removeClass('is-valid-custom');
                                Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์ร้านค้านี้มีในระบบแล้ว', 'warning');
                            } else {
                                el.removeClass('is-invalid').addClass('is-valid-custom');
                            }
                        }, 'json');
                    }
                } else {
                    el.removeClass('is-invalid is-valid-custom'); // ปล่อยว่างได้
                }
            });

            // ---------------------------------------------------------
            // 2. ตรวจสอบเลขประจำตัวผู้เสียภาษี (shop_tax_id) + เช็คสูตร 13 หลัก
            // ---------------------------------------------------------
            // ฟังก์ชันคำนวณความถูกต้องของเลข 13 หลัก (Mod 11)
            function validateThaiTaxID(id) {
                if (id.length !== 13) return false;
                let sum = 0;
                for (let i = 0; i < 12; i++) {
                    sum += parseInt(id.charAt(i)) * (13 - i);
                }
                let check = (11 - (sum % 11)) % 10;
                return check === parseInt(id.charAt(12));
            }

            $('#shop_tax_id').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, ''); // พิมพ์ได้แค่ตัวเลข
            });

            $('#shop_tax_id').on('blur', function() {
                let el = $(this);
                let tax = el.val().trim();

                if (tax.length > 0) {
                    if (!validateThaiTaxID(tax)) {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('รูปแบบผิดพลาด', 'เลขประจำตัวผู้เสียภาษีไม่ถูกต้องตามสูตรคำนวณ', 'error');
                    } else {
                        $.post('check_availability.php', {
                            action: 'check_tax_id',
                            tax_id: tax
                        }, function(res) {
                            if (res.status === 'taken') {
                                el.addClass('is-invalid').removeClass('is-valid-custom');
                                Swal.fire('ข้อมูลซ้ำ', res.message, 'warning');
                            } else {
                                el.removeClass('is-invalid').addClass('is-valid-custom');
                            }
                        }, 'json');
                    }
                } else {
                    el.removeClass('is-invalid is-valid-custom'); // ปล่อยว่างได้
                }
            });

            // ระบบโหลดที่อยู่ (Dropdown)
            $.get('get_locations.php?action=get_provinces', function(data) {
                data.forEach(function(item) {
                    $('#province_select').append(new Option(item.province_name_th, item.province_id));
                });
            });
            $('#province_select').change(function() {
                let id = $(this).val();
                let dist = $('#district_select').empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', true);
                $('#subdistrict_select').empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
                $('#zipcode').val('');
                if (id) {
                    $.get('get_locations.php?action=get_districts&id=' + id, function(data) {
                        data.forEach(function(item) {
                            dist.append(new Option(item.district_name_th, item.district_id));
                        });
                        dist.prop('disabled', false);
                    });
                }
            });
            $('#district_select').change(function() {
                let id = $(this).val();
                let subdist = $('#subdistrict_select').empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
                $('#zipcode').val('');
                if (id) {
                    $.get('get_locations.php?action=get_subdistricts&id=' + id, function(data) {
                        data.forEach(function(item) {
                            let option = new Option(item.subdistrict_name_th, item.subdistrict_id);
                            $(option).attr('data-zip', item.zip_code);
                            subdist.append(option);
                        });
                        subdist.prop('disabled', false);
                    });
                }
            });
            $('#subdistrict_select').change(function() {
                $('#zipcode').val($(this).find(':selected').data('zip') || '');
            });
        });
        // ---------------------------------------------------------
        // 2. ตรวจสอบอีเมลร้านค้า (shop_email) และระบบ OTP
        // ---------------------------------------------------------
        $('#shop_email').on('input', function() {
            const email = $(this).val().trim();
            if (email.length > 0) {
                $('#btnSendShopOTP').fadeIn();
                isShopEmailVerified = false; // ถ้าพิมพ์อีเมล บังคับว่าต้องยืนยัน
            } else {
                $('#btnSendShopOTP').fadeOut();
                $('#shopOtpBox').fadeOut();
                isShopEmailVerified = true; // ลบข้อความทิ้ง อนุญาตให้ผ่านได้
                $(this).removeClass('is-invalid is-valid-custom');
            }
        });

        $('#shop_email').on('blur', function() {
            let el = $(this);
            let email = el.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email.length > 0 && !emailRegex.test(email)) {
                el.addClass('is-invalid').removeClass('is-valid-custom');
                Swal.fire('ผิดพลาด', 'รูปแบบอีเมลร้านค้าไม่ถูกต้อง', 'warning');
                $('#btnSendShopOTP').hide();
            } else if (email.length > 0) {
                $.post('check_availability.php', {
                    action: 'check_email',
                    email: email
                }, function(res) {
                    if (res.status === 'taken') {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('อีเมลซ้ำ', 'อีเมลร้านค้านี้ถูกใช้งานแล้ว', 'warning');
                        $('#btnSendShopOTP').hide();
                    } else {
                        el.removeClass('is-invalid').addClass('is-valid-custom');
                        $('#btnSendShopOTP').show();
                    }
                }, 'json');
            }
        });

        // ปุ่มส่ง OTP (ร้านค้า)
        $('#btnSendShopOTP').click(function() {
            const email = $('#shop_email').val();
            if ($('#shop_email').hasClass('is-invalid') || !email) return;

            $(this).prop('disabled', true).text('กำลังส่ง...');
            fetch('send_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email
                    }) // ส่งคีย์ email ไปให้ API
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('สำเร็จ', 'ส่งรหัส OTP ไปที่อีเมลร้านค้าของคุณแล้ว', 'success');
                        $('#shopOtpBox').fadeIn();
                    } else Swal.fire('ผิดพลาด', data.message, 'error');
                }).catch(err => {
                    Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ส่งอีเมลได้', 'error');
                }).finally(() => {
                    $('#btnSendShopOTP').prop('disabled', false).html('ส่ง OTP');
                });
        });

        // ปุ่มยืนยัน OTP (ร้านค้า)
        $('#btnVerifyShopOTP').click(function() {
            const otp = $('#shop_otp_code').val();
            if (otp.length !== 6) return Swal.fire('แจ้งเตือน', 'กรุณากรอก OTP 6 หลักให้ครบ', 'warning');

            fetch('verify_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        otp: otp
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('สำเร็จ', 'ยืนยันอีเมลร้านค้าสำเร็จ', 'success');
                        isShopEmailVerified = true;
                        $('#shopOtpBox').fadeOut();
                        $('#btnSendShopOTP').fadeOut();
                        $('#shop_email').addClass('is-valid-custom').prop('readonly', true);
                    } else Swal.fire('รหัสไม่ถูกต้อง', data.message, 'error');
                });
        });


        // -----------------------
        // NAVIGATION & VALIDATION (แทนที่ของเดิมด้วยโค้ดนี้)
        // -----------------------
        function updatePrefixEn() {
            const select = document.getElementById('prefix_id');
            document.getElementById('prefix_en').value = select.options[select.selectedIndex].getAttribute('data-en') || '';
        }

        function validateStep1() {
            let emptyFields = 0;

            // 1. วนลูปเช็คเฉพาะช่องที่บังคับกรอก (required)
            $('#step1 input[required]').each(function() {
                if ($(this).val().trim() === '') {
                    $(this).addClass('is-invalid'); // ใส่ขอบแดงถ้าว่าง
                    emptyFields++;
                } else {
                    // หากพิมพ์แล้ว จะลบขอบแดงออก (แต่ถ้าติด Error จาก AJAX จะยังคงไว้)
                    if (!$(this).hasClass('is-invalid-custom')) {
                        $(this).removeClass('is-invalid');
                    }
                }
            });

            // หากมีช่องว่าง
            if (emptyFields > 0) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมายดอกจัน (*) ให้ครบถ้วน',
                    confirmButtonColor: '#198754'
                });
            }

            // 2. เช็คว่ามี Error จากระบบดักจับ (เช่น รหัสสั้นไป, ชื่อซ้ำ) ค้างอยู่หรือไม่
            if ($('#step1 .is-invalid').length > 0) {
                return Swal.fire({
                    icon: 'error',
                    title: 'ข้อมูลไม่ถูกต้อง',
                    text: 'กรุณาแก้ไขข้อมูลในช่องที่มีขอบสีแดงให้ถูกต้อง',
                    confirmButtonColor: '#dc3545'
                });
            }

            // 3. ตรวจสอบสถานะ OTP
            if (!isEmailVerified) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'รอสักครู่',
                    text: 'กรุณายืนยันรหัส OTP ของอีเมลก่อนดำเนินการต่อ'
                });
            }

            // หากผ่านทั้งหมด ให้เปลี่ยนไป Step 2
            goToStep(2);
        }

        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            if ($('#step2 .is-invalid').length > 0) return Swal.fire('ผิดพลาด', 'กรุณาแก้ไขข้อมูลที่ผิดพลาดให้ถูกต้อง', 'warning');

            const inputs = document.querySelectorAll('#step2 input[required]');
            let empty = false;
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    empty = true;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            if (!isShopEmailVerified) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'รอสักครู่',
                    text: 'กรุณายืนยันรหัส OTP ของอีเมลร้านค้าให้เสร็จสิ้นก่อน'
                });
            }

            if (empty) return Swal.fire({
                icon: 'warning',
                title: 'กรุณากรอกข้อมูลบังคับให้ครบ',
                confirmButtonColor: '#198754'
            });

            const shopId = $('#existing_shop_id').val();
            const branchName = $('#branch_name').val().trim();

            $.post('check_availability.php', {
                action: 'check_branch_duplicate',
                shop_id: shopId,
                branch_name: branchName
            }, function(data) {
                if (data.status === 'taken') {
                    Swal.fire({
                        icon: 'error',
                        title: 'ชื่อสาขาซ้ำ!',
                        text: data.message
                    });
                    $('#branch_name').addClass('is-invalid');
                } else {
                    submitRegister();
                }
            }, 'json');
        });
        // ---------------------------------------------------------
        // ตรวจสอบอีเมลร้านค้า (shop_email)
        // ---------------------------------------------------------
        $('input[name="shop_email"]').on('blur', function() {
            let el = $(this);
            let email = el.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email.length > 0 && !emailRegex.test(email)) {
                el.addClass('is-invalid').removeClass('is-valid-custom');
                Swal.fire('ผิดพลาด', 'รูปแบบอีเมลร้านค้าไม่ถูกต้อง', 'warning');
            } else if (email.length > 0) {
                $.post('check_availability.php', {
                    action: 'check_email',
                    email: email
                }, function(res) {
                    if (res.status === 'taken') {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('อีเมลซ้ำ', 'อีเมลร้านค้านี้ถูกใช้งานแล้ว', 'warning');
                    } else {
                        el.removeClass('is-invalid').addClass('is-valid-custom');
                    }
                }, 'json');
            } else {
                el.removeClass('is-invalid is-valid-custom'); // ไม่บังคับกรอก
            }
        });

        // ---------------------------------------------------------
        // ตรวจสอบเลขประจำตัวผู้เสียภาษี (shop_tax_id)
        // ---------------------------------------------------------
        $('#shop_tax_id').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, ''); // บังคับพิมพ์แค่ตัวเลข
        });

        $('#shop_tax_id').on('blur', function() {
            let el = $(this);
            let tax = el.val().trim();

            if (tax.length > 0 && tax.length !== 13) {
                el.addClass('is-invalid').removeClass('is-valid-custom');
                Swal.fire('รูปแบบผิดพลาด', 'เลขผู้เสียภาษีต้องมี 13 หลัก', 'warning');
            } else if (tax.length === 13) {
                $.post('check_availability.php', {
                    action: 'check_tax_id',
                    tax_id: tax
                }, function(res) {
                    if (res.status === 'taken') {
                        el.addClass('is-invalid').removeClass('is-valid-custom');
                        Swal.fire('ข้อมูลซ้ำ', res.message, 'warning');
                    } else {
                        el.removeClass('is-invalid').addClass('is-valid-custom');
                    }
                }, 'json');
            } else {
                el.removeClass('is-invalid is-valid-custom'); // ไม่บังคับกรอก
            }
        });

        function submitRegister() {
            let formData = new FormData(document.getElementById('registerForm'));
            Swal.fire({
                title: 'กำลังบันทึก...',
                text: 'โปรดรอสักครู่',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('register_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                                icon: 'success',
                                title: 'ลงทะเบียนสำเร็จ!',
                                confirmButtonText: 'ไปหน้าเข้าสู่ระบบ',
                                confirmButtonColor: '#198754'
                            })
                            .then(() => window.location.href = '../global/login.php');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: data.message
                        });
                    }
                });
        }
        // ---------------------------------------------------------
        // 1. ตรวจสอบเบอร์โทรร้านค้า (shop_phone)
        // ---------------------------------------------------------
        // บังคับให้พิมพ์ได้เฉพาะตัวเลขเท่านั้น
        $('input[name="shop_phone"]').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // ตรวจสอบเมื่อพิมพ์เสร็จแล้วคลิกออกข้างนอก (blur)
        $('input[name="shop_phone"]').on('blur', function() {
            let el = $(this);
            let phone = el.val().trim();

            // ถ้ามีการกรอกข้อมูล (ความยาวมากกว่า 0)
            if (phone.length > 0) {
                if (phone.length !== 10) {
                    el.addClass('is-invalid').removeClass('is-valid-custom');
                    Swal.fire('รูปแบบผิดพลาด', 'เบอร์โทรศัพท์ร้านค้าต้องมี 10 หลัก', 'warning');
                } else {
                    // ส่งไปเช็คซ้ำที่ check_availability.php
                    $.post('check_availability.php', {
                        action: 'check_phone',
                        phone: phone
                    }, function(res) {
                        if (res.status === 'taken') {
                            el.addClass('is-invalid').removeClass('is-valid-custom');
                            Swal.fire('ข้อมูลซ้ำ', 'เบอร์โทรศัพท์ร้านค้านี้มีในระบบแล้ว', 'warning');
                        } else {
                            el.removeClass('is-invalid').addClass('is-valid-custom');
                        }
                    }, 'json');
                }
            } else {
                // ถ้าไม่ได้กรอก (ปล่อยว่าง) ให้เคลียร์สถานะสีแดง/เขียวออก เพราะไม่บังคับกรอก
                el.removeClass('is-invalid is-valid-custom');
            }
        });

        function goToStep(step) {
            $('.form-step').removeClass('active');
            $(`#step${step}`).addClass('active');
            $('.step-item').removeClass('active');
            for (let i = 1; i <= step; i++) $(`#indicator${i}`).addClass('active');
            $('#stepLabel').text(step === 1 ? 'ข้อมูลผู้ดูแลระบบ' : 'ข้อมูลร้านค้า / สาขา');
        }

        function prevStep() {
            goToStep(1);
        }
    </script>
</body>

</html>