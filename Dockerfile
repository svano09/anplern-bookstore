# Apache + PHP 8.3 + PDO MySQL สำหรับ Deploy ขึ้น Cloud (Koyeb / Render / Fly.io)
FROM php:8.3-apache

# ติดตั้ง pdo_mysql และ ca-certificates (สำหรับเชื่อมต่อ Cloud DB ด้วย SSL เช่น TiDB Cloud)
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates \
 && update-ca-certificates \
 && docker-php-ext-install pdo_mysql \
 && ln -sf /usr/share/zoneinfo/Asia/Bangkok /etc/localtime \
 && echo "date.timezone = Asia/Bangkok" > /usr/local/etc/php/conf.d/timezone.ini \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# คัดลอกโค้ดเว็บและไฟล์ฐานข้อมูลเริ่มต้นเข้าสู่อิมเมจ
COPY bookshop/ /var/www/html/
COPY database/bookshop.sql /var/www/bookshop.sql

# ตั้งค่าสิทธิ์โฟลเดอร์สำหรับอัปโหลดภาพปกหนังสือ
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 777 /var/www/html/img

EXPOSE 80
