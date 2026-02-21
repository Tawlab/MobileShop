<?php
require '_layout.php';
layout_head('ข้อมูลร้านค้าและสาขา', 'store', '#10b981', 'แก้ไขข้อมูลร้าน และจัดการสาขาที่ให้บริการ');
?>

<div class="section-label"><i class="fas fa-store"></i> ข้อมูลร้านค้า</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        หน้าข้อมูลร้านค้าใช้แก้ไขรายละเอียดของร้าน เช่น ชื่อร้าน ที่อยู่ เบอร์โทรศัพท์
        และโลโก้ร้าน ข้อมูลเหล่านี้จะปรากฏบนใบเสร็จรับเงินและเอกสารต่างๆ ของระบบ
    </p>
</div>

<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลร้านค้า</strong>
            <span>อัปเดตชื่อร้าน ที่อยู่ เบอร์โทร และข้อมูลติดต่ออื่นๆ</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-image"></i></div>
        <div class="feature-text">
            <strong>อัปโหลดโลโก้ร้าน</strong>
            <span>เลือกรูปโลโก้ของร้านเพื่อให้แสดงบนเอกสารต่างๆ</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-code-branch"></i> สาขา</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        หน้าสาขาใช้จัดการสาขาของร้านค้า สามารถเพิ่มสาขาใหม่ แก้ไขชื่อและที่อยู่สาขา
        หรือลบสาขาที่ปิดให้บริการแล้วออกจากระบบ
    </p>
</div>

<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus"></i></div>
        <div class="feature-text">
            <strong>เพิ่มสาขาใหม่</strong>
            <span>บันทึกชื่อสาขา ที่อยู่ และเบอร์โทรของสาขา</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลสาขา</strong>
            <span>อัปเดตรายละเอียดของสาขาที่มีอยู่</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="feature-text">
            <strong>ลบสาขา</strong>
            <span>ลบสาขาที่ปิดให้บริการแล้วออกจากระบบ</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีแก้ไขข้อมูลร้านค้า</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "ข้อมูลร้านค้า" → "ข้อมูลร้านค้า"</h5>
        <p>คลิกเมนูร้านค้าในแถบด้านซ้าย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "แก้ไข"</h5>
        <p>เปิดฟอร์มแก้ไขข้อมูลร้านค้า</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>อัปเดตข้อมูลที่ต้องการเปลี่ยน</h5>
        <p>แก้ไขชื่อร้าน ที่อยู่ เบอร์โทร หรืออัปโหลดโลโก้ใหม่</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>ข้อมูลจะอัปเดตและแสดงบนใบเสร็จทันที</p>
    </div>
</div>

<?php layout_foot(); ?>
