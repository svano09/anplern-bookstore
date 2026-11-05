<?php include "connect.php"; ?>
<?php
require_login();
$title = "ข้อมูลส่วนตัว";
include "header.php";

$sql = "
    SELECT
        m.first_name,
        m.last_name,
        m.email,
        m.phone,
        m.address,
        m.role,
        m.created_at,
        COUNT(o.order_id) AS order_count,
        IFNULL(SUM(o.net_total), 0) AS total_spent
    FROM member m
    LEFT JOIN orders o
        ON o.member_id = m.member_id
       AND o.status <> 'cancelled'
    WHERE m.member_id = ?
    GROUP BY m.member_id
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $_SESSION["member_id"]);
$stmt->execute();
$me = $stmt->fetch();
?>
<div class="stats">
  <div class="stat"><b><?=$me["order_count"]?></b><span>คำสั่งซื้อ</span></div>
  <div class="stat"><b><?=number_format($me["total_spent"], 2)?></b><span>ยอดซื้อสะสม (บาท)</span></div>
  <div class="stat"><b><?=cart_count($pdo)?></b><span>เล่มในตะกร้า</span></div>
  <div class="stat"><b><?=$me["role"] == "admin" ? "ผู้ดูแล" : "ลูกค้า"?></b><span>สิทธิ์ผู้ใช้</span></div>
</div>

<div class="auth-layout">
  <section class="panel">
    <h2>ข้อมูลบัญชี</h2>
    <form class="edit" action="account-save.php" method="post">
      <?=csrf_field()?>
      <label>ชื่อ-นามสกุล</label>
      <p><?=htmlspecialchars($me["first_name"] . " " . $me["last_name"])?></p>

      <label>อีเมล</label>
      <p><?=htmlspecialchars($me["email"])?> <small class="hint">(สมัครเมื่อ <?=substr($me["created_at"], 0, 10)?>)</small></p>

      <label for="phone">เบอร์โทรศัพท์</label>
      <input type="tel" id="phone" name="phone" required value="<?=htmlspecialchars($me["phone"])?>"
             pattern="0[0-9]{1,2}-?[0-9]{3}-?[0-9]{4}" title="เบอร์โทร 9-10 หลัก ขึ้นต้นด้วย 0 เช่น 081-234-5678">

      <label for="address">ที่อยู่จัดส่ง</label>
      <textarea id="address" name="address" rows="3" required minlength="10" maxlength="500"><?=htmlspecialchars($me["address"])?></textarea>

      <p><input type="submit" value="บันทึกการแก้ไข"></p>
    </form>
  </section>

  <aside class="panel">
    <h2>ข้อมูล Session ของคุณ</h2>
    <p class="hint">เว็บจำผู้ใช้ที่เข้าสู่ระบบด้วย Session (เก็บบนเซิร์ฟเวอร์) โดยเบราว์เซอร์ถือแค่รหัส Session ไว้ใน Cookie ชื่อ <?=session_name()?></p>
    <table class="data">
      <tr><th>Session ID</th><td><code><?=substr(session_id(), 0, 10)?>…</code> (แสดงแค่บางส่วน)</td></tr>
      <tr><th>$_SESSION["member_id"]</th><td><?=$_SESSION["member_id"]?></td></tr>
      <tr><th>$_SESSION["role"]</th><td><?=$_SESSION["role"]?></td></tr>
      <tr><th>$_SESSION["login_time"]</th><td><?=$_SESSION["login_time"] ?? "-"?></td></tr>
      <tr><th>$_SESSION["coupon"]</th><td><?=htmlspecialchars($_SESSION["coupon"] ?? "-")?></td></tr>
      <tr><th>Cookie remember_email</th><td><?=htmlspecialchars($_COOKIE["remember_email"] ?? "-")?></td></tr>
    </table>
    <p><a class="btn secondary" href="logout.php">ออกจากระบบ (ทำลาย Session)</a></p>
  </aside>
</div>
<?php include "footer.php"; ?>
