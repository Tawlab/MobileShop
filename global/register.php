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

        /* Select2 Override */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 10px;
            padding: 5px;
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
                                        <input type="text" class="form-control" name="firstname" id="firstname" required placeholder="ภาษาไทย">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="lastname" id="lastname" required placeholder="ภาษาไทย">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" name="username" id="username" required placeholder="สำหรับเข้าสู่ระบบ (ภาษาอังกฤษ)">
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
                                    <button type="button" class="btn btn-success btn-nav" onclick="nextStep()"><i class="bi bi-arrow-right me-2"></i>ถัดไป</button>
                                </div>
                            </div>

                            <div class="form-step" id="step2">
                                <h5 class="text-success mb-4 border-bottom pb-2"><i class="bi bi-shop me-2"></i>ข้อมูลร้านค้า</h5>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อร้านค้า <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="shop_name" id="shop_name" required placeholder="ชื่อร้านของคุณ">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เลขประจำตัวผู้เสียภาษี (ถ้ามี)</label>
                                        <input type="text" class="form-control" name="shop_tax_id" placeholder="13 หลัก">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรร้าน <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="shop_phone" id="shop_phone" required placeholder="08xxxxxxxx">
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
                                        <input type="text" class="form-control" name="home_no" placeholder="รายละเอียดที่ตั้ง">
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
            // Init Select2 with Bootstrap 5 theme
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Load Provinces
            $.get('get_locations.php?action=get_provinces', function(data) {
                data.forEach(function(item) {
                    $('#province_select').append(new Option(item.province_name_th, item.province_id));
                });
            });

            // Chain Select: Province -> District
            $('#province_select').change(function() {
                let id = $(this).val();
                let dist = $('#district_select');
                let subdist = $('#subdistrict_select');
                
                dist.empty().append('<option value="">-- เลือกอำเภอ --</option>').prop('disabled', true);
                subdist.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
                $('#zipcode').val('');

                if(id) {
                    $.get('get_locations.php?action=get_districts&id=' + id, function(data) {
                        data.forEach(function(item) {
                            dist.append(new Option(item.district_name_th, item.district_id));
                        });
                        dist.prop('disabled', false);
                    });
                }
            });

            // Chain Select: District -> Subdistrict
            $('#district_select').change(function() {
                let id = $(this).val();
                let subdist = $('#subdistrict_select');
                
                subdist.empty().append('<option value="">-- เลือกตำบล --</option>').prop('disabled', true);
                $('#zipcode').val('');

                if(id) {
                    $.get('get_locations.php?action=get_subdistricts&id=' + id, function(data) {
                        data.forEach(function(item) {
                            // เก็บ Zipcode ไว้ใน attribute data-zip
                            let option = new Option(item.subdistrict_name_th, item.subdistrict_id);
                            $(option).attr('data-zip', item.zip_code);
                            subdist.append(option);
                        });
                        subdist.prop('disabled', false);
                    });
                }
            });

            // Auto Zipcode
            $('#subdistrict_select').change(function() {
                let zip = $(this).find(':selected').data('zip');
                $('#zipcode').val(zip || '');
            });
        });

        // Navigation Logic
        function nextStep() {
            // Validate Step 1
            const step1Inputs = document.querySelectorAll('#step1 input[required]');
            let valid = true;
            step1Inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if(valid) {
                $('#step1').removeClass('active');
                $('#step2').addClass('active');
                $('#indicator2').addClass('active');
                $('#stepLabel').text('ข้อมูลร้านค้า');
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณากรอกข้อมูลส่วนตัวให้ครบถ้วน',
                    confirmButtonColor: '#198754'
                });
            }
        }

        function prevStep() {
            $('#step2').removeClass('active');
            $('#step1').addClass('active');
            $('#indicator2').removeClass('active');
            $('#stepLabel').text('ข้อมูลส่วนตัว');
        }

        // Form Submission
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            
            // Validate Step 2 inputs
            const step2Inputs = document.querySelectorAll('#step2 input[required]');
            let valid = true;
            step2Inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if(!valid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณากรอกข้อมูลร้านค้าที่จำเป็น',
                    confirmButtonColor: '#198754'
                });
                return;
            }

            // AJAX Submission
            let formData = new FormData(this);
            
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                text: 'โปรดรอสักครู่',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('register_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: 'ลงทะเบียนเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบได้ทันที',
                        confirmButtonText: 'เข้าสู่ระบบ',
                        confirmButtonColor: '#198754'
                    }).then((result) => {
                        window.location.href = '../global/login.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    </script>

</body>
</html>