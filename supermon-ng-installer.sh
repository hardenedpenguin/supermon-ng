#!/bin/sh
set -eu

APP_VERSION="V2.0.0"
DOWNLOAD_URL="https://github.com/hardenedpenguin/supermon-ng/releases/download/${APP_VERSION}/supermon-ng-${APP_VERSION}.tar.xz"
DEST_DIR="/var/www/html"
EXTRACTED_DIR="supermon-ng"
EXPECTED_ARCHIVE_CHECKSUM="b4f64d96d02ef695a47be6372da05ba508d39d6e3a2ef7cc9f58e2ecf54921d5"

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

log_error() { printf "${C_RED}Error: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1" >&2; }
log_warning() { printf "${C_YELLOW}Warning: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1" >&2; }
log_info() { printf "${C_BLUE}Info: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1"; }
log_success() { printf "${C_GREEN}Success: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1"; }

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
        return 1
    fi
    log_success "Checksum for $file_name verified."
}

install_system_dependencies() {
    log_info "--- Checking and Installing System Dependencies ---"
    
    if ! command -v apt-get >/dev/null 2>&1; then
        log_warning "apt-get not found. This script can only auto-install dependencies on Debian/Ubuntu systems."
        log_warning "Please ensure all required packages are installed manually before proceeding."
        printf "${C_YELLOW}Required commands: curl, tar, sha256sum, visudo, rsync, setfacl, bc.${C_RESET}\n"
        printf "${C_YELLOW}Do you want to continue anyway? (y/N): ${C_RESET}"
        read -r response
        case "$response" in
            [yY][eE][sS]|[yY]) 
                log_info "Continuing without automatic dependency installation."
                return 0 
                ;;
            *) 
                log_error "Aborting."
                return 1 
                ;;
        esac
    fi

    if ! command -v dpkg-query >/dev/null 2>&1; then
        log_info "dpkg-query not found, attempting to install the 'dpkg' package..."
        apt-get update
        apt-get install -y dpkg || { log_error "Failed to install dpkg. Cannot check dependencies."; return 1; }
    fi

    local required_packages="apache2 php libapache2-mod-php libcgi-session-perl bc acl curl tar coreutils sudo rsync"
    local missing_packages=""

    log_info "Checking for required packages..."
    for pkg in $required_packages; do
        if ! dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "ok installed"; then
            missing_packages="$missing_packages $pkg"
        fi
    done

    if [ -n "$missing_packages" ]; then
        missing_packages=$(echo "$missing_packages" | sed 's/^ *//')
        log_info "The following required packages are missing and will be installed: $missing_packages"
        log_info "Updating package lists..."
        apt-get update || { log_error "apt-get update failed."; return 1; }
        
        log_info "Attempting to install packages..."
        apt-get install -y $missing_packages || { log_error "Failed to install one or more required packages."; return 1; }
        log_success "System dependencies installed successfully."
    else
        log_success "All required system dependencies are already installed."
    fi
}

download_and_install_component() {
    local name="$1" url="$2" checksum="$3" dest_path="$4" perms="$5" owner="$6"
    shift 6
    local validation_cmd="$@"
    local tmp_file

    log_info "--- Processing ${name} ---"
    tmp_file="${TMP_DIR}/$(basename "$url")"

    log_info "Downloading $name..."
    if ! curl --fail -sSL "$url" -o "$tmp_file"; then
        log_error "Failed to download $name."
        return 1
    fi

    verify_checksum "$tmp_file" "$checksum" || return 1

    if [ -n "$validation_cmd" ]; then
        log_info "Validating $name syntax..."
        if ! $validation_cmd "$tmp_file"; then
            log_error "$name has invalid syntax. Aborting installation of this file."
            return 1
        fi
        log_success "$name syntax is valid."
    fi

    log_info "Installing $name to $dest_path..."
    mkdir -p "$(dirname "$dest_path")"
    mv "$tmp_file" "$dest_path"
    chmod "$perms" "$dest_path"
    chown "$owner" "$dest_path"
    log_success "$name installed."
}

