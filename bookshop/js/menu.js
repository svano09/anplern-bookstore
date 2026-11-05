// สลับการแสดงผลเมนูนำทางบนหน้าจอมือถือ
function toggle_visibility(id) {
  var e = document.getElementById(id);
  if (e.style.display == 'block')
    e.style.display = 'none';
  else
    e.style.display = 'block';
}

// ปรับสถานะเมนูอัตโนมัติเมื่อเปลี่ยนขนาดหน้าจอ (จอ >= 768px แสดงเมนูด้านข้างเสมอ)
window.onresize = function () {
  var e = document.getElementById("menu");
  if (window.innerWidth >= 768)
    e.style.display = 'block';
  else
    e.style.display = 'none';
};
