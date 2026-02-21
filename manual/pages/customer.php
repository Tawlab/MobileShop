<?php
require '_layout.php';
layout_head('ลูกค้า', 'user-friends', '#8b5cf6', 'บันทึกข้อมูลสมาชิก ประวัติการซื้อ และการซ่อมของลูกค้า');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบข้อมูลลูกค้าคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบข้อมูลลูกค้าช่วยเก็บประวัติลูกค้าทั้งหมดของร้าน ไม่ว่าจะเป็นข้อมูลติดต่อ
        ประวัติการซื้อสินค้า และประวัติการนำโทรศัพท์มาซ่อม ช่วยให้พนักงานให้บริการลูกค้าได้อย่างเป็นระบบ
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลัก</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-user-plus"></i></div>
        <div class="feature-text">
            <strong>เพิ่มลูกค้าใหม่</strong>
            <span>บันทึกชื่อ เบอร์โทร ที่อยู่ และข้อมูลส่วนตัวของลูกค้า</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-search"></i></div>
        <div class="feature-text">
            <strong>ค้นหาลูกค้า</strong>
            <span>ค้นหาด้วยชื่อ เบอร์โทร หรือรหัสลูกค้า</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-history"></i></div>
        <div class="feature-text">
            <strong>ดูประวัติลูกค้า</strong>
            <span>ดูรายการซื้อและซ่อมทั้งหมดของลูกค้าแต่ละราย</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-print"></i></div>
        <div class="feature-text">
            <strong>พิมพ์บัตรสมาชิก</strong>
            <span>พิมพ์ข้อมูลลูกค้าเป็นเอกสาร</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลลูกค้า</strong>
            <span>อัปเดตข้อมูลติดต่อหรือที่อยู่เมื่อมีการเปลี่ยนแปลง</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มลูกค้าใหม่</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "ลูกค้า"</h5>
        <p>คลิกเมนูลูกค้าในแถบด้านซ้าย จะแสดงรายชื่อลูกค้าทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มลูกค้าใหม่"</h5>
        <p>เปิดฟอร์มกรอกข้อมูลลูกค้า</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกข้อมูลให้ครบถ้วน</h5>
        <p>ระบุชื่อ-นามสกุล เบอร์โทรศัพท์ อีเมล และที่อยู่ของลูกค้า</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>ลูกค้าจะถูกบันทึกในระบบและพร้อมใช้ในการขายและรับซ่อมทันที</p>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>คำแนะนำ:</strong> ควรบันทึกข้อมูลลูกค้าทุกคนไว้ในระบบ
        เพื่อสะดวกในการติดตามประวัติและให้บริการที่ดียิ่งขึ้น
    </div>
</div>

<?php layout_foot(); ?>
