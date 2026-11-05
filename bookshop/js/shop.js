// เพิ่มหนังสือลงตะกร้าด้วย Ajax (XMLHttpRequest) และอัปเดตจำนวนบนไอคอนตะกร้าทันที
function addToCart(book_id, qty) {
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
      var result = JSON.parse(xhr.responseText);
      if (result.ok) {
        var count = document.getElementById("cart-count");
        count.innerHTML = result.count;
        count.className = "count bump";
        setTimeout(function () { count.className = "count"; }, 600);
      }
      showToast(result.message, result.ok);
    }
  };
  xhr.open("POST", "cart-action.php");
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("action=add&ajax=1&book_id=" + book_id + "&qty=" + qty + "&token=" + TOKEN);
  return false;
}

// แสดงข้อความแจ้งเตือนมุมขวาล่างของจอ แล้วลบออกอัตโนมัติหลัง 3 วินาที
function showToast(text, ok) {
  var box = document.getElementById("toast");
  if (box == null) {
    box = document.createElement("div");
    box.id = "toast";
    document.body.appendChild(box);
  }
  var item = document.createElement("p");
  item.className = ok ? "ok" : "error";
  item.textContent = text;
  box.appendChild(item);
  setTimeout(function () { box.removeChild(item); }, 3000);
}
