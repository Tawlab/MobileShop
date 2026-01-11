<?php
session_start();
require '../config/config.php';

// ดึงคำนำหน้าชื่อมาแสดง
$prefixes = mysqli_query($conn, "SELECT prefix_id, prefix_th FROM prefixs ORDER BY prefix_id ASC");
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
        }

        .register-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 800px;
            position: relative;
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
            padding: 0 20px;
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
            margin: 0 15px;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
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
            width: 60px;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.5s;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-label {
            font-weight: 500;
            font-size: 0.95rem;
            color: #495057;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
        }

        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
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

        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 10px;
            padding: 5px;
        }
        
        .is-valid-custom {
            border-color: #198754 !important;
            background-image: url("data:image/svg+xml,..."); /* Optional: Success Icon */
        }
    </style>
</head>

<body>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="register-card">
                    <div class="card-header-custom">
                        <h3 class="mb-0"><i class="bi bi-person-badge-fill me-2"></i>ลงทะเบียนพาร์ทเนอร์</h3>
                        <p class="mb-0 opacity-75">สร้างบัญชีเพื่อเริ่มต้นธุรกิจของคุณ</p>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="position-relative text-center mb-4">
                            <div class="step-indicator">
                                <div class="step-line"></div>
                                <div class="step-item active" id="indicator1">1</div>
                                <div class="step-item" id="indicator2">2</div>
                            </div>
                            <small class="text-muted d-block mt-2" id="stepLabel">ข้อมูลส่วนตัว</small>
                        </div>

                        <form id="registerForm" novalidate>
                            <input type="hidden" name="existing_shop_id" id="existing_shop_id" value="">

                            <div class="form-step active" id="step1">
                                <h5 class="text-success mb-4 border-bottom pb-2"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลส่วนตัว</h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">คำนำหน้าชื่อ</label>
                                        <select class="form-select select2" name="prefix_id" id="prefix_id">
                                            <?php while ($row = mysqli_fetch_assoc($prefixes)): ?>
                                                <option value="<?= $row['prefix_id'] ?>"><?= $row['prefix_th'] ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อจริง <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="firstname" id="firstname" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="lastname" id="lastname" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                                        <div class="input-group has-validation">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" name="username" id="username" required placeholder="ภาษาอังกฤษเท่านั้น">
                                            <div class="invalid-feedback" id="username-feedback">
                                                กรุณากรอกชื่อผู้ใช้งาน
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                            <input type="password" class="form-control" name="password" id="password" required minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="button" class="btn btn-success btn-nav" onclick="validateStep1()"><i class="bi bi-arrow-right me-2"></i>ถัดไป</button>
                                </div>
                            </div>

                            <div class="form-step" id="step2">
                                <h5 class="text-success mb-4 border-bottom pb-2"><i class="bi bi-shop me-2"></i>ข้อมูลร้านค้า</h5>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อร้านค้า <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="shop_name" id="shop_name" required placeholder="ชื่อร้านของคุณ">
                                        <div id="shop_name_feedback" class="form-text"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เลขประจำตัวผู้เสียภาษี</label>
                                        <input type="text" class="form-control" name="shop_tax_id" id="shop_tax_id" placeholder="13 หลัก">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรร้าน <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="shop_phone" id="shop_phone" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อสาขาแรก <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_name" id="branch_name" required value="สำนักงานใหญ่">
                                    </div>
                                </div>

                                <h5 class="text-success mb-3 border-bottom pb-2"><i class="bi bi-geo-alt me-2"></i>ที่อยู่ร้านค้า (ไม่บังคับ)</h5>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">บ้านเลขที่ / ถนน / ซอย</label>
                                        <input type="text" class="form-control" name="home_no">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">จังหวัด</label>
                                        <select class="form-select select2" id="province_select" style="width: 100%">
                                            <option value="">-- เลือกจังหวัด --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">อำเภอ/เขต</label>
                                        <select class="form-select select2" id="district_select" style="width: 100%" disabled>
                                            <option value="">-- เลือกอำเภอ --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ตำบล/แขวง</label>
                                        <select class="form-select select2" name="subdistrict_id" id="subdistrict_select" style="width: 100%" disabled>
                                            <option value="">-- เลือกตำบล --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">รหัสไปรษณีย์</label>
                                        <input type="text" class="form-control bg-light" id="zipcode" readonly>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-5">
                                    <button type="button" class="btn btn-secondary btn-nav" onclick="prevStep()"><i class="bi bi-arrow-left me-2"></i>ย้อนกลับ</button>
                                    <button type="submit" class="btn btn-success btn-nav"><i class="bi bi-check-circle me-2"></i>ยืนยันการสมัคร</button>
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
        $(document).ready(function() {
            // Select2 Init
            $('.select2').select2({ theme: 'bootstrap-5' });

            // Load Address Data (เหมือนเดิม)
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
                if(id) {
                    $.get('get_locations.php?action=get_districts&id=' + id, function(data) {
                        data.forEach(function(item) { dist.append(new Option(item.district_name_th, item.district_id)); });
                        dist.prop('disabled', false);
                    });
                }
            });

            $('#district_select').change(function() {
                let id = $(this).val();
                let subdist = $('#subdistrict_select').empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
                $('#zipcode').val('');
                if(id) {
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

            // --- Logic: ตรวจสอบชื่อร้านค้าเมื่อพิมพ์เสร็จ (Blur) ---
            $('#shop_name').on('blur', function() {
                let shopName = $(this).val().trim();
                if(shopName.length > 0) {
                    checkShopName(shopName);
                }
            });
        });

        // ============================
        // STEP 1 VALIDATION & CHECK USERNAME
        // ============================
        function validateStep1() {
            // Check Empty Fields
            const inputs = document.querySelectorAll('#step1 input[required]');
            let empty = false;
            inputs.forEach(input => {
                if(!input.value.trim()) { input.classList.add('is-invalid'); empty = true; }
                else { input.classList.remove('is-invalid'); }
            });

            if(empty) {
                Swal.fire({ icon: 'warning', title: 'กรุณากรอกข้อมูลให้ครบ', confirmButtonColor: '#198754' });
                return;
            }

            // AJAX Check Username
            const username = $('#username').val().trim();
            $.post('check_availability.php', { action: 'check_username', username: username }, function(data) {
                if(data.status === 'available') {
                    // ผ่าน -> ไป Step 2
                    $('#username').removeClass('is-invalid').addClass('is-valid-custom');
                    goToStep(2);
                } else {
                    // ไม่ผ่าน
                    $('#username').addClass('is-invalid');
                    $('#username-feedback').text(data.message);
                    Swal.fire({ icon: 'error', title: 'ขออภัย', text: data.message, confirmButtonColor: '#dc3545' });
                }
            }, 'json');
        }

        // ============================
        // STEP 2 LOGIC & SHOP CHECK
        // ============================
        function checkShopName(shopName) {
            $.post('check_availability.php', { action: 'check_shop_name', shop_name: shopName }, function(data) {
                
                if (data.status === 'exists') {
                    // พบชื่อร้านซ้ำ -> ถามผู้ใช้
                    Swal.fire({
                        title: 'พบชื่อร้านค้านี้ในระบบ!',
                        html: `มีร้านค้าชื่อ <b>"${data.shop_name}"</b> อยู่แล้ว<br>คุณต้องการเพิ่มสาขาใหม่ในร้านค้านี้ใช่หรือไม่?`,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'ใช่, เพิ่มสาขาในร้านนี้',
                        cancelButtonText: 'ไม่, ฉันจะเปลี่ยนชื่อร้าน',
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // เลือกใช้ร้านเดิม
                            $('#existing_shop_id').val(data.shop_id);
                            $('#shop_tax_id').val(data.tax_id).prop('readonly', true); // ล็อคเลขผู้เสียภาษี
                            $('#shop_name').addClass('is-valid-custom');
                            $('#shop_name_feedback').html('<span class="text-success"><i class="bi bi-check-circle"></i> เชื่อมต่อกับร้านค้าเดิมแล้ว</span>');
                        } else {
                            // เปลี่ยนชื่อร้าน
                            $('#existing_shop_id').val(''); // เคลียร์ค่า
                            $('#shop_tax_id').val('').prop('readonly', false);
                            $('#shop_name').val('').focus(); // ล้างชื่อเพื่อให้กรอกใหม่
                            $('#shop_name_feedback').text('');
                        }
                    });
                } else {
                    // ชื่อร้านใหม่
                    $('#existing_shop_id').val('');
                    $('#shop_tax_id').prop('readonly', false);
                    $('#shop_name').removeClass('is-invalid');
                    $('#shop_name_feedback').text('');
                }
            }, 'json');
        }

        // ============================
        // FINAL SUBMIT
        // ============================
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();

            // Validate Step 2 inputs
            const inputs = document.querySelectorAll('#step2 input[required]');
            let empty = false;
            inputs.forEach(input => {
                if(!input.value.trim()) { input.classList.add('is-invalid'); empty = true; }
                else { input.classList.remove('is-invalid'); }
            });

            if(empty) {
                Swal.fire({ icon: 'warning', title: 'กรุณากรอกข้อมูลร้านค้า', confirmButtonColor: '#198754' });
                return;
            }

            // AJAX Check Branch Duplicate
            const shopId = $('#existing_shop_id').val(); // ค่าอาจเป็นว่าง (ร้านใหม่) หรือ ID (ร้านเดิม)
            const branchName = $('#branch_name').val().trim();

            $.post('check_availability.php', { 
                action: 'check_branch_duplicate', 
                shop_id: shopId, 
                branch_name: branchName 
            }, function(data) {
                if(data.status === 'taken') {
                    // ชื่อสาขาซ้ำในร้านเดิม
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'ชื่อสาขาซ้ำ!', 
                        text: `ร้านค้านี้มีสาขาชื่อ "${branchName}" อยู่แล้ว กรุณาตั้งชื่อสาขาใหม่`, 
                        confirmButtonColor: '#dc3545' 
                    });
                    $('#branch_name').addClass('is-invalid');
                } else {
                    // ผ่านทุกอย่าง -> ส่งข้อมูลสมัคร
                    submitRegister();
                }
            }, 'json');
        });

        function submitRegister() {
            let formData = new FormData(document.getElementById('registerForm'));
            
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                text: 'โปรดรอสักครู่',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('register_process.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลงทะเบียนสำเร็จ!',
                        text: 'คุณสามารถเข้าสู่ระบบได้ทันที',
                        confirmButtonText: 'ไปหน้าเข้าสู่ระบบ',
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        window.location.href = '../global/login.php';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message });
                }
            })
            .catch(error => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์' });
            });
        }

        // Navigation Helpers
        function goToStep(step) {
            $('.form-step').removeClass('active');
            $(`#step${step}`).addClass('active');
            
            $('.step-item').removeClass('active');
            for(let i=1; i<=step; i++) $(`#indicator${i}`).addClass('active');
            
            $('#stepLabel').text(step === 1 ? 'ข้อมูลส่วนตัว' : 'ข้อมูลร้านค้า');
        }

        function prevStep() { goToStep(1); }
    </script>

</body>
</html>