manage_optional_auth() {
    local app_path="$1"
    local tmp_extract_path="$2"
    local optional_files="user_files/authini.inc user_files/authusers.inc"

    log_info "--- Optional Feature Configuration ---"
    printf "${C_YELLOW}Supermon can use local files for authentication. This is optional.\n"
    printf "This feature uses the files: 'authini.inc' and 'authusers.inc'.\n"
    printf "Do you want to enable/install local file authentication? (Y/n): ${C_RESET}"
    read -r response
    case "$response" in
        [nN]|[nN][oO])
            log_info "Disabling local file authentication. Removing files..."
            for file in $optional_files; do
                rm -f "${app_path}/${file}"
            done
            log_success "Optional authentication files removed."
            ;;
        *)
            log_info "Enabling local file authentication..."
            for file in $optional_files; do
                local dest_file="${app_path}/${file}"
                local src_file="${tmp_extract_path}/${file}"
                if [ ! -e "$dest_file" ]; then
                    log_info "Installing missing auth file: $file"
                    cp "$src_file" "$dest_file"
                else
                    log_info "Auth file '$file' already exists. Leaving it untouched."
                fi
            done
            log_success "Local authentication files are enabled."
            ;;
    esac
}

install_application() {
    log_info "--- Processing Supermon-NG Application ---"
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local archive_path="${TMP_DIR}/${APP_VERSION}.tar.xz"
    local tmp_extract_path="${TMP_DIR}/${EXTRACTED_DIR}"
    local preserve_files=" .htaccess .htpasswd supermon-ng.css user_files/admin-controlpanel.ini user_files/admin-favorites.ini user_files/allmon.ini user_files/authini.inc user_files/authusers.inc user_files/authusers.inc.backup user_files/background.jpg user_files/controlpanel.ini user_files/cyborg_hamradio.png user_files/favorites.ini user_files/global.inc user_files/privatenodes.txt user_files/set_password.sh user_files/sbin/node_info.ini user_files/Xauthini.inc user_files/Xauthusers.inc user_files/Xcntrlini.inc user_files/Xcntrlnolog.ini user_files/Xfavini.inc user_files/Xfavnolog.ini user_files/Xnolog.ini "
    local did_update="false"

    log_info "Downloading application..."
    curl --fail -sSL "$DOWNLOAD_URL" -o "$archive_path" || { log_error "Download failed."; return 1; }
    verify_checksum "$archive_path" "$EXPECTED_ARCHIVE_CHECKSUM" || return 1
    log_info "Extracting application to temporary location..."
    tar -xaf "$archive_path" -C "$TMP_DIR" || { log_error "Extraction failed."; return 1; }

    if [ -d "$app_path" ]; then
        log_warning "An existing Supermon-NG installation was found."
        log_warning "Update will protect user files but replace core application."
        printf "${C_YELLOW}Do you want to proceed with the update? (y/N): ${C_RESET}"
        read -r response
        case "$response" in
            [yY][eE][sS]|[yY])
                log_info "Starting update..."
                did_update="true"

                log_warning "----------------- IMPORTANT NOTICE: CSS FILE -----------------"
                printf "${C_YELLOW}Your existing 'supermon-ng.css' file will NOT be overwritten to protect\n"
                printf "your custom styles. The new version of the application may require\n"
                printf "CSS changes to function or display correctly.\n"
                printf "${C_YELLOW}ACTION REQUIRED: You should manually compare your existing file:\n"
                printf "  '${app_path}/supermon-ng.css'\n"
                printf "with the new version from this release, which is located at:\n"
                printf "  '${tmp_extract_path}/supermon-ng.css'\n"
                printf "after this script completes.${C_RESET}\n"
                ;;
            *)
                log_info "Update cancelled."; return 0 ;;
        esac

        log_info "Syncing core application files (deleting any obsolete files)..."
        rsync -a --delete --exclude='user_files/' --exclude='.htaccess' --exclude='.htpasswd' --exclude='supermon-ng.css' "${tmp_extract_path}/" "${app_path}/" || { log_error "rsync failed."; return 1; }
        
        log_info "Syncing updatable scripts in user_files/sbin/ (deleting any obsolete scripts)..."
        mkdir -p "${app_path}/user_files/sbin"
        rsync -a --delete --exclude='node_info.ini' "${tmp_extract_path}/user_files/sbin/" "${app_path}/user_files/sbin/" || { log_error "sbin rsync failed."; return 1; }
        
        log_info "Checking for other missing user-configurable files..."
        for file in $preserve_files; do
            if [ ! -e "${app_path}/${file}" ] && [ -e "${tmp_extract_path}/${file}" ]; then
                log_warning "Default file '$file' exists in new version but is not in your installation. No action taken."
            fi
        done
    else
        log_info "Performing a fresh install."
        log_info "Extracting archive to final destination $DEST_DIR..."
        tar -xaf "$archive_path" -C "$DEST_DIR" || { log_error "Extraction failed."; return 1; }
    fi

    manage_optional_auth "$app_path" "$tmp_extract_path"

    log_info "Setting final file ownership..."
    chown -R root:root "$app_path"
    for file in $preserve_files; do
        if [ -e "${app_path}/$file" ]; then
            chown -h "root:$WWW_GROUP" "${app_path}/$file"
        fi
    done
    
    if [ "$did_update" = "true" ]; then
        log_warning "REMINDER: As this was an upgrade, please remember to manually review and merge style changes into your 'supermon-ng.css' file."
    fi
    
    log_success "Application installation/update finished."
}

