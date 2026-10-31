# shellcheck shell=bash
# Sourced by the root-run announce-*.sh helpers. The web app (www-data) passes
# directory and file arguments; only these locations are accepted, and files in
# the www-data-owned library are read and written as www-data so its symlinks
# cannot redirect root.

ANNOUNCE_MP3_DIR=/var/lib/supermon-ng/mp3
ANNOUNCE_SOUNDS_DIRS=(
    /usr/local/share/asterisk/sounds/announcements
    /usr/share/asterisk/sounds/announcements
    /var/lib/asterisk/sounds/announcements
)
ANNOUNCE_USER=www-data

# Exits unless $1 resolves to the announcement library.
require_mp3_dir() {
    local p
    p=$(realpath -e -- "$1" 2>/dev/null) || p=""
    [[ "$p" == "$ANNOUNCE_MP3_DIR" ]] || { echo "MP3 dir not allowed" >&2; exit 1; }
}

# Prints the canonical form of $1 when it is an allowed Asterisk sounds dir.
canonical_sounds_dir() {
    local p d
    p=$(realpath -m -- "$1" 2>/dev/null) || return 1
    for d in "${ANNOUNCE_SOUNDS_DIRS[@]}"; do
        [[ "$p" == "$d" ]] && { printf '%s' "$p"; return 0; }
    done
    return 1
}

# Exits unless $1 is a regular, non-symlink file directly inside the library.
require_library_file() {
    local f="$1" dir
    [[ -f "$f" && ! -L "$f" ]] || { echo "File not found or not a regular file" >&2; exit 1; }
    dir=$(realpath -e -- "$(dirname -- "$f")" 2>/dev/null) || dir=""
    [[ "$dir" == "$ANNOUNCE_MP3_DIR" ]] || { echo "File is outside the announcement library" >&2; exit 1; }
}

as_app_user() {
    runuser -u "$ANNOUNCE_USER" -- "$@"
}
