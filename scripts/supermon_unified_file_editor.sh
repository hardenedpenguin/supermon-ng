#!/bin/bash

set -eo pipefail

declare -A WHITELISTED_FILES

# Supermon-ng files
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/allmon.ini"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/authini.inc"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/authusers.inc"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/controlpanel.ini"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/favini.inc"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/favorites.ini"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/privatenodes.txt"]="www-data:www-data:755:"
WHITELISTED_FILES["/var/www/html/supermon-ng/user_files/global.inc"]="www-data:www-data:755:"

# Asterisk files
WHITELISTED_FILES["/etc/asterisk/extensions.conf"]="asterisk:asterisk:644:asterisk -rx 'dialplan reload'"
WHITELISTED_FILES["/etc/asterisk/sip.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/iax.conf"]="asterisk:asterisk:644:asterisk -rx 'iax2 reload'"
WHITELISTED_FILES["/etc/asterisk/users.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/rpt.conf"]="asterisk:asterisk:644:asterisk -rx 'rpt restart'"
WHITELISTED_FILES["/etc/asterisk/dnsmgr.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/http.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/voter.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/manager.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/asterisk.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/modules.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/logger"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/usbradio.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/simpleusb.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/irlp.conf"]="asterisk:asterisk:644:"
WHITELISTED_FILES["/etc/asterisk/echolink.conf"]="asterisk:asterisk:644:"

# DvSwitch files
WHITELISTED_FILES["/opt/Analog_Bridge/Analog_Bridge.ini"]="root:root:644:"
WHITELISTED_FILES["/opt/MMDVM_Bridge/MMDVM_Bridge.ini"]="root:root:644:"
WHITELISTED_FILES["/opt/MMDVM_Bridge/DVSwitch.ini"]="root:root:644:"

# IRLP files
WHITELISTED_FILES["/home/irlp/scripts/irlp.crons"]="irlp:irlp:640:sudo -u irlp crontab /home/irlp/scripts/irlp.crons"
WHITELISTED_FILES["/home/irlp/noupdate/scripts/irlp.crons"]="irlp:irlp:640:sudo -u irlp crontab /home/irlp/noupdate/scripts/irlp.crons"
WHITELISTED_FILES["/home/irlp/custom/environment"]="irlp:irlp:640:"
WHITELISTED_FILES["/home/irlp/custom/custom_decode"]="irlp:irlp:640:"
WHITELISTED_FILES["/home/irlp/custom/custom.crons"]="irlp:irlp:640:sudo -u irlp crontab /home/irlp/custom/custom.crons"
WHITELISTED_FILES["/home/irlp/custom/timeoutvalue"]="irlp:irlp:640:"
WHITELISTED_FILES["/home/irlp/custom/lockout_list"]="irlp:irlp:640:"
WHITELISTED_FILES["/home/irlp/custom/timing"]="irlp:irlp:640:"

TARGET_FILE_ARG="$1"
TEMP_CONTENT_FILE=""

cleanup() {
    if [[ -n "$TEMP_CONTENT_FILE" && -f "$TEMP_CONTENT_FILE" ]]; then
        rm -f "$TEMP_CONTENT_FILE"
    fi
}
trap cleanup EXIT SIGINT SIGTERM

log_error() {
    echo "ERROR: $1" >&2
}

log_info() {
    echo "INFO: $1"
}

if [ "$#" -ne 1 ]; then
    log_error "Usage: $0 <target_filepath>"
    exit 1
fi

TARGET_FILE_CANONICAL=$(realpath -s "$TARGET_FILE_ARG")
if [ -z "$TARGET_FILE_CANONICAL" ]; then
    log_error "Could not determine canonical path for '$TARGET_FILE_ARG'."
    exit 1
fi

FILE_CONFIG_STRING="${WHITELISTED_FILES[$TARGET_FILE_CANONICAL]}"

if [ -z "$FILE_CONFIG_STRING" ]; then
    log_error "File '$TARGET_FILE_CANONICAL' is not whitelisted for editing by this script."
    exit 1
fi

IFS=':' read -r OWNER GROUP PERMISSIONS POST_EDIT_COMMAND <<< "$FILE_CONFIG_STRING"

if [ -z "$OWNER" ] || [ -z "$GROUP" ] || [ -z "$PERMISSIONS" ]; then
    log_error "Invalid configuration string for '$TARGET_FILE_CANONICAL' in the script's whitelist."
    exit 1
fi

TEMP_CONTENT_FILE=$(mktemp "/tmp/supermon_edit_content.XXXXXX")
if ! cat > "$TEMP_CONTENT_FILE"; then
    log_error "Failed to read new content from stdin into temporary file."
    exit 1
fi

if [ -e "$TARGET_FILE_CANONICAL" ]; then
    BACKUP_FILE="$TARGET_FILE_CANONICAL.bak_$(date +%Y%m%d%H%M%S)"
    if ! cp -a "$TARGET_FILE_CANONICAL" "$BACKUP_FILE"; then
        log_warn "Failed to create backup '$BACKUP_FILE'. Proceeding without backup."
    else
        log_info "Original file backed up to '$BACKUP_FILE'."
    fi
elif [ ! -d "$(dirname "$TARGET_FILE_CANONICAL")" ]; then
    log_error "Parent directory for '$TARGET_FILE_CANONICAL' does not exist."
    exit 1
fi

if ! mv "$TEMP_CONTENT_FILE" "$TARGET_FILE_CANONICAL"; then
    log_error "Failed to move temporary content to '$TARGET_FILE_CANONICAL'."
    exit 1
fi
TEMP_CONTENT_FILE=""

log_info "Successfully updated content of '$TARGET_FILE_CANONICAL'."

log_info "Setting ownership to '$OWNER:$GROUP' for '$TARGET_FILE_CANONICAL'."
if ! chown "$OWNER:$GROUP" "$TARGET_FILE_CANONICAL"; then
    log_error "Failed to set ownership for '$TARGET_FILE_CANONICAL'."
    exit 1
fi

log_info "Setting permissions to '$PERMISSIONS' for '$TARGET_FILE_CANONICAL'."
if ! chmod "$PERMISSIONS" "$TARGET_FILE_CANONICAL"; then
    log_error "Failed to set permissions for '$TARGET_FILE_CANONICAL'."
    exit 1
fi

if [ -n "$POST_EDIT_COMMAND" ] && [ "$POST_EDIT_COMMAND" != ":" ]; then
    log_info "Executing post-edit command: $POST_EDIT_COMMAND"
    if eval "$POST_EDIT_COMMAND"; then
        log_info "Post-edit command executed successfully."
    else
        log_error "Post-edit command failed with exit code $?."
    fi
fi

log_info "File edit process completed for '$TARGET_FILE_CANONICAL'."
exit 0