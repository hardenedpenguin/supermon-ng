#!/bin/bash
# Download a Piper voice model into the ASL3 voices directory.
# Invoked by supermon-ng via sudo -n.

set -euo pipefail

VOICE_ID=""
VOICES_DIR=""
HF_PATH=""
BASE_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main"

usage() {
    echo "Usage: $0 --voice-id ID --voices-dir DIR --huggingface-path PATH" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --voice-id) VOICE_ID="${2:-}"; shift 2 ;;
        --voices-dir) VOICES_DIR="${2:-}"; shift 2 ;;
        --huggingface-path) HF_PATH="${2:-}"; shift 2 ;;
        -h|--help) usage ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

[[ -n "$VOICE_ID" && -n "$VOICES_DIR" && -n "$HF_PATH" ]] || usage
[[ "$VOICE_ID" =~ ^[a-zA-Z0-9._-]+$ ]] || { echo "Invalid voice id" >&2; exit 1; }
[[ "$HF_PATH" =~ ^[a-zA-Z0-9._/-]+$ ]] || { echo "Invalid huggingface path" >&2; exit 1; }

if ! command -v wget >/dev/null 2>&1; then
    echo "wget is not installed" >&2
    exit 1
fi

mkdir -p "$VOICES_DIR"

ONNX="${VOICES_DIR}/${VOICE_ID}.onnx"
JSON="${VOICES_DIR}/${VOICE_ID}.onnx.json"

if [[ -f "$ONNX" && -f "$JSON" ]]; then
    echo "Voice already installed: ${VOICE_ID}"
    exit 0
fi

TMP_ONNX="$(mktemp)"
TMP_JSON="$(mktemp)"
trap 'rm -f "$TMP_ONNX" "$TMP_JSON"' EXIT

wget -q -4 -O "$TMP_ONNX" "${BASE_URL}/${HF_PATH}.onnx"
wget -q -4 -O "$TMP_JSON" "${BASE_URL}/${HF_PATH}.onnx.json"

install -m 644 -o root -g root "$TMP_ONNX" "$ONNX"
install -m 644 -o root -g root "$TMP_JSON" "$JSON"

echo "Installed voice ${VOICE_ID}"
