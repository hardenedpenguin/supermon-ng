#!/bin/sh
set -eu

APP_VERSION="V2.0.3"
DOWNLOAD_URL="https://github.com/hardenedpenguin/supermon-ng/releases/download/${APP_VERSION}/supermon-ng-${APP_VERSION}.tar.xz"
DEST_DIR="/var/www/html"
EXTRACTED_DIR="supermon-ng"
EXPECTED_ARCHIVE_CHECKSUM="5815bd42b0efa7cf266bbfa8890b0dae240b530101589a8d974724b1a90e42aa"

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

ASTERISK_LOG_DIR="/var/log/asterisk"

TMP_DIR=""
WARNINGS_FILE=""
SCRIPT_NAME="$(basename "$0")"

C_RESET='\033[0m'
C_RED='\033[0;31m'
C_YELLOW='\033[0;33m'

log_error() { printf "${C_RED}Error: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1"; }
log_warning() { printf "${C_YELLOW}Warning: ${SCRIPT_NAME}: %s\n${C_RESET}" "$1" >> "$WARNINGS_FILE"; }
log_info() { :; }
log_success() { :; }

cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}
trap cleanup EXIT INT TERM HUP

verify_checksum() {
    local file_path="$1"
    local expected_checksum="$2"
    local file_name
    file_name=$(basename "$file_path")
    DOWNLOADED_CHECKSUM=$(sha256sum "$file_path" | awk '{print $1}')
    if [ "$DOWNLOADED_CHECKSUM" != "$expected_checksum" ]; then
        log_error "Checksum mismatch for $file_name."
        log_error "Expected: $expected_checksum"
        log_error "Got:      $DOWNLOADED_CHECKSUM"
        return 1
    fi
}

install_system_dependencies() {
    if ! command -v apt-get >/dev/null 2>&1; then
        log_warning "apt-get not found. This script can only auto-install dependencies on Debian/Ubuntu systems."
        log_warning "Please ensure all required packages are installed manually before proceeding: curl, tar, sha256sum, visudo, rsync, setfacl, bc."
        printf "${C_YELLOW}Do you want to continue anyway? (y/N): ${C_RESET}"
        read -r response
        case "$response" in
            [yY][eE][sS]|[yY]) return 0 ;;
            *) log_error "Aborting."; return 1 ;;
        esac
    fi

    if ! command -v dpkg-query >/dev/null 2>&1; then
        apt-get update >/dev/null 2>&1
        apt-get install -y dpkg >/dev/null 2>&1 || { log_error "Failed to install dpkg. Cannot check dependencies."; return 1; }
    fi

    local required_packages="apache2 php libapache2-mod-php libcgi-session-perl bc acl curl tar coreutils sudo rsync"
    local missing_packages=""

    for pkg in $required_packages; do
        if ! dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "ok installed"; then
            missing_packages="$missing_packages $pkg"
        fi
    done

    if [ -n "$missing_packages" ]; then
        missing_packages=$(echo "$missing_packages" | sed 's/^ *//')
        apt-get update >/dev/null 2>&1 || { log_error "apt-get update failed."; return 1; }
        apt-get install -y $missing_packages >/dev/null 2>&1 || { log_error "Failed to install one or more required packages."; return 1; }
    fi
}

download_and_install_component() {
    local name="$1" url="$2" checksum="$3" dest_path="$4" perms="$5" owner="$6"
    shift 6
    local validation_cmd="$@"
    local tmp_file="${TMP_DIR}/$(basename "$url")"

    if ! curl --fail -sSL "$url" -o "$tmp_file"; then
        log_error "Failed to download $name."
        return 1
    fi

    verify_checksum "$tmp_file" "$checksum" || return 1

    if [ -n "$validation_cmd" ]; then
        if ! $validation_cmd "$tmp_file"; then
            log_error "$name has invalid syntax. Aborting installation of this file."
            return 1
        fi
    fi

    mkdir -p "$(dirname "$dest_path")"
    mv "$tmp_file" "$dest_path"
    chmod "$perms" "$dest_path"
    chown "$owner" "$dest_path"
}

manage_optional_auth() {
    local app_path="$1"
    local tmp_extract_path="$2"
    local optional_files="user_files/authini.inc user_files/authusers.inc"

    printf "${C_YELLOW}Supermon can use local files for authentication. This is optional.\n"
    printf "This feature uses the files: 'authini.inc' and 'authusers.inc'.\n"
    printf "Do you want to enable/install local file authentication? (Y/n): ${C_RESET}"
    read -r response
    case "$response" in
        [nN]|[nN][oO])
            for file in $optional_files; do rm -f "${app_path}/${file}"; done
            ;;
        *)
            for file in $optional_files; do
                if [ ! -e "${app_path}/${file}" ]; then
                    cp "${tmp_extract_path}/${file}" "${app_path}/${file}"
                fi
            done
            ;;
    esac
}