install_sudo_config() {
    if ! command -v visudo > /dev/null 2>&1; then
        log_error "'visudo' command not found, cannot safely install sudoers file. Skipping."
        return 1
    fi
    chmod 0750 "$SUDO_DIR"
    download_and_install_component "Sudoers File" "$SUDO_FILE_URL" "$EXPECTED_SUDO_CHECKSUM" "$SUDO_FILE_PATH" "0440" "root:root" visudo -c -f
}

install_editor_script() {
    download_and_install_component "Editor Script" "$EDITOR_SCRIPT_URL" "$EXPECTED_EDITOR_SCRIPT_CHECKSUM" "$EDITOR_SCRIPT_PATH" "0750" "root:root"
}

install_cron_job() {
    if [ -f "$CRON_FILE_PATH" ]; then
        log_warning "Cron file '$CRON_FILE_PATH' already exists. Skipping."
        return 0
    fi
    log_info "Creating new cron job file at $CRON_FILE_PATH..."
    printf '%s\n' \
        "0 3 * * * root ${DEST_DIR}/${EXTRACTED_DIR}/astdb.php cron" \
        "# */3 * * * * root ${DEST_DIR}/${EXTRACTED_DIR}/user_files/sbin/ast_node_status_update.py" \
        > "$CRON_FILE_PATH"
    chmod 0644 "$CRON_FILE_PATH"
    chown root:root "$CRON_FILE_PATH"
    log_success "New cron file created."
    log_warning "The 'ast_node_status_update.py' cron job is disabled by default."
}

configure_log_acls() {
    local log_dir="/var/log/apache2"
    log_info "--- Configuring Apache Log Permissions ---"

    if ! command -v setfacl > /dev/null 2>&1; then
        log_error "'setfacl' command not found, cannot configure log permissions. Skipping."
        return 1
    fi

    if [ ! -d "$log_dir" ]; then
        log_warning "Apache log directory '$log_dir' not found. Skipping ACL configuration."
        return 0
    fi

    local acl_set
    acl_set=$(getfacl -p "$log_dir" 2>/dev/null | grep -E "^group:${WWW_GROUP}:r-x$")
    local default_acl_set
    default_acl_set=$(getfacl -pd "$log_dir" 2>/dev/null | grep -E "^default:group:${WWW_GROUP}:r-x$")

    if [ -n "$acl_set" ] && [ -n "$default_acl_set" ]; then
        log_info "ACL for '${WWW_GROUP}' on '${log_dir}' is already configured."
        return 0
    fi

    log_info "Setting ACL for group '${WWW_GROUP}' to have read access on '${log_dir}'..."
    if ! setfacl -R -m "g:${WWW_GROUP}:rX" "$log_dir"; then
        log_error "Failed to set recursive ACL on $log_dir."
        log_warning "This can happen if the filesystem does not support ACLs. You may need to remount it with the 'acl' option."
        return 1
    fi
    if ! setfacl -R -d -m "g:${WWW_GROUP}:rX" "$log_dir"; then
        log_error "Failed to set default ACL on $log_dir."
        return 1
    fi

    log_success "ACL for Apache logs configured successfully."
}

main() {
    if [ "$(id -u)" -ne 0 ]; then log_error "This script must be run as root."; exit 1; fi
    
    install_system_dependencies || { log_error "Dependency installation failed. Aborting."; exit 1; }

    if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then log_error "Group '$WWW_GROUP' does not exist."; exit 1; fi
    mkdir -p "$DEST_DIR"
    TMP_DIR=$(mktemp -d)
    
    install_application || exit 1
    install_sudo_config || exit 1
    install_editor_script || exit 1
    install_cron_job || exit 1
    configure_log_acls || exit 1
    
    log_success "Supermon-NG installation/update script finished successfully."
}

main
