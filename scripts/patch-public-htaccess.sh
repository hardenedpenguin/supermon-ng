#!/bin/bash
# Set RewriteBase in public/.htaccess from APP_BASE_PATH
# Usage: patch-public-htaccess.sh <path-to-public/.htaccess> [APP_BASE_PATH]

set -euo pipefail

HTACCESS="${1:?Usage: patch-public-htaccess.sh <public/.htaccess> [APP_BASE_PATH]}"
APP_BASE_PATH="${2:-${APP_BASE_PATH:-/supermon-ng}}"

BASE_PATH="$(echo "$APP_BASE_PATH" | sed 's#/*$##')"
if [ -z "$BASE_PATH" ] || [ "$BASE_PATH" = "/" ]; then
  REWRITE_BASE="/"
else
  REWRITE_BASE="/${BASE_PATH#/}/"
fi

if [ ! -f "$HTACCESS" ]; then
  echo "patch-public-htaccess: file not found: $HTACCESS" >&2
  exit 1
fi

if grep -q '^RewriteBase ' "$HTACCESS"; then
  sed -i "s|^RewriteBase .*|RewriteBase ${REWRITE_BASE}|" "$HTACCESS"
else
  sed -i "/^RewriteEngine On/a RewriteBase ${REWRITE_BASE}" "$HTACCESS"
fi

echo "patch-public-htaccess: RewriteBase ${REWRITE_BASE} (${HTACCESS})"
