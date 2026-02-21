<?php
require '_layout.php';
layout_head('รายการสินค้า', 'mobile', '#3b82f6', 'จัดการข้อมูลโทรศัพท์ รุ่น ราคา และรายละเอียดสินค้าทั้งหมด');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> หน้ารายการสินค้าคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        หน้ารายการสินค้าเป็นฐานข้อมูลหลักของสินค้าในร้าน ใช้บันทึกรุ่นโทรศัพท์ ยี่ห้อ
        สเปก และราคาขายแนะนำ เมื่อมีสินค้าเข้าสต็อค ระบบจะดึงข้อมูลจากหน้านี้มาใช้
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลัก</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus"></i></div>
        <div class="feature-text">
            <strong>เพิ่มสินค้าใหม่</strong>
            <span>บันทึกรุ่นสินค้า ยี่ห้อ ประเภท สเปก และราคาขาย</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลสินค้า</strong>
            <span>อัปเดตข้อมูล ราคา หรือรายละเอียดของสินค้าที่มีอยู่</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-eye"></i></div>
        <div class="feature-text">
            <strong>ดูรายละเอียดสินค้า</strong>
            <span>ดูข้อมูลสินค้าทั้งหมดรวมถึงประวัติการขายของสินค้านั้น</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="feature-text">
            <strong>ลบสินค้า</strong>
            <span>ลบสินค้าที่ไม่ได้จำหน่ายแล้วออกจากระบบ</span>
        </div>
    </div>
</div>

<div class="note-box">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>ข้อควรทราบ:</strong> "รายการสินค้า" คือข้อมูลรุ่นสินค้า (Model) ซึ่งแตกต่างจาก "สต็อค"
        ที่เป็นตัวเครื่องจริงๆ แต่ละรุ่นสามารถมีสินค้าในสต็อคได้หลายชิ้น
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มสินค้าใหม่</div>
<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "สินค้า" → "รายการสินค้า"</h5>
        <p>คลิกที่เมนูสินค้าเพื่อดูรายการทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มสินค้าใหม่"</h5>
        <p>เปิดฟอร์มกรอกข้อมูลสินค้า</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกข้อมูลให้ครบถ้วน</h5>
        <p>ระบุชื่อรุ่น เลือกยี่ห้อ ประเภทสินค้า และราคาขายแนะนำ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>กดบันทึก</h5>
        <p>บันทึกข้อมูล สินค้าจะพร้อมใช้งานสำหรับการเพิ่มสต็อคและขายทันที</p>
    </div>
</div>

<?php layout_foot(); ?>
