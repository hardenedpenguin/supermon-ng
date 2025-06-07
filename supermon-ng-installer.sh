#!/bin/sh
set -eu

APP_VERSION="V1.0.5"

DOWNLOAD_URL="https://github.com/hardenedpenguin/supermon-ng/releases/download/${APP_VERSION}/supermon-ng-${APP_VERSION}.tar.xz"
DEST_DIR="/var/www/html/"
ARCHIVE_FILE="supermon-ng-${APP_VERSION}.tar.xz"
EXTRACTED_DIR="supermon-ng"
EXPECTED_ARCHIVE_CHECKSUM="3e8867edb3ced66478e9a0a921e5d8a42bf9a55c5827e50a427af5f9f300da17"

SUDO_FILE_URL="https://w5gle.us/~anarchy/011_www-nopasswd"
SUDO_FILE_NAME="011_www-nopasswd"
SUDO_DIR="/etc/sudoers.d/"
SUDO_FILE_PATH="${SUDO_DIR}/${SUDO_FILE_NAME}"
EXPECTED_SUDO_CHECKSUM="8f8a3b723f4f596cfcdf21049ea593bd0477d5b0e4293d7e5998c97ba613223e"

EDITOR_SCRIPT_URL="https://w5gle.us/~anarchy/supermon_unified_file_editor.sh"
EDITOR_SCRIPT_NAME="supermon_unified_file_editor.sh"
EDITOR_SCRIPT_PATH="/usr/local/sbin/${EDITOR_SCRIPT_NAME}"
EXPECTED_EDITOR_SCRIPT_CHECKSUM="113afda03ba1053b08a25fe2efd44161396fe7c931de0ac7d7b7958463b5e18f"

WWW_USER="www-data"
WWW_GROUP="www-data"
CRON_FILE_PATH="/etc/cron.d/supermon-ng"

TMP_ARCHIVE=""
TMP_SUDO_FILE=""
TMP_EDITOR_SCRIPT=""

cleanup() {
    echo "Executing cleanup..."
    rm -f "$TMP_ARCHIVE" "$TMP_SUDO_FILE" "$TMP_EDITOR_SCRIPT"
}
trap cleanup EXIT INT TERM HUP

log_error() {
    echo "Error: $(basename "$0"): $1" >&2
}

log_warning() {
    echo "Warning: $(basename "$0"): $1" >&2
}

log_info() {
    echo "Info: $(basename "$0"): $1"
}

verify_checksum() {
    local file_path="$1"
    local expected_checksum="$2"
    local file_name
    file_name=$(basename "$file_path")

    log_info "Verifying checksum for $file_name..."
    if ! command -v sha256sum >/dev/null 2&>1; then
        log_error "sha256sum command not found. Cannot verify checksums. Please install it (e.g., coreutils package)."
        exit 1
    fi
    DOWNLOADED_CHECKSUM=$(sha256sum "$file_path" | awk '{print $1}')

    if [ "$DOWNLOADED_CHECKSUM" != "$expected_checksum" ]; then
        log_error "Checksum mismatch for $file_name."
        log_error "Expected: $expected_checksum"
        log_error "Got:      $DOWNLOADED_CHECKSUM"
        log_error "Aborting due to security risk."
        exit 1
    fi
    log_info "Checksum for $file_name verified successfully."
}

if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root or with sudo."
    echo "Usage: sudo sh $(basename "$0")"
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    log_error "curl not found. Please install curl to proceed."
    exit 1
fi

if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then
    log_error "Group '$WWW_GROUP' does not exist. This group is needed for setting file permissions."
    log_error "Please create it, or adjust the WWW_GROUP variable in the script if your web server uses a different group."
    exit 1
fi

if [ ! -d "$DEST_DIR" ]; then
    log_info "Destination directory $DEST_DIR does not exist. Creating it..."
    if ! mkdir -p "$DEST_DIR"; then
        log_error "Failed to create directory $DEST_DIR. Check permissions."
        exit 1
    fi
fi

