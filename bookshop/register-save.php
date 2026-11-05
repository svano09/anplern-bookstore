<?php include "connect.php"; ?>
<?php
// ตรวจสอบข้อมูลการสมัครสมาชิก เข้ารหัสรหัสผ่าน และเข้าสู่ระบบให้อัตโนมัติ
check_csrf();
$first_name = trim($_POST["first_name"] ?? "");
$last_name  = trim($_POST["last_name"] ?? "");
$email      = trim($_POST["email"] ?? "");
$password   = $_POST["password"] ?? "";
$phone      = trim($_POST["phone"] ?? "");
$address    = trim($_POST["address"] ?? "");

$errors = [];
if ($first_name == "" || $last_name == "" || mb_strlen($first_name) > 50 || mb_strlen($last_name) > 50)
    $errors[] = "กรุณากรอกชื่อและนามสกุล (ไม่เกิน 50 ตัวอักษร)";
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}$/', $password))
    $errors[] = "รหัสผ่านต้องมีอย่างน้อย 8 ตัว มีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข";
if ($password !== ($_POST["confirm"] ?? ""))
    $errors[] = "ยืนยันรหัสผ่านไม่ตรงกัน";
if (!preg_match('/^0[0-9]{1,2}-?[0-9]{3}-?[0-9]{4}$/', $phone))
    $errors[] = "รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง";
if (mb_strlen($address) < 10)
    $errors[] = "กรุณากรอกที่อยู่จัดส่งให้ครบถ้วน";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
$stmt->bindParam(1, $email);
$stmt->execute();
if ($stmt->fetchColumn() > 0)
    $errors[] = "อีเมลนี้มีผู้ใช้แล้ว";

if ($errors) {
    $_SESSION["register_old"] = compact("first_name", "last_name", "email", "phone", "address");
    flash("error", implode(" / ", $errors));
    header("location: register.php");
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "
    INSERT INTO member (
        first_name,
        last_name,
        email,
        password_hash,
        phone,
        address,
        role
    ) VALUES (?, ?, ?, ?, ?, ?, 'customer')
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $first_name);
$stmt->bindParam(2, $last_name);
$stmt->bindParam(3, $email);
$stmt->bindParam(4, $hash);
$stmt->bindParam(5, $phone);
$stmt->bindParam(6, $address);
$stmt->execute();

session_regenerate_id(true);
$_SESSION["member_id"]  = $pdo->lastInsertId();
$_SESSION["first_name"] = $first_name;
$_SESSION["role"]       = "customer";
$_SESSION["login_time"] = date("Y-m-d H:i:s");
cart_merge_session($pdo);

flash("ok", "สมัครสมาชิกสำเร็จ ยินดีต้อนรับ คุณ" . $first_name);
header("location: index.php");
?>
