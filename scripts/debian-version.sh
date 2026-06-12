#!/bin/bash
# Print Debian upstream version from includes/common.inc (e.g. 4.2.1).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
grep -oE 'V[0-9]+\.[0-9]+\.[0-9]+' "$ROOT/includes/common.inc" | head -1 | tr -d 'V'
