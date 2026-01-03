<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_prodStock');

// 1. ตรวจสอบสิทธิ์ Admin
$current_user_id = $_SESSION['user_id'];
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if ($stmt = $conn->prepare($chk_sql)) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $is_admin = true;
    $stmt->close();
}

// 2. ข้อมูลเริ่มต้น (User ปัจจุบัน)
$user_shop_id = $_SESSION['shop_id'];
$user_branch_id = $_SESSION['branch_id'];
$user_shop_name = $_SESSION['shop_name'] ?? 'Shop';
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';

// ถ้าเป็น Admin โหลดรายชื่อร้านค้าทั้งหมดเตรียมไว้
$shops_list = null;
if ($is_admin) {
    $shops_list = $conn->query("SELECT shop_id, shop_name FROM shop_info ORDER BY shop_name");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เพิ่มสต็อก (กรณีพิเศษ/ของแถม)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <?php require '../config/load_theme.php'; ?>
    <?php include 'add_prodStock_css.php'; ?>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container my-4">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-gift me-2"></i>เพิ่มสต็อก (กรณีพิเศษ/ของแถม)</h4>
                        <a href="add_stock_barcode.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-barcode fa-lg me-2"></i> รับเข้าด้วยบาร์โค้ด (Scan Mode)
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        หน้านี้ใช้สำหรับเพิ่มสินค้าเข้าสต็อกโดยตรง (เช่น ของแถม, สินค้าตกหล่น, ปรับสต็อก)
                        <br>หากต้องการรับสินค้าจาก PO, กรุณาไปที่หน้า "ใบสั่งซื้อ" และกดปุ่ม "รับสินค้า"
                    </div>

                    <form action="add_prodStock_process.php" method="POST" enctype="multipart/form-data" id="addStockForm" novalidate>
                        
                        <div class="form-section">
                            <h5><i class="fas fa-store me-2"></i>ข้อมูลร้านและสาขา</h5>
                            <table>
                                <?php if ($is_admin): ?>
                                <tr>
                                    <td class="label-col">ร้านค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select select2" id="shopSelect" style="width: 100%; max-width: 400px;" onchange="loadBranches(this.value); loadProducts(this.value);">
                                            <option value="">-- เลือกร้านค้า --</option>
                                            <?php while($s = $shops_list->fetch_assoc()): ?>
                                                <option value="<?= $s['shop_id'] ?>" <?= ($s['shop_id'] == $user_shop_id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($s['shop_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">สาขา <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select select2" name="branch_id" id="branchSelect" style="width: 100%; max-width: 400px;">
                                            <option value="">-- รอการเลือกร้านค้า --</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td class="label-col">ร้านค้า</td>
                                    <td>
                                        <input type="text" class="form-control bg-light" style="width: 100%; max-width: 400px;" value="<?= htmlspecialchars($user_shop_name) ?>" readonly>
                                        <input type="hidden" id="shopSelect" value="<?= $user_shop_id ?>"> </td>
                                </tr>
                                <tr>
                                    <td class="label-col">สาขา</td>
                                    <td>
                                        <input type="text" class="form-control bg-light" style="width: 100%; max-width: 400px;" value="<?= htmlspecialchars($user_branch_name) ?>" readonly>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-box me-2"></i>ข้อมูลสินค้า</h5>
                            <table>
                                <tr>
                                    <td class="label-col">สินค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select select2" name="products_prod_id" id="products_prod_id" style="width: 100%; max-width: 500px;" required>
                                            <option value="">-- กรุณาเลือกร้านค้าก่อน --</option>
                                        </select>
                                        <div class="error-feedback">กรุณาเลือกสินค้า</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">จำนวนสินค้า <span class="text-danger">*</span></td>
                                    <td>
                                        <div class="input-group" style="width: 200px;">
                                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" max="50" value="1" required>
                                            <span class="input-group-text">ชิ้น</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">ราคาขาย <span class="text-danger">*</span></td>
                                    <td>
                                        <div class="input-group" style="width: 250px;">
                                            <span class="input-group-text">฿</span>
                                            <input type="number" class="form-control" name="price" id="price" step="0.01" min="0.01" required placeholder="0.00">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-col">เหตุผล <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-select" name="manual_reason" id="manual_reason" required style="width: 100%; max-width: 500px;">
                                            <option value="">-- เลือกเหตุผล --</option>
                                            <option value="MANUAL_ENTRY">ปรับสต็อก (กรอกเอง)</option>
                                            <option value="FREEBIE">ของแถมจาก Supplier</option>
                                            <option value="RETURN">ลูกค้ารับคืน (นอกประกัน)</option>
                                            <option value="OTHER">อื่นๆ</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-barcode me-2"></i>ระบุ Serial Number (S/N) / IMEI</h5>
                            <div id="serialContainer"></div>
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-camera me-2"></i>รูปภาพสินค้า</h5>
                            <p class="text-muted mb-3">รูปภาพจะใช้ร่วมกันสำหรับสินค้าทุกชิ้นในรอบนี้ (เฉพาะรูปแรกจะเป็นปก)</p>
                            <div class="image-preview" onclick="document.getElementById('prod_image').click()">
                                <div id="imagePreview">
                                    <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">คลิกเพื่อเลือกรูปภาพ (สูงสุด 6 รูป)</p>
                                </div>
                                <div id="selectedImages" class="images-grid"></div>
                            </div>
                            <input type="file" class="d-none" name="prod_image[]" id="prod_image" accept="image/*" multiple onchange="previewImages(this)">
                        </div>

                        <div class="form-section">
                            <h5><i class="fas fa-calendar-alt me-2"></i>ข้อมูลวันที่</h5>
                            <table>
                                <tr>
                                    <td class="label-col">วันที่เข้าสต็อก</td>
                                    <td>
                                        <input type="date" class="form-control" name="date_in" id="date_in" style="width: 200px;">
                                        <small class="text-muted">หากไม่เลือก จะใช้วันที่ปัจจุบันอัตโนมัติ</small>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="text-end">
                            <a href="prod_stock.php" class="btn btn-secondary rounded-pill px-4">ยกเลิก</a>
                            <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow">
                                <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({ theme: 'bootstrap-5' });

            // ตั้งค่าวันที่ปัจจุบัน
            document.getElementById('date_in').value = new Date().toISOString().split('T')[0];

            // Initial Load
            const initShopId = $('#shopSelect').val();
            if(initShopId) {
                loadBranches(initShopId); 
                loadProducts(initShopId); 
            }
            
            // Event Listeners
            $('#quantity').on('change input', updateSerialFields);
            $('#products_prod_id').on('change', updatePrice);
            updateSerialFields(); 
        });

        // --- Logic โหลดข้อมูล ---
        function loadBranches(shopId) {
            if(!shopId) return;
            // ถ้าไม่ใช่ Admin ไม่ต้องโหลดเพราะใช้ค่าจาก Session (Input hidden จะไม่มี class select2 ในเคส Non-Admin)
            if(!$('#branchSelect').hasClass('select2')) return;

            fetch(`add_prodStock_process.php?ajax_action=get_branches&shop_id=${shopId}`)
                .then(r => r.json())
                .then(data => {
                    const sel = $('#branchSelect');
                    sel.empty().append('<option value="">-- เลือกสาขา --</option>');
                    data.forEach(b => sel.append(new Option(b.branch_name, b.branch_id)));
                    // Admin: ถ้าเป็นร้านตัวเอง ให้ default สาขาตัวเอง
                    if(shopId == "<?= $user_shop_id ?>") sel.val("<?= $user_branch_id ?>").trigger('change');
                });
        }

        function loadProducts(shopId) {
            if(!shopId) return;
            fetch(`add_prodStock_process.php?ajax_action=get_products&shop_id=${shopId}`)
                .then(r => r.json())
                .then(data => {
                    const sel = $('#products_prod_id');
                    sel.empty().append('<option value="">-- เลือกสินค้า --</option>');
                    data.forEach(p => {
                        let text = `${p.prod_name} ${p.brand_name || ''} (${p.model_name}) - ฿${parseFloat(p.prod_price).toFixed(2)}`;
                        let opt = new Option(text, p.prod_id);
                        $(opt).data('price', p.prod_price);
                        sel.append(opt);
                    });
                });
        }

        function updatePrice() {
            const price = $('#products_prod_id').find(':selected').data('price');
            $('#price').val(price || '');
        }

        // --- Logic Form Fields ---
        function updateSerialFields() {
            const qty = parseInt($('#quantity').val()) || 1;
            const container = $('#serialContainer');
            container.empty();
            
            for(let i=1; i<=qty; i++) {
                const html = `
                    <div class="serial-row">
                        <div class="item-number">ชิ้นที่ ${i}</div>
                        <div class="row">
                            <div class="col-md-12">
                                <label class="small text-muted">Serial Number (S/N) / IMEI</label>
                                <input type="text" class="form-control serial-input" name="serial_no[]" placeholder="กรอก S/N หรือ IMEI" required onblur="checkSerial(this)">
                                <small class="text-danger error-msg d-none"></small>
                            </div>
                        </div>
                    </div>`;
                container.append(html);
            }
        }

        function checkSerial(input) {
            const val = input.value.trim();
            if(val.length < 5) return;
            
            const formData = new FormData();
            formData.append('serial_no', val);
            
            fetch(`add_prodStock_process.php?ajax_action=check_serial`, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    const err = $(input).siblings('.error-msg');
                    if(res.exists) {
                        $(input).addClass('is-invalid');
                        err.text('Serial นี้มีในระบบแล้ว').removeClass('d-none');
                    } else {
                        $(input).removeClass('is-invalid');
                        err.addClass('d-none');
                    }
                });
        }

        // --- Image Preview ---
        let selectedFiles = [];
        function previewImages(input) {
            const container = document.getElementById('selectedImages');
            const previewBox = document.getElementById('imagePreview');
            container.innerHTML = '';
            
            if (input.files) {
                if (input.files.length > 6) { alert('เลือกได้สูงสุด 6 รูป'); return; }
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'position-relative';
                        div.innerHTML = `<img src="${e.target.result}"><button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0" style="width:20px;height:20px;line-height:0;" onclick="alert('กรุณาเลือกไฟล์ใหม่หากต้องการแก้ไข')">&times;</button>`;
                        container.appendChild(div);
                    }
                    reader.readAsDataURL(file);
                });
                previewBox.style.display = 'none';
            }
        }
        
        // --- Form Validation ---
        $('#addStockForm').on('submit', function(e) {
            let isValid = true;
            // Check manual reason
            if(!$('#manual_reason').val()) { isValid = false; $('#manual_reason').addClass('is-invalid'); }
            
            // Check serial duplicates
            const serials = [];
            $('input[name="serial_no[]"]').each(function() {
                const val = $(this).val().trim();
                if(val && serials.includes(val)) {
                    $(this).addClass('is-invalid');
                    $(this).siblings('.error-msg').text('Serial Number ซ้ำกันในฟอร์ม').removeClass('d-none');
                    isValid = false;
                }
                serials.push(val);
            });

            if($('.is-invalid').length > 0 || !isValid) {
                e.preventDefault();
                const firstError = document.querySelector('.is-invalid');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                const btn = document.getElementById('submitBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
                btn.disabled = true;
            }
        });
    </script>
</body>
</html>