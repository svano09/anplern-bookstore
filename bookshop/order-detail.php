<?php include "connect.php"; ?>
<?php
require_login();
$order_id = (int)($_GET["order_id"] ?? 0);
$title = "รายละเอียดคำสั่งซื้อ #" . $order_id;
include "header.php";

// ดึงข้อมูลหลักของคำสั่งซื้อ ข้อมูลลูกค้า โปรโมชั่นขั้นบันได และรายละเอียดการชำระเงิน
$sql_order = "
    SELECT
        o.*,
        CONCAT(m.first_name, ' ', m.last_name) AS customer,
        m.email,
        m.phone,
        p.promo_name AS tier_name,
        pay.method,
        pay.status AS pay_status,
        pay.ref_no,
        pay.card_last4,
        pay.paid_at
    FROM orders o
    JOIN member m ON m.member_id = o.member_id
    LEFT JOIN promotion p ON p.promo_id = o.promo_id
    LEFT JOIN payment pay ON pay.order_id = o.order_id
    WHERE o.order_id = ?
      AND (o.member_id = ? OR ? = 1)
";
$is_admin = is_admin() ? 1 : 0;
$stmt = $pdo->prepare($sql_order);
$stmt->bindParam(1, $order_id);
$stmt->bindParam(2, $_SESSION["member_id"]);
$stmt->bindParam(3, $is_admin);
$stmt->execute();
$order = $stmt->fetch();

if (!$order) {
    echo '<p class="msg error">ไม่พบคำสั่งซื้อนี้</p>';
    include "footer.php";
    exit;
}

// ดึงรายการหนังสือในคำสั่งซื้อและส่วนลดระดับสินค้าที่ได้รับ
$sql_items = "
    SELECT
        b.book_id,
        b.title,
        oi.quantity,
        oi.unit_price,
        oi.discount_amount,
        oi.quantity * oi.unit_price - oi.discount_amount AS line_total,
        p.promo_name
    FROM order_item oi
    JOIN book b ON b.book_id = oi.book_id
    LEFT JOIN promotion p ON p.promo_id = oi.promo_id
    WHERE oi.order_id = ?
    ORDER BY b.book_id
";
$items = $pdo->prepare($sql_items);
$items->bindParam(1, $order_id);
$items->execute();

// ดึงประวัติการเปลี่ยนสถานะคำสั่งซื้อเรียงตามลำดับเวลา
$sql_logs = "
    SELECT
        l.status,
        l.note,
        l.changed_at,
        CONCAT(m.first_name, ' ', m.last_name) AS admin_name
    FROM order_status_log l
    LEFT JOIN member m ON m.member_id = l.changed_by
    WHERE l.order_id = ?
    ORDER BY l.changed_at, l.log_id
";
$logs = $pdo->prepare($sql_logs);
$logs->bindParam(1, $order_id);
$logs->execute();
$log_rows = $logs->fetchAll();
?>
<script>
  // ตรวจสอบสถานะคำสั่งซื้อล่าสุดทุก 10 วินาทีด้วย Ajax และอัปเดตไทม์ไลน์อัตโนมัติ
  var statusXhr;
  var logCount = <?=count($log_rows)?>;

  function pollStatus() {
    statusXhr = new XMLHttpRequest();
    statusXhr.onreadystatechange = showStatus;
    statusXhr.open("GET", "order-status.php?order_id=<?=$order_id?>");
    statusXhr.send();
  }

  function showStatus() {
    if (statusXhr.readyState == 4 && statusXhr.status == 200) {
      var data = JSON.parse(statusXhr.responseText);
      var now = new Date();
      document.getElementById("live").innerHTML = "อัปเดตอัตโนมัติ &middot; ตรวจล่าสุด " + now.toLocaleTimeString("th-TH");
      if (data.logs.length == logCount) return;

      var badge = document.getElementById("order-status");
      badge.className = "badge " + data.status;
      badge.innerHTML = data.status_th;
      document.getElementById("tracking").innerHTML = data.tracking_no
        ? data.carrier + " เลขพัสดุ <code>" + data.tracking_no + "</code>" : "-";

      var timeline = document.getElementById("timeline");
      for (var i = logCount; i < data.logs.length; i++) {
        var log = data.logs[i];
        var li = document.createElement("li");
        li.className = "new";
        li.innerHTML = '<span class="badge ' + log.status + '">' + log.status_th + '</span> <time>' + log.changed_at + '</time><br>';
        li.appendChild(document.createTextNode(log.note));
        timeline.appendChild(li);
      }
      logCount = data.logs.length;
      showToast("สถานะคำสั่งซื้ออัปเดตเป็น: " + data.status_th, true);
    }
  }

