<?php include "connect.php"; ?>
<?php
// บันทึกการแก้ไขเบอร์โทรศัพท์และที่อยู่จัดส่งของสมาชิกที่เข้าสู่ระบบอยู่
require_login();
check_csrf();
$phone   = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");

if (!preg_match('/^0[0-9]{1,2}-?[0-9]{3}-?[0-9]{4}$/', $phone) || mb_strlen($address) < 10) {
    flash("error", "กรุณากรอกเบอร์โทรศัพท์และที่อยู่ให้ถูกต้อง");
} else {
    $sql = "
        UPDATE member
        SET phone = ?, address = ?
        WHERE member_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $phone);
    $stmt->bindParam(2, $address);
    $stmt->bindParam(3, $_SESSION["member_id"]);
    $stmt->execute();
    flash("ok", "บันทึกข้อมูลเรียบร้อย");
}
header("location: account.php");
?>
