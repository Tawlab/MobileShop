<?php
require '_layout.php';
layout_head('การรับเข้าสินค้า (PO)', 'truck-loading', '#14b8a6', 'สร้างใบสั่งซื้อ รับสินค้าจากผู้จำหน่าย อัปเดตสต็อคอัตโนมัติ');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบ Purchase Order (PO) คืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบการรับเข้าสินค้าช่วยจัดการการสั่งซื้อสินค้าจากผู้จำหน่าย (Supplier)
        ตั้งแต่สร้างใบสั่งซื้อ ไปจนถึงการรับสินค้าเข้าคลัง ระบบจะอัปเดตสต็อคให้อัตโนมัติ
        ทำให้ข้อมูลสินค้าสมบูรณ์ตลอดเวลา
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลักใน PO</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
        <div class="feature-text">
            <strong>สร้างใบสั่งซื้อ (PO)</strong>
            <span>เลือก Supplier และรายการสินค้าที่ต้องการสั่ง พร้อมระบุจำนวนและราคา</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-box-open"></i></div>
        <div class="feature-text">
            <strong>รับสินค้าตาม PO</strong>
            <span>ยืนยันการรับสินค้าหลังสินค้ามาถึง ระบบเพิ่มสต็อคให้ทันที</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-edit"></i></div>
        <div class="feature-text">
            <strong>แก้ไขใบ PO</strong>
            <span>แก้ไขรายละเอียดก่อนยืนยัน เช่น ราคา จำนวน หรือ Supplier</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-times-circle"></i></div>
        <div class="feature-text">
            <strong>ยกเลิก PO</strong>
            <span>ยกเลิกใบสั่งซื้อก่อนยืนยันรับสินค้าได้</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> ขั้นตอนการสั่งซื้อและรับสินค้า</div>

<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "การรับเข้าสินค้า"</h5>
        <p>คลิกเมนูด้านซ้ายเพื่อดูรายการ PO ทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "สร้างใบสั่งซื้อใหม่"</h5>
        <p>เปิดฟอร์มและเลือก Supplier ตามด้วยรายการสินค้าและจำนวนที่ต้องการ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>ยืนยันและบันทึก PO</h5>
        <p>ตรวจสอบรายการให้ถูกต้องแล้วกดยืนยัน PO จะอยู่ในสถานะ "รอรับสินค้า"</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>เมื่อสินค้ามาถึง กดรับสินค้า</h5>
        <p>กดปุ่ม "รับสินค้า" ระบุจำนวนที่ได้รับจริง ระบบจะเพิ่มสต็อคและสร้างบาร์โค้ดให้อัตโนมัติ</p>
    </div>
</div>

<div class="tip-box">
    <i class="fas fa-lightbulb"></i>
    <div>
        <strong>คำแนะนำ:</strong> ควรสร้าง PO ทุกครั้งที่สั่งสินค้า เพื่อใช้ตรวจสอบยอดสินค้า
        และเปรียบเทียบกับใบส่งของจากผู้จำหน่ายได้อย่างแม่นยำ
    </div>
</div>

<?php layout_foot(); ?>
