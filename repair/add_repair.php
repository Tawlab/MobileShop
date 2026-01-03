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
    <?php require '../config/load_theme.php'; ?>
    <?php require 'add_repair_style.php'; ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h4 class="mb-0">
                                <i class="fas fa-file-alt me-2"></i>
                                ฟอร์มรับเครื่องซ่อม (Job Order)
                            </h4>
                        </div>

                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="add_repair.php" id="repairForm" novalidate>

                                <?php if ($is_admin): ?>
                                    <div class="form-section bg-light border-primary">
                                        <h5 class="text-primary"><i class="fas fa-store me-2"></i>เลือกสาขาที่รับงาน (ผู้ดูแลระบบ)</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ร้านค้า <span class="text-danger">*</span></label>
                                                <select name="selected_shop_id" id="selected_shop_id" class="form-select" required>
                                                    <option value="">-- เลือกร้านค้า --</option>
                                                    <?php foreach ($shops_list as $shop): ?>
                                                        <option value="<?= $shop['shop_id'] ?>" <?= ($shop['shop_id'] == $current_shop_id) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($shop['shop_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">สาขา <span class="text-danger">*</span></label>
                                                <select name="selected_branch_id" id="selected_branch_id" class="form-select" required>
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
                                    <h5><i class="fas fa-id-card-alt me-2"></i>ข้อมูลผู้เกี่ยวข้อง</h5>
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="customer_display" class="form-label">ลูกค้าที่นำเครื่องมาซ่อม <span class="text-danger">*</span></label>
                                            <div class="customer-combo-box input-group">
                                                <input type="text" class="form-control" id="customer_display" placeholder="คลิกปุ่มเพื่อค้นหาลูกค้า" readonly required>
                                                <input type="hidden" name="customer_id" id="customer_id" required>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerSearchModal" title="ค้นหาลูกค้า">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <a href="../customer/add_customer.php?return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-success" title="เพิ่มลูกค้าใหม่">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือก/ค้นหาลูกค้าจากรายการ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="customer-info-box" id="customer_info_box">
                                                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>ข้อมูลลูกค้าจะปรากฏที่นี่</p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="employee_display" class="form-label">พนักงานผู้รับเรื่อง <span class="text-danger">*</span></label>
                                            <div class="employee-combo-box input-group">
                                                <input type="text" class="form-control" id="employee_display" placeholder="คลิกปุ่มเพื่อค้นหาพนักงาน" readonly required>
                                                <input type="hidden" name="employee_id" id="employee_id" required>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeSearchModal" title="ค้นหาพนักงาน" onclick="setAssignedMode(false)">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือกพนักงานจากรายการ</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="assigned_employee_display" class="form-label">ช่างผู้รับผิดชอบงานซ่อม <span class="text-danger">*</span></label>
                                            <div class="employee-combo-box input-group">
                                                <input type="text" class="form-control" id="assigned_employee_display" placeholder="คลิกปุ่มเพื่อค้นหาช่าง" readonly required>
                                                <input type="hidden" name="assigned_employee_id" id="assigned_employee_id" required>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeSearchModal" title="ค้นหาช่าง" onclick="setAssignedMode(true)">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">กรุณาเลือกช่างผู้รับผิดชอบจากรายการ</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-mobile-alt me-2"></i>ข้อมูลเครื่องที่ซ่อม</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="serial_no" class="form-label">Serial Number (หรือ IMEI) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="serial_no" id="serial_no" maxlength="50" required placeholder="กรอก Serial Number เครื่อง">
                                            <div class="serial-check-status" id="serial_status"></div>
                                            <input type="hidden" name="is_new_device" id="is_new_device" value="0">
                                        </div>

                                        <div class="col-md-6" id="new_device_select" style="display:none;">
                                            <label for="new_product_id" class="form-label">รุ่นสินค้า (ถ้าเป็นเครื่องใหม่) <span class="text-danger">*</span></label>
                                            <select class="form-select" name="new_product_id" id="new_product_id">
                                                <option value="">-- เลือกรุ่นสินค้า --</option>
                                                <?php mysqli_data_seek($products_result, 0); ?>
                                                <?php while ($p = mysqli_fetch_assoc($products_result)): ?>
                                                    <option value="<?= $p['prod_id'] ?>">
                                                        <?= htmlspecialchars($p['prod_name']) ?> (<?= htmlspecialchars($p['brand_name_th']) ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <small class="text-muted">ใช้เมื่อ Serial นี้ไม่เคยมีในระบบ</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="estimated_cost" class="form-label">ค่าซ่อมประเมิน (บาท) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">฿</span>
                                                <input type="number" class="form-control" name="estimated_cost" id="estimated_cost" step="0.01" min="0.00" value="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="accessories_list" class="form-label">อุปกรณ์เสริมที่ให้มา</label>
                                            <input type="text" class="form-control" name="accessories_list" id="accessories_list" maxlength="255" placeholder="เช่น: กล่อง, สายชาร์จ, เคส">
                                        </div>

                                        <div class="col-md-12">
                                            <label for="device_description" class="form-label">คำอธิบายสภาพเครื่องภายนอก </label>
                                            <textarea class="form-control" name="device_description" id="device_description" rows="2" maxlength="255" placeholder="เช่น: มีรอยร้าวที่มุมซ้ายบน, สีดำ, เคสยังอยู่"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="fas fa-diagnoses me-2"></i>อาการเสียและรายละเอียด</h5>
                                    <label class="form-label">เลือกอาการเสียหลักที่พบ (เลือกได้หลายข้อ) <span class="text-danger">*</span></label>
                                    <div class="symptom-grid border p-3 rounded-3" style="border-color: #dee2e6 !important;">
                                        <?php mysqli_data_seek($symptoms_result, 0); ?>
                                        <?php if (mysqli_num_rows($symptoms_result) > 0): ?>
                                            <?php while ($symp = mysqli_fetch_assoc($symptoms_result)): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input symptom-checkbox" type="checkbox" name="symptoms[]" value="<?= $symp['symptom_id'] ?>" id="symptom_<?= $symp['symptom_id'] ?>">
                                                    <label class="form-check-label" for="symptom_<?= $symp['symptom_id'] ?>">
                                                        <?= htmlspecialchars($symp['symptom_name']) ?>
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <p class="text-danger">❌ ไม่พบข้อมูลอาการเสีย กรุณาไปเพิ่มที่หน้าจัดการอาการเสีย</p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-4">
                                        <label for="repair_desc" class="form-label">รายละเอียดอาการเพิ่มเติม (ตามที่ลูกค้าแจ้ง)</label>
                                        <textarea class="form-control" name="repair_desc" id="repair_desc" rows="4" maxlength="500" placeholder="เช่น: ลูกค้าแจ้งว่าทำตกเมื่อวาน, เครื่องเคยซ่อมมาก่อน, ทัชสกรีนรวนเป็นบางครั้ง"></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <a href="<?= isset($_GET['return_to']) ? urldecode($_GET['return_to']) : 'repair_list.php' ?>" class="btn btn-secondary me-2">
                                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                                    </a>
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="fas fa-save me-1"></i> บันทึกใบรับซ่อม
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="customerSearchModal" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-users me-2"></i>ค้นหาและเลือกลูกค้า</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="modal_customer_search" placeholder="พิมพ์ชื่อ, นามสกุล, หรือเบอร์โทรศัพท์">
                                    <button class="btn btn-primary" type="button" id="modal_search_btn">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>เบอร์โทร</th>
                                                <th>อีเมล</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal_customer_results">
                                            <tr><td colspan="5" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาลูกค้า...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="employeeSearchModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>ค้นหาและเลือกพนักงาน</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetAssignedMode()"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="modal_employee_search" placeholder="พิมพ์ชื่อ, นามสกุล, หรือรหัสพนักงาน">
                                    <button class="btn btn-primary" type="button" id="modal_employee_search_btn">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>รหัสพนักงาน</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal_employee_results">
                                            <tr><td colspan="4" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาพนักงาน...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isAssignedMode = false;

        function setAssignedMode(state) {
            isAssignedMode = state;
        }

        const customerSearchModal = new bootstrap.Modal(document.getElementById('customerSearchModal'));
        const customerIdInput = document.getElementById('customer_id');
        const customerDisplayInput = document.getElementById('customer_display');

        function searchCustomerInModal(query) {
            const resultsBody = document.getElementById('modal_customer_results');
            resultsBody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>กำลังค้นหา...</td></tr>';

            if (query.length < 2) {
                resultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหา</td></tr>';
                return;
            }

            const currentShopId = document.getElementById('selected_shop_id') ? document.getElementById('selected_shop_id').value : '<?= $current_shop_id ?>';

            fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=search_customer&query=${query}&shop_id=${currentShopId}`
                })
                .then(res => res.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.success && data.customers.length > 0) {
                        data.customers.forEach(customer => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td class="text-center">${customer.cs_id}</td>
                            <td>${customer.firstname_th} ${customer.lastname_th}</td>
                            <td>${customer.cs_phone_no}</td>
                            <td>${customer.cs_email || '—'}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal" 
                                        onclick="selectCustomerInForm(${customer.cs_id}, '${customer.firstname_th}', '${customer.lastname_th}', '${customer.cs_phone_no}', '${customer.cs_email || ''}')">
                                    <i class="fas fa-check"></i> เลือก
                                </button>
                            </td>
                        `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">ไม่พบข้อมูลลูกค้า</td></tr>';
                    }
                });
        }

        function selectCustomerInForm(cs_id, fname, lname, phone, email) {
            customerIdInput.value = cs_id;
            customerIdInput.classList.remove('is-invalid');
            customerDisplayInput.classList.remove('is-invalid');
            customerDisplayInput.value = `${fname} ${lname} (${phone})`;

            const infoBox = document.getElementById('customer_info_box');
            infoBox.innerHTML = `
                <p class="mb-0"><strong>ลูกค้า:</strong> ${fname} ${lname}</p>
                <p class="mb-0"><strong>โทร:</strong> ${phone}</p>
                <p class="mb-0"><strong>Email:</strong> ${email || 'ไม่มี'}</p>
            `;
        }

        document.getElementById('customer_display').addEventListener('click', function() {
            document.getElementById('modal_customer_search').value = '';
            searchCustomerInModal('');
            customerSearchModal.show();
        });

        document.getElementById('modal_customer_search').addEventListener('input', function() {
            searchCustomerInModal(this.value.trim());
        });

        document.getElementById('modal_search_btn').addEventListener('click', function() {
            searchCustomerInModal(document.getElementById('modal_customer_search').value.trim());
        });

        document.getElementById('customerSearchModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('modal_customer_search').value = '';
            document.getElementById('modal_customer_search').focus();
            document.getElementById('modal_customer_results').innerHTML = '<tr><td colspan="5" class="text-center text-muted">เริ่มพิมพ์เพื่อค้นหาลูกค้า...</td></tr>';
        });

        // --- Employee Search ---
        const employeeSearchModal = new bootstrap.Modal(document.getElementById('employeeSearchModal'));
        const employeeIdInput = document.getElementById('employee_id');
        const employeeDisplayInput = document.getElementById('employee_display');
        const assignedEmployeeIdInput = document.getElementById('assigned_employee_id');
        const assignedEmployeeDisplayInput = document.getElementById('assigned_employee_display');

        function searchEmployeeInModal(query) {
            const resultsBody = document.getElementById('modal_employee_results');
            resultsBody.innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>กำลังค้นหา...</td></tr>';

            if (query.length < 2) {
                resultsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหา</td></tr>';
                return;
            }

            const currentShopId = document.getElementById('selected_shop_id') ? document.getElementById('selected_shop_id').value : '<?= $current_shop_id ?>';

            fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=search_employee&query=${query}&shop_id=${currentShopId}`
                })
                .then(res => res.json())
                .then(data => {
                    resultsBody.innerHTML = '';
                    if (data.success && data.employees.length > 0) {
                        data.employees.forEach(employee => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td class="text-center">${employee.emp_id}</td>
                            <td class="text-center">${employee.emp_code || '—'}</td>
                            <td>${employee.firstname_th} ${employee.lastname_th}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal" 
                                        onclick="selectEmployeeInForm(${employee.emp_id}, '${employee.firstname_th}', '${employee.lastname_th}', '${employee.emp_code || ''}')">
                                    <i class="fas fa-check"></i> เลือก
                                </button>
                            </td>
                        `;
                            resultsBody.appendChild(row);
                        });
                    } else {
                        resultsBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูลพนักงาน</td></tr>';
                    }
                });
        }

        function selectEmployeeInForm(emp_id, fname, lname, emp_code) {
            const targetIdInput = isAssignedMode ? assignedEmployeeIdInput : employeeIdInput;
            const targetDisplayInput = isAssignedMode ? assignedEmployeeDisplayInput : employeeDisplayInput;

            targetIdInput.value = emp_id;
            targetIdInput.classList.remove('is-invalid');
            targetDisplayInput.classList.remove('is-invalid');
            targetDisplayInput.value = `${fname} ${lname} (Code: ${emp_code})`;
            isAssignedMode = false;
        }

        document.getElementById('employee_display').addEventListener('click', function() {
            setAssignedMode(false);
            document.getElementById('modal_employee_search').value = '';
            searchEmployeeInModal('');
            employeeSearchModal.show();
        });

        document.getElementById('assigned_employee_display').addEventListener('click', function() {
            setAssignedMode(true);
            document.getElementById('modal_employee_search').value = '';
            searchEmployeeInModal('');
            employeeSearchModal.show();
        });

        document.getElementById('modal_employee_search').addEventListener('input', function() {
            searchEmployeeInModal(this.value.trim());
        });

        document.getElementById('modal_employee_search_btn').addEventListener('click', function() {
            searchEmployeeInModal(document.getElementById('modal_employee_search').value.trim());
        });

        // --- Serial Check ---
        document.getElementById('serial_no').addEventListener('input', function() {
            const serial = this.value.trim();
            const statusDiv = document.getElementById('serial_status');
            const newDeviceSelect = document.getElementById('new_device_select');
            const isNewDeviceInput = document.getElementById('is_new_device');

            if (serial.length < 5) {
                statusDiv.style.display = 'none';
                newDeviceSelect.style.display = 'none';
                isNewDeviceInput.value = 0;
                this.classList.remove('is-invalid');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=check_serial&serial_no=${serial}`
                })
                .then(res => res.json())
                .then(data => {
                    statusDiv.classList.remove('valid', 'new', 'error');
                    statusDiv.style.display = 'block';
                    newDeviceSelect.style.display = 'none';
                    isNewDeviceInput.value = 0;

                    if (data.success) {
                        if (data.exists) {
                            statusDiv.classList.add('valid');
                            statusDiv.innerHTML = `<i class="fas fa-check-circle me-1"></i> Serial นี้มีในระบบแล้ว (Stock ID: ${data.stock_id}). สถานะปัจจุบัน: <strong>${data.status}</strong>.`;
                            newDeviceSelect.style.display = 'none';
                            document.getElementById('serial_no').classList.remove('is-invalid');
                        } else {
                            statusDiv.classList.add('new');
                            statusDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i> Serial นี้เป็นของ <strong>ใหม่</strong>. กรุณาเลือกรุ่นสินค้าด้านล่าง.`;
                            newDeviceSelect.style.display = 'block';
                            isNewDeviceInput.value = 1;
                            document.getElementById('serial_no').classList.remove('is-invalid');
                        }
                    } else {
                        statusDiv.classList.add('error');
                        statusDiv.innerHTML = `<i class="fas fa-times-circle me-1"></i> เกิดข้อผิดพลาดในการตรวจสอบ Serial.`;
                        document.getElementById('serial_no').classList.add('is-invalid');
                    }
                });
        });

        // --- Form Submit ---
        document.getElementById('repairForm').addEventListener('submit', function(e) {
            let isValid = true;
            const customerId = document.getElementById('customer_id');
            const employeeId = document.getElementById('employee_id');
            const assignedEmployeeId = document.getElementById('assigned_employee_id');
            const serialNo = document.getElementById('serial_no');
            const selectedSymptoms = document.querySelectorAll('.symptom-checkbox:checked').length;
            const isNewDevice = document.getElementById('is_new_device').value === '1';
            const newProductId = document.getElementById('new_product_id');
            const estimatedCost = document.getElementById('estimated_cost');

            const checkField = (field) => {
                if (!field.value.trim() || field.classList.contains('is-invalid')) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            };

            checkField(customerId);
            checkField(employeeId);
            checkField(assignedEmployeeId);
            checkField(serialNo);

            if (estimatedCost.value.trim() === '' || parseFloat(estimatedCost.value) < 0) {
                estimatedCost.classList.add('is-invalid');
                isValid = false;
            } else {
                estimatedCost.classList.remove('is-invalid');
            }

            if (selectedSymptoms === 0) {
                isValid = false;
                document.querySelector('.symptom-grid').style.border = '1px solid #dc3545';
            } else {
                document.querySelector('.symptom-grid').style.border = 'none';
            }

            if (isNewDevice) {
                checkField(newProductId);
            }

            if (!isValid) {
                e.preventDefault();
                document.getElementById('submitBtn').disabled = false;
                alert('กรุณากรอกข้อมูลที่จำเป็นทั้งหมดให้ถูกต้อง');
                return;
            }

            const customerNameText = document.getElementById('customer_info_box').innerText.split('\n')[0].replace('ลูกค้า:', '').trim();
            const confirmRepair = confirm(
                `ยืนยันการรับเครื่องซ่อม:\n` +
                `ลูกค้า: ${customerNameText}\n` +
                `Serial No: ${serialNo.value}\n` +
                `ค่าซ่อมประเมิน: ฿${parseFloat(estimatedCost.value).toLocaleString()}\n` +
                `สถานะ: รับเครื่อง\n\n` +
                `ระบบจะบันทึกเครื่องเข้าสต็อกในสถานะ 'Repair' และสร้าง Job Order ใหม่\n` +
                `ดำเนินการต่อหรือไม่?`
            );

            if (!confirmRepair) {
                e.preventDefault();
                return;
            }

            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            document.getElementById('submitBtn').disabled = true;
        });

        // สคริปต์กรองสาขา (Admin)
        const shopSelect = document.getElementById('selected_shop_id');
        const branchSelect = document.getElementById('selected_branch_id');

        if (shopSelect && branchSelect && shopSelect.tagName === 'SELECT') {
            shopSelect.addEventListener('change', function() {
                const selectedShop = this.value;
                branchSelect.value = "";
                Array.from(branchSelect.options).forEach(option => {
                    if (option.value === "") return;
                    if (option.getAttribute('data-shop') == selectedShop) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });
            shopSelect.dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>