#!/usr/bin/env bash
#
# Automatic installer for Soorin Inventory on Debian 13 (Trixie).
#
# On a fresh Debian server (with root access and internet) this installs and
# configures the whole stack: Nginx + PHP 8.4-FPM + MariaDB + the app itself.
# At the end you just open the server address in a browser and sign in.
#
# Usage (from inside the app folder):
#     cd /var/www/soorin-inventory
#     sudo bash deploy/debian-setup.sh                # server_name = _  (any address)
#     sudo bash deploy/debian-setup.sh anbar.example.com  # with a domain
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this script with sudo or as root." >&2
    exit 1
fi

# ریشهٔ پروژه = پوشهٔ والدِ همین اسکریپت (اسکریپت داخل deploy/ است)
APP_DIR="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
PHP_VER="8.4"
DB_NAME="soorin_inventory"
DB_USER="soorin"
# رمزِ دیتابیس: اگر .env از قبل هست و رمز دارد، همان را نگه می‌داریم تا اجرای
# دوبارهٔ اسکریپت رمز را عوض (و اتصال را خراب) نکند؛ وگرنه یک رمزِ تازه می‌سازیم.
if [ -f "$APP_DIR/.env" ] && grep -qE '^DB_PASSWORD=.+' "$APP_DIR/.env"; then
    DB_PASS="$(grep -E '^DB_PASSWORD=' "$APP_DIR/.env" | head -1 | cut -d= -f2- | tr -d '\042\047')"
else
    DB_PASS="$(openssl rand -hex 16 2>/dev/null || head -c 16 /dev/urandom | xxd -p)"
fi
SERVER_NAME="${1:-_}"

echo "- Installing system packages ..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y --no-install-recommends \
    nginx mariadb-server unzip git curl ca-certificates openssl xxd \
    smbclient \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-zip" \
    "php${PHP_VER}-bcmath" "php${PHP_VER}-curl" "php${PHP_VER}-gd" "php${PHP_VER}-intl"
# smbclient لازم است تا «بکاپ روی پوشهٔ شبکهٔ SMB» با نام‌کاربری/رمز کار کند.

if ! command -v composer >/dev/null 2>&1; then
    echo "- Installing Composer ..."
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

echo "- Installing PHP dependencies ..."
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "- Setting up the database ..."
systemctl enable --now mariadb
# کاربر برای هر دو میزبان ساخته می‌شود: 'localhost' (سوکتِ یونیکس) و '127.0.0.1'
# (TCP). .env روی DB_HOST=127.0.0.1 است؛ روی سرورهایی که name-resolutionِ مای‌اس‌کیو‌ال
# خاموش است (skip-name-resolve)، اتصالِ 127.0.0.1 با کاربرِ @localhost جور نمی‌شود و
# «Access denied» می‌دهد. داشتنِ هر دو میزبان این را قطعی می‌کند.
# ALTER هم می‌آید تا اگر کاربر از قبل بود، رمزِ هر دو با .env هماهنگ شود.
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "- Creating .env file ..."
IP="$(hostname -I | awk '{print $1}')"
if [ "$SERVER_NAME" = "_" ]; then APP_URL="http://${IP}"; else APP_URL="http://${SERVER_NAME}"; fi
[ -f .env ] || cp .env.example .env

# مقدارها از راه متغیر محیطی به PHP داده می‌شوند تا دردسر نقل‌قول نباشد
APP_URL="$APP_URL" DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" php -r '
$p=".env"; $e=file_get_contents($p);
$vals=[
 "APP_ENV"=>"production","APP_DEBUG"=>"false","APP_URL"=>getenv("APP_URL"),
 "DB_CONNECTION"=>"mysql","DB_HOST"=>"127.0.0.1","DB_PORT"=>"3306",
 "DB_DATABASE"=>getenv("DB_NAME"),"DB_USERNAME"=>getenv("DB_USER"),"DB_PASSWORD"=>getenv("DB_PASS"),
 "CACHE_STORE"=>"file","SESSION_DRIVER"=>"file","QUEUE_CONNECTION"=>"sync",
];
foreach($vals as $k=>$v){
 $l=$k."=".$v; $pat="/^".preg_quote($k,"/")."=.*$/m";
 $e=preg_match($pat,$e)?preg_replace($pat,$l,$e):rtrim($e,"\n")."\n".$l."\n";
}
file_put_contents($p,$e);
'
php artisan key:generate --force

echo "- Setting file permissions ..."
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;

# پاک‌سازیِ کشِ پیکربندی تا رمزِ تازهٔ .env قطعاً خوانده شود — نه نسخهٔ کش‌شدهٔ قدیمی.
# اگر جایی config:cache اجرا شده باشد، رمزِ قدیمی در کش می‌ماند و در اجرای دوباره
# «Access denied» می‌دهد (و در نتیجه ضربانِ زمان‌بند نوشته نمی‌شود و چراغ قرمز می‌ماند).
sudo -u www-data php artisan optimize:clear >/dev/null 2>&1 || true

