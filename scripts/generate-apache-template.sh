#!/bin/bash
# Generate apache-config-template.conf for install.sh / update.sh
# APP_BASE_PATH=/  → dedicated vhost (DocumentRoot = app public, /api, /ws)
# APP_BASE_PATH=/supermon-ng → subdirectory under shared /var/www/html

set -euo pipefail

OUTPUT="${1:?Usage: generate-apache-template.sh <output-file>}"
APP_DIR="${APP_DIR:-/var/www/html/supermon-ng}"
APP_BASE_PATH="${APP_BASE_PATH:-/supermon-ng}"
SERVER_NAME="${SUPERMON_SERVER_NAME:-}"
SSL_CERT_NAME="${SSL_CERT_NAME:-}"

# Normalize: empty or / means root
BASE_PATH="$(echo "$APP_BASE_PATH" | sed 's#/*$##')"
if [ -z "$BASE_PATH" ] || [ "$BASE_PATH" = "/" ]; then
  BASE_MODE="root"
  BASE_PATH=""
else
  BASE_MODE="subdir"
  BASE_PATH="/${BASE_PATH#/}"
fi

write_root_vhost() {
  local port="$1"
  local ssl_block="$2"
  local server_line=""
  if [ -n "$SERVER_NAME" ]; then
    server_line="    ServerName ${SERVER_NAME}"
  fi

  cat << APACHE_EOF
<VirtualHost *:${port}>
${server_line}
    DocumentRoot ${APP_DIR}/public

    ProxyPreserveHost On

    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api

    ProxyPass "/ws/" "ws://localhost:8105/ws/" upgrade=websocket
    ProxyPassReverse "/ws/" "ws://localhost:8105/ws/"

    Alias /user_files ${APP_DIR}/user_files

    <Directory "${APP_DIR}/public">
        AllowOverride All
        Require all granted
        DirectoryIndex index.html index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteRule ^ index.html [QSA,L]
    </Directory>

    <Directory "${APP_DIR}/user_files">
        AllowOverride All
        Require all granted
    </Directory>

    # Legacy subdirectory URLs after APP_BASE_PATH=/
    RedirectMatch 301 ^/supermon-ng/?(.*)$ /\$1

    ErrorLog \${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog \${APACHE_LOG_DIR}/supermon-ng_access.log combined
${ssl_block}
</VirtualHost>
APACHE_EOF
}

write_subdir_vhost() {
  local port="$1"
  local ssl_block="$2"
  local prefix="${BASE_PATH}"

  cat << APACHE_EOF
<VirtualHost *:${port}>
    DocumentRoot /var/www/html

    ProxyPreserveHost On

    ProxyPass ${prefix}/api http://localhost:8000/api
    ProxyPassReverse ${prefix}/api http://localhost:8000/api

    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteCond %{HTTP:Connection} =Upgrade [NC]
    RewriteRule ^${prefix}/ws/(.+)\$ ws://localhost:8105${prefix}/ws/\$1 [P,L]
    ProxyPassReverse ${prefix}/ws/ ws://localhost:8105${prefix}/ws/

    Alias ${prefix} ${APP_DIR}/public
    Alias ${prefix}/user_files ${APP_DIR}/user_files

    # Install tree lives under DocumentRoot; block direct URL access except via Alias to public/
    <Directory "${APP_DIR}">
        Require all denied
    </Directory>

    <Directory "${APP_DIR}/public">
        AllowOverride All
        Require all granted
        DirectoryIndex index.html index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.html [QSA,L]
    </Directory>

    <Directory "${APP_DIR}/user_files">
        AllowOverride All
        Require all granted
    </Directory>

    <Directory "/var/www/html">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/supermon-ng_error.log
    CustomLog \${APACHE_LOG_DIR}/supermon-ng_access.log combined
${ssl_block}
</VirtualHost>
APACHE_EOF
}

SSL_HTTP=''

# Prefer Certbot/Let's Encrypt when present; fall back to Debian snakeoil for fresh installs
detect_letsencrypt_cert_name() {
  if [ -n "$SSL_CERT_NAME" ] && [ -f "/etc/letsencrypt/live/${SSL_CERT_NAME}/fullchain.pem" ]; then
    echo "$SSL_CERT_NAME"
    return 0
  fi
  if [ -n "$SERVER_NAME" ] && [ -f "/etc/letsencrypt/live/${SERVER_NAME}/fullchain.pem" ]; then
    echo "$SERVER_NAME"
    return 0
  fi
  for live_dir in /etc/letsencrypt/live/*/; do
    [ -d "$live_dir" ] || continue
    local name
    name="$(basename "$live_dir")"
    [ "$name" != "README" ] || continue
    if [ -f "${live_dir}fullchain.pem" ]; then
      echo "$name"
      return 0
    fi
  done
  return 1
}

LE_CERT="$(detect_letsencrypt_cert_name || true)"
if [ -n "$LE_CERT" ] && [ -f "/etc/letsencrypt/options-ssl-apache.conf" ]; then
  SSL_HTTPS="
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${LE_CERT}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${LE_CERT}/privkey.pem
"
else
  SSL_HTTPS='
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
    SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
'
fi

# :443 only — ProxyPass to localhost:8000 does not set HTTPS; PHP needs these for Secure cookies and wss://
SSL_HTTPS="${SSL_HTTPS}
    RequestHeader set X-Forwarded-Proto \"https\"
    RequestHeader set X-Forwarded-Port \"443\""
if [ -n "$SERVER_NAME" ]; then
  SSL_HTTPS="${SSL_HTTPS}
    Header always set Strict-Transport-Security \"max-age=31536000\""
fi

{
  echo "# Supermon-NG Apache configuration (APP_BASE_PATH=${APP_BASE_PATH:-/supermon-ng})"
  echo "# Generated by scripts/generate-apache-template.sh"
  echo

  if [ "$BASE_MODE" = "root" ]; then
    if [ -n "$SERVER_NAME" ]; then
      cat << APACHE_EOF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    RewriteEngine On
    RewriteRule ^ https://${SERVER_NAME}%{REQUEST_URI} [R=301,L,NE]
</VirtualHost>
APACHE_EOF
      echo
      write_root_vhost 443 "$SSL_HTTPS"
    else
      write_root_vhost 80 ""
      echo
      write_root_vhost 443 "$SSL_HTTPS"
    fi
  else
    write_subdir_vhost 80 ""
    echo
    write_subdir_vhost 443 "$SSL_HTTPS"
  fi
} > "$OUTPUT"

if [ -n "$LE_CERT" ]; then
  echo "Wrote Apache template (${BASE_MODE} mode, TLS cert: ${LE_CERT}) to $OUTPUT"
else
  echo "Wrote Apache template (${BASE_MODE} mode, TLS: snakeoil) to $OUTPUT"
fi
