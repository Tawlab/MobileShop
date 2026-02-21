<?php
require '_layout.php';
layout_head('การขายสินค้า (POS)', 'cash-register', '#10b981', 'ระบบขายหน้าร้าน บันทึกบิล รับชำระเงิน ออกใบเสร็จรับเงิน');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบ POS คืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบ POS (Point of Sale) คือระบบขายหน้าร้านที่ช่วยให้พนักงานสามารถบันทึกรายการขาย
        เลือกสินค้า กำหนดราคา รับชำระเงินทั้งเงินสดและ QR Code
        พร้อมออกใบเสร็จรับเงินให้ลูกค้าได้อย่างรวดเร็ว
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลักในระบบขาย</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus"></i></div>
        <div class="feature-text">
            <strong>สร้างบิลขายใหม่</strong>
            <span>เปิดบิลใหม่ เลือกสินค้าจากสต็อค กำหนดจำนวน และราคาขาย</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
        <div class="feature-text">
            <strong>รับชำระเงิน</strong>
            <span>รองรับการชำระด้วยเงินสด บัตรเครดิต/เดบิต และ QR Code พร้อมระบบทอน</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-print"></i></div>
        <div class="feature-text">
            <strong>พิมพ์ใบเสร็จรับเงิน</strong>
            <span>ออกใบเสร็จให้ลูกค้าได้ทันทีหลังชำระเสร็จสิ้น</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-times-circle"></i></div>
        <div class="feature-text">
            <strong>ยกเลิกบิล</strong>
            <span>ยกเลิกรายการขายพร้อมให้ระบุเหตุผล ระบบจะคืนสต็อคอัตโนมัติ</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-eye"></i></div>
        <div class="feature-text">
            <strong>ดูประวัติการขาย</strong>
            <span>ค้นหาและตรวจสอบรายการขายย้อนหลัง กรองตามวันที่หรือชื่อลูกค้า</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> ขั้นตอนการขายสินค้า</div>

<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "การขาย (POS)"</h5>
        <p>คลิกที่เมนู <strong>การขาย (POS)</strong> ในแถบด้านซ้าย จะปรากฏรายการบิลทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "สร้างบิลใหม่"</h5>
        <p>คลิกปุ่ม <strong>+ สร้างบิลใหม่</strong> เพื่อเริ่มต้นรายการขาย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>เลือกลูกค้าและสินค้า</h5>
        <p>ค้นหาและเลือกลูกค้า (หรือขายแบบไม่ระบุลูกค้า) จากนั้นเลือกสินค้าที่ต้องการขาย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กำหนดจำนวนและราคา</h5>
        <p>ตรวจสอบจำนวนสินค้าและราคา สามารถให้ส่วนลดพิเศษได้หากมีสิทธิ์</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">5</div>
    <div class="step-content">
        <h5>เลือกวิธีชำระเงิน</h5>
        <p>เลือกชำระด้วย <strong>เงินสด</strong> หรือ <strong>QR Code</strong> ระบบจะคำนวณเงินทอนให้อัตโนมัติ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">6</div>
    <div class="step-content">
        <h5>ยืนยันและออกใบเสร็จ</h5>
        <p>กดยืนยันการขาย ระบบจะตัดสต็อคอัตโนมัติและออกใบเสร็จให้ลูกค้า</p>
    </div>
</div>

<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>ข้อควรระวัง:</strong> หลังจากยืนยันบิลแล้วจะไม่สามารถแก้ไขได้
        หากต้องการเปลี่ยนแปลง ต้องยกเลิกบิลและสร้างใหม่เท่านั้น
    </div>
</div>

<?php layout_foot(); ?>
