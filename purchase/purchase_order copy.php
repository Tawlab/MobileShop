<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'purchase_order');

// Pagination, Search, Sort
$limit = 10; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// สำหรับค้นหา
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(
        po.purchase_id LIKE '%$search%' OR 
        s.co_name LIKE '%$search%' OR 
        e.firstname_th LIKE '%$search%' OR 
        e.lastname_th LIKE '%$search%' OR
        b.branch_name LIKE '%$search%'
    )";
}
$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

//  SQL QUERY (หลัก)
$main_sql = "SELECT 
                po.purchase_id,
                po.purchase_date,
                po.po_status, 
                s.co_name as supplier_name,
                b.branch_name,
                e.firstname_th,
                e.lastname_th,
                (SELECT COUNT(*) 
                 FROM order_details od 
                 WHERE od.purchase_orders_purchase_id = po.purchase_id) as item_count,
                 
                (SELECT SUM(od.price * od.amount) 
                 FROM order_details od 
                 WHERE od.purchase_orders_purchase_id = po.purchase_id) as total_amount,
                 
                (SELECT SUM(od.amount) 
                 FROM order_details od 
                 WHERE od.purchase_orders_purchase_id = po.purchase_id) as total_quantity,

                (SELECT COUNT(DISTINCT sm.prod_stocks_stock_id) 
                 FROM order_details od_sm 
                 JOIN stock_movements sm ON od_sm.order_id = sm.ref_id AND sm.ref_table = 'order_details'
                 WHERE od_sm.purchase_orders_purchase_id = po.purchase_id) as received_quantity

            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.suppliers_supplier_id = s.supplier_id
            LEFT JOIN branches b ON po.branches_branch_id = b.branch_id
            LEFT JOIN employees e ON po.employees_emp_id = e.emp_id
            $where_clause
            ORDER BY po.purchase_id DESC";

// COUNT TOTAL (สำหรับ Pagination)
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM ($main_sql) as count_table");
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// สำหรับแสดงผล
$data_sql = $main_sql . " LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $data_sql);

