<?php
// Include Logic File
require 'receive_po_process.php';

// -----------------------------------------------------------------------------
//  GET PO DATA FOR DISPLAY
// -----------------------------------------------------------------------------
$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
$po_data = null;
$po_items = [];
$js_pending_data = [];

// ตรวจสอบ Admin
$current_user_id = $_SESSION['user_id'] ?? 0;
$is_admin = false;
$chk_sql = "SELECT r.role_name FROM roles r JOIN user_roles ur ON r.role_id = ur.roles_role_id WHERE ur.users_user_id = ? AND r.role_name = 'Admin'";
if($stmt=$conn->prepare($chk_sql)){ $stmt->bind_param("i",$current_user_id); $stmt->execute(); if($stmt->get_result()->num_rows>0)$is_admin=true; $stmt->close();}

if ($po_id > 0) {
    // 1. ดึงข้อมูล Header
    $sql = "SELECT po.*, s.co_name, b.branch_name, sh.shop_name 
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
            LEFT JOIN branches b ON po.branches_branch_id = b.branch_id
            LEFT JOIN shop_info sh ON b.shop_info_shop_id = sh.shop_id
            WHERE po.purchase_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $po_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($po_data) {
        // Security Check
        if (!$is_admin && $po_data['branches_branch_id'] != $_SESSION['branch_id']) {
            $_SESSION['error'] = "คุณไม่มีสิทธิ์เข้าถึงใบสั่งซื้อของสาขาอื่น";
            header("Location: purchase_order.php"); exit;
        }

        // 2. ดึงรายการสินค้าค้างรับ
        $items_sql = "SELECT od.order_id, od.products_prod_id, od.amount, 
                             p.prod_name, p.model_name, p.prod_price, pb.brand_name_th,
                             (SELECT COUNT(*) FROM stock_movements sm WHERE sm.ref_table='order_details' AND sm.ref_id=od.order_id) as received
                      FROM order_details od
                      JOIN products p ON od.products_prod_id = p.prod_id
                      LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                      WHERE od.purchase_orders_purchase_id = ?";
        
        $stmt_i = $conn->prepare($items_sql);
        $stmt_i->bind_param("i", $po_id);
        $stmt_i->execute();
        $res_i = $stmt_i->get_result();
        
        while ($row = $res_i->fetch_assoc()) {
            $pending = $row['amount'] - $row['received'];
            if ($pending > 0) {
                $row['amount_pending'] = $pending;
                $po_items[] = $row;
                $js_pending_data[$row['order_id']] = $pending;
            }
        }
        $stmt_i->close();

        if (empty($po_items)) {
            $_SESSION['success'] = "PO นี้รับสินค้าครบแล้ว";
            header("Location: purchase_order.php"); exit;
        }
    } else {
        $_SESSION['error'] = "ไม่พบ PO ID: $po_id";
        header("Location: purchase_order.php"); exit;
    }
} else {
    header("Location: purchase_order.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รับสินค้าเข้า - PO #<?= $po_id ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php require '../config/load_theme.php'; ?>
    <?php include 'receive_po_css.php'; ?>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container my-4">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-truck-loading me-2"></i>รับสินค้าเข้า (Receive PO)</h4>
                        <span class="badge bg-secondary fs-6">PO #<?= $po_id ?></span>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="receiveForm">
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">

                        <div class="form-section">
                            <h5>ข้อมูลใบสั่งซื้อ</h5>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <strong>ร้านค้า/สาขาที่สั่ง:</strong><br>
                                    <?= htmlspecialchars($po_data['shop_name']) ?> / <?= htmlspecialchars($po_data['branch_name']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>ผู้จำหน่าย:</strong><br>
                                    <?= htmlspecialchars($po_data['co_name']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>วันที่สั่งซื้อ:</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($po_data['purchase_date'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h5>รายการรับสินค้าเข้า</h5>

                            <?php foreach ($po_items as $i => $item): ?>
                                <div class="form-section po-item-card" id="item-card-<?= $item['order_id'] ?>">
                                    
                                    <div class="po-item-header">
                                        <div>
                                            <div class="po-item-title"><?= htmlspecialchars($item['prod_name']) ?> (<?= htmlspecialchars($item['brand_name_th']) ?>)</div>
                                            <small class="text-muted">Item ID: <?= $item['order_id'] ?></small>
                                        </div>
                                        <div class="po-item-pending">
                                            ค้างรับ: <span id="pending-qty-<?= $item['order_id'] ?>"><?= $item['amount_pending'] ?></span> ชิ้น
                                        </div>
                                    </div>

                                    <div id="batches-container-<?= $item['order_id'] ?>"></div>

                                    <button type="button" class="btn btn-sm btn-add-batch mt-2"
                                            onclick="addBatch(<?= $item['order_id'] ?>, <?= $item['products_prod_id'] ?>, '<?= number_format($item['prod_price'], 2, '.', '') ?>')">
                                        <i class="fas fa-plus me-1"></i> เพิ่มชุดรับเข้า (Batch)
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="text-end">
                            <a href="purchase_order.php" class="btn btn-secondary me-2">ยกเลิก</a>
                            <button type="submit" class="btn btn-success px-4" id="btnSubmit">
                                <i class="fas fa-save me-2"></i> บันทึกรับเข้า
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const pendingData = <?= json_encode($js_pending_data) ?>;
        let batchCounters = {};

        // [แก้ไข Logic ตรงนี้] รวมยอดทุก Batch เพื่อเช็ค
        function checkAndCapQty(input, orderId, batchKey) {
            // 1. ดึง input ทั้งหมดที่เป็นของ Order ID นี้มาเพื่อรวมยอด
            const allInputs = document.querySelectorAll(`input[data-order-id="${orderId}"]`);
            let totalAllocated = 0;
            
            allInputs.forEach(el => {
                totalAllocated += parseInt(el.value) || 0;
            });

            // 2. เช็คกับยอด Max
            const max = pendingData[orderId];
            
            if (totalAllocated > max) {
                // คำนวณว่าเกินไปเท่าไหร่
                const currentVal = parseInt(input.value) || 0;
                const otherTotal = totalAllocated - currentVal;
                
                // คำนวณค่าที่ยอมรับได้สำหรับช่องนี้ (Max - ยอดของช่องอื่น)
                let allowedVal = max - otherTotal;
                if(allowedVal < 0) allowedVal = 0; // กันพลาด

                input.value = allowedVal; // ปรับค่าลง
                
                // แจ้งเตือน
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'ยอดรวมเกินจำนวนค้างรับ',
                    text: `รับได้รวม ${max} ชิ้น (เหลือโควต้าให้กรอก ${allowedVal} ชิ้น)`,
                    showConfirmButton: false,
                    timer: 2500
                });
                
                // ส่งค่าที่ปรับแล้วไป render
                renderSerials(batchKey, orderId, allowedVal);
            } else {
                // ถ้ายอดรวมไม่เกิน ก็ใช้ค่าปัจจุบัน
                renderSerials(batchKey, orderId, parseInt(input.value) || 0);
            }
        }

        function addBatch(orderId, prodId, price) {
            if (!batchCounters[orderId]) batchCounters[orderId] = 0;
            const batchIdx = ++batchCounters[orderId];
            const batchKey = `${orderId}_${batchIdx}`;
            
            const container = document.getElementById(`batches-container-${orderId}`);
            
            const html = `
                <div class="batch-box" id="batch-${batchKey}">
                    <button type="button" class="btn-close batch-remove-btn" onclick="removeBatch(this, '${orderId}', '${batchKey}')"></button>
                    <h6 class="text-primary mb-3">ชุดที่ ${batchIdx}</h6>
                    <input type="hidden" name="items[${orderId}][${batchKey}][product_id]" value="${prodId}">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="small text-muted">จำนวนรับ</label>
                            <input type="number" class="form-control form-control-sm qty-input" 
                                   name="items[${orderId}][${batchKey}][quantity]" 
                                   data-order-id="${orderId}" 
                                   min="1" max="${pendingData[orderId]}" required
                                   oninput="checkAndCapQty(this, '${orderId}', '${batchKey}')">
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">ราคาขายจริง</label>
                            <input type="number" class="form-control form-control-sm" 
                                   name="items[${orderId}][${batchKey}][selling_price]" 
                                   value="${price}" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted">รูปภาพ</label>
                            <input type="file" class="form-control form-control-sm" 
                                   name="batch_image_${batchKey}" accept="image/*"
                                   onchange="previewImg(this)">
                            <img class="batch-image-preview" style="display:none">
                        </div>
                    </div>
                    <div id="serials-${batchKey}" class="mt-3"></div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        // เพิ่มฟังก์ชันลบ Batch (เผื่อผู้ใช้ต้องการลบ แล้วคำนวณยอดใหม่)
        function removeBatch(btn, orderId, batchKey) {
            btn.parentElement.remove();
            // อาจจะ trigger checkAndCapQty ของ input อื่นๆ เพื่อ update state 
            // แต่ใน case นี้แค่ลบออก ยอดรวมลดลง ก็ไม่มีปัญหาเรื่องเกิน Max
        }

        function renderSerials(batchKey, orderId, qty) {
            const container = document.getElementById(`serials-${batchKey}`);
            container.innerHTML = '';
            qty = parseInt(qty);
            
            if (qty <= 0) return;

            for(let i=1; i<=qty; i++) {
                container.insertAdjacentHTML('beforeend', `
                    <div class="serial-row">
                        <div class="d-flex align-items-center">
                            <span class="item-number me-2">#${i}</span>
                            <input type="text" class="form-control form-control-sm" 
                                   name="items[${orderId}][${batchKey}][serial_no][]" 
                                   placeholder="Serial Number / IMEI" required onblur="checkSerial(this)">
                        </div>
                        <div class="error-feedback">Serial นี้มีในระบบแล้ว</div>
                    </div>
                `);
            }
        }

        function previewImg(input) {
            const img = input.nextElementSibling;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        async function checkSerial(input) {
            const val = input.value.trim();
            if(val.length < 3) return;
            
            const fd = new FormData();
            fd.append('action', 'check_serial');
            fd.append('serial_no', val);
            
            try {
                const res = await fetch('receive_po_process.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.exists) {
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            } catch(e) { console.error(e); }
        }

        document.getElementById('receiveForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (document.querySelectorAll('.is-invalid').length > 0) {
                Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณาตรวจสอบ Serial Number ที่ซ้ำกัน', 'error');
                return;
            }

            const btn = document.getElementById('btnSubmit');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

            const formData = new FormData(this);

            try {
                const response = await fetch('receive_po_process.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: result.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = 'purchase_order.php';
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }

            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>