#!/bin/bash
# Build supermon-ng Debian package (binary-only, no signing).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v dpkg-buildpackage >/dev/null 2>&1; then
    echo "build-deb: install build tools: apt-get install -y debhelper dh-systemd composer npm nodejs php-cli php-xml php-mbstring php-curl php-sqlite3" >&2
    exit 1
fi

VERSION="$(./scripts/debian-version.sh)"
DEB_REVISION="$(grep -m1 '^supermon-ng (' debian/changelog | sed -E 's/^supermon-ng \([^)]+\-([0-9]+)\).*/\1/')"
DEB_REVISION="${DEB_REVISION:-1}"
FULL_VERSION="${VERSION}-${DEB_REVISION}"
echo "build-deb: building supermon-ng ${FULL_VERSION} ..."

export NODE_STATUS_INTERVAL_MINUTES="${NODE_STATUS_INTERVAL_MINUTES:-5}"

# Rebuild frontend by default; reuse existing dist only when DEB_SKIP_FRONTEND_BUILD=1
if [ -f "$ROOT/frontend/dist/index.html" ] && [ "${DEB_SKIP_FRONTEND_BUILD:-0}" = "1" ]; then
    export DEB_SKIP_FRONTEND_BUILD=1
    echo "build-deb: using existing frontend/dist (DEB_SKIP_FRONTEND_BUILD=1)"
fi

# Sync changelog version if needed (best-effort)
if grep -q "^supermon-ng (${FULL_VERSION})" debian/changelog 2>/dev/null; then
    :
else
    echo "build-deb: note: debian/changelog may not match ${FULL_VERSION}" >&2
fi

dpkg-buildpackage -us -uc -b

DEB="../supermon-ng_${FULL_VERSION}_all.deb"
if [ -f "$DEB" ]; then
    echo ""
    echo "Built: $DEB ($(du -h "$DEB" | cut -f1))"
    ls -la "$DEB"
else
    echo "build-deb: expected $DEB not found; check dpkg-buildpackage output" >&2
    exit 1
fi