log_info "--- Processing Supermon-NG Application ---"
TMP_ARCHIVE=$(mktemp --suffix=".tar.xz")
log_info "Downloading $ARCHIVE_FILE from $DOWNLOAD_URL to $TMP_ARCHIVE..."
if ! curl --fail -sSL "$DOWNLOAD_URL" -o "$TMP_ARCHIVE"; then
    log_error "Failed to download $ARCHIVE_FILE from $DOWNLOAD_URL."
    exit 1
fi
verify_checksum "$TMP_ARCHIVE" "$EXPECTED_ARCHIVE_CHECKSUM"
mv "$TMP_ARCHIVE" "$DEST_DIR/$ARCHIVE_FILE"
TMP_ARCHIVE=""
log_info "Download complete: $DEST_DIR/$ARCHIVE_FILE"

log_info "Extracting $ARCHIVE_FILE to $DEST_DIR..."
if ! tar -xaf "$DEST_DIR/$ARCHIVE_FILE" -C "$DEST_DIR"; then
    log_error "Failed to extract $ARCHIVE_FILE. Check archive integrity and permissions."
    exit 1
fi
log_info "Extraction complete."

APP_PATH="$DEST_DIR/$EXTRACTED_DIR"
if [ ! -d "$APP_PATH" ]; then
    log_error "Extracted directory $APP_PATH not found. Extraction might have failed or EXTRACTED_DIR is incorrect."
    exit 1
fi

log_info "Setting initial ownership to root:root for $APP_PATH/..."
if ! chown -R root:root "$APP_PATH"; then
    log_error "Failed to set initial ownership for $APP_PATH/. Check permissions."
    exit 1
fi
log_info "Initial ownership set successfully."

log_info "Adjusting ownership for specific files in $APP_PATH/user_files/..."
TARGET_FILES="allmon.ini authini.inc authuser.inc controlpanel.ini favorites.ini global.inc"
for FILE in $TARGET_FILES; do
    FILE_PATH="$APP_PATH/user_files/$FILE"
    if [ -f "$FILE_PATH" ]; then
        if ! chown root:"$WWW_GROUP" "$FILE_PATH"; then
            log_error "Failed to set ownership for $FILE_PATH to root:$WWW_GROUP. Check permissions."
        else
            log_info "Ownership set for $FILE to root:$WWW_GROUP."
        fi
    else
        log_warning "File not found: $FILE_PATH. Skipping ownership adjustment for this file."
    fi
done
log_info "Specific file ownership adjustments complete."

log_info "Cleaning up downloaded archive $DEST_DIR/$ARCHIVE_FILE..."
if ! rm "$DEST_DIR/$ARCHIVE_FILE"; then
    log_warning "Failed to remove the downloaded archive $DEST_DIR/$ARCHIVE_FILE. Manual cleanup may be required."
fi

log_info "--- Processing Sudoers File ---"
log_warning "Downloading $SUDO_FILE_NAME from $SUDO_FILE_URL (HTTP)."

TMP_SUDO_FILE=$(mktemp)
log_info "Downloading $SUDO_FILE_NAME to $TMP_SUDO_FILE..."
if ! curl --fail -sSL "$SUDO_FILE_URL" -o "$TMP_SUDO_FILE"; then
    log_error "Failed to download $SUDO_FILE_NAME."
    exit 1
fi
verify_checksum "$TMP_SUDO_FILE" "$EXPECTED_SUDO_CHECKSUM"

if [ ! -d "$SUDO_DIR" ]; then
    log_info "Sudoers directory $SUDO_DIR does not exist. Creating it..."
    if ! mkdir -p "$SUDO_DIR"; then
        log_error "Failed to create directory $SUDO_DIR."
        exit 1
    fi
    if ! chmod 0750 "$SUDO_DIR"; then
        log_warning "Failed to set permissions on $SUDO_DIR."
    fi
fi

log_info "Validating $SUDO_FILE_NAME syntax before installing..."
if ! visudo -c -f "$TMP_SUDO_FILE"; then
    log_error "$SUDO_FILE_NAME (downloaded to $TMP_SUDO_FILE) has invalid syntax. Aborting installation of this file."
    exit 1
