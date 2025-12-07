<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/config.php'; // เรียกใช้ config เพื่อใช้ connection และ function hasPermission

$sidebar_uid = $_SESSION['user_id'] ?? 0;
$sidebar_username = $_SESSION['username'] ?? 'User';
$user_avatar_char = strtoupper(substr($sidebar_username, 0, 1));

// Helper: เช็ค Active Menu
function isActive($keywords)
{
    $current_url = $_SERVER['PHP_SELF'];
    if (!is_array($keywords)) $keywords = [$keywords];
    foreach ($keywords as $key) {
        if (strpos($current_url, $key) !== false) return 'active';
    }
    return '';
}

// Helper: เปิดเมนูย่อยค้างไว้
function isExpanded($keywords)
{
    return isActive($keywords) ? 'show' : '';
}

// Helper: ไฮไลท์หัวข้อหลัก (ปรับสีให้เข้ากับพื้นหลังเขียว)
function isGroupActive($keywords)
{
    return isActive($keywords) ? 'text-white fw-bold bg-white-10' : 'text-white-80';
}
?>

<style>
    :root {
        --sidebar-width: 260px;
        --sidebar-bg-gradient: linear-gradient(180deg, #10b981 0%, #047857 100%);
        /* พื้นหลังเขียวไล่เฉด */
        --sidebar-text: #ffffff;
        --sidebar-text-muted: rgba(255, 255, 255, 0.7);
        --sidebar-hover-bg: rgba(255, 255, 255, 0.15);
        --sidebar-active-bg: #ffffff;
        --sidebar-active-text: #047857;
        /* สีเขียวเข้มสำหรับตัวที่เลือกอยู่ */
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
        background: var(--sidebar-bg-gradient);
        color: var(--sidebar-text);
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    /* Scrollbar สวยๆ */
    #sidebar-wrapper::-webkit-scrollbar {
        width: 6px;
    }

    #sidebar-wrapper::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
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
        padding: 1.5rem 1.25rem;
        font-size: 1.4rem;
        font-weight: 800;
        color: #fff;
        display: flex;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(0, 0, 0, 0.05);
        /* พื้นหลังหัวข้อเข้มขึ้นนิดนึง */
    }

    /* สไตล์รายการเมนู */
    .list-group-item {
        border: none;
        padding: 12px 25px;
        font-size: 0.95rem;
        color: var(--sidebar-text-muted);
        font-weight: 500;
        transition: all 0.2s;
        background-color: transparent;
        display: flex;
        align-items: center;
    }

    .list-group-item:hover {
        background-color: var(--sidebar-hover-bg);
        color: #fff;
        padding-left: 30px;
        /* ขยับขวาเล็กน้อยเมื่อชี้ */
    }

    /* เมนูที่กำลังเลือก (Active) */
    .list-group-item.active {
        background-color: var(--sidebar-active-bg);
        color: var(--sidebar-active-text);
        font-weight: 700;
        border-radius: 0 25px 25px 0;
        /* ทำขอบมนด้านขวา */
        margin-right: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .list-group-item.active i {
        color: var(--sidebar-active-text);
    }

    .list-group-item i {
        width: 30px;
        text-align: center;
        margin-right: 10px;
        font-size: 1.1rem;
    }

    /* เมนูย่อย */
    .submenu {
        background-color: rgba(0, 0, 0, 0.1);
        /* พื้นหลังเข้มขึ้นสำหรับเมนูย่อย */
        /* border-left: 3px solid rgba(255,255,255,0.2); */
    }

    .submenu .list-group-item {
        padding-left: 60px;
        font-size: 0.9rem;
        padding-top: 8px;
        padding-bottom: 8px;
        color: rgba(255, 255, 255, 0.6);
    }

    .submenu .list-group-item:hover {
        color: #fff;
        padding-left: 65px;
    }

    .submenu .list-group-item.active {
        background: transparent;
        color: #fff;
        text-decoration: underline;
        box-shadow: none;
        border-radius: 0;
    }

    .menu-toggle {
        justify-content: space-between;
        cursor: pointer;
    }

    .bg-white-10 {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff !important;
    }

    .sidebar-footer {
        margin-top: auto;
        padding: 20px 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background-color: rgba(0, 0, 0, 0.2);
    }

    .user-avatar {
        width: 42px;
        height: 42px;
        background: #fff;
        color: #047857;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin-right: 12px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .text-white-80 {
        color: rgba(255, 255, 255, 0.8);
    }

    /* ปุ่ม Toggle บนมือถือ */
    #menu-toggle-btn {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 8px 12px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
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

<button class="btn" id="menu-toggle-btn"><i class="fas fa-bars"></i></button>

<div id="sidebar-wrapper">
    <a href="../global/home.php" class="sidebar-heading text-decoration-none">
        <i class="fas fa-mobile-alt me-3"></i> Mobile Shop
    </a>

    <div class="list-group list-group-flush flex-grow-1 pt-3">

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_dashboard')): ?>
            <a href="../home/dashboard.php" class="list-group-item list-group-item-action <?= isActive('dashboard.php') ?>">
                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_employee')): ?>
            <a href="#sub-emp" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['department.php', 'employee.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['department.php', 'employee.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-user-tie"></i> ข้อมูลพนักงาน</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['department.php', 'employee.php']) ?>" id="sub-emp">
                <a href="../department/department.php" class="list-group-item list-group-item-action <?= isActive('department.php') ?>">แผนก</a>
                <a href="../employee/employee.php" class="list-group-item list-group-item-action <?= isActive('employee.php') ?>">พนักงาน</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_customer')): ?>
            <a href="../customer/customer_list.php" class="list-group-item list-group-item-action <?= isActive('customer') ?>">
                <i class="fas fa-users"></i> ลูกค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_supplier')): ?>
            <a href="../supplier/supplier.php" class="list-group-item list-group-item-action <?= isActive('supplier.php') ?>">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_general')): ?>
            <a href="#sub-gen" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-globe"></i> ข้อมูลทั่วไป</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['prename.php', 'religion.php', 'province.php', 'districts.php', 'subdistricts.php']) ?>" id="sub-gen">
                <a href="../prename/prename.php" class="list-group-item list-group-item-action <?= isActive('prename.php') ?>">คำนำหน้านาม</a>
                <a href="../religion/religion.php" class="list-group-item list-group-item-action <?= isActive('religion.php') ?>">ศาสนา</a>
                <a href="../provinces/province.php" class="list-group-item list-group-item-action <?= isActive('province.php') ?>">จังหวัด</a>
                <a href="../districts/districts.php" class="list-group-item list-group-item-action <?= isActive('/districts.php') ?>">อำเภอ</a>
                <a href="../subdistricts/subdistricts.php" class="list-group-item list-group-item-action <?= isActive('subdistricts.php') ?>">ตำบล</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_product')): ?>
            <a href="#sub-prod" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['product.php', 'prodtype.php', 'prodbrand.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['product.php', 'prodtype.php', 'prodbrand.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-mobile"></i> สินค้า</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['product.php', 'prodtype.php', 'prodbrand.php']) ?>" id="sub-prod">
                <a href="../product/product.php" class="list-group-item list-group-item-action <?= isActive('product.php') ?>">สินค้า</a>
                <a href="../prod_type/prodtype.php" class="list-group-item list-group-item-action <?= isActive('prodtype.php') ?>">ประเภทสินค้า</a>
                <a href="../prod_brand/prodbrand.php" class="list-group-item list-group-item-action <?= isActive('prodbrand.php') ?>">ยี่ห้อสินค้า</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_stock')): ?>
            <a href="../prod_stock/prod_stock.php" class="list-group-item list-group-item-action <?= isActive('prod_stock.php') ?>">
                <i class="fas fa-boxes"></i> สต็อคสินค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_purchase')): ?>
            <a href="../purchase/purchase_order.php" class="list-group-item list-group-item-action <?= isActive('purchase_order.php') ?>">
                <i class="fas fa-file-invoice-dollar"></i> การรับเข้าสินค้า
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_sale')): ?>
            <a href="../sales/sale_list.php" class="list-group-item list-group-item-action <?= isActive('sale_list.php') ?>">
                <i class="fas fa-cash-register"></i> การขาย (POS)
            </a>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_repair')): ?>
            <a href="#sub-repair" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['repair_list.php', 'symptoms.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['repair_list.php', 'symptoms.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-tools"></i> การซ่อม</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['repair_list.php', 'symptoms.php']) ?>" id="sub-repair">
                <a href="../repair/repair_list.php" class="list-group-item list-group-item-action <?= isActive('repair_list.php') ?>">รายการซ่อม</a>
                <a href="../symptom/symptoms.php" class="list-group-item list-group-item-action <?= isActive('symptoms.php') ?>">อาการเสีย</a>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_manage_shop') || hasPermission($conn, $sidebar_uid, 'branch')): ?>
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

        <?php if (hasPermission($conn, $sidebar_uid, 'menu_manage_users')): ?>
            <a href="#sub-users" class="list-group-item list-group-item-action menu-toggle <?= isGroupActive(['permission.php', 'role.php']) ?>" data-bs-toggle="collapse" aria-expanded="<?= !empty(isExpanded(['permission.php', 'role.php'])) ? 'true' : 'false' ?>">
                <span><i class="fas fa-user-shield"></i> จัดการผู้ใช้</span>
            </a>
            <div class="collapse submenu <?= isExpanded(['permission.php', 'role.php']) ?>" id="sub-users">
                <a href="../permission/permission.php" class="list-group-item list-group-item-action <?= isActive('permission.php') ?>">สิทธิ์การใช้งาน</a>
                <a href="../role/role.php" class="list-group-item list-group-item-action <?= isActive('role.php') ?>">บทบาทผู้ใช้</a>
            </div>
        <?php endif; ?>

        <div style="height: 50px;"></div>
    </div>
    <div class="sidebar-footer">
        <div class="dropup">
            <a href="#" class="d-flex align-items-center text-decoration-none text-white dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar shadow-sm"><?= $user_avatar_char ?></div>
                <div style="line-height: 1.3;">
                    <div class="fw-bold small text-truncate" style="max-width: 140px;"><?= htmlspecialchars($sidebar_username) ?></div>
                    <small class="text-white-50" style="font-size: 0.75rem;"><i class="fas fa-circle text-success" style="font-size: 8px;"></i> Online</small>
                </div>
            </a>
            <ul class="dropdown-menu shadow-lg border-0 mb-2 p-2">
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