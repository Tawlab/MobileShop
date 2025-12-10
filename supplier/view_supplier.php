<?php
session_start();
require '../config/config.php';
checkPageAccess($conn, 'view_supplier');

$supplier_id = $_GET['id'] ?? '';
$data = null;

if (empty($supplier_id)) {
    header("Location: supplier.php");
    exit();
}

// ดึงข้อมูล 
$sql = "SELECT 
            s.*, 
            p.prefix_th,
            a.home_no, a.moo, a.soi, a.road, a.village,
            sd.subdistrict_name_th,
            d.district_name_th,
            pv.province_name_th
        FROM suppliers s
        LEFT JOIN prefixs p ON s.prefixs_prefix_id = p.prefix_id
        LEFT JOIN addresses a ON s.Addresses_address_id = a.address_id
        LEFT JOIN subdistricts sd ON a.subdistricts_subdistrict_id = sd.subdistrict_id
        LEFT JOIN districts d ON sd.districts_district_id = d.district_id
        LEFT JOIN provinces pv ON d.provinces_province_id = pv.province_id
        WHERE s.supplier_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลซัพพลายเออร์'); window.location='supplier.php';</script>";
    exit();
}

//  จัดการข้อมูลสำหรับแสดงผล
$contact_name = htmlspecialchars($data['prefix_th'] ?? '');
$contact_name .= htmlspecialchars($data['contact_firstname'] ?? '');
$contact_name .= ' ' . htmlspecialchars($data['contact_lastname'] ?? '');
if (trim($contact_name) === '') {
    $contact_name = '-';
}

// ฟังก์ชันสำหรับแสดง '-' ถ้าค่าว่าง
function displayValue($value)
{
    return htmlspecialchars($value ?? '-');
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดผู้จัดจำหน่าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php include '../config/load_theme.php';
    ?>
    <style>
        h5 {
            margin-top: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid <?= $theme_color ?>;
            font-weight: 600;
            color: <?= $theme_color ?>;
        }

        .form-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px 25px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .label-col {
            width: 150px;
            font-weight: 500;
            vertical-align: middle;
            padding-top: 5px;
            color: #555;
        }

        .value-col {
            padding-top: 5px;
        }

        .view-field {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 0.95rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #e9ecef;
            border: 1px solid;
            border-radius: 0.375rem;
            min-height: calc(1.5em + 0.75rem + 2px);
        }
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container my-4" style="max-width: 900px;">
                    <h4 class="mb-4">
                        <i class="bi bi-eye-fill me-2"></i>
                        รายละเอียดผู้จัดจำหน่าย (ID: <?= htmlspecialchars($data['supplier_id']) ?>)
                    </h4>

                    <div class="form-section">
                        <h5>ข้อมูลผู้จัดจำหน่าย</h5>
                        <table>
                            <tr>
                                <td class="label-col">ชื่อบริษัท:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['co_name']) ?></div>
                                </td>
                                <td class="label-col">เลขผู้เสียภาษี:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['tax_id']) ?></div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="form-section">
                        <h5>ข้อมูลผู้ติดต่อ</h5>
                        <table>
                            <tr>
                                <td class="label-col">ชื่อผู้ติดต่อ:</td>
                                <td class="value-col" colspan="3">
                                    <div class="view-field border-secondary"><?= $contact_name ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">เบอร์โทร:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['supplier_phone_no']) ?></div>
                                </td>
                                <td class="label-col">อีเมล:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['supplier_email']) ?></div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="form-section">
                        <h5>ที่อยู่</h5>
                        <table>
                            <tr>
                                <td class="label-col">บ้านเลขที่:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['home_no']) ?></div>
                                </td>
                                <td class="label-col">หมู่ที่:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['moo']) ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">ซอย:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['soi']) ?></div>
                                </td>
                                <td class="label-col">หมู่บ้าน:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['village']) ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">ถนน:</td>
                                <td class="value-col" colspan="3">
                                    <div class="view-field border-secondary"><?= displayValue($data['road']) ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">จังหวัด:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['province_name_th']) ?></div>
                                </td>
                                <td class="label-col">อำเภอ:</td>
                                <td class="value-col">
                                    <div class="view-field border-secondary"><?= displayValue($data['district_name_th']) ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-col">ตำบล:</td>
                                <td class="value-col" colspan="3">
                                    <div class="view-field border-secondary"><?= displayValue($data['subdistrict_name_th']) ?></div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="text-end mt-4">
                        <a href="edit_supplier.php?id=<?= htmlspecialchars($supplier_id) ?>" class="btn btn-edit">
                            <i class="bi bi-pencil-fill me-1"></i> แก้ไข
                        </a>
                        <a href="supplier.php" class="btn btn-outline-secondary">
                            <i class="bi bi-chevron-left me-1"></i> ย้อนกลับ
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>