// HELPER FUNCTION 
function build_query_string($exclude = [])
{
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการใบสั่งซื้อ/รับเข้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background-color: <?= $background_color ?>;
            font-family: '<?= $font_style ?>', sans-serif;
            color: <?= $text_color ?>;
        }

        .container-xl {
            max-width: 1400px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 2px solid <?= $theme_color ?>;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
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

        /* **[เพิ่ม]** จัดการคอลัมน์ Action ในตาราง */
        .table td:last-child {
            display: flex;
            gap: 5px; 
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap; /* ป้องกันปุ่มขึ้นบรรทัดใหม่บน Desktop */
        }

        /* ... โค้ดปุ่มและ Form Control เดิม ... */
        .btn-add {
            background-color: <?= $btn_add_color ?>;
            border-color: <?= $btn_add_color ?>;
            color: white;
        }

        .btn-add:hover {
            color: white;
            filter: brightness(90%);
        }

        .btn-edit {
            background-color: <?= $btn_edit_color ?>;
            color: white;
        }

        .btn-delete {
            background-color: <?= $btn_delete_color ?>;
            color: white;
        }

        .btn-info {
            background-color: #0dcaf0;
            color: white;
        }

        /* (เพิ่มปุ่มรับของ) */
        .btn-receive {
            background-color: #198754;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }

        .pagination .page-link {
            color: <?= $theme_color ?>;
        }

        .pagination .page-item.active .page-link {
            background-color: <?= $theme_color ?>;
            border-color: <?= $theme_color ?>;
            color: white;
        }

        .form-control:focus {
            border-color: <?= $theme_color ?>;
            box-shadow: 0 0 0 0.25rem rgba(<?= hexdec(substr($theme_color, 1, 2)) ?>, <?= hexdec(substr($theme_color, 3, 2)) ?>, <?= hexdec(substr($theme_color, 5, 2)) ?>, 0.25);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* (CSS สำหรับสถานะการรับ) */
        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.3em 0.6em;
            border-radius: 15px;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-partial {
            background-color: #d1edff;
            color: #0c63e4;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        /* (เพิ่มสถานะยกเลิก) */
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            text-decoration: line-through;
        }

        /* -------------------------------------------------------------------- */
        /* --- **[เพิ่ม]** Responsive Override สำหรับ Mobile (จอเล็กกว่า 992px) --- */
        /* -------------------------------------------------------------------- */
        @media (max-width: 991.98px) {
            .container-xl {
                padding-left: 10px;
                padding-right: 10px;
            }

            /* 1. จัดการ Filter/Action Bar (สมมติว่าใช้ d-flex) */
            .card-header .d-flex {
                flex-direction: column; 
                gap: 10px;
            }

            .card-header .d-flex > div {
                 width: 100% !important; 
            }

            /* 2. ทำให้ Form Control และ Button ใช้เต็มความกว้าง */
            .card-header .form-control,
            .card-header .form-select,
            .card-header .btn {
                width: 100% !important; 
            }

            /* 3. ปรับ Table Cell Padding/Font */
            .table th, .table td {
                padding: 0.5rem 0.5rem; 
                font-size: 0.8rem; 
                white-space: nowrap; 
            }
            
            /* 4. จัดการคอลัมน์ Action ในตาราง */
            .table td:last-child {
                flex-direction: column; /* เรียงปุ่ม Action เป็นแนวตั้งบน Mobile */
                gap: 5px;
            }

            /* 5. ปรับขนาด Badge */
            .status-badge {
                font-size: 10px;
                padding: 3px 6px;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการลบ
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>คุณต้องการลบใบสั่งซื้อ/รับเข้า <strong id="deletePoName"></strong> ใช่หรือไม่?</p>
                                <p class="text-danger small">
                                    <strong>คำเตือน:</strong> การลบใบสั่งซื้อนี้ จะลบรายการสินค้าที่รับเข้า (Order Details) ทั้งหมด
                                    (เราตั้งค่า ON DELETE CASCADE ไว้)
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                <form id="deleteForm" method="POST" action="delete_purchase_order.php" style="display:inline;">
                                    <input type="hidden" name="po_id" id="deletePoIdInput">
                                    <button type="submit" class="btn btn-delete">
                                        <i class="fas fa-trash me-1"></i> ยืนยันการลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="confirmCancelModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">

                            <form id="cancelForm" method="POST" action="cancel_purchase_order.php" novalidate>
                                <div class="modal-header bg-warning">
                                    <h5 class="modal-title text-dark">
                                        <i class="fas fa-exclamation-triangle me-2"></i> ยืนยันการยกเลิก
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>คุณต้องการยกเลิกใบสั่งซื้อ <strong id="cancelPoName"></strong> ใช่หรือไม่?</p>

                                    <div class="mb-3">
                                        <label for="cancelCommentInput" class="form-label"><strong>เหตุผลการยกเลิก (จำเป็น):</strong></label>
                                        <textarea class="form-control" id="cancelCommentInput" name="cancel_comment" rows="3" required placeholder="เช่น: สั่งสินค้าผิด, supplier ไม่มีของ, ฯลฯ"></textarea>
                                        <div class="invalid-feedback">
                                            กรุณากรอกเหตุผลการยกเลิก
                                        </div>
                                    </div>

                                    <p class="text-danger small">
                                        <strong>คำเตือน:</strong> สถานะ PO จะถูกเปลี่ยนเป็น 'Cancelled' และจะไม่สามารถรับสินค้าเข้าจาก PO นี้ได้อีก
                                    </p>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="po_id" id="cancelPoIdInput">

                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิด</button>

                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-ban me-1"></i> ยืนยันการยกเลิก
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


                <div class="container-xl py-5">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">
                                    <i class="fas fa-dolly-flatbed me-2" style="color: <?= $theme_color ?>;"></i>
                                    รายการใบสั่งซื้อ / รับเข้าสินค้า
                                </h4>
                                <a href="add_purchase_order.php" class="btn btn-add">
                                    <i class="fas fa-plus-circle me-1"></i> สร้างใบสั่งซื้อใหม่
                                </a>
                            </div>
                        </div>

                        <div class="card-body">

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

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <form method="GET" action="purchase_order.php">
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control"
                                                placeholder="ค้นหาเลขที่ PO, ชื่อ Supplier, ชื่อพนักงาน..."
                                                value="<?= htmlspecialchars($search) ?>">
                                            <button class="btn btn-outline-secondary" type="submit">
                                                <i class="fas fa-search"></i> ค้นหา
                                            </button>
                                            <?php if (!empty($search)): ?>
                                                <a href="purchase_order.php" class="btn btn-outline-danger">
                                                    <i class="fas fa-times"></i> ล้าง
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th width="8%">เลขที่ PO</th>
                                            <th width="12%">วันที่สั่ง</th>
                                            <th width="20%">Supplier</th>
                                            <th width="12%">สาขาที่รับ</th>
                                            <th width="12%">พนักงาน</th>
                                            <th width="12%">สถานะ PO</th>
                                            <th width="10%">ยอดรวม (บาท)</th>
                                            <th width="14%">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <?php
                                                // (คำนวณสถานะ)
                                                $status_text = 'รอรับ';
                                                $status_class = 'status-pending';
                                                $can_receive = true;

                                                $total_qty = (int)$row['total_quantity'];
                                                $received_qty = (int)$row['received_quantity'];

                                                // (*** FIXED ***: ตรวจสอบ po_status ก่อน)
                                                if ($row['po_status'] == 'Cancelled') {
                                                    $status_text = 'ยกเลิกแล้ว';
                                                    $status_class = 'status-cancelled';
                                                    $can_receive = false;
                                                } elseif ($received_qty > 0 && $received_qty < $total_qty) {
                                                    $status_text = 'รับแล้วบางส่วน';
                                                    $status_class = 'status-partial';
                                                } elseif ($received_qty >= $total_qty && $total_qty > 0) {
                                                    $status_text = 'รับครบแล้ว';
                                                    $status_class = 'status-completed';
                                                    $can_receive = false; // (รับครบแล้วไม่ต้องมีปุ่ม)
                                                } elseif ($total_qty == 0) {
                                                    $status_text = 'ยังไม่สั่งของ';
                                                    $status_class = 'status-pending';
                                                    $can_receive = false; // (PO ว่าง)
                                                }

                                                // (ถ้าสถานะ Pending แต่รับครบแล้ว ให้เปลี่ยนเป็น Completed)
                                                if ($row['po_status'] == 'Pending' && $status_text == 'รับครบแล้ว') {
                                                    $status_text = 'รับครบแล้ว';
                                                    $status_class = 'status-completed';
                                                }
                                                ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <strong><?= htmlspecialchars($row['purchase_id']) ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= date('d/m/Y H:i', strtotime($row['purchase_date'])) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['supplier_name'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($row['branch_name'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($row['firstname_th'] . ' ' . $row['lastname_th']) ?></td>

                                                    <td class="text-center">
                                                        <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                        <div style="font-size: 0.8em; margin-top: 4px;">
                                                            (<?= $received_qty ?> / <?= $total_qty ?>)
                                                        </div>
                                                    </td>

                                                    <td class="text-end">
                                                        <?= number_format($row['total_amount'] ?? 0, 2) ?>
                                                    </td>
                                                    <td class="text-center">

                                                        <?php if ($row['po_status'] == 'Pending'): ?>

                                                            <?php if ($can_receive): ?>
                                                                <a href="receive_po.php?po_id=<?= $row['purchase_id'] ?>"
                                                                    class="btn btn-receive btn-sm" title="รับสินค้าเข้า">
                                                                    <i class="fas fa-truck-loading"></i>
                                                                </a>
                                                            <?php endif; ?>

                                                            <a href="edit_purchase_order.php?id=<?= $row['purchase_id'] ?>"
                                                                class="btn btn-edit btn-sm" title="แก้ไข PO">
                                                                <i class="fas fa-edit"></i>
                                                            </a>

                                                        <?php endif; ?>

                                                        <a href="view_purchase_order.php?id=<?= $row['purchase_id'] ?>"
                                                            class="btn btn-info btn-sm" title="ดูรายละเอียด">
                                                            <i class="fas fa-eye"></i>
                                                        </a>

                                                        <?php if ($row['po_status'] == 'Pending'): ?>
                                                            <button class="btn btn-warning btn-sm cancel-btn"
                                                                data-id="<?= $row['purchase_id'] ?>"
                                                                data-name="PO #<?= $row['purchase_id'] ?>"
                                                                title="ยกเลิก PO">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8">
                                                    <div class="empty-state">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                        <h4>ไม่พบข้อมูลใบสั่งซื้อ</h4>
                                                        <?php if (!empty($search)): ?>
                                                            <p>ไม่พบข้อมูลที่ตรงกับคำค้นหา "<?= htmlspecialchars($search) ?>"</p>
                                                        <?php else: ?>
                                                            <p>ยังไม่มีการสร้างใบรับเข้าสินค้า</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?><?= build_query_string(['page']) ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= build_query_string(['page']) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?><?= build_query_string(['page']) ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // Modal Delete 
        const deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const poId = this.getAttribute('data-id');
                const poName = this.getAttribute('data-name');
                document.getElementById('deletePoName').textContent = poName;
                document.getElementById('deletePoIdInput').value = poId;
                deleteModal.show();
            });
        });

        // ปุ่มยกเลิก
        const cancelModal = new bootstrap.Modal(document.getElementById('confirmCancelModal'));
        const cancelCommentInput = document.getElementById('cancelCommentInput');
        const cancelForm = document.getElementById('cancelForm');

        document.querySelectorAll('.cancel-btn').forEach(button => {
            button.addEventListener('click', function() {
                const poId = this.getAttribute('data-id');
                const poName = this.getAttribute('data-name');

                // ใส่ ID และชื่อใน Modal
                document.getElementById('cancelPoName').textContent = poName;
                document.getElementById('cancelPoIdInput').value = poId;

                // ล้างค่า textarea เก่า และลบ error
                cancelCommentInput.value = '';
                cancelCommentInput.classList.remove('is-invalid');

                // แสดง Modal
                cancelModal.show();
            });
        });

        //  เพิ่มการ Validation ก่อน Submit
        cancelForm.addEventListener('submit', function(event) {
            if (!cancelCommentInput.value.trim()) {
                event.preventDefault(); // หยุดการส่งฟอร์ม
                cancelCommentInput.classList.add('is-invalid'); // แสดงกรอบสีแดง
            } else {
                cancelCommentInput.classList.remove('is-invalid');
            }
        });
    </script>
</body>

</html>