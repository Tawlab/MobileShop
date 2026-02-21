<?php
require '_layout.php';
layout_head('คำนำหน้านาม / ศาสนา', 'font', '#eab308', 'จัดการคำนำหน้านาม ศาสนา จังหวัด อำเภอ และตำบล');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไปคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ข้อมูลทั่วไปเป็นข้อมูลพื้นฐานที่ระบบต้องการสำหรับการกรอกข้อมูลพนักงานและลูกค้า
        เช่น คำนำหน้านาม (นาย, นาง, นางสาว) ศาสนา จังหวัด อำเภอ และตำบล
        ระบบมาพร้อมข้อมูลเตรียมไว้ให้แล้ว แต่สามารถเพิ่มเติมหรือแก้ไขได้
    </p>
</div>

<div class="section-label"><i class="fas fa-list"></i> รายการข้อมูลทั่วไป</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-font"></i></div>
        <div class="feature-text"><strong>คำนำหน้านาม</strong><span>เช่น นาย, นาง, นางสาว, ดร., ศ., ฯลฯ</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-pray"></i></div>
        <div class="feature-text"><strong>ศาสนา</strong><span>รายชื่อศาสนาสำหรับระบุในข้อมูลพนักงาน</span></div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div>
        <div class="feature-text"><strong>จังหวัด, อำเภอ, ตำบล</strong><span>ข้อมูลพื้นที่สำหรับกรอกที่อยู่ในระบบ เชื่อมโยงกันอัตโนมัติ</span></div>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>คำแนะนำ:</strong> ข้อมูลเหล่านี้โดยทั่วไปไม่จำเป็นต้องแก้ไข
    เว้นแต่ต้องการเพิ่มคำนำหน้าหรือศาสนาพิเศษที่ไม่มีในรายการ</div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มคำนำหน้านาม</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>ไปที่เมนู "ข้อมูลทั่วไป" → "คำนำหน้านาม"</h5><p>คลิกเมนูเพื่อดูรายการคำนำหน้าทั้งหมด</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>กดปุ่ม "เพิ่มคำนำหน้าใหม่"</h5><p>กรอกคำนำหน้าที่ต้องการ</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>กดบันทึก</h5><p>คำนำหน้าพร้อมใช้งานในฟอร์มเพิ่มพนักงาน/ลูกค้า</p></div></div>
<?php layout_foot(); ?>
