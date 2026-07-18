#!/bin/bash
# Play an announcement on an AllStar node via Asterisk.
# Invoked by supermon-ng via sudo -n.

set -euo pipefail

NODE=""
SCOPE="local"
MODE="polite"
FILE=""

usage() {
    echo "Usage: $0 --node NODE --scope local|global --mode polite|priority --file announcements/name" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --node) NODE="${2:-}"; shift 2 ;;
        --scope) SCOPE="${2:-}"; shift 2 ;;
        --mode) MODE="${2:-}"; shift 2 ;;
        --file) FILE="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$NODE" && -n "$FILE" ]] || usage
[[ "$NODE" =~ ^[0-9]+$ ]] || { echo "Invalid node" >&2; exit 1; }
[[ "$SCOPE" == "local" || "$SCOPE" == "global" ]] || { echo "Invalid scope" >&2; exit 1; }
[[ "$MODE" == "polite" || "$MODE" == "priority" ]] || { echo "Invalid mode" >&2; exit 1; }
[[ "$FILE" =~ ^announcements/[a-zA-Z0-9._-]+$ ]] || { echo "Invalid file path" >&2; exit 1; }

ASTERISK=/usr/sbin/asterisk
MAX_WAIT=300
CHECK_INTERVAL=1
TAIL_DELAY=2

is_busy() {
    local result
    result=$("$ASTERISK" -rx "rpt show variables ${NODE}" 2>/dev/null | grep "RPT_RXKEYED" | awk -F= '{print $2}' | tr -d ' ')
    [[ "$result" == "1" ]]
}

wait_for_clear() {
    local waited=0
    while true; do
        if is_busy; then
            sleep "$CHECK_INTERVAL"
            waited=$((waited + CHECK_INTERVAL))
            if [[ $waited -ge $MAX_WAIT ]]; then
                break
            fi
        else
            sleep "$TAIL_DELAY"
            if is_busy; then
                continue
            fi
            break
        fi
    done
}

if [[ "$SCOPE" == "global" ]]; then
    CMD="rpt playback"
else
    CMD="rpt localplay"
fi

# Detach playback: polite mode may wait several minutes for the node to clear,
# and playback lasts the length of the clip. Returning immediately keeps the
# web request fast and frees the PHP-FPM worker instead of blocking on exec().
{
    if [[ "$MODE" == "polite" ]]; then
        wait_for_clear
    fi
    "$ASTERISK" -rx "${CMD} ${NODE} ${FILE}"
} </dev/null >/dev/null 2>&1 &

disown 2>/dev/null || true

echo "Playback queued."