<?php if (!in_array($order["status"], ["delivered", "cancelled"])) : ?>
  window.addEventListener("load", function () { setInterval(pollStatus, 10000); });
<?php endif; ?>
</script>

<section class="order-head panel">
  <p>
    สถานะ <span id="order-status" class="badge <?=$order["status"]?>"><?=$status_th[$order["status"]]?></span>
    &middot; ขนส่ง <span id="tracking"><?=$order["tracking_no"] ? htmlspecialchars($order["carrier"]) . " เลขพัสดุ <code>" . htmlspecialchars($order["tracking_no"]) . "</code>" : "-"?></span>
  </p>
  <p>
    สั่งซื้อเมื่อ <?=$order["order_date"]?>
<?php if (is_admin()) : ?>
    &middot; ลูกค้า: <b><?=htmlspecialchars($order["customer"])?></b> (<?=htmlspecialchars($order["email"])?>, <?=htmlspecialchars($order["phone"])?>)
<?php endif; ?>
    <br>ที่อยู่จัดส่ง: <?=htmlspecialchars($order["ship_address"])?>
  </p>
<?php if ($order["ref_no"]) : ?>
  <p>
    การชำระเงิน: <?=$method_th[$order["method"]]?><?=$order["card_last4"] ? " ****" . $order["card_last4"] : ""?>
    &middot; เลขอ้างอิง <code><?=$order["ref_no"]?></code> &middot; <?=$order["paid_at"]?>
    <span class="badge <?=$order["pay_status"] == "paid" ? "ok" : "off"?>"><?=$order["pay_status"] == "paid" ? "ชำระแล้ว" : "คืนเงินแล้ว"?></span>
  </p>
<?php endif; ?>
</section>

<h2>รายการหนังสือ</h2>
<div class="table-wrap">
<table class="data">
  <thead>
    <tr><th>ชื่อหนังสือ</th><th class="num">ราคา/เล่ม</th><th class="num">จำนวน</th><th>โปรโมชั่นระดับสินค้า</th><th class="num">ส่วนลด</th><th class="num">รวม</th></tr>
  </thead>
  <tbody>
<?php while ($row = $items->fetch()) : ?>
    <tr>
      <td><a href="book.php?book_id=<?=$row["book_id"]?>"><?=htmlspecialchars($row["title"])?></a></td>
      <td class="num"><?=number_format($row["unit_price"], 2)?></td>
      <td class="num"><?=$row["quantity"]?></td>
      <td><?=$row["promo_name"] ? htmlspecialchars($row["promo_name"]) : "-"?></td>
      <td class="num"><?=$row["discount_amount"] > 0 ? "-" . number_format($row["discount_amount"], 2) : "-"?></td>
      <td class="num"><?=number_format($row["line_total"], 2)?></td>
    </tr>
<?php endwhile; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="5">ราคารวมก่อนหักส่วนลด</td><td class="num"><?=number_format($order["subtotal"], 2)?></td></tr>
    <tr><td colspan="5">(1) ส่วนลดระดับสินค้า (เทศกาล/ล้างสต็อก)</td><td class="num">-<?=number_format($order["item_discount"], 2)?></td></tr>
    <tr><td colspan="5">(2) ส่วนลดยอดซื้อขั้นบันได <?=$order["tier_name"] ? "&ndash; " . htmlspecialchars($order["tier_name"]) : ""?></td><td class="num">-<?=number_format($order["tier_discount"], 2)?></td></tr>
    <tr><td colspan="5">(3) คูปอง <?=$order["coupon_code"] ? "&ndash; " . htmlspecialchars($order["coupon_code"]) : ""?></td><td class="num">-<?=number_format($order["coupon_discount"], 2)?></td></tr>
    <tr><td colspan="5">ยอดสุทธิ</td><td class="num"><?=number_format($order["net_total"], 2)?></td></tr>
  </tfoot>
