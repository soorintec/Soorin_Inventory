#!/usr/bin/env bash
#
# Install the Soorin SSL helper on a Debian server.
#
# Copies soorin-ssl.sh to /usr/local/bin/soorin-ssl (owned by root, not writable
# by www-data) and adds a locked-down sudoers rule so www-data may run ONLY this
# one command as root. This lets the app panel (which runs as www-data) manage SSL
# without the app itself being root.
#
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Please run this script with sudo/root." >&2; exit 1; }

SRC="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)/soorin-ssl.sh"
[ -f "$SRC" ] || { echo "soorin-ssl.sh was not found next to this script." >&2; exit 1; }

echo "- Installing helper at /usr/local/bin/soorin-ssl ..."
install -o root -g root -m 0755 "$SRC" /usr/local/bin/soorin-ssl

echo "- Adding restricted sudoers rule (this command only, www-data only) ..."
RULE_FILE=/etc/sudoers.d/soorin-ssl
echo 'www-data ALL=(root) NOPASSWD: /usr/local/bin/soorin-ssl' > "$RULE_FILE"
chmod 0440 "$RULE_FILE"
visudo -cf "$RULE_FILE" >/dev/null

mkdir -p /etc/ssl/soorin

if [ ! -f /etc/soorin-ssl.conf ]; then
    echo "- Creating state file /etc/soorin-ssl.conf ..."
    ROOT="$(grep -m1 -oP '(?<=root )\S+' /etc/nginx/sites-available/soorin 2>/dev/null | tr -d ';' || true)"
    [ -n "$ROOT" ] || ROOT=/var/www/soorin-inventory/public
    SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
    [ -n "$SOCK" ] || SOCK=/run/php/php8.4-fpm.sock
    NAME="$(grep -m1 -oP '(?<=server_name )\S+' /etc/nginx/sites-available/soorin 2>/dev/null | tr -d ';' || true)"
    [ -n "$NAME" ] || NAME=_

    cat > /etc/soorin-ssl.conf <<EOF
APP_ROOT="$ROOT"
PHP_SOCK="$SOCK"
SERVER_NAME="$NAME"
MODE="none"
FORCE="off"
DOMAIN=""
EMAIL=""
EOF
fi

echo "OK: SSL helper installed. The 'SSL' section in the panel is now available."
