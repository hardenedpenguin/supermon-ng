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
echo "build-deb: building supermon-ng ${VERSION}-1 ..."

export NODE_STATUS_INTERVAL_MINUTES="${NODE_STATUS_INTERVAL_MINUTES:-5}"

# Reuse frontend/dist when present (full rebuild: DEB_SKIP_FRONTEND_BUILD=0 ./scripts/build-deb.sh)
if [ -f "$ROOT/frontend/dist/index.html" ] && [ "${DEB_SKIP_FRONTEND_BUILD:-1}" = "1" ]; then
    export DEB_SKIP_FRONTEND_BUILD=1
    echo "build-deb: using existing frontend/dist (set DEB_SKIP_FRONTEND_BUILD=0 to rebuild)"
fi

# Sync changelog version if needed (best-effort)
if grep -q "^supermon-ng (${VERSION}-1)" debian/changelog 2>/dev/null; then
    :
else
    echo "build-deb: note: debian/changelog may not match ${VERSION}-1" >&2
fi

dpkg-buildpackage -us -uc -b

DEB="../supermon-ng_${VERSION}-1_all.deb"
if [ -f "$DEB" ]; then
    echo ""
    echo "Built: $DEB ($(du -h "$DEB" | cut -f1))"
    ls -la "$DEB"
else
    echo "build-deb: expected $DEB not found; check dpkg-buildpackage output" >&2
    exit 1
fi
