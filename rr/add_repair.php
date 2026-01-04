<?php
require 'add_repair_logic.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับเครื่องซ่อมใหม่ (Job Order)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <?php require '../config/load_theme.php'; ?>
    <style>
        .form-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 5px solid <?= $theme_color ?>;
        }

        .form-section h5 {
            color: <?= $theme_color ?>;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .symptom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h4 class="mb-0 text-primary">
                                <i class="fas fa-file-alt me-2"></i>
                                ฟอร์มรับเครื่องซ่อม (Job Order)
                            </h4>
                        </div>

                        <div class="card-body bg-light">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger shadow-sm">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $_SESSION['error'];
                                    unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="add_repair.php" id="repairForm" novalidate>

                                <?php if ($is_admin): ?>
                                    <div class="form-section border-warning" style="border-left-color: #ffc107;">
                                        <h5 class="text-warning"><i class="fas fa-store me-2"></i>เลือกสาขาที่รับงาน (Admin Only)</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">ร้านค้า <span class="text-danger">*</span></label>
                                                <select name="selected_shop_id" id="selected_shop_id" class="form-select select2" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach ($shops_list as $shop): ?>
                                                        <option value="<?= $shop['shop_id'] ?>" <?= ($shop['shop_id'] == $current_shop_id) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($shop['shop_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">สาขา <span class="text-danger">*</span></label>
                                                <select name="selected_branch_id" id="selected_branch_id" class="form-select select2" required>
                                                    <option value="">-- เลือกสาขา --</option>
                                                    <?php foreach ($branches_list as $br): ?>
                                                        <option value="<?= $br['branch_id'] ?>" data-shop="<?= $br['shop_info_shop_id'] ?>">
                                                            <?= htmlspecialchars($br['branch_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="selected_shop_id" value="<?= $current_shop_id ?>">
                                    <input type="hidden" name="selected_branch_id" value="<?= $current_branch_id ?>">
                                <?php endif; ?>

                                <div class="form-section">
                                    <h5><i class="fas fa-users me-2"></i>ข้อมูลผู้เกี่ยวข้อง</h5>
                                    <div class="row g-4">

                                        <div class="col-md-12">
                                            <label for="customer_id" class="form-label fw-bold">ลูกค้าที่นำเครื่องมาซ่อม <span class="text-danger">*</span></label>
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <select name="customer_id" id="customer_id" class="form-select select2" required style="width: 100%;">
                                                        <option value="">-- ค้นหาชื่อ หรือเบอร์โทรลูกค้า --</option>
                                                        <?php foreach ($customers_list as $cust): ?>
                                                            <option value="<?= $cust['cs_id'] ?>">
                                                                <?= htmlspecialchars($cust['firstname_th'] . ' ' . $cust['lastname_th']) ?> (<?= $cust['cs_phone_no'] ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success ms-2" title="เพิ่มลูกค้าใหม่">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">พนักงานผู้รับเรื่อง <span class="text-danger">*</span></label>
                                            <select name="employee_id" id="employee_id" class="form-select select2" required>
                                                <option value="">-- ค้นหาชื่อ หรือรหัสพนักงาน --</option>
                                                <?php foreach ($employees_list as $emp): ?>
                                                    <option value="<?= $emp['emp_id'] ?>">
                                                        <?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?> (<?= $emp['emp_code'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">ช่างผู้รับผิดชอบงาน <span class="text-danger">*</span></label>
                                            <select name="assigned_employee_id" id="assigned_employee_id" class="form-select select2" required>
                                                <option value="">-- ค้นหาชื่อ หรือรหัสพนักงาน --</option>
                                                <?php foreach ($employees_list as $emp): ?>
                                                    <option value="<?= $emp['emp_id'] ?>">
                                                        <?= htmlspecialchars($emp['firstname_th'] . ' ' . $emp['lastname_th']) ?> (<?= $emp['emp_code'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-mobile-alt me-2"></i>ข้อมูลเครื่อง</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="serial_no" class="form-label fw-bold">Serial Number / IMEI <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="serial_no" id="serial_no" maxlength="50" required placeholder="สแกน หรือพิมพ์ Serial Number">
                                            <input type="hidden" name="is_new_device" id="is_new_device" value="0">
                                        </div>

                                        <div class="col-md-6" id="new_device_select" style="display:none;">
                                            <label for="new_product_id" class="form-label fw-bold text-success">เลือกรุ่นสินค้า (กรณีเครื่องใหม่/เครื่องนอก)</label>
                                            <select class="form-select select2" name="new_product_id" id="new_product_id">
                                                <option value="">-- ค้นหาชื่อรุ่น / ยี่ห้อ --</option>
                                                <?php mysqli_data_seek($products_result, 0); ?>
                                                <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                                    <option value="<?= $p['prod_id'] ?>">
                                                        <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="estimated_cost" class="form-label fw-bold">ประเมินค่าซ่อม (บาท) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">฿</span>
                                                <input type="number" class="form-control" name="estimated_cost" id="estimated_cost" step="0.01" min="0.00" value="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="accessories_list" class="form-label fw-bold">อุปกรณ์ที่นำมาด้วย</label>
                                            <input type="text" class="form-control" name="accessories_list" id="accessories_list" placeholder="เช่น: สายชาร์จ, เคส, กล่อง">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="device_description" class="form-label fw-bold">สภาพตัวเครื่องภายนอก</label>
                                            <textarea class="form-control" name="device_description" id="device_description" rows="2" placeholder="เช่น: มีรอยถลอกมุมขวา, จอมีรอยขีดข่วน"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-stethoscope me-2"></i>อาการเสีย</h5>
                                    <label class="form-label fw-bold mb-2">เลือกอาการที่พบ (เลือกได้มากกว่า 1) <span class="text-danger">*</span></label>

                                    <div class="symptom-grid border p-3 rounded bg-white">
                                        <?php mysqli_data_seek($symptoms_result, 0); ?>
                                        <?php if (mysqli_num_rows($symptoms_result) > 0): ?>
                                            <?php while ($symp = mysqli_fetch_assoc($symptoms_result)): ?>
                                                <div class="form-check form-check-inline w-100">
                                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $symp['symptom_id'] ?>" id="symptom_<?= $symp['symptom_id'] ?>">
                                                    <label class="form-check-label" for="symptom_<?= $symp['symptom_id'] ?>">
                                                        <?= htmlspecialchars($symp['symptom_name']) ?>
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <p class="text-muted small text-center mb-0">ไม่พบข้อมูลอาการเสีย (กรุณาเพิ่มข้อมูล Master Data)</p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-3">
                                        <label for="repair_desc" class="form-label fw-bold">รายละเอียดอาการเพิ่มเติม (Note)</label>
                                        <textarea class="form-control" name="repair_desc" id="repair_desc" rows="3" placeholder="ระบุรายละเอียดอาการตามที่ลูกค้าแจ้ง..."></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 py-3">
                                    <a href="<?= isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'repair_list.php' ?>" class="btn btn-secondary px-4">
                                        <i class="fas fa-times me-2"></i>ยกเลิก
                                    </a>
                                    <button type="submit" class="btn btn-success px-5 fw-bold" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>บันทึกรับงาน
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Init Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "คลิกเพื่อเลือก..."
            });

            // Logic กรองสาขา (Admin)
            const shopSelect = $('#selected_shop_id');
            const branchSelect = $('#selected_branch_id');

            if (shopSelect.length > 0) {
                shopSelect.on('change', function() {
                    const shopId = $(this).val();
                    branchSelect.val('').trigger('change');

                    branchSelect.find('option').each(function() {
                        if ($(this).val() === "") return;
                        if ($(this).data('shop') == shopId) {
                            $(this).prop('disabled', false);
                        } else {
                            $(this).prop('disabled', true);
                        }
                    });
                    // Re-init Select2
                    branchSelect.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                });
                shopSelect.trigger('change');
            }
        });

        // ----------------------------------------------------
        // Logic ตรวจสอบ Serial (Mockup)
        // ----------------------------------------------------
        document.getElementById('serial_no').addEventListener('input', function() {
            const val = this.value;
            const newDevDiv = document.getElementById('new_device_select');
            const isNewInput = document.getElementById('is_new_device');

            if (val.length > 5) {
                newDevDiv.style.display = 'block';
                isNewInput.value = '1';
            } else {
                newDevDiv.style.display = 'none';
                isNewInput.value = '0';
            }
        });
    </script>
</body>

</html>