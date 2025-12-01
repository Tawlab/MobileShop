<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$sidebar_uid = $_SESSION['user_id'] ?? 0;
$sidebar_username = $_SESSION['username'] ?? 'User';
$user_avatar_char = strtoupper(substr($sidebar_username, 0, 1));

// Helper: เช็ค Active Menu (แก้ไขให้ตรวจสอบแม่นยำขึ้น)
function isActive($keywords)
{
    $current_url = $_SERVER['PHP_SELF'];
    if (!is_array($keywords)) $keywords = [$keywords];
    foreach ($keywords as $key) {
        // ตรวจสอบว่า URL มีคำที่ระบุหรือไม่
        if (strpos($current_url, $key) !== false) return 'active';
    }
    return '';
}

// Helper: เปิดเมนูย่อยค้างไว้
function isExpanded($keywords)
{
    return isActive($keywords) ? 'show' : '';
}

// Helper: ไฮไลท์หัวข้อหลัก
function isGroupActive($keywords)
{
    return isActive($keywords) ? 'text-success fw-bold' : 'text-dark';
}
?>

<style>
    :root {
        --sidebar-width: 250px;
        --sidebar-bg: #ffffff;
        --sidebar-hover: #ecfdf5;
        --sidebar-active: #d1fae5;
        --primary-green: #10b981;
        --text-color: #374151;
    }

    #wrapper {
        display: flex;
        width: 100%;
        overflow-x: hidden;
    }

    #sidebar-wrapper {
        min-height: 100vh;
        width: var(--sidebar-width);
        margin-left: 0;
        transition: margin 0.25s ease-out;
        background-color: var(--sidebar-bg);
        border-right: 1px solid #e5e7eb;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    #wrapper.toggled #sidebar-wrapper {
        margin-left: calc(-1 * var(--sidebar-width));
    }

    .main-content {
        width: 100%;
        margin-left: var(--sidebar-width);
        transition: margin 0.25s ease-out;
    }

    #wrapper.toggled .main-content {
        margin-left: 0;
    }

    .sidebar-heading {
        padding: 1.2rem 1.25rem;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--primary-green);
        display: flex;
        align-items: center;
        border-bottom: 1px solid #f3f4f6;
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
    }

    .list-group-item {
        border: none;
        padding: 10px 20px;
        font-size: 0.9rem;
        color: var(--text-color);
        font-weight: 500;
        transition: all 0.2s;
        background-color: transparent;
        display: flex;
        align-items: center;
    }

    .list-group-item:hover {
        background-color: var(--sidebar-hover);
        color: var(--primary-green);
        padding-left: 25px;
    }

    .list-group-item.active {
        background-color: var(--sidebar-active);
        color: #047857;
        font-weight: 700;
        border-left: 4px solid var(--primary-green);
    }

    .list-group-item i {
        width: 25px;
        text-align: center;
        margin-right: 10px;
        font-size: 1rem;
    }

    .submenu {
        background-color: #f9fafb;
        border-top: 1px solid #f3f4f6;
        border-bottom: 1px solid #f3f4f6;
    }

    .submenu .list-group-item {
        padding-left: 55px;
        font-size: 0.85rem;
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .menu-toggle {
        justify-content: space-between;
        cursor: pointer;
    }

    .menu-toggle::after {
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
        content: "\f107";
        transition: transform 0.3s;
        font-size: 0.8rem;
        opacity: 0.5;
    }

    .menu-toggle[aria-expanded="true"]::after {
        transform: rotate(-180deg);
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 15px;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
        position: sticky;
        bottom: 0;
        z-index: 10;
    }

    .user-avatar {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 10px;
    }

    #menu-toggle-btn {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 5px 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        display: none;
    }

    @media (max-width: 768px) {
        #sidebar-wrapper {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        #wrapper.toggled #sidebar-wrapper {
            margin-left: 0;
        }

        .main-content {
            margin-left: 0 !important;
        }

        #menu-toggle-btn {
            display: block;
        }
    }
</style>

<button class="btn" id="menu-toggle-btn"><i class="fas fa-bars text-success"></i></button>

