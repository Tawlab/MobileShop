<?php
require '_layout.php';
layout_head('จังหวัด / อำเภอ / ตำบล', 'map-marked-alt', '#eab308', 'จัดการข้อมูลพื้นที่สำหรับกรอกที่อยู่ในระบบ');
?>
<div class="section-label"><i class="fas fa-info-circle"></i> ข้อมูลพื้นที่คืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบจัดเก็บข้อมูลจังหวัด อำเภอ และตำบลทั่วประเทศ เพื่อใช้สำหรับกรอกที่อยู่ของ
        พนักงาน ลูกค้า และร้านค้า โดยเมื่อเลือกจังหวัด รายการอำเภอจะปรากฏอัตโนมัติ
        และเมื่อเลือกอำเภอ รายการตำบลก็จะโหลดขึ้นมาเองทันที
    </p>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div><strong>คำแนะนำ:</strong> ข้อมูลจังหวัด อำเภอ และตำบลในระบบครบถ้วนแล้ว
    ไม่จำเป็นต้องเพิ่มเติม เว้นแต่มีการเปลี่ยนแปลงจากทางราชการ</div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีการทำงานของระบบพื้นที่</div>
<div class="step-card"><div class="step-num">1</div><div class="step-content"><h5>เมื่อกรอกที่อยู่ในแบบฟอร์ม ให้เลือกจังหวัดก่อน</h5><p>รายการจังหวัดทั้งหมดจะแสดงใน Dropdown</p></div></div>
<div class="step-card"><div class="step-num">2</div><div class="step-content"><h5>เลือกอำเภอ</h5><p>หลังเลือกจังหวัด รายการอำเภอในจังหวัดนั้นจะโหลดขึ้นโดยอัตโนมัติ</p></div></div>
<div class="step-card"><div class="step-num">3</div><div class="step-content"><h5>เลือกตำบล</h5><p>หลังเลือกอำเภอ รายการตำบลจะโหลดขึ้นโดยอัตโนมัติ</p></div></div>
<div class="step-card"><div class="step-num">4</div><div class="step-content"><h5>รหัสไปรษณีย์จะกรอกให้อัตโนมัติ</h5><p>เมื่อเลือกตำบลแล้ว รหัสไปรษณีย์จะถูกกรอกให้โดยอัตโนมัติ</p></div></div>
<?php layout_foot(); ?>
