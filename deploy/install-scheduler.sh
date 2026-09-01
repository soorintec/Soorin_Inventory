#!/usr/bin/env bash
#
# نصب/تعمیرِ زمان‌بندِ سورین (systemd timer) — به‌تنهایی و بی‌خطر.
#
# چرا لازم است: بکاپِ خودکار و بررسیِ به‌روزرسانی به «schedule:run»ِ لاراول تکیه
# دارند که باید هر دقیقه اجرا شود. اگر این تایمر نصب نباشد (مثلاً سرور پیش از
# افزوده‌شدنِ آن نصب شده)، هیچ کارِ خودکاری اجرا نمی‌شود. این اسکریپت فقط همین
# تایمر را می‌سازد و روشن می‌کند — چیزِ دیگری روی سرور دست نمی‌زند.
#
# اجرا:  sudo bash deploy/install-scheduler.sh
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "این اسکریپت باید با root/sudo اجرا شود:  sudo bash deploy/install-scheduler.sh" >&2
    exit 1
fi

# مسیرِ برنامه = پوشهٔ والدِ همین اسکریپت (deploy/..)، پس از هرجا اجرا شود درست است.
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ ! -f "$APP_DIR/artisan" ]; then
    echo "artisan در «$APP_DIR» پیدا نشد؛ اسکریپت را از داخلِ پوشهٔ پروژه اجرا کنید." >&2
    exit 1
fi

# مسیرِ php و کاربرِ اجرا (مالکِ فایل‌های پروژه — معمولاً www-data).
PHP_BIN="$(command -v php || echo /usr/bin/php)"
RUN_USER="$(stat -c '%U' "$APP_DIR/artisan")"

echo "- برنامه:   $APP_DIR"
echo "- php:      $PHP_BIN"
echo "- کاربر:    $RUN_USER"

cat > /etc/systemd/system/soorin-scheduler.service <<UNIT
[Unit]
Description=Soorin Laravel scheduler
After=network.target

[Service]
Type=oneshot
User=${RUN_USER}
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} ${APP_DIR}/artisan schedule:run
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
systemctl enable --now soorin-scheduler.timer

echo
echo "✓ زمان‌بند نصب و فعال شد. برای اطمینان:"
echo "    systemctl status soorin-scheduler.timer"
echo "  و پس از یکی‌دو دقیقه، در صفحهٔ «پشتیبان‌گیری» باید چراغِ «زمان‌بندِ سرور» سبز شود."