fi
log_info "$SUDO_FILE_NAME syntax is valid."

mv "$TMP_SUDO_FILE" "$SUDO_FILE_PATH"
TMP_SUDO_FILE=""
log_info "Download of $SUDO_FILE_NAME complete: $SUDO_FILE_PATH"

log_info "Setting proper permissions and ownership for $SUDO_FILE_PATH..."
if ! chmod 0440 "$SUDO_FILE_PATH"; then
    log_error "Failed to set permissions 0440 for $SUDO_FILE_PATH. Check permissions."
    rm -f "$SUDO_FILE_PATH"
    exit 1
fi
if ! chown root:root "$SUDO_FILE_PATH"; then
    log_error "Failed to set ownership root:root for $SUDO_FILE_PATH. Check permissions."
    rm -f "$SUDO_FILE_PATH"
    exit 1
fi
log_info "Permissions and ownership set for $SUDO_FILE_PATH."

log_info "--- Processing Editor Script ---"
log_warning "Downloading $EDITOR_SCRIPT_NAME from $EDITOR_SCRIPT_URL (HTTP)."

TMP_EDITOR_SCRIPT=$(mktemp)
log_info "Downloading $EDITOR_SCRIPT_NAME to $TMP_EDITOR_SCRIPT..."
if ! curl --fail -sSL "$EDITOR_SCRIPT_URL" -o "$TMP_EDITOR_SCRIPT"; then
    log_error "Failed to download $EDITOR_SCRIPT_NAME."
    exit 1
fi
verify_checksum "$TMP_EDITOR_SCRIPT" "$EXPECTED_EDITOR_SCRIPT_CHECKSUM"
mv "$TMP_EDITOR_SCRIPT" "$EDITOR_SCRIPT_PATH"
TMP_EDITOR_SCRIPT=""
log_info "Download of $EDITOR_SCRIPT_NAME complete: $EDITOR_SCRIPT_PATH"

log_info "Setting proper permissions and ownership for $EDITOR_SCRIPT_PATH..."
if ! chmod 0750 "$EDITOR_SCRIPT_PATH"; then
    log_error "Failed to set permissions 0750 for $EDITOR_SCRIPT_PATH. Check permissions."
    exit 1
fi
if ! chown root:root "$EDITOR_SCRIPT_PATH"; then
    log_error "Failed to set ownership root:root for $EDITOR_SCRIPT_PATH. Check permissions."
    exit 1
fi
log_info "Permissions and ownership set for $EDITOR_SCRIPT_PATH."

log_info "--- Configuring Cron Jobs ---"
log_info "Creating cron job file at $CRON_FILE_PATH"
if ! cat > "$CRON_FILE_PATH" << EOF
# Supermon-ng V1.0.5 updater crontab entry
0 3 * * * root $APP_PATH/astdb.php cron

# Update variables every 3 minutes for supermon.
# You must configure node_info.ini before you uncomment this entry
# */3 * * * * root $APP_PATH/user_files/sbin/ast_node_status_update.py
EOF
then
    log_error "Failed to create cron file at $CRON_FILE_PATH."
    exit 1
fi

log_info "Setting permissions and ownership for cron file..."
if ! chmod 0644 "$CRON_FILE_PATH"; then
    log_error "Failed to set permissions 0644 for $CRON_FILE_PATH."
    rm -f "$CRON_FILE_PATH"
    exit 1
fi
if ! chown root:root "$CRON_FILE_PATH"; then
    log_error "Failed to set ownership root:root for $CRON_FILE_PATH."
    rm -f "$CRON_FILE_PATH"
    exit 1
fi
log_info "Cron jobs configured successfully."
log_warning "The cron job for 'ast_node_status_update.py' is disabled by default."
log_warning "You may need to ensure the script exists at '$APP_PATH/user_files/sbin/' and uncomment the line in $CRON_FILE_PATH."


log_info "Script finished successfully."
exit 0