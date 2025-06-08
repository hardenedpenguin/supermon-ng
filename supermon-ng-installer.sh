#!/bin/sh
set -eu

APP_VERSION="V1.0.5"
DOWNLOAD_URL="https://github.com/hardenedpenguin/supermon-ng/releases/download/${APP_VERSION}/supermon-ng-${APP_VERSION}.tar.xz"
DEST_DIR="/var/www/html"
EXTRACTED_DIR="supermon-ng"
EXPECTED_ARCHIVE_CHECKSUM="3e8867edb3ced66478e9a0a921e5d8a42bf9a55c5827e50a427af5f9f300da17"

SUDO_FILE_URL="https://w5gle.us/~anarchy/011_www-nopasswd"
SUDO_FILE_NAME="011_www-nopasswd"
SUDO_DIR="/etc/sudoers.d"
SUDO_FILE_PATH="${SUDO_DIR}/${SUDO_FILE_NAME}"
EXPECTED_SUDO_CHECKSUM="8f8a3b723f4f596cfcdf21049ea593bd0477d5b0e4293d7e5998c97ba613223e"

EDITOR_SCRIPT_URL="https://w5gle.us/~anarchy/supermon_unified_file_editor.sh"
EDITOR_SCRIPT_NAME="supermon_unified_file_editor.sh"
EDITOR_SCRIPT_PATH="/usr/local/sbin/${EDITOR_SCRIPT_NAME}"
EXPECTED_EDITOR_SCRIPT_CHECKSUM="113afda03ba1053b08a25fe2efd44161396fe7c931de0ac7d7b7958463b5e18f"

WWW_GROUP="www-data"
CRON_FILE_PATH="/etc/cron.d/supermon-ng"

TMP_DIR=""
SCRIPT_NAME="$(basename "$0")"

C_RESET='\033[0m'
C_RED='\033[0;31m'
C_GREEN='\033[0;32m'
C_YELLOW='\033[0;33m'
C_BLUE='\033[0;34m'

log_error() { echo -e "${C_RED}Error: ${SCRIPT_NAME}: $1${C_RESET}" >&2; }
log_warning() { echo -e "${C_YELLOW}Warning: ${SCRIPT_NAME}: $1${C_RESET}" >&2; }
log_info() { echo -e "${C_BLUE}Info: ${SCRIPT_NAME}: $1${C_RESET}"; }
log_success() { echo -e "${C_GREEN}Success: ${SCRIPT_NAME}: $1${C_RESET}"; }

cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        log_info "Executing cleanup of temporary directory $TMP_DIR..."
        rm -rf "$TMP_DIR"
    fi
}
trap cleanup EXIT INT TERM HUP

verify_checksum() {
    local file_path="$1"
    local expected_checksum="$2"
    local file_name
    file_name=$(basename "$file_path")
    log_info "Verifying checksum for $file_name..."
    DOWNLOADED_CHECKSUM=$(sha256sum "$file_path" | awk '{print $1}')
    if [ "$DOWNLOADED_CHECKSUM" != "$expected_checksum" ]; then
        log_error "Checksum mismatch for $file_name."
        log_error "Expected: $expected_checksum"
        log_error "Got:      $DOWNLOADED_CHECKSUM"
        log_error "Aborting due to security risk."
        exit 1
    fi
    log_success "Checksum for $file_name verified."
}

check_dependencies() {
    log_info "Checking for required commands..."
    for cmd in curl tar sha256sum visudo id getent rsync; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            log_error "Required command '$cmd' not found. Please install it and try again."
            exit 1
        fi
    done
    log_success "All required commands are present."
}

install_system_dependencies() {
    log_info "--- Checking and Installing System Dependencies (for Debian/Ubuntu) ---"
    local deps="apache2 php libapache2-mod-php libcgi-session-perl bc"
    if ! command -v apt-get >/dev/null 2>&1; then
        log_warning "apt-get package manager not found. Cannot automatically install system dependencies."
        log_warning "Please ensure the following (or equivalent) packages are installed:"
        log_warning "$deps"
        return 0
    fi
    log_info "Updating package lists..."
    if ! apt-get update; then
        log_error "Failed to update package lists with 'apt-get update'. Please check your configuration."
        return 1
    fi
    log_info "Attempting to install: $deps"
    if ! apt-get install -y $deps; then
        log_error "Failed to install one or more system dependencies."
        log_error "Please try installing them manually."
        return 1
    fi
    log_success "System dependencies installed successfully."
}

