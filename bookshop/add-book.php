<?php include "connect.php"; ?>
<?php
require_admin();
$title = "เพิ่มหนังสือใหม่";
include "header.php";

$cats = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_id");
$cats->execute();

$old = $_SESSION["book_old"] ?? [];
unset($_SESSION["book_old"]);
function old($name, $default = "") {
    global $old;
    return htmlspecialchars($old[$name] ?? $default);
}
?>
<script>
  // ดึงข้อมูลหนังสือจาก Open Library API (รูปแบบ JSON) มาประมวลผลและเติมลงในฟอร์มอัตโนมัติ
  async function importIsbn() {
    var input = document.getElementById("isbn");
    var isbn = input.value.replace(/[^0-9X]/gi, "");
    var result = document.getElementById("isbn-result");
    if (!/^([0-9]{9}[0-9X]|[0-9]{13})$/i.test(isbn)) {
      input.setCustomValidity("ISBN ต้องเป็นตัวเลข 10 หรือ 13 หลัก");
      input.reportValidity();
      return;
    }
    input.setCustomValidity("");
    result.innerHTML = "กำลังดึงข้อมูลจาก Open Library...";

    try {
      var response = await fetch("https://openlibrary.org/api/books?bibkeys=ISBN:" + isbn + "&format=json&jscmd=data");
      var rawData = await response.text();
      var objectData = JSON.parse(rawData);
      var book = objectData["ISBN:" + isbn];
      if (!book) {
        result.innerHTML = '<p class="msg error">ไม่พบหนังสือ ISBN ' + isbn + ' ใน Open Library</p>';
        return;
      }

      var authors = (book.authors || []).map(function (a) { return a.name; }).join(", ");
      var year = (book.publish_date || "").match(/[0-9]{4}/);
      var publisher = book.publishers ? book.publishers[0].name : "-";
      var subjects = (book.subjects || []).slice(0, 3).map(function (s) { return s.name; }).join(", ");
      var fullTitle = book.title + (book.subtitle ? ": " + book.subtitle : "");
      var details = "ISBN " + isbn + " · สำนักพิมพ์ " + publisher + (year ? " · พิมพ์ปี " + year[0] : "") +
                    (book.number_of_pages ? " · " + book.number_of_pages + " หน้า" : "") +
                    (subjects ? "\nหัวข้อ: " + subjects : "");

      result.innerHTML = "";
      var card = document.createElement("div");
      card.className = "isbn-card";
      if (book.cover) {
        var img = document.createElement("img");
        img.src = book.cover.medium;
        img.alt = "ปกหนังสือ";
        card.appendChild(img);
      }
      var text = document.createElement("div");
      text.innerHTML = "<b></b><br><span></span><br><small></small>";
      text.querySelector("b").textContent = fullTitle;
      text.querySelector("span").textContent = "ผู้แต่ง: " + (authors || "-");
      text.querySelector("small").textContent = details;
      card.appendChild(text);
      result.appendChild(card);

      document.getElementById("title").value = fullTitle.substring(0, 200);
      document.getElementById("author").value = (authors || "ไม่ระบุ").substring(0, 150);
      document.getElementById("description").value = details;
      document.getElementById("cover_url").value = book.cover ? book.cover.medium : "";
      document.getElementById("cover-note").style.display = book.cover ? "block" : "none";
      document.getElementById("category_id").focus();
      showToast("ดึงข้อมูล \"" + book.title + "\" แล้ว เลือกหมวด ราคา และจำนวน แล้วกดบันทึก", true);
    } catch (e) {
      result.innerHTML = '<p class="msg error">เชื่อมต่อ Open Library ไม่ได้ (ต้องต่ออินเทอร์เน็ต)</p>';
    }
  }
</script>

<p class="lead">Create: กรอกข้อมูลหนังสือใหม่ หรือนำเข้าข้อมูลจาก ISBN แล้วกดปุ่ม "เพิ่มหนังสือ"</p>

<section class="panel isbn-import">
  <h2>นำเข้าข้อมูลจาก ISBN (Open Library Web API)</h2>
  <div class="inline-form">
    <input type="text" id="isbn" inputmode="numeric" placeholder="เช่น 9780132350884" aria-label="ISBN"
           onkeydown="if (event.key == 'Enter') { importIsbn(); return false; }">
    <button type="button" class="btn" onclick="importIsbn()">ดึงข้อมูล</button>
  </div>
  <p class="hint">ลองใช้: 9780132350884 (Clean Code), 9780439708180 (Harry Potter), 9780061120084 (To Kill a Mockingbird)</p>
  <div id="isbn-result"></div>
</section>

<form class="edit" action="insert-book.php" method="post" enctype="multipart/form-data">
  <?=csrf_field()?>
  <input type="hidden" id="cover_url" name="cover_url" value="">

  <label for="title">ชื่อหนังสือ</label>
  <input type="text" id="title" name="title" required maxlength="200" value="<?=old("title")?>">

  <label for="author">ผู้แต่ง</label>
  <input type="text" id="author" name="author" required maxlength="150" value="<?=old("author")?>">

  <label for="category_id">หมวดหมู่</label>
  <select id="category_id" name="category_id" required>
    <option value="">-- เลือกหมวดหมู่ --</option>
<?php while ($row = $cats->fetch()) : ?>
    <option value="<?=$row["category_id"]?>"<?=($old["category_id"] ?? "") == $row["category_id"] ? " selected" : ""?>><?=$row["category_name"]?></option>
<?php endwhile; ?>
  </select>

  <label for="price">ราคา (บาท)</label>
  <input type="number" id="price" name="price" required min="1" max="99999" step="0.01" value="<?=old("price")?>">

  <label for="stock">จำนวนคงเหลือ (เล่ม)</label>
  <input type="number" id="stock" name="stock" required min="0" max="99999" step="1" value="<?=old("stock")?>">

  <label for="added_date">วันที่นำเข้าระบบ</label>
  <input type="date" id="added_date" name="added_date" required value="<?=old("added_date", date("Y-m-d"))?>">
  <div class="hint">ใช้คำนวณส่วนลดล้างสต็อก (หนังสือที่อยู่ในระบบนานและเหลือน้อย)</div>

  <label for="description">รายละเอียด</label>
  <textarea id="description" name="description" rows="3"><?=old("description")?></textarea>

  <label for="cover">ภาพปก (ไฟล์ .jpg)</label>
  <input type="file" id="cover" name="cover" accept="image/jpeg">
  <div id="cover-note" class="hint" style="display:none">ถ้าไม่เลือกไฟล์ จะใช้ภาพปกจาก Open Library</div>

  <p>
    <input type="submit" value="เพิ่มหนังสือ">
    <a class="btn secondary" href="list-book.php">ยกเลิก</a>
  </p>
</form>
<?php include "footer.php"; ?>
