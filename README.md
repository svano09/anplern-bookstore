# อ่านเพลิน Bookstore — Web Project
เว็บร้านหนังสือออนไลน์ (PHP + PDO + MySQL/MariaDB) ครบตาม Requirements ที่เสนอไว้ และ Project Rubrics ทั้ง 11 ข้อ
รายละเอียดทั้งหมดพร้อมภาพหน้าจออยู่ใน `รายงานโครงงาน-ฉบับสมบูรณ์.pdf`
`bookshop/`โค้ดเว็บทั้งหมด (PHP, CSS, JavaScript, ภาพปก)
`database/bookshop.sql` สร้าง 10 ตาราง + ข้อมูลตัวอย่าง (เพิ่มตาราง `payment` จากครั้งที่ 2)
`database/queries.sql`Query จากความคืบหน้าครั้งที่ 2
`docs/`System Architecture, Sitemap, Wireframe, ER Diagram (PNG + SVG)
`รายงานโครงงาน-ฉบับสมบูรณ์.pdf`รายงาน 31 หน้า: Requirements, Architecture, Wireframe, ER, ภาพหน้าจอ, ตารางเทียบ Rubrics
`docker-compose.yml`, `docker/`เปิดระบบด้วย Docker

**Docker** (ง่ายสุด): ในโฟลเดอร์นี้สั่ง `docker compose up -d --build`

- เว็บ http://localhost:8081 · phpMyAdmin http://localhost:8082
- ล้างข้อมูลกลับเป็นค่าเริ่มต้น: `docker compose down -v` แล้ว `docker compose up -d`

**XAMPP**

1. คัดลอก `bookshop/` ไปไว้ที่ `C:\xampp\htdocs\bookshop`
2. phpMyAdmin → สร้างฐานข้อมูล `bookshop` (utf8mb4_unicode_ci) → Import `database/bookshop.sql`
3. เปิด http://localhost/bookshop/

## บัญชีและข้อมูลทดลอง

| ผู้ดูแลระบบ | `wipawadee.admin@example.com` / `Book@2026`
| ลูกค้า | `somchai.j@example.com` / `Book@2026` (มีหนังสือในตะกร้าอยู่แล้ว 3 เล่ม ใช้คูปอง BOOK15 ได้ทันที) |
| บัตรชำระสำเร็จ | `4242 4242 4242 4242` วันหมดอายุในอนาคต CVV 3 หลักใดก็ได้
| บัตรถูกปฏิเสธ | `4000 0000 0000 0002`
| คูปองที่ใช้ได้ | `BOOK15` (ขั้นต่ำ 700 ลด 15% สูงสุด 150), `WELCOME50` (ขั้นต่ำ 300 ลด 50)
| ISBN ทดลอง | `9780132350884`, `9780439708180` (ต้องต่ออินเทอร์เน็ต)

กล่องบัญชีทดลองในหน้าเข้าสู่ระบบแสดงเฉพาะตอนเปิดผ่าน localhost


