# استقرار روی سرور دبیان (ESXi / شبکه داخلی)

دو کار لازم است: کد را روی سرور بگذاری، و یک دستور بزنی که همه‌چیز خودکار نصب شود.

> پیش‌نیاز: سرور Debian 13، دسترسی root (یا کاربر با sudo)، و **اینترنت**
> برای دانلود بسته‌ها. اگر سرور کاملاً آفلاین است، بگو تا بستهٔ آفلاین بسازم.

## قدم ۱: کد را روی سرور بگذار

**راه ساده (فایل زیپ):**
1. فایل `soorin-inventory.zip` را (که برایت فرستادم) با WinSCP یا دستور زیر
   روی سرور آپلود کن:
   ```
   pscp soorin-inventory.zip root@IP-سرور:/root/
   ```
2. در PuTTY، اکسترکت کن:
   ```
   apt-get update && apt-get install -y unzip
   mkdir -p /var/www/soorin-inventory
   unzip /root/soorin-inventory.zip -d /var/www/soorin-inventory
   cd /var/www/soorin-inventory
   ```

**یا از گیت‌هاب (اگر توکن دسترسی داری):**
```
apt-get update && apt-get install -y git
git clone <آدرس-مخزن> /var/www/soorin-inventory
cd /var/www/soorin-inventory
```

## قدم ۲: اجرای نصب خودکار

```
sudo bash deploy/debian-setup.sh
```
(اگر دامنه داری: `sudo bash deploy/debian-setup.sh anbar.yoursite.com`)

این دستور: Nginx، PHP 8.4، MariaDB و Composer را نصب می‌کند، دیتابیس می‌سازد،
وابستگی‌ها را نصب می‌کند، جدول‌ها را می‌سازد، و **ایمیل/نام/گذرواژهٔ مدیر را از
تو می‌پرسد**. آخر کار آدرس ورود را نشان می‌دهد.

## قدم ۳: ورود

مرورگر را باز کن: `http://IP-سرور/admin` و با همان ایمیل/گذرواژه‌ای که دادی
وارد شو. تمام.

---

- برای وارد کردن فایل اکسل انبار: `sudo -u www-data php artisan inventory:import-anbar مسیر/Anbar.xlsx`
- بکاپ‌گیری خودکار روزانه (اختیاری): یک cron برای `php artisan …` یا از خود
  صفحهٔ «پشتیبان‌گیری» برنامه استفاده کن؛ به‌علاوه از VM روی ESXi هم Snapshot بگیر.
- همه‌چیز با systemd خودکار بعد از ری‌استارت بالا می‌آید (Nginx + PHP-FPM + MariaDB).
