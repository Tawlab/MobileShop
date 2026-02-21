<?php
require '_layout.php';
layout_head('สาขา', 'code-branch', '#3b82f6', 'จัดการสาขาของร้านค้า เพิ่ม/แก้ไข/ลบสาขาที่ให้บริการ');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> ระบบสาขาคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบสาขาช่วยจัดการข้อมูลสาขาต่างๆ ของร้าน ไม่ว่าจะมีกี่สาขา สามารถเพิ่ม แก้ไข
        และดูข้อมูลแต่ละสาขาได้อย่างสะดวก
    </p>
</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus"></i></div>
        <div class="feature-text"><strong>เพิ่มสาขาใหม่</strong><span>บันทึกชื่อสาขา ที่อยู่ และเบอร์โทรของสาขา</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text"><strong>แก้ไขข้อมูลสาขา</strong><span>อัปเดตรายละเอียดสาขาเมื่อมีการเปลี่ยนแปลง</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="feature-text"><strong>ลบสาขา</strong><span>ลบสาขาที่ไม่ได้ใช้งานออกจากระบบ</span></div>
    </div>
</div>
<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มสาขาใหม่</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "ข้อมูลร้านค้า" → "สาขา"</h5>
        <p>คลิกเมนูด้านซ้ายเพื่อดูรายการสาขาทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มสาขาใหม่"</h5>
        <p>เปิดฟอร์มกรอกข้อมูลสาขา</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกชื่อและที่อยู่สาขา</h5>
        <p>ระบุชื่อสาขา ที่อยู่ เบอร์โทรให้ครบ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>สาขาจะถูกเพิ่มในระบบเรียบร้อย</p>
    </div>
</div>
<?php layout_foot(); ?>