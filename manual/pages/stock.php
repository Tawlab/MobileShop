<?php
require '_layout.php';
layout_head('สต็อคสินค้า', 'boxes', '#3b82f6', 'ตรวจสอบและจัดการคลังสินค้า บาร์โค้ด และจำนวนคงเหลือ');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบสต็อคสินค้าคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบสต็อคสินค้าช่วยบันทึกและติดตามจำนวนสินค้าในคลัง แต่ละชิ้นมีบาร์โค้ดประจำตัว
        ทำให้ตรวจสอบจำนวนคงเหลือได้ง่าย และลดความผิดพลาดในการนับสินค้า
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลักในระบบสต็อค</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-plus-circle"></i></div>
        <div class="feature-text">
            <strong>เพิ่มสินค้าในสต็อค</strong>
            <span>บันทึกสินค้าที่ได้รับเข้ามา โดยเลือกรุ่น ราคาทุน และจำนวน</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-barcode"></i></div>
        <div class="feature-text">
            <strong>สร้างและพิมพ์บาร์โค้ด</strong>
            <span>ทุกชิ้นสินค้ามีบาร์โค้ดเฉพาะตัว สามารถพิมพ์ติดฉลากได้ทันที</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-search"></i></div>
        <div class="feature-text">
            <strong>ค้นหาสินค้าในสต็อค</strong>
            <span>ค้นหาด้วยชื่อรุ่น ยี่ห้อ หรือสแกนบาร์โค้ดเพื่อหาสินค้า</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขข้อมูลในสต็อค</strong>
            <span>ปรับราคาขาย แก้ไขข้อมูลให้ถูกต้อง</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="feature-text">
            <strong>นำสินค้าออกจากสต็อค</strong>
            <span>ลบรายการสินค้าที่เสียหายหรือสูญหาย พร้อมระบุเหตุผล</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> วิธีเพิ่มสินค้าลงสต็อค</div>

<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "สต็อคสินค้า"</h5>
        <p>คลิกเมนู <strong>สต็อคสินค้า</strong> จะแสดงรายการสินค้าทั้งหมดในคลัง</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "เพิ่มสินค้าในสต็อค"</h5>
        <p>คลิกปุ่มเพื่อเปิดฟอร์มเพิ่มสินค้าใหม่</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>เลือกรุ่นสินค้าและกรอกข้อมูล</h5>
        <p>เลือกรุ่นจากรายการสินค้าที่มีในระบบ ระบุสี ความจุ ราคาทุน และราคาขาย</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>ระบบสร้างบาร์โค้ดอัตโนมัติ</h5>
        <p>ระบบจะสร้างบาร์โค้ดให้แต่ละชิ้นโดยอัตโนมัติ สามารถพิมพ์ได้ทันที</p>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>คำแนะนำ:</strong> ควรพิมพ์บาร์โค้ดและติดบนตัวสินค้าทันทีที่รับเข้าสต็อค
        เพื่อให้การขายและตรวจนับสต็อคเป็นไปอย่างรวดเร็วและถูกต้อง
    </div>
</div>

<?php layout_foot(); ?>
