#!/bin/bash
# Apply APP_BASE_PATH from .env to public/index.html (meta app-base) and public/.htaccess (RewriteBase)
# Usage: configure-app-base-path.sh <app_dir>
# Reads APP_BASE_PATH from environment or <app_dir>/.env

set -euo pipefail

APP_DIR="${1:?Usage: configure-app-base-path.sh <app_dir>}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -f "$APP_DIR/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  source "$APP_DIR/.env" 2>/dev/null || true
  set +a
fi

APP_BASE_PATH="${APP_BASE_PATH:-/supermon-ng}"
export APP_BASE_PATH

INDEX="$APP_DIR/public/index.html"
HTACCESS="$APP_DIR/public/.htaccess"

if [ -f "$INDEX" ]; then
  BASE_PATH="$(echo "$APP_BASE_PATH" | sed 's#/*$##')"
  if [ -z "$BASE_PATH" ]; then
    # Dedicated vhost (APP_BASE_PATH=/): force site root, do not auto-detect /supermon-ng
    META_CONTENT="/"
  else
    META_CONTENT="/${BASE_PATH#/}"
  fi
  sed -i '/<meta name="app-base"/d' "$INDEX"
  sed -i "s|</head>|    <meta name=\"app-base\" content=\"${META_CONTENT}\" />\\n  </head>|" "$INDEX"
  echo "configure-app-base-path: index.html app-base=\"${META_CONTENT}\" (APP_BASE_PATH=${APP_BASE_PATH})"
else
  echo "configure-app-base-path: warning: missing $INDEX" >&2
fi

if [ -f "$HTACCESS" ] && [ -f "$SCRIPT_DIR/patch-public-htaccess.sh" ]; then
  bash "$SCRIPT_DIR/patch-public-htaccess.sh" "$HTACCESS" "$APP_BASE_PATH"
fi
