<?php include "connect.php"; ?>
<?php
require_admin();
$title = "แดชบอร์ดผู้ดูแลระบบ";
include "header.php";

// สรุปจำนวนรายการที่ต้องดูแลในปัจจุบัน
$todoSql = "
    SELECT
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'packing') AS packing_orders,
        (SELECT COUNT(*) FROM book WHERE stock <= 5) AS low_stock,
        (SELECT COUNT(*) FROM member WHERE role = 'customer') AS customers
";
$stmt = $pdo->prepare($todoSql);
$stmt->execute();
$todo = $stmt->fetch();

$cats = $pdo->prepare("SELECT category_id, category_name FROM category ORDER BY category_id");
$cats->execute();
?>
<script src="js/chart.umd.min.js"></script>
<script>
  var charts = {};

  // ดึงข้อมูลสถิติจาก report-data.php ตามตัวกรอง แล้ววาดกราฟและตารางใหม่โดยไม่ต้องโหลดหน้า
  async function loadReport() {
    var from = document.getElementById("from").value;
    var to = document.getElementById("to").value;
    var category = document.getElementById("category").value;
    document.getElementById("report-status").innerHTML = "กำลังโหลดข้อมูล...";

    var response = await fetch("report-data.php?from=" + from + "&to=" + to + "&category=" + category);
    var rawData = await response.text();
    var data = JSON.parse(rawData);

    showSummary(data.summary);
    showMonthly(data.monthly);
    showCategories(data.categories);
    showStatus(data.status);
    showTopBooks(data.top_books);
    document.getElementById("report-status").innerHTML = "ข้อมูลวันที่ " + data.from + " ถึง " + data.to;
    return false;
  }

  function baht(n) {
    return Number(n).toLocaleString("th-TH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function showSummary(s) {
    var orders = Number(s.orders);
    document.getElementById("sum-revenue").innerHTML = baht(s.revenue);
    document.getElementById("sum-orders").innerHTML = orders;
    document.getElementById("sum-books").innerHTML = s.books;
    document.getElementById("sum-avg").innerHTML = orders ? baht(s.revenue / orders) : "-";
  }

  function draw(id, config) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(document.getElementById(id), config);
  }

  // ยอดขายรายเดือน: กราฟแท่ง + ตารางที่คำนวณอัตราการเติบโตเทียบกับเดือนก่อนหน้า
  function showMonthly(rows) {
    draw("chart-monthly", {
      type: "bar",
      data: {
        labels: rows.map(function (r) { return r.month; }),
        datasets: [
          { label: "ยอดขาย (บาท)", data: rows.map(function (r) { return r.revenue; }), backgroundColor: "#4f8db8", yAxisID: "y" },
          { label: "จำนวนคำสั่งซื้อ", data: rows.map(function (r) { return r.orders; }), type: "line", borderColor: "#c8553d", backgroundColor: "#c8553d", yAxisID: "y1" }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
                 scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false } } } }
    });

    var tbody = document.getElementById("monthly-rows");
    tbody.innerHTML = "";
    for (var i = 0; i < rows.length; i++) {
      var growth = "-";
      if (i > 0 && Number(rows[i - 1].revenue) > 0) {
        var pct = (rows[i].revenue - rows[i - 1].revenue) / rows[i - 1].revenue * 100;
        growth = '<span class="' + (pct >= 0 ? "up" : "down") + '">' + (pct >= 0 ? "▲ " : "▼ ") + Math.abs(pct).toFixed(1) + "%</span>";
      }
      var tr = document.createElement("tr");
      tr.innerHTML = "<td>" + rows[i].month + '</td><td class="num">' + rows[i].orders + '</td><td class="num">' +
                     baht(rows[i].revenue) + '</td><td class="num">' + growth + "</td>";
      tbody.appendChild(tr);
    }
  }

  // ยอดขายแยกหมวด: กราฟโดนัทพร้อมคำนวณสัดส่วนเป็นเปอร์เซ็นต์
  function showCategories(rows) {
    var total = 0;
    for (var i = 0; i < rows.length; i++) total += Number(rows[i].revenue);
    draw("chart-category", {
      type: "doughnut",
      data: {
        labels: rows.map(function (r) { return r.category_name + " " + (total ? (r.revenue / total * 100).toFixed(1) : 0) + "%"; }),
        datasets: [{ data: rows.map(function (r) { return r.revenue; }),
                     backgroundColor: ["#22577a", "#38a3a5", "#57cc99", "#ffd166", "#ef8354", "#c8553d", "#8e7dbe"] }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "right" } } }
    });
  }

  function showStatus(rows) {
    draw("chart-status", {
      type: "bar",
      data: {
        labels: rows.map(function (r) { return r.status_th; }),
        datasets: [{ label: "คำสั่งซื้อ", data: rows.map(function (r) { return r.orders; }),
                     backgroundColor: ["#d4a017", "#8e7dbe", "#4f8db8", "#2e7d4f", "#aaaaaa"] }]
      },
      options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
  }

  function showTopBooks(rows) {
    var tbody = document.getElementById("top-rows");
    tbody.innerHTML = "";
    for (var i = 0; i < rows.length; i++) {
      var tr = document.createElement("tr");
      var td = document.createElement("td");
      td.textContent = (i + 1) + ". " + rows[i].title;
      tr.appendChild(td);
      tr.insertAdjacentHTML("beforeend", '<td class="num">' + rows[i].books + '</td><td class="num">' + baht(rows[i].revenue) + "</td>");
      tbody.appendChild(tr);
    }
  }

  window.addEventListener("load", loadReport);
</script>

<p class="lead">ภาพรวมร้าน สถิติธุรกรรม และกราฟวิเคราะห์ข้อมูล &mdash; เลือกช่วงวันที่หรือหมวดหมู่ กราฟจะเปลี่ยนทันที</p>

<div class="stats">
  <a class="stat" href="orders.php?status=pending"><b><?=$todo["pending_orders"]?></b><span>คำสั่งซื้อรอยืนยัน</span></a>
  <a class="stat" href="orders.php?status=packing"><b><?=$todo["packing_orders"]?></b><span>รอจัดส่ง (กำลังแพ็ก)</span></a>
  <a class="stat" href="list-book.php?low=1"><b><?=$todo["low_stock"]?></b><span>หนังสือใกล้หมด (≤ 5 เล่ม)</span></a>
  <a class="stat" href="members.php"><b><?=$todo["customers"]?></b><span>สมาชิกลูกค้า</span></a>
</div>

<form class="filter" onsubmit="return false">
  <label>ตั้งแต่วันที่ <input type="date" id="from" value="<?=report_start($pdo)?>" onchange="loadReport()" required></label>
  <label>ถึงวันที่ <input type="date" id="to" value="<?=date("Y-m-d")?>" onchange="loadReport()" required></label>
  <label>หมวดหมู่
    <select id="category" onchange="loadReport()">
      <option value="0">ทุกหมวด</option>
<?php while ($c = $cats->fetch()) : ?>
      <option value="<?=$c["category_id"]?>"><?=$c["category_name"]?></option>
<?php endwhile; ?>
    </select>
  </label>
  <span id="report-status" class="hint"></span>
</form>

<div class="stats">
  <div class="stat"><b id="sum-revenue">-</b><span>ยอดขาย (บาท)</span></div>
  <div class="stat"><b id="sum-orders">-</b><span>คำสั่งซื้อ</span></div>
  <div class="stat"><b id="sum-books">-</b><span>จำนวนเล่มที่ขายได้</span></div>
  <div class="stat"><b id="sum-avg">-</b><span>เฉลี่ยต่อคำสั่งซื้อ (บาท)</span></div>
</div>
<p class="hint">ยอดขาย = ราคา &times; จำนวน หักส่วนลดระดับสินค้า (ยังไม่หักส่วนลดขั้นบันไดและคูปองซึ่งคิดทั้งคำสั่งซื้อ) ไม่นับคำสั่งซื้อที่ยกเลิก</p>

<div class="dash-grid">
  <section class="panel wide">
    <h2>ยอดขายรายเดือน</h2>
    <div class="chart-box"><canvas id="chart-monthly"></canvas></div>
  </section>
  <section class="panel">
    <h2>ยอดขายแยกหมวดหมู่</h2>
    <div class="chart-box"><canvas id="chart-category"></canvas></div>
  </section>
  <section class="panel">
    <h2>สถานะคำสั่งซื้อ</h2>
    <div class="chart-box"><canvas id="chart-status"></canvas></div>
  </section>
  <section class="panel">
    <h2>ตารางยอดขายรายเดือน</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>เดือน</th><th class="num">คำสั่งซื้อ</th><th class="num">ยอดขาย</th><th class="num">เทียบเดือนก่อน</th></tr></thead>
        <tbody id="monthly-rows"></tbody>
      </table>
    </div>
  </section>
  <section class="panel">
    <h2>หนังสือขายดี 5 อันดับ</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>ชื่อหนังสือ</th><th class="num">เล่ม</th><th class="num">ยอดขาย</th></tr></thead>
        <tbody id="top-rows"></tbody>
      </table>
    </div>
  </section>
</div>
<?php include "footer.php"; ?>