</table>
</div>

<div class="detail-bottom">
  <section>
    <h2>ไทม์ไลน์สถานะพัสดุ</h2>
    <p id="live" class="hint"><?=in_array($order["status"], ["delivered", "cancelled"]) ? "" : "อัปเดตอัตโนมัติทุก 10 วินาที"?></p>
    <ol class="timeline" id="timeline">
<?php foreach ($log_rows as $row) : ?>
      <li>
        <span class="badge <?=$row["status"]?>"><?=$status_th[$row["status"]]?></span>
        <time><?=$row["changed_at"]?></time><br>
        <?=htmlspecialchars($row["note"])?>
        <?php if ($row["admin_name"] && is_admin()) : ?><small>&middot; โดย <?=htmlspecialchars($row["admin_name"])?></small><?php endif; ?>
      </li>
<?php endforeach; ?>
    </ol>
  </section>

<?php if (is_admin() && !in_array($order["status"], ["delivered", "cancelled"])) : ?>
  <aside class="panel">
    <h2>อัปเดตสถานะ (ผู้ดูแล)</h2>
    <script>
      function genTracking() {
        var now = new Date();
        var mmdd = ("0" + (now.getMonth() + 1)).slice(-2) + ("0" + now.getDate()).slice(-2);
        document.getElementById("tracking_no").value = "TH" + mmdd + Math.floor(1000000 + Math.random() * 9000000);
      }
      // แสดงช่องเลือกขนส่งและเลขพัสดุเฉพาะเมื่อเปลี่ยนสถานะเป็น "จัดส่งแล้ว"
      function toggleShipFields() {
        var shipped = document.getElementById("new_status").value == "shipped";
        document.getElementById("ship-fields").style.display = shipped ? "block" : "none";
        document.getElementById("carrier").required = shipped;
        document.getElementById("tracking_no").required = shipped;
      }
    </script>
    <form class="edit" action="order-update.php" method="post">
      <?=csrf_field()?>
      <input type="hidden" name="order_id" value="<?=$order_id?>">
      <label for="new_status">สถานะใหม่</label>
      <select id="new_status" name="status" onchange="toggleShipFields()" required>
<?php   foreach ($next_status[$order["status"]] as $s) : ?>
        <option value="<?=$s?>"><?=$status_th[$s]?></option>
<?php   endforeach; ?>
      </select>
      <div id="ship-fields" style="display:none">
        <label for="carrier">บริษัทขนส่ง</label>
        <select id="carrier" name="carrier">
          <option value="">-- เลือก --</option>
<?php   foreach ($carriers as $c) : ?>
          <option<?=$c == $order["carrier"] ? " selected" : ""?>><?=$c?></option>
<?php   endforeach; ?>
        </select>
        <label for="tracking_no">หมายเลขพัสดุ</label>
        <input type="text" id="tracking_no" name="tracking_no" value="<?=htmlspecialchars($order["tracking_no"] ?? "")?>"
               pattern="[A-Z0-9]{8,20}" title="ตัวพิมพ์ใหญ่ภาษาอังกฤษหรือตัวเลข 8-20 ตัว">
        <button type="button" class="link-btn" onclick="genTracking()">สร้างเลขพัสดุอัตโนมัติ</button>
      </div>
      <label for="note">หมายเหตุ (ไม่บังคับ)</label>
      <input type="text" id="note" name="note" maxlength="200">
      <p><input type="submit" value="บันทึกสถานะ"></p>
    </form>
    <script>toggleShipFields();</script>
  </aside>
<?php endif; ?>
</div>

<p><a class="btn secondary" href="orders.php">&larr; กลับไปหน้าคำสั่งซื้อ</a></p>
<?php include "footer.php"; ?>
