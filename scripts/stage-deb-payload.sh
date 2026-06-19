#!/bin/bash
# Stage Supermon-NG files into a Debian package tree ($DESTDIR).
# Called from debian/rules during package build.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTDIR="${1:?Usage: stage-deb-payload.sh DESTDIR}"
APP_DIR="${SUPERMON_INSTALL_DIR:-/var/www/html/supermon-ng}"
STAGE="$DESTDIR$APP_DIR"

echo "stage-deb-payload: staging to $STAGE"

mkdir -p "$STAGE"/{logs,cache,database,public/assets}

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
[ -f "$ROOT/astdb.txt" ] && cp "$ROOT/astdb.txt" "$STAGE/" || true
cp "$ROOT/.env.example" "$STAGE/" 2>/dev/null || true

# Static assets at app root
for f in "$ROOT"/*.{jpg,png,ico}; do
    [ -f "$f" ] && cp "$f" "$STAGE/" || true
done

# user_files (templates; conffiles protect local edits on upgrade)
copy_tree user_files user_files
chmod 755 "$STAGE/user_files/sbin" 2>/dev/null || true
for script in ast_node_status_update.py din ssinfo dvswitch-bridge-restart.sh \
    announce-play.sh announce-install.sh announce-tts.sh announce-delete.sh announce-schedule.sh announce-voice-install.sh; do
    [ -f "$STAGE/user_files/sbin/$script" ] && chmod 755 "$STAGE/user_files/sbin/$script" || true
done
[ -f "$STAGE/user_files/sbin/node_info.ini" ] && chmod 644 "$STAGE/user_files/sbin/node_info.ini" || true
mkdir -p "$STAGE/user_files/mp3"

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
for s in manage_users.php generate_local_allmon.php update.sh version-check.sh \
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
