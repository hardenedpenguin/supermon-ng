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
[[ -f "$TEXT_FILE" ]] || { echo "Text file not found" >&2; exit 1; }
[[ "$TTS_CMD" =~ ^[a-zA-Z0-9._/-]+$ ]] || { echo "Invalid TTS command" >&2; exit 1; }

if ! command -v "$TTS_CMD" >/dev/null 2>&1; then
    echo "${TTS_CMD} is not installed" >&2
    exit 1
fi

[[ -d "$MP3_DIR" ]] || mkdir -p "$MP3_DIR"
[[ -d "$SOUNDS_DIR" ]] || mkdir -p "$SOUNDS_DIR"

UL_BASE="${MP3_DIR}/${NAME}"
UL_OUT="${UL_BASE}.ul"
TEXT=$(cat "$TEXT_FILE")
rm -f "$TEXT_FILE"
rm -f "$UL_OUT"

if [[ -n "$VOICE" ]]; then
    "$TTS_CMD" -n "$NODE" -t "$TEXT" -v "$VOICE" -f "$UL_BASE"
else
    "$TTS_CMD" -n "$NODE" -t "$TEXT" -f "$UL_BASE"
fi

if [[ ! -f "$UL_OUT" ]]; then
    echo "TTS did not produce output" >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$SCRIPT_DIR/announce-install.sh" \
    --input "$UL_OUT" \
    --name "$NAME" \
    --mp3-dir "$MP3_DIR" \
    --sounds-dir "$SOUNDS_DIR"
