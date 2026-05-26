#!/bin/bash
# Install production PHP dependencies as www-data with a dedicated Composer cache.
# Avoids "Cannot create cache directory /var/www/.cache/composer" when www-data
# has no writable home (common on Apache deployments).
#
# Usage: composer-install-production.sh [app_dir]

set -euo pipefail

APP_DIR="${1:-/var/www/html/supermon-ng}"
WWW_USER="${WWW_USER:-www-data}"
WWW_GROUP="${WWW_GROUP:-www-data}"
COMPOSER_CACHE_ROOT="${SUPERMON_COMPOSER_CACHE_ROOT:-/var/cache/supermon-ng}"

if [ ! -f "$APP_DIR/composer.json" ]; then
    echo "composer-install-production: composer.json not found in $APP_DIR" >&2
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "composer-install-production: composer not found in PATH" >&2
    exit 1
fi

COMPOSER_HOME="${COMPOSER_CACHE_ROOT}/home"
COMPOSER_CACHE_DIR="${COMPOSER_CACHE_ROOT}/files"

mkdir -p "$COMPOSER_HOME" "$COMPOSER_CACHE_DIR"
chown -R "${WWW_USER}:${WWW_GROUP}" "$COMPOSER_CACHE_ROOT"
chmod 755 "$COMPOSER_CACHE_ROOT"
find "$COMPOSER_CACHE_ROOT" -type d -exec chmod 775 {} \;
find "$COMPOSER_CACHE_ROOT" -type f -exec chmod 664 {} \; 2>/dev/null || true

cd "$APP_DIR"

echo "Composer: COMPOSER_HOME=${COMPOSER_HOME}"
echo "Composer: COMPOSER_CACHE_DIR=${COMPOSER_CACHE_DIR}"

sudo -u "$WWW_USER" env \
    HOME="$COMPOSER_HOME" \
    COMPOSER_HOME="$COMPOSER_HOME" \
    COMPOSER_CACHE_DIR="$COMPOSER_CACHE_DIR" \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_DISABLE_XDEBUG_WARN=1 \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress
