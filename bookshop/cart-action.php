<?php include "connect.php"; ?>
<?php
// จัดการรายการในตะกร้าสินค้า (เพิ่ม / ปรับจำนวน / ลบ / ใช้หรือยกเลิกคูปอง)
check_csrf();

$action  = $_POST["action"] ?? "";
$book_id = (int)($_POST["book_id"] ?? 0);
$qty     = (int)($_POST["qty"] ?? 1);
$cart    = cart_get($pdo);
$ok      = true;
$message = "";

if ($action == "add" || $action == "update") {
    $stmt = $pdo->prepare("SELECT title, stock FROM book WHERE book_id = ?");
    $stmt->bindParam(1, $book_id);
    $stmt->execute();
    $book = $stmt->fetch();

    $new_qty = $action == "add" ? ($cart[$book_id] ?? 0) + $qty : $qty;
    if (!$book) {
        $ok = false;
        $message = "ไม่พบหนังสือที่เลือก";
    } else if ($qty < 1 || $qty > 99) {
        $ok = false;
        $message = "จำนวนต้องอยู่ระหว่าง 1 - 99 เล่ม";
    } else if ($book["stock"] < 1) {
        $ok = false;
        $message = "\"" . $book["title"] . "\" สินค้าหมด";
    } else if ($new_qty > $book["stock"]) {
        $ok = false;
        $new_qty = $book["stock"];
        cart_set($pdo, $book_id, $new_qty);
        $message = "\"" . $book["title"] . "\" เหลือเพียง " . $book["stock"] . " เล่ม ตะกร้ามีครบจำนวนที่ซื้อได้แล้ว";
    } else {
        cart_set($pdo, $book_id, $new_qty);
        $message = $action == "add"
            ? "เพิ่ม \"" . $book["title"] . "\" ลงตะกร้าแล้ว (ในตะกร้า " . $new_qty . " เล่ม)"
            : "เปลี่ยนจำนวน \"" . $book["title"] . "\" เป็น " . $new_qty . " เล่ม";
    }

} else if ($action == "remove") {
    cart_set($pdo, $book_id, 0);
    $message = "นำหนังสือออกจากตะกร้าแล้ว";

} else if ($action == "coupon") {
    $code = strtoupper(trim($_POST["code"] ?? ""));
    if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
        $ok = false;
        $message = "รหัสคูปองต้องเป็นตัวอักษรภาษาอังกฤษหรือตัวเลข 3-20 ตัว";
    } else {
        $price = price_cart($pdo, $cart, $code);
        if ($price["coupon"]) {
            $_SESSION["coupon"] = $code;
            $message = "ใช้คูปอง " . $code . " ลด " . number_format($price["coupon_discount"], 2) . " บาท";
        } else {
            $ok = false;
            $message = $price["coupon_error"];
        }
    }

} else if ($action == "remove_coupon") {
    unset($_SESSION["coupon"]);
    $message = "ยกเลิกการใช้คูปองแล้ว";
}

if (!empty($_POST["ajax"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["ok" => $ok, "count" => cart_count($pdo), "message" => $message]);
    exit;
}
flash($ok ? "ok" : "error", $message);
header("location: " . safe_back($_POST["back"] ?? "cart.php"));
?>
