<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'add_sale');
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ชำระเงิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php require '../config/load_theme.php'; ?>
    <style>
        body {
            background: #f8f9fa;
        }

        .container {
            max-width: 750px;
            margin-top: 40px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 12px #ccc;
        }

        h4 {
            font-weight: bold;
            margin-bottom: 25px;
            color: #198754;
        }

        .card-option {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .card-option:hover {
            transform: scale(1.02);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }

        .card-option.selected {
            border: 2px solid #198754;
        }

        .icon-lg {
            font-size: 2rem;
            color: #198754;
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">

                <div class="container">
                    <h4><i class="fas fa-credit-card me-2"></i>ช่องทางการชำระเงิน</h4>
                    <form id="paymentForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card card-option p-3" onclick="selectMethod('cash')" id="card-cash">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-money-bill-wave icon-lg me-3"></i>
                                        <div>
                                            <h6 class="mb-1">ชำระด้วยเงินสด</h6>
                                            <small class="text-muted">เหมาะสำหรับการชำระที่จุดขาย</small>
                                        </div>
                                    </div>
                                    <input type="radio" name="payment_method" value="cash" class="form-check-input d-none" checked>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-option p-3" onclick="selectMethod('qr')" id="card-qr">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-qrcode icon-lg me-3"></i>
                                        <div>
                                            <h6 class="mb-1">QR Code</h6>
                                            <small class="text-muted">ระบบนี้ยังไม่เปิดใช้งาน</small>
                                        </div>
                                    </div>
                                    <input type="radio" name="payment_method" value="qr" class="form-check-input d-none">
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="button" class="btn btn-success" onclick="showCashConfirm()">ยืนยันการชำระเงิน</button>
                        </div>
                    </form>
                </div>

                <!-- Popup ยืนยันชำระเงิน -->
                <div class="modal fade" id="cashModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-light">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">ยืนยันการชำระเงิน</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>คุณต้องการยืนยันการชำระเงินด้วยเงินสดใช่หรือไม่?</p>
                                <p><b>ยอดสุทธิ:</b> 6,953.93 บาท</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                <a href="bill_sale.php" class="btn btn-primary">ยืนยันชำระเงิน</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function selectMethod(method) {
            document.querySelectorAll('.card-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`#card-${method}`).classList.add('selected');
            document.querySelector(`#card-${method} input[type=radio]`).checked = true;
        }

        function showCashConfirm() {
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            if (method === 'cash') {
                new bootstrap.Modal(document.getElementById('cashModal')).show();
            } else {
                alert('ระบบ QR ยังไม่เปิดใช้งาน');
            }
        }
    </script>

    <script src="https://kit.fontawesome.com/4f2c6f7b67.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>