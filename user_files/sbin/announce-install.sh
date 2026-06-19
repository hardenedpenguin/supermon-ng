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
[[ -f "$INPUT" ]] || { echo "Input file not found" >&2; exit 1; }
[[ -d "$MP3_DIR" ]] || { echo "MP3 dir not found" >&2; exit 1; }
[[ -d "$SOUNDS_DIR" ]] || mkdir -p "$SOUNDS_DIR"

UL_LOCAL="${MP3_DIR}/${NAME}.ul"
UL_DEST="${SOUNDS_DIR}/${NAME}.ul"

if [[ "$INPUT" == *.ul ]]; then
    if [[ "$INPUT" != "$UL_LOCAL" ]]; then
        install -m 644 -o root -g root "$INPUT" "$UL_LOCAL"
    fi
    if [[ "$UL_LOCAL" != "$UL_DEST" ]]; then
        install -m 644 -o root -g root "$UL_LOCAL" "$UL_DEST"
    fi
    if [[ "$INPUT" != "$UL_LOCAL" && "$INPUT" != "$UL_DEST" ]]; then
        rm -f "$INPUT"
    fi
    echo "Installed ${NAME}.ul"
    exit 0
fi

if ! command -v sox >/dev/null 2>&1; then
    echo "sox is not installed" >&2
    exit 1
fi

sox "$INPUT" -t raw -r 8000 -c 1 -e u-law "$UL_LOCAL"

install -m 644 -o root -g root "$UL_LOCAL" "$UL_DEST"
rm -f "$INPUT"

# Remove any leftover wav from TTS or prior conversion
rm -f "${MP3_DIR}/${NAME}.wav"

echo "Installed ${NAME}.ul"
