<?php include "connect.php"; ?>
<?php
// ประมวลผลการชำระเงินและสร้างคำสั่งซื้อภายใน Database Transaction
require_login();
check_csrf();

$checkout = $_SESSION["checkout"] ?? null;
$cart     = cart_get($pdo);
$price    = price_cart($pdo, $cart, $_SESSION["coupon"] ?? "");
if (!$checkout || !$price["lines"]) {
    header("location: cart.php");
    exit;
}

// ตรวจสอบว่ายอดเงินตรงกับที่แสดงในหน้าชำระเงิน
if (abs($price["net_total"] - $checkout["amount"]) > 0.001) {
    flash("error", "ยอดชำระเปลี่ยนเป็น " . number_format($price["net_total"], 2) . " บาท กรุณาตรวจสอบอีกครั้ง");
    header("location: checkout.php");
    exit;
}

// ตรวจสอบความถูกต้องของบัตรด้วยอัลกอริทึม Luhn วันหมดอายุ และ CVV
$card_last4 = null;
if ($checkout["method"] == "card") {
    $card_no = preg_replace('/\D/', '', $_POST["card_no"] ?? "");
    $sum = 0;
    for ($i = 0; $i < strlen($card_no); $i++) {
        $d = (int)$card_no[strlen($card_no) - 1 - $i];
        if ($i % 2 == 1) {
            $d *= 2;
            if ($d > 9) $d -= 9;
        }
        $sum += $d;
    }
    $exp_ok = preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $_POST["expiry"] ?? "", $exp)
              && ("20" . $exp[2] . $exp[1]) >= date("Ym");

    $error = "";
    if (strlen($card_no) != 16 || $sum % 10 != 0)
        $error = "เลขบัตรไม่ถูกต้อง";
    else if (!$exp_ok)
        $error = "บัตรหมดอายุหรือวันหมดอายุไม่ถูกต้อง";
    else if (!preg_match('/^[0-9]{3}$/', $_POST["cvv"] ?? ""))
        $error = "CVV ไม่ถูกต้อง";
    else if ($card_no == "4000000000000002")
        $error = "ธนาคารปฏิเสธรายการ: วงเงินไม่เพียงพอ";
    if ($error != "") {
        flash("error", "ชำระเงินไม่สำเร็จ: " . $error);
        header("location: payment.php");
        exit;
    }
    $card_last4 = substr($card_no, -4);
}

try {
    $pdo->beginTransaction();

    // ล็อกแถวหนังสือ (FOR UPDATE) เพื่อป้องกันการสั่งซื้อเกินสต็อกกรณีทำรายการพร้อมกัน
    $ids = array_keys($cart);
    $marks = implode(", ", array_fill(0, count($ids), "?"));
    $sql_lock = "
        SELECT book_id, title, stock
        FROM book
        WHERE book_id IN ($marks)
        FOR UPDATE
    ";
    $stmt = $pdo->prepare($sql_lock);
    $stmt->execute($ids);
    while ($row = $stmt->fetch()) {
        if ($cart[$row["book_id"]] > $row["stock"])
            throw new Exception("\"" . $row["title"] . "\" เหลือในสต็อกเพียง " . $row["stock"] . " เล่ม");
    }

    $tier_id     = $price["tier"] ? $price["tier"]["promo_id"] : null;
    $coupon_code = $price["coupon"] ? $price["coupon"]["coupon_code"] : null;
    $sql_order = "
        INSERT INTO orders (
            member_id,
            order_date,
            ship_address,
            subtotal,
            item_discount,
            promo_id,
            tier_discount,
            coupon_code,
            coupon_discount,
            net_total,
            status
        ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ";
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([
        $_SESSION["member_id"],
        $checkout["address"],
        $price["subtotal"],
        $price["item_discount"],
        $tier_id,
        $price["tier_discount"],
        $coupon_code,
        $price["coupon_discount"],
        $price["net_total"],
    ]);
    $order_id = $pdo->lastInsertId();

    $sql_item = "
        INSERT INTO order_item (
            order_id,
            book_id,
            quantity,
            unit_price,
            promo_id,
            discount_amount
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    $item = $pdo->prepare($sql_item);
    $cut  = $pdo->prepare("UPDATE book SET stock = stock - ? WHERE book_id = ?");
    foreach ($price["lines"] as $line) {
        $item->execute([$order_id, $line["book_id"], $line["qty"], $line["price"], $line["promo_id"], $line["discount_amount"]]);
        $cut->execute([$line["qty"], $line["book_id"]]);
    }

    $ref_no = "PAY" . date("ymd") . str_pad($order_id, 4, "0", STR_PAD_LEFT);
    $sql_pay = "
        INSERT INTO payment (
            order_id,
            method,
            amount,
            status,
            ref_no,
            card_last4,
            paid_at
        ) VALUES (?, ?, ?, 'paid', ?, ?, NOW())
    ";
    $stmt = $pdo->prepare($sql_pay);
    $stmt->execute([$order_id, $checkout["method"], $price["net_total"], $ref_no, $card_last4]);

    $note = "ชำระเงินสำเร็จด้วย" . $method_th[$checkout["method"]] . ($card_last4 ? " ****" . $card_last4 : "")
          . " (เลขอ้างอิง " . $ref_no . ") รอร้านยืนยันคำสั่งซื้อ";
    $sql_log = "
        INSERT INTO order_status_log (order_id, status, note, changed_at)
        VALUES (?, 'pending', ?, NOW())
    ";
    $stmt = $pdo->prepare($sql_log);
    $stmt->execute([$order_id, $note]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    flash("error", "สั่งซื้อไม่สำเร็จ: " . ($e instanceof PDOException ? "เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่" : $e->getMessage()));
    header("location: cart.php");
    exit;
}

cart_clear($pdo);
unset($_SESSION["checkout"]);
flash("ok", "ชำระเงินสำเร็จ ขอบคุณที่สั่งซื้อ คำสั่งซื้อเลขที่ #" . $order_id);
header("location: order-detail.php?order_id=" . $order_id);
?>
