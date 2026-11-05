<?php include "connect.php"; ?>
<?php
// บันทึกข้อมูลคูปอง (สร้างใหม่/แก้ไข) และลบคูปองที่ยังไม่เคยถูกใช้ในคำสั่งซื้อ
require_admin();
check_csrf();
$code = strtoupper(trim($_POST["coupon_code"] ?? ""));

if (($_POST["action"] ?? "") == "delete") {
    try {
        $stmt = $pdo->prepare("DELETE FROM coupon WHERE coupon_code = ?");
        $stmt->bindParam(1, $code);
        $stmt->execute();
        flash("ok", "ลบคูปอง " . $code . " แล้ว");
    } catch (PDOException $e) {
        flash("error", "ลบไม่ได้ เพราะคูปอง " . $code . " ถูกใช้ในคำสั่งซื้อแล้ว");
    }
    header("location: coupons.php");
    exit;
}

$is_new = !empty($_POST["is_new"]);
$type   = $_POST["discount_type"] ?? "";
$value  = $_POST["discount_value"] ?? "";
$max    = $type == "percent" && ($_POST["max_discount"] ?? "") !== "" ? $_POST["max_discount"] : null;
$data   = [
    trim($_POST["description"] ?? ""),
    $type,
    $value,
    $max,
    $_POST["min_spend"] ?? "",
    $_POST["usage_limit"] ?? "",
    $_POST["start_date"] ?? "",
    $_POST["end_date"] ?? "",
];

$errors = [];
if (!preg_match('/^[A-Z0-9]{3,20}$/', $code))
    $errors[] = "รหัสคูปองต้องเป็นตัวพิมพ์ใหญ่ภาษาอังกฤษหรือตัวเลข 3-20 ตัว";
if ($data[0] == "" || mb_strlen($data[0]) > 150)
    $errors[] = "กรุณากรอกรายละเอียดคูปอง";
if (!in_array($type, ["fixed", "percent"]) || !is_numeric($value) || $value < 1 || ($type == "percent" && $value > 90))
    $errors[] = "ส่วนลดไม่ถูกต้อง (แบบเปอร์เซ็นต์ 1-90%)";
if ($max !== null && (!is_numeric($max) || $max < 1))
    $errors[] = "เพดานส่วนลดไม่ถูกต้อง";
if (!is_numeric($data[4]) || $data[4] < 0)
    $errors[] = "ยอดซื้อขั้นต่ำไม่ถูกต้อง";
if (!ctype_digit((string)$data[5]) || $data[5] < 1)
    $errors[] = "จำนวนสิทธิ์ต้องเป็นจำนวนเต็มตั้งแต่ 1 ขึ้นไป";
if (!strtotime($data[6]) || !strtotime($data[7]) || $data[7] < $data[6])
    $errors[] = "ช่วงวันที่ไม่ถูกต้อง";

if ($is_new) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupon WHERE coupon_code = ?");
    $stmt->bindParam(1, $code);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0)
        $errors[] = "มีคูปองรหัส " . $code . " อยู่แล้ว";
}
if ($errors) {
    flash("error", implode(" / ", $errors));
    header("location: coupon-form.php" . ($is_new ? "" : "?code=" . urlencode($code)));
    exit;
}

if ($is_new) {
    $sql = "
        INSERT INTO coupon (
            description,
            discount_type,
            discount_value,
            max_discount,
            min_spend,
            usage_limit,
            start_date,
            end_date,
            coupon_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql);
    flash("ok", "สร้างคูปอง " . $code . " แล้ว");
} else {
    $sql = "
        UPDATE coupon
        SET
            description    = ?,
            discount_type  = ?,
            discount_value = ?,
            max_discount   = ?,
            min_spend      = ?,
            usage_limit    = ?,
            start_date     = ?,
            end_date       = ?
        WHERE coupon_code = ?
    ";
    $stmt = $pdo->prepare($sql);
    flash("ok", "แก้ไขคูปอง " . $code . " แล้ว");
}
$data[] = $code;
$stmt->execute($data);
header("location: coupons.php");
?>
