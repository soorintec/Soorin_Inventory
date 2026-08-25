#!/usr/bin/env bash
#
# soorin-ssl — root helper for managing the Soorin Inventory SSL.
#
# Runs as root (allowed via sudoers for www-data, and only this file). It does:
#   self-signed <CN>            issue and apply a self-signed cert (internal server)
#   letsencrypt <domain> <mail> obtain and apply a Let's Encrypt cert (public server)
#   force-https <on|off>        turn the http->https redirect on/off
#   disable                     remove SSL, keep http only
#   status                      print the current state (key=value)
#
# Security: inputs are validated with strict regex, and `nginx -t` runs before any
# reload; if the generated config is invalid it reverts to the previous one, so the
# site is never taken down.
#
set -euo pipefail

STATE=/etc/soorin-ssl.conf
SS_DIR=/etc/ssl/soorin
NGINX_SITE=/etc/nginx/sites-available/soorin

die() { echo "ERROR: $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "This command must be run as root."
[ -f "$STATE" ] || die "State file ($STATE) is missing; run install-ssl-helper first."

# shellcheck disable=SC1090
. "$STATE"   # APP_ROOT PHP_SOCK SERVER_NAME MODE FORCE DOMAIN EMAIL

CERT=""; KEY=""

save_state() {
    cat > "$STATE" <<EOF
APP_ROOT="$APP_ROOT"
PHP_SOCK="$PHP_SOCK"
SERVER_NAME="$SERVER_NAME"
MODE="$MODE"
FORCE="$FORCE"
DOMAIN="$DOMAIN"
EMAIL="$EMAIL"
EOF
}

valid_domain() { [[ "$1" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$ ]]; }
valid_email()  { [[ "$1" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; }
valid_cn()     { [[ "$1" =~ ^[A-Za-z0-9._-]+$ ]]; }
is_ip()        { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; }

set_cert_paths() {
    case "$MODE" in
        self-signed) CERT="$SS_DIR/cert.pem"; KEY="$SS_DIR/key.pem" ;;
        letsencrypt) CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"; KEY="/etc/letsencrypt/live/$DOMAIN/privkey.pem" ;;
        *)           CERT=""; KEY="" ;;
    esac
}

# Shared app-serving directives. Arg 1: extra lines inside the php block (e.g. HTTPS on)
emit_common() {
    cat <<EOF
    index index.php;
    charset utf-8;
    client_max_body_size 50M;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "same-origin" always;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }

    location ^~ /branding/ {
        add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'" always;
        add_header X-Content-Type-Options "nosniff" always;
        try_files \$uri =404;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        ${1:-}
    }

    location ~ /\.(?!well-known).* { deny all; }
EOF
}

emit_http_block() {
    local name="$1" kind="$2"
    echo "server {"
    echo "    listen 80;"
    echo "    server_name ${name};"
    echo "    root ${APP_ROOT};"
    if [ "$kind" = "redirect" ]; then
        # The Let's Encrypt renewal challenge must still be served on port 80 even when forcing https
        echo "    location ^~ /.well-known/acme-challenge/ { root ${APP_ROOT}; }"
        echo "    location / { return 301 https://\$host\$request_uri; }"
    else
        emit_common
    fi
    echo "}"
}

emit_https_block() {
    local name="$1"
    echo "server {"
    echo "    listen 443 ssl;"
    echo "    http2 on;"
    echo "    server_name ${name};"
    echo "    root ${APP_ROOT};"
    echo "    ssl_certificate ${CERT};"
    echo "    ssl_certificate_key ${KEY};"
    echo "    ssl_protocols TLSv1.2 TLSv1.3;"
    echo "    ssl_prefer_server_ciphers off;"
    emit_common "fastcgi_param HTTPS on;"
    echo "}"
}

