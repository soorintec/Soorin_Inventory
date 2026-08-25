# آموزش نصب روی دبیان

نصب سامانه انبارداری سورین روی یک سرور Debian 13 (مثلاً یک VM روی ESXi در شبکهٔ
داخلی). برای حداکثر ۲ کاربر همزمان کاملاً مناسب است.

> پیش‌نیاز: سرور Debian 13 با دسترسی root (یا کاربر با sudo) و **اینترنت** برای
> دانلود بسته‌ها. اگر سرور کاملاً آفلاین است، بستهٔ آفلاین جدا لازم است.

دو راه برای گذاشتن کد روی سرور هست؛ یکی را انتخاب کن. بعد از هر دو، یک دستور
نصب مشترک زده می‌شود.

---

## روش ۱ — با فایل زیپ (بدون نیاز به توکن گیت‌هاب)

### قدم ۱: آپلود فایل روی سرور
فایل `soorin-inventory.zip` را با **WinSCP** بکش‌وبنداز روی سرور، یا در ویندوز
(cmd) این را بزن (`pscp` همراه PuTTY نصب می‌شود):
```
pscp soorin-inventory.zip root@IP-سرور:/root/
```

### قدم ۲: اکسترکت
در PuTTY:
```bash
apt-get update && apt-get install -y unzip
mkdir -p /var/www/soorin-inventory
unzip /root/soorin-inventory.zip -d /var/www/soorin-inventory
cd /var/www/soorin-inventory
```

سپس برو به **«قدم نصب مشترک»** پایین.

> **می‌خواهی با زیپ نصب کنی ولی آپدیت گیت‌هاب هم داشته باشی؟** بعد از نصب، در
> صفحهٔ «به‌روزرسانی» دکمهٔ **«اتصال به گیت‌هاب»** را بزن (یا در ترمینال:
> `sudo -u www-data php artisan soorin:link-github`). این کار پوشهٔ `.git` را
> می‌سازد و از آن پس «به‌روزرسانی از گیت‌هاب» هم کار می‌کند.

---

## روش ۲ — گرفتن از گیت‌هاب (توصیه‌شده برای به‌روزرسانی خودکار)

> **مهم:** فقط این روش (git clone) امکان «به‌روزرسانی از گیت‌هاب» را داخل خودِ
> برنامه فراهم می‌کند، چون پوشهٔ `.git` روی سرور ساخته می‌شود. روش زیپ این را
> ندارد و فقط با فایل زیپ به‌روزرسانی می‌شود.

### اگر مخزن **public** است (بدون توکن)
هیچ توکنی لازم نیست؛ کافی است:
```bash
apt-get update && apt-get install -y git
git clone https://github.com/soorintec/Soorin_Inventory.git /var/www/soorin-inventory
cd /var/www/soorin-inventory
```
با این حالت، «به‌روزرسانی از گیت‌هاب» داخل برنامه هم **بدون توکن و بدون هیچ
تنظیم اضافه‌ای** کار می‌کند.

### اگر مخزن **private** است (نیاز به توکن)
اول یک **Personal Access Token** بساز: گیت‌هاب → **Settings → Developer settings
→ Personal access tokens → Tokens (classic) → Generate new token** → دسترسی `repo`
→ توکن (`ghp_...`) را کپی کن. سپس با توکنِ داخل URL کلون کن تا به‌روزرسانی بعدی
هم رمز نپرسد:
```bash
apt-get update && apt-get install -y git
git clone https://ghp_abcd1234@github.com/soorintec/Soorin_Inventory.git /var/www/soorin-inventory
cd /var/www/soorin-inventory
```
(به‌جای `ghp_abcd1234` توکن خودت را بگذار.)

سپس برو به **«قدم نصب مشترک»** پایین.

---

## قدم نصب مشترک (برای هر دو روش)

```bash
sudo bash deploy/debian-setup.sh
```
(اگر دامنه داری: `sudo bash deploy/debian-setup.sh anbar.yoursite.com`)

این دستور همه‌چیز را خودکار انجام می‌دهد: نصب **Nginx + PHP 8.4-FPM + MariaDB +
Composer**، ساخت دیتابیس، نصب وابستگی‌ها، ساخت جدول‌ها، و راه‌اندازی سرویس‌ها با
systemd (خودکار بعد از ری‌استارت). وسط کار **ایمیل، نام و گذرواژهٔ مدیر** را از
تو می‌پرسد.

## ورود

مرورگر را باز کن: `http://IP-سرور/admin` و با همان ایمیل/گذرواژه‌ای که دادی وارد شو.

---

## بعد از نصب

- **وارد کردن اکسل انبار:** `sudo -u www-data php artisan inventory:import-anbar مسیر/Anbar.xlsx`
- **پشتیبان‌گیری:** از خود صفحهٔ «پشتیبان‌گیری» برنامه؛ به‌علاوه از VM روی ESXi هم Snapshot بگیر.
- **به‌روزرسانی برنامه:** از صفحهٔ «به‌روزرسانی» داخل خود برنامه (هم از گیت‌هاب و هم با فایل زیپ).
- **همه‌چیز خودکار بعد از ری‌استارت بالا می‌آید** (Nginx + PHP-FPM + MariaDB با systemd).

## SSL / HTTPS

از پنل، بخش **«SSL»** (فقط مدیر) می‌توانی گواهی امنیتی بگیری و https را فعال کنی:

- **سرور داخلی:** گواهی **self-signed** بگیر (HTTPS فعال می‌شود؛ مرورگر بار اول
  هشدار می‌دهد که باید تأیید کنی — چون داخلی است، امن است).
- **سرور عمومی با دامنه:** گواهی رایگان و معتبر **Let's Encrypt** با تمدید خودکار.
- گزینهٔ **«اجبار HTTPS»** هرکس با http وارد شود را به https منتقل می‌کند.

> اسکریپت نصب، «دستیار SSL» را خودش نصب می‌کند. اگر برنامه را قبلاً نصب کرده‌ای،
> یک‌بار در مسیر پروژه این را با root اجرا کن تا بخش SSL فعال شود:
> ```bash
> cd /var/www/soorin-inventory
> sudo bash deploy/install-ssl-helper.sh
> ```
> (خروجی این دستور انگلیسی است.)