install_application() {
    log_info "--- Processing Supermon-NG Application ---"
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local archive_path="${TMP_DIR}/${APP_VERSION}.tar.xz"
    local tmp_extract_path="${TMP_DIR}/${EXTRACTED_DIR}"
    local preserve_files=".htpasswd supermon-ng.css user_files/allmon.ini user_files/authini.inc user_files/authusers.inc user_files/background.jpg user_files/controlpanel.ini user_files/favorites.ini user_files/global.inc user_files/privatenodes.txt user_files/sbin/node_info.ini"

    if [ -d "$app_path" ]; then
        log_warning "An existing Supermon-NG installation was found at '$app_path'."
        log_warning "Updating will replace core files but preserve configuration."
        printf "${C_YELLOW}Do you want to proceed with the update? (y/N): ${C_RESET}"
        read -r response
        case "$response" in
            [yY][eE][sS]|[yY])
                log_info "Starting update process..."
                ;;
            *)
                log_info "Update cancelled by user. Skipping application processing."
                return 0
                ;;
        esac

        log_info "Downloading new version for update..."
        if ! curl --fail -sSL "$DOWNLOAD_URL" -o "$archive_path"; then
            log_error "Failed to download new application version."
            return 1
        fi
        verify_checksum "$archive_path" "$EXPECTED_ARCHIVE_CHECKSUM"

        log_info "Extracting new version to temporary location..."
        if ! tar -xaf "$archive_path" -C "$TMP_DIR"; then
            log_error "Failed to extract new archive version."
            return 1
        fi

        log_info "Syncing new files to installation directory, preserving user configurations..."
        local rsync_excludes=""
        for file in $preserve_files; do
            rsync_excludes="$rsync_excludes --exclude=$file"
        done
        if ! rsync -a --delete $rsync_excludes "${tmp_extract_path}/" "${app_path}/"; then
            log_error "rsync failed to update the application files."
            return 1
        fi
        log_success "Core application files have been updated."

        log_info "Checking for and installing missing config files from the tarball..."
        for file in $preserve_files; do
            local dest_config_file="${app_path}/${file}"
            local src_config_file="${tmp_extract_path}/${file}"
            if [ ! -e "$dest_config_file" ] && [ -e "$src_config_file" ]; then
                log_info "Installing missing config file: $file"
                cp "$src_config_file" "$dest_config_file"
            fi
        done

    else
        install_system_dependencies || return 1
        log_info "No existing installation found. Performing a fresh install."
        log_info "Downloading application from $DOWNLOAD_URL..."
        if ! curl --fail -sSL "$DOWNLOAD_URL" -o "$archive_path"; then
            log_error "Failed to download application."
            return 1
        fi
        verify_checksum "$archive_path" "$EXPECTED_ARCHIVE_CHECKSUM"
        log_info "Extracting archive to $DEST_DIR..."
        if ! tar -xaf "$archive_path" -C "$DEST_DIR"; then
            log_error "Failed to extract archive. Check archive integrity and permissions."
            return 1
        fi
        log_success "Extraction complete."
    fi

    log_info "Setting base ownership for $app_path..."
    chown -R root:root "$app_path"

    log_info "Setting final permissions on user-configurable files..."
    for file in $preserve_files; do
        local file_path="$app_path/$file"
        if [ -e "$file_path" ]; then
            chown "root:$WWW_GROUP" "$file_path"
            log_info "Ownership set for $file to root:$WWW_GROUP."
        else
            log_warning "Expected user file not found after install/update: $file_path. Skipping ownership change."
        fi
    done

    log_success "Application installation/update finished."
}


