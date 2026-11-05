<?php include "connect.php"; ?>
<?php
require_login();

$cart  = cart_get($pdo);
$price = price_cart($pdo, $cart, $_SESSION["coupon"] ?? "");
if (!$price["lines"]) {
    flash("error", "ยังไม่มีหนังสือในตะกร้า");
    header("location: cart.php");
    exit;
}
foreach ($price["lines"] as $line) {
    if ($line["qty"] > $line["stock"]) {
        flash("error", "\"" . $line["title"] . "\" เหลือในสต็อกเพียง " . $line["stock"] . " เล่ม กรุณาแก้ไขจำนวน");
        header("location: cart.php");
        exit;
    }
}

$title = "ยืนยันคำสั่งซื้อ";
include "header.php";

$sql = "
    SELECT first_name, last_name, phone, address
    FROM member
    WHERE member_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $_SESSION["member_id"]);
$stmt->execute();
$me = $stmt->fetch();
$address = $_SESSION["checkout"]["address"] ?? $me["address"];
$method  = $_SESSION["checkout"]["method"] ?? "card";
?>
<script>
  // สลับการแสดงคำอธิบายตามวิธีชำระเงินที่เลือก
  function showMethod() {
    var card = document.getElementById("method-card").checked;
    document.getElementById("info-card").style.display = card ? "block" : "none";
    document.getElementById("info-promptpay").style.display = card ? "none" : "block";
  }
</script>

<ol class="steps">
  <li class="done">ตะกร้าสินค้า</li>
  <li class="now">ยืนยันคำสั่งซื้อ</li>
  <li>ชำระเงิน</li>
  <li>ติดตามพัสดุ</li>
</ol>

<form action="payment.php" method="post" class="checkout">
  <?=csrf_field()?>
  <section class="panel">
    <h2>1. ที่อยู่จัดส่ง</h2>
    <p>ผู้รับ: <b><?=htmlspecialchars($me["first_name"] . " " . $me["last_name"])?></b> โทร <?=htmlspecialchars($me["phone"])?></p>
    <textarea name="address" rows="3" required minlength="10" maxlength="500" aria-label="ที่อยู่จัดส่ง"><?=htmlspecialchars($address)?></textarea>

    <h2>2. วิธีชำระเงิน</h2>
    <div class="pay-methods">
      <label class="pay-option">
        <input type="radio" id="method-card" name="method" value="card" onchange="showMethod()"<?=$method == "card" ? " checked" : ""?>>
        บัตรเครดิต/เดบิต
      </label>
      <label class="pay-option">
        <input type="radio" id="method-promptpay" name="method" value="promptpay" onchange="showMethod()"<?=$method == "promptpay" ? " checked" : ""?>>
        พร้อมเพย์ (QR)
      </label>
    </div>
    <div id="info-card" class="hint-box"<?=$method == "card" ? "" : ' style="display:none"'?>>
      กรอกเลขบัตรในหน้าถัดไป (ระบบจำลอง ไม่มีการตัดเงินจริง) ทดลองด้วยบัตร <code>4242 4242 4242 4242</code>
    </div>
    <div id="info-promptpay" class="hint-box"<?=$method == "promptpay" ? "" : ' style="display:none"'?>>
      หน้าถัดไปจะแสดง QR Code พร้อมเพย์สำหรับสแกนจ่าย (ระบบจำลอง)
    </div>
  </section>

  <section class="panel summary">
    <h2>3. สรุปรายการ (<?=array_sum($cart)?> เล่ม)</h2>
    <ul class="mini-list">
<?php foreach ($price["lines"] as $line) : ?>
      <li><span><?=htmlspecialchars($line["title"])?> &times; <?=$line["qty"]?></span><span><?=number_format($line["line_total"], 2)?></span></li>
<?php endforeach; ?>
    </ul>
    <dl>
      <dt>ราคารวม</dt><dd><?=number_format($price["subtotal"], 2)?></dd>
      <dt>ส่วนลดระดับสินค้า</dt><dd>-<?=number_format($price["item_discount"], 2)?></dd>
      <dt>ส่วนลดซื้อครบยอด<?=$price["tier"] ? " (" . ($price["tier"]["discount_percent"] + 0) . "%)" : ""?></dt><dd>-<?=number_format($price["tier_discount"], 2)?></dd>
      <dt>คูปอง<?=$price["coupon"] ? " " . htmlspecialchars($_SESSION["coupon"]) : ""?></dt><dd>-<?=number_format($price["coupon_discount"], 2)?></dd>
      <dt class="total">ยอดที่ต้องชำระ</dt><dd class="total"><?=number_format($price["net_total"], 2)?> ฿</dd>
    </dl>
<?php if ($price["coupon_error"]) : ?>
    <p class="warn-text">คูปอง <?=htmlspecialchars($_SESSION["coupon"])?> ใช้ไม่ได้: <?=htmlspecialchars($price["coupon_error"])?> (<a href="cart.php">แก้ไข</a>)</p>
<?php endif; ?>
    <input type="submit" class="block" value="ไปหน้าชำระเงิน &rarr;">
    <p><a href="cart.php">&larr; กลับไปแก้ไขตะกร้า</a></p>
  </section>
</form>
<?php include "footer.php"; ?>
