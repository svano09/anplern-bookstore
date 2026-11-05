<?php include "connect.php"; ?>
<?php
// อัปเดตสถานะคำสั่งซื้อ บันทึกประวัติลง order_status_log และคืนสต็อกกรณีถูกยกเลิก
require_admin();
check_csrf();

$order_id    = (int)($_POST["order_id"] ?? 0);
$status      = $_POST["status"] ?? "";
$carrier     = $_POST["carrier"] ?? "";
$tracking_no = strtoupper(trim($_POST["tracking_no"] ?? ""));
$note        = trim($_POST["note"] ?? "");

$stmt = $pdo->prepare("SELECT status, carrier, tracking_no FROM orders WHERE order_id = ?");
$stmt->bindParam(1, $order_id);
$stmt->execute();
$order = $stmt->fetch();

$error = "";
if (!$order)
    $error = "ไม่พบคำสั่งซื้อ";
else if (!in_array($status, $next_status[$order["status"]] ?? []))
    $error = "เปลี่ยนจาก \"" . $status_th[$order["status"]] . "\" เป็นสถานะที่เลือกไม่ได้";
else if ($status == "shipped" && (!in_array($carrier, $carriers) || !preg_match('/^[A-Z0-9]{8,20}$/', $tracking_no)))
    $error = "กรุณาเลือกบริษัทขนส่งและกรอกหมายเลขพัสดุ (ตัวพิมพ์ใหญ่/ตัวเลข 8-20 ตัว)";

if ($error) {
    flash("error", $error);
    header("location: order-detail.php?order_id=" . $order_id);
    exit;
}

if ($status != "shipped") {
    $carrier = $order["carrier"];
    $tracking_no = $order["tracking_no"];
}
$default_note = [
    "packing"   => "ร้านยืนยันคำสั่งซื้อและกำลังแพ็กสินค้า",
    "shipped"   => "ส่งมอบพัสดุให้ " . $carrier . " เลขพัสดุ " . $tracking_no,
    "delivered" => "ผู้รับได้รับพัสดุเรียบร้อย",
    "cancelled" => "ร้านยกเลิกคำสั่งซื้อ คืนสต็อกและคืนเงินแล้ว",
];
$note = $default_note[$status] . ($note != "" ? " (" . $note . ")" : "");

$pdo->beginTransaction();

$sql_update = "
    UPDATE orders
    SET status = ?, carrier = ?, tracking_no = ?
    WHERE order_id = ?
";
$stmt = $pdo->prepare($sql_update);
$stmt->execute([$status, $carrier, $tracking_no, $order_id]);

$sql_log = "
    INSERT INTO order_status_log (order_id, status, note, changed_at, changed_by)
    VALUES (?, ?, ?, NOW(), ?)
";
$stmt = $pdo->prepare($sql_log);
$stmt->execute([$order_id, $status, $note, $_SESSION["member_id"]]);

if ($status == "cancelled") {
    $sql_restock = "
        UPDATE book b
        JOIN order_item oi ON oi.book_id = b.book_id
        SET b.stock = b.stock + oi.quantity
        WHERE oi.order_id = ?
    ";
    $stmt = $pdo->prepare($sql_restock);
    $stmt->execute([$order_id]);

    $stmt = $pdo->prepare("UPDATE payment SET status = 'refunded' WHERE order_id = ?");
    $stmt->execute([$order_id]);
}

$pdo->commit();

flash("ok", "อัปเดตคำสั่งซื้อ #" . $order_id . " เป็น \"" . $status_th[$status] . "\" แล้ว");
header("location: order-detail.php?order_id=" . $order_id);
?>
