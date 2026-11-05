<?php include "connect.php"; ?>
<?php
// ตรวจสอบว่าอีเมลมีผู้ใช้งานในระบบแล้วหรือไม่ (ส่งกลับเป็นข้อความ okay / taken / invalid)
$email = trim($_GET["email"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "invalid";
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
    $stmt->bindParam(1, $email);
    $stmt->execute();
    echo $stmt->fetchColumn() > 0 ? "taken" : "okay";
}
?>