install_sudo_config() {
    log_info "--- Processing Sudoers File ---"
    local tmp_sudo_file="${TMP_DIR}/${SUDO_FILE_NAME}"
    log_info "Downloading $SUDO_FILE_NAME from $SUDO_FILE_URL..."
    if ! curl --fail -sSL "$SUDO_FILE_URL" -o "$tmp_sudo_file"; then
        log_error "Failed to download $SUDO_FILE_NAME."
        return 1
    fi
    verify_checksum "$tmp_sudo_file" "$EXPECTED_SUDO_CHECKSUM"
    log_info "Validating sudoers file syntax..."
    if ! visudo -c -f "$tmp_sudo_file"; then
        log_error "Downloaded sudoers file has invalid syntax. Aborting installation of this file."
        return 1
    fi
    log_success "Sudoers syntax is valid."
    log_info "Installing sudoers file to $SUDO_FILE_PATH..."
    mkdir -p "$SUDO_DIR"
    chmod 0750 "$SUDO_DIR"
    mv "$tmp_sudo_file" "$SUDO_FILE_PATH"
    log_info "Setting permissions and ownership for $SUDO_FILE_PATH..."
    chmod 0440 "$SUDO_FILE_PATH"
    chown root:root "$SUDO_FILE_PATH"
    log_success "Sudoers file installed at $SUDO_FILE_PATH."
}

install_editor_script() {
    log_info "--- Processing Editor Script ---"
    local tmp_editor_script="${TMP_DIR}/${EDITOR_SCRIPT_NAME}"
    log_info "Downloading $EDITOR_SCRIPT_NAME from $EDITOR_SCRIPT_URL..."
    if ! curl --fail -sSL "$EDITOR_SCRIPT_URL" -o "$tmp_editor_script"; then
        log_error "Failed to download $EDITOR_SCRIPT_NAME."
        return 1
    fi
    verify_checksum "$tmp_editor_script" "$EXPECTED_EDITOR_SCRIPT_CHECKSUM"
    log_info "Installing editor script to $EDITOR_SCRIPT_PATH..."
    mv "$tmp_editor_script" "$EDITOR_SCRIPT_PATH"
    log_info "Setting permissions and ownership for $EDITOR_SCRIPT_PATH..."
    chmod 0750 "$EDITOR_SCRIPT_PATH"
    chown root:root "$EDITOR_SCRIPT_PATH"
    log_success "Editor script installed at $EDITOR_SCRIPT_PATH."
}

install_cron_job() {
    log_info "--- Configuring Cron Jobs ---"
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local marker_comment="# Supermon-ng V1.0.5 cron entries"

    if [ -f "$CRON_FILE_PATH" ]; then
        log_warning "Cron file '$CRON_FILE_PATH' already exists. Skipping to preserve user modifications."
        return 0
    fi

    log_info "Creating new cron job file at $CRON_FILE_PATH..."
    cat > "$CRON_FILE_PATH" << EOF
$marker_comment
0 3 * * * root $app_path/astdb.php cron
# Update variables every 3 minutes for supermon.
# You must configure node_info.ini before you uncomment this entry
# */3 * * * * root $app_path/user_files/sbin/ast_node_status_update.py
EOF

    log_info "Setting permissions for cron file..."
    chmod 0644 "$CRON_FILE_PATH"
    chown root:root "$CRON_FILE_PATH"
    log_success "New cron file created."
    log_warning "The cron job for 'ast_node_status_update.py' is disabled by default."
    log_warning "You may need to ensure the script exists and uncomment the line in $CRON_FILE_PATH."
}

main() {
    check_dependencies
    if [ "$(id -u)" -ne 0 ]; then
        log_error "This script must be run as root or with sudo."
        echo "Usage: sudo sh $SCRIPT_NAME"
        exit 1
    fi
    if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then
        log_error "Group '$WWW_GROUP' does not exist. This group is needed for setting file permissions."
        exit 1
    fi
    if [ ! -d "$DEST_DIR" ]; then
        log_info "Destination directory $DEST_DIR does not exist. Creating it..."
        if ! mkdir -p "$DEST_DIR"; then
            log_error "Failed to create directory $DEST_DIR. Check permissions."
            exit 1
        fi
    fi
    TMP_DIR=$(mktemp -d)
    install_application || exit 1
    install_sudo_config || exit 1
    install_editor_script || exit 1
    install_cron_job || exit 1
    log_success "Supermon-NG installation/update script finished successfully."
    exit 0
}

main
