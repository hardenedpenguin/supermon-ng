#!/bin/bash
# Convert uploaded audio to ulaw, install to Asterisk sounds, remove source.
# Invoked by supermon-ng via sudo -n.

set -euo pipefail

INPUT=""
NAME=""
MP3_DIR=""
SOUNDS_DIR=""

usage() {
    echo "Usage: $0 --input PATH --name BASENAME --mp3-dir DIR --sounds-dir DIR" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --input) INPUT="${2:-}"; shift 2 ;;
        --name) NAME="${2:-}"; shift 2 ;;
        --mp3-dir) MP3_DIR="${2:-}"; shift 2 ;;
        --sounds-dir) SOUNDS_DIR="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$INPUT" && -n "$NAME" && -n "$MP3_DIR" && -n "$SOUNDS_DIR" ]] || usage
[[ "$NAME" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Invalid name" >&2; exit 1; }

# shellcheck source=announce-common.sh
. "$(dirname "$(realpath "${BASH_SOURCE[0]}")")/announce-common.sh"
require_mp3_dir "$MP3_DIR"
MP3_DIR="$ANNOUNCE_MP3_DIR"
SOUNDS_DIR=$(canonical_sounds_dir "$SOUNDS_DIR") || { echo "Sounds dir not allowed" >&2; exit 1; }
require_library_file "$INPUT"
INPUT="${MP3_DIR}/$(basename -- "$INPUT")"
[[ -d "$SOUNDS_DIR" ]] || mkdir -p "$SOUNDS_DIR"

UL_LOCAL="${MP3_DIR}/${NAME}.ul"
UL_DEST="${SOUNDS_DIR}/${NAME}.ul"

# The library belongs to www-data, so convert/copy there as www-data.
if [[ "$INPUT" == *.ul ]]; then
    if [[ "$INPUT" != "$UL_LOCAL" ]]; then
        as_app_user rm -f -- "$UL_LOCAL"
        as_app_user cp -- "$INPUT" "$UL_LOCAL"
    fi
else
    if ! command -v sox >/dev/null 2>&1; then
        echo "sox is not installed" >&2
        exit 1
    fi
    as_app_user rm -f -- "$UL_LOCAL"
    as_app_user sox "$INPUT" -t raw -r 8000 -c 1 -e u-law "$UL_LOCAL"
fi

# Stage the finished file in a root-only temp dir (read as www-data), then
# install it into the root-owned sounds dir.
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
as_app_user cat -- "$UL_LOCAL" >"$WORK/${NAME}.ul"
install -m 644 -o root -g root "$WORK/${NAME}.ul" "$UL_DEST"

if [[ "$INPUT" != "$UL_LOCAL" ]]; then
    as_app_user rm -f -- "$INPUT"
fi
# Remove any leftover wav from TTS or prior conversion
as_app_user rm -f -- "${MP3_DIR}/${NAME}.wav"

echo "Installed ${NAME}.ul"
