#!/usr/bin/env bash
# بکاپ شبانه دیتابیس و فایل‌های آپلودشده
# در crontab:  0 2 * * * /var/www/scripts/backup.sh >> /var/log/soorin-backup.log 2>&1
set -e

BACKUP_DIR="$(dirname "$0")/../storage/backups"
KEEP_DAYS=30
STAMP=$(date +%Y-%m-%d_%H%M)

mkdir -p "$BACKUP_DIR"

# shellcheck disable=SC1091
set -a; source "$(dirname "$0")/../.env"; set +a

docker compose exec -T db \
  mariadb-dump -u root -p"${DB_ROOT_PASSWORD}" --single-transaction "${DB_DATABASE}" \
  | gzip > "${BACKUP_DIR}/db_${STAMP}.sql.gz"

tar -czf "${BACKUP_DIR}/files_${STAMP}.tar.gz" -C "$(dirname "$0")/.." storage/app/public 2>/dev/null || true

find "$BACKUP_DIR" -name '*.gz' -mtime +${KEEP_DAYS} -delete

echo "[$(date)] بکاپ انجام شد: db_${STAMP}.sql.gz"
