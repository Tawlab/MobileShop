<?php
require '_layout.php';
layout_head('การซ่อมโทรศัพท์', 'tools', '#f97316', 'รับงานซ่อม บันทึกอาการเสีย ติดตามสถานะ ออกใบเสร็จซ่อม');
?>

<div class="section-label"><i class="fas fa-info-circle"></i> ระบบซ่อมคืออะไร?</div>
<div class="info-box">
    <p style="color:#374151; line-height:1.8;">
        ระบบการซ่อมช่วยจัดการงานรับซ่อมโทรศัพท์ตั้งแต่เริ่มรับแจ้ง ไปจนถึงส่งมอบให้ลูกค้า
        บันทึกอาการเสีย กำหนดค่าใช้จ่าย ติดตามสถานะงาน และออกใบเสร็จเมื่อซ่อมเสร็จ
    </p>
</div>

<div class="section-label"><i class="fas fa-list-check"></i> ฟังก์ชันหลักในระบบซ่อม</div>
<div class="info-box">
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-file-medical"></i></div>
        <div class="feature-text">
            <strong>รับงานซ่อมใหม่</strong>
            <span>บันทึกข้อมูลลูกค้า รุ่นโทรศัพท์ อาการเสีย และรายละเอียดงานซ่อม</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
        <div class="feature-text">
            <strong>อัปเดตสถานะงานซ่อม</strong>
            <span>เปลี่ยนสถานะงาน เช่น รอตรวจ → กำลังซ่อม → ซ่อมเสร็จแล้ว → รอรับเครื่อง</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="feature-text">
            <strong>ออกใบเสร็จค่าซ่อม</strong>
            <span>บันทึกค่าอะไหล่ ค่าแรง และออกใบเสร็จให้ลูกค้า</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
        <div class="feature-text">
            <strong>รับชำระผ่าน QR Code</strong>
            <span>รองรับการจ่ายค่าซ่อมผ่านระบบพร้อมเพย์หรือ QR Payment</span>
        </div>
    </div>
    <div class="feature-item">
        <div class="feature-icon"><i class="fas fa-print"></i></div>
        <div class="feature-text">
            <strong>พิมพ์ใบรับซ่อม / ใบเสร็จ</strong>
            <span>พิมพ์เอกสารมอบให้ลูกค้าทั้งใบรับฝาก และใบเสร็จค่าซ่อม</span>
        </div>
    </div>
</div>

<div class="section-label"><i class="fas fa-play-circle"></i> ขั้นตอนการรับงานซ่อม</div>

<div class="step-card">
    <div class="step-num">1</div>
    <div class="step-content">
        <h5>ไปที่เมนู "การซ่อม" → "รายการซ่อม"</h5>
        <p>คลิกเมนูการซ่อม แล้วเลือก <strong>รายการซ่อม</strong> เพื่อดูงานทั้งหมด</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">2</div>
    <div class="step-content">
        <h5>กดปุ่ม "รับงานซ่อมใหม่"</h5>
        <p>กดปุ่มเพื่อเปิดฟอร์มกรอกข้อมูลการรับซ่อม</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">3</div>
    <div class="step-content">
        <h5>กรอกข้อมูลลูกค้าและโทรศัพท์</h5>
        <p>ระบุชื่อลูกค้า เบอร์โทร รุ่นโทรศัพท์ IMEI และอาการเสียที่พบ</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">4</div>
    <div class="step-content">
        <h5>ประเมินค่าใช้จ่ายเบื้องต้น</h5>
        <p>กำหนดราคาค่าซ่อมประมาณการ เพื่อแจ้งลูกค้าก่อนเริ่มซ่อม</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">5</div>
    <div class="step-content">
        <h5>อัปเดตสถานะระหว่างซ่อม</h5>
        <p>เปลี่ยนสถานะงานเป็น "กำลังซ่อม" และเพิ่มรายละเอียดค่าใช้จ่ายจริง</p>
    </div>
</div>
<div class="step-card">
    <div class="step-num">6</div>
    <div class="step-content">
        <h5>ส่งมอบและรับชำระเงิน</h5>
        <p>เมื่อซ่อมเสร็จ เปลี่ยนสถานะ รับเงิน และออกใบเสร็จให้ลูกค้า</p>
    </div>
</div>

<div class="section-label"><i class="fas fa-tag"></i> สถานะงานซ่อม</div>
<div class="info-box">
    <table class="table table-borderless mb-0">
        <tbody>
            <tr><td width="160"><span class="badge bg-warning text-dark px-3 py-2 rounded-pill">รอตรวจสอบ</span></td><td class="text-muted">รับเครื่องมาแล้ว รอช่างตรวจอาการ</td></tr>
            <tr><td><span class="badge bg-primary px-3 py-2 rounded-pill">กำลังซ่อม</span></td><td class="text-muted">ช่างกำลังดำเนินการซ่อม</td></tr>
            <tr><td><span class="badge bg-success px-3 py-2 rounded-pill">ซ่อมเสร็จแล้ว</span></td><td class="text-muted">ซ่อมเรียบร้อย รอลูกค้ามารับ</td></tr>
            <tr><td><span class="badge bg-secondary px-3 py-2 rounded-pill">ส่งมอบแล้ว</span></td><td class="text-muted">ลูกค้ารับเครื่องคืนและชำระเงินแล้ว</td></tr>
            <tr><td><span class="badge bg-danger px-3 py-2 rounded-pill">ยกเลิก</span></td><td class="text-muted">งานซ่อมถูกยกเลิก</td></tr>
        </tbody>
    </table>
</div>

<?php layout_foot(); ?>
