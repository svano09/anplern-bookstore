<?php include "connect.php"; ?>
<?php
if (is_login()) {
    header("location: account.php");
    exit;
}
$title = "สมัครสมาชิก";
include "header.php";

$old = $_SESSION["register_old"] ?? [];
unset($_SESSION["register_old"]);
function old($name) {
    global $old;
    return htmlspecialchars($old[$name] ?? "");
}
?>
<script>
  var xmlHttp;

  // ตรวจสอบอีเมลซ้ำแบบเรียลไทม์ผ่าน Ajax (XMLHttpRequest)
  function checkEmail() {
    var email = document.getElementById("email").value;
    if (email == "") return;
    document.getElementById("email").className = "thinking";
    document.getElementById("email-status").innerHTML = "กำลังตรวจสอบ...";

    xmlHttp = new XMLHttpRequest();
    xmlHttp.onreadystatechange = showEmailStatus;
    xmlHttp.open("GET", "check-email.php?email=" + encodeURIComponent(email));
    xmlHttp.send();
  }

  function showEmailStatus() {
    if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
      var input = document.getElementById("email");
      var status = document.getElementById("email-status");
      if (xmlHttp.responseText == "okay") {
        input.className = "approved";
        input.setCustomValidity("");
        status.innerHTML = "✓ ใช้อีเมลนี้ได้";
        status.style.color = "var(--good)";
      } else {
        input.className = "denied";
        var text = xmlHttp.responseText == "taken" ? "อีเมลนี้มีผู้ใช้แล้ว" : "รูปแบบอีเมลไม่ถูกต้อง";
        input.setCustomValidity(text);
        status.innerHTML = "✗ " + text;
        status.style.color = "var(--sale)";
      }
    }
  }

  // ประเมินระดับความปลอดภัยของรหัสผ่านและอัปเดตแถบสถานะด้วย DOM
  function checkPassword() {
    var pw = document.getElementById("password").value;
    var rules = {
      "rule-length": pw.length >= 8,
      "rule-upper": /[A-Z]/.test(pw),
      "rule-lower": /[a-z]/.test(pw),
      "rule-digit": /[0-9]/.test(pw),
      "rule-special": /[^A-Za-z0-9]/.test(pw)
    };
    var score = 0;
    for (var id in rules) {
      document.getElementById(id).className = rules[id] ? "pass" : "fail";
      if (rules[id]) score++;
    }
    var levels = [
      ["", "#ddd"], ["อ่อนมาก", "#c8553d"], ["อ่อน", "#e07b39"],
      ["ปานกลาง", "#d4a017"], ["ดี", "#5b9e4d"], ["แข็งแรงมาก", "#2e7d4f"]
    ];
    var bar = document.getElementById("strength-bar");
    bar.style.width = (score * 20) + "%";
    bar.style.background = levels[score][1];
    document.getElementById("strength-text").innerHTML = pw == "" ? "" : "ความปลอดภัย: " + levels[score][0];

    document.getElementById("rules").style.display = score == 5 ? "none" : "block";
    checkConfirm();
  }

  // ตรวจสอบว่ารหัสผ่านและช่องยืนยันรหัสผ่านตรงกัน
  function checkConfirm() {
    var pw = document.getElementById("password").value;
    var confirm = document.getElementById("confirm");
    var match = document.getElementById("confirm-status");
    if (confirm.value == "") {
      match.innerHTML = "";
      confirm.setCustomValidity("");
    } else if (confirm.value == pw) {
      match.innerHTML = "✓ รหัสผ่านตรงกัน";
      match.style.color = "var(--good)";
      confirm.setCustomValidity("");
    } else {
      match.innerHTML = "✗ รหัสผ่านไม่ตรงกัน";
      match.style.color = "var(--sale)";
      confirm.setCustomValidity("รหัสผ่านไม่ตรงกัน");
    }
  }
</script>

<p class="lead">สมัครสมาชิกเพื่อสั่งซื้อหนังสือและติดตามพัสดุ บัญชีใหม่จะได้สิทธิ์ลูกค้าและเข้าสู่ระบบให้อัตโนมัติ</p>

<section class="panel">
  <form class="edit" action="register-save.php" method="post">
    <?=csrf_field()?>
    <div class="row-2">
      <div>
        <label for="first_name">ชื่อ</label>
        <input type="text" id="first_name" name="first_name" required maxlength="50" value="<?=old("first_name")?>">
      </div>
      <div>
        <label for="last_name">นามสกุล</label>
        <input type="text" id="last_name" name="last_name" required maxlength="50" value="<?=old("last_name")?>">
      </div>
    </div>

    <label for="email">อีเมล</label>
    <input type="email" id="email" name="email" required maxlength="100" value="<?=old("email")?>"
           onblur="checkEmail()" placeholder="name@example.com" autocomplete="email">
    <div id="email-status" class="hint" aria-live="polite"></div>

    <label for="password">รหัสผ่าน</label>
    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}"
           title="อย่างน้อย 8 ตัว มีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข" oninput="checkPassword()">
    <div class="strength"><div id="strength-bar"></div></div>
    <div id="strength-text" class="hint"></div>
    <ul id="rules" class="rules">
      <li id="rule-length" class="fail">อย่างน้อย 8 ตัวอักษร</li>
      <li id="rule-upper" class="fail">มีตัวพิมพ์ใหญ่ (A-Z)</li>
      <li id="rule-lower" class="fail">มีตัวพิมพ์เล็ก (a-z)</li>
      <li id="rule-digit" class="fail">มีตัวเลข (0-9)</li>
      <li id="rule-special" class="fail">มีอักขระพิเศษ เช่น @ # ! (ไม่บังคับ แต่ช่วยให้ปลอดภัยขึ้น)</li>
    </ul>

    <label for="confirm">ยืนยันรหัสผ่าน</label>
    <input type="password" id="confirm" name="confirm" required autocomplete="new-password" oninput="checkConfirm()">
    <div id="confirm-status" class="hint" aria-live="polite"></div>

    <label for="phone">เบอร์โทรศัพท์</label>
    <input type="tel" id="phone" name="phone" required value="<?=old("phone")?>" placeholder="081-234-5678"
           pattern="0[0-9]{1,2}-?[0-9]{3}-?[0-9]{4}" title="เบอร์โทร 9-10 หลัก ขึ้นต้นด้วย 0 เช่น 081-234-5678">

    <label for="address">ที่อยู่จัดส่ง</label>
    <textarea id="address" name="address" rows="3" required minlength="10" maxlength="500"
              placeholder="บ้านเลขที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด รหัสไปรษณีย์"><?=old("address")?></textarea>

    <p><input type="submit" value="สมัครสมาชิก"></p>
    <p>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
  </form>
</section>
<?php include "footer.php"; ?>
