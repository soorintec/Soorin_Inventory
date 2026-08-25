#!/usr/bin/env bash
# نصب اولیه روی سرور اوبونتو (Docker)
set -e

echo "==> بررسی فایل .env"
if [ ! -f .env ]; then
  cp .env.example .env
  echo "    .env ساخته شد — قبل از ادامه رمزهای دیتابیس را تنظیم کنید."
  read -p "    پس از ویرایش .env کلید Enter را بزنید..."
fi

echo "==> بالا آوردن کانتینرها"
docker compose up -d --build

echo "==> نصب وابستگی‌ها"
docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> ساخت کلید برنامه"
docker compose exec -T app php artisan key:generate --force

echo "==> ساخت جدول‌های دیتابیس"
docker compose exec -T app php artisan migrate --force --seed

echo "==> بهینه‌سازی"
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan optimize

echo ""
echo "نصب کامل شد."
echo "  پنل مدیریت : http://localhost:${APP_PORT:-8080}"
echo "  phpMyAdmin : http://localhost:${PMA_PORT:-8081}"
