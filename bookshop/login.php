<?php include "connect.php"; ?>
<?php
$msg  = $_GET["msg"] ?? "";
$back = safe_back($_GET["back"] ?? "index.php");

// หากเข้าสู่ระบบอยู่แล้วให้ส่งกลับหน้าเดิม (ยกเว้นกรณีที่ถูกส่งมาเพราะต้องใช้สิทธิ์ผู้ดูแลระบบ)
if (is_login() && $msg != "admin") {
    header("location: " . $back);
    exit;
}

$title = "เข้าสู่ระบบ";
include "header.php";

$remember = $_COOKIE["remember_email"] ?? "";
$messages = [
    "admin"  => ["error", "หน้านี้สำหรับผู้ดูแลระบบ กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแล"],
    "failed" => ["error", "อีเมลหรือรหัสผ่านไม่ถูกต้อง"],
    "logout" => ["ok", "ออกจากระบบเรียบร้อยแล้ว"],
];
?>
<?php if (isset($messages[$msg])) : ?>
<p class="msg <?=$messages[$msg][0]?>"><?=$messages[$msg][1]?></p>
<?php endif; ?>

<div class="auth-layout">
  <section class="panel">
    <form class="edit" action="check-login.php" method="post">
      <?=csrf_field()?>
      <input type="hidden" name="back" value="<?=htmlspecialchars($back)?>">

      <label for="email">อีเมล</label>
      <input type="email" id="email" name="email" required maxlength="100" autocomplete="username"
             value="<?=htmlspecialchars($remember)?>" placeholder="name@example.com">

      <label for="password">รหัสผ่าน</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">

      <label class="check"><input type="checkbox" name="remember" value="1"<?=$remember ? " checked" : ""?>> จดจำอีเมลของฉัน (Cookie 30 วัน)</label>

      <p><input type="submit" value="เข้าสู่ระบบ"></p>
      <p>ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
    </form>
  </section>

<?php // แสดงข้อมูลบัญชีตัวอย่างเฉพาะเมื่อเปิดผ่านเครื่องตัวเอง (localhost) ?>
<?php if (in_array($_SERVER["SERVER_NAME"], ["localhost", "127.0.0.1"])) : ?>
  <aside class="panel demo-accounts">
    <h2>บัญชีทดลองสำหรับตรวจงาน</h2>
    <p>รหัสผ่านทุกบัญชี: <code>Book@2026</code></p>
    <ul>
      <li>ผู้ดูแลระบบ: <code>wipawadee.admin@example.com</code></li>
      <li>ลูกค้า: <code>somchai.j@example.com</code></li>
    </ul>
  </aside>
<?php endif; ?>
</div>
<?php include "footer.php"; ?>
