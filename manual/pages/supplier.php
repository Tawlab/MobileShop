<?php
require '_layout.php';
layout_head('ผู้จำหน่าย (Supplier)', 'truck', '#14b8a6', 'จัดการข้อมูลคู่ค้า ตัวแทนจำหน่าย และผู้ส่งสินค้า');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบข้อมูลผู้จำหน่ายคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบ Supplier ใช้เก็บข้อมูลผู้จำหน่ายสินค้าที่ร้านค้าติดต่อสั่งซื้อ
        เช่น ตัวแทนจำหน่ายโทรศัพท์ ร้านอะไหล่ และคู่ค้าต่างๆ
        ข้อมูลเหล่านี้จะถูกใช้เมื่อสร้างใบสั่งซื้อ (PO)
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลัก</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus"></i></div>
        <div class="feature-text">
            <strong>เพิ่มผู้จำหน่ายใหม่</strong>
            <span>บันทึกชื่อบริษัท ผู้ติดต่อ เบอร์โทร และที่อยู่</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลผู้จำหน่าย</strong>
            <span>อัปเดตข้อมูลติดต่อเมื่อมีการเปลี่ยนแปลง</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-eye"></i></div>
        <div class="feature-text">
            <strong>ดูประวัติการสั่งซื้อ</strong>
            <span>ตรวจสอบประวัติ PO ที่สั่งกับผู้จำหน่ายแต่ละราย</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มผู้จำหน่ายใหม่</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "Suppliers"</h5>
        <p>คลิกเมนู Suppliers ในแถบด้านซ้าย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มผู้จำหน่ายใหม่"</h5>
        <p>เปิดฟอร์มกรอกข้อมูลผู้จำหน่าย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกข้อมูลให้ครบถ้วน</h5>
        <p>ระบุชื่อบริษัท ผู้ติดต่อ เบอร์โทร อีเมล และที่อยู่จัดส่ง</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>ผู้จำหน่ายพร้อมใช้งานในการสร้าง PO ได้ทันที</p>
    </div>
</div>

<?php layout_foot(); ?>
