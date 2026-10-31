#!/bin/bash
# Generate ulaw announcement audio via asl-tts, then install to sounds.
# Invoked by supermon-ng via sudo -n.

set -euo pipefail

TEXT_FILE=""
NAME=""
NODE=""
VOICE=""
MP3_DIR=""
SOUNDS_DIR=""
TTS_CMD="asl-tts"

usage() {
    echo "Usage: $0 --text-file PATH --name BASENAME --node NODE --voice VOICE --mp3-dir DIR --sounds-dir DIR [--tts-cmd CMD]" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --text-file) TEXT_FILE="${2:-}"; shift 2 ;;
        --name) NAME="${2:-}"; shift 2 ;;
        --node) NODE="${2:-}"; shift 2 ;;
        --voice) VOICE="${2:-}"; shift 2 ;;
        --mp3-dir) MP3_DIR="${2:-}"; shift 2 ;;
        --sounds-dir) SOUNDS_DIR="${2:-}"; shift 2 ;;
        --tts-cmd) TTS_CMD="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$TEXT_FILE" && -n "$NAME" && -n "$NODE" && -n "$MP3_DIR" && -n "$SOUNDS_DIR" ]] || usage
[[ "$NAME" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Invalid name" >&2; exit 1; }
[[ "$NODE" =~ ^[0-9]+$ ]] || { echo "Invalid node" >&2; exit 1; }
[[ -z "$VOICE" || "$VOICE" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Invalid voice" >&2; exit 1; }

# Runs as root: only the packaged asl-tts may be executed.
case "$TTS_CMD" in
    asl-tts|/usr/bin/asl-tts|/usr/local/bin/asl-tts) ;;
    *) echo "Unsupported TTS command (only asl-tts is allowed)" >&2; exit 1 ;;
esac
TTS_CMD=$(command -v asl-tts) || { echo "asl-tts is not installed" >&2; exit 1; }

SCRIPT_DIR="$(dirname "$(realpath "${BASH_SOURCE[0]}")")"
# shellcheck source=announce-common.sh
. "$SCRIPT_DIR/announce-common.sh"
require_mp3_dir "$MP3_DIR"
MP3_DIR="$ANNOUNCE_MP3_DIR"
SOUNDS_DIR=$(canonical_sounds_dir "$SOUNDS_DIR") || { echo "Sounds dir not allowed" >&2; exit 1; }
require_library_file "$TEXT_FILE"
[[ "$(basename -- "$TEXT_FILE")" == tts-* ]] || { echo "Invalid text file" >&2; exit 1; }
TEXT_FILE="${MP3_DIR}/$(basename -- "$TEXT_FILE")"
[[ -d "$SOUNDS_DIR" ]] || mkdir -p "$SOUNDS_DIR"

UL_OUT="${MP3_DIR}/${NAME}.ul"
TEXT=$(as_app_user cat -- "$TEXT_FILE")
as_app_user rm -f -- "$TEXT_FILE" "$UL_OUT"

# asl-tts writes as root, so let it write into a root-only temp dir and copy
# the result into the www-data-owned library as www-data.
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
if [[ -n "$VOICE" ]]; then
    "$TTS_CMD" -n "$NODE" -t "$TEXT" -v "$VOICE" -f "$WORK/$NAME"
else
    "$TTS_CMD" -n "$NODE" -t "$TEXT" -f "$WORK/$NAME"
fi

if [[ ! -f "$WORK/$NAME.ul" || -L "$WORK/$NAME.ul" ]]; then
    echo "TTS did not produce output" >&2
    exit 1
fi
chmod 755 "$WORK"
chmod 644 "$WORK/$NAME.ul"
as_app_user cp -- "$WORK/$NAME.ul" "$UL_OUT"

"$SCRIPT_DIR/announce-install.sh" \
    --input "$UL_OUT" \
    --name "$NAME" \
    --mp3-dir "$MP3_DIR" \
    --sounds-dir "$SOUNDS_DIR"
