<?php
$form_valid = true;
$errors = [];

// ฟังก์ชันตรวจรูปแบบ
function isThai($text) {
    return preg_match('/^[\x{0E00}-\x{0E7F}\s]+$/u', $text);
}

function isEnglish($text) {
    return preg_match('/^[a-zA-Z\s]+$/', $text);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone($phone) {
    return preg_match('/^0[689]\d{8}$/', $phone);
}

function isValidNationalID($id) {
    if (!preg_match('/^[0-9]{13}$/', $id)) return false;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += intval($id[$i]) * (13 - $i);
    }
    return (11 - ($sum % 11)) % 10 == intval($id[12]);
}

// ตรวจแต่ละฟิลด์
if (empty($_POST['employee_id']) || !preg_match('/^[0-9]{6}$/', $_POST['employee_id'])) {
    $form_valid = false;
    $errors[] = "รหัสพนักงานต้องมี 6 หลักตัวเลข";
}

if (empty($_POST['username'])) {
    $form_valid = false;
    $errors[] = "กรุณากรอก Username";
}

if (isset($_POST['password']) && $_POST['password'] !== '') {
    if (strlen($_POST['password']) < 6) {
        $form_valid = false;
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    }
}

if (empty($_POST['national_id']) || !isValidNationalID($_POST['national_id'])) {
    $form_valid = false;
    $errors[] = "เลขบัตรประชาชนไม่ถูกต้อง";
}

if (empty($_POST['fname_th']) || !isThai($_POST['fname_th'])) {
    $form_valid = false;
    $errors[] = "ชื่อ (ไทย) ต้องเป็นภาษาไทยเท่านั้น";
}

if (empty($_POST['lname_th']) || !isThai($_POST['lname_th'])) {
    $form_valid = false;
    $errors[] = "นามสกุล (ไทย) ต้องเป็นภาษาไทยเท่านั้น";
}

if (!empty($_POST['fname_en']) && !isEnglish($_POST['fname_en'])) {
    $form_valid = false;
    $errors[] = "ชื่อ (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น";
}

if (!empty($_POST['lname_en']) && !isEnglish($_POST['lname_en'])) {
    $form_valid = false;
    $errors[] = "นามสกุล (อังกฤษ) ต้องเป็นภาษาอังกฤษเท่านั้น";
}

if (empty($_POST['email']) || !isValidEmail($_POST['email'])) {
    $form_valid = false;
    $errors[] = "อีเมลไม่ถูกต้อง";
}

if (empty($_POST['phone_no']) || !isValidPhone($_POST['phone_no'])) {
    $form_valid = false;
    $errors[] = "เบอร์โทรไม่ถูกต้อง";
}

if (!empty($_POST['zip_code']) && !preg_match('/^\d{5}$/', $_POST['zip_code'])) {
    $form_valid = false;
    $errors[] = "รหัสไปรษณีย์ต้องมี 5 หลัก";
}

if (empty($_POST['departments_id']) || !is_numeric($_POST['departments_id'])) {
    $form_valid = false;
    $errors[] = "กรุณาเลือกแผนก";
}

if (empty($_POST['prenames_id']) || !is_numeric($_POST['prenames_id'])) {
    $form_valid = false;
    $errors[] = "กรุณาเลือกคำนำหน้า";
}

if (empty($_POST['religions_id']) || !is_numeric($_POST['religions_id'])) {
    $form_valid = false;
    $errors[] = "กรุณาเลือกศาสนา";
}

if (empty($_POST['start_date'])) {
    $form_valid = false;
    $errors[] = "กรุณาระบุวันที่เริ่มงาน";
}
