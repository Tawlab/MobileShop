<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_purchase_order');

// ดึงข้อมูลสำหรับ Dropdowns
// Suppliers
$suppliers_result = mysqli_query($conn, "SELECT supplier_id, co_name FROM suppliers ORDER BY co_name");
// Employees 
$employees_result = mysqli_query($conn, "SELECT emp_id, firstname_th, lastname_th FROM employees WHERE emp_status = 'Active' ORDER BY firstname_th");
// Branches
$branches_result = mysqli_query($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");

// ดึงข้อมูลสินค้าทั้งหมด
$products_sql = "SELECT p.prod_id, p.prod_name, p.model_name, p.prod_price, pb.brand_name_th 
                 FROM products p
                 LEFT JOIN prod_brands pb ON p.prod_brands_brand_id = pb.brand_id
                 WHERE p.prod_types_type_id != 4 
                 ORDER BY p.prod_name";
$products_query = mysqli_query($conn, $products_sql);
$products_json = [];
while ($row = mysqli_fetch_assoc($products_query)) {
    $products_json[] = $row;
}
$products_json = json_encode($products_json);


// จัดการบันทึกข้อมูล (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // รับข้อมูลส่วนหัว
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $suppliers_supplier_id = (int)$_POST['suppliers_supplier_id'];
    $employees_emp_id = (int)$_POST['employees_emp_id']; // (สมมติว่าดึงจาก Session จริง)
    $branches_branch_id = (int)$_POST['branches_branch_id'];

    //  รับข้อมูลรายการสินค้า (Arrays)
    $product_ids = $_POST['product_ids'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    $prices = $_POST['prices'] ?? [];

    // Validation
    if (empty($purchase_date) || empty($suppliers_supplier_id) || empty($employees_emp_id) || empty($branches_branch_id)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลใบสั่งซื้อ (วันที่, Supplier, พนักงาน, สาขา) ให้ครบถ้วน';
    } elseif (empty($product_ids)) {
        $_SESSION['error'] = 'กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ';
    } else {

        mysqli_autocommit($conn, false);
        try {
            // บันทึกส่วนหัว 
            $sql_header = "INSERT INTO purchase_orders (
                                purchase_date, create_at, update_at, 
                                suppliers_supplier_id, branches_branch_id, employees_emp_id
                           ) VALUES (?, NOW(), NOW(), ?, ?, ?)";

            $stmt_header = $conn->prepare($sql_header);
            $stmt_header->bind_param(
                "siii",
                $purchase_date,
                $suppliers_supplier_id,
                $branches_branch_id,
                $employees_emp_id
            );

            if (!$stmt_header->execute()) {
                throw new Exception("ไม่สามารถบันทึก Header PO ได้: " . $stmt_header->error);
            }

            //  ดึง ID ของ PO ที่เพิ่งสร้าง
            $new_purchase_id = mysqli_insert_id($conn);
            $stmt_header->close();

            // บันทึกรายการสินค้า
            $sql_details = "INSERT INTO order_details (
                                amount, price, create_at, update_at, 
                                purchase_orders_purchase_id, products_prod_id
                           ) VALUES (?, ?, NOW(), NOW(), ?, ?)";

            $stmt_details = $conn->prepare($sql_details);

            $total_items = 0;
            foreach ($product_ids as $index => $prod_id) {
                $amount = (int)($amounts[$index] ?? 1);
                $price = (float)($prices[$index] ?? 0);

                if ($amount > 0 && $price >= 0) {
                    $stmt_details->bind_param(
                        "idii", 
                        $amount,
                        $price,
                        $new_purchase_id,
                        $prod_id
                    );

                    if (!$stmt_details->execute()) {
                        throw new Exception("ไม่สามารถบันทึกรายการสินค้า (ID: $prod_id) ได้: " . $stmt_details->error);
                    }
                    $total_items++;
                }
            }
            $stmt_details->close();

            if ($total_items == 0) {
                throw new Exception("ไม่มีรายการสินค้าที่ถูกต้อง (จำนวนหรือราคาต้องมากกว่า 0)");
            }

            mysqli_commit($conn);
            mysqli_autocommit($conn, true);

            $_SESSION['success'] = "สร้างใบรับเข้า PO #$new_purchase_id ( $total_items รายการ) สำเร็จแล้ว";
            // ขั้นต่อไปคือการ "รับเข้าสต็อก" จากหน้ารายละเอียด PO นี้
            header("Location: view_purchase_order.php?id=$new_purchase_id");
            exit;
        } catch (Exception $e) {
            // Rollback 
            mysqli_rollback($conn);
            mysqli_autocommit($conn, true);
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
<<<<<<< HEAD
    <title>สร้างใบรับเข้าสินค้า (PO)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
=======
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างใบรับเข้าสินค้า (PO)</title>
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
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
            max-width: 1200px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid <?= $theme_color ?>;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .table th {
            background-color: <?= $header_bg_color ?>;
            color: <?= $header_text_color ?>;
            font-weight: 600;
            vertical-align: middle;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

<<<<<<< HEAD
        /* **[เพิ่ม]** จัดการปุ่ม Action ในตาราง */
        .table td:last-child {
            display: flex;
            gap: 5px; 
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }

=======
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
        .btn-success {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
            color: white;
            padding: 0.5rem 1.5rem;
        }

        .btn-success:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-danger {
            background-color: <?= $btn_delete_color ?>;
            border-color: <?= $btn_delete_color ?>;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.25);
        }

        .required-label::after {
            content: " *";
            color: red;
        }

<<<<<<< HEAD
        /* (CSS สำหรับแถวสินค้าที่ถูกลบ) */
=======
        /* สำหรับแถวสินค้าที่ถูกลบ */
>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
        .product-row-removed {
            opacity: 0.5;
            background-color: #f8d7da;
        }

        .product-row-removed .form-control,
        .product-row-removed .form-select {
            background-color: #f1f1f1;
        }
<<<<<<< HEAD

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 992px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 991.98px) {
            .container {
                /* เพิ่ม Padding ด้านข้างบน Mobile */
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .card-header {
                padding: 1rem;
                font-size: 1.1rem;
            }
            
            /* 1. ปรับ Table Cell/Font ในตารางรายการสินค้า */
            .table th, .table td {
                padding: 0.5rem 0.5rem; /* ลด Padding ด้านข้าง */
                font-size: 0.8rem; /* ลดขนาด Font เล็กน้อย */
                white-space: nowrap; /* ป้องกันไม่ให้ข้อความยาวๆ ขึ้นบรรทัดใหม่ในตาราง Responsive */
            }

            /* 2. ทำให้ Form Control ในตาราง/Grid มีขนาดเหมาะสม */
            .table .form-control,
            .table .form-select {
                 padding: 0.5rem; /* ลด Padding ใน Input/Select */
                 font-size: 0.8rem;
            }

            /* 3. จัดการปุ่ม Action (ถ้ามีปุ่มเพิ่ม/ลบ ในแต่ละแถว) */
            .table td:last-child {
                flex-direction: column; /* จัดปุ่มเป็นแนวตั้งในแถว */
                gap: 5px;
            }

            /* 4. ทำให้ปุ่มหลักที่ด้านล่าง/บน กินพื้นที่เต็มความกว้าง */
            .d-grid .btn {
                width: 100% !important;
                margin-bottom: 10px;
            }
        }
    </style>
</head>


=======
    </style>
</head>

>>>>>>> 87d2bdcaa5a9158c74359bf647e536fa344f68ca
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container py-5">

                    <form method="POST" id="poForm" novalidate>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">
                                <i class="fas fa-plus-circle me-2" style="color: <?= $theme_color ?>;"></i>
                                สร้างใบสั่งซื้อ / รับเข้าสินค้าใหม่
                            </h4>
                            <div>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>บันทึกใบรับเข้า
                                </button>
                                <a href="purchase_order.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>ยกเลิก
                                </a>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $_SESSION['success'];
                                unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $_SESSION['error'];
                                unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-file-invoice me-2"></i>
                                ข้อมูลใบรับเข้า
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label required-label">วันที่รับเข้า</label>
                                        <input type="datetime-local" class="form-control" name="purchase_date"
                                            value="<?= date('Y-m-d\TH:i') ?>" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label required-label">Supplier (ผู้จำหน่าย)</label>
                                        <div class="input-group">
                                            <select class="form-select" name="suppliers_supplier_id" required>
                                                <option value="">-- เลือก Supplier --</option>
                                                <?php mysqli_data_seek($suppliers_result, 0); ?>
                                                <?php while ($row = mysqli_fetch_assoc($suppliers_result)): ?>
                                                    <option value="<?= $row['supplier_id'] ?>">
                                                        <?= htmlspecialchars($row['co_name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <a href="../supplier/add_supplier.php?return_url=<?= urlencode('../purchase/add_purchase_order.php') ?>"
                                                class="btn btn-outline-success" title="เพิ่ม Supplier ใหม่">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required-label">สาขาที่รับเข้า</label>
                                        <select class="form-select" name="branches_branch_id" required>
                                            <option value="">-- เลือกสาขา --</option>
                                            <?php mysqli_data_seek($branches_result, 0); ?>
                                            <?php while ($row = mysqli_fetch_assoc($branches_result)): ?>
                                                <option value="<?= $row['branch_id'] ?>" <?= ($row['branch_id'] == 1) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($row['branch_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label required-label">พนักงานผู้รับเข้า</label>
                                        <select class="form-select" name="employees_emp_id" required>
                                            <option value="">-- เลือกพนักงาน --</option>
                                            <?php mysqli_data_seek($employees_result, 0); ?>
                                            <?php while ($row = mysqli_fetch_assoc($employees_result)): ?>
                                                <option value="<?= $row['emp_id'] ?>" <?= ($row['emp_id'] == 1) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-boxes me-2"></i>
                                รายการสินค้า
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width="40%">สินค้า</th>
                                                <th width="15%">จำนวน</th>
                                                <th width="20%">ราคา/หน่วย (บาท)</th>
                                                <th width="20%">ราคารวม (บาท)</th>
                                                <th width="5%">ลบ</th>
                                            </tr>
                                        </thead>
                                        <tbody id="product-list-container">
                                        </tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-outline-success mt-2" id="add-product-btn">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการสินค้า
                                </button>

                                <hr>

                                <div class="row justify-content-end">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="fw-bold">จำนวนรวม (ชิ้น):</span>
                                            <span id="total-quantity" class="fw-bold">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="fw-bold fs-5">ยอดรวมสุทธิ (บาท):</span>
                                            <span id="total-price" class="fw-bold fs-5 text-success">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

                <template id="product-row-template">
                    <tr class="product-row">
                        <td>
                            <select class="form-select product-select" name="product_ids[]" required>
                                <option value="">-- เลือกสินค้า --</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control amount-input" name="amounts[]" value="1" min="1" step="1" required>
                        </td>
                        <td>
                            <input type="number" class="form-control price-input" name="prices[]" value="0.00" min="0" step="0.01" required>
                        </td>
                        <td>
                            <input type="text" class="form-control line-total" value="0.00" readonly>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remove-row-btn">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ดึงข้อมูล Products 
        const productsData = <?php echo $products_json; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('product-list-container');
            const addBtn = document.getElementById('add-product-btn');
            const template = document.getElementById('product-row-template');

            //  ฟังก์ชันเพิ่มแถวสินค้า
            function addProductRow() {
                const newRow = template.content.cloneNode(true);
                const productSelect = newRow.querySelector('.product-select');

                // เติม Dropdown สินค้า
                productsData.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.prod_id;
                    option.text = `${product.brand_name_th} - ${product.prod_name} (${product.model_name})`;
                    option.dataset.price = product.prod_price;
                    productSelect.appendChild(option);
                });

                //  Event Listeners ให้แถวใหม่
                addEventListeners(newRow);

                container.appendChild(newRow);
                updateTotals();
            }

            // ฟังก์ชันผูก Event Listeners
            function addEventListeners(rowElement) {
                const productSelect = rowElement.querySelector('.product-select');
                const amountInput = rowElement.querySelector('.amount-input');
                const priceInput = rowElement.querySelector('.price-input');
                const removeBtn = rowElement.querySelector('.remove-row-btn');

                // เมื่อเลือกสินค้า
                productSelect.addEventListener('change', function() {
                    const selectedPrice = this.options[this.selectedIndex].dataset.price || 0;
                    this.closest('tr').querySelector('.price-input').value = parseFloat(selectedPrice).toFixed(2);
                    updateLineTotal(this.closest('tr'));
                });

                // เมื่อเปลี่ยนจำนวนหรือราคา
                amountInput.addEventListener('input', function() {
                    updateLineTotal(this.closest('tr')); // หาแถวที่ตัวเองอยู่
                });
                priceInput.addEventListener('input', function() {
                    updateLineTotal(this.closest('tr')); // หาแถวที่ตัวเองอยู่
                });

                //  เมื่อกดลบแถว
                removeBtn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    row.classList.add('product-row-removed');
                    row.style.display = 'none';

                    row.querySelector('.amount-input').value = 0;
                    row.querySelector('.price-input').value = 0;
                    row.querySelector('.product-select').required = false;

                    updateTotals();
                });
            }

            //  ฟังก์ชันคำนวณราคารวมต่อแถว
            function updateLineTotal(row) {
                // ป้องกัน Error ถ้า row เป็น null
                if (!row) return;

                const amount = parseFloat(row.querySelector('.amount-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const lineTotalInput = row.querySelector('.line-total');

                lineTotalInput.value = (amount * price).toFixed(2);
                updateTotals();
            }

            //  ฟังก์ชันคำนวณยอดรวมสุทธิ
            function updateTotals() {
                let totalQuantity = 0;
                let totalPrice = 0;

                container.querySelectorAll('.product-row').forEach(row => {
                    if (!row.classList.contains('product-row-removed')) {
                        totalQuantity += parseFloat(row.querySelector('.amount-input').value) || 0;
                        totalPrice += parseFloat(row.querySelector('.line-total').value) || 0;
                    }
                });

                document.getElementById('total-quantity').textContent = totalQuantity.toLocaleString();
                document.getElementById('total-price').textContent = totalPrice.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // ปุ่มเพิ่มสินค้า
            addBtn.addEventListener('click', addProductRow);

            // เพิ่ม 1 แถวอัตโนมัติเมื่อโหลดหน้า
            addProductRow();

            //  Client-side Validation 
            const form = document.getElementById('poForm');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert('กรุณากรอกข้อมูลที่จำเป็น (*) ให้ครบถ้วน');
                }
                form.classList.add('was-validated');
            }, false);

        });
    </script>
</body>

</html>