<?php include "connect.php"; ?>
<?php
// ตรวจสอบอีเมลและรหัสผ่านด้วย password_verify
check_csrf();
$email = trim($_POST["email"] ?? "");
$back  = safe_back($_POST["back"] ?? "index.php");

$sql = "
    SELECT member_id, first_name, password_hash, role
    FROM member
    WHERE email = ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $email);
$stmt->execute();
$row = $stmt->fetch();

if ($row && password_verify($_POST["password"] ?? "", $row["password_hash"])) {
    // สร้าง Session ID ใหม่เพื่อป้องกันการโจมตีแบบ Session Fixation
    session_regenerate_id(true);

    $_SESSION["member_id"]  = $row["member_id"];
    $_SESSION["first_name"] = $row["first_name"];
    $_SESSION["role"]       = $row["role"];
    $_SESSION["login_time"] = date("Y-m-d H:i:s");

    // โอนย้ายตะกร้าที่เลือกไว้ก่อนเข้าสู่ระบบไปรวมในฐานข้อมูล
    cart_merge_session($pdo);

    // บันทึกหรือลบ Cookie สำหรับจดจำอีเมลผู้ใช้งาน (อายุ 30 วัน)
    if (!empty($_POST["remember"]))
        setcookie("remember_email", $email, time() + 3600 * 24 * 30, "", "", false, true);
    else
        setcookie("remember_email", "", time() - 3600);

    flash("ok", "ยินดีต้อนรับ คุณ" . $row["first_name"]);
    if ($row["role"] == "admin" && $back == "index.php")
        $back = "admin.php";
    header("location: " . $back);
} else {
    header("location: login.php?msg=failed&back=" . urlencode($back));
}
?>