install_application() {
    local app_path="${DEST_DIR}/${EXTRACTED_DIR}"
    local archive_path="${TMP_DIR}/${APP_VERSION}.tar.xz"
    local tmp_extract_path="${TMP_DIR}/${EXTRACTED_DIR}"
    local preserve_files=" .htaccess .htpasswd supermon-ng.css user_files/admin-controlpanel.ini user_files/admin-favorites.ini user_files/allmon.ini user_files/authini.inc user_files/authusers.inc user_files/authusers.inc.backup user_files/background.jpg user_files/controlpanel.ini user_files/cyborg_hamradio.png user_files/favorites.ini user_files/global.inc user_files/privatenodes.txt user_files/set_password.sh user_files/sbin/node_info.ini user_files/Xauthini.inc user_files/Xauthusers.inc user_files/Xcntrlini.inc user_files/Xcntrlnolog.ini user_files/Xfavini.inc user_files/Xfavnolog.ini user_files/Xnolog.ini "

    curl --fail -sSL "$DOWNLOAD_URL" -o "$archive_path" || { log_error "Download failed."; return 1; }
    verify_checksum "$archive_path" "$EXPECTED_ARCHIVE_CHECKSUM" || return 1
    tar -xaf "$archive_path" -C "$TMP_DIR" || { log_error "Extraction failed."; return 1; }

    if [ -d "$app_path" ]; then
        log_warning "An existing Supermon-NG installation was found."
        printf "${C_YELLOW}An existing installation was found. Update will protect user files but replace core application files. Proceed? (y/N): ${C_RESET}"
        read -r response
        case "$response" in
            [yY][eE][sS]|[yY])
                log_warning "----------------- IMPORTANT NOTICE: CSS FILE -----------------"
                log_warning "Your existing 'supermon-ng.css' will NOT be overwritten."
                log_warning "ACTION REQUIRED: Manually compare your existing file with the new version from this release to ensure correct display."
                log_warning "Your file: '${app_path}/supermon-ng.css'"
                log_warning "New file:  '${tmp_extract_path}/supermon-ng.css'"

                rsync -a --delete --exclude='user_files/' --exclude='.htaccess' --exclude='.htpasswd' --exclude='supermon-ng.css' "${tmp_extract_path}/" "${app_path}/" >/dev/null 2>&1 || { log_error "rsync failed."; return 1; }
                mkdir -p "${app_path}/user_files/sbin"
                rsync -a --delete --exclude='node_info.ini' "${tmp_extract_path}/user_files/sbin/" "${app_path}/user_files/sbin/" >/dev/null 2>&1 || { log_error "sbin rsync failed."; return 1; }
                
                for file in $preserve_files; do
                    if [ ! -e "${app_path}/${file}" ] && [ -e "${tmp_extract_path}/${file}" ]; then
                        log_warning "Default file '$file' exists in new version but is not in your installation. No action taken."
                    fi
                done
                ;;
            *)
                log_error "Update cancelled."; return 1 ;;
        esac
    else
        tar -xaf "$archive_path" -C "$DEST_DIR" || { log_error "Extraction failed."; return 1; }
    fi

    manage_optional_auth "$app_path" "$tmp_extract_path"

    chown -R root:root "$app_path"
    for file in $preserve_files; do
        if [ -e "${app_path}/$file" ]; then
            chown -h "root:$WWW_GROUP" "${app_path}/$file"
        fi
    done
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
    printf '%s\n' \
        "0 3 * * * root ${DEST_DIR}/${EXTRACTED_DIR}/astdb.php cron" \
        "# */3 * * * * root ${DEST_DIR}/${EXTRACTED_DIR}/user_files/sbin/ast_node_status_update.py" \
        > "$CRON_FILE_PATH"
    chmod 0644 "$CRON_FILE_PATH"
    chown root:root "$CRON_FILE_PATH"
    log_warning "The 'ast_node_status_update.py' cron job is disabled by default in the new cron file."
}

configure_acl_on_dir() {
    local log_dir="$1" dir_purpose="$2"

    if ! command -v setfacl > /dev/null 2>&1; then
        log_error "'setfacl' command not found, cannot configure log permissions. Skipping."
        return 1
    fi

    if [ ! -d "$log_dir" ]; then
        log_warning "${dir_purpose} log directory '$log_dir' not found. Skipping ACL configuration."
        return 0
    fi

    local acl_set=$(getfacl -p "$log_dir" 2>/dev/null | grep -E "^group:${WWW_GROUP}:r-x$")
    local default_acl_set=$(getfacl -pd "$log_dir" 2>/dev/null | grep -E "^default:group:${WWW_GROUP}:r-x$")

    if [ -n "$acl_set" ] && [ -n "$default_acl_set" ]; then
        return 0
    fi

    if ! setfacl -R -m "g:${WWW_GROUP}:rX" "$log_dir" || ! setfacl -R -d -m "g:${WWW_GROUP}:rX" "$log_dir"; then
        log_error "Failed to set ACL on $log_dir."
        log_warning "This can happen if the filesystem does not support ACLs. You may need to remount it with the 'acl' option."
        return 1
    fi
}

main() {
    if [ "$(id -u)" -ne 0 ]; then log_error "This script must be run as root."; exit 1; fi
    
    TMP_DIR=$(mktemp -d)
    WARNINGS_FILE="${TMP_DIR}/warnings.log"
    touch "$WARNINGS_FILE"

    {
        install_system_dependencies
        if ! getent group "$WWW_GROUP" >/dev/null 2>&1; then log_error "Group '$WWW_GROUP' does not exist."; exit 1; fi
        mkdir -p "$DEST_DIR"
        
        install_application
        install_sudo_config
        install_editor_script
        install_cron_job
        
        configure_acl_on_dir "/var/log/apache2" "Apache"
        configure_acl_on_dir "$ASTERISK_LOG_DIR" "Asterisk"
    } || { 
        # On any error, display warnings that occurred up to that point before exiting
        if [ -s "$WARNINGS_FILE" ]; then
            printf "\n${C_YELLOW}--- !!! Warnings that occurred before the error !!! ---\n${C_RESET}" >&2
            cat "$WARNINGS_FILE" >&2
        fi
        exit 1
    }
    
    # Display all collected warnings at the very end of a successful run
    if [ -s "$WARNINGS_FILE" ]; then
        printf "\n${C_YELLOW}--- !!! Script finished with the following warnings !!! ---\n${C_RESET}" >&2
        cat "$WARNINGS_FILE" >&2
    fi

    log_success "Supermon-NG installation/update script finished successfully."
}

main
