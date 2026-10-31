#!/bin/bash
# Stage Supermon-NG files into a Debian package tree ($DESTDIR).
# Called from debian/rules during package build.
#
# Layout: code in APP_DIR (/usr/share/supermon-ng), configuration in CONF_DIR
# (/etc/supermon-ng), state in STATE_DIR (/var/lib/supermon-ng) and logs in
# LOG_DIR (/var/log/supermon-ng). APP_DIR carries symlinks to the other three
# so the app's relative paths (user_files/, logs/, cache/, database/,
# astdb.txt, .env) keep working. postinst creates the STATE_DIR/LOG_DIR targets.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTDIR="${1:?Usage: stage-deb-payload.sh DESTDIR}"
APP_DIR="${SUPERMON_INSTALL_DIR:-/usr/share/supermon-ng}"
CONF_DIR="${SUPERMON_CONF_DIR:-/etc/supermon-ng}"
STATE_DIR="${SUPERMON_STATE_DIR:-/var/lib/supermon-ng}"
LOG_DIR="${SUPERMON_LOG_DIR:-/var/log/supermon-ng}"
STAGE="$DESTDIR$APP_DIR"
CONF_STAGE="$DESTDIR$CONF_DIR"

echo "stage-deb-payload: staging to $STAGE (config: $CONF_STAGE)"

mkdir -p "$STAGE/public/assets" "$CONF_STAGE"

copy_tree() {
    local src="$1" dst="$2"
    if [ -d "$ROOT/$src" ]; then
        cp -a "$ROOT/$src/." "$STAGE/$dst/"
    fi
}

# Core application
cp "$ROOT/index.php" "$STAGE/"
copy_tree includes includes
copy_tree src src
copy_tree config config
copy_tree bin bin
cp "$ROOT/composer.json" "$STAGE/"
[ -f "$ROOT/composer.lock" ] && cp "$ROOT/composer.lock" "$STAGE/" || true
[ -f "$ROOT/.htaccess" ] && cp "$ROOT/.htaccess" "$STAGE/" || true
# Seed copied to $STATE_DIR/astdb.txt by postinst when missing
[ -f "$ROOT/astdb.txt" ] && cp "$ROOT/astdb.txt" "$STAGE/astdb.txt.dist" || true
cp "$ROOT/.env.example" "$STAGE/" 2>/dev/null || true

# Static assets at app root
for f in "$ROOT"/*.{jpg,png,ico}; do
    [ -f "$f" ] && cp "$f" "$STAGE/" || true
done

# Privileged helper scripts (sudoers and systemd reference these paths)
copy_tree sbin sbin
chmod 755 "$STAGE/sbin" "$STAGE"/sbin/* 2>/dev/null || true

# user_files: everything under /etc becomes a conffile (dh_installdeb), so local
# edits survive upgrades. Announcement audio lives in $STATE_DIR/mp3 instead.
mkdir -p "$CONF_STAGE/user_files"
cp -a "$ROOT/user_files/." "$CONF_STAGE/user_files/"
rm -rf "$CONF_STAGE/user_files/mp3"
find "$CONF_STAGE/user_files" -type f -exec chmod 644 {} +
ln -s "$STATE_DIR/mp3" "$CONF_STAGE/user_files/mp3"

ln -s "$CONF_DIR/user_files" "$STAGE/user_files"
ln -s "$CONF_DIR/.env" "$STAGE/.env"
ln -s "$LOG_DIR" "$STAGE/logs"
ln -s "$STATE_DIR/cache" "$STAGE/cache"
ln -s "$STATE_DIR/database" "$STAGE/database"
ln -s "$STATE_DIR/astdb.txt" "$STAGE/astdb.txt"

# public/ (non-asset static files; assets come from frontend dist)
mkdir -p "$STAGE/public"
for f in index.php index.html .htaccess; do
    [ -f "$ROOT/public/$f" ] && cp "$ROOT/public/$f" "$STAGE/public/" || true
done

# Frontend build (requires devDependencies; only dist/ is shipped)
if [ -d "$ROOT/frontend/dist" ] && [ -f "$ROOT/frontend/dist/index.html" ] && [ "${DEB_SKIP_FRONTEND_BUILD:-0}" = "1" ]; then
    echo "stage-deb-payload: using existing frontend/dist (DEB_SKIP_FRONTEND_BUILD=1)"
    cp -a "$ROOT/frontend/dist/." "$STAGE/public/"
elif [ -d "$ROOT/frontend" ] && [ -f "$ROOT/frontend/package.json" ]; then
    if command -v node >/dev/null && command -v npm >/dev/null; then
        echo "stage-deb-payload: building frontend..."
        (cd "$ROOT/frontend" && npm ci 2>/dev/null || npm install)
        (cd "$ROOT/frontend" && npm run build)
        cp -a "$ROOT/frontend/dist/." "$STAGE/public/"
    elif [ -d "$ROOT/frontend/dist" ]; then
        echo "stage-deb-payload: using existing frontend/dist"
        cp -a "$ROOT/frontend/dist/." "$STAGE/public/"
    else
        echo "stage-deb-payload: ERROR: no frontend/dist and npm unavailable" >&2
        exit 1
    fi
elif [ -d "$ROOT/frontend/dist" ]; then
    cp -a "$ROOT/frontend/dist/." "$STAGE/public/"
fi

# PHP vendor (production)
if command -v composer >/dev/null 2>&1; then
    echo "stage-deb-payload: composer install --no-dev..."
    (cd "$STAGE" && COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --ignore-platform-reqs)
else
    echo "stage-deb-payload: WARNING: composer not found; package will need postinst composer install" >&2
fi

# Admin scripts shipped with the app
mkdir -p "$STAGE/scripts"
for s in manage_users.php generate_local_allmon.php version-check.sh \
    generate-apache-template.sh configure-apache.sh patch-public-htaccess.sh \
    configure-app-base-path.sh composer-install-production.sh database-auto-update.php \
    supermon_unified_file_editor.sh; do
    [ -f "$ROOT/scripts/$s" ] && cp "$ROOT/scripts/$s" "$STAGE/scripts/" || true
done
chmod +x "$STAGE/scripts/"*.sh 2>/dev/null || true

# Documentation
mkdir -p "$STAGE/docs"
[ -f "$ROOT/docs/DEBIAN.md" ] && cp "$ROOT/docs/DEBIAN.md" "$STAGE/docs/"
[ -f "$ROOT/README.md" ] && cp "$ROOT/README.md" "$STAGE/" || true

echo "stage-deb-payload: done"
