<?php
session_start();
require '../config/config.php'; // (1) ต้องใช้ $conn สำหรับ Query
checkPageAccess($conn, 'prename');

// (2) แก้ Query ให้ตรงกับฐานข้อมูล (ตาราง prefixs, คอลัมน์ prefix_id)
$result = $conn->query("SELECT * FROM prefixs ORDER BY prefix_id ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการคำนำหน้า</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">


    <?php
    // (3) โหลดธีมจาก System Config
    // สมมติว่าไฟล์ load_theme.php อยู่ใน ../config/
    // ไฟล์นี้จะ output <link> และ <style> ที่จำเป็นทั้งหมด
    require '../config/load_theme.php';
    ?>

    <style>
        .container {
            max-width: 900px;
        }

        th,
        td {
            vertical-align: middle;
            text-align: center;
        }

        /* .status-icon และ .inactive ถูกกำหนดใน load_theme.php แล้ว */
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include '../global/sidebar.php'; ?>
        <div class="main-content w-100">
            <div class="container-fluid py-4">
                <div class="container py-4">
                    <div class="card shadow-lg rounded-4 p-4">

                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                ✅ <?php echo htmlspecialchars($_GET['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="m-0"><i class="bi bi-card-list me-2"></i>รายการคำนำหน้า</h4>

                            <a href="add_prename.php" class="btn btn-success">
                                <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มคำนำหน้า
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-striped align-middle">
                                <thead class="">
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รหัส</th>
                                        <th>ชื่อไทย</th>
                                        <th>ชื่อย่อไทย</th>
                                        <th>ชื่ออังกฤษ</th>
                                        <th>ชื่อย่ออังกฤษ</th>
                                        <th>สถานะ</th>
                                        <th>การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $index = 1;
                                    // (8) แก้ไขชื่อคอลัมน์ทั้งหมดให้ตรงกับ DB
                                    while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $index++ ?></td>
                                            <td><?= htmlspecialchars($row['prefix_id']) ?></td>
                                            <td><?= htmlspecialchars($row['prefix_th']) ?></td>
                                            <td><?= htmlspecialchars($row['prefix_th_abbr']) ?></td>
                                            <td><?= htmlspecialchars($row['prefix_en']) ?></td>
                                            <td><?= htmlspecialchars($row['prefix_en_abbr']) ?></td>
                                            <td>
                                                <i class="bi bi-check-circle-fill status-icon on toggle-status <?= $row['is_active'] ? '' : 'inactive' ?>"
                                                    data-id="<?= $row['prefix_id'] ?>" data-status="1" title="เปิดใช้งาน"></i>
                                                <i class="bi bi-x-circle-fill status-icon off toggle-status <?= !$row['is_active'] ? '' : 'inactive' ?>"
                                                    data-id="<?= $row['prefix_id'] ?>" data-status="0" title="ปิดการใช้งาน"></i>
                                            </td>

                                            <td>
                                                <a href="edit_prename.php?id=<?= $row['prefix_id'] ?>" class="btn btn-edit btn-sm me-1">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="delete_prename.php?id=<?= $row['prefix_id'] ?>" class="btn btn-delete btn-sm"
                                                    onclick="return confirm('ยืนยันการลบคำนำหน้านี้?')">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.toggle-status').forEach(icon => {
            icon.addEventListener('click', () => {
                const id = icon.dataset.id;
                const newStatus = icon.dataset.status; // สถานะใหม่ที่ต้องการ

                fetch('toggle_prename_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        // (11) ส่ง id กลับไปเป็น 'id' ตามที่ไฟล์ toggle_prename_status.php คาดหวัง
                        body: `id=${id}&status=${newStatus}`
                    })
                    .then(res => res.text())
                    .then(text => {
                        console.log(text); // Debug
                        if (text.trim() === 'updated') {
                            // อัปเดต UI ทั้งสองไอคอน
                            const iconsInGroup = document.querySelectorAll(`.toggle-status[data-id="${id}"]`);
                            iconsInGroup.forEach(i => {
                                if (i.dataset.status === newStatus) {
                                    i.classList.remove('inactive'); // เปิดใช้งานไอคอนที่คลิก
                                } else {
                                    i.classList.add('inactive'); // ปิดใช้งานไอคอนตรงข้าม
                                }
                            });
                        }
                    })
                    .catch(err => console.error(err));
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>