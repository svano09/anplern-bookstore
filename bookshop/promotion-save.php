<?php include "connect.php"; ?>
<?php
// บันทึกข้อมูลโปรโมชั่น (เพิ่ม/แก้ไข) หรือสลับสถานะการเปิด-ปิดใช้งาน
require_admin();
check_csrf();
$promo_id = (int)($_POST["promo_id"] ?? 0);

if (($_POST["action"] ?? "") == "toggle") {
    $stmt = $pdo->prepare("UPDATE promotion SET is_active = 1 - is_active WHERE promo_id = ?");
    $stmt->bindParam(1, $promo_id);
    $stmt->execute();
    flash("ok", "เปลี่ยนสถานะการใช้งานโปรโมชั่นแล้ว");
    header("location: promotions.php");
    exit;
}

// เก็บเฉพาะค่าเงื่อนไขที่ตรงกับประเภทโปรโมชั่นที่เลือก ส่วนประเภทอื่นกำหนดเป็น NULL
$type = $_POST["promo_type"] ?? "";
$data = [
    "promo_name"        => trim($_POST["promo_name"] ?? ""),
    "promo_type"        => $type,
    "discount_percent"  => $_POST["discount_percent"] ?? "",
    "min_spend"         => $type == "tier" ? ($_POST["min_spend"] ?? "") : null,
    "category_id"       => $type == "seasonal" && ($_POST["category_id"] ?? "") !== "" ? (int)$_POST["category_id"] : null,
    "min_days_in_stock" => $type == "clearance" ? ($_POST["min_days_in_stock"] ?? "") : null,
    "max_stock"         => $type == "clearance" ? ($_POST["max_stock"] ?? "") : null,
    "start_date"        => $_POST["start_date"] ?? "",
    "end_date"          => $_POST["end_date"] ?? "",
    "is_active"         => !empty($_POST["is_active"]) ? 1 : 0,
];

$errors = [];
if ($data["promo_name"] == "" || mb_strlen($data["promo_name"]) > 150)
    $errors[] = "กรุณากรอกชื่อโปรโมชั่น";
if (!in_array($type, ["tier", "seasonal", "clearance"]))
    $errors[] = "ประเภทโปรโมชั่นไม่ถูกต้อง";
if (!is_numeric($data["discount_percent"]) || $data["discount_percent"] < 1 || $data["discount_percent"] > 90)
    $errors[] = "ส่วนลดต้องอยู่ระหว่าง 1 - 90%";
if ($type == "tier" && (!is_numeric($data["min_spend"]) || $data["min_spend"] < 1))
    $errors[] = "กรุณากรอกยอดซื้อขั้นต่ำ";
if ($type == "clearance" && (!ctype_digit((string)$data["min_days_in_stock"]) || !ctype_digit((string)$data["max_stock"])))
    $errors[] = "กรุณากรอกจำนวนวันในคลังและจำนวนคงเหลือสูงสุด";
if (!strtotime($data["start_date"]) || !strtotime($data["end_date"]) || $data["end_date"] < $data["start_date"])
    $errors[] = "ช่วงวันที่ไม่ถูกต้อง (วันสิ้นสุดต้องไม่ก่อนวันเริ่ม)";

if ($errors) {
    flash("error", implode(" / ", $errors));
    header("location: promotion-form.php" . ($promo_id ? "?promo_id=" . $promo_id : ""));
    exit;
}

if ($promo_id) {
    $sql = "
        UPDATE promotion
        SET
            promo_name        = ?,
            promo_type        = ?,
            discount_percent  = ?,
            min_spend         = ?,
            category_id       = ?,
            min_days_in_stock = ?,
            max_stock         = ?,
            start_date        = ?,
            end_date          = ?,
            is_active         = ?
        WHERE promo_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(array_values($data), [$promo_id]));
    flash("ok", "แก้ไขโปรโมชั่น \"" . $data["promo_name"] . "\" แล้ว");
} else {
    $sql = "
        INSERT INTO promotion (
            promo_name,
            promo_type,
            discount_percent,
            min_spend,
            category_id,
            min_days_in_stock,
            max_stock,
            start_date,
            end_date,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    flash("ok", "สร้างโปรโมชั่น \"" . $data["promo_name"] . "\" แล้ว");
}
header("location: promotions.php");
?>
