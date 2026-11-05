<?php include "connect.php"; ?>
<?php
$title = "คูปองส่วนลด";
include "header.php";

$code   = strtoupper(trim($_GET["code"] ?? ""));
$amount = $_GET["amount"] ?? "";

// ตรวจสอบเงื่อนไขคูปองที่กรอกเทียบกับยอดซื้อหลังหักโปรโมชั่น
$check = null;
if ($code != "" && $amount != "")
    $check = check_coupon($pdo, $code, $amount);

// ดึงรายการคูปองที่กำลังเปิดใช้งานและยังมีสิทธิ์คงเหลือ
$sql = "
    SELECT
        c.coupon_code,
        c.description,
        c.discount_type,
        c.discount_value,
        c.max_discount,
        c.min_spend,
        c.end_date,
        c.usage_limit - COUNT(o.order_id) AS remaining
    FROM coupon c
    LEFT JOIN orders o
        ON o.coupon_code = c.coupon_code
       AND o.status <> 'cancelled'
    WHERE CURDATE() BETWEEN c.start_date AND c.end_date
    GROUP BY
        c.coupon_code,
        c.description,
        c.discount_type,
        c.discount_value,
        c.max_discount,
        c.min_spend,
        c.end_date,
        c.usage_limit
    HAVING remaining > 0
    ORDER BY c.end_date
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
?>
<p class="lead">กรอกรหัสคูปองในหน้าตะกร้าเพื่อหักส่วนลดเงินสดหรือเปอร์เซ็นต์ ระบบตรวจสอบวันหมดอายุ ยอดซื้อขั้นต่ำ และสิทธิ์คงเหลือ</p>

<h2>คูปองที่ใช้ได้ตอนนี้</h2>
<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>รหัสคูปอง</th><th>รายละเอียด</th><th class="num">ส่วนลด</th><th class="num">ยอดขั้นต่ำ</th><th class="num">สิทธิ์คงเหลือ</th><th>ใช้ได้ถึง</th></tr>
  </thead>
  <tbody>
<?php while ($row = $stmt->fetch()) : ?>
    <tr>
      <td><b><?=$row["coupon_code"]?></b></td>
      <td><?=htmlspecialchars($row["description"])?></td>
      <td class="num"><?=$row["discount_type"] == "percent" ? ($row["discount_value"] + 0) . "%" . ($row["max_discount"] ? " (สูงสุด " . number_format($row["max_discount"]) . ")" : "") : number_format($row["discount_value"]) . " บาท"?></td>
      <td class="num"><?=number_format($row["min_spend"], 2)?></td>
      <td class="num"><?=$row["remaining"]?></td>
      <td><?=$row["end_date"]?></td>
    </tr>
<?php endwhile; ?>
  </tbody>
</table>
</div>

<h2>ตรวจสอบคูปอง</h2>
<form class="filter">
  <label>รหัสคูปอง
    <input type="text" name="code" value="<?=htmlspecialchars($code)?>" required
           pattern="[A-Za-z0-9]{3,20}" title="ตัวอักษรภาษาอังกฤษหรือตัวเลข 3-20 ตัว" placeholder="เช่น BOOK15">
  </label>
  <label>ยอดซื้อหลังหักโปรโมชั่น (บาท)
    <input type="number" name="amount" min="1" step="0.01" value="<?=htmlspecialchars($amount)?>" required>
  </label>
  <input type="submit" value="ตรวจสอบคูปอง">
</form>

<?php if ($code != "" && $amount != "") : ?>
<?php   if (!$check) : ?>
<p class="msg error">ไม่พบคูปองรหัส <b><?=htmlspecialchars($code)?></b></p>
<?php   elseif ($check["check_result"] == "ใช้คูปองได้") : ?>
<p class="msg ok">
  <b><?=$check["coupon_code"]?></b> &ndash; <?=$check["check_result"]?> (<?=htmlspecialchars($check["description"])?>)<br>
  ส่วนลด <b><?=number_format($check["discount"], 2)?></b> บาท &rarr; ยอดที่ต้องชำระ <b><?=number_format($amount - $check["discount"], 2)?></b> บาท
  &middot; สิทธิ์คงเหลือ <?=$check["remaining"]?> สิทธิ์ &middot; ใช้ได้ถึง <?=$check["end_date"]?>
</p>
<?php   else : ?>
<p class="msg error">
  <b><?=$check["coupon_code"]?></b> &ndash; <?=$check["check_result"]?>
  (ยอดขั้นต่ำ <?=number_format($check["min_spend"], 2)?> บาท, ใช้ได้ <?=$check["start_date"]?> ถึง <?=$check["end_date"]?>, สิทธิ์คงเหลือ <?=max(0, $check["remaining"])?>)
</p>
<?php   endif; ?>
<?php endif; ?>

<?php if (is_admin()) : ?>
<details class="sql">
  <summary>ดูคำสั่ง SQL</summary>
  <pre><?=htmlspecialchars(COUPON_CHECK_SQL)?></pre>
</details>
<?php endif; ?>
<?php include "footer.php"; ?>