<div id="sidebar-wrapper">
    <div class="sidebar-heading"><i class="fas fa-mobile-alt me-2"></i> Mobile Shop</div>

    <div class="list-group list-group-flush flex-grow-1">

        <a href="../home/dashboard.php" class="list-group-item list-group-item-action <?= isActive('dashboard.php') ?>">
            <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
        </a>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_employee')): ?>
            <a href="#sub-emp" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['department.php', 'employee.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['department.php', 'employee.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-user-tie"></i> ข้อมูลพนักงาน</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['department.php', 'employee.php']) ?>" id="sub-emp">
                <a href="../department/department.php" class="list-group-item list-group-item-action <?= isActive('department.php') ?>">แผนก</a>
                <a href="../employee/employee.php" class="list-group-item list-group-item-action <?= isActive('employee.php') ?>">พนักงาน</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_customer')): ?>
            <a href="../customer/customer_list.php" class="list-group-item list-group-item-action <?= isActive('customer') ?>">
                <i class="fas fa-users"></i> ลูกค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_supplier')): ?>
            <a href="../supplier/supplier.php" class="list-group-item list-group-item-action <?= isActive('supplier.php') ?>">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_general')): ?>
            <a href="#sub-gen" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-globe"></i> ข้อมูลทั่วไป</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php']) ?>" id="sub-gen">
                <a href="../prename/prename.php" class="list-group-item list-group-item-action <?= isActive('prename.php') ?>">คำนำหน้านาม</a>
                <a href="../religion/religion.php" class="list-group-item list-group-item-action <?= isActive('religion.php') ?>">ศาสนา</a>
                <a href="../provinces/province.php" class="list-group-item list-group-item-action <?= isActive('province.php') ?>">จังหวัด</a>
                <a href="../districts/districts.php" class="list-group-item list-group-item-action <?= isActive('districts.php') ?>">อำเภอ</a>
                <a href="../subdistricts/subdistricts.php" class="list-group-item list-group-item-action <?= isActive('subdistricts.php') ?>">ตำบล</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_product')): ?>
            <a href="#sub-prod" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['product.php', 'prodtype.php', 'prodbrand.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['product.php', 'prodtype.php', 'prodbrand.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-box-open"></i> สินค้า</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['product.php', 'prodtype.php', 'prodbrand.php']) ?>" id="sub-prod">
                <a href="../product/product.php" class="list-group-item list-group-item-action <?= isActive('product.php') ?>">สินค้า</a>
                <a href="../prod_type/prodtype.php" class="list-group-item list-group-item-action <?= isActive('prodtype.php') ?>">ประเภทสินค้า</a>
                <a href="../prod_brand/prodbrand.php" class="list-group-item list-group-item-action <?= isActive('prodbrand.php') ?>">ยี่ห้อสินค้า</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_stock')): ?>
            <a href="../prod_stock/prod_stock.php" class="list-group-item list-group-item-action <?= isActive('prod_stock.php') ?>">
                <i class="fas fa-cubes"></i> สต็อคสินค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_purchase')): ?>
            <a href="../purchase/purchase_order.php" class="list-group-item list-group-item-action <?= isActive('purchase_order.php') ?>">
                <i class="fas fa-cart-arrow-down"></i> การรับเข้าสินค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_sale')): ?>
            <a href="../sales/sale_list.php" class="list-group-item list-group-item-action <?= isActive('sale_list.php') ?>">
                <i class="fas fa-cash-register"></i> การขาย
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'view_repair')): ?>
            <a href="#sub-repair" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['repair_list.php', 'symptoms.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['repair_list.php', 'symptoms.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-tools"></i> การซ่อม</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['repair_list.php', 'symptoms.php']) ?>" id="sub-repair">
                <a href="../repair/repair_list.php" class="list-group-item list-group-item-action <?= isActive('repair_list.php') ?>">รายการซ่อม</a>
                <a href="../symptom/symptoms.php" class="list-group-item list-group-item-action <?= isActive('symptoms.php') ?>">อาการเสีย</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'manage_shop') || hasPermission($conn, $sidebar_uid, 'branch')): ?>
            <a href="#sub-shop" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['shop.php', 'branch.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['shop.php', 'branch.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-store"></i> ข้อมูลร้านค้า</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['shop.php', 'branch.php']) ?>" id="sub-shop">
                <?php if (hasPermission($conn, $sidebar_uid, 'manage_shop')): ?>
                    <a href="../shop/shop.php" class="list-group-item list-group-item-action <?= isActive('shop.php') ?>">ข้อมูลร้านค้า</a>
                <?php endif; ?>

                <?php if (hasPermission($conn, $sidebar_uid, 'branch')): ?>
                    <a href="../branch/branch.php" class="list-group-item list-group-item-action <?= isActive('branch.php') ?>">สาขา</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'manage_users')): ?>
            <a href="#sub-users" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['permission.php', 'role.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['permission.php', 'role.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-user-shield"></i> จัดการผู้ใช้</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['permission.php', 'role.php']) ?>" id="sub-users">
                <a href="../permission/permission.php" class="list-group-item list-group-item-action <?= isActive('permission.php') ?>">สิทธิ์</a>
                <a href="../role/role.php" class="list-group-item list-group-item-action <?= isActive('role.php') ?>">บทบาท</a>
            </div>
        <?php endif; ?>

        <div style="height: 50px;"></div>
    </div>

    <div class="sidebar-footer">
        <div class="dropup">
            <a href="#" class="d-flex align-items-center text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar shadow-sm"><?= $user_avatar_char ?></div>
                <div style="line-height: 1.3;">
                    <div class="fw-bold small text-truncate" style="max-width: 130px;"><?= htmlspecialchars($sidebar_username) ?></div>
                    <small class="text-success" style="font-size: 0.75rem;"><i class="fas fa-circle" style="font-size: 8px;"></i> Online</small>
                </div>
            </a>
            <ul class="dropdown-menu shadow border-0 mb-2 p-2">
                <li><a class="dropdown-item rounded" href="../profile/change_profile.php"><i class="fas fa-user-circle me-2 text-muted"></i> ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item rounded" href="../profile/change_password.php"><i class="fas fa-key me-2 text-muted"></i> เปลี่ยนรหัสผ่าน</a></li>
                <li><a class="dropdown-item rounded" href="../system_config/settings.php"><i class="fas fa-palette me-2 text-muted"></i> ธีม</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item rounded text-danger" href="../global/logout.php"><i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.getElementById("wrapper");
        const toggleBtn = document.getElementById("menu-toggle-btn");
        if (toggleBtn) {
            toggleBtn.onclick = function() {
                wrapper.classList.toggle("toggled");
            };
        }
    });
</script>