write_nginx() {
    local name="${SERVER_NAME:-_}"
    local tmp; tmp="$(mktemp)"

    {
        if [ "$MODE" = "none" ]; then
            emit_http_block "$name" "full"
        else
            if [ "$FORCE" = "on" ]; then
                emit_http_block "$name" "redirect"
            else
                emit_http_block "$name" "full"
            fi
            emit_https_block "$name"
        fi
    } > "$tmp"

    # Back up the current config, replace it, then test nginx
    [ -f "$NGINX_SITE" ] && cp -f "$NGINX_SITE" "${NGINX_SITE}.soorin.bak"
    mv "$tmp" "$NGINX_SITE"

    if ! nginx -t 2>/tmp/soorin-nginx-test; then
        [ -f "${NGINX_SITE}.soorin.bak" ] && mv -f "${NGINX_SITE}.soorin.bak" "$NGINX_SITE"
        cat /tmp/soorin-nginx-test >&2
        die "Invalid nginx config; reverted to the previous version."
    fi

    systemctl reload nginx
}

ensure_certbot() {
    command -v certbot >/dev/null 2>&1 && return 0
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y --no-install-recommends certbot
}

cmd="${1:-status}"
shift || true

case "$cmd" in
    self-signed)
        cn="${1:-}"; [ -n "$cn" ] || die "A name (CN) is required."
        valid_cn "$cn" || die "Invalid name."

        mkdir -p "$SS_DIR"
        if is_ip "$cn"; then san="IP:$cn"; else san="DNS:$cn"; fi

        openssl req -x509 -newkey rsa:2048 -nodes \
            -keyout "$SS_DIR/key.pem" -out "$SS_DIR/cert.pem" \
            -days 3650 -subj "/CN=$cn" -addext "subjectAltName=$san"
        chmod 600 "$SS_DIR/key.pem"

        MODE="self-signed"; SERVER_NAME="$cn"; DOMAIN=""; EMAIL=""
        set_cert_paths; write_nginx; save_state
        echo "OK self-signed $cn"
        ;;

    letsencrypt)
        d="${1:-}"; e="${2:-}"
        [ -n "$d" ] && [ -n "$e" ] || die "Domain and email are required."
        valid_domain "$d" || die "Invalid domain."
        valid_email "$e"  || die "Invalid email."

        ensure_certbot

        # Make sure port 80 serves the webroot challenge for this domain
        SERVER_NAME="$d"
        if [ "$MODE" = "none" ]; then write_nginx; fi

        certbot certonly --webroot -w "$APP_ROOT" -d "$d" \
            --email "$e" --agree-tos -n \
            --deploy-hook "systemctl reload nginx" \
            || die "Failed to obtain a Let's Encrypt certificate (the domain must reach this server from the internet on port 80)."

        MODE="letsencrypt"; DOMAIN="$d"; EMAIL="$e"
        set_cert_paths; write_nginx; save_state
        echo "OK letsencrypt $d"
        ;;

    force-https)
        v="${1:-}"; [ "$v" = "on" ] || [ "$v" = "off" ] || die "Use on or off."
        [ "$MODE" != "none" ] || die "Issue a certificate first."
        FORCE="$v"
        set_cert_paths; write_nginx; save_state
        echo "OK force=$v"
        ;;

    disable)
        MODE="none"; FORCE="off"
        write_nginx; save_state
        echo "OK disabled"
        ;;

    status)
        echo "installed=1"
        echo "mode=$MODE"
        echo "server_name=$SERVER_NAME"
        echo "domain=$DOMAIN"
        echo "email=$EMAIL"
        echo "force=$FORCE"
        set_cert_paths
        if [ -n "$CERT" ] && [ -f "$CERT" ]; then
            exp="$(openssl x509 -enddate -noout -in "$CERT" 2>/dev/null | cut -d= -f2 || true)"
            echo "expiry=$exp"
        fi
        # Is certbot auto-renewal enabled?
        if systemctl is-enabled certbot.timer >/dev/null 2>&1; then
            echo "auto_renew=1"
        else
            echo "auto_renew=0"
        fi
        ;;

    *)
        die "Unknown command: $cmd"
        ;;
esac