echo "- Creating tables and the admin user ..."
read -rp "  Admin email: " ADMIN_EMAIL
read -rp "  Admin name [System Administrator]: " ADMIN_NAME; ADMIN_NAME="${ADMIN_NAME:-System Administrator}"
read -rsp "  Admin password (min 8 chars): " ADMIN_PASS; echo
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan soorin:install --name="$ADMIN_NAME" --email="$ADMIN_EMAIL" --password="$ADMIN_PASS"
sudo -u www-data php artisan storage:link || true

echo "- Configuring Nginx ..."
cat > /etc/nginx/sites-available/soorin <<NGINX
server {
    listen 80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    # سرآیندهای امنیتی پایه
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "same-origin" always;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }

    # لوگوهای آپلودیِ مدیر. اگر کسی SVG آلوده آپلود کند، این سرآیند اجرای اسکریپت
    # را هنگام باز شدنِ مستقیمِ فایل خنثی می‌کند (نمایش با <img> اصلاً اسکریپت اجرا نمی‌کند).
    location ^~ /branding/ {
        add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'" always;
        add_header X-Content-Type-Options "nosniff" always;
        try_files \$uri =404;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/soorin /etc/nginx/sites-enabled/soorin
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VER}-fpm" nginx
systemctl reload nginx

# زمان‌بندِ لاراول (systemd timer) — برای بررسیِ روزانهٔ نسخهٔ جدید و کارهای دوره‌ای.
# اگر این نباشد هم برنامه با defer() روزی یک‌بار خودش بررسی می‌کند، ولی این مطمئن‌تر است.
cat > /etc/systemd/system/soorin-scheduler.service <<UNIT
[Unit]
Description=Soorin Laravel scheduler
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan schedule:run
UNIT

cat > /etc/systemd/system/soorin-scheduler.timer <<UNIT
[Unit]
Description=Run Soorin Laravel scheduler every minute

[Timer]
OnCalendar=*:0/1
Persistent=true

[Install]
WantedBy=timers.target
UNIT

systemctl daemon-reload
systemctl enable --now soorin-scheduler.timer || echo "  (scheduler timer enable failed; not critical)"

# SSL helper: lets the admin issue certificates and enable https from the panel's "SSL" section
if [ -f "$APP_DIR/deploy/install-ssl-helper.sh" ]; then
    echo "- Installing SSL helper ..."
    bash "$APP_DIR/deploy/install-ssl-helper.sh" || echo "  (SSL helper install failed; run it manually later.)"
fi

# باز کردن پورت https اگر فایروال ufw فعال باشد
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow 80/tcp  || true
    ufw allow 443/tcp || true
fi

# ---------------------------------------------------------------------------
# Post-install self-check — همان چیزهایی که اگر خراب باشند، چراغِ «زمان‌بندِ
# سرور» در صفحهٔ پشتیبان‌گیری قرمز می‌شود. بهتر است همین‌جا معلوم شود تا کاربر
# بعداً سردرگم نشود.
# ---------------------------------------------------------------------------
echo "- Verifying installation ..."

# ۱) آیا برنامه (همان کاربری که زمان‌بند با آن می‌دود) به دیتابیس وصل می‌شود؟
if sudo -u www-data php "$APP_DIR/artisan" migrate:status >/dev/null 2>&1; then
    DB_OK="yes"
else
    DB_OK="no"
fi

# ۲) آیا تایمرِ زمان‌بند فعال است؟
if systemctl is-active --quiet soorin-scheduler.timer; then
    TIMER_OK="yes"
else
    TIMER_OK="no"
fi

# ۳) یک اجرای دستیِ زمان‌بند تا همین حالا ضربان نوشته شود (چراغ زودتر سبز شود).
sudo -u www-data php "$APP_DIR/artisan" schedule:run >/dev/null 2>&1 || true

echo
echo "======================================================================"
if [ "$DB_OK" = "yes" ] && [ "$TIMER_OK" = "yes" ]; then
    echo "  Installation complete.  (database: OK, scheduler: OK)"
else
    echo "  Installation finished WITH WARNINGS:"
    [ "$DB_OK" = "yes" ]    || echo "   ✗ The app cannot connect to the database — check .env DB_* and the MySQL user."
    [ "$TIMER_OK" = "yes" ] || echo "   ✗ The scheduler timer is not active — run:  sudo bash deploy/install-scheduler.sh"
    echo "   NOTE: while either is broken, automatic backups won't run and the"
    echo "         'server scheduler' light on the Backups page stays red."
fi
echo "  Open in your browser:   ${APP_URL}/admin"
echo "  Sign in with the email and password you just entered."
echo ""
echo "  (Database created — keep for your records):"
echo "     database: ${DB_NAME}   user: ${DB_USER}   password: ${DB_PASS}"
echo "======================================================================"
