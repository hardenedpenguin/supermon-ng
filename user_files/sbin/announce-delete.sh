#!/bin/bash
# Delete announcement files from library and Asterisk sounds.
# Invoked by supermon-ng via sudo -n.

set -euo pipefail

NAME=""
MP3_DIR=""
SOUNDS_DIR=""

usage() {
    echo "Usage: $0 --name BASENAME --mp3-dir DIR --sounds-dir DIR" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --name) NAME="${2:-}"; shift 2 ;;
        --mp3-dir) MP3_DIR="${2:-}"; shift 2 ;;
        --sounds-dir) SOUNDS_DIR="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$NAME" && -n "$MP3_DIR" && -n "$SOUNDS_DIR" ]] || usage
[[ "$NAME" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Invalid name" >&2; exit 1; }

rm -f "${MP3_DIR}/${NAME}.ul" "${MP3_DIR}/${NAME}.wav" "${MP3_DIR}/${NAME}.mp3"
rm -f "${SOUNDS_DIR}/${NAME}.ul"

echo "Deleted ${NAME